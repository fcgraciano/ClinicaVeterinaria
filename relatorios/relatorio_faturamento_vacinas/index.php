<?php
$base = '../../';
include __DIR__ . '/../../includes/cabecalho.php';
include __DIR__ . '/../../includes/conexao.php';
include __DIR__ . '/../../includes/funcoes.php';

$erro = '';

$dataInicio  = trim((string) ($_GET['data_inicio'] ?? ''));
$dataFim     = trim((string) ($_GET['data_fim'] ?? ''));
$status      = (string) ($_GET['status'] ?? 'todos');
$tipoVacina  = trim((string) ($_GET['tipo_vacina'] ?? ''));

if (!in_array($status, ['todos', 'pago', 'nao_pago'], true)) {
    $status = 'todos';
}

if ($dataInicio !== '' && !data_valida($dataInicio)) {
    $erro = 'A data inicial informada é inválida.';
    $dataInicio = '';
}

if ($dataFim !== '' && !data_valida($dataFim)) {
    $erro = 'A data final informada é inválida.';
    $dataFim = '';
}

if ($dataInicio !== '' && $dataFim !== '' && $dataInicio > $dataFim) {
    $erro = 'A data inicial não pode ser posterior à data final.';
}

// Tipos de vacina já aplicados, usados no filtro
$vacinasFiltro = mysqli_fetch_all(
    mysqli_query(
        $conexao,
        'SELECT DISTINCT TipoVacina
           FROM vacinas_aplicacadas
          ORDER BY TipoVacina'
    ),
    MYSQLI_ASSOC
);

$condicoes = [];
$parametros = [];
$tiposParametros = '';

if ($dataInicio !== '') {
    $condicoes[] = 'ap.DataAplicacao >= ?';
    $parametros[] = $dataInicio;
    $tiposParametros .= 's';
}

if ($dataFim !== '') {
    $condicoes[] = 'ap.DataAplicacao <= ?';
    $parametros[] = $dataFim;
    $tiposParametros .= 's';
}

if ($status === 'pago') {
    $condicoes[] = 'ap.Pago = 1';
} elseif ($status === 'nao_pago') {
    $condicoes[] = 'ap.Pago = 0';
}

if ($tipoVacina !== '') {
    $condicoes[] = 'ap.TipoVacina = ?';
    $parametros[] = $tipoVacina;
    $tiposParametros .= 's';
}

$sql = 'SELECT ap.Aplicacaoid, ap.DataAplicacao, ap.Retorno, ap.dose,
               ap.TipoVacina, ap.Pago, ap.Valor, ap.Pagamentoid,
               an.Nome AS Animal, an.Especie,
               pg.DataPagamento
          FROM vacinas_aplicacadas ap
          LEFT JOIN animais an ON an.Animalid = ap.Animalid
          LEFT JOIN pagamentos pg ON pg.Pagamentoid = ap.Pagamentoid';

if (count($condicoes) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $condicoes);
}

$sql .= ' ORDER BY ap.DataAplicacao DESC, ap.Aplicacaoid DESC';

if (count($parametros) > 0) {
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, $tiposParametros, ...$parametros);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
} else {
    $resultado = mysqli_query($conexao, $sql);
}

$aplicacoes = $resultado ? mysqli_fetch_all($resultado, MYSQLI_ASSOC) : [];

$totalAplicacoes = count($aplicacoes);
$totalDoses = 0;
$totalFaturado = 0.0;
$totalPago = 0.0;
$totalEmAberto = 0.0;

$resumoPorVacina = [];

foreach ($aplicacoes as $aplicacao) {
    $dose = (int) $aplicacao['dose'];
    $valor = (float) $aplicacao['Valor'];
    $pago = (int) $aplicacao['Pago'] === 1;
    $vacina = $aplicacao['TipoVacina'];

    $totalDoses += $dose;
    $totalFaturado += $valor;

    if ($pago) {
        $totalPago += $valor;
    } else {
        $totalEmAberto += $valor;
    }

    if (!isset($resumoPorVacina[$vacina])) {
        $resumoPorVacina[$vacina] = [
            'aplicacoes' => 0,
            'doses' => 0,
            'total' => 0.0,
            'pago' => 0.0,
            'aberto' => 0.0,
        ];
    }

    $resumoPorVacina[$vacina]['aplicacoes']++;
    $resumoPorVacina[$vacina]['doses'] += $dose;
    $resumoPorVacina[$vacina]['total'] += $valor;

    if ($pago) {
        $resumoPorVacina[$vacina]['pago'] += $valor;
    } else {
        $resumoPorVacina[$vacina]['aberto'] += $valor;
    }
}

