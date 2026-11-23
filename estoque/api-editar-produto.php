<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function responderEdicao(int $status, array $dados): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function idValidoEdicao($id): bool
{
    return is_string($id) && preg_match('/\A[1-9][0-9]{0,9}\z/', $id) && $id <= 4294967295;
}

$metodo = $_SERVER['REQUEST_METHOD'];
if ($metodo !== 'GET' && $metodo !== 'POST') {
    header('Allow: GET, POST');
    responderEdicao(405, ['erro' => 'Método não permitido.']);
}

$id = $_GET['id'] ?? '';
if (!idValidoEdicao($id)) {
    responderEdicao(400, ['erro' => 'Informe um produto válido.']);
}

session_start();
$_SESSION['edicao_produto_token'] ??= bin2hex(random_bytes(32));
$token = $_SESSION['edicao_produto_token'];
session_write_close();

if ($metodo === 'POST') {
    $tokenEnviado = $_POST['token'] ?? '';
    if (!is_string($tokenEnviado) || !hash_equals($token, $tokenEnviado)) {
        responderEdicao(403, ['erro' => 'Sessão do formulário inválida. Recarregue a página.']);
    }

    $nome = is_string($_POST['nome'] ?? null) ? trim($_POST['nome']) : '';
    $codigo = is_string($_POST['codigo_barras'] ?? null) ? trim($_POST['codigo_barras']) : '';
    $categoria = $_POST['categoria_id'] ?? '';
    $minimo = $_POST['estoque_minimo'] ?? '';
    $fornecedores = $_POST['fornecedores'] ?? [];

    if (!preg_match('/\A[^\p{C}]{1,150}\z/u', $nome)
        || !preg_match('/\A[^\s\p{C}]{1,50}\z/u', $codigo)
        || !idValidoEdicao($categoria)
        || !is_string($minimo) || !preg_match('/\A[0-9]{1,10}\z/', $minimo) || $minimo > 4294967295
        || !is_array($fornecedores)) {
        responderEdicao(422, ['erro' => 'Confira o nome, código de barras, categoria e estoque mínimo.']);
    }
    foreach ($fornecedores as $fornecedor) {
        if (!idValidoEdicao($fornecedor)) {
            responderEdicao(422, ['erro' => 'Selecione fornecedores válidos.']);
        }
    }
    $fornecedores = array_unique($fornecedores);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require __DIR__ . '/../includes/conexao.php';

    if ($metodo === 'POST') {
        mysqli_begin_transaction($conexao);
    }
    try {
        $sql = 'SELECT id, nome, codigo_barras, categoria_id, estoque_minimo FROM produtos WHERE id = ?';
        if ($metodo === 'POST') {
            $sql .= ' FOR UPDATE';
        }
        $consulta = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($consulta, 's', $id);
        mysqli_stmt_execute($consulta);
        $produto = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));
        mysqli_stmt_close($consulta);
        if (!$produto) {
            if ($metodo === 'POST') {
                mysqli_rollback($conexao);
            }
            responderEdicao(404, ['erro' => 'Produto não encontrado.']);
        }

        $sql = 'SELECT fornecedor_id FROM produto_fornecedor WHERE produto_id = ?';
        if ($metodo === 'POST') {
            $sql .= ' FOR UPDATE';
        }
        $consulta = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($consulta, 's', $id);
        mysqli_stmt_execute($consulta);
        $vinculos = mysqli_fetch_all(mysqli_stmt_get_result($consulta), MYSQLI_NUM);
        mysqli_stmt_close($consulta);
        $atuais = array_column($vinculos, 0);

        if ($metodo === 'GET') {
            $categorias = mysqli_fetch_all(mysqli_query($conexao, 'SELECT id, nome FROM categorias ORDER BY nome, id'), MYSQLI_ASSOC);
            $opcoes = mysqli_fetch_all(mysqli_query($conexao, 'SELECT id, nome FROM fornecedores ORDER BY nome, id'), MYSQLI_ASSOC);
            responderEdicao(200, ['produto' => $produto, 'categorias' => $categorias, 'fornecedores' => $opcoes,
                'fornecedores_ids' => $atuais, 'token' => $token]);
        }

        $consulta = mysqli_prepare($conexao,
            'UPDATE produtos SET nome = ?, codigo_barras = ?, categoria_id = ?, estoque_minimo = ? WHERE id = ?');
        mysqli_stmt_bind_param($consulta, 'sssss', $nome, $codigo, $categoria, $minimo, $id);
        mysqli_stmt_execute($consulta);
        mysqli_stmt_close($consulta);

        $alteracoes = [
            ['INSERT INTO produto_fornecedor (produto_id, fornecedor_id) VALUES (?, ?)', array_diff($fornecedores, $atuais)],
            ['DELETE FROM produto_fornecedor WHERE produto_id = ? AND fornecedor_id = ?', array_diff($atuais, $fornecedores)],
        ];
        foreach ($alteracoes as [$sql, $ids]) {
            if (!$ids) {
                continue;
            }
            $consulta = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($consulta, 'ss', $id, $fornecedor);
            foreach ($ids as $fornecedor) {
                mysqli_stmt_execute($consulta);
            }
            mysqli_stmt_close($consulta);
        }

        mysqli_commit($conexao);
        responderEdicao(200, ['mensagem' => 'Produto atualizado com sucesso.']);
    } catch (Throwable $excecao) {
        if ($metodo === 'POST') {
            mysqli_rollback($conexao);
        }
        throw $excecao;
    }
} catch (mysqli_sql_exception $excecao) {
    switch ($excecao->getCode()) {
        case 1062:
            responderEdicao(409, ['erro' => 'Já existe um produto com esse código de barras.']);
        case 1451:
            responderEdicao(409, ['erro' => 'Um fornecedor com lotes registrados não pode ser desvinculado.']);
        case 1452:
            responderEdicao(422, ['erro' => 'A categoria ou um dos fornecedores selecionados não existe.']);
        default:
            error_log($excecao->getMessage());
            responderEdicao(500, ['erro' => 'Não foi possível acessar ou atualizar o produto.']);
    }
}
