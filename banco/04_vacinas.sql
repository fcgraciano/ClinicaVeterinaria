-- ============================================================
--  MÓDULO: VACINAS
--  Arquivo: banco/04_vacinas.sql
--  Rode no phpMyAdmin, dentro do banco patas_db, DEPOIS do
--  00_criar_banco.sql.
--
--  O que este arquivo cria:
--    1. Tabelas de apoio (tutores/animais/vacinas) - somente se
--       ainda não existirem (outros módulos podem criar antes).
--    2. Tabelas do módulo: vacinas_aplicadas e notificacoes.
--    3. View  vw_vacinas_a_vencer  (a "query" pronta).
--    4. Procedure sp_gerar_notificacoes_vacinas (rotina).
--    5. Event  ev_gerar_notificacoes_vacinas (agendamento diário).
--
--  INTEGRAÇÃO COM OS OUTROS MÓDULOS:
--  O banco é compartilhado entre os grupos. Na prática, as
--  tabelas "animais" e "vacinas" já existem com estas chaves:
--      animais(Animalid INT, Nome, Especie, ...)
--      vacinas(Vacinaid INT, TipoVacina, DataValidade, ...)
--  Por isso vacinas_aplicadas usa animal_id INT e vacina_id INT
--  (sem UNSIGNED), apontando para Animalid e Vacinaid.
--
--  O vínculo do animal com o tutor é a coluna animais.tutor_id.
--  Se ela ainda não existir, este script a cria (nullable) e
--  adiciona a chave estrangeira para tutores. Sem tutor_id não
--  é possível descobrir o dono e nenhuma notificação é gerada.
--
--  O Event apenas CRIA as notificações na tabela notificacoes.
--  O envio dos e-mails é feito por vacinas/notificar.php, que
--  deve rodar logo depois (cron / Agendador de Tarefas).
-- ============================================================

USE patas_db;

-- O Event abaixo só pode ser criado com o agendador ligado.
-- No XAMPP/MariaDB ele vem desligado por padrão.
-- Para continuar ligado depois de reiniciar o XAMPP, adicione
--   event_scheduler=ON
-- na seção [mysqld] do arquivo C:\xampp\mysql\bin\my.ini.
-- Se o usuário do banco não tiver permissão para isso, comente
-- esta linha e o bloco do EVENT (seção 5); nesse caso use o
-- script vacinas/notificar.php no Agendador de Tarefas.
SET GLOBAL event_scheduler = ON;

