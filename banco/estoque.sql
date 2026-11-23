-- Execute depois de 00_criar_banco.sql.
USE patas_db;

CREATE TABLE IF NOT EXISTS categorias (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categorias_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fornecedores (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS produtos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    categoria_id INT UNSIGNED NOT NULL,
    codigo_barras VARCHAR(50) NOT NULL,
    estoque_minimo INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_produtos_codigo_barras (codigo_barras),
    CONSTRAINT fk_produtos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS produto_fornecedor (
    produto_id INT UNSIGNED NOT NULL,
    fornecedor_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (produto_id, fornecedor_id),
    CONSTRAINT fk_produto_fornecedor_produto FOREIGN KEY (produto_id) REFERENCES produtos (id),
    CONSTRAINT fk_produto_fornecedor_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lotes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    produto_id INT UNSIGNED NOT NULL,
    fornecedor_id INT UNSIGNED NOT NULL,
    numero_lote VARCHAR(50) NOT NULL,
    data_validade DATE NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lotes_produto_fornecedor_numero (produto_id, fornecedor_id, numero_lote),
    CONSTRAINT fk_lotes_produto_fornecedor FOREIGN KEY (produto_id, fornecedor_id)
        REFERENCES produto_fornecedor (produto_id, fornecedor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movimentacoes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lote_id INT UNSIGNED NOT NULL,
    tipo ENUM('entrada', 'saida') NOT NULL,
    quantidade INT UNSIGNED NOT NULL,
    data_movimentacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_movimentacoes_quantidade CHECK (quantidade > 0),
    CONSTRAINT fk_movimentacoes_lote FOREIGN KEY (lote_id) REFERENCES lotes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acrescenta o status tambem em bancos que ja possuem lotes.
SET @ddl_status_lotes = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lotes' AND COLUMN_NAME = 'status'
    ),
    'DO 0',
    'ALTER TABLE lotes ADD COLUMN status ENUM(''ativo'', ''bloqueado'', ''vencido'') NOT NULL DEFAULT ''ativo'''
);
PREPARE atualizar_status_lotes FROM @ddl_status_lotes;
EXECUTE atualizar_status_lotes;
DEALLOCATE PREPARE atualizar_status_lotes;

-- Classifique vacinas e medicamentos controlados ja cadastrados antes de registrar saidas.
SET @ddl_classificacao_produtos = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos' AND COLUMN_NAME = 'classificacao'
    ),
    'DO 0',
    'ALTER TABLE produtos ADD COLUMN classificacao
        ENUM(''comum'', ''vacina'', ''controle_especial'') NOT NULL DEFAULT ''comum'''
);
PREPARE atualizar_classificacao_produtos FROM @ddl_classificacao_produtos;
EXECUTE atualizar_classificacao_produtos;
DEALLOCATE PREPARE atualizar_classificacao_produtos;

SET @ddl_responsavel_movimentacoes = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimentacoes' AND COLUMN_NAME = 'responsavel_tecnico'
    ),
    'DO 0',
    'ALTER TABLE movimentacoes
        ADD COLUMN responsavel_tecnico VARCHAR(150) NULL,
        ADD COLUMN registro_responsavel VARCHAR(30) NULL'
);
PREPARE atualizar_responsavel_movimentacoes FROM @ddl_responsavel_movimentacoes;
EXECUTE atualizar_responsavel_movimentacoes;
DEALLOCATE PREPARE atualizar_responsavel_movimentacoes;

-- Fatores em unidades de estoque por 1 mL ou 1 dose.
-- Exemplo: uma unidade com 10 mL usa fator_ml = 0.100000.
SET @ddl_fracionamento = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos' AND COLUMN_NAME = 'fracionamento_habilitado'
    ),
    'DO 0',
    'ALTER TABLE produtos
        ADD COLUMN fracionamento_habilitado BOOLEAN NOT NULL DEFAULT FALSE,
        ADD COLUMN fator_ml DECIMAL(7,6) NULL,
        ADD COLUMN fator_dose DECIMAL(7,6) NULL,
        ADD CONSTRAINT chk_produtos_fator_ml CHECK (fator_ml > 0 AND fator_ml <= 1),
        ADD CONSTRAINT chk_produtos_fator_dose CHECK (fator_dose > 0 AND fator_dose <= 1)'
);
PREPARE atualizar_fracionamento FROM @ddl_fracionamento;
EXECUTE atualizar_fracionamento;
DEALLOCATE PREPARE atualizar_fracionamento;

SET @ddl_quantidade_decimal = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimentacoes'
            AND COLUMN_NAME = 'quantidade' AND DATA_TYPE = 'decimal'
            AND NUMERIC_PRECISION = 24 AND NUMERIC_SCALE = 9
    ),
    'DO 0',
    'ALTER TABLE movimentacoes MODIFY COLUMN quantidade DECIMAL(24,9) UNSIGNED NOT NULL'
);
PREPARE atualizar_quantidade_decimal FROM @ddl_quantidade_decimal;
EXECUTE atualizar_quantidade_decimal;
DEALLOCATE PREPARE atualizar_quantidade_decimal;
