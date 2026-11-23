<?php
require __DIR__ . '/lotes-fefo.php';

$filtros = ['produto_id' => null, 'lote_id' => null];
$erro = '';
$produtos = [];
$lotes = [];
$movimentacoes = [];
$tipos = ['entrada' => 'Entrada', 'saida' => 'Saída', 'perda' => 'Perda', 'descarte' => 'Descarte'];

foreach (array_keys($filtros) as $campo) {
    $valor = $_GET[$campo] ?? '';
    if ($valor === '') {
        continue;
    }
    if (!is_string($valor) || !preg_match('/\A[1-9][0-9]{0,9}\z/', $valor) || $valor > 4294967295) {
        $erro = 'Selecione um produto e um lote válidos para consultar o histórico.';
        continue;
    }
    $filtros[$campo] = $valor;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require __DIR__ . '/../includes/conexao.php';
    $resultado = mysqli_query($conexao, '
        SELECT p.id AS produto_id, p.nome, l.id AS lote_id, l.numero_lote, f.nome AS fornecedor
        FROM produtos p
        LEFT JOIN lotes l ON l.produto_id = p.id
        LEFT JOIN fornecedores f ON f.id = l.fornecedor_id
        ORDER BY p.nome, p.id, l.numero_lote, l.id
    ');
    while ($linha = mysqli_fetch_assoc($resultado)) {
        $produtos[$linha['produto_id']] = $linha['nome'];
        if ($linha['lote_id'] !== null) {
            $lotes[$linha['lote_id']] = [
                'produto_id' => (string) $linha['produto_id'],
                'rotulo' => $linha['nome'] . ' — ' . $linha['numero_lote'] . ' — ' . $linha['fornecedor'],
            ];
        }
    }
    mysqli_free_result($resultado);

    $produtoId = $filtros['produto_id'];
    $loteId = $filtros['lote_id'];
    if ($produtoId !== null && !isset($produtos[$produtoId])) {
        $erro = 'O produto selecionado não existe.';
    } elseif ($loteId !== null && !isset($lotes[$loteId])) {
        $erro = 'O lote selecionado não existe.';
    } elseif ($produtoId !== null && $loteId !== null && $lotes[$loteId]['produto_id'] !== $produtoId) {
        $erro = 'O lote selecionado não pertence ao produto escolhido.';
    }

    if ($erro === '') {
        $consulta = mysqli_prepare($conexao, "
            SELECT historico.*, saldo_depois - variacao AS saldo_antes
            FROM (
                SELECT m.id, m.data_movimentacao, m.tipo, m.motivo_saida, m.quantidade, m.justificativa,
                       m.responsavel_movimentacao, m.responsavel_tecnico, m.registro_responsavel,
                       p.nome AS produto, l.numero_lote, f.nome AS fornecedor,
                       CASE m.tipo WHEN 'saida' THEN -CAST(m.quantidade AS DECIMAL(24,9))
                           ELSE CAST(m.quantidade AS DECIMAL(24,9)) END AS variacao,
                       SUM(CASE m.tipo WHEN 'saida' THEN -CAST(m.quantidade AS DECIMAL(24,9))
                           ELSE CAST(m.quantidade AS DECIMAL(24,9)) END)
                         OVER (PARTITION BY l.id ORDER BY m.data_movimentacao, m.id
                               ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS saldo_depois
                FROM movimentacoes m
                JOIN lotes l ON l.id = m.lote_id
                JOIN produtos p ON p.id = l.produto_id
                JOIN fornecedores f ON f.id = l.fornecedor_id
                WHERE (? IS NULL OR p.id = ?) AND (? IS NULL OR l.id = ?)
            ) AS historico
            ORDER BY data_movimentacao DESC, id DESC
        ");
        try {
            mysqli_stmt_bind_param($consulta, 'ssss', $produtoId, $produtoId, $loteId, $loteId);
            mysqli_stmt_execute($consulta);
            $resultado = mysqli_stmt_get_result($consulta);
            $movimentacoes = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
            mysqli_free_result($resultado);
        } finally {
            mysqli_stmt_close($consulta);
        }
    }
} catch (mysqli_sql_exception $excecao) {
    error_log($excecao->getMessage());
    $erro = 'Não foi possível consultar o histórico de estoque. Tente novamente.';
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<h1 class="mb-3">Histórico de movimentações</h1>

<form method="get" action="historico-movimentacoes.php" class="row g-3 align-items-end mb-4">
    <div class="col-md-4">
        <label class="form-label" for="produto_id">Produto</label>
        <select class="form-select" id="produto_id" name="produto_id">
            <option value="">Todos os produtos</option>
            <?php foreach ($produtos as $id => $nome): ?>
                <option value="<?= htmlspecialchars((string) $id) ?>" <?= $filtros['produto_id'] === (string) $id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($nome) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-5">
        <label class="form-label" for="lote_id">Lote</label>
        <select class="form-select" id="lote_id" name="lote_id">
            <option value="">Todos os lotes</option>
            <?php foreach ($lotes as $id => $lote): ?>
                <option value="<?= htmlspecialchars((string) $id) ?>" <?= $filtros['lote_id'] === (string) $id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($lote['rotulo']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-auto">
        <button class="btn btn-primary" type="submit">Consultar</button>
    </div>
</form>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erro) ?></div>
<?php elseif (!$movimentacoes): ?>
    <div class="alert alert-info" role="status">Nenhuma movimentação encontrada para os filtros selecionados.</div>
<?php else: ?>
    <div class="table-responsive mb-3">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th scope="col">Data e hora</th>
                    <th scope="col">Produto</th>
                    <th scope="col">Lote / fornecedor</th>
                    <th scope="col">Tipo</th>
                    <th scope="col" class="text-end">Quantidade</th>
                    <th scope="col">Justificativa</th>
                    <th scope="col">Responsável registrado</th>
                    <th scope="col" class="text-end">Saldo anterior do lote</th>
                    <th scope="col" class="text-end">Saldo após a movimentação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movimentacoes as $movimentacao): ?>
                    <?php
                    $tipo = $movimentacao['motivo_saida'] ?? $movimentacao['tipo'];
                    $responsavel = $movimentacao['responsavel_movimentacao'] ?? trim(
                        ($movimentacao['responsavel_tecnico'] ?? '') . ' ' . ($movimentacao['registro_responsavel'] ?? '')
                    );
                    ?>
                    <tr>
                        <td class="text-nowrap"><?= htmlspecialchars((new DateTimeImmutable($movimentacao['data_movimentacao']))->format('d/m/Y H:i:s')) ?></td>
                        <td><?= htmlspecialchars($movimentacao['produto']) ?></td>
                        <td><?= htmlspecialchars($movimentacao['numero_lote'] . ' / ' . $movimentacao['fornecedor']) ?></td>
                        <td><?= htmlspecialchars($tipos[$tipo] ?? $tipo) ?></td>
                        <td class="text-end"><?= htmlspecialchars(formatarQuantidadeEstoque((string) $movimentacao['quantidade'])) ?></td>
                        <td><?= nl2br(htmlspecialchars($movimentacao['justificativa'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars($responsavel !== '' ? $responsavel : '—') ?></td>
                        <td class="text-end"><?= htmlspecialchars(formatarQuantidadeEstoque((string) $movimentacao['saldo_antes'])) ?></td>
                        <td class="text-end"><?= htmlspecialchars(formatarQuantidadeEstoque((string) $movimentacao['saldo_depois'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<a class="btn btn-outline-secondary" href="index.php">Voltar ao estoque</a>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
