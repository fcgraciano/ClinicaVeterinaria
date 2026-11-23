<?php
session_start();

$saldo = $_SESSION['saida_estoque_saldo'] ?? null;
unset($_SESSION['saida_estoque_saldo']);
$_SESSION['saida_estoque_token'] ??= bin2hex(random_bytes(32));

$loteId = '';
$quantidade = '';
$lotes = [];
$erros = [];
$erroBanco = '';
$enviado = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($enviado) {
    $token = $_POST['token'] ?? '';
    $loteId = is_string($_POST['lote_id'] ?? null) ? $_POST['lote_id'] : '';
    $quantidade = is_string($_POST['quantidade'] ?? null) ? trim($_POST['quantidade']) : '';

    if (!is_string($token) || !hash_equals($_SESSION['saida_estoque_token'], $token)) {
        $erros['token'] = 'O formulário expirou ou já foi enviado. Revise os dados e tente novamente.';
    }
    if (!preg_match('/\A[1-9][0-9]{0,9}\z/', $loteId) || $loteId > 4294967295) {
        $erros['lote'] = 'Selecione um lote válido.';
    }
    if (!preg_match('/\A[1-9][0-9]{0,9}\z/', $quantidade) || $quantidade > 4294967295) {
        $erros['quantidade'] = 'Informe uma quantidade inteira entre 1 e 4294967295.';
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require __DIR__ . '/../includes/conexao.php';

    if ($enviado && !$erros) {
        mysqli_begin_transaction($conexao);
        try {
            // Usa o mesmo bloqueio por lote das demais movimentacoes de estoque.
            $consulta = mysqli_prepare($conexao, 'SELECT id FROM lotes WHERE id = ? FOR UPDATE');
            mysqli_stmt_bind_param($consulta, 's', $loteId);
            mysqli_stmt_execute($consulta);
            mysqli_stmt_bind_result($consulta, $loteEncontrado);
            $existe = mysqli_stmt_fetch($consulta);
            mysqli_stmt_close($consulta);

            if (!$existe) {
                $erros['lote'] = 'O lote selecionado não existe.';
            } else {
                $consulta = mysqli_prepare($conexao, "
                    SELECT saldo, saldo - CAST(? AS SIGNED)
                    FROM (
                        SELECT COALESCE(SUM(CASE tipo
                            WHEN 'entrada' THEN CAST(quantidade AS SIGNED)
                            WHEN 'saida' THEN -CAST(quantidade AS SIGNED)
                        END), 0) AS saldo
                        FROM movimentacoes
                        WHERE lote_id = ?
                    ) AS saldo_lote
                ");
                mysqli_stmt_bind_param($consulta, 'ss', $quantidade, $loteId);
                mysqli_stmt_execute($consulta);
                mysqli_stmt_bind_result($consulta, $saldoAtual, $saldoRestante);
                mysqli_stmt_fetch($consulta);
                mysqli_stmt_close($consulta);

                if ($saldoRestante < 0) {
                    $erros['quantidade'] = 'Saldo insuficiente. Saldo disponível no lote: ' . $saldoAtual . '.';
                }
            }

            if ($erros) {
                mysqli_rollback($conexao);
            } else {
                $consulta = mysqli_prepare($conexao, "
                    INSERT INTO movimentacoes (lote_id, tipo, quantidade) VALUES (?, 'saida', ?)
                ");
                mysqli_stmt_bind_param($consulta, 'ss', $loteId, $quantidade);
                mysqli_stmt_execute($consulta);
                mysqli_stmt_close($consulta);
                mysqli_commit($conexao);

                $_SESSION['saida_estoque_saldo'] = $saldoRestante;
                unset($_SESSION['saida_estoque_token']);
                header('Location: registrar-saida-de-estoque.php', true, 303);
                exit;
            }
        } catch (Throwable $excecao) {
            mysqli_rollback($conexao);
            throw $excecao;
        }
    }

    $consulta = mysqli_query($conexao, '
        SELECT l.id, p.nome AS produto, l.numero_lote, f.nome AS fornecedor
        FROM lotes l
        JOIN produtos p ON p.id = l.produto_id
        JOIN fornecedores f ON f.id = l.fornecedor_id
        ORDER BY p.nome, l.numero_lote, f.nome, l.id
    ');
    $lotes = mysqli_fetch_all($consulta, MYSQLI_ASSOC);
    mysqli_free_result($consulta);
} catch (mysqli_sql_exception $excecao) {
    error_log($excecao->getMessage());
    $erroBanco = 'Não foi possível acessar o estoque ou registrar a saída. Tente novamente.';
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<h1 class="mb-3">Registrar saída de estoque</h1>

<?php if ($saldo !== null): ?>
    <div class="alert alert-success" role="status">
        Saída registrada. Saldo do lote: <?= htmlspecialchars((string) $saldo) ?>.
    </div>
<?php endif; ?>
<?php if ($erroBanco !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= $erroBanco ?></div>
<?php elseif (!$lotes): ?>
    <div class="alert alert-info" role="status">Cadastre um lote para registrar uma saída.</div>
<?php endif; ?>
<?php if (isset($erros['token'])): ?>
    <div class="alert alert-danger" role="alert"><?= $erros['token'] ?></div>
<?php endif; ?>

<?php if ($lotes): ?>
    <form method="post" action="registrar-saida-de-estoque.php" class="card card-body col-lg-6">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['saida_estoque_token']) ?>">
        <div class="mb-3">
            <label class="form-label" for="lote_id">Lote</label>
            <select class="form-select<?= isset($erros['lote']) ? ' is-invalid' : '' ?>" id="lote_id" name="lote_id" required
                <?php if (isset($erros['lote'])): ?>aria-invalid="true" aria-describedby="lote-erro"<?php endif; ?>>
                <option value="">Selecione</option>
                <?php foreach ($lotes as $lote): ?>
                    <option value="<?= htmlspecialchars((string) $lote['id']) ?>" <?= $loteId === (string) $lote['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lote['produto'] . ' — Lote ' . $lote['numero_lote'] . ' — ' . $lote['fornecedor']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($erros['lote'])): ?>
                <div class="invalid-feedback" id="lote-erro"><?= $erros['lote'] ?></div>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <label class="form-label" for="quantidade">Quantidade</label>
            <input class="form-control<?= isset($erros['quantidade']) ? ' is-invalid' : '' ?>" type="number"
                id="quantidade" name="quantidade" min="1" max="4294967295" step="1" required
                value="<?= htmlspecialchars($quantidade, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                <?php if (isset($erros['quantidade'])): ?>aria-invalid="true" aria-describedby="quantidade-erro"<?php endif; ?>>
            <?php if (isset($erros['quantidade'])): ?>
                <div class="invalid-feedback" id="quantidade-erro"><?= htmlspecialchars($erros['quantidade']) ?></div>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit">Registrar saída</button>
            <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
        </div>
    </form>
<?php else: ?>
    <a class="btn btn-outline-secondary" href="index.php">Voltar ao estoque</a>
<?php endif; ?>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
