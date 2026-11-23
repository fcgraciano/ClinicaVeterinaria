<?php
$campos = [
    'nome' => ['rotulo' => 'Nome', 'atributos' => 'required'],
    'categoria' => ['rotulo' => 'Categoria', 'atributos' => 'required'],
    'fornecedor' => ['rotulo' => 'Fornecedor', 'atributos' => 'required'],
    'codigo_barras' => ['rotulo' => 'Código de barras', 'atributos' => 'required inputmode="numeric" pattern="[0-9]+"'],
    'estoque_minimo' => ['rotulo' => 'Estoque mínimo', 'atributos' => 'required min="0" step="1"'],
];

$valores = [];
$erros = [];
$enviado = $_SERVER['REQUEST_METHOD'] === 'POST';

foreach ($campos as $nome => $campo) {
    $valor = $enviado ? ($_POST[$nome] ?? '') : '';
    $valores[$nome] = is_string($valor) ? trim($valor) : '';

    if ($enviado && $valores[$nome] === '') {
        $erros[$nome] = 'Preencha este campo.';
    }
}

if ($enviado && !isset($erros['codigo_barras']) && !ctype_digit($valores['codigo_barras'])) {
    $erros['codigo_barras'] = 'Informe apenas números.';
}

if ($enviado && !isset($erros['estoque_minimo']) && !ctype_digit($valores['estoque_minimo'])) {
    $erros['estoque_minimo'] = 'Informe um número inteiro maior ou igual a zero.';
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<div class="row">
    <div class="col-lg-8">
        <h1 class="mb-3">Adicionar produto</h1>

        <?php if ($enviado && !$erros): ?>
            <div class="alert alert-info" role="status">
                Dados validados. O cadastro ainda não salva produtos.
            </div>
        <?php endif; ?>

        <form method="post" action="adicionar-produto.php" class="card card-body">
            <div class="row g-3">
                <?php foreach ($campos as $nome => $campo): ?>
                    <div class="<?= $nome === 'nome' ? 'col-12' : 'col-md-6' ?>">
                        <label class="form-label" for="<?= $nome ?>"><?= $campo['rotulo'] ?></label>
                        <input
                            class="form-control<?= isset($erros[$nome]) ? ' is-invalid' : '' ?>"
                            type="<?= $nome === 'estoque_minimo' ? 'number' : 'text' ?>"
                            id="<?= $nome ?>"
                            name="<?= $nome ?>"
                            value="<?= htmlspecialchars($valores[$nome], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $campo['atributos'] ?>
                            <?php if (isset($erros[$nome])): ?>
                                aria-invalid="true" aria-describedby="<?= $nome ?>-erro"
                            <?php endif; ?>
                        >
                        <?php if (isset($erros[$nome])): ?>
                            <div class="invalid-feedback" id="<?= $nome ?>-erro"><?= $erros[$nome] ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit">Validar cadastro</button>
                <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
