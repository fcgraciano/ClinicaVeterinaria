<?php
function consultarLotesDoProduto(mysqli $conexao, string $produtoId, string $hoje): ?array
{
    $consulta = mysqli_prepare($conexao, "
        SELECT p.id AS produto_id, p.nome AS produto, c.nome AS categoria, p.codigo_barras, p.estoque_minimo,
               l.id AS lote_id, l.numero_lote, l.data_validade, f.nome AS fornecedor,
               CASE
                   WHEN l.status = 'bloqueado' THEN 'bloqueado'
                   WHEN l.data_validade < ? THEN 'vencido'
                   ELSE l.status
               END AS status,
               COALESCE(SUM(CASE m.tipo
                   WHEN 'entrada' THEN CAST(m.quantidade AS DECIMAL(24,9))
                   WHEN 'saida' THEN -CAST(m.quantidade AS DECIMAL(24,9))
               END), 0) AS saldo
        FROM produtos p
        JOIN categorias c ON c.id = p.categoria_id
        LEFT JOIN lotes l ON l.produto_id = p.id
        LEFT JOIN fornecedores f ON f.id = l.fornecedor_id
        LEFT JOIN movimentacoes m ON m.lote_id = l.id
        WHERE p.id = ?
        GROUP BY p.id, p.nome, c.nome, p.codigo_barras, p.estoque_minimo,
                 l.id, l.numero_lote, l.data_validade, l.status, f.nome
        ORDER BY l.data_validade IS NULL, l.data_validade, l.id
    ");

    try {
        mysqli_stmt_bind_param($consulta, 'ss', $hoje, $produtoId);
        mysqli_stmt_execute($consulta);
        $resultado = mysqli_stmt_get_result($consulta);
        $dados = null;
        while ($linha = mysqli_fetch_assoc($resultado)) {
            $dados ??= [
                'produto' => [
                    'id' => $linha['produto_id'],
                    'nome' => $linha['produto'],
                    'categoria' => $linha['categoria'],
                    'codigo_barras' => $linha['codigo_barras'],
                    'estoque_minimo' => $linha['estoque_minimo'],
                ],
                'lotes' => [],
            ];
            if ($linha['lote_id'] !== null) {
                $dados['lotes'][] = [
                    'id' => $linha['lote_id'],
                    'numero_lote' => $linha['numero_lote'],
                    'status' => $linha['status'],
                    'data_validade' => $linha['data_validade'],
                    'fornecedor' => $linha['fornecedor'],
                    'saldo' => $linha['saldo'],
                ];
            }
        }
        mysqli_free_result($resultado);
        return $dados;
    } finally {
        mysqli_stmt_close($consulta);
    }
}
