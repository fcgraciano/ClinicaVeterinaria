<?php
$base = '../../';
include __DIR__ . '/../../includes/cabecalho.php';
include __DIR__ . '/../../includes/conexao.php';
include __DIR__ . '/../../includes/funcoes.php';

$erro = '';

$animais = mysqli_fetch_all(
    mysqli_query($conexao, 'SELECT Animalid, Nome, Especie FROM animais ORDER BY Nome'),
    MYSQLI_ASSOC
);

$tipos = mysqli_fetch_all(
    mysqli_query($conexao, 'SELECT DISTINCT TipoVacina FROM vacinas ORDER BY TipoVacina'),
    MYSQLI_ASSOC
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $animalid  = (int) ($_POST['animalid'] ?? 0);
    $tipo      = trim($_POST['tipo'] ?? '');
    $dose      = trim($_POST['dose'] ?? '');
    $aplicacao = trim($_POST['aplicacao'] ?? '');
    $retorno   = trim($_POST['retorno'] ?? '');

    if ($animalid <= 0) {
        $erro = 'Selecione o animal.';
    } elseif ($tipo === '') {
        $erro = 'Informe o tipo da vacina.';
    } elseif (mb_strlen($tipo) > 100) {
        $erro = 'O tipo da vacina deve ter no máximo 100 caracteres.';
    } elseif (!ctype_digit($dose) || (int) $dose < 1) {
        $erro = 'Informe a dose (número maior que zero).';
    } elseif (!data_valida($aplicacao)) {
        $erro = 'Informe uma data de aplicação válida.';
    } elseif ($retorno !== '' && !data_valida($retorno)) {
        $erro = 'Informe uma data de retorno válida.';
    } elseif ($retorno !== '' && $retorno < $aplicacao) {
        $erro = 'A data de retorno não pode ser anterior à aplicação.';
    } else {
        $stmt = mysqli_prepare($conexao, 'SELECT Animalid FROM animais WHERE Animalid = ?');
        mysqli_stmt_bind_param($stmt, 'i', $animalid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) === 0) {
            $erro = 'Selecione um animal válido.';
        } else {
            $doseInt = (int) $dose;
            $retornoParam = $retorno === '' ? null : $retorno;

            $sql = 'INSERT INTO vacinas_aplicacadas (DataAplicacao, Retorno, dose, TipoVacina, Animalid)
                    VALUES (?, ?, ?, ?, ?)';
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, 'ssisi', $aplicacao, $retornoParam, $doseInt, $tipo, $animalid);

            if (mysqli_stmt_execute($stmt)) {
                header('Location: index.php?ok=1');
                exit;
            }

            $erro = 'Erro ao salvar a aplicação: ' . mysqli_error($conexao);
        }
    }
}

$aplicacoes = mysqli_fetch_all(
    mysqli_query(
        $conexao,
        'SELECT ap.Aplicacaoid, ap.DataAplicacao, ap.Retorno, ap.dose, ap.TipoVacina, an.Nome AS Animal
           FROM vacinas_aplicacadas ap
           LEFT JOIN animais an ON an.Animalid = ap.Animalid
          ORDER BY ap.DataAplicacao DESC, ap.Aplicacaoid DESC'
    ),
    MYSQLI_ASSOC
);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1">Aplicação Vacinas</h1>
        <p class="text-secondary mb-0">Registro das doses aplicadas</p>
    </div>
    <a href="../index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger"><?= h($erro) ?></div>
<?php endif; ?>

<?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success">Aplicação registrada com sucesso!</div>
<?php endif; ?>

<?php if (count($animais) === 0): ?>
    <div class="alert alert-warning">
        Nenhum animal cadastrado ainda.
        <a href="<?= $base ?>cadastro/animais/index.php" class="alert-link">Cadastre um animal</a>
        para poder registrar a aplicação.
    </div>
<?php endif; ?>

<div class="row g-4">

    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">
                    <i class="bi bi-plus-circle me-1"></i> Nova aplicação
                </h2>

                <form method="post" action="index.php" class="row g-3">
                    <div class="col-12">
                        <label for="animalid" class="form-label">Animal</label>
                        <select class="form-select" id="animalid" name="animalid" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($animais as $animal): ?>
                                <option value="<?= (int) $animal['Animalid'] ?>"
                                    <?= (int) ($_POST['animalid'] ?? 0) === (int) $animal['Animalid'] ? 'selected' : '' ?>>
                                    <?= h($animal['Nome']) ?> (<?= h($animal['Especie']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="tipo" class="form-label">Tipo da vacina</label>
                        <input type="text" class="form-control" id="tipo" name="tipo"
                               list="tipos-vacina" maxlength="100" required
                               placeholder="Ex.: V8, Antirrábica..."
                               value="<?= h($_POST['tipo'] ?? '') ?>">
                        <datalist id="tipos-vacina">
                            <?php foreach ($tipos as $tipo): ?>
                                <option value="<?= h($tipo['TipoVacina']) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <div class="form-text">Os tipos já cadastrados aparecem como sugestão.</div>
                    </div>

                    <div class="col-12 col-sm-4">
                        <label for="dose" class="form-label">Dose</label>
                        <input type="number" class="form-control" id="dose" name="dose"
                               min="1" step="1" required placeholder="1"
                               value="<?= h($_POST['dose'] ?? '') ?>">
                    </div>

                    <div class="col-12 col-sm-8">
                        <label for="aplicacao" class="form-label">Data da aplicação</label>
                        <input type="date" class="form-control" id="aplicacao" name="aplicacao"
                               required value="<?= h($_POST['aplicacao'] ?? date('Y-m-d')) ?>">
                    </div>

                    <div class="col-12">
                        <label for="retorno" class="form-label">Retorno (opcional)</label>
                        <input type="date" class="form-control" id="retorno" name="retorno"
                               value="<?= h($_POST['retorno'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-sistema" <?= count($animais) === 0 ? 'disabled' : '' ?>>
                            <i class="bi bi-check-lg"></i> Salvar aplicação
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
                    <i class="bi bi-list-ul me-1"></i> Aplicações registradas
                </h2>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Animal</th>
                                <th>Vacina</th>
                                <th class="text-end">Dose</th>
                                <th>Aplicação</th>
                                <th>Retorno</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($aplicacoes) === 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-3">
                                        Nenhuma aplicação registrada.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($aplicacoes as $item): ?>
                                    <tr>
                                        <td><?= h($item['Animal'] ?? '—') ?></td>
                                        <td><?= h($item['TipoVacina']) ?></td>
                                        <td class="text-end"><?= (int) $item['dose'] ?></td>
                                        <td><?= data_br($item['DataAplicacao']) ?></td>
                                        <td><?= data_br($item['Retorno']) ?></td>
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
