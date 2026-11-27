<?php
session_start();
require __DIR__ . '/lotes-fefo.php';

$saldo = $_SESSION['perda_estoque_saldo'] ?? null;
unset($_SESSION['perda_estoque_saldo']);
$_SESSION['perda_estoque_token'] ??= bin2hex(random_bytes(32));

$enviado = $_SERVER['REQUEST_METHOD'] === 'POST';
$valores = ['lote_id' => '', 'motivo' => '', 'quantidade' => '', 'justificativa' => '', 'responsavel' => ''];
$motivos = ['perda' => 'Perda', 'descarte' => 'Descarte'];
$erros = [];
$lotes = [];
$erroBanco = false;

if ($enviado) {
    $token = $_POST['token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['perda_estoque_token'], $token)) {
        $erros['token'] = 'O formulário expirou ou já foi enviado. Revise os dados e tente novamente.';
    }

    foreach (array_keys($valores) as $campo) {
        $valor = $_POST[$campo] ?? '';
        $valores[$campo] = is_string($valor) ? trim($valor) : '';
    }

    $loteId = $valores['lote_id'];
    $motivo = $valores['motivo'];
    $quantidade = str_replace(',', '.', $valores['quantidade']);
    $justificativa = $valores['justificativa'];
    $responsavel = $valores['responsavel'];

    if (!preg_match('/\A[1-9][0-9]{0,9}\z/', $loteId) || $loteId > 4294967295) {
        $erros['lote_id'] = 'Selecione um lote válido.';
    }
    if (!isset($motivos[$motivo])) {
        $erros['motivo'] = 'Selecione perda ou descarte.';
    }
    if (!preg_match('/\A[0-9]{1,15}(?:\.[0-9]{1,9})?\z/', $quantidade)
        || trim($quantidade, '0.') === '') {
        $erros['quantidade'] = 'Informe uma quantidade positiva com até nove casas decimais.';
    }
    if (!preg_match('/\A.{15,1000}\z/us', $justificativa)
        || !preg_match('/\S[\s\S]*\S/u', $justificativa)) {
        $erros['justificativa'] = 'Descreva o ocorrido com 15 a 1.000 caracteres.';
    }
    if (!preg_match('/\A(?=.*[^\s\p{C}])[^\p{C}]{1,150}\z/u', $responsavel)) {
        $erros['responsavel'] = 'Informe a identificação do responsável com até 150 caracteres.';
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require __DIR__ . '/../includes/conexao.php';

    if ($enviado && !$erros) {
        mysqli_begin_transaction($conexao);
        try {
            // O mesmo bloqueio usado pela saida normal serializa as baixas do lote.
            $consulta = mysqli_prepare($conexao, 'SELECT id FROM lotes WHERE id = ? FOR UPDATE');
            mysqli_stmt_bind_param($consulta, 's', $loteId);
            mysqli_stmt_execute($consulta);
            mysqli_stmt_bind_result($consulta, $loteEncontrado);
            $existe = mysqli_stmt_fetch($consulta);
            mysqli_stmt_close($consulta);

            if (!$existe) {
                $erros['lote_id'] = 'O lote selecionado não existe.';
            } else {
                $consulta = mysqli_prepare($conexao, "
                    SELECT COALESCE(SUM(CASE tipo
                        WHEN 'entrada' THEN quantidade
                        WHEN 'saida' THEN -CAST(quantidade AS DECIMAL(24,9))
                    END), 0) - CAST(? AS DECIMAL(24,9))
                    FROM movimentacoes WHERE lote_id = ?
                ");
                mysqli_stmt_bind_param($consulta, 'ss', $quantidade, $loteId);
                mysqli_stmt_execute($consulta);
                mysqli_stmt_bind_result($consulta, $saldoRestante);
                mysqli_stmt_fetch($consulta);
                mysqli_stmt_close($consulta);
                if ($saldoRestante < 0) {
                    $erros['quantidade'] = 'A quantidade informada excede o saldo do lote.';
                }
            }

            if ($erros) {
                mysqli_rollback($conexao);
            } else {
                $consulta = mysqli_prepare($conexao, "
                    INSERT INTO movimentacoes
                        (lote_id, tipo, quantidade, motivo_saida, justificativa, responsavel_movimentacao)
                    VALUES (?, 'saida', ?, ?, ?, ?)
                ");
                mysqli_stmt_bind_param($consulta, 'sssss', $loteId, $quantidade, $motivo, $justificativa, $responsavel);
                mysqli_stmt_execute($consulta);
                mysqli_stmt_close($consulta);
                mysqli_commit($conexao);

                $_SESSION['perda_estoque_saldo'] = $saldoRestante;
                unset($_SESSION['perda_estoque_token']);
                header('Location: registrar-perda-ou-descarte.php', true, 303);
                exit;
            }
        } catch (Throwable $excecao) {
            mysqli_rollback($conexao);
            throw $excecao;
        }
    }

    // Lotes vencidos ou bloqueados podem ser descartados.
    $lotes = consultarLotesFefo($conexao, null);
} catch (mysqli_sql_exception $excecao) {
    error_log($excecao->getMessage());
    $erroBanco = true;
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<h1 class="mb-3">Registrar perda ou descarte</h1>

<?php if ($saldo !== null): ?>
    <div class="alert alert-success" role="status">
        Movimentação registrada. Saldo do lote: <?= htmlspecialchars(formatarQuantidadeEstoque((string) $saldo)) ?>.
    </div>
<?php endif; ?>
<?php if ($erroBanco): ?>
    <div class="alert alert-danger" role="alert">Não foi possível acessar ou atualizar o estoque. Tente novamente.</div>
<?php elseif (!$lotes): ?>
    <div class="alert alert-info" role="status">Nenhum lote tem saldo disponível.</div>
<?php endif; ?>
<?php if ($erros): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
            <?php foreach ($erros as $erro): ?>
                <li><?= htmlspecialchars($erro) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($lotes): ?>
    <form method="post" action="registrar-perda-ou-descarte.php" class="card card-body col-lg-6 mb-3">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['perda_estoque_token']) ?>">
        <div class="mb-3">
            <label class="form-label" for="lote_id">Lote</label>
            <select class="form-select" id="lote_id" name="lote_id" required>
                <option value="">Selecione</option>
                <?php foreach ($lotes as $lote): ?>
                    <option value="<?= htmlspecialchars((string) $lote['id']) ?>" <?= $valores['lote_id'] === (string) $lote['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lote['produto'] . ' — ' . $lote['numero_lote'] . ' — ' . $lote['fornecedor']
                            . ' — Saldo ' . formatarQuantidadeEstoque((string) $lote['saldo'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="motivo">Tipo de movimentação</label>
            <select class="form-select" id="motivo" name="motivo" required>
                <option value="">Selecione</option>
                <?php foreach ($motivos as $valor => $rotulo): ?>
                    <option value="<?= $valor ?>" <?= $valores['motivo'] === $valor ? 'selected' : '' ?>><?= $rotulo ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="quantidade">Quantidade em unidades de estoque</label>
            <input class="form-control" id="quantidade" name="quantidade" inputmode="decimal" maxlength="25"
                value="<?= htmlspecialchars($valores['quantidade']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="justificativa">Justificativa</label>
            <textarea class="form-control" id="justificativa" name="justificativa" rows="3" minlength="15" maxlength="1000"
                required><?= htmlspecialchars($valores['justificativa']) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label" for="responsavel">Identificação do responsável</label>
            <input class="form-control" id="responsavel" name="responsavel" maxlength="150"
                value="<?= htmlspecialchars($valores['responsavel']) ?>" required>
        </div>
        <p class="form-text">Ao confirmar, a quantidade será baixada do saldo do lote e registrada no histórico com motivo, justificativa e responsável.</p>
        <button class="btn btn-primary align-self-start" type="submit"
            onclick="return confirm('Confirma o registro de ' + document.getElementById('motivo').selectedOptions[0].text.toLowerCase() + '? A quantidade será baixada do saldo do lote.');">
            Confirmar e registrar movimentação
        </button>
    </form>
<?php endif; ?>

<a class="btn btn-outline-secondary" href="index.php">Voltar ao estoque</a>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
