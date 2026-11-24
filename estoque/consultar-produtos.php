<?php
// =====================================================================
//  CONSULTAR PRODUTOS — Listagem server-side com filtro (RNF03)
//  Lista produtos ativos e filtra por nome ou código de barras.
//  PHP procedural + mysqli · consultas preparadas.
// =====================================================================

$busca      = '';
$produtos   = [];
$erroConsulta = '';
$consultou  = false;

// ---- Captura e sanitiza o filtro (GET) ----
$buscaEnviada = $_GET['busca'] ?? '';
if (is_string($buscaEnviada)) {
    $busca = trim($buscaEnviada);
}

// Valida comprimento máximo
if (mb_strlen($busca, 'UTF-8') > 150) {
    $busca = mb_substr($busca, 0, 150, 'UTF-8');
}

// ---- Consulta ao banco ----
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require __DIR__ . '/../includes/conexao.php';

    $sql = "
        SELECT p.id,
               p.nome,
               p.codigo_barras,
               c.nome                  AS categoria,
               p.estoque_minimo,
               p.classificacao,
               p.fracionamento_habilitado,
               COALESCE(SUM(CASE m.tipo
                   WHEN 'entrada' THEN  CAST(m.quantidade AS DECIMAL(24,9))
                   WHEN 'saida'   THEN -CAST(m.quantidade AS DECIMAL(24,9))
               END), 0)               AS saldo
        FROM produtos p
        JOIN categorias c ON c.id = p.categoria_id
        LEFT JOIN lotes l ON l.produto_id = p.id
        LEFT JOIN movimentacoes m ON m.lote_id = l.id
        WHERE (? = '' OR LOCATE(?, p.nome) > 0 OR LOCATE(?, p.codigo_barras) > 0)
        GROUP BY p.id, p.nome, p.codigo_barras, c.nome,
                 p.estoque_minimo, p.classificacao, p.fracionamento_habilitado
        ORDER BY p.nome, p.id
    ";

    $consulta = mysqli_prepare($conexao, $sql);

    try {
        mysqli_stmt_bind_param($consulta, 'sss', $busca, $busca, $busca);
        mysqli_stmt_execute($consulta);
        $resultado = mysqli_stmt_get_result($consulta);
        $produtos  = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
        mysqli_free_result($resultado);
    } finally {
        mysqli_stmt_close($consulta);
    }

    $consultou = true;
} catch (mysqli_sql_exception $excecao) {
    error_log($excecao->getMessage());
    $erroConsulta = 'Não foi possível consultar os produtos. Tente novamente.';
}

// ---- Helpers de exibição ----
function formatarSaldo(string $saldo): string
{
    if (strpos($saldo, '.') === false) {
        return $saldo;
    }
    return rtrim(rtrim($saldo, '0'), '.');
}

function rotuloClassificacao(string $classificacao): string
{
    $rotulos = [
        'comum'            => 'Comum',
        'vacina'           => 'Vacina',
        'controle_especial' => 'Controle especial',
    ];
    return $rotulos[$classificacao] ?? $classificacao;
}

function badgeClassificacao(string $classificacao): string
{
    $classes = [
        'comum'            => 'bg-secondary',
        'vacina'           => 'bg-info text-dark',
        'controle_especial' => 'bg-warning text-dark',
    ];
    $classe = $classes[$classificacao] ?? 'bg-secondary';
    $rotulo = rotuloClassificacao($classificacao);
    return '<span class="badge ' . $classe . '">' . htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') . '</span>';
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<h1 class="mb-3">Listar e buscar produtos</h1>

<?php if ($erroConsulta !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= $erroConsulta ?></div>
<?php endif; ?>

<!-- Formulário de busca (GET — RNF03) -->
<form method="get" action="consultar-produtos.php" class="row g-3 align-items-end mb-4">
    <div class="col-md-5">
        <label class="form-label" for="busca">Buscar por nome ou código de barras</label>
        <input
            class="form-control"
            type="search"
            id="busca"
            name="busca"
            value="<?= htmlspecialchars($busca, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            maxlength="150"
            placeholder="Digite o nome ou código de barras..."
        >
    </div>
    <div class="col-md-auto">
        <button class="btn btn-primary" type="submit">
            <i class="bi bi-search"></i> Buscar
        </button>
    </div>
    <?php if ($busca !== ''): ?>
        <div class="col-md-auto">
            <a class="btn btn-outline-secondary" href="consultar-produtos.php">Limpar filtro</a>
        </div>
    <?php endif; ?>
</form>

<?php if ($consultou): ?>

    <!-- Contagem de resultados -->
    <p role="status">
        <?php if ($busca !== ''): ?>
            <?= count($produtos) === 0
                ? 'Nenhum produto encontrado para "<strong>' . htmlspecialchars($busca, ENT_QUOTES, 'UTF-8') . '</strong>".'
                : count($produtos) . ' produto(s) encontrado(s) para "<strong>' . htmlspecialchars($busca, ENT_QUOTES, 'UTF-8') . '</strong>".' ?>
        <?php else: ?>
            <?= count($produtos) === 0
                ? 'Nenhum produto cadastrado.'
                : count($produtos) . ' produto(s) cadastrado(s).' ?>
        <?php endif; ?>
    </p>

    <?php if (count($produtos) > 0): ?>
        <div class="table-responsive mb-3">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Nome</th>
                        <th scope="col">Código de barras</th>
                        <th scope="col">Categoria</th>
                        <th scope="col">Classificação</th>
                        <th scope="col" class="text-center">Fracionamento</th>
                        <th scope="col" class="text-end">Estoque mín.</th>
                        <th scope="col" class="text-end">Saldo</th>
                        <th scope="col">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $indice => $produto): ?>
                        <?php
                            $saldoFormatado = formatarSaldo($produto['saldo']);
                            $abaixoMinimo   = (float) $produto['saldo'] < (float) $produto['estoque_minimo'];
                        ?>
                        <tr<?= $abaixoMinimo ? ' class="table-danger"' : '' ?>>
                            <td><?= $indice + 1 ?></td>
                            <th scope="row"><?= htmlspecialchars($produto['nome'], ENT_QUOTES, 'UTF-8') ?></th>
                            <td><?= htmlspecialchars($produto['codigo_barras'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($produto['categoria'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= badgeClassificacao($produto['classificacao']) ?></td>
                            <td class="text-center">
                                <?php if ($produto['fracionamento_habilitado']): ?>
                                    <i class="bi bi-check-circle-fill text-success" title="Habilitado"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle text-muted" title="Desabilitado"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= (int) $produto['estoque_minimo'] ?></td>
                            <td class="text-end<?= $abaixoMinimo ? ' fw-bold text-danger' : '' ?>">
                                <?= htmlspecialchars($saldoFormatado, ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($abaixoMinimo): ?>
                                    <i class="bi bi-exclamation-triangle-fill" title="Abaixo do estoque mínimo"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a class="btn btn-outline-primary"
                                       href="editar-produto.php?id=<?= urlencode($produto['id']) ?>"
                                       aria-label="Editar <?= htmlspecialchars($produto['nome'], ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a class="btn btn-outline-secondary"
                                       href="lotes-do-produto.php?produto_id=<?= urlencode($produto['id']) ?>"
                                       aria-label="Ver lotes de <?= htmlspecialchars($produto['nome'], ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-box-seam"></i> Lotes
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php endif; ?>

<div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="index.php">
        <i class="bi bi-arrow-left"></i> Voltar ao estoque
    </a>
    <a class="btn btn-success" href="cadastrar-produto.php">
        <i class="bi bi-plus-lg"></i> Cadastrar novo produto
    </a>
</div>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
