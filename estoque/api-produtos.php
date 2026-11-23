<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

$busca = $_GET['busca'] ?? '';
if (!is_string($busca) || !preg_match('/\A[^\p{C}]{0,150}\z/u', trim($busca))) {
    http_response_code(400);
    echo json_encode(['erro' => 'Informe uma busca válida com até 150 caracteres.']);
    exit;
}
$busca = trim($busca);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require __DIR__ . '/../includes/conexao.php';
    $consulta = mysqli_prepare($conexao, "
        SELECT p.nome, p.codigo_barras, c.nome AS categoria,
               COALESCE(SUM(CASE m.tipo
                   WHEN 'entrada' THEN CAST(m.quantidade AS DECIMAL(24,9))
                   WHEN 'saida' THEN -CAST(m.quantidade AS DECIMAL(24,9))
               END), 0) AS saldo
        FROM produtos p
        JOIN categorias c ON c.id = p.categoria_id
        LEFT JOIN lotes l ON l.produto_id = p.id
        LEFT JOIN movimentacoes m ON m.lote_id = l.id
        WHERE ? = '' OR LOCATE(?, p.nome) > 0 OR LOCATE(?, p.codigo_barras) > 0
        GROUP BY p.id, p.nome, p.codigo_barras, c.nome
        ORDER BY p.nome, p.id
    ");

    try {
        mysqli_stmt_bind_param($consulta, 'sss', $busca, $busca, $busca);
        mysqli_stmt_execute($consulta);
        $resultado = mysqli_stmt_get_result($consulta);
        $produtos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
        mysqli_free_result($resultado);
    } finally {
        mysqli_stmt_close($consulta);
    }

    echo json_encode(['produtos' => $produtos], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (mysqli_sql_exception | JsonException $excecao) {
    error_log($excecao->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível consultar os produtos. Tente novamente.']);
}
