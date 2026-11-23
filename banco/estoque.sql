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
