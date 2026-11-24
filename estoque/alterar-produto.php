<?php
// =====================================================================
//  ALTERAR PRODUTO — Processamento PHP server-side (RF09)
//  Carrega produto por ?id=, exibe formulário preenchido,
//  valida e persiste alterações via POST.
//  Campos: nome, categoria, fornecedores, código de barras,
//          estoque mínimo, classificação e fracionamento.
//  PHP procedural + mysqli · consultas preparadas.
// =====================================================================

// ---- Validação do ID do produto ----
$idParam = $_GET['id'] ?? '';
if (!is_string($idParam) || !preg_match('/\A[1-9][0-9]{0,9}\z/', $idParam) || $idParam > 4294967295) {
    include __DIR__ . '/../includes/cabecalho.php';
    echo '<div class="alert alert-danger" role="alert">Produto inválido ou não informado.</div>';
    echo '<a class="btn btn-outline-secondary" href="consultar-produtos.php"><i class="bi bi-arrow-left"></i> Voltar aos produtos</a>';
    include __DIR__ . '/../includes/rodape.php';
    exit;
}
$produtoId = (int) $idParam;

$valores = [
    'nome'                     => '',
    'categoria_id'             => '',
    'codigo_barras'            => '',
    'estoque_minimo'           => '',
    'classificacao'            => 'comum',
    'fracionamento_habilitado' => '0',
    'fator_ml'                 => '',
    'fator_dose'               => '',
];
$fornecedoresSelecionados = [];

$erros          = [];
$erroPagina     = '';
$atualizado     = false;
$produto        = null;
$categorias     = [];
$fornecedores   = [];

// ---- Conexão e carregamento de dados auxiliares ----
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require __DIR__ . '/../includes/conexao.php';

    // Carrega o produto
    $sqlProduto = mysqli_prepare($conexao,
        'SELECT id, nome, categoria_id, codigo_barras, estoque_minimo,
                classificacao, fracionamento_habilitado, fator_ml, fator_dose
         FROM produtos WHERE id = ?'
    );
    try {
        mysqli_stmt_bind_param($sqlProduto, 'i', $produtoId);
        mysqli_stmt_execute($sqlProduto);
        $produto = mysqli_fetch_assoc(mysqli_stmt_get_result($sqlProduto));
    } finally {
        mysqli_stmt_close($sqlProduto);
    }

    if (!$produto) {
        include __DIR__ . '/../includes/cabecalho.php';
        echo '<div class="alert alert-warning" role="alert">Produto não encontrado.</div>';
        echo '<a class="btn btn-outline-secondary" href="consultar-produtos.php"><i class="bi bi-arrow-left"></i> Voltar aos produtos</a>';
        include __DIR__ . '/../includes/rodape.php';
        exit;
    }

    // Carrega fornecedores vinculados ao produto
    $sqlVinculos = mysqli_prepare($conexao,
        'SELECT fornecedor_id FROM produto_fornecedor WHERE produto_id = ?'
    );
    try {
        mysqli_stmt_bind_param($sqlVinculos, 'i', $produtoId);
        mysqli_stmt_execute($sqlVinculos);
        $resultadoVinculos = mysqli_stmt_get_result($sqlVinculos);
        while ($linha = mysqli_fetch_assoc($resultadoVinculos)) {
            $fornecedoresSelecionados[] = (string) $linha['fornecedor_id'];
        }
        mysqli_free_result($resultadoVinculos);
    } finally {
        mysqli_stmt_close($sqlVinculos);
    }

    // Categorias e fornecedores para os selects
    $resultado  = mysqli_query($conexao, 'SELECT id, nome FROM categorias ORDER BY nome');
    $categorias = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    mysqli_free_result($resultado);

    $resultado    = mysqli_query($conexao, 'SELECT id, nome FROM fornecedores ORDER BY nome');
    $fornecedores = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    mysqli_free_result($resultado);

    // Preenche valores padrão com dados do produto (GET)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $valores['nome']                     = $produto['nome'];
        $valores['categoria_id']             = (string) $produto['categoria_id'];
        $valores['codigo_barras']            = $produto['codigo_barras'];
        $valores['estoque_minimo']           = (string) $produto['estoque_minimo'];
        $valores['classificacao']            = $produto['classificacao'];
        $valores['fracionamento_habilitado'] = $produto['fracionamento_habilitado'] ? '1' : '0';
        $valores['fator_ml']                 = $produto['fator_ml'] !== null ? $produto['fator_ml'] : '';
        $valores['fator_dose']               = $produto['fator_dose'] !== null ? $produto['fator_dose'] : '';
    }

} catch (mysqli_sql_exception $excecao) {
    error_log($excecao->getMessage());
    $erroPagina = 'Não foi possível carregar os dados do produto. Tente novamente.';
}

