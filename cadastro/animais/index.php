<?php
$base = '../../';
include __DIR__ . '/../../includes/cabecalho.php';
include __DIR__ . '/../../includes/conexao.php';
include __DIR__ . '/../../includes/funcoes.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome         = trim($_POST['nome'] ?? '');
    $especie      = trim($_POST['especie'] ?? '');
    $raca         = trim($_POST['raca'] ?? '');
    $sexo         = trim($_POST['sexo'] ?? '');
    $nascimento   = trim($_POST['nascimento'] ?? '');
    $peso         = numero_br($_POST['peso'] ?? '');
    $altura       = numero_br($_POST['altura'] ?? '');
    $cor          = trim($_POST['cor'] ?? '');
    $observacoes  = trim($_POST['observacoes'] ?? '');

    if ($nome === '') {
        $erro = 'Informe o nome do animal.';
    } elseif (mb_strlen($nome) > 100) {
        $erro = 'O nome deve ter no máximo 100 caracteres.';
    } elseif ($especie === '') {
        $erro = 'Informe a espécie do animal.';
    } elseif (mb_strlen($especie) > 100) {
        $erro = 'A espécie deve ter no máximo 100 caracteres.';
    } elseif ($raca === '') {
        $erro = 'Informe a raça do animal.';
    } elseif (mb_strlen($raca) > 100) {
        $erro = 'A raça deve ter no máximo 100 caracteres.';
    } elseif (!in_array($sexo, ['Macho', 'Fêmea'], true)) {
        $erro = 'Selecione o sexo do animal.';
    } elseif (!data_valida($nascimento)) {
        $erro = 'Informe uma data de nascimento válida.';
    } elseif ($nascimento > date('Y-m-d')) {
        $erro = 'A data de nascimento não pode ser futura.';
    } elseif ($peso === null || $peso <= 0 || $peso > 999.99) {
        $erro = 'Informe o peso em kg (maior que zero).';
    } elseif ($altura === null || $altura <= 0 || $altura > 999.99) {
        $erro = 'Informe a altura em metros (maior que zero).';
    } elseif ($cor === '') {
        $erro = 'Informe a cor do animal.';
    } elseif (mb_strlen($cor) > 50) {
        $erro = 'A cor deve ter no máximo 50 caracteres.';
    } else {
        $obsParam = $observacoes === '' ? null : $observacoes;

        $sql = 'INSERT INTO animais
                    (Nome, Especie, Raca, Sexo, DataNascimento, Peso, Altura, Cor, Observacoes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            'sssssddss',
            $nome,
            $especie,
            $raca,
            $sexo,
            $nascimento,
            $peso,
            $altura,
            $cor,
            $obsParam
        );

        if (mysqli_stmt_execute($stmt)) {
            header('Location: index.php?ok=1');
            exit;
        }

        $erro = 'Erro ao salvar o animal: ' . mysqli_error($conexao);
    }
}

$animais = mysqli_fetch_all(
    mysqli_query(
        $conexao,
        'SELECT Animalid, Nome, Especie, Raca, Sexo, DataNascimento, Peso, Altura, Cor, Observacoes
           FROM animais
          ORDER BY Nome'
    ),
    MYSQLI_ASSOC
);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1">Animais</h1>
        <p class="text-secondary mb-0">Cadastro dos animais da clínica</p>
    </div>
    <a href="../index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger"><?= h($erro) ?></div>
<?php endif; ?>

<?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success">Animal cadastrado com sucesso!</div>
<?php endif; ?>

