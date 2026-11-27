<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

$dias = $_GET['dias'] ?? '';
if (!is_string($dias) || !in_array($dias, ['30', '60', '90'], true)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Selecione um intervalo de 30, 60 ou 90 dias.']);
    exit;
}

$hoje = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
$inicio = $hoje->format('Y-m-d');
$fim = $hoje->add(new DateInterval('P' . $dias . 'D'))->format('Y-m-d');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require __DIR__ . '/../includes/conexao.php';
    $consulta = mysqli_prepare($conexao, '
        SELECT p.nome AS produto, l.numero_lote, f.nome AS fornecedor,
               l.data_validade, DATEDIFF(l.data_validade, ?) AS dias_restantes
        FROM lotes l
        JOIN produtos p ON p.id = l.produto_id
        JOIN fornecedores f ON f.id = l.fornecedor_id
        JOIN movimentacoes m ON m.lote_id = l.id
        WHERE l.status = \'ativo\'
          AND l.data_validade BETWEEN ? AND ?
        GROUP BY l.id, p.nome, l.numero_lote, f.nome, l.data_validade
        HAVING SUM(CASE m.tipo
            WHEN \'entrada\' THEN CAST(m.quantidade AS DECIMAL(24,9))
            WHEN \'saida\' THEN -CAST(m.quantidade AS DECIMAL(24,9))
        END) > 0
        ORDER BY l.data_validade, p.nome, l.id
    ');

    try {
        mysqli_stmt_bind_param($consulta, 'sss', $inicio, $inicio, $fim);
        mysqli_stmt_execute($consulta);
        $resultado = mysqli_stmt_get_result($consulta);
        $lotes = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
        mysqli_free_result($resultado);
    } finally {
        mysqli_stmt_close($consulta);
    }

    echo json_encode([
        'inicio' => $inicio,
        'fim' => $fim,
        'lotes' => $lotes,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (mysqli_sql_exception | JsonException $excecao) {
    error_log($excecao->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível consultar os vencimentos. Tente novamente.']);
}
