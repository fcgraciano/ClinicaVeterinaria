<?php
// =====================================================================
//  CADASTRAR PRODUTO — Processamento POST com validação (RF09)
//  Valida: nome, categoria, fornecedor padrão, estoque mínimo,
//          classificação (controle) e fracionamento.
//  PHP procedural + mysqli · consultas preparadas.
// =====================================================================

$valores = [
    'nome'                     => '',
    'categoria_id'             => '',
    'fornecedor_id'            => '',
    'codigo_barras'            => '',
    'estoque_minimo'           => '',
    'classificacao'            => 'comum',
    'fracionamento_habilitado' => '0',
    'fator_ml'                 => '',
    'fator_dose'               => '',
];

$erros         = [];
$erroCadastro  = '';
$cadastrado    = false;
$categorias    = [];
$fornecedores  = [];

// ---- Carrega categorias e fornecedores para os selects ----
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require __DIR__ . '/../includes/conexao.php';

    $resultado  = mysqli_query($conexao, 'SELECT id, nome FROM categorias ORDER BY nome');
    $categorias = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    mysqli_free_result($resultado);

    $resultado    = mysqli_query($conexao, 'SELECT id, nome FROM fornecedores ORDER BY nome');
    $fornecedores = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    mysqli_free_result($resultado);
} catch (mysqli_sql_exception $excecao) {
    error_log($excecao->getMessage());
    $erroCadastro = 'Não foi possível carregar os dados auxiliares. Tente novamente.';
}