uasort($resumoPorVacina, function ($a, $b) {
    return $b['total'] <=> $a['total'];
});
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1">Relatório de Faturamento de Vacinas</h1>
        <p class="text-secondary mb-0">
            Receitas de imunização, valores recebidos e pagamentos em aberto
        </p>
    </div>
    <a href="../index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger"><?= h($erro) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h6 mb-3">
            <i class="bi bi-funnel me-1"></i> Filtros
        </h2>

        <form method="get" action="index.php" class="row g-3 align-items-end">
            <div class="col-12 col-sm-6 col-lg-3">
                <label for="data_inicio" class="form-label">Aplicação de</label>
                <input type="date" class="form-control" id="data_inicio" name="data_inicio"
                       value="<?= h($dataInicio) ?>">
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <label for="data_fim" class="form-label">Aplicação até</label>
                <input type="date" class="form-control" id="data_fim" name="data_fim"
                       value="<?= h($dataFim) ?>">
            </div>

            <div class="col-12 col-sm-6 col-lg-2">
                <label for="status" class="form-label">Pagamento</label>
                <select class="form-select" id="status" name="status">
                    <option value="todos" <?= $status === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="pago" <?= $status === 'pago' ? 'selected' : '' ?>>Pagos</option>
                    <option value="nao_pago" <?= $status === 'nao_pago' ? 'selected' : '' ?>>Em aberto</option>
                </select>
            </div>

            <div class="col-12 col-sm-6 col-lg-2">
                <label for="tipo_vacina" class="form-label">Vacina</label>
                <select class="form-select" id="tipo_vacina" name="tipo_vacina">
                    <option value="">Todas</option>
                    <?php foreach ($vacinasFiltro as $vacina): ?>
                        <option value="<?= h($vacina['TipoVacina']) ?>"
                            <?= $tipoVacina === $vacina['TipoVacina'] ? 'selected' : '' ?>>
                            <?= h($vacina['TipoVacina']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-sistema flex-grow-1">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <a href="index.php" class="btn btn-outline-secondary" title="Limpar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>

            <div class="col-12">
                <p class="small text-secondary mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Deixe as datas em branco para consultar todo o período.
                </p>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <p class="text-secondary small mb-1">Faturamento total</p>
                <p class="h4 mb-0"><?= moeda_br($totalFaturado) ?></p>
                <p class="small text-secondary mb-0"><?= $totalAplicacoes ?> aplicação(ões)</p>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card h-100 border-success">
            <div class="card-body">
                <p class="text-secondary small mb-1">Recebido</p>
                <p class="h4 mb-0 text-success"><?= moeda_br($totalPago) ?></p>
                <p class="small text-secondary mb-0">Aplicações marcadas como pagas</p>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card h-100 border-danger">
            <div class="card-body">
                <p class="text-secondary small mb-1">Em aberto</p>
                <p class="h4 mb-0 text-danger"><?= moeda_br($totalEmAberto) ?></p>
                <p class="small text-secondary mb-0">Aplicações ainda não pagas</p>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <p class="text-secondary small mb-1">Doses aplicadas</p>
                <p class="h4 mb-0"><?= $totalDoses ?></p>
                <p class="small text-secondary mb-0">Somatório das doses no período</p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold">
            <i class="bi bi-receipt me-1"></i> Aplicações e faturamento
        </span>
        <span class="badge text-bg-secondary"><?= $totalAplicacoes ?> registro(s)</span>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Animal</th>
                        <th>Vacina</th>
                        <th class="text-end">Dose</th>
                        <th>Aplicação</th>
                        <th>Pagamento</th>
                        <th class="text-end">Valor</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($totalAplicacoes === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-4">
                                Nenhuma aplicação encontrada com os filtros informados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($aplicacoes as $aplicacao): ?>
                            <?php $estaPago = (int) $aplicacao['Pago'] === 1; ?>
                            <tr>
                                <td>
                                    <span class="me-1" aria-hidden="true">
                                        <?= icone_especie($aplicacao['Especie'] ?? '') ?>
                                    </span>
                                    <?= h($aplicacao['Animal'] ?? '—') ?>
                                </td>
                                <td><?= h($aplicacao['TipoVacina']) ?></td>
                                <td class="text-end"><?= (int) $aplicacao['dose'] ?></td>
                                <td><?= data_br($aplicacao['DataAplicacao']) ?></td>
                                <td><?= data_br($aplicacao['DataPagamento']) ?></td>
                                <td class="text-end"><?= moeda_br($aplicacao['Valor']) ?></td>
                                <td>
                                    <span class="badge <?= $estaPago ? 'text-bg-success' : 'text-bg-danger' ?>">
                                        <?= $estaPago ? 'Pago' : 'Em aberto' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>

                <?php if ($totalAplicacoes > 0): ?>
                    <tfoot>
                        <tr>
                            <th colspan="2" class="text-end">Total</th>
                            <th class="text-end"><?= $totalDoses ?></th>
                            <th colspan="2"></th>
                            <th class="text-end"><?= moeda_br($totalFaturado) ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php if ($totalAplicacoes > 0): ?>
    <div class="card">
        <div class="card-header bg-white">
            <span class="fw-semibold">
                <i class="bi bi-bar-chart me-1"></i> Resumo por vacina
            </span>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Vacina</th>
                            <th class="text-end">Aplicações</th>
                            <th class="text-end">Doses</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Recebido</th>
                            <th class="text-end">Em aberto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumoPorVacina as $vacina => $resumo): ?>
                            <tr>
                                <td><?= h($vacina) ?></td>
                                <td class="text-end"><?= $resumo['aplicacoes'] ?></td>
                                <td class="text-end"><?= $resumo['doses'] ?></td>
                                <td class="text-end"><?= moeda_br($resumo['total']) ?></td>
                                <td class="text-end text-success"><?= moeda_br($resumo['pago']) ?></td>
                                <td class="text-end text-danger"><?= moeda_br($resumo['aberto']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th class="text-end"><?= $totalAplicacoes ?></th>
                            <th class="text-end"><?= $totalDoses ?></th>
                            <th class="text-end"><?= moeda_br($totalFaturado) ?></th>
                            <th class="text-end text-success"><?= moeda_br($totalPago) ?></th>
                            <th class="text-end text-danger"><?= moeda_br($totalEmAberto) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/rodape.php'; ?>
