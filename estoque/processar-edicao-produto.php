<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function responderEdicaoProduto(int $status, array $dados): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function idValidoEdicaoProduto($valor): bool
{
    return is_string($valor) && preg_match('/\A[1-9][0-9]{0,9}\z/', $valor)
        && $valor <= 4294967295;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    responderEdicaoProduto(405, ['erro' => 'Método não permitido.']);
}

$id = $_GET['id'] ?? '';
if (!idValidoEdicaoProduto($id)) {
    responderEdicaoProduto(400, ['erro' => 'Informe um produto válido.']);
}

session_start();
$tokenSessao = $_SESSION['edicao_produto_token'] ?? '';
$tokenEnviado = $_POST['token'] ?? '';
if (!is_string($tokenEnviado) || !is_string($tokenSessao) || $tokenSessao === ''
    || !hash_equals($tokenSessao, $tokenEnviado)) {
    session_write_close();
    responderEdicaoProduto(403, ['erro' => 'Sessão do formulário inválida. Recarregue a página.']);
}
session_write_close();

$nome = is_string($_POST['nome'] ?? null) ? trim($_POST['nome']) : '';
$codigo = is_string($_POST['codigo_barras'] ?? null) ? trim($_POST['codigo_barras']) : '';
$categoriaId = $_POST['categoria_id'] ?? '';
$estoqueMinimo = $_POST['estoque_minimo'] ?? '';
$fornecedores = $_POST['fornecedores'] ?? [];

if (!preg_match('/\A[^\p{C}]{1,150}\z/u', $nome)
    || !preg_match('/\A[^\s\p{C}]{1,50}\z/u', $codigo)
    || !idValidoEdicaoProduto($categoriaId)
    || !is_string($estoqueMinimo)
    || !preg_match('/\A[0-9]{1,10}\z/', $estoqueMinimo)
    || $estoqueMinimo > 4294967295
    || !is_array($fornecedores)
    || !$fornecedores) {
    responderEdicaoProduto(422, ['erro' => 'Confira nome, código de barras, categoria, estoque mínimo e fornecedores.']);
}

foreach ($fornecedores as $fornecedorId) {
    if (!idValidoEdicaoProduto($fornecedorId)) {
        responderEdicaoProduto(422, ['erro' => 'Selecione fornecedores válidos.']);
    }
}
$fornecedores = array_values(array_unique($fornecedores));

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require __DIR__ . '/../includes/conexao.php';
    mysqli_begin_transaction($conexao);

    try {
        $consulta = mysqli_prepare($conexao, 'SELECT id FROM produtos WHERE id = ? FOR UPDATE');
        mysqli_stmt_bind_param($consulta, 's', $id);
        mysqli_stmt_execute($consulta);
        $produtoExiste = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));
        mysqli_stmt_close($consulta);
        if (!$produtoExiste) {
            mysqli_rollback($conexao);
            responderEdicaoProduto(404, ['erro' => 'Produto não encontrado.']);
        }

        $consulta = mysqli_prepare($conexao, 'SELECT id FROM categorias WHERE id = ?');
        mysqli_stmt_bind_param($consulta, 's', $categoriaId);
        mysqli_stmt_execute($consulta);
        $categoriaExiste = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));
        mysqli_stmt_close($consulta);
        if (!$categoriaExiste) {
            mysqli_rollback($conexao);
            responderEdicaoProduto(422, ['erro' => 'A categoria selecionada não existe.']);
        }

        $consulta = mysqli_prepare($conexao,
            'SELECT fornecedor_id FROM produto_fornecedor WHERE produto_id = ? FOR UPDATE');
        mysqli_stmt_bind_param($consulta, 's', $id);
        mysqli_stmt_execute($consulta);
        $atuais = array_column(mysqli_fetch_all(mysqli_stmt_get_result($consulta), MYSQLI_NUM), 0);
        mysqli_stmt_close($consulta);

        $consulta = mysqli_prepare($conexao, 'SELECT id FROM fornecedores WHERE id = ?');
        foreach ($fornecedores as $fornecedorId) {
            mysqli_stmt_bind_param($consulta, 's', $fornecedorId);
            mysqli_stmt_execute($consulta);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($consulta))) {
                mysqli_stmt_close($consulta);
                mysqli_rollback($conexao);
                responderEdicaoProduto(422, ['erro' => 'Um dos fornecedores selecionados não existe.']);
            }
        }
        mysqli_stmt_close($consulta);

        $consulta = mysqli_prepare($conexao,
            'UPDATE produtos SET nome = ?, codigo_barras = ?, categoria_id = ?, estoque_minimo = ? WHERE id = ?');
        mysqli_stmt_bind_param($consulta, 'sssss', $nome, $codigo, $categoriaId, $estoqueMinimo, $id);
        mysqli_stmt_execute($consulta);
        mysqli_stmt_close($consulta);

        $incluir = array_diff($fornecedores, $atuais);
        if ($incluir) {
            $consulta = mysqli_prepare($conexao,
                'INSERT INTO produto_fornecedor (produto_id, fornecedor_id) VALUES (?, ?)');
            foreach ($incluir as $fornecedorId) {
                mysqli_stmt_bind_param($consulta, 'ss', $id, $fornecedorId);
                mysqli_stmt_execute($consulta);
            }
            mysqli_stmt_close($consulta);
        }

        $remover = array_diff($atuais, $fornecedores);
        if ($remover) {
            $consulta = mysqli_prepare($conexao,
                'DELETE FROM produto_fornecedor WHERE produto_id = ? AND fornecedor_id = ?');
            foreach ($remover as $fornecedorId) {
                mysqli_stmt_bind_param($consulta, 'ss', $id, $fornecedorId);
                mysqli_stmt_execute($consulta);
            }
            mysqli_stmt_close($consulta);
        }

        mysqli_commit($conexao);
        responderEdicaoProduto(200, ['mensagem' => 'Produto atualizado com sucesso.']);
    } catch (Throwable $excecao) {
        mysqli_rollback($conexao);
        throw $excecao;
    }
} catch (mysqli_sql_exception $excecao) {
    switch ($excecao->getCode()) {
        case 1062:
            responderEdicaoProduto(409, ['erro' => 'Já existe um produto com esse código de barras.']);
        case 1451:
            responderEdicaoProduto(409, ['erro' => 'Um fornecedor com lotes registrados não pode ser desvinculado.']);
        case 1452:
            responderEdicaoProduto(422, ['erro' => 'A categoria ou um dos fornecedores selecionados não existe.']);
        default:
            error_log($excecao->getMessage());
            responderEdicaoProduto(500, ['erro' => 'Não foi possível atualizar o produto. Tente novamente.']);
    }
}
