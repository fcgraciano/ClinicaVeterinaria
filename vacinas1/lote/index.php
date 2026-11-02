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

    if ($tipo === '') {
        $erro = 'Informe o tipo da vacina.';
    } elseif (mb_strlen($tipo) > 100) {
        $erro = 'O tipo da vacina deve ter no máximo 100 caracteres.';
    } elseif (!data_valida($validade)) {
        $erro = 'Informe uma data de validade válida.';
    } elseif (!ctype_digit($quantidade)) {
        $erro = 'Informe a quantidade do lote (somente números).';
    } else {
        $qtd = (int) $quantidade;

        $sql = 'INSERT INTO vacinas (TipoVacina, DataValidade, QuantidadeLote) VALUES (?, ?, ?)';
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, 'ssi', $tipo, $validade, $qtd);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: index.php?ok=1');
            exit;
        }

        $erro = 'Erro ao salvar o lote: ' . mysqli_error($conexao);
    }
}

$lotes = mysqli_fetch_all(
    mysqli_query($conexao, 'SELECT Vacinaid, TipoVacina, DataValidade, QuantidadeLote FROM vacinas ORDER BY DataValidade, TipoVacina'),
    MYSQLI_ASSOC
);
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
        <div class="card h-100">
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
                        <button type="submit" class="btn btn-sistema">
                            <i class="bi bi-check-lg"></i> Salvar lote
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">
                    <i class="bi bi-list-ul me-1"></i> Lotes cadastrados
                </h2>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Vacina</th>
                                <th>Validade</th>
                                <th class="text-end">Quantidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($lotes) === 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-secondary py-3">
                                        Nenhum lote cadastrado.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lotes as $lote): ?>
                                    <tr>
                                        <td><?= h($lote['TipoVacina']) ?></td>
                                        <td>
                                            <?= data_br($lote['DataValidade']) ?>
                                            <?php if ($lote['DataValidade'] < date('Y-m-d')): ?>
                                                <span class="badge text-bg-danger">Vencido</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?= $lote['QuantidadeLote'] === null ? '—' : (int) $lote['QuantidadeLote'] ?>
                                        </td>
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