// ---- Processamento POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $produto && $erroPagina === '') {

    // Captura valores do formulário
    foreach (array_keys($valores) as $campo) {
        $enviado = $_POST[$campo] ?? '';
        $valores[$campo] = is_string($enviado) ? trim($enviado) : '';
    }
    $valores['fracionamento_habilitado'] = isset($_POST['fracionamento_habilitado']) ? '1' : '0';

    // Captura fornecedores selecionados (checkboxes)
    $fornecedoresPost = $_POST['fornecedores'] ?? [];
    if (!is_array($fornecedoresPost)) {
        $fornecedoresPost = [];
    }
    $fornecedoresSelecionados = [];
    foreach ($fornecedoresPost as $fId) {
        if (is_string($fId) && ctype_digit($fId)) {
            $fornecedoresSelecionados[] = $fId;
        }
    }
    $fornecedoresSelecionados = array_unique($fornecedoresSelecionados);

    // --- Validação: nome ---
    if ($valores['nome'] === '') {
        $erros['nome'] = 'Informe o nome do produto.';
    } elseif (!preg_match('/\A.{1,150}\z/us', $valores['nome'])) {
        $erros['nome'] = 'O nome deve ter no máximo 150 caracteres.';
    }

    // --- Validação: categoria ---
    if ($valores['categoria_id'] === '') {
        $erros['categoria_id'] = 'Selecione uma categoria.';
    } elseif (!ctype_digit($valores['categoria_id'])) {
        $erros['categoria_id'] = 'Categoria inválida.';
    } else {
        $categoriaValida = false;
        foreach ($categorias as $cat) {
            if ((string) $cat['id'] === $valores['categoria_id']) {
                $categoriaValida = true;
                break;
            }
        }
        if (!$categoriaValida) {
            $erros['categoria_id'] = 'A categoria selecionada não existe.';
        }
    }

    // --- Validação: fornecedores ---
    if (empty($fornecedoresSelecionados)) {
        $erros['fornecedores'] = 'Selecione pelo menos um fornecedor.';
    } else {
        $idsValidos = array_column($fornecedores, 'id');
        foreach ($fornecedoresSelecionados as $fId) {
            if (!in_array((int) $fId, array_map('intval', $idsValidos), true)) {
                $erros['fornecedores'] = 'Um dos fornecedores selecionados não existe.';
                break;
            }
        }
    }

    // --- Validação: código de barras ---
    if ($valores['codigo_barras'] === '') {
        $erros['codigo_barras'] = 'Informe o código de barras.';
    } elseif (!ctype_digit($valores['codigo_barras'])) {
        $erros['codigo_barras'] = 'O código de barras deve conter apenas números.';
    } elseif (!preg_match('/\A[0-9]{1,50}\z/', $valores['codigo_barras'])) {
        $erros['codigo_barras'] = 'O código de barras deve ter no máximo 50 dígitos.';
    }

    // --- Validação: estoque mínimo ---
    if ($valores['estoque_minimo'] === '') {
        $erros['estoque_minimo'] = 'Informe o estoque mínimo.';
    } elseif (!ctype_digit($valores['estoque_minimo'])) {
        $erros['estoque_minimo'] = 'O estoque mínimo deve ser um número inteiro maior ou igual a zero.';
    } elseif ($valores['estoque_minimo'] > 4294967295) {
        $erros['estoque_minimo'] = 'Valor máximo permitido: 4.294.967.295.';
    }

    // --- Validação: classificação (RF09) ---
    $classificacoesValidas = ['comum', 'vacina', 'controle_especial'];
    if (!in_array($valores['classificacao'], $classificacoesValidas, true)) {
        $erros['classificacao'] = 'Selecione uma classificação válida.';
    }

    // --- Validação: fracionamento (RF09) ---
    $fracionamento = $valores['fracionamento_habilitado'] === '1';

    if ($fracionamento) {
        if ($valores['fator_ml'] === '') {
            $erros['fator_ml'] = 'Informe o fator por mL quando o fracionamento está habilitado.';
        } else {
            $fatorMl = filter_var($valores['fator_ml'], FILTER_VALIDATE_FLOAT);
            if ($fatorMl === false || $fatorMl <= 0 || $fatorMl > 1) {
                $erros['fator_ml'] = 'O fator por mL deve ser um valor entre 0 (exclusivo) e 1 (inclusivo).';
            }
        }

        if ($valores['fator_dose'] === '') {
            $erros['fator_dose'] = 'Informe o fator por dose quando o fracionamento está habilitado.';
        } else {
            $fatorDose = filter_var($valores['fator_dose'], FILTER_VALIDATE_FLOAT);
            if ($fatorDose === false || $fatorDose <= 0 || $fatorDose > 1) {
                $erros['fator_dose'] = 'O fator por dose deve ser um valor entre 0 (exclusivo) e 1 (inclusivo).';
            }
        }
    }

    // ---- Persistência ----
    if (!$erros) {
        $nome            = $valores['nome'];
        $categoriaId     = (int) $valores['categoria_id'];
        $codigoBarras    = $valores['codigo_barras'];
        $estoqueMinimo   = (int) $valores['estoque_minimo'];
        $classificacao   = $valores['classificacao'];
        $fracHabilitado  = $fracionamento ? 1 : 0;
        $fatorMlVal      = $fracionamento ? (float) $valores['fator_ml']   : null;
        $fatorDoseVal    = $fracionamento ? (float) $valores['fator_dose'] : null;

        try {
            mysqli_begin_transaction($conexao);

            // Atualiza o produto
            $sqlUpdate = mysqli_prepare($conexao,
                'UPDATE produtos
                 SET nome = ?, categoria_id = ?, codigo_barras = ?, estoque_minimo = ?,
                     classificacao = ?, fracionamento_habilitado = ?, fator_ml = ?, fator_dose = ?
                 WHERE id = ?'
            );
            try {
                mysqli_stmt_bind_param(
                    $sqlUpdate,
                    'sisisidd' . 'i',
                    $nome,
                    $categoriaId,
                    $codigoBarras,
                    $estoqueMinimo,
                    $classificacao,
                    $fracHabilitado,
                    $fatorMlVal,
                    $fatorDoseVal,
                    $produtoId
                );
                mysqli_stmt_execute($sqlUpdate);
            } finally {
                mysqli_stmt_close($sqlUpdate);
            }

            // Recalcula fornecedores: quais adicionar e quais remover
            $sqlVinculosAtuais = mysqli_prepare($conexao,
                'SELECT fornecedor_id FROM produto_fornecedor WHERE produto_id = ? FOR UPDATE'
            );
            try {
                mysqli_stmt_bind_param($sqlVinculosAtuais, 'i', $produtoId);
                mysqli_stmt_execute($sqlVinculosAtuais);
                $resVinculos = mysqli_stmt_get_result($sqlVinculosAtuais);
                $atuais = [];
                while ($row = mysqli_fetch_assoc($resVinculos)) {
                    $atuais[] = (string) $row['fornecedor_id'];
                }
                mysqli_free_result($resVinculos);
            } finally {
                mysqli_stmt_close($sqlVinculosAtuais);
            }

            $adicionar = array_diff($fornecedoresSelecionados, $atuais);
            $remover   = array_diff($atuais, $fornecedoresSelecionados);

            if (!empty($adicionar)) {
                $sqlInsert = mysqli_prepare($conexao,
                    'INSERT INTO produto_fornecedor (produto_id, fornecedor_id) VALUES (?, ?)'
                );
                try {
                    foreach ($adicionar as $fId) {
                        $fIdInt = (int) $fId;
                        mysqli_stmt_bind_param($sqlInsert, 'ii', $produtoId, $fIdInt);
                        mysqli_stmt_execute($sqlInsert);
                    }
                } finally {
                    mysqli_stmt_close($sqlInsert);
                }
            }

            if (!empty($remover)) {
                $sqlDelete = mysqli_prepare($conexao,
                    'DELETE FROM produto_fornecedor WHERE produto_id = ? AND fornecedor_id = ?'
                );
                try {
                    foreach ($remover as $fId) {
                        $fIdInt = (int) $fId;
                        mysqli_stmt_bind_param($sqlDelete, 'ii', $produtoId, $fIdInt);
                        mysqli_stmt_execute($sqlDelete);
                    }
                } finally {
                    mysqli_stmt_close($sqlDelete);
                }
            }

            mysqli_commit($conexao);
            $atualizado = true;

            // Recarrega valores persistidos
            $valores['nome']                     = $nome;
            $valores['categoria_id']             = (string) $categoriaId;
            $valores['codigo_barras']            = $codigoBarras;
            $valores['estoque_minimo']           = (string) $estoqueMinimo;
            $valores['classificacao']            = $classificacao;
            $valores['fracionamento_habilitado'] = $fracionamento ? '1' : '0';
            $valores['fator_ml']                 = $fatorMlVal !== null ? (string) $fatorMlVal : '';
            $valores['fator_dose']               = $fatorDoseVal !== null ? (string) $fatorDoseVal : '';

        } catch (mysqli_sql_exception $excecao) {
            mysqli_rollback($conexao);

            switch ($excecao->getCode()) {
                case 1062:
                    $erros['codigo_barras'] = 'Já existe outro produto com esse código de barras.';
                    break;
                case 1451:
                    $erros['fornecedores'] = 'Um fornecedor com lotes registrados não pode ser desvinculado.';
                    break;
                case 1452:
                    $erroPagina = 'A categoria ou um dos fornecedores selecionados não existe mais.';
                    break;
                default:
                    error_log($excecao->getMessage());
                    $erroPagina = 'Não foi possível atualizar o produto. Tente novamente.';
                    break;
            }
        }
    }
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<div class="row">
    <div class="col-lg-8">
        <h1 class="mb-3">Alterar produto</h1>

        <?php if ($atualizado): ?>
            <div class="alert alert-success" role="status">
                <i class="bi bi-check-circle-fill"></i> Produto atualizado com sucesso.
            </div>
        <?php endif; ?>
        <?php if ($erroPagina !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= $erroPagina ?></div>
        <?php endif; ?>

        <?php if ($produto): ?>
        <form method="post" action="alterar-produto.php?id=<?= urlencode($produtoId) ?>" class="card card-body">
            <div class="row g-3">

                <!-- Nome -->
                <div class="col-12">
                    <label class="form-label" for="nome">Nome do produto</label>
                    <input
                        class="form-control<?= isset($erros['nome']) ? ' is-invalid' : '' ?>"
                        type="text"
                        id="nome"
                        name="nome"
                        value="<?= htmlspecialchars($valores['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        maxlength="150"
                        required
                        <?php if (isset($erros['nome'])): ?>
                            aria-invalid="true" aria-describedby="nome-erro"
                        <?php endif; ?>
                    >
                    <?php if (isset($erros['nome'])): ?>
                        <div class="invalid-feedback" id="nome-erro"><?= $erros['nome'] ?></div>
                    <?php endif; ?>
                </div>

                <!-- Categoria -->
                <div class="col-md-6">
                    <label class="form-label" for="categoria_id">Categoria</label>
                    <select
                        class="form-select<?= isset($erros['categoria_id']) ? ' is-invalid' : '' ?>"
                        id="categoria_id"
                        name="categoria_id"
                        required
                        <?php if (isset($erros['categoria_id'])): ?>
                            aria-invalid="true" aria-describedby="categoria_id-erro"
                        <?php endif; ?>
                    >
                        <option value="">Selecione...</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>"<?= $valores['categoria_id'] === (string) $cat['id'] ? ' selected' : '' ?>>
                                <?= htmlspecialchars($cat['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($erros['categoria_id'])): ?>
                        <div class="invalid-feedback" id="categoria_id-erro"><?= $erros['categoria_id'] ?></div>
                    <?php endif; ?>
                </div>

                <!-- Código de barras -->
                <div class="col-md-6">
                    <label class="form-label" for="codigo_barras">Código de barras</label>
                    <input
                        class="form-control<?= isset($erros['codigo_barras']) ? ' is-invalid' : '' ?>"
                        type="text"
                        id="codigo_barras"
                        name="codigo_barras"
                        value="<?= htmlspecialchars($valores['codigo_barras'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        maxlength="50"
                        inputmode="numeric"
                        pattern="[0-9]+"
                        required
                        <?php if (isset($erros['codigo_barras'])): ?>
                            aria-invalid="true" aria-describedby="codigo_barras-erro"
                        <?php endif; ?>
                    >
                    <?php if (isset($erros['codigo_barras'])): ?>
                        <div class="invalid-feedback" id="codigo_barras-erro"><?= $erros['codigo_barras'] ?></div>
                    <?php endif; ?>
                </div>

                <!-- Estoque mínimo -->
                <div class="col-md-6">
                    <label class="form-label" for="estoque_minimo">Estoque mínimo</label>
                    <input
                        class="form-control<?= isset($erros['estoque_minimo']) ? ' is-invalid' : '' ?>"
                        type="number"
                        id="estoque_minimo"
                        name="estoque_minimo"
                        value="<?= htmlspecialchars($valores['estoque_minimo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        min="0"
                        max="4294967295"
                        step="1"
                        required
                        <?php if (isset($erros['estoque_minimo'])): ?>
                            aria-invalid="true" aria-describedby="estoque_minimo-erro"
                        <?php endif; ?>
                    >
                    <?php if (isset($erros['estoque_minimo'])): ?>
                        <div class="invalid-feedback" id="estoque_minimo-erro"><?= $erros['estoque_minimo'] ?></div>
                    <?php endif; ?>
                </div>

                <!-- Classificação (RF09) -->
                <div class="col-md-6">
                    <label class="form-label" for="classificacao">Classificação</label>
                    <select
                        class="form-select<?= isset($erros['classificacao']) ? ' is-invalid' : '' ?>"
                        id="classificacao"
                        name="classificacao"
                        required
                        <?php if (isset($erros['classificacao'])): ?>
                            aria-invalid="true" aria-describedby="classificacao-erro"
                        <?php endif; ?>
                    >
                        <option value="comum"<?= $valores['classificacao'] === 'comum' ? ' selected' : '' ?>>Comum</option>
                        <option value="vacina"<?= $valores['classificacao'] === 'vacina' ? ' selected' : '' ?>>Vacina</option>
                        <option value="controle_especial"<?= $valores['classificacao'] === 'controle_especial' ? ' selected' : '' ?>>Controle especial</option>
                    </select>
                    <?php if (isset($erros['classificacao'])): ?>
                        <div class="invalid-feedback" id="classificacao-erro"><?= $erros['classificacao'] ?></div>
                    <?php endif; ?>
                </div>

                <!-- Fornecedores (checkboxes) -->
                <div class="col-12">
                    <fieldset>
                        <legend class="form-label fs-6">Fornecedores</legend>
                        <?php if (empty($fornecedores)): ?>
                            <p class="text-muted">Nenhum fornecedor cadastrado.</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($fornecedores as $forn): ?>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                id="fornecedor_<?= $forn['id'] ?>"
                                                name="fornecedores[]"
                                                value="<?= $forn['id'] ?>"
                                                <?= in_array((string) $forn['id'], $fornecedoresSelecionados, true) ? 'checked' : '' ?>
                                            >
                                            <label class="form-check-label" for="fornecedor_<?= $forn['id'] ?>">
                                                <?= htmlspecialchars($forn['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($erros['fornecedores'])): ?>
                            <div class="text-danger small mt-1"><?= $erros['fornecedores'] ?></div>
                        <?php endif; ?>
                    </fieldset>
                </div>

                <!-- Fracionamento (RF09) -->
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="fracionamento_habilitado"
                            name="fracionamento_habilitado"
                            value="1"
                            <?= $valores['fracionamento_habilitado'] === '1' ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="fracionamento_habilitado">
                            Habilitar fracionamento
                        </label>
                    </div>
                </div>

                <div class="col-md-6"></div>

                <!-- Fator mL -->
                <div class="col-md-6" id="campo-fator-ml"<?= $valores['fracionamento_habilitado'] !== '1' ? ' hidden' : '' ?>>
                    <label class="form-label" for="fator_ml">Fator por mL</label>
                    <input
                        class="form-control<?= isset($erros['fator_ml']) ? ' is-invalid' : '' ?>"
                        type="number"
                        id="fator_ml"
                        name="fator_ml"
                        value="<?= htmlspecialchars($valores['fator_ml'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        min="0.000001"
                        max="1"
                        step="0.000001"
                        <?php if (isset($erros['fator_ml'])): ?>
                            aria-invalid="true" aria-describedby="fator_ml-erro"
                        <?php endif; ?>
                    >
                    <small class="form-text text-muted">Unidades de estoque por 1 mL (ex.: 0.100000 para 10 mL).</small>
                    <?php if (isset($erros['fator_ml'])): ?>
                        <div class="invalid-feedback" id="fator_ml-erro"><?= $erros['fator_ml'] ?></div>
                    <?php endif; ?>
                </div>

                <!-- Fator dose -->
                <div class="col-md-6" id="campo-fator-dose"<?= $valores['fracionamento_habilitado'] !== '1' ? ' hidden' : '' ?>>
                    <label class="form-label" for="fator_dose">Fator por dose</label>
                    <input
                        class="form-control<?= isset($erros['fator_dose']) ? ' is-invalid' : '' ?>"
                        type="number"
                        id="fator_dose"
                        name="fator_dose"
                        value="<?= htmlspecialchars($valores['fator_dose'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        min="0.000001"
                        max="1"
                        step="0.000001"
                        <?php if (isset($erros['fator_dose'])): ?>
                            aria-invalid="true" aria-describedby="fator_dose-erro"
                        <?php endif; ?>
                    >
                    <small class="form-text text-muted">Unidades de estoque por 1 dose (ex.: 0.200000 para 5 doses).</small>
                    <?php if (isset($erros['fator_dose'])): ?>
                        <div class="invalid-feedback" id="fator_dose-erro"><?= $erros['fator_dose'] ?></div>
                    <?php endif; ?>
                </div>

            </div>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-check-lg"></i> Salvar alterações
                </button>
                <a class="btn btn-outline-secondary" href="consultar-produtos.php">Cancelar</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Toggle dos campos de fracionamento -->
<script>
    (function () {
        var checkbox   = document.getElementById('fracionamento_habilitado');
        var campoMl    = document.getElementById('campo-fator-ml');
        var campoDose  = document.getElementById('campo-fator-dose');
        var inputMl    = document.getElementById('fator_ml');
        var inputDose  = document.getElementById('fator_dose');

        if (!checkbox) return;

        function atualizar() {
            var ativo = checkbox.checked;
            campoMl.hidden   = !ativo;
            campoDose.hidden = !ativo;
            inputMl.required   = ativo;
            inputDose.required = ativo;
            if (!ativo) {
                inputMl.value  = '';
                inputDose.value = '';
            }
        }

        checkbox.addEventListener('change', atualizar);
        atualizar();
    })();
</script>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