<div class="row g-4">

    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">
                    <i class="bi bi-plus-circle me-1"></i> Novo animal
                </h2>

                <form method="post" action="index.php" class="row g-3">
                    <div class="col-12">
                        <label for="nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome"
                               maxlength="100" required placeholder="Ex.: Rex"
                               value="<?= h($_POST['nome'] ?? '') ?>">
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="especie" class="form-label">Espécie</label>
                        <input type="text" class="form-control" id="especie" name="especie"
                               list="especies" maxlength="100" required placeholder="Ex.: Cachorro"
                               value="<?= h($_POST['especie'] ?? '') ?>">
                        <datalist id="especies">
                            <option value="Cachorro"></option>
                            <option value="Gato"></option>
                            <option value="Ave"></option>
                            <option value="Coelho"></option>
                            <option value="Roedor"></option>
                            <option value="Réptil"></option>
                        </datalist>
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="raca" class="form-label">Raça</label>
                        <input type="text" class="form-control" id="raca" name="raca"
                               maxlength="100" required placeholder="Ex.: SRD"
                               value="<?= h($_POST['raca'] ?? '') ?>">
                    </div>

                    <div class="col-12 col-sm-4">
                        <label for="sexo" class="form-label">Sexo</label>
                        <select class="form-select" id="sexo" name="sexo" required>
                            <option value="">Selecione...</option>
                            <option value="Macho" <?= ($_POST['sexo'] ?? '') === 'Macho' ? 'selected' : '' ?>>Macho</option>
                            <option value="Fêmea" <?= ($_POST['sexo'] ?? '') === 'Fêmea' ? 'selected' : '' ?>>Fêmea</option>
                        </select>
                    </div>

                    <div class="col-12 col-sm-8">
                        <label for="nascimento" class="form-label">Data de nascimento</label>
                        <input type="date" class="form-control" id="nascimento" name="nascimento"
                               required max="<?= date('Y-m-d') ?>"
                               value="<?= h($_POST['nascimento'] ?? '') ?>">
                    </div>

                    <div class="col-6">
                        <label for="peso" class="form-label">Peso (kg)</label>
                        <input type="text" class="form-control" id="peso" name="peso"
                               inputmode="decimal" required placeholder="Ex.: 12,5"
                               value="<?= h($_POST['peso'] ?? '') ?>">
                    </div>

                    <div class="col-6">
                        <label for="altura" class="form-label">Altura (m)</label>
                        <input type="text" class="form-control" id="altura" name="altura"
                               inputmode="decimal" required placeholder="Ex.: 0,50"
                               value="<?= h($_POST['altura'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <label for="cor" class="form-label">Cor</label>
                        <input type="text" class="form-control" id="cor" name="cor"
                               maxlength="50" required placeholder="Ex.: Marrom"
                               value="<?= h($_POST['cor'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <label for="observacoes" class="form-label">Observações (opcional)</label>
                        <textarea class="form-control" id="observacoes" name="observacoes"
                                  rows="2" placeholder="Alergias, cuidados, etc."><?= h($_POST['observacoes'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-sistema">
                            <i class="bi bi-check-lg"></i> Salvar animal
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
                    <i class="bi bi-list-ul me-1"></i> Animais cadastrados
                    <span class="badge text-bg-secondary"><?= count($animais) ?></span>
                </h2>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Animal</th>
                                <th>Espécie</th>
                                <th>Sexo</th>
                                <th>Nascimento</th>
                                <th class="text-end">Peso</th>
                                <th class="text-end">Altura</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($animais) === 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-secondary py-3">
                                        Nenhum animal cadastrado.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($animais as $animal): ?>
                                    <tr>
                                        <td>
                                            <strong><?= h($animal['Nome']) ?></strong><br>
                                            <span class="small text-secondary">
                                                <?= h($animal['Raca']) ?> · <?= h($animal['Cor']) ?>
                                            </span>
                                            <?php if (!empty($animal['Observacoes'])): ?>
                                                <i class="bi bi-info-circle small text-secondary"
                                                   title="<?= h($animal['Observacoes']) ?>"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= h($animal['Especie']) ?></td>
                                        <td><?= h($animal['Sexo']) ?></td>
                                        <td>
                                            <?= data_br($animal['DataNascimento']) ?><br>
                                            <span class="small text-secondary"><?= idade($animal['DataNascimento']) ?></span>
                                        </td>
                                        <td class="text-end"><?= number_format((float) $animal['Peso'], 2, ',', '') ?> kg</td>
                                        <td class="text-end"><?= number_format((float) $animal['Altura'], 2, ',', '') ?> m</td>
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
