-- Testes SQL de RF04, RF07, RF08 e RF11 para MySQL 8.0.16+.
-- Execute com: mysql -u usuario -p < estoque/testes-controlados-perdas-fracionamento.sql
-- Usa apenas patas_db_teste_controlados; nao altera patas_db.
CREATE DATABASE IF NOT EXISTS patas_db_teste_controlados;
USE patas_db_teste_controlados;

DROP TRIGGER IF EXISTS teste_bi_movimentacao_controlada;
DROP TRIGGER IF EXISTS teste_bu_historico_imutavel;
DROP TRIGGER IF EXISTS teste_bd_historico_imutavel;
DROP TABLE IF EXISTS teste_movimentacoes;
DROP TABLE IF EXISTS teste_lotes;
DROP TABLE IF EXISTS teste_produtos;
DROP PROCEDURE IF EXISTS registrar_baixa_fracionada_teste;
DROP PROCEDURE IF EXISTS testar_controlados_perdas_fracionamento;

CREATE TABLE teste_produtos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    classificacao ENUM('comum', 'vacina', 'controle_especial') NOT NULL DEFAULT 'comum',
    fracionamento_habilitado BOOLEAN NOT NULL DEFAULT FALSE,
    fator_ml DECIMAL(7,6) NULL,
    fator_dose DECIMAL(7,6) NULL,
    PRIMARY KEY (id),
    CONSTRAINT chk_teste_fator_ml CHECK (fator_ml IS NULL OR (fator_ml > 0 AND fator_ml <= 1)),
    CONSTRAINT chk_teste_fator_dose CHECK (fator_dose IS NULL OR (fator_dose > 0 AND fator_dose <= 1))
) ENGINE=InnoDB;

CREATE TABLE teste_lotes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    produto_id INT UNSIGNED NOT NULL,
    numero_lote VARCHAR(50) NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_teste_lote_produto FOREIGN KEY (produto_id) REFERENCES teste_produtos (id)
) ENGINE=InnoDB;

CREATE TABLE teste_movimentacoes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lote_id INT UNSIGNED NOT NULL,
    tipo ENUM('entrada', 'saida') NOT NULL,
    quantidade DECIMAL(24,9) UNSIGNED NOT NULL,
    motivo_saida ENUM('perda', 'descarte') NULL,
    justificativa VARCHAR(1000) NULL,
    responsavel_movimentacao VARCHAR(150) NULL,
    responsavel_tecnico VARCHAR(150) NULL,
    registro_responsavel VARCHAR(30) NULL,
    data_movimentacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_teste_quantidade CHECK (quantidade > 0),
    CONSTRAINT chk_teste_perda_descarte CHECK (
        motivo_saida IS NULL OR (
            tipo = 'saida' AND justificativa IS NOT NULL
            AND CHAR_LENGTH(TRIM(justificativa)) >= 15
            AND responsavel_movimentacao IS NOT NULL
            AND CHAR_LENGTH(TRIM(responsavel_movimentacao)) > 0
        )
    ),
    CONSTRAINT fk_teste_movimentacao_lote FOREIGN KEY (lote_id) REFERENCES teste_lotes (id)
) ENGINE=InnoDB;

DELIMITER $$

