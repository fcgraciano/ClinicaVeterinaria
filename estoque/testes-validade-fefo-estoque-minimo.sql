-- Testes de validade, prioridade FEFO e estoque minimo para MySQL 8.0+.
-- Execute com: mysql -u usuario -p < estoque/testes-validade-fefo-estoque-minimo.sql
-- Usa exclusivamente o banco patas_db_teste_validade_fefo; nao altera patas_db.
CREATE DATABASE IF NOT EXISTS patas_db_teste_validade_fefo;
USE patas_db_teste_validade_fefo;

DROP TRIGGER IF EXISTS teste_bi_validar_saida;
DROP TABLE IF EXISTS teste_movimentacoes;
DROP TABLE IF EXISTS teste_lotes;
DROP TABLE IF EXISTS teste_produtos;
DROP PROCEDURE IF EXISTS testar_validade_fefo_estoque_minimo;

CREATE TABLE teste_produtos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    estoque_minimo DECIMAL(24,9) UNSIGNED NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE teste_lotes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    produto_id INT UNSIGNED NOT NULL,
    numero_lote VARCHAR(50) NOT NULL,
    data_validade DATE NULL,
    status ENUM('ativo', 'bloqueado', 'vencido') NOT NULL DEFAULT 'ativo',
    PRIMARY KEY (id),
    CONSTRAINT fk_teste_lote_produto FOREIGN KEY (produto_id) REFERENCES teste_produtos (id)
) ENGINE=InnoDB;

CREATE TABLE teste_movimentacoes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lote_id INT UNSIGNED NOT NULL,
    tipo ENUM('entrada', 'saida') NOT NULL,
    quantidade DECIMAL(24,9) UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT chk_teste_movimentacao_quantidade CHECK (quantidade > 0),
    CONSTRAINT fk_teste_movimentacao_lote FOREIGN KEY (lote_id) REFERENCES teste_lotes (id)
) ENGINE=InnoDB;

DELIMITER $$

-- Espelha o bloqueio de saida usado pela tela: status vencido ou validade anterior a hoje.
CREATE TRIGGER teste_bi_validar_saida
BEFORE INSERT ON teste_movimentacoes
FOR EACH ROW
BEGIN
    DECLARE status_lote VARCHAR(20);
    DECLARE validade_lote DATE;
    IF NEW.tipo = 'saida' THEN
        SELECT status, data_validade INTO status_lote, validade_lote
        FROM teste_lotes WHERE id = NEW.lote_id;
        IF status_lote = 'bloqueado' OR status_lote = 'vencido'
            OR (validade_lote IS NOT NULL AND validade_lote < CURRENT_DATE()) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Lote bloqueado ou vencido.';
        END IF;
    END IF;
END$$

