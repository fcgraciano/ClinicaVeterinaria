<?php
$produtos = [];
$erroConsulta = false;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require __DIR__ . '/../includes/conexao.php';
    $resultado = mysqli_query($conexao, "
        SELECT p.nome, p.codigo_barras, p.estoque_minimo,
               COALESCE(SUM(CASE m.tipo
                   WHEN 'entrada' THEN CAST(m.quantidade AS DECIMAL(24,9))
                   WHEN 'saida' THEN -CAST(m.quantidade AS DECIMAL(24,9))
               END), 0) AS saldo_total
        FROM produtos p
        LEFT JOIN lotes l ON l.produto_id = p.id
        LEFT JOIN movimentacoes m ON m.lote_id = l.id
        GROUP BY p.id, p.nome, p.codigo_barras, p.estoque_minimo
        HAVING saldo_total <= p.estoque_minimo
        ORDER BY p.nome, p.id
    ");
    $produtos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    mysqli_free_result($resultado);
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
                    <?php
                    $saldo = (string) $produto['saldo_total'];
                    if (strpos($saldo, '.') !== false) {
                        $saldo = rtrim(rtrim($saldo, '0'), '.');
                    }
                    ?>
                    <tr>
                        <th scope="row"><?= htmlspecialchars($produto['nome']) ?></th>
                        <td><?= htmlspecialchars($produto['codigo_barras']) ?></td>
                        <td class="text-end"><?= htmlspecialchars(str_replace('.', ',', $saldo)) ?></td>
                        <td class="text-end"><?= htmlspecialchars((string) $produto['estoque_minimo']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<a class="btn btn-outline-secondary" href="index.php">Voltar ao estoque</a>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