// ---- Processamento POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Captura e sanitiza valores
    foreach (array_keys($valores) as $campo) {
        $enviado = $_POST[$campo] ?? '';
        $valores[$campo] = is_string($enviado) ? trim($enviado) : '';
    }

    // Checkbox vem vazio quando desmarcado
    $valores['fracionamento_habilitado'] = isset($_POST['fracionamento_habilitado']) ? '1' : '0';

    // --- Validação: nome (obrigatório, até 150 caracteres) ---
    if ($valores['nome'] === '') {
        $erros['nome'] = 'Informe o nome do produto.';
    } elseif (!preg_match('/\A.{1,150}\z/us', $valores['nome'])) {
        $erros['nome'] = 'O nome deve ter no máximo 150 caracteres.';
    }

    // --- Validação: categoria (obrigatória, deve existir) ---
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

    // --- Validação: fornecedor padrão (obrigatório, deve existir) ---
    if ($valores['fornecedor_id'] === '') {
        $erros['fornecedor_id'] = 'Selecione um fornecedor padrão.';
    } elseif (!ctype_digit($valores['fornecedor_id'])) {
        $erros['fornecedor_id'] = 'Fornecedor inválido.';
    } else {
        $fornecedorValido = false;
        foreach ($fornecedores as $forn) {
            if ((string) $forn['id'] === $valores['fornecedor_id']) {
                $fornecedorValido = true;
                break;
            }
        }
        if (!$fornecedorValido) {
            $erros['fornecedor_id'] = 'O fornecedor selecionado não existe.';
        }
    }

    // --- Validação: código de barras (obrigatório, apenas números) ---
    if ($valores['codigo_barras'] === '') {
        $erros['codigo_barras'] = 'Informe o código de barras.';
    } elseif (!ctype_digit($valores['codigo_barras'])) {
        $erros['codigo_barras'] = 'O código de barras deve conter apenas números.';
    } elseif (!preg_match('/\A[0-9]{1,50}\z/', $valores['codigo_barras'])) {
        $erros['codigo_barras'] = 'O código de barras deve ter no máximo 50 dígitos.';
    }

    // --- Validação: estoque mínimo (obrigatório, inteiro >= 0) ---
    if ($valores['estoque_minimo'] === '') {
        $erros['estoque_minimo'] = 'Informe o estoque mínimo.';
    } elseif (!ctype_digit($valores['estoque_minimo'])) {
        $erros['estoque_minimo'] = 'O estoque mínimo deve ser um número inteiro maior ou igual a zero.';
    }

    // --- Validação: classificação (controle — RF09) ---
    $classificacoesValidas = ['comum', 'vacina', 'controle_especial'];
    if (!in_array($valores['classificacao'], $classificacoesValidas, true)) {
        $erros['classificacao'] = 'Selecione uma classificação válida.';
    }

    // --- Validação: fracionamento (RF09) ---
    $fracionamento = $valores['fracionamento_habilitado'] === '1';

    if ($fracionamento) {
        // Fator mL: obrigatório quando fracionamento ativo, decimal > 0 e <= 1
        if ($valores['fator_ml'] === '') {
            $erros['fator_ml'] = 'Informe o fator por mL quando o fracionamento está habilitado.';
        } else {
            $fatorMl = filter_var($valores['fator_ml'], FILTER_VALIDATE_FLOAT);
            if ($fatorMl === false || $fatorMl <= 0 || $fatorMl > 1) {
                $erros['fator_ml'] = 'O fator por mL deve ser um valor decimal entre 0 (exclusivo) e 1 (inclusivo).';
            }
        }

        // Fator dose: obrigatório quando fracionamento ativo, decimal > 0 e <= 1
        if ($valores['fator_dose'] === '') {
            $erros['fator_dose'] = 'Informe o fator por dose quando o fracionamento está habilitado.';
        } else {
            $fatorDose = filter_var($valores['fator_dose'], FILTER_VALIDATE_FLOAT);
            if ($fatorDose === false || $fatorDose <= 0 || $fatorDose > 1) {
                $erros['fator_dose'] = 'O fator por dose deve ser um valor decimal entre 0 (exclusivo) e 1 (inclusivo).';
            }
        }
    }

    // ---- Persistência (somente se não há erros) ----
    if (!$erros && $erroCadastro === '') {
        $nome            = $valores['nome'];
        $categoriaId     = (int) $valores['categoria_id'];
        $fornecedorId    = (int) $valores['fornecedor_id'];
        $codigoBarras    = $valores['codigo_barras'];
        $estoqueMinimo   = (int) $valores['estoque_minimo'];
        $classificacao   = $valores['classificacao'];
        $fracHabilitado  = $fracionamento ? 1 : 0;
        $fatorMlVal      = $fracionamento ? (float) $valores['fator_ml']   : null;
        $fatorDoseVal    = $fracionamento ? (float) $valores['fator_dose'] : null;

        try {
            mysqli_begin_transaction($conexao);

            // Insere o produto
            $sqlProduto = mysqli_prepare($conexao,
                'INSERT INTO produtos (nome, categoria_id, codigo_barras, estoque_minimo, classificacao, fracionamento_habilitado, fator_ml, fator_dose)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            try {
                mysqli_stmt_bind_param(
                    $sqlProduto,
                    'sisisidd',
                    $nome,
                    $categoriaId,
                    $codigoBarras,
                    $estoqueMinimo,
                    $classificacao,
                    $fracHabilitado,
                    $fatorMlVal,
                    $fatorDoseVal
                );
                mysqli_stmt_execute($sqlProduto);
                $produtoId = (int) mysqli_insert_id($conexao);
            } finally {
                mysqli_stmt_close($sqlProduto);
            }

            // Vincula fornecedor padrão na tabela produto_fornecedor
            $sqlFornecedor = mysqli_prepare($conexao,
                'INSERT INTO produto_fornecedor (produto_id, fornecedor_id) VALUES (?, ?)'
            );
            try {
                mysqli_stmt_bind_param($sqlFornecedor, 'ii', $produtoId, $fornecedorId);
                mysqli_stmt_execute($sqlFornecedor);
            } finally {
                mysqli_stmt_close($sqlFornecedor);
            }

            mysqli_commit($conexao);
            $cadastrado = true;
            // Limpa o formulário após sucesso
            $valores = array_fill_keys(array_keys($valores), '');
            $valores['classificacao']            = 'comum';
            $valores['fracionamento_habilitado'] = '0';
        } catch (mysqli_sql_exception $excecao) {
            mysqli_rollback($conexao);

            if ($excecao->getCode() === 1062) {
                $erros['codigo_barras'] = 'Já existe um produto com esse código de barras.';
            } else {
                error_log($excecao->getMessage());
                $erroCadastro = 'Não foi possível cadastrar o produto. Tente novamente.';
            }
        }
    }
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<div class="row">
    <div class="col-lg-8">
        <h1 class="mb-3">Cadastrar produto</h1>

        <?php if ($cadastrado): ?>
            <div class="alert alert-success" role="status">Produto cadastrado com sucesso.</div>
        <?php endif; ?>
        <?php if ($erroCadastro !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= $erroCadastro ?></div>
        <?php endif; ?>

        <form method="post" action="cadastrar-produto.php" class="card card-body">
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

                <!-- Fornecedor padrão -->
                <div class="col-md-6">
                    <label class="form-label" for="fornecedor_id">Fornecedor padrão</label>
                    <select
                        class="form-select<?= isset($erros['fornecedor_id']) ? ' is-invalid' : '' ?>"
                        id="fornecedor_id"
                        name="fornecedor_id"
                        required
                        <?php if (isset($erros['fornecedor_id'])): ?>
                            aria-invalid="true" aria-describedby="fornecedor_id-erro"
                        <?php endif; ?>
                    >
                        <option value="">Selecione...</option>
                        <?php foreach ($fornecedores as $forn): ?>
                            <option value="<?= $forn['id'] ?>"<?= $valores['fornecedor_id'] === (string) $forn['id'] ? ' selected' : '' ?>>
                                <?= htmlspecialchars($forn['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($erros['fornecedor_id'])): ?>
                        <div class="invalid-feedback" id="fornecedor_id-erro"><?= $erros['fornecedor_id'] ?></div>
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

                <!-- Classificação (controle — RF09) -->
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

                <!-- Fator mL (visível quando fracionamento habilitado) -->
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

                <!-- Fator dose (visível quando fracionamento habilitado) -->
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
                <button class="btn btn-primary" type="submit">Cadastrar produto</button>
                <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<!-- Toggle dos campos de fracionamento via JS -->
<script>
    (function () {
        var checkbox   = document.getElementById('fracionamento_habilitado');
        var campoMl    = document.getElementById('campo-fator-ml');
        var campoDose  = document.getElementById('campo-fator-dose');
        var inputMl    = document.getElementById('fator_ml');
        var inputDose  = document.getElementById('fator_dose');

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
