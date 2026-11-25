-- Patas & Cia
-- Banco de dados do setor de cadastro

USE patas_db;

-- Tabela de tutores

CREATE TABLE IF NOT EXISTS tutores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    cpf VARCHAR(14) NOT NULL UNIQUE,
    telefone VARCHAR(20) NOT NULL,
    email VARCHAR(150),
    cep VARCHAR(9),
    logradouro VARCHAR(150),
    numero VARCHAR(20),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado CHAR(2),
    complemento VARCHAR(150),
    observacoes TEXT,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

USE patas_db;

CREATE TABLE IF NOT EXISTS animais (

    id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    especie VARCHAR(50) NOT NULL,
    raca VARCHAR(100),
    sexo VARCHAR(20) NOT NULL,
    data_nascimento DATE,
    peso DECIMAL(6,2),
    cor VARCHAR(100),
    castrado VARCHAR(20),
    observacoes TEXT,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_animais_tutores
        FOREIGN KEY (tutor_id)
        REFERENCES tutores(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

);