<?php
function consultarLotesFefo(mysqli $conexao, string $hoje): array
{
    $consulta = mysqli_prepare($conexao, "
        SELECT l.id, p.nome AS produto, l.numero_lote, f.nome AS fornecedor,
               l.data_validade,
               SUM(CASE m.tipo
                   WHEN 'entrada' THEN CAST(m.quantidade AS SIGNED)
                   WHEN 'saida' THEN -CAST(m.quantidade AS SIGNED)
               END) AS saldo
        FROM lotes l
        JOIN produtos p ON p.id = l.produto_id
        JOIN fornecedores f ON f.id = l.fornecedor_id
        JOIN movimentacoes m ON m.lote_id = l.id
        WHERE l.status = 'ativo' AND (l.data_validade IS NULL OR l.data_validade >= ?)
        GROUP BY l.id, p.nome, l.numero_lote, f.nome, l.data_validade
        HAVING saldo > 0
        ORDER BY l.data_validade IS NULL, l.data_validade, l.id
    ");

    try {
        mysqli_stmt_bind_param($consulta, 's', $hoje);
        mysqli_stmt_execute($consulta);
        $resultado = mysqli_stmt_get_result($consulta);
        return mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    } finally {
        mysqli_stmt_close($consulta);
    }
}