CREATE PROCEDURE testar_validade_fefo_estoque_minimo()
BEGIN
    DECLARE rejeitada BOOLEAN DEFAULT FALSE;
    DECLARE primeiro_lote INT UNSIGNED DEFAULT NULL;
    DECLARE saldo_igual INT DEFAULT 0;
    DECLARE saldo_abaixo INT DEFAULT 0;
    DECLARE saldo_acima INT DEFAULT 0;

    INSERT INTO teste_produtos (id, nome, estoque_minimo) VALUES
        (1, 'FEFO no limite', 5),
        (2, 'Acima do minimo', 5),
        (3, 'Abaixo do minimo', 5),
        (4, 'Lotes vencidos', 20);

    INSERT INTO teste_lotes (id, produto_id, numero_lote, data_validade, status) VALUES
        (1, 1, 'MAIS-TARDE', DATE_ADD(CURRENT_DATE(), INTERVAL 10 DAY), 'ativo'),
        (2, 1, 'MAIS-PROXIMO', DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), 'ativo'),
        (3, 2, 'ACIMA', DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY), 'ativo'),
        (4, 3, 'ABAIXO', DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY), 'ativo'),
        (5, 4, 'STATUS-VENCIDO', DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY), 'vencido'),
        (6, 4, 'DATA-VENCIDA', DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY), 'ativo');

    INSERT INTO teste_movimentacoes (lote_id, tipo, quantidade) VALUES
        (1, 'entrada', 4),
        (2, 'entrada', 1),
        (3, 'entrada', 6),
        (4, 'entrada', 4),
        (5, 'entrada', 2),
        (6, 'entrada', 2);

    -- FEFO: entre lotes ativos com saldo e validade, escolher a menor data primeiro.
    SELECT l.id INTO primeiro_lote
    FROM teste_lotes l
    JOIN (
        SELECT lote_id, SUM(CASE tipo WHEN 'entrada' THEN quantidade ELSE -quantidade END) AS saldo
        FROM teste_movimentacoes
        GROUP BY lote_id
        HAVING saldo > 0
    ) m ON m.lote_id = l.id
    WHERE l.status = 'ativo'
      AND (l.data_validade IS NULL OR l.data_validade >= CURRENT_DATE())
      AND l.produto_id = 1
    ORDER BY l.data_validade IS NULL, l.data_validade, l.id
    LIMIT 1;
    IF primeiro_lote <> 2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FEFO nao priorizou o vencimento mais proximo.'; END IF;

    -- Saida dos dois casos vencidos deve ser recusada, mesmo quando o lote ainda consta ativo.
    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_movimentacoes (lote_id, tipo, quantidade) VALUES (5, 'saida', 1);
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saida de lote com status vencido foi aceita.'; END IF;

    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_movimentacoes (lote_id, tipo, quantidade) VALUES (6, 'saida', 1);
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saida de lote com data vencida foi aceita.'; END IF;

    -- Reposicao: alerta deve incluir saldo igual ou menor que o minimo e excluir saldo maior.
    SELECT COUNT(*) INTO saldo_igual
    FROM (
        SELECT p.id, p.estoque_minimo,
               COALESCE(SUM(CASE m.tipo WHEN 'entrada' THEN m.quantidade ELSE -m.quantidade END), 0) AS saldo_total
        FROM teste_produtos p
        LEFT JOIN teste_lotes l ON l.produto_id = p.id
        LEFT JOIN teste_movimentacoes m ON m.lote_id = l.id
        WHERE p.id = 1
        GROUP BY p.id, p.estoque_minimo
        HAVING saldo_total <= p.estoque_minimo AND saldo_total = p.estoque_minimo
    ) resultado;
    IF saldo_igual <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Alerta nao incluiu saldo igual ao minimo.'; END IF;

    SELECT COUNT(*) INTO saldo_abaixo
    FROM (
        SELECT p.id, p.estoque_minimo,
               COALESCE(SUM(CASE m.tipo WHEN 'entrada' THEN m.quantidade ELSE -m.quantidade END), 0) AS saldo_total
        FROM teste_produtos p
        LEFT JOIN teste_lotes l ON l.produto_id = p.id
        LEFT JOIN teste_movimentacoes m ON m.lote_id = l.id
        WHERE p.id = 3
        GROUP BY p.id, p.estoque_minimo
        HAVING saldo_total <= p.estoque_minimo AND saldo_total < p.estoque_minimo
    ) resultado;
    IF saldo_abaixo <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Alerta nao incluiu saldo abaixo do minimo.'; END IF;

    SELECT COUNT(*) INTO saldo_acima
    FROM (
        SELECT p.id, p.estoque_minimo,
               COALESCE(SUM(CASE m.tipo WHEN 'entrada' THEN m.quantidade ELSE -m.quantidade END), 0) AS saldo_total
        FROM teste_produtos p
        LEFT JOIN teste_lotes l ON l.produto_id = p.id
        LEFT JOIN teste_movimentacoes m ON m.lote_id = l.id
        WHERE p.id = 2
        GROUP BY p.id, p.estoque_minimo
        HAVING saldo_total <= p.estoque_minimo
    ) resultado;
    IF saldo_acima <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Alerta incluiu saldo acima do minimo.'; END IF;

    SELECT 'OK: bloqueio de vencidos, prioridade FEFO e alerta de estoque minimo' AS resultado;
END$$

DELIMITER ;

CALL testar_validade_fefo_estoque_minimo();

DROP PROCEDURE testar_validade_fefo_estoque_minimo;
