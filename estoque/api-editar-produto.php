<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function responderConsultaEdicao(int $status, array $dados): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    responderConsultaEdicao(405, ['erro' => 'Método não permitido.']);
}

$id = $_GET['id'] ?? '';
if (!is_string($id) || !preg_match('/\A[1-9][0-9]{0,9}\z/', $id) || $id > 4294967295) {
    responderConsultaEdicao(400, ['erro' => 'Informe um produto válido.']);
}

session_start();
$_SESSION['edicao_produto_token'] ??= bin2hex(random_bytes(32));
$token = $_SESSION['edicao_produto_token'];
session_write_close();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require __DIR__ . '/../includes/conexao.php';

    $consulta = mysqli_prepare($conexao, '
        SELECT id, nome, codigo_barras, categoria_id, estoque_minimo
        FROM produtos WHERE id = ?
    ');
    mysqli_stmt_bind_param($consulta, 's', $id);
    mysqli_stmt_execute($consulta);
    $produto = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));
    mysqli_stmt_close($consulta);
    if (!$produto) {
        responderConsultaEdicao(404, ['erro' => 'Produto não encontrado.']);
    }

    $consulta = mysqli_prepare($conexao,
        'SELECT fornecedor_id FROM produto_fornecedor WHERE produto_id = ? ORDER BY fornecedor_id');
    mysqli_stmt_bind_param($consulta, 's', $id);
    mysqli_stmt_execute($consulta);
    $vinculos = mysqli_fetch_all(mysqli_stmt_get_result($consulta), MYSQLI_NUM);
    $fornecedoresSelecionados = array_column($vinculos, 0);
    mysqli_stmt_close($consulta);

    $categorias = mysqli_fetch_all(mysqli_query($conexao,
        'SELECT id, nome FROM categorias ORDER BY nome, id'), MYSQLI_ASSOC);
    $fornecedores = mysqli_fetch_all(mysqli_query($conexao,
        'SELECT id, nome FROM fornecedores ORDER BY nome, id'), MYSQLI_ASSOC);

    responderConsultaEdicao(200, [
        'produto' => $produto,
        'categorias' => $categorias,
        'fornecedores' => $fornecedores,
        'fornecedores_ids' => $fornecedoresSelecionados,
        'token' => $token,
    ]);
} catch (mysqli_sql_exception $excecao) {
    error_log($excecao->getMessage());
    responderConsultaEdicao(500, ['erro' => 'Não foi possível consultar o produto. Tente novamente.']);
}
