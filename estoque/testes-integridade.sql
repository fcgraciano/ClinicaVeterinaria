-- Testes de integridade do estoque para MySQL 8.0.16+ (CHECK efetivo).
-- Execute com: mysql -u usuario -p < estoque/testes-integridade.sql
-- Usa exclusivamente o banco patas_db_teste_integridade; nao altera patas_db.
CREATE DATABASE IF NOT EXISTS patas_db_teste_integridade;
USE patas_db_teste_integridade;

DROP TRIGGER IF EXISTS teste_bi_movimentacoes_saldo;
DROP TABLE IF EXISTS teste_movimentacoes;
DROP TABLE IF EXISTS teste_lotes;
DROP TABLE IF EXISTS teste_produto_fornecedor;
DROP TABLE IF EXISTS teste_produtos;
DROP TABLE IF EXISTS teste_fornecedores;
DROP TABLE IF EXISTS teste_categorias;
DROP PROCEDURE IF EXISTS testar_integridade_estoque;

CREATE TABLE teste_categorias (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teste_categorias_nome (nome)
) ENGINE=InnoDB;

CREATE TABLE teste_fornecedores (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE teste_produtos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    categoria_id INT UNSIGNED NOT NULL,
    codigo_barras VARCHAR(50) NOT NULL,
    estoque_minimo INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teste_produtos_codigo_barras (codigo_barras),
    CONSTRAINT fk_teste_produtos_categoria FOREIGN KEY (categoria_id)
        REFERENCES teste_categorias (id)
) ENGINE=InnoDB;

CREATE TABLE teste_produto_fornecedor (
    produto_id INT UNSIGNED NOT NULL,
    fornecedor_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (produto_id, fornecedor_id),
    CONSTRAINT fk_teste_pf_produto FOREIGN KEY (produto_id)
        REFERENCES teste_produtos (id),
    CONSTRAINT fk_teste_pf_fornecedor FOREIGN KEY (fornecedor_id)
        REFERENCES teste_fornecedores (id)
) ENGINE=InnoDB;

CREATE TABLE teste_lotes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    produto_id INT UNSIGNED NOT NULL,
    fornecedor_id INT UNSIGNED NOT NULL,
    numero_lote VARCHAR(50) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teste_lotes_produto_fornecedor_numero (produto_id, fornecedor_id, numero_lote),
    CONSTRAINT fk_teste_lotes_produto_fornecedor FOREIGN KEY (produto_id, fornecedor_id)
        REFERENCES teste_produto_fornecedor (produto_id, fornecedor_id)
) ENGINE=InnoDB;

CREATE TABLE teste_movimentacoes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lote_id INT UNSIGNED NOT NULL,
    tipo ENUM('entrada', 'saida') NOT NULL,
    quantidade DECIMAL(24,9) UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT chk_teste_movimentacoes_quantidade CHECK (quantidade > 0),
    CONSTRAINT fk_teste_movimentacoes_lote FOREIGN KEY (lote_id)
        REFERENCES teste_lotes (id)
) ENGINE=InnoDB;

DELIMITER $$

CREATE TRIGGER teste_bi_movimentacoes_saldo
BEFORE INSERT ON teste_movimentacoes
FOR EACH ROW
BEGIN
    DECLARE saldo_atual DECIMAL(30,9) DEFAULT 0;
    IF NEW.tipo = 'saida' THEN
        SELECT COALESCE(SUM(CASE tipo
            WHEN 'entrada' THEN quantidade
            WHEN 'saida' THEN -quantidade
        END), 0)
        INTO saldo_atual
        FROM teste_movimentacoes
        WHERE lote_id = NEW.lote_id;

        IF saldo_atual < NEW.quantidade THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saldo insuficiente no lote.';
        END IF;
    END IF;
END$$