CREATE TRIGGER teste_bi_movimentacao_controlada
BEFORE INSERT ON teste_movimentacoes
FOR EACH ROW
BEGIN
    DECLARE classificacao_produto VARCHAR(30);
    DECLARE numero_lote VARCHAR(50);
    SELECT p.classificacao, l.numero_lote
    INTO classificacao_produto, numero_lote
    FROM teste_lotes l
    JOIN teste_produtos p ON p.id = l.produto_id
    WHERE l.id = NEW.lote_id;

    IF NEW.tipo = 'saida' AND classificacao_produto IN ('vacina', 'controle_especial')
        AND (CHAR_LENGTH(TRIM(COALESCE(numero_lote, ''))) = 0
            OR CHAR_LENGTH(TRIM(COALESCE(NEW.responsavel_tecnico, ''))) = 0
            OR CHAR_LENGTH(TRIM(COALESCE(NEW.registro_responsavel, ''))) = 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saida controlada exige lote e identificacao tecnica.';
    END IF;
END$$

CREATE TRIGGER teste_bu_historico_imutavel
BEFORE UPDATE ON teste_movimentacoes
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico de movimentacoes e imutavel.';
END$$

CREATE TRIGGER teste_bd_historico_imutavel
BEFORE DELETE ON teste_movimentacoes
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico de movimentacoes e imutavel.';
END$$

CREATE PROCEDURE registrar_baixa_fracionada_teste(
    IN p_lote_id INT UNSIGNED,
    IN p_quantidade DECIMAL(13,3),
    IN p_unidade VARCHAR(10)
)
BEGIN
    DECLARE v_habilitado BOOLEAN DEFAULT FALSE;
    DECLARE v_fator DECIMAL(7,6) DEFAULT NULL;
    DECLARE v_saldo DECIMAL(30,9) DEFAULT 0;
    DECLARE v_quantidade_estoque DECIMAL(24,9) DEFAULT 0;

    SELECT p.fracionamento_habilitado,
           CASE p_unidade
               WHEN 'ml' THEN p.fator_ml
               WHEN 'dose' THEN p.fator_dose
               WHEN 'unidade' THEN 1
               ELSE NULL
           END
    INTO v_habilitado, v_fator
    FROM teste_lotes l
    JOIN teste_produtos p ON p.id = l.produto_id
    WHERE l.id = p_lote_id;

    IF v_fator IS NULL OR v_fator <= 0 OR v_fator > 1
        OR (p_unidade <> 'unidade' AND NOT v_habilitado) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unidade fracionada nao configurada.';
    END IF;

    SET v_quantidade_estoque = p_quantidade * v_fator;
    SELECT COALESCE(SUM(CASE tipo WHEN 'entrada' THEN quantidade ELSE -quantidade END), 0)
    INTO v_saldo FROM teste_movimentacoes WHERE lote_id = p_lote_id;
    IF v_quantidade_estoque <= 0 OR v_quantidade_estoque > v_saldo THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saldo insuficiente para conversao fracionada.';
    END IF;

    INSERT INTO teste_movimentacoes (lote_id, tipo, quantidade)
    VALUES (p_lote_id, 'saida', v_quantidade_estoque);
    SELECT v_quantidade_estoque AS quantidade_em_unidades_padrao;
END$$

CREATE PROCEDURE testar_controlados_perdas_fracionamento()
BEGIN
    DECLARE rejeitada BOOLEAN DEFAULT FALSE;
    DECLARE registros_antes INT DEFAULT 0;
    DECLARE registros_depois INT DEFAULT 0;
    DECLARE quantidade_convertida DECIMAL(24,9) DEFAULT 0;
    DECLARE quantidade_historico DECIMAL(24,9) DEFAULT 0;

    INSERT INTO teste_produtos (id, nome, classificacao) VALUES
        (1, 'Medicamento controlado', 'controle_especial');
    INSERT INTO teste_produtos (id, nome, classificacao, fracionamento_habilitado, fator_ml, fator_dose) VALUES
        (2, 'Medicamento fracionavel', 'comum', TRUE, 0.100000, 0.250000);
    INSERT INTO teste_lotes (id, produto_id, numero_lote) VALUES
        (1, 1, 'CONT-001'),
        (2, 2, 'FRAC-001');
    INSERT INTO teste_movimentacoes (lote_id, tipo, quantidade) VALUES
        (1, 'entrada', 5),
        (2, 'entrada', 10);

    -- RF04: controlado nao pode sair sem responsavel e registro profissional.
    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_movimentacoes (lote_id, tipo, quantidade)
            VALUES (1, 'saida', 1);
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Aceitou saida controlada sem identificacao tecnica.'; END IF;

    INSERT INTO teste_movimentacoes
        (lote_id, tipo, quantidade, responsavel_tecnico, registro_responsavel)
    VALUES (1, 'saida', 1, 'Dra. Ana', 'CRMV-123');

    -- RF11: justificativa e responsavel sao obrigatorios para perda/descarte.
    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_movimentacoes
            (lote_id, tipo, quantidade, motivo_saida, justificativa, responsavel_movimentacao)
        VALUES (2, 'saida', 1, 'perda', 'curta', 'Operador');
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Aceitou justificativa curta para perda.'; END IF;

    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_movimentacoes
            (lote_id, tipo, quantidade, motivo_saida, justificativa, responsavel_movimentacao)
        VALUES (2, 'saida', 1, 'descarte', 'Justificativa valida com mais de quinze caracteres', '');
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Aceitou descarte sem responsavel.'; END IF;

    INSERT INTO teste_movimentacoes
        (lote_id, tipo, quantidade, motivo_saida, justificativa, responsavel_movimentacao)
    VALUES (2, 'saida', 1, 'descarte', 'Justificativa valida com mais de quinze caracteres', 'Operador');

    -- RF07: tentativas diretas de alterar ou apagar um evento devem ser recusadas.
    SELECT COUNT(*) INTO registros_antes FROM teste_movimentacoes;
    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        UPDATE teste_movimentacoes SET justificativa = 'Texto alterado' WHERE id = 4;
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico permitiu UPDATE.'; END IF;

    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        DELETE FROM teste_movimentacoes WHERE id = 4;
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico permitiu DELETE.'; END IF;
    SELECT COUNT(*) INTO registros_depois FROM teste_movimentacoes;
    IF registros_depois <> registros_antes THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historico foi alterado.'; END IF;

    -- RF08: 2.5 mL * 0.1 e 2 doses * 0.25 devem baixar 0.25 e 0.5 unidades padrao.
    CALL registrar_baixa_fracionada_teste(2, 2.500, 'ml');
    CALL registrar_baixa_fracionada_teste(2, 2.000, 'dose');
    SELECT COALESCE(SUM(quantidade), 0) INTO quantidade_historico
    FROM teste_movimentacoes
    WHERE lote_id = 2 AND tipo = 'saida' AND motivo_saida IS NULL;
    SET quantidade_convertida = 1 + 0.250000000 + 0.500000000;
    IF quantidade_historico <> quantidade_convertida THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conversao fracionada incorreta no historico.';
    END IF;

    SELECT 'OK: responsavel tecnico, perda/descarte, historico imutavel e fracionamento' AS resultado;
END$$

DELIMITER ;

CALL testar_controlados_perdas_fracionamento();

DROP PROCEDURE testar_controlados_perdas_fracionamento;
DROP PROCEDURE registrar_baixa_fracionada_teste;