-- ------------------------------------------------------------
-- 1) TABELAS COMPARTILHADAS
--    CREATE TABLE IF NOT EXISTS não altera tabelas que já
--    existem; abaixo também garantimos as colunas que o módulo
--    de vacinas precisa (tutor_id e ativo em animais).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tutores (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome      VARCHAR(120) NOT NULL,
    email     VARCHAR(150) NULL,
    telefone  VARCHAR(30)  NULL,
    ativo     TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estrutura usada pelo módulo de Cadastro/Prontuário.
-- Inclui tutor_id e ativo, que são o que o módulo de vacinas usa.
CREATE TABLE IF NOT EXISTS animais (
    Animalid       INT NOT NULL AUTO_INCREMENT,
    Nome           VARCHAR(100) NOT NULL,
    Especie        VARCHAR(100) NOT NULL,
    Raca           VARCHAR(100) NOT NULL,
    Sexo           VARCHAR(10)  NOT NULL,
    DataNascimento DATE NOT NULL,
    Peso           DECIMAL(5,2) NOT NULL,
    Altura         DECIMAL(5,2) NOT NULL,
    Cor            VARCHAR(50)  NOT NULL,
    Observacoes    TEXT NULL,
    tutor_id       INT UNSIGNED NULL,
    ativo          TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (Animalid),
    KEY idx_animais_tutor (tutor_id),
    CONSTRAINT fk_animais_tutor
        FOREIGN KEY (tutor_id) REFERENCES tutores (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estrutura usada pelo módulo de Estoque (lote de vacinas).
-- TipoVacina é o nome que aparece nas notificações.
CREATE TABLE IF NOT EXISTS vacinas (
    Vacinaid       INT NOT NULL AUTO_INCREMENT,
    DataValidade   DATE NOT NULL,
    QuantidadeLote INT NULL,
    Valor          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    TipoVacina     VARCHAR(100) NOT NULL,
    PRIMARY KEY (Vacinaid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Se as tabelas já existiam sem estas colunas, adiciona agora.
-- ADD COLUMN IF NOT EXISTS é suportado pelo MariaDB 10.x (XAMPP).
ALTER TABLE animais
    ADD COLUMN IF NOT EXISTS tutor_id INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS ativo TINYINT(1) NOT NULL DEFAULT 1;

-- Adiciona a FK de tutor_id apenas se ela ainda não existir
-- (assim o script pode ser executado mais de uma vez).
SET @tem_fk_tutor := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'animais'
      AND constraint_name = 'fk_animais_tutor'
);
SET @sql_fk_tutor := IF(
    @tem_fk_tutor = 0,
    'ALTER TABLE animais ADD CONSTRAINT fk_animais_tutor
        FOREIGN KEY (tutor_id) REFERENCES tutores (id)
        ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_fk_tutor FROM @sql_fk_tutor;
EXECUTE stmt_fk_tutor;
DEALLOCATE PREPARE stmt_fk_tutor;

-- ------------------------------------------------------------
-- 2) TABELAS DO MÓDULO DE VACINAS
-- ------------------------------------------------------------

-- Cada aplicação registrada. data_proxima_dose é a data em que
-- o reforço/revacinação deve acontecer.
CREATE TABLE IF NOT EXISTS vacinas_aplicadas (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id         INT NOT NULL,
    vacina_id         INT NOT NULL,
    data_aplicacao    DATE NOT NULL,
    data_proxima_dose DATE NULL,
    lote              VARCHAR(50)  NULL,
    veterinario       VARCHAR(120) NULL,
    observacoes       TEXT         NULL,
    status            ENUM('ativa','cancelada') NOT NULL DEFAULT 'ativa',
    criado_em         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_va_animal (animal_id),
    KEY idx_va_proxima_dose (data_proxima_dose, status),
    CONSTRAINT fk_va_animal
        FOREIGN KEY (animal_id) REFERENCES animais (Animalid)
        ON DELETE CASCADE,
    CONSTRAINT fk_va_vacina
        FOREIGN KEY (vacina_id) REFERENCES vacinas (Vacinaid)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notificações geradas para os tutores.
-- A chave única evita que a rotina diária crie o mesmo lembrete
-- duas vezes para a mesma dose/canal.
CREATE TABLE IF NOT EXISTS notificacoes (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id          INT NOT NULL,
    tutor_id           INT UNSIGNED NOT NULL,
    vacina_aplicada_id INT UNSIGNED NOT NULL,
    tipo               VARCHAR(40) NOT NULL DEFAULT 'lembrete_vacina',
    canal              ENUM('email','whatsapp','sms','sistema') NOT NULL DEFAULT 'email',
    mensagem           VARCHAR(500) NOT NULL,
    status             ENUM('pendente','enviada','erro','cancelada') NOT NULL DEFAULT 'pendente',
    data_agendada      DATE NOT NULL,
    enviada_em         DATETIME NULL,
    erro               TEXT NULL,
    criado_em          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notificacao_dose (vacina_aplicada_id, tipo, canal),
    KEY idx_notif_status (status, data_agendada),
    KEY idx_notif_tutor (tutor_id),
    CONSTRAINT fk_notif_animal
        FOREIGN KEY (animal_id) REFERENCES animais (Animalid)
        ON DELETE CASCADE,
    CONSTRAINT fk_notif_tutor
        FOREIGN KEY (tutor_id) REFERENCES tutores (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_notif_aplicada
        FOREIGN KEY (vacina_aplicada_id) REFERENCES vacinas_aplicadas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3) VIEW: VACINAS A VENCER
--    Consulta pronta. Ex.: WHERE dias_para_vencer <= 30
--    dias_para_vencer < 0  -> já venceu
--    dias_para_vencer = 0  -> vence hoje
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW vw_vacinas_a_vencer AS
SELECT
    va.id                                   AS vacina_aplicada_id,
    a.Animalid                              AS animal_id,
    a.Nome                                  AS animal,
    a.Especie                               AS especie,
    t.id                                    AS tutor_id,
    t.nome                                  AS tutor,
    t.email                                 AS tutor_email,
    t.telefone                              AS tutor_telefone,
    v.Vacinaid                              AS vacina_id,
    v.TipoVacina                            AS vacina,
    va.data_aplicacao                       AS data_aplicacao,
    va.data_proxima_dose                    AS data_proxima_dose,
    DATEDIFF(va.data_proxima_dose, CURDATE()) AS dias_para_vencer,
    CASE
        WHEN va.data_proxima_dose < CURDATE()               THEN 'vencida'
        WHEN DATEDIFF(va.data_proxima_dose, CURDATE()) <= 7  THEN 'vence_em_breve'
        WHEN DATEDIFF(va.data_proxima_dose, CURDATE()) <= 30 THEN 'a_vencer'
        ELSE 'em_dia'
    END                                     AS situacao
FROM vacinas_aplicadas va
JOIN animais  a ON a.Animalid = va.animal_id
JOIN tutores  t ON t.id       = a.tutor_id
JOIN vacinas  v ON v.Vacinaid = va.vacina_id
WHERE va.status = 'ativa'
  AND va.data_proxima_dose IS NOT NULL
  AND a.ativo = 1
  AND t.ativo = 1;

-- ------------------------------------------------------------
-- 4) PROCEDURE: GERA AS NOTIFICAÇÕES
--    p_dias = quantos dias de antecedência (ex.: 30)
--    Pode ser chamada manualmente:
--        CALL sp_gerar_notificacoes_vacinas(30);
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_gerar_notificacoes_vacinas;

DELIMITER $$

CREATE PROCEDURE sp_gerar_notificacoes_vacinas(IN p_dias INT)
BEGIN
    DECLARE v_dias INT DEFAULT 30;

    IF p_dias IS NOT NULL AND p_dias >= 0 THEN
        SET v_dias = p_dias;
    END IF;

    INSERT IGNORE INTO notificacoes
        (animal_id, tutor_id, vacina_aplicada_id, tipo, canal,
         mensagem, status, data_agendada)
    SELECT
        a.Animalid,
        t.id,
        va.id,
        'lembrete_vacina',
        'email',
        CONCAT(
            'Olá, ', t.nome, '! A vacina ', v.TipoVacina,
            ' do animal ', a.Nome,
            CASE
                WHEN va.data_proxima_dose < CURDATE() THEN
                    CONCAT(' venceu em ',
                           DATE_FORMAT(va.data_proxima_dose, '%d/%m/%Y'),
                           ' (há ', ABS(DATEDIFF(va.data_proxima_dose, CURDATE())),
                           ' dia(s)).')
                WHEN va.data_proxima_dose = CURDATE() THEN
                    ' vence hoje.'
                ELSE
                    CONCAT(' vence em ',
                           DATEDIFF(va.data_proxima_dose, CURDATE()),
                           ' dia(s), em ',
                           DATE_FORMAT(va.data_proxima_dose, '%d/%m/%Y'), '.')
            END,
            ' Por favor, entre em contato para agendar a aplicação.'
        ),
        'pendente',
        CURDATE()
    FROM vacinas_aplicadas va
    JOIN animais a ON a.Animalid = va.animal_id
    JOIN tutores t ON t.id       = a.tutor_id
    JOIN vacinas v ON v.Vacinaid = va.vacina_id
    WHERE va.status = 'ativa'
      AND va.data_proxima_dose IS NOT NULL
      AND va.data_proxima_dose <= DATE_ADD(CURDATE(), INTERVAL v_dias DAY)
      AND a.ativo = 1
      AND t.ativo = 1
      AND t.email IS NOT NULL
      AND t.email <> ''
      AND t.email LIKE '%@%';
END$$

DELIMITER ;

-- ------------------------------------------------------------
-- 5) EVENT: RODA A ROTINA TODO DIA ÀS 08:00
--    Antes de criar, o agendador precisa estar ligado:
--        SET GLOBAL event_scheduler = ON;
--    (em phpMyAdmin rode essa linha sozinha, pois exige
--     privilégio de administrador)
-- ------------------------------------------------------------
DROP EVENT IF EXISTS ev_gerar_notificacoes_vacinas;

CREATE EVENT ev_gerar_notificacoes_vacinas
ON SCHEDULE EVERY 1 DAY
STARTS (CURDATE() + INTERVAL 1 DAY + INTERVAL 8 HOUR)
DO
    CALL sp_gerar_notificacoes_vacinas(30);

-- ------------------------------------------------------------
-- 6) CONSULTAS ÚTEIS
-- ------------------------------------------------------------

-- Vacinas que vencem nos próximos 30 dias (inclui vencidas):
-- SELECT * FROM vw_vacinas_a_vencer
-- WHERE dias_para_vencer <= 30
-- ORDER BY dias_para_vencer;

-- Notificações pendentes de envio:
-- SELECT * FROM notificacoes
-- WHERE status = 'pendente'
-- ORDER BY data_agendada;

-- Ver se o event scheduler está ligado:
-- SHOW VARIABLES LIKE 'event_scheduler';

-- Ver se o animal está ligado a um tutor (sem isso não há aviso):
-- SELECT Animalid, Nome, tutor_id, ativo FROM animais;
