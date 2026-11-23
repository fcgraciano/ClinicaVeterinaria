<?php
$base = '../../';
include __DIR__ . '/../../includes/cabecalho.php';
include __DIR__ . '/../../includes/conexao.php';
include __DIR__ . '/../../includes/funcoes.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo       = trim($_POST['tipo'] ?? '');
    $validade   = trim($_POST['validade'] ?? '');
    $quantidade = trim($_POST['quantidade'] ?? '');
    $valor      = numero_br($_POST['valor'] ?? '');

    if ($tipo === '') {
        $erro = 'Informe o tipo da vacina.';
    } elseif (mb_strlen($tipo) > 100) {
        $erro = 'O tipo da vacina deve ter no máximo 100 caracteres.';
    } elseif (!data_valida($validade)) {
        $erro = 'Informe uma data de validade válida.';
    } elseif (!ctype_digit($quantidade)) {
        $erro = 'Informe a quantidade do lote (somente números).';
    } elseif ($valor === null || $valor < 0 || $valor > 99999999.99) {
        $erro = 'Informe o preço da vacina (ex.: 45,90).';
    } else {
        $qtd = (int) $quantidade;

        $sql = 'INSERT INTO vacinas (TipoVacina, DataValidade, QuantidadeLote, Valor) VALUES (?, ?, ?, ?)';
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, 'ssid', $tipo, $validade, $qtd, $valor);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: index.php?ok=1');
            exit;
        }

        $erro = 'Erro ao salvar o lote: ' . mysqli_error($conexao);
    }
}

$lotes = mysqli_fetch_all(
    mysqli_query(
        $conexao,
        'SELECT Vacinaid, TipoVacina, DataValidade, QuantidadeLote, Valor
           FROM vacinas
          ORDER BY DataValidade, TipoVacina'
    ),
    MYSQLI_ASSOC
);

$hoje = date('Y-m-d');
$lotesValidos = [];
$lotesVencidos = [];

foreach ($lotes as $lote) {
    if ($lote['DataValidade'] < $hoje) {
        $lotesVencidos[] = $lote;
    } else {
        $lotesValidos[] = $lote;
    }
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1">Lote Vacinas</h1>
        <p class="text-secondary mb-0">Cadastro dos lotes recebidos</p>
    </div>
    <a href="../index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger"><?= h($erro) ?></div>
<?php endif; ?>

<?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success">Lote cadastrado com sucesso!</div>
<?php endif; ?>

<div class="row g-4">

    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-body">
                <h2 class="h6 mb-3">
                    <i class="bi bi-plus-circle me-1"></i> Novo lote
                </h2>

                <form method="post" action="index.php" class="row g-3">
                    <div class="col-12">
                        <label for="tipo" class="form-label">Tipo da vacina</label>
                        <input type="text" class="form-control" id="tipo" name="tipo"
                               maxlength="100" required placeholder="Ex.: V8, Antirrábica..."
                               value="<?= h($_POST['tipo'] ?? '') ?>">
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="validade" class="form-label">Data de validade</label>
                        <input type="date" class="form-control" id="validade" name="validade"
                               required value="<?= h($_POST['validade'] ?? '') ?>">
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="quantidade" class="form-label">Quantidade</label>
                        <input type="number" class="form-control" id="quantidade" name="quantidade"
                               min="0" step="1" required placeholder="0"
                               value="<?= h($_POST['quantidade'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <label for="valor" class="form-label">Valor (R$)</label>
                        <input type="text" class="form-control" id="valor" name="valor"
                               inputmode="decimal" required placeholder="Ex.: 45,90"
                               value="<?= h($_POST['valor'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-sistema">
                            <i class="bi bi-check-lg"></i> Salvar lote
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">

        <div class="card border-success mb-4">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">
                    <i class="bi bi-check-circle me-1"></i> Lotes dentro da validade
                </span>
                <span class="badge text-bg-light text-success"><?= count($lotesValidos) ?></span>
            </div>

            <div class="card-body bg-success-subtle">
                <div class="table-responsive">
                    <table class="table table-sm table-success align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Vacina</th>
                                <th>Validade</th>
                                <th class="text-end">Quantidade</th>
                                <th class="text-end">Preço</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($lotesValidos) === 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-secondary py-3">
                                        Nenhum lote dentro da validade.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lotesValidos as $lote): ?>
                                    <tr>
                                        <td><?= h($lote['TipoVacina']) ?></td>
                                        <td><?= data_br($lote['DataValidade']) ?></td>
                                        <td class="text-end">
                                            <?= $lote['QuantidadeLote'] === null ? '—' : (int) $lote['QuantidadeLote'] ?>
                                        </td>
                                        <td class="text-end"><?= moeda_br($lote['Valor']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-danger">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">
                    <i class="bi bi-exclamation-triangle me-1"></i> Lotes vencidos
                </span>
                <span class="badge text-bg-light text-danger"><?= count($lotesVencidos) ?></span>
            </div>

            <div class="card-body bg-danger-subtle">
                <div class="table-responsive">
                    <table class="table table-sm table-danger align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Vacina</th>
                                <th>Validade</th>
                                <th class="text-end">Quantidade</th>
                                <th class="text-end">Preço</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($lotesVencidos) === 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-secondary py-3">
                                        Nenhum lote vencido.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lotesVencidos as $lote): ?>
                                    <tr>
                                        <td><?= h($lote['TipoVacina']) ?></td>
                                        <td><?= data_br($lote['DataValidade']) ?></td>
                                        <td class="text-end">
                                            <?= $lote['QuantidadeLote'] === null ? '—' : (int) $lote['QuantidadeLote'] ?>
                                        </td>
                                        <td class="text-end"><?= moeda_br($lote['Valor']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>

<?php include __DIR__ . '/../../includes/rodape.php'; ?>
