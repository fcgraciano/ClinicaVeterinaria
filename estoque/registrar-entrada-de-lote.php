<?php
session_start();

function dataEntradaValida(string $valor): bool
{
    if (!preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $valor, $partes)) {
        return false;
    }

    return (int) $partes[1] >= 1000
        && checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]);
}

$agora = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
$hoje = $agora->format('Y-m-d');
$valores = [
    'vinculo' => '',
    'numero_lote' => '',
    'quantidade' => '',
    'data_entrada' => $hoje,
    'data_validade' => '',
];
$campos = [
    'numero_lote' => ['Número do lote', 'text', 'maxlength="50" required'],
    'quantidade' => ['Quantidade', 'number', 'min="1" max="4294967295" step="1" required'],
    'data_entrada' => ['Data de entrada', 'date', 'max="' . $hoje . '" required'],
    'data_validade' => ['Data de validade (opcional)', 'date', 'max="9999-12-31"'],
];
$erros = [];
$erroBanco = '';
$vinculos = [];
$saldo = $_SESSION['entrada_lote_saldo'] ?? null;
unset($_SESSION['entrada_lote_saldo']);
$_SESSION['entrada_lote_token'] ??= bin2hex(random_bytes(32));
$enviado = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($enviado) {
    $token = $_POST['token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['entrada_lote_token'], $token)) {
        $erros['token'] = 'O formulário expirou ou já foi enviado. Revise os dados e tente novamente.';
    }

    foreach ($valores as $campo => $padrao) {
        $enviadoCampo = $_POST[$campo] ?? '';
        $valores[$campo] = is_string($enviadoCampo) ? trim($enviadoCampo) : '';
        if (!is_string($enviadoCampo)) {
            $erros[$campo] = 'Valor inválido.';
        }
    }

    if (!preg_match('/\A[1-9][0-9]{0,9}:[1-9][0-9]{0,9}\z/', $valores['vinculo'])) {
        $erros['vinculo'] = 'Selecione um produto e fornecedor.';
    }
    if (!preg_match('/\A[^\p{C}]{1,50}\z/u', $valores['numero_lote'])) {
        $erros['numero_lote'] = 'Informe um número de lote válido com até 50 caracteres.';
    }
    if (!preg_match('/\A[0-9]{1,10}\z/', $valores['quantidade'])
        || $valores['quantidade'] < 1 || $valores['quantidade'] > 4294967295) {
        $erros['quantidade'] = 'Informe uma quantidade inteira entre 1 e 4294967295.';
    }
    if (!dataEntradaValida($valores['data_entrada']) || $valores['data_entrada'] > $hoje) {
        $erros['data_entrada'] = 'Informe uma data de entrada válida, até hoje.';
    }
    if ($valores['data_validade'] !== '' && (!dataEntradaValida($valores['data_validade'])
        || (!isset($erros['data_entrada']) && $valores['data_validade'] < $valores['data_entrada']))) {
        $erros['data_validade'] = 'Informe uma validade igual ou posterior à data de entrada.';
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require __DIR__ . '/../includes/conexao.php';
    $consulta = mysqli_query($conexao, '
        SELECT pf.produto_id, pf.fornecedor_id, p.nome AS produto, f.nome AS fornecedor
        FROM produto_fornecedor pf
        JOIN produtos p ON p.id = pf.produto_id
        JOIN fornecedores f ON f.id = pf.fornecedor_id
        ORDER BY p.nome, f.nome, pf.produto_id, pf.fornecedor_id
    ');
    while ($linha = mysqli_fetch_assoc($consulta)) {
        $chave = $linha['produto_id'] . ':' . $linha['fornecedor_id'];
        $vinculos[$chave] = $linha['produto'] . ' — ' . $linha['fornecedor'];
    }
    mysqli_free_result($consulta);

    if ($enviado && !isset($vinculos[$valores['vinculo']])) {
        $erros['vinculo'] = 'Selecione um vínculo existente entre produto e fornecedor.';
    }

    if ($enviado && !$erros) {
        [$produtoId, $fornecedorId] = explode(':', $valores['vinculo']);
        $numeroLote = $valores['numero_lote'];
        $validade = $valores['data_validade'] === '' ? null : $valores['data_validade'];
        $quantidade = $valores['quantidade'];
        $dataMovimentacao = $valores['data_entrada'] . ' ' . $agora->format('H:i:s');

        mysqli_begin_transaction($conexao);
        try {
            // A chave unica do lote permite reutiliza-lo e bloqueia entradas concorrentes.
            $consulta = mysqli_prepare($conexao, '
                INSERT INTO lotes (produto_id, fornecedor_id, numero_lote, data_validade)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
            ');
            mysqli_stmt_bind_param($consulta, 'ssss', $produtoId, $fornecedorId, $numeroLote, $validade);
            mysqli_stmt_execute($consulta);
            $loteId = (string) mysqli_insert_id($conexao);
            mysqli_stmt_close($consulta);

            $consulta = mysqli_prepare($conexao, 'SELECT data_validade FROM lotes WHERE id = ? FOR UPDATE');
            mysqli_stmt_bind_param($consulta, 's', $loteId);
            mysqli_stmt_execute($consulta);
            mysqli_stmt_bind_result($consulta, $validadeExistente);
            mysqli_stmt_fetch($consulta);
            mysqli_stmt_close($consulta);

            if ($validadeExistente !== $validade) {
                throw new DomainException('Informe a mesma validade já cadastrada para este lote.');
            }

            $consulta = mysqli_prepare($conexao, "
                INSERT INTO movimentacoes (lote_id, tipo, quantidade, data_movimentacao)
                VALUES (?, 'entrada', ?, ?)
            ");
            mysqli_stmt_bind_param($consulta, 'sss', $loteId, $quantidade, $dataMovimentacao);
            mysqli_stmt_execute($consulta);
            mysqli_stmt_close($consulta);

            $consulta = mysqli_prepare($conexao, "
                SELECT COALESCE(SUM(CASE tipo
                    WHEN 'entrada' THEN CAST(quantidade AS SIGNED)
                    WHEN 'saida' THEN -CAST(quantidade AS SIGNED)
                END), 0)
                FROM movimentacoes
                WHERE lote_id = ?
            ");
            mysqli_stmt_bind_param($consulta, 's', $loteId);
            mysqli_stmt_execute($consulta);
            mysqli_stmt_bind_result($consulta, $saldoLote);
            mysqli_stmt_fetch($consulta);
            mysqli_stmt_close($consulta);

            mysqli_commit($conexao);
            $_SESSION['entrada_lote_saldo'] = $saldoLote;
            unset($_SESSION['entrada_lote_token']);
            header('Location: registrar-entrada-de-lote.php', true, 303);
            exit;
        } catch (DomainException $excecao) {
            mysqli_rollback($conexao);
            $erros['data_validade'] = $excecao->getMessage();
        } catch (Throwable $excecao) {
            mysqli_rollback($conexao);
            throw $excecao;
        }
    }
} catch (mysqli_sql_exception $excecao) {
    error_log($excecao->getMessage());
    $erroBanco = 'Não foi possível acessar o estoque ou registrar a entrada. Tente novamente.';
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<h1 class="mb-3">Registrar entrada de lote</h1>

<?php if ($saldo !== null): ?>
    <div class="alert alert-success" role="status">Entrada registrada. Saldo do lote: <?= htmlspecialchars((string) $saldo) ?>.</div>
<?php endif; ?>
<?php if ($erroBanco !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= $erroBanco ?></div>
<?php elseif (!$vinculos): ?>
    <div class="alert alert-info" role="status">Cadastre um vínculo entre produto e fornecedor para registrar uma entrada.</div>
<?php endif; ?>
<?php if (isset($erros['token'])): ?>
    <div class="alert alert-danger" role="alert"><?= $erros['token'] ?></div>
<?php endif; ?>

<?php if ($vinculos): ?>
    <form method="post" action="registrar-entrada-de-lote.php" class="card card-body col-lg-6">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['entrada_lote_token']) ?>">
        <div class="mb-3">
            <label class="form-label" for="vinculo">Produto e fornecedor</label>
            <select class="form-select<?= isset($erros['vinculo']) ? ' is-invalid' : '' ?>" id="vinculo" name="vinculo" required
                <?php if (isset($erros['vinculo'])): ?>aria-invalid="true" aria-describedby="vinculo-erro"<?php endif; ?>>
                <option value="">Selecione</option>
                <?php foreach ($vinculos as $chave => $rotulo): ?>
                    <option value="<?= htmlspecialchars($chave) ?>" <?= $valores['vinculo'] === $chave ? 'selected' : '' ?>>
                        <?= htmlspecialchars($rotulo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($erros['vinculo'])): ?>
                <div class="invalid-feedback" id="vinculo-erro"><?= $erros['vinculo'] ?></div>
            <?php endif; ?>
        </div>

        <?php foreach ($campos as $campo => [$rotulo, $tipo, $atributos]): ?>
            <div class="mb-3">
                <label class="form-label" for="<?= $campo ?>"><?= $rotulo ?></label>
                <input class="form-control<?= isset($erros[$campo]) ? ' is-invalid' : '' ?>"
                    type="<?= $tipo ?>" id="<?= $campo ?>" name="<?= $campo ?>"
                    value="<?= htmlspecialchars($valores[$campo], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $atributos ?>
                    <?php if (isset($erros[$campo])): ?>aria-invalid="true" aria-describedby="<?= $campo ?>-erro"<?php endif; ?>>
                <?php if (isset($erros[$campo])): ?>
                    <div class="invalid-feedback" id="<?= $campo ?>-erro"><?= $erros[$campo] ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit">Registrar entrada</button>
            <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
        </div>
    </form>
<?php else: ?>
    <a class="btn btn-outline-secondary" href="index.php">Voltar ao estoque</a>
<?php endif; ?>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