CREATE PROCEDURE testar_integridade_estoque()
BEGIN
    DECLARE rejeitada BOOLEAN DEFAULT FALSE;
    DECLARE saldo_final DECIMAL(30,9) DEFAULT 0;

    INSERT INTO teste_categorias (id, nome) VALUES (1, 'Medicamentos');
    INSERT INTO teste_fornecedores (id, nome) VALUES (1, 'Fornecedor A'), (2, 'Fornecedor B');
    INSERT INTO teste_produtos (id, nome, categoria_id, codigo_barras, estoque_minimo)
        VALUES (1, 'Produto de teste', 1, 'COD-001', 0);
    INSERT INTO teste_produto_fornecedor (produto_id, fornecedor_id) VALUES (1, 1);
    INSERT INTO teste_lotes (id, produto_id, fornecedor_id, numero_lote)
        VALUES (1, 1, 1, 'LOTE-001');
    INSERT INTO teste_movimentacoes (lote_id, tipo, quantidade) VALUES (1, 'entrada', 5);

    -- PK duplicada.
    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_categorias (id, nome) VALUES (1, 'Outra categoria');
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falhou teste de PK.'; END IF;

    -- UNIQUE de categoria e codigo de barras.
    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_categorias (nome) VALUES ('Medicamentos');
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falhou teste de UNIQUE em categoria.'; END IF;

    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_produtos (nome, categoria_id, codigo_barras, estoque_minimo)
            VALUES ('Duplicado', 1, 'COD-001', 0);
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falhou teste de UNIQUE em produto.'; END IF;

    -- FK de categoria e estoque minimo nao negativo.
    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_produtos (nome, categoria_id, codigo_barras, estoque_minimo)
            VALUES ('Categoria ausente', 99, 'COD-099', 0);
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falhou teste de FK categoria.'; END IF;

    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_produtos (nome, categoria_id, codigo_barras, estoque_minimo)
            VALUES ('Minimo negativo', 1, 'COD-098', -1);
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falhou teste de estoque minimo negativo.'; END IF;

    -- PK composta e FK do vinculo produto/fornecedor usado pelo lote.
    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_produto_fornecedor (produto_id, fornecedor_id) VALUES (1, 1);
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falhou teste de PK composta.'; END IF;

    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_produto_fornecedor (produto_id, fornecedor_id) VALUES (1, 99);
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falhou teste de FK fornecedor.'; END IF;

    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_produto_fornecedor (produto_id, fornecedor_id) VALUES (99, 1);
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falhou teste de FK produto no vinculo.'; END IF;

    -- UNIQUE do lote e validacao do par produto/fornecedor.
    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_lotes (produto_id, fornecedor_id, numero_lote) VALUES (1, 1, 'LOTE-001');
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falhou teste de UNIQUE em lote.'; END IF;

    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_lotes (produto_id, fornecedor_id, numero_lote) VALUES (1, 2, 'LOTE-ERRADO');
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falhou teste de vinculo produto/lote.'; END IF;

    -- FK do lote, CHECK/UNSIGNED da quantidade e saldo insuficiente.
    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_movimentacoes (lote_id, tipo, quantidade) VALUES (99, 'entrada', 1);
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falhou teste de FK lote.'; END IF;

    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_movimentacoes (lote_id, tipo, quantidade) VALUES (1, 'entrada', 0);
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falhou teste de CHECK quantidade zero.'; END IF;

    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_movimentacoes (lote_id, tipo, quantidade) VALUES (1, 'entrada', -1);
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falhou teste de quantidade negativa.'; END IF;

    SET rejeitada = FALSE;
    INSERT INTO teste_movimentacoes (lote_id, tipo, quantidade) VALUES (1, 'saida', 2);
    SET rejeitada = FALSE;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejeitada = TRUE;
        INSERT INTO teste_movimentacoes (lote_id, tipo, quantidade) VALUES (1, 'saida', 4);
    END;
    IF NOT rejeitada THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falhou teste de saldo negativo.'; END IF;

    SELECT COALESCE(SUM(CASE tipo WHEN 'entrada' THEN quantidade ELSE -quantidade END), 0)
        INTO saldo_final
        FROM teste_movimentacoes
        WHERE lote_id = 1;
    IF saldo_final <> 3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saldo incorreto apos rejeicao.'; END IF;

    SELECT 'OK: PK, FK, UNIQUE, CHECK, vinculo produto/lote, quantidade e saldo' AS resultado;
END$$

DELIMITER ;

CALL testar_integridade_estoque();

DROP PROCEDURE testar_integridade_estoque;
