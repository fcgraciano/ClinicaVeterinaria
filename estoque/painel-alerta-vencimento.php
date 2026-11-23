<?php
$lotesComAlerta = [];
$falhaVencimentos = false;
$hojeVencimentos = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
$inicioVencimentos = $hojeVencimentos->format('Y-m-d');
$fimVencimentos = $hojeVencimentos->add(new DateInterval('P30D'))->format('Y-m-d');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require_once __DIR__ . '/../includes/conexao.php';
    $consulta = mysqli_prepare($conexao, '
        SELECT l.id, l.produto_id, l.numero_lote, l.data_validade, p.nome AS produto
        FROM lotes l
        JOIN produtos p ON p.id = l.produto_id
        WHERE l.data_validade <= ?
        ORDER BY l.data_validade, p.nome, l.id
    ');
    try {
        mysqli_stmt_bind_param($consulta, 's', $fimVencimentos);
        mysqli_stmt_execute($consulta);
        $resultado = mysqli_stmt_get_result($consulta);
        $lotesComAlerta = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
        mysqli_free_result($resultado);
    } finally {
        mysqli_stmt_close($consulta);
    }
} catch (mysqli_sql_exception $excecao) {
    error_log($excecao->getMessage());
    $falhaVencimentos = true;
}
?>

<section class="card my-3" aria-labelledby="titulo-alertas-vencimento">
    <div class="card-body">
        <h2 class="h5" id="titulo-alertas-vencimento">Alertas de vencimento</h2>
        <?php if ($falhaVencimentos): ?>
            <div class="alert alert-danger mb-0" role="alert">Não foi possível consultar a validade dos lotes.</div>
        <?php elseif (!$lotesComAlerta): ?>
            <p class="mb-0">Nenhum lote vencido ou a vencer nos próximos 30 dias.</p>
        <?php else: ?>
            <p>Lotes vencidos ou com validade até <?= (new DateTimeImmutable($fimVencimentos))->format('d/m/Y') ?>.</p>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Produto</th>
                            <th scope="col">Lote</th>
                            <th scope="col">Validade</th>
                            <th scope="col">Aviso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lotesComAlerta as $loteComAlerta): ?>
                            <?php
                            $dataValidade = $loteComAlerta['data_validade'];
                            $vencido = $dataValidade < $inicioVencimentos;
                            $aviso = $vencido ? 'Vencido' : ($dataValidade === $inicioVencimentos ? 'Vence hoje' : 'A vencer');
                            $detalhe = 'lotes-do-produto.php?produto_id=' . rawurlencode((string) $loteComAlerta['produto_id'])
                                . '#lote-' . rawurlencode((string) $loteComAlerta['id']);
                            ?>
                            <tr>
                                <th scope="row"><?= htmlspecialchars($loteComAlerta['produto']) ?></th>
                                <td><a href="<?= htmlspecialchars($detalhe) ?>"><?= htmlspecialchars($loteComAlerta['numero_lote']) ?></a></td>
                                <td><?= (new DateTimeImmutable($dataValidade))->format('d/m/Y') ?></td>
                                <td><span class="badge <?= $vencido ? 'text-bg-danger' : 'text-bg-warning' ?>"><?= $aviso ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
