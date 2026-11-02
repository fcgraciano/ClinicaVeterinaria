-- Rode este arquivo PRIMEIRO no phpMyAdmin.
-- Depois cada grupo cria o seu arquivo aqui na pasta banco:
--   01_cadastro.sql, 02_agendamento.sql, 03_prontuario.sql ...

CREATE DATABASE IF NOT EXISTS patas_db
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;


USE patas_db;

CREATE TABLE IF NOT EXISTS vacinas(
    Vacinaid int AUTO_INCREMENT NOT NULL PRIMARY KEY,
    DataValidade date NOT NULL,
    QuantidadeLote int,
    TipoVacina varchar(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS animais(
    Animalid int AUTO_INCREMENT NOT NULL PRIMARY KEY,
    Nome varchar(100) NOT NULL,
    Especie varchar(100) NOT NULL,
    Raca varchar(100) NOT NULL,
    Sexo varchar(10) NOT NULL,
    DataNascimento date NOT NULL,
    Peso decimal(5,2) NOT NULL,
    Altura decimal(5,2) NOT NULL,
    Cor varchar(50) NOT NULL,
    Observacoes text
);

CREATE TABLE IF NOT EXISTS vacinas_aplicacadas(
    Aplicacaoid int AUTO_INCREMENT NOT NULL PRIMARY KEY,
    DataAplicacao date NOT NULL,
    Retorno date,
    dose int NOT NULL,
    TipoVacina varchar(100) NOT NULL,
    Animalid int NOT NULL,
    FOREIGN KEY (Animalid) REFERENCES animais(Animalid)
);
