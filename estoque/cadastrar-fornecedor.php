<?php
function normalizarCnpj(string $valor): ?string
{
    $valor = strtoupper($valor);
    if (!preg_match('/\A(?:[A-Z0-9]{12}[0-9]{2}|[A-Z0-9]{2}\.[A-Z0-9]{3}\.[A-Z0-9]{3}\/[A-Z0-9]{4}-[0-9]{2})\z/', $valor)) {
        return null;
    }

    $cnpj = str_replace(['.', '/', '-'], '', $valor);
    if ($cnpj === str_repeat($cnpj[0], 14)) {
        return null;
    }

    for ($tamanho = 12; $tamanho <= 13; $tamanho++) {
        $soma = 0;
        for ($i = 0; $i < $tamanho; $i++) {
            $peso = 2 + (($tamanho - 1 - $i) % 8);
            $soma += (ord($cnpj[$i]) - ord('0')) * $peso;
        }

        $resto = $soma % 11;
        $digito = $resto < 2 ? 0 : 11 - $resto;
        if ($cnpj[$tamanho] !== (string) $digito) {
            return null;
        }
    }

    return $cnpj;
}

$campos = [
    'nome' => ['rotulo' => 'Nome do fornecedor', 'tipo' => 'text', 'limite' => 150, 'obrigatorio' => true],
    'cnpj' => ['rotulo' => 'CNPJ', 'tipo' => 'text', 'limite' => 18, 'obrigatorio' => true],
    'telefone' => ['rotulo' => 'Telefone', 'tipo' => 'tel', 'limite' => 30, 'obrigatorio' => false],
    'email' => ['rotulo' => 'E-mail', 'tipo' => 'email', 'limite' => 254, 'obrigatorio' => false],
];
$valores = array_fill_keys(array_keys($campos), '');
$erros = [];
$erroCadastro = '';
$cadastrado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($campos as $campo => $configuracao) {
        $enviado = $_POST[$campo] ?? '';
        if (!is_string($enviado)) {
            $erros[$campo] = 'Valor inválido.';
            continue;
        }

        $valores[$campo] = trim($enviado);
        if ($configuracao['obrigatorio'] && $valores[$campo] === '') {
            $erros[$campo] = 'Preencha este campo.';
        } elseif (!preg_match('/\A.{0,' . $configuracao['limite'] . '}\z/u', $valores[$campo])) {
            $erros[$campo] = 'Informe um valor válido com até ' . $configuracao['limite'] . ' caracteres.';
        }
    }

    $cnpj = normalizarCnpj($valores['cnpj']);
    if (!isset($erros['cnpj']) && $cnpj === null) {
        $erros['cnpj'] = 'Informe um CNPJ válido.';
    }
    if (!isset($erros['email']) && $valores['email'] !== '' && !filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'Informe um e-mail válido.';
    }

    if (!$erros) {
        $nome = $valores['nome'];
        $telefone = $valores['telefone'] === '' ? null : $valores['telefone'];
        $email = $valores['email'] === '' ? null : $valores['email'];
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            require __DIR__ . '/../includes/conexao.php';
            $consulta = mysqli_prepare($conexao, 'INSERT INTO fornecedores (nome, cnpj, telefone, email) VALUES (?, ?, ?, ?)');
            try {
                mysqli_stmt_bind_param($consulta, 'ssss', $nome, $cnpj, $telefone, $email);
                mysqli_stmt_execute($consulta);
            } finally {
                mysqli_stmt_close($consulta);
            }

            $cadastrado = true;
            $valores = array_fill_keys(array_keys($campos), '');
        } catch (mysqli_sql_exception $excecao) {
            if ($excecao->getCode() === 1062) {
                $erros['cnpj'] = 'Já existe um fornecedor com esse CNPJ.';
            } else {
                error_log($excecao->getMessage());
                $erroCadastro = 'Não foi possível cadastrar o fornecedor. Tente novamente.';
            }
        }
    }
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<h1 class="mb-3">Cadastrar fornecedor</h1>

<?php if ($cadastrado): ?>
    <div class="alert alert-success" role="status">Fornecedor cadastrado com sucesso.</div>
<?php endif; ?>
<?php if ($erroCadastro !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= $erroCadastro ?></div>
<?php endif; ?>

<form method="post" action="cadastrar-fornecedor.php" class="card card-body col-lg-6">
    <?php foreach ($campos as $campo => $configuracao): ?>
        <div class="mb-3">
            <label class="form-label" for="<?= $campo ?>"><?= $configuracao['rotulo'] ?></label>
            <input
                class="form-control<?= isset($erros[$campo]) ? ' is-invalid' : '' ?>"
                type="<?= $configuracao['tipo'] ?>"
                id="<?= $campo ?>"
                name="<?= $campo ?>"
                value="<?= htmlspecialchars($valores[$campo], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                maxlength="<?= $configuracao['limite'] ?>"
                <?= $configuracao['obrigatorio'] ? 'required' : '' ?>
                <?php if (isset($erros[$campo])): ?>
                    aria-invalid="true" aria-describedby="<?= $campo ?>-erro"
                <?php endif; ?>
            >
            <?php if (isset($erros[$campo])): ?>
                <div class="invalid-feedback" id="<?= $campo ?>-erro"><?= $erros[$campo] ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">Cadastrar</button>
        <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
