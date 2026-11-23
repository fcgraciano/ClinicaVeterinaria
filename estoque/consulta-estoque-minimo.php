<?php
function consultarEstoqueMinimo(mysqli $conexao): array
{
    $resultado = mysqli_query($conexao, "
        SELECT p.nome, p.codigo_barras, p.estoque_minimo,
               COALESCE(SUM(CASE m.tipo
                   WHEN 'entrada' THEN CAST(m.quantidade AS DECIMAL(24,9))
                   WHEN 'saida' THEN -CAST(m.quantidade AS DECIMAL(24,9))
               END), 0) AS saldo_total
        FROM produtos p
        LEFT JOIN lotes l ON l.produto_id = p.id
        LEFT JOIN movimentacoes m ON m.lote_id = l.id
        GROUP BY p.id, p.nome, p.codigo_barras, p.estoque_minimo
        HAVING saldo_total <= p.estoque_minimo
        ORDER BY p.nome, p.id
    ");
    $produtos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    mysqli_free_result($resultado);
    return $produtos;
}

function formatarSaldoParaReposicao(string $saldo): string
{
    if (strpos($saldo, '.') !== false) {
        $saldo = rtrim(rtrim($saldo, '0'), '.');
    }
    return str_replace('.', ',', $saldo);
}
