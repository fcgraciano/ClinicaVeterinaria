<?php
require_once __DIR__ . '/consulta-estoque-minimo.php';

$itensReposicao = [];
$falhaReposicao = false;
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require_once __DIR__ . '/../includes/conexao.php';
    $itensReposicao = consultarEstoqueMinimo($conexao);
} catch (mysqli_sql_exception $excecao) {
    error_log($excecao->getMessage());
    $falhaReposicao = true;
}
?>

<section class="card mb-3" aria-labelledby="titulo-reposicao">
    <div class="card-body">
        <h2 class="h5" id="titulo-reposicao">Estoque mínimo</h2>
        <?php if ($falhaReposicao): ?>
            <div class="alert alert-danger mb-0" role="alert">Não foi possível consultar os itens para reposição.</div>
        <?php elseif (!$itensReposicao): ?>
            <p class="mb-0">Nenhum produto atingiu o estoque mínimo.</p>
        <?php else: ?>
            <div class="alert alert-warning">
                <?php $totalReposicao = count($itensReposicao); ?>
                <p><strong><?= $totalReposicao ?> <?= $totalReposicao === 1 ? 'produto para reposição' : 'produtos para reposição' ?></strong></p>
                <ul class="mb-0">
                    <?php foreach (array_slice($itensReposicao, 0, 5) as $item): ?>
                        <li>
                            <?= htmlspecialchars($item['nome']) ?> —
                            saldo <?= htmlspecialchars(formatarSaldoParaReposicao((string) $item['saldo_total'])) ?>,
                            mínimo <?= htmlspecialchars((string) $item['estoque_minimo']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <a class="btn btn-outline-primary" href="alerta-estoque-minimo.php">Ver itens para reposição</a>
        <?php endif; ?>
    </div>
</section>
