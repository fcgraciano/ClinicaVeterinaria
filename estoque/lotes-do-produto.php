<?php
require __DIR__ . '/consulta-lotes-produto.php';

$produtoId = $_GET['produto_id'] ?? '';
$erro = '';
$produtos = [];
$dados = null;
$statusLote = ['ativo' => 'Ativo', 'bloqueado' => 'Bloqueado', 'vencido' => 'Vencido'];

if (!is_string($produtoId) || ($produtoId !== '' &&
    (!preg_match('/\A[1-9][0-9]{0,9}\z/', $produtoId) || $produtoId > 4294967295))) {
    $produtoId = '';
    $erro = 'Selecione um produto válido.';
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require __DIR__ . '/../includes/conexao.php';
    $resultado = mysqli_query($conexao, 'SELECT id, nome, codigo_barras FROM produtos ORDER BY nome, id');
    $produtos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    mysqli_free_result($resultado);

    if ($produtoId !== '') {
        $hoje = (new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        $dados = consultarLotesDoProduto($conexao, $produtoId, $hoje);
        if ($dados === null) {
            $erro = 'O produto selecionado não existe.';
        }
    }
} catch (mysqli_sql_exception $excecao) {
    error_log($excecao->getMessage());
    $erro = 'Não foi possível consultar os lotes do produto. Tente novamente.';
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<h1 class="mb-3">Lotes do produto</h1>

<?php if ($produtos): ?>
    <form method="get" action="lotes-do-produto.php" class="row g-3 align-items-end mb-4">
        <div class="col-md-5">
            <label class="form-label" for="produto_id">Produto</label>
            <select class="form-select" id="produto_id" name="produto_id" required>
                <option value="">Selecione</option>
                <?php foreach ($produtos as $produto): ?>
                    <option value="<?= htmlspecialchars((string) $produto['id']) ?>" <?= $produtoId === (string) $produto['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($produto['nome'] . ' — ' . $produto['codigo_barras']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-auto">
            <button class="btn btn-primary" type="submit">Consultar</button>
        </div>
    </form>
<?php endif; ?>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erro) ?></div>
<?php elseif (!$produtos): ?>
    <div class="alert alert-info" role="status">Nenhum produto cadastrado.</div>
<?php elseif ($dados !== null): ?>
    <h2 class="h4"><?= htmlspecialchars($dados['produto']['nome']) ?></h2>
    <dl class="row mb-3">
        <dt class="col-sm-4">Categoria</dt>
        <dd class="col-sm-8"><?= htmlspecialchars($dados['produto']['categoria']) ?></dd>
        <dt class="col-sm-4">Código de barras</dt>
        <dd class="col-sm-8"><?= htmlspecialchars($dados['produto']['codigo_barras']) ?></dd>
        <dt class="col-sm-4">Estoque mínimo</dt>
        <dd class="col-sm-8"><?= htmlspecialchars((string) $dados['produto']['estoque_minimo']) ?></dd>
    </dl>
    <?php if (!$dados['lotes']): ?>
        <div class="alert alert-info" role="status">Este produto ainda não tem lotes cadastrados.</div>
    <?php else: ?>
        <div class="table-responsive mb-3">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th scope="col">Número do lote</th>
                        <th scope="col">Fornecedor</th>
                        <th scope="col">Status</th>
                        <th scope="col">Validade</th>
                        <th scope="col" class="text-end">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados['lotes'] as $lote): ?>
                        <?php
                        $validade = $lote['data_validade'] === null
                            ? 'Sem validade'
                            : (new DateTimeImmutable($lote['data_validade']))->format('d/m/Y');
                        $saldo = (string) $lote['saldo'];
                        if (strpos($saldo, '.') !== false) {
                            $saldo = rtrim(rtrim($saldo, '0'), '.');
                        }
                        ?>
                        <tr>
                            <th scope="row"><?= htmlspecialchars($lote['numero_lote']) ?></th>
                            <td><?= htmlspecialchars($lote['fornecedor']) ?></td>
                            <td><?= htmlspecialchars($statusLote[$lote['status']] ?? $lote['status']) ?></td>
                            <td><?= $validade ?></td>
                            <td class="text-end"><?= htmlspecialchars(str_replace('.', ',', $saldo)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<a class="btn btn-outline-secondary" href="index.php">Voltar ao estoque</a>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
