<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function responderHistorico(int $status, array $dados): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    responderHistorico(405, ['erro' => 'Método não permitido.']);
}

$filtros = ['produto_id' => null, 'lote_id' => null];
foreach (array_keys($filtros) as $campo) {
    $valor = $_GET[$campo] ?? '';
    if ($valor === '') {
        continue;
    }
    if (!is_string($valor) || !preg_match('/\A[1-9][0-9]{0,9}\z/', $valor) || $valor > 4294967295) {
        responderHistorico(400, ['erro' => 'Selecione um produto e um lote válidos.']);
    }
    $filtros[$campo] = $valor;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require __DIR__ . '/../includes/conexao.php';

    $resultado = mysqli_query($conexao, '
        SELECT p.id AS produto_id, p.nome, l.id AS lote_id, l.numero_lote, f.nome AS fornecedor
        FROM produtos p
        LEFT JOIN lotes l ON l.produto_id = p.id
        LEFT JOIN fornecedores f ON f.id = l.fornecedor_id
        ORDER BY p.nome, p.id, l.numero_lote, l.id
    ');
    $produtos = [];
    $lotes = [];
    while ($linha = mysqli_fetch_assoc($resultado)) {
        $produtos[$linha['produto_id']] = ['id' => (string) $linha['produto_id'], 'nome' => $linha['nome']];
        if ($linha['lote_id'] !== null) {
            $lotes[$linha['lote_id']] = [
                'id' => (string) $linha['lote_id'],
                'produto_id' => (string) $linha['produto_id'],
                'rotulo' => $linha['nome'] . ' — ' . $linha['numero_lote'] . ' — ' . $linha['fornecedor'],
            ];
        }
    }
    mysqli_free_result($resultado);

    $produtoId = $filtros['produto_id'];
    $loteId = $filtros['lote_id'];
    if ($produtoId !== null && !isset($produtos[$produtoId])) {
        responderHistorico(404, ['erro' => 'O produto selecionado não existe.']);
    }
    if ($loteId !== null && !isset($lotes[$loteId])) {
        responderHistorico(404, ['erro' => 'O lote selecionado não existe.']);
    }
    if ($produtoId !== null && $loteId !== null && $lotes[$loteId]['produto_id'] !== $produtoId) {
        responderHistorico(422, ['erro' => 'O lote selecionado não pertence ao produto escolhido.']);
    }

    $consulta = mysqli_prepare($conexao, '
        SELECT historico.*, saldo_depois - variacao AS saldo_antes
        FROM (
            SELECT m.id, m.data_movimentacao, m.tipo, m.motivo_saida, m.quantidade, m.justificativa,
                   COALESCE(m.responsavel_movimentacao,
                       NULLIF(TRIM(CONCAT_WS(\' \', m.responsavel_tecnico, m.registro_responsavel)), \'\')) AS operador,
                   p.nome AS produto, l.id AS lote_id, l.numero_lote, f.nome AS fornecedor,
                   CASE m.tipo WHEN \'saida\' THEN -CAST(m.quantidade AS DECIMAL(24,9))
                       ELSE CAST(m.quantidade AS DECIMAL(24,9)) END AS variacao,
                   SUM(CASE m.tipo WHEN \'saida\' THEN -CAST(m.quantidade AS DECIMAL(24,9))
                       ELSE CAST(m.quantidade AS DECIMAL(24,9)) END)
                     OVER (PARTITION BY l.id ORDER BY m.data_movimentacao, m.id
                           ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS saldo_depois
            FROM movimentacoes m
            JOIN lotes l ON l.id = m.lote_id
            JOIN produtos p ON p.id = l.produto_id
            JOIN fornecedores f ON f.id = l.fornecedor_id
            WHERE (? IS NULL OR p.id = ?) AND (? IS NULL OR l.id = ?)
        ) AS historico
        ORDER BY data_movimentacao DESC, id DESC
    ');
    try {
        mysqli_stmt_bind_param($consulta, 'ssss', $produtoId, $produtoId, $loteId, $loteId);
        mysqli_stmt_execute($consulta);
        $resultado = mysqli_stmt_get_result($consulta);
        $movimentacoes = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
        mysqli_free_result($resultado);
    } finally {
        mysqli_stmt_close($consulta);
    }

    foreach ($movimentacoes as &$movimentacao) {
        $movimentacao['tipo_exibicao'] = $movimentacao['motivo_saida'] ?? $movimentacao['tipo'];
        $movimentacao['operador'] = $movimentacao['operador'] ?? '';
    }
    unset($movimentacao);

    responderHistorico(200, [
        'produtos' => array_values($produtos),
        'lotes' => array_values($lotes),
        'movimentacoes' => $movimentacoes,
    ]);
} catch (mysqli_sql_exception $excecao) {
    error_log($excecao->getMessage());
    responderHistorico(500, ['erro' => 'Não foi possível consultar o histórico de estoque. Tente novamente.']);
}
