-- Execute uma vez depois de estoque.sql, inclusive em bancos ja instalados.
USE patas_db;

ALTER TABLE lotes
    ADD INDEX idx_lotes_validade (data_validade);

ALTER TABLE movimentacoes
    ADD INDEX idx_movimentacoes_lote_data (lote_id, data_movimentacao, id),
    ADD CONSTRAINT chk_movimentacoes_tipo CHECK (tipo IN ('entrada', 'saida'));

DELIMITER $$

-- Chame fora de uma transacao aberta. A rotina controla COMMIT e ROLLBACK.
-- Exemplo: CALL registrar_movimentacao_estoque(1, 'entrada', 10);
CREATE PROCEDURE registrar_movimentacao_estoque(
    IN p_lote_id BIGINT,
    IN p_tipo TEXT,
    IN p_quantidade BIGINT
)
BEGIN
    DECLARE v_lote_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_saldo DECIMAL(30, 0);

    IF p_lote_id IS NULL OR p_lote_id NOT BETWEEN 1 AND 4294967295
        OR p_tipo IS NULL OR BINARY p_tipo NOT IN ('entrada', 'saida')
        OR p_quantidade IS NULL OR p_quantidade NOT BETWEEN 1 AND 4294967295 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Dados de movimentacao invalidos.';
    END IF;

    BEGIN
        DECLARE EXIT HANDLER FOR SQLEXCEPTION
        BEGIN
            ROLLBACK;
            RESIGNAL;
        END;

        START TRANSACTION;

        -- Serializa as movimentacoes do mesmo lote antes da leitura do saldo.
        SELECT id INTO v_lote_id
        FROM lotes
        WHERE id = p_lote_id
        FOR UPDATE;

        IF v_lote_id IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Lote nao encontrado.';
        END IF;

        IF p_tipo = 'saida' THEN
            SELECT COALESCE(SUM(
                CASE tipo
                    WHEN 'entrada' THEN CAST(quantidade AS SIGNED)
                    ELSE -CAST(quantidade AS SIGNED)
                END
            ), 0) INTO v_saldo
            FROM movimentacoes
            WHERE lote_id = v_lote_id;

            IF v_saldo < p_quantidade THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saldo insuficiente no lote.';
            END IF;
        END IF;

        INSERT INTO movimentacoes (lote_id, tipo, quantidade)
        VALUES (v_lote_id, p_tipo, p_quantidade);

        COMMIT;
    END;
END$$

DELIMITER ;
