<?php
session_start();
$_SESSION['adicionar_produto_token'] ??= bin2hex(random_bytes(32));
$token = $_SESSION['adicionar_produto_token'];
$valores = ['nome' => '', 'categoria_id' => '', 'fornecedor_id' => '', 'codigo_barras' => '', 'estoque_minimo' => ''];
$erros = [];
$erroBanco = '';
$categorias = [];
$fornecedores = [];
$cadastrado = $_SESSION['produto_cadastrado'] ?? null;
unset($_SESSION['produto_cadastrado']);
$enviado = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($enviado) {
    $tokenEnviado = $_POST['token'] ?? '';
    if (!is_string($tokenEnviado) || !hash_equals($token, $tokenEnviado)) {
        $erros['token'] = 'O formulário expirou. Recarregue a página e tente novamente.';
    }
    foreach (array_keys($valores) as $campo) {
        $valor = $_POST[$campo] ?? '';
        $valores[$campo] = is_string($valor) ? trim($valor) : '';
        if (!is_string($valor)) {
            $erros[$campo] = 'Valor inválido.';
        }
    }
    if (!preg_match('/\A[^\p{C}]{1,150}\z/u', $valores['nome'])) {
        $erros['nome'] = 'Informe um nome válido com até 150 caracteres.';
    }
    foreach (['categoria_id', 'fornecedor_id'] as $campo) {
        if (!preg_match('/\A[1-9][0-9]{0,9}\z/', $valores[$campo]) || $valores[$campo] > 4294967295) {
            $erros[$campo] = 'Selecione uma opção válida.';
        }
    }
    if (!preg_match('/\A[0-9]{1,50}\z/', $valores['codigo_barras'])) {
        $erros['codigo_barras'] = 'Informe de 1 a 50 números para o código de barras.';
    }
    if (!preg_match('/\A[0-9]{1,10}\z/', $valores['estoque_minimo']) || $valores['estoque_minimo'] > 4294967295) {
        $erros['estoque_minimo'] = 'Informe um número inteiro entre zero e 4294967295.';
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require __DIR__ . '/../includes/conexao.php';
    $categorias = mysqli_fetch_all(mysqli_query($conexao, 'SELECT id, nome FROM categorias ORDER BY nome, id'), MYSQLI_ASSOC);
    $fornecedores = mysqli_fetch_all(mysqli_query($conexao, 'SELECT id, nome FROM fornecedores ORDER BY nome, id'), MYSQLI_ASSOC);

    if ($enviado && !$erros) {
        $categoriaIds = array_column($categorias, 'id');
        $fornecedorIds = array_column($fornecedores, 'id');
        if (!in_array($valores['categoria_id'], array_map('strval', $categoriaIds), true)) {
            $erros['categoria_id'] = 'A categoria selecionada não existe.';
        }
        if (!in_array($valores['fornecedor_id'], array_map('strval', $fornecedorIds), true)) {
            $erros['fornecedor_id'] = 'O fornecedor selecionado não existe.';
        }
    }

    if ($enviado && !$erros) {
        mysqli_begin_transaction($conexao);
        try {
            $consulta = mysqli_prepare($conexao,
                'INSERT INTO produtos (nome, categoria_id, codigo_barras, estoque_minimo) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($consulta, 'ssss', $valores['nome'], $valores['categoria_id'],
                $valores['codigo_barras'], $valores['estoque_minimo']);
            mysqli_stmt_execute($consulta);
            $produtoId = (string) mysqli_insert_id($conexao);
            mysqli_stmt_close($consulta);

            $consulta = mysqli_prepare($conexao,
                'INSERT INTO produto_fornecedor (produto_id, fornecedor_id) VALUES (?, ?)');
            mysqli_stmt_bind_param($consulta, 'ss', $produtoId, $valores['fornecedor_id']);
            mysqli_stmt_execute($consulta);
            mysqli_stmt_close($consulta);
            mysqli_commit($conexao);

            $_SESSION['produto_cadastrado'] = $valores['nome'];
            unset($_SESSION['adicionar_produto_token']);
            header('Location: adicionar-produto.php', true, 303);
            exit;
        } catch (Throwable $excecao) {
            mysqli_rollback($conexao);
            throw $excecao;
        }
    }
} catch (mysqli_sql_exception $excecao) {
    if ($excecao->getCode() === 1062) {
        $erros['codigo_barras'] = 'Já existe um produto com esse código de barras.';
    } else {
        error_log($excecao->getMessage());
        $erroBanco = 'Não foi possível consultar ou cadastrar o produto. Tente novamente.';
    }
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<div class="row">
    <div class="col-lg-8">
        <h1 class="mb-3">Adicionar produto</h1>

        <?php if ($cadastrado !== null): ?>
            <div class="alert alert-success" role="status">Produto <?= htmlspecialchars($cadastrado) ?> cadastrado com sucesso.</div>
        <?php endif; ?>
        <?php if ($erroBanco !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erroBanco) ?></div>
        <?php endif; ?>
        <?php if (isset($erros['token'])): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erros['token']) ?></div>
        <?php endif; ?>

        <?php if (!$categorias || !$fornecedores): ?>
            <div class="alert alert-info" role="status">Cadastre ao menos uma categoria e um fornecedor antes de adicionar produtos.</div>
        <?php else: ?>
            <form method="post" action="adicionar-produto.php" class="card card-body">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="nome">Nome</label>
                        <input class="form-control<?= isset($erros['nome']) ? ' is-invalid' : '' ?>" id="nome" name="nome"
                            maxlength="150" value="<?= htmlspecialchars($valores['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
                        <?php if (isset($erros['nome'])): ?><div class="invalid-feedback"><?= htmlspecialchars($erros['nome']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="categoria_id">Categoria</label>
                        <select class="form-select<?= isset($erros['categoria_id']) ? ' is-invalid' : '' ?>" id="categoria_id" name="categoria_id" required>
                            <option value="">Selecione</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= htmlspecialchars((string) $categoria['id']) ?>" <?= $valores['categoria_id'] === (string) $categoria['id'] ? 'selected' : '' ?>><?= htmlspecialchars($categoria['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($erros['categoria_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($erros['categoria_id']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="fornecedor_id">Fornecedor</label>
                        <select class="form-select<?= isset($erros['fornecedor_id']) ? ' is-invalid' : '' ?>" id="fornecedor_id" name="fornecedor_id" required>
                            <option value="">Selecione</option>
                            <?php foreach ($fornecedores as $fornecedor): ?>
                                <option value="<?= htmlspecialchars((string) $fornecedor['id']) ?>" <?= $valores['fornecedor_id'] === (string) $fornecedor['id'] ? 'selected' : '' ?>><?= htmlspecialchars($fornecedor['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($erros['fornecedor_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($erros['fornecedor_id']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="codigo_barras">Código de barras</label>
                        <input class="form-control<?= isset($erros['codigo_barras']) ? ' is-invalid' : '' ?>" id="codigo_barras" name="codigo_barras"
                            inputmode="numeric" pattern="[0-9]+" maxlength="50" value="<?= htmlspecialchars($valores['codigo_barras']) ?>" required>
                        <?php if (isset($erros['codigo_barras'])): ?><div class="invalid-feedback"><?= htmlspecialchars($erros['codigo_barras']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="estoque_minimo">Estoque mínimo</label>
                        <input class="form-control<?= isset($erros['estoque_minimo']) ? ' is-invalid' : '' ?>" type="number" id="estoque_minimo" name="estoque_minimo"
                            min="0" max="4294967295" step="1" value="<?= htmlspecialchars($valores['estoque_minimo']) ?>" required>
                        <?php if (isset($erros['estoque_minimo'])): ?><div class="invalid-feedback"><?= htmlspecialchars($erros['estoque_minimo']) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-primary" type="submit">Cadastrar produto</button>
                    <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
