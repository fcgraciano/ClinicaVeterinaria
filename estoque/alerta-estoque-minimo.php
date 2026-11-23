<?php
require_once __DIR__ . '/consulta-estoque-minimo.php';

$produtos = [];
$erroConsulta = false;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require __DIR__ . '/../includes/conexao.php';
    $produtos = consultarEstoqueMinimo($conexao);
} catch (mysqli_sql_exception $excecao) {
    error_log($excecao->getMessage());
    $erroConsulta = true;
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<h1 class="mb-3">Itens para reposição</h1>

<?php if ($erroConsulta): ?>
    <div class="alert alert-danger" role="alert">Não foi possível consultar o estoque. Tente novamente.</div>
<?php elseif (!$produtos): ?>
    <div class="alert alert-success" role="status">Nenhum produto atingiu o estoque mínimo.</div>
<?php else: ?>
    <div class="table-responsive mb-3">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th scope="col">Produto</th>
                    <th scope="col">Código de barras</th>
                    <th scope="col" class="text-end">Saldo total</th>
                    <th scope="col" class="text-end">Estoque mínimo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produtos as $produto): ?>
                    <tr>
                        <th scope="row"><?= htmlspecialchars($produto['nome']) ?></th>
                        <td><?= htmlspecialchars($produto['codigo_barras']) ?></td>
                        <td class="text-end"><?= htmlspecialchars(formatarSaldoParaReposicao((string) $produto['saldo_total'])) ?></td>
                        <td class="text-end"><?= htmlspecialchars((string) $produto['estoque_minimo']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<a class="btn btn-outline-secondary" href="index.php">Voltar ao estoque</a>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
