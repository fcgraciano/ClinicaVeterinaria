<?php
session_start();
require __DIR__ . '/lotes-fefo.php';

$saldo = $_SESSION['saida_estoque_saldo'] ?? null;
unset($_SESSION['saida_estoque_saldo']);
$_SESSION['saida_estoque_token'] ??= bin2hex(random_bytes(32));

$loteId = '';
$quantidade = '';
$unidade = 'unidade';
$unidades = ['unidade' => 'Unidade', 'ml' => 'mL', 'dose' => 'Dose'];
$lotes = [];
$erros = [];
$erroBanco = '';
$camposResponsavel = [
    'responsavel_tecnico' => ['Nome do responsável técnico', 150],
    'registro_responsavel' => ['Registro profissional', 30],
];
$identificacao = array_fill_keys(array_keys($camposResponsavel), '');
$enviado = $_SERVER['REQUEST_METHOD'] === 'POST';
$fusoEstoque = new DateTimeZone('America/Sao_Paulo');

if ($enviado) {
    $token = $_POST['token'] ?? '';
    $loteId = is_string($_POST['lote_id'] ?? null) ? $_POST['lote_id'] : '';
    $quantidade = is_string($_POST['quantidade'] ?? null) ? trim($_POST['quantidade']) : '';
    $unidadeEnviada = $_POST['unidade'] ?? 'unidade';
    $unidade = is_string($unidadeEnviada) ? $unidadeEnviada : '';

    if (!is_string($token) || !hash_equals($_SESSION['saida_estoque_token'], $token)) {
        $erros['token'] = 'O formulário expirou ou já foi enviado. Revise os dados e tente novamente.';
    }
    if (!preg_match('/\A[1-9][0-9]{0,9}\z/', $loteId) || $loteId > 4294967295) {
        $erros['lote'] = 'Selecione um lote válido.';
    }
    if (!isset($unidades[$unidade])) {
        $erros['unidade'] = 'Selecione uma unidade de medida válida.';
    }
    $quantidade = str_replace(',', '.', $quantidade);
    $formatoQuantidade = $unidade === 'unidade' ? '/\A[1-9][0-9]{0,9}\z/' : '/\A[0-9]{1,10}(?:\.[0-9]{1,3})?\z/';
    if (!preg_match($formatoQuantidade, $quantidade) || $quantidade <= 0 || $quantidade > 4294967295) {
        $erros['quantidade'] = $unidade === 'unidade'
            ? 'Informe uma quantidade inteira entre 1 e 4294967295.'
            : 'Informe uma quantidade positiva até 4294967295, com até três casas decimais.';
    }
    foreach ($camposResponsavel as $campo => [$rotulo, $limite]) {
        $valor = $_POST[$campo] ?? '';
        $identificacao[$campo] = is_string($valor) ? trim($valor) : '';
        if (!is_string($valor) || ($identificacao[$campo] !== '' &&
            !preg_match('/\A(?=.*[^\s\p{C}])[^\p{C}]{1,' . $limite . '}\z/u', $identificacao[$campo]))) {
            $erros[$campo] = 'Informe um valor válido com até ' . $limite . ' caracteres.';
        }
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require __DIR__ . '/../includes/conexao.php';

    if ($enviado && !$erros) {
        mysqli_begin_transaction($conexao);
        try {
            // Usa o mesmo bloqueio por lote das demais movimentacoes de estoque.
            $consulta = mysqli_prepare($conexao, '
                SELECT l.data_validade, l.status, l.numero_lote, p.classificacao,
                       p.fracionamento_habilitado, p.fator_ml, p.fator_dose
                FROM lotes l
                JOIN produtos p ON p.id = l.produto_id
                WHERE l.id = ?
                FOR UPDATE
            ');
            mysqli_stmt_bind_param($consulta, 's', $loteId);
            mysqli_stmt_execute($consulta);
            mysqli_stmt_bind_result(
                $consulta, $dataValidade, $statusLote, $numeroLote, $classificacao,
                $fracionamentoHabilitado, $fatorMl, $fatorDose
            );
            $existe = mysqli_stmt_fetch($consulta);
            mysqli_stmt_close($consulta);

            $hoje = (new DateTimeImmutable('now', $fusoEstoque))->format('Y-m-d');

            if (!$existe) {
                $erros['lote'] = 'O lote selecionado não existe.';
            } elseif ($statusLote === 'bloqueado') {
                $erros['lote'] = 'O lote selecionado está bloqueado para saída.';
            } elseif ($statusLote === 'vencido' || ($dataValidade !== null && $dataValidade < $hoje)) {
                $erros['lote'] = 'O lote selecionado está vencido e não pode ter saída registrada.';
            } elseif (in_array($classificacao, ['vacina', 'controle_especial'], true)) {
                if (!preg_match('/[^\s\p{C}]/u', $numeroLote)) {
                    $erros['lote'] = 'A saída exige um lote com número registrado.';
                }
                foreach ($identificacao as $campo => $valor) {
                    if ($valor === '') {
                        $erros[$campo] = 'Preencha este campo para a saída de vacina ou medicamento de controle especial.';
                    }
                }
            }

            if (!$erros) {
                $fator = ['unidade' => '1', 'ml' => $fatorMl, 'dose' => $fatorDose][$unidade];
                if ($unidade !== 'unidade' && !$fracionamentoHabilitado) {
                    $erros['unidade'] = 'O fracionamento não está habilitado para este produto.';
                } elseif ($fator === null || $fator <= 0 || $fator > 1) {
                    $erros['unidade'] = 'Configure um fator de conversão válido para esta medida.';
                }
            }

            if (!$erros) {
                $consulta = mysqli_prepare($conexao, "
                    SELECT saldo, baixa, saldo - baixa
                    FROM (
                        SELECT COALESCE(SUM(CASE tipo
                            WHEN 'entrada' THEN quantidade
                            WHEN 'saida' THEN -CAST(quantidade AS DECIMAL(24,9))
                        END), 0) AS saldo
                        FROM movimentacoes
                        WHERE lote_id = ?
                    ) AS saldo_lote
                    CROSS JOIN (
                        SELECT CAST(? AS DECIMAL(13,3)) * CAST(? AS DECIMAL(7,6)) AS baixa
                    ) AS conversao
                ");
                mysqli_stmt_bind_param($consulta, 'sss', $loteId, $quantidade, $fator);
                mysqli_stmt_execute($consulta);
                mysqli_stmt_bind_result($consulta, $saldoAtual, $quantidadeBaixa, $saldoRestante);
                mysqli_stmt_fetch($consulta);
                mysqli_stmt_close($consulta);

                if ($saldoRestante < 0) {
                    $erros['quantidade'] = 'Saldo insuficiente. Saldo disponível no lote: '
                        . formatarQuantidadeEstoque((string) $saldoAtual) . '.';
                }
            }

            if ($erros) {
                mysqli_rollback($conexao);
            } else {
                $responsavel = $identificacao['responsavel_tecnico'] !== '' ? $identificacao['responsavel_tecnico'] : null;
                $registro = $identificacao['registro_responsavel'] !== '' ? $identificacao['registro_responsavel'] : null;
                $consulta = mysqli_prepare($conexao, "
                    INSERT INTO movimentacoes (lote_id, tipo, quantidade, responsavel_tecnico, registro_responsavel)
                    VALUES (?, 'saida', ?, ?, ?)
                ");
                mysqli_stmt_bind_param($consulta, 'ssss', $loteId, $quantidadeBaixa, $responsavel, $registro);
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

    $hoje = (new DateTimeImmutable('now', $fusoEstoque))->format('Y-m-d');
    $lotes = consultarLotesFefo($conexao, $hoje);
} catch (mysqli_sql_exception $excecao) {
    error_log($excecao->getMessage());
    $erroBanco = 'Não foi possível acessar o estoque ou registrar a saída. Tente novamente.';
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<h1 class="mb-3">Registrar saída de estoque</h1>

<?php if ($saldo !== null): ?>
    <div class="alert alert-success" role="status">
        Saída registrada. Saldo do lote: <?= htmlspecialchars(formatarQuantidadeEstoque((string) $saldo)) ?> unidade(s).
    </div>
<?php endif; ?>
<?php if ($erroBanco !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= $erroBanco ?></div>
<?php elseif (!$lotes): ?>
    <div class="alert alert-info" role="status">Nenhum lote disponível para saída.</div>
<?php endif; ?>
<?php if (isset($erros['token'])): ?>
    <div class="alert alert-danger" role="alert"><?= $erros['token'] ?></div>
<?php endif; ?>

<?php if ($lotes): ?>
    <form method="post" action="registrar-saida-de-estoque.php" class="card card-body col-lg-6">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['saida_estoque_token']) ?>">
        <div class="mb-3">
            <label class="form-label" for="lote_id">Lote por ordem de validade (FEFO)</label>
            <select class="form-select<?= isset($erros['lote']) ? ' is-invalid' : '' ?>" id="lote_id" name="lote_id" required
                <?php if (isset($erros['lote'])): ?>aria-invalid="true" aria-describedby="lote-erro"<?php endif; ?>>
                <option value="">Selecione</option>
                <?php foreach ($lotes as $lote): ?>
                    <?php
                    $validade = $lote['data_validade'] === null
                        ? 'Sem validade'
                        : 'Validade ' . (new DateTimeImmutable($lote['data_validade']))->format('d/m/Y');
                    $rotuloLote = $lote['produto'] . ' — Lote ' . $lote['numero_lote'] . ' — '
                        . $lote['fornecedor'] . ' — ' . $validade . ' — Saldo '
                        . formatarQuantidadeEstoque((string) $lote['saldo']);
                    if ($lote['classificacao'] !== 'comum') {
                        $rotuloLote .= ' — Exige responsável técnico';
                    }
                    ?>
                    <option value="<?= htmlspecialchars((string) $lote['id']) ?>" <?= $loteId === (string) $lote['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($rotuloLote) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($erros['lote'])): ?>
                <div class="invalid-feedback" id="lote-erro"><?= $erros['lote'] ?></div>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <label class="form-label" for="unidade">Unidade de medida</label>
            <select class="form-select<?= isset($erros['unidade']) ? ' is-invalid' : '' ?>" id="unidade" name="unidade"
                <?php if (isset($erros['unidade'])): ?>aria-invalid="true" aria-describedby="unidade-erro"<?php endif; ?>>
                <?php foreach ($unidades as $valor => $rotulo): ?>
                    <option value="<?= $valor ?>" <?= $unidade === $valor ? 'selected' : '' ?>><?= $rotulo ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($erros['unidade'])): ?>
                <div class="invalid-feedback" id="unidade-erro"><?= $erros['unidade'] ?></div>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <label class="form-label" for="quantidade">Quantidade</label>
            <input class="form-control<?= isset($erros['quantidade']) ? ' is-invalid' : '' ?>" type="number"
                id="quantidade" name="quantidade" min="0.001" max="4294967295" step="0.001" required
                value="<?= htmlspecialchars($quantidade, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                <?php if (isset($erros['quantidade'])): ?>aria-invalid="true" aria-describedby="quantidade-erro"<?php endif; ?>>
            <?php if (isset($erros['quantidade'])): ?>
                <div class="invalid-feedback" id="quantidade-erro"><?= htmlspecialchars($erros['quantidade']) ?></div>
            <?php endif; ?>
        </div>
        <fieldset class="mb-3">
            <legend class="fs-6">Responsável técnico</legend>
            <p class="form-text">Preencha o nome e o registro profissional para vacinas e medicamentos de controle especial.</p>
            <?php foreach ($camposResponsavel as $campo => [$rotulo, $limite]): ?>
                <div class="mb-2">
                    <label class="form-label" for="<?= $campo ?>"><?= $rotulo ?></label>
                    <input class="form-control<?= isset($erros[$campo]) ? ' is-invalid' : '' ?>"
                        type="text" id="<?= $campo ?>" name="<?= $campo ?>" maxlength="<?= $limite ?>"
                        value="<?= htmlspecialchars($identificacao[$campo], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        <?php if (isset($erros[$campo])): ?>aria-invalid="true" aria-describedby="<?= $campo ?>-erro"<?php endif; ?>>
                    <?php if (isset($erros[$campo])): ?>
                        <div class="invalid-feedback" id="<?= $campo ?>-erro"><?= $erros[$campo] ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </fieldset>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit">Registrar saída</button>
            <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
        </div>
    </form>
<?php else: ?>
    <?php foreach (array_merge(['lote', 'unidade', 'quantidade'], array_keys($camposResponsavel)) as $campo): ?>
        <?php if (isset($erros[$campo])): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erros[$campo]) ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <a class="btn btn-outline-secondary" href="index.php">Voltar ao estoque</a>
<?php endif; ?>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
