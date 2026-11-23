<?php
$intervalos = ['30', '60', '90'];
$intervalo = $_GET['dias'] ?? '30';
$lotes = [];
$erro = '';

if (!in_array($intervalo, $intervalos, true)) {
    $erro = 'Selecione um intervalo de 30, 60 ou 90 dias.';
} else {
    $hoje = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
    $limite = $hoje->add(new DateInterval('P' . $intervalo . 'D'));
    $inicio = $hoje->format('Y-m-d');
    $fim = $limite->format('Y-m-d');

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        require __DIR__ . '/../includes/conexao.php';
        $consulta = mysqli_prepare($conexao, '
            SELECT p.nome AS produto, l.numero_lote, f.nome AS fornecedor,
                   l.data_validade, DATEDIFF(l.data_validade, ?) AS dias_restantes
            FROM lotes l
            JOIN produtos p ON p.id = l.produto_id
            JOIN fornecedores f ON f.id = l.fornecedor_id
            WHERE l.data_validade BETWEEN ? AND ?
            ORDER BY l.data_validade, p.nome, l.id
        ');

        try {
            mysqli_stmt_bind_param($consulta, 'sss', $inicio, $inicio, $fim);
            mysqli_stmt_execute($consulta);
            $resultado = mysqli_stmt_get_result($consulta);
            $lotes = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
            mysqli_free_result($resultado);
        } finally {
            mysqli_stmt_close($consulta);
        }
    } catch (mysqli_sql_exception $excecao) {
        error_log($excecao->getMessage());
        $erro = 'Não foi possível consultar os vencimentos. Tente novamente.';
    }
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<h1 class="mb-3">Relatório de vencimentos</h1>

<form method="get" action="relatorio-vencimentos.php" class="row g-3 align-items-end mb-4">
    <div class="col-sm-auto">
        <label class="form-label" for="dias">Intervalo a partir de hoje</label>
        <select class="form-select" id="dias" name="dias">
            <?php foreach ($intervalos as $dias): ?>
                <option value="<?= $dias ?>" <?= $intervalo === $dias ? 'selected' : '' ?>>
                    Até <?= $dias ?> dias
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-sm-auto">
        <button class="btn btn-primary" type="submit">Consultar</button>
    </div>
</form>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erro) ?></div>
<?php else: ?>
    <p>Vencimentos de <?= $hoje->format('d/m/Y') ?> a <?= $limite->format('d/m/Y') ?>.</p>
    <?php if (!$lotes): ?>
        <div class="alert alert-info" role="status">Nenhum lote vence no intervalo selecionado.</div>
    <?php else: ?>
        <div class="table-responsive mb-3">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th scope="col">Produto</th>
                        <th scope="col">Lote</th>
                        <th scope="col">Fornecedor</th>
                        <th scope="col">Data de validade</th>
                        <th scope="col" class="text-end">Dias restantes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lotes as $lote): ?>
                        <tr>
                            <th scope="row"><?= htmlspecialchars($lote['produto']) ?></th>
                            <td><?= htmlspecialchars($lote['numero_lote']) ?></td>
                            <td><?= htmlspecialchars($lote['fornecedor']) ?></td>
                            <td><?= (new DateTimeImmutable($lote['data_validade']))->format('d/m/Y') ?></td>
                            <td class="text-end"><?= (int) $lote['dias_restantes'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<a class="btn btn-outline-secondary" href="index.php">Voltar ao estoque</a>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
