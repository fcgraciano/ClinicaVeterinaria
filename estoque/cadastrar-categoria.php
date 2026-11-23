<?php
$nome = '';
$erroNome = '';
$erroCadastro = '';
$cadastrada = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeEnviado = $_POST['nome'] ?? '';
    $nome = is_string($nomeEnviado) ? trim($nomeEnviado) : '';

    if ($nome === '') {
        $erroNome = 'Informe o nome da categoria.';
    } elseif (!preg_match('/\A.{1,100}\z/us', $nome)) {
        $erroNome = 'Informe um nome válido com até 100 caracteres.';
    } else {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            require __DIR__ . '/../includes/conexao.php';
            $consulta = mysqli_prepare($conexao, 'INSERT INTO categorias (nome) VALUES (?)');
            try {
                mysqli_stmt_bind_param($consulta, 's', $nome);
                mysqli_stmt_execute($consulta);
            } finally {
                mysqli_stmt_close($consulta);
            }

            $cadastrada = true;
            $nome = '';
        } catch (mysqli_sql_exception $excecao) {
            if ($excecao->getCode() === 1062) {
                $erroNome = 'Já existe uma categoria com esse nome.';
            } else {
                error_log($excecao->getMessage());
                $erroCadastro = 'Não foi possível cadastrar a categoria. Tente novamente.';
            }
        }
    }
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<h1 class="mb-3">Cadastrar categoria</h1>

<?php if ($cadastrada): ?>
    <div class="alert alert-success" role="status">Categoria cadastrada com sucesso.</div>
<?php endif; ?>
<?php if ($erroCadastro !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= $erroCadastro ?></div>
<?php endif; ?>

<form method="post" action="cadastrar-categoria.php" class="card card-body col-lg-6">
    <div class="mb-3">
        <label class="form-label" for="nome">Nome da categoria</label>
        <input
            class="form-control<?= $erroNome !== '' ? ' is-invalid' : '' ?>"
            type="text"
            id="nome"
            name="nome"
            value="<?= htmlspecialchars($nome, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            maxlength="100"
            required
            <?php if ($erroNome !== ''): ?>
                aria-invalid="true" aria-describedby="nome-erro"
            <?php endif; ?>
        >
        <?php if ($erroNome !== ''): ?>
            <div class="invalid-feedback" id="nome-erro"><?= $erroNome ?></div>
        <?php endif; ?>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">Cadastrar</button>
        <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
