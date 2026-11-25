<?php

require_once __DIR__ . '/../includes/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteção do formulário
if (empty($_SESSION["csrf_animais"])) {
    $_SESSION["csrf_animais"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_animais"];

$mensagem = $_SESSION["mensagem_animal"] ?? "";
$tipo = $_SESSION["tipo_animal"] ?? "";

unset($_SESSION["mensagem_animal"]);
unset($_SESSION["tipo_animal"]);

// Proteger os textos exibidos
function textoAnimal($valor) {
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Formatar CPF
function formatarCpfAnimal($cpf) {

    $cpf = preg_replace('/\D/', '', $cpf);

    if (strlen($cpf) == 11) {

        return substr($cpf, 0, 3) . "." .
               substr($cpf, 3, 3) . "." .
               substr($cpf, 6, 3) . "-" .
               substr($cpf, 9, 2);

    }

    return $cpf;
}

// Formatar datas
function formatarDataAnimal($data) {

    if (empty($data) || $data == "0000-00-00") {
        return "Não informado";
    }

    return date("d/m/Y", strtotime($data));
}

// Formatar peso
function formatarPesoAnimal($peso) {

    if ($peso === null || $peso === "") {
        return "Não informado";
    }

    return number_format((float) $peso, 2, ",", ".") . " kg";
}

// Identificar status do animal
function statusAnimal($animal) {

    if (!empty($animal["data_obito"])) {
        return "Falecido";
    }

    if ($animal["ativo"] == 1) {
        return "Ativo";
    }

    return "Inativo";
}

// Opções de castração
$opcoesCastrado = [
    "Sim",
    "Não",
    "Não informado"
];

// ========================================
// EDITAR OU REGISTRAR ÓBITO
// ========================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $acao = $_POST["acao"] ?? "";
    $id = (int) ($_POST["id"] ?? 0);

    $tokenRecebido = $_POST["csrf_token"] ?? "";

    if (
        !is_string($tokenRecebido) ||
        !hash_equals($token, $tokenRecebido)
    ) {

        $mensagem = "Solicitação inválida. Atualize a página.";
        $tipo = "danger";

    } elseif ($id <= 0) {

        $mensagem = "Animal inválido.";
        $tipo = "danger";

    } elseif ($acao == "editar") {

        $peso = trim($_POST["peso"] ?? "");
        $raca = trim($_POST["raca"] ?? "");
        $cor = trim($_POST["cor"] ?? "");
        $castrado = trim($_POST["castrado"] ?? "");
        $observacoes = trim($_POST["observacoes"] ?? "");

        if (
            $peso != "" &&
            (
                !is_numeric($peso) ||
                $peso < 0.01 ||
                $peso > 9999.99
            )
        ) {

            $mensagem = "Informe um peso válido.";
            $tipo = "danger";

        } elseif (!in_array($castrado, $opcoesCastrado, true)) {

            $mensagem = "Selecione uma opção de castração válida.";
            $tipo = "danger";

        } elseif (
            mb_strlen($raca) > 100 ||
            mb_strlen($cor) > 100 ||
            mb_strlen($observacoes) > 2000
        ) {

            $mensagem = "Um dos campos ultrapassou o limite de caracteres.";
            $tipo = "danger";

        } else {

            try {

                $pesoBanco = $peso != ""
                    ? (float) $peso
                    : null;

                // Atualizar somente o animal selecionado
                $sqlEditar = "UPDATE animais SET
                                peso = ?,
                                raca = ?,
                                cor = ?,
                                castrado = ?,
                                observacoes = ?
                              WHERE id = ?
                              AND ativo = 1
                              AND data_obito IS NULL";

                $stmtEditar = mysqli_prepare(
                    $conexao,
                    $sqlEditar
                );

                mysqli_stmt_bind_param(
                    $stmtEditar,
                    "dssssi",
                    $pesoBanco,
                    $raca,
                    $cor,
                    $castrado,
                    $observacoes,
                    $id
                );

                mysqli_stmt_execute($stmtEditar);

                if (mysqli_stmt_affected_rows($stmtEditar) > 0) {

                    $mensagem = "Dados do animal atualizados com sucesso!";
                    $tipo = "success";

                } else {

                    $mensagem = "Nenhuma alteração realizada ou animal indisponível.";
                    $tipo = "warning";

                }

                mysqli_stmt_close($stmtEditar);

            } catch (Exception $erro) {

                $mensagem = "Não foi possível atualizar o animal.";
                $tipo = "danger";

                error_log($erro->getMessage());

            }

        }

    } elseif ($acao == "obito") {

        $data_obito = trim($_POST["data_obito"] ?? "");

        $data = DateTime::createFromFormat(
            '!Y-m-d',
            $data_obito
        );

        $dataValida = $data !== false
            && $data->format('Y-m-d') === $data_obito
            && $data_obito <= date("Y-m-d");

        if (!$dataValida) {

            $mensagem = "Informe uma data de falecimento válida.";
            $tipo = "danger";

        } else {

            try {

                // Registrar o óbito sem apagar o histórico
                $sqlObito = "UPDATE animais SET
                                ativo = 0,
                                data_obito = ?
                             WHERE id = ?
                             AND data_obito IS NULL
                             AND (
                                 data_nascimento IS NULL
                                 OR data_nascimento <= ?
                             )";

                $stmtObito = mysqli_prepare(
                    $conexao,
                    $sqlObito
                );

                mysqli_stmt_bind_param(
                    $stmtObito,
                    "sis",
                    $data_obito,
                    $id,
                    $data_obito
                );

                mysqli_stmt_execute($stmtObito);

                if (mysqli_stmt_affected_rows($stmtObito) > 0) {

                    $mensagem = "Óbito registrado com sucesso. O histórico do animal foi preservado.";
                    $tipo = "success";

                } else {

                    $mensagem = "Não foi possível registrar o óbito. Verifique o animal e a data informada.";
                    $tipo = "warning";

                }

                mysqli_stmt_close($stmtObito);

            } catch (Exception $erro) {

                $mensagem = "Erro ao registrar o óbito do animal.";
                $tipo = "danger";

                error_log($erro->getMessage());

            }

        }

    } else {

        $mensagem = "Operação inválida.";
        $tipo = "danger";

    }

    // Evitar repetir a operação ao atualizar a página
    $_SESSION["mensagem_animal"] = $mensagem;
    $_SESSION["tipo_animal"] = $tipo;

    header("Location: animais.php");
    exit;

}

// ========================================
// PESQUISA E FILTROS
// ========================================

$pesquisa = trim($_GET["pesquisa"] ?? "");
$filtro = $_GET["status"] ?? "todos";

$filtrosValidos = [
    "todos",
    "ativos",
    "inativos",
    "falecidos"
];

if (!in_array($filtro, $filtrosValidos, true)) {
    $filtro = "todos";
}

// Buscar animais e seus respectivos tutores
$sql = "SELECT
            animais.*,
            tutores.nome AS nome_tutor,
            tutores.cpf AS cpf_tutor,
            tutores.telefone AS telefone_tutor,
            tutores.email AS email_tutor
        FROM animais
        INNER JOIN tutores
            ON animais.tutor_id = tutores.id
        WHERE 1 = 1";

// Filtro de status
if ($filtro == "ativos") {

    $sql .= " AND animais.ativo = 1
              AND animais.data_obito IS NULL";

} elseif ($filtro == "inativos") {

    $sql .= " AND animais.ativo = 0
              AND animais.data_obito IS NULL";

} elseif ($filtro == "falecidos") {

    $sql .= " AND animais.data_obito IS NOT NULL";

}

// Pesquisa por nome, tutor, CPF, espécie ou ID
if ($pesquisa != "") {

    $sql .= " AND (
                animais.nome LIKE ?
                OR tutores.nome LIKE ?
                OR REPLACE(
                    REPLACE(tutores.cpf, '.', ''),
                    '-', ''
                ) LIKE ?
                OR animais.especie LIKE ?
                OR CAST(animais.id AS CHAR) = ?
              )";

}

$sql .= " ORDER BY animais.nome ASC, animais.id ASC";

$stmt = mysqli_prepare($conexao, $sql);

if ($pesquisa != "") {

    $termo = "%" . $pesquisa . "%";

    $cpfPesquisa = preg_replace('/\D/', '', $pesquisa);

    $termoCpf = preg_match('/^[\d.\-\s]+$/', $pesquisa)
        && $cpfPesquisa != ""
        ? "%" . $cpfPesquisa . "%"
        : $termo;

    $codigoPesquisa = ltrim($pesquisa, "#");

    mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $termo,
        $termo,
        $termoCpf,
        $termo,
        $codigoPesquisa
    );

}

mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);

$animais = [];

while ($animal = mysqli_fetch_assoc($resultado)) {

    $animais[] = $animal;

}

mysqli_stmt_close($stmt);

// ========================================
// CONTADORES
// ========================================

$sqlContadores = "SELECT
                    COUNT(*) AS total,

                    COALESCE(SUM(
                        ativo = 1
                        AND data_obito IS NULL
                    ), 0) AS ativos,

                    COALESCE(SUM(
                        ativo = 0
                        AND data_obito IS NULL
                    ), 0) AS inativos,

                    COALESCE(SUM(
                        data_obito IS NOT NULL
                    ), 0) AS falecidos

                  FROM animais";

$resultadoContadores = mysqli_query(
    $conexao,
    $sqlContadores
);

$contadores = mysqli_fetch_assoc(
    $resultadoContadores
);

$total = (int) $contadores["total"];
$totalAtivos = (int) $contadores["ativos"];
$totalInativos = (int) $contadores["inativos"];
$totalFalecidos = (int) $contadores["falecidos"];

include __DIR__ . '/../includes/cabecalho.php';

?>

<style>

    .animais-container {
        padding-top: 25px;
        padding-bottom: 40px;
    }

    .animais-banner {
        background: linear-gradient(135deg, #11796f, #159589);
        color: white;
        padding: 35px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 4px 12px rgba(17, 121, 111, 0.15);
    }

    .animais-banner h1 {
        font-size: 30px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .animais-banner p {
        margin-bottom: 0;
        font-size: 16px;
    }

    .animais-card {
        background-color: white;
        border: 1px solid #dedede;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
    }

    .animais-total {
        background-color: #eaf5f3;
        border-left: 5px solid #11796f;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 25px;
    }

    .animais-total p {
        color: #555;
        margin-bottom: 5px;
        font-size: 14px;
    }

    .animais-total h2 {
        color: #11796f;
        font-size: 32px;
        font-weight: 600;
        margin-bottom: 0;
    }

    .animais-total-inativos {
        background-color: #f1f1f1;
        border-left-color: #777;
    }

    .animais-total-inativos h2 {
        color: #777;
    }

    .animais-total-falecidos {
        background-color: #fcebec;
        border-left-color: #b94a48;
    }

    .animais-total-falecidos h2 {
        color: #b94a48;
    }

    .animais-container .form-control,
    .animais-container .form-select {
        border-radius: 7px;
        padding: 10px 12px;
    }

    .animais-container .form-control:focus,
    .animais-container .form-select:focus {
        border-color: #11796f;
        box-shadow: 0 0 0 0.2rem rgba(17, 121, 111, 0.15);
    }

    .animais-container label {
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 8px;
    }

    .animais-botao {
        background-color: #11796f;
        color: white;
        border: none;
        border-radius: 7px;
        padding: 10px 20px;
        text-decoration: none;
        display: inline-block;
        transition: 0.3s;
        cursor: pointer;
    }

    .animais-botao:hover {
        background-color: #0d625a;
        color: white;
    }

    .animais-voltar {
        background-color: white;
        color: #11796f;
        border: 1px solid #11796f;
        padding: 10px 20px;
        border-radius: 7px;
        text-decoration: none;
        display: inline-block;
        transition: 0.3s;
    }

    .animais-voltar:hover {
        background-color: #eaf5f3;
        color: #11796f;
    }

    .animais-tabela {
        width: 100%;
        margin-bottom: 0;
        border-collapse: collapse;
    }

    .animais-tabela th {
        background-color: #11796f;
        color: white;
        padding: 15px;
        white-space: nowrap;
    }

    .animais-tabela td {
        padding: 15px;
        border-bottom: 1px solid #eeeeee;
        vertical-align: middle;
    }

    .animais-tabela tbody tr:hover {
        background-color: #f4faf9;
    }

    .animais-acoes {
        display: flex;
        gap: 7px;
        flex-wrap: wrap;
        min-width: 150px;
    }

    .animais-acao {
        border: none;
        border-radius: 6px;
        padding: 8px 13px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: 0.3s;
        white-space: nowrap;
    }

    .animais-visualizar {
        background-color: #e0f2ef;
        color: #11796f;
    }

    .animais-editar {
        background-color: #fff3cd;
        color: #856404;
    }

    .animais-obito {
        background-color: #f8d7da;
        color: #842029;
    }

    .animais-acao:hover {
        opacity: 0.8;
    }

    .animais-status {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .animais-ativo {
        background-color: #dff5e7;
        color: #198754;
    }

    .animais-inativo {
        background-color: #eeeeee;
        color: #666;
    }

    .animais-falecido {
        background-color: #f8d7da;
        color: #842029;
    }

    .animais-tabela .animais-vazio {
        text-align: center;
        padding: 40px;
        color: #777;
    }

    .animais-modal-titulo {
        color: #11796f;
        font-weight: 600;
    }

    .animais-modal-secao {
        color: #11796f;
        font-size: 18px;
        font-weight: 600;
        border-bottom: 1px solid #eeeeee;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .animais-dado {
        background-color: #f5f7f7;
        padding: 12px;
        border-radius: 7px;
        margin-bottom: 15px;
        overflow-wrap: anywhere;
    }

    .animais-dado strong {
        display: block;
        color: #11796f;
        font-size: 13px;
        margin-bottom: 5px;
    }

    .animais-tutor {
        font-weight: 500;
        color: #11796f;
    }

    .animais-codigo {
        font-size: 13px;
        font-weight: 600;
        color: #777;
    }

    .animais-peso {
        font-weight: 600;
        color: #11796f;
        white-space: nowrap;
    }

    .animais-aviso-obito {
        background-color: #fff1f2;
        border-left: 4px solid #dc3545;
        border-radius: 8px;
        padding: 15px;
        color: #842029;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .animais-confirmar-obito {
        background-color: #dc3545;
        color: white;
        border: none;
        border-radius: 7px;
        padding: 10px 20px;
        cursor: pointer;
    }

    .animais-confirmar-obito:hover {
        background-color: #bb2d3b;
    }

    .animais-info-edicao {
        background-color: #eaf5f3;
        border-left: 4px solid #11796f;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 25px;
        color: #11796f;
    }

    .animais-data-obito {
        display: block;
        font-size: 12px;
        color: #777;
        margin-top: 6px;
    }

    @media (max-width: 768px) {

        .animais-banner {
            padding: 25px;
        }

        .animais-banner h1 {
            font-size: 25px;
        }

        .animais-card {
            padding: 15px;
        }

    }

</style>

<div class="container animais-container">

    <!-- Banner -->
    <div class="animais-banner">

        <h1>Animais Cadastrados</h1>

        <p>
            Consulte, atualize e gerencie os animais
            cadastrados na clínica e seus respectivos tutores.
        </p>

    </div>

    <!-- Mensagem de sucesso ou erro -->
    <?php if ($mensagem != ""): ?>

        <div class="alert alert-<?php echo textoAnimal($tipo); ?>
                    alert-dismissible fade show"
             role="alert">

            <?php echo textoAnimal($mensagem); ?>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Fechar">
            </button>

        </div>

    <?php endif; ?>

    <!-- Contadores -->
    <div class="row">

        <div class="col-md-3 col-6">

            <div class="animais-total">

                <p>Total cadastrados</p>

                <h2><?php echo $total; ?></h2>

            </div>

        </div>

        <div class="col-md-3 col-6">

            <div class="animais-total">

                <p>Animais ativos</p>

                <h2><?php echo $totalAtivos; ?></h2>

            </div>

        </div>

        <div class="col-md-3 col-6">

            <div class="animais-total animais-total-inativos">

                <p>Animais inativos</p>

                <h2><?php echo $totalInativos; ?></h2>

            </div>

        </div>

        <div class="col-md-3 col-6">

            <div class="animais-total animais-total-falecidos">

                <p>Animais falecidos</p>

                <h2><?php echo $totalFalecidos; ?></h2>

            </div>

        </div>

    </div>

    <!-- Pesquisa -->
    <div class="animais-card">

        <form method="GET" action="animais.php">

            <div class="row align-items-end">

                <div class="col-md-5 mb-3">

                    <label for="pesquisa">
                        Pesquisar animal
                    </label>

                    <input type="text"
                           class="form-control"
                           id="pesquisa"
                           name="pesquisa"
                           placeholder="Nome, tutor, CPF, espécie ou ID..."
                           value="<?php echo textoAnimal($pesquisa); ?>">

                </div>

                <div class="col-md-3 mb-3">

                    <label for="status">
                        Status do animal
                    </label>

                    <select class="form-select"
                            name="status"
                            id="status">

                        <option value="todos"
                            <?php echo $filtro == "todos" ? "selected" : ""; ?>>

                            Todos os animais

                        </option>

                        <option value="ativos"
                            <?php echo $filtro == "ativos" ? "selected" : ""; ?>>

                            Animais ativos

                        </option>

                        <option value="inativos"
                            <?php echo $filtro == "inativos" ? "selected" : ""; ?>>

                            Animais inativos

                        </option>

                        <option value="falecidos"
                            <?php echo $filtro == "falecidos" ? "selected" : ""; ?>>

                            Animais falecidos

                        </option>

                    </select>

                </div>

                <div class="col-md-4 mb-3">

                    <div class="d-flex flex-wrap gap-2">

                        <button type="submit"
                                class="animais-botao">

                            Pesquisar

                        </button>

                        <a href="animais.php"
                           class="animais-voltar">

                            Limpar

                        </a>

                        <a href="cadastro_animal.php"
                           class="animais-botao">

                            + Novo Animal

                        </a>

                    </div>

                </div>

            </div>

        </form>

    </div>

    <!-- Lista de animais -->
    <div class="animais-card">

        <h4 class="animais-modal-secao">

            Lista de Animais

        </h4>

        <p class="text-muted">

            Animais encontrados:

            <strong>
                <?php echo count($animais); ?>
            </strong>

        </p>

        <div class="table-responsive">

            <table class="animais-tabela">

                <thead>

                    <tr>

                        <th>ID</th>
                        <th>Nome do animal</th>
                        <th>Espécie</th>
                        <th>Raça</th>
                        <th>Sexo</th>
                        <th>Peso</th>
                        <th>Tutor responsável</th>
                        <th>Status</th>
                        <th>Ações</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (count($animais) > 0): ?>

                        <?php foreach ($animais as $animal): ?>

                            <?php
                            $idAnimal = (int) $animal["id"];
                            $statusAtual = statusAnimal($animal);
                            ?>

                            <tr>

                                <!-- Código do animal -->
                                <td>

                                    <span class="animais-codigo">

                                        #<?php echo $idAnimal; ?>

                                    </span>

                                </td>

                                <!-- Nome -->
                                <td>

                                    <strong>

                                        <?php echo textoAnimal($animal["nome"]); ?>

                                    </strong>

                                </td>

                                <!-- Espécie -->
                                <td>

                                    <?php echo textoAnimal($animal["especie"]); ?>

                                </td>

                                <!-- Raça -->
                                <td>

                                    <?php echo textoAnimal($animal["raca"] ?: "Não informado"); ?>

                                </td>

                                <!-- Sexo -->
                                <td>

                                    <?php echo textoAnimal($animal["sexo"]); ?>

                                </td>

                                <!-- Peso -->
                                <td>

                                    <span class="animais-peso">

                                        <?php echo formatarPesoAnimal($animal["peso"]); ?>

                                    </span>

                                </td>

                                <!-- Tutor -->
                                <td>

                                    <span class="animais-tutor">

                                        <?php echo textoAnimal($animal["nome_tutor"]); ?>

                                    </span>

                                    <small class="d-block text-muted">

                                        CPF:

                                        <?php echo textoAnimal(
                                            formatarCpfAnimal($animal["cpf_tutor"])
                                        ); ?>

                                    </small>

                                </td>

                                <!-- Status -->
                                <td>

                                    <?php if ($statusAtual == "Falecido"): ?>

                                        <span class="animais-status animais-falecido">

                                            Falecido

                                        </span>

                                        <small class="animais-data-obito">

                                            <?php echo formatarDataAnimal($animal["data_obito"]); ?>

                                        </small>

                                    <?php elseif ($statusAtual == "Ativo"): ?>

                                        <span class="animais-status animais-ativo">

                                            Ativo

                                        </span>

                                    <?php else: ?>

                                        <span class="animais-status animais-inativo">

                                            Inativo

                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- Ações -->
                                <td>

                                    <div class="animais-acoes">

                                        <button type="button"
                                                class="animais-acao animais-visualizar"
                                                data-bs-toggle="modal"
                                                data-bs-target="#visualizarAnimal<?php echo $idAnimal; ?>">

                                            Visualizar

                                        </button>

                                        <?php if ($statusAtual == "Ativo"): ?>

                                            <button type="button"
                                                    class="animais-acao animais-editar"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editarAnimal<?php echo $idAnimal; ?>">

                                                Editar

                                            </button>

                                        <?php endif; ?>

                                        <?php if ($statusAtual != "Falecido"): ?>

                                            <button type="button"
                                                    class="animais-acao animais-obito"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#obitoAnimal<?php echo $idAnimal; ?>">

                                                Registrar óbito

                                            </button>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="9"
                                class="animais-vazio">

                                Nenhum animal encontrado.

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- Botão voltar -->
    <a href="index.php"
       class="animais-voltar">

        ← Voltar

    </a>

    <?php foreach ($animais as $animal): ?>

        <?php
        $idAnimal = (int) $animal["id"];
        $statusAtual = statusAnimal($animal);
        ?>

        <!-- ======================================== -->
        <!-- JANELA DE VISUALIZAÇÃO -->
        <!-- ======================================== -->

        <div class="modal fade"
             id="visualizarAnimal<?php echo $idAnimal; ?>"
             tabindex="-1"
             aria-hidden="true">

            <div class="modal-dialog modal-lg modal-dialog-scrollable">

                <div class="modal-content">

                    <div class="modal-header">

                        <h5 class="modal-title animais-modal-titulo">

                            Dados do Animal

                        </h5>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Fechar">
                        </button>

                    </div>

                    <div class="modal-body">

                        <h5 class="animais-modal-secao">

                            Informações do Animal

                        </h5>

                        <div class="row">

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>Código do animal</strong>

                                    #<?php echo $idAnimal; ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>Nome</strong>

                                    <?php echo textoAnimal($animal["nome"]); ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>Espécie</strong>

                                    <?php echo textoAnimal($animal["especie"]); ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>Raça</strong>

                                    <?php echo textoAnimal($animal["raca"] ?: "Não informado"); ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>Sexo</strong>

                                    <?php echo textoAnimal($animal["sexo"]); ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>Data de nascimento</strong>

                                    <?php echo formatarDataAnimal($animal["data_nascimento"]); ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>Peso registrado</strong>

                                    <?php echo formatarPesoAnimal($animal["peso"]); ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>Cor / Pelagem</strong>

                                    <?php echo textoAnimal($animal["cor"] ?: "Não informado"); ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>Castrado</strong>

                                    <?php echo textoAnimal($animal["castrado"] ?: "Não informado"); ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>Status</strong>

                                    <?php echo textoAnimal($statusAtual); ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>Data de cadastro</strong>

                                    <?php echo formatarDataAnimal($animal["data_cadastro"]); ?>

                                </div>

                            </div>

                            <?php if ($statusAtual == "Falecido"): ?>

                                <div class="col-md-6">

                                    <div class="animais-dado">

                                        <strong>Data do falecimento</strong>

                                        <?php echo formatarDataAnimal($animal["data_obito"]); ?>

                                    </div>

                                </div>

                            <?php endif; ?>

                        </div>

                        <!-- Dados do tutor -->
                        <h5 class="animais-modal-secao">

                            Tutor Responsável

                        </h5>

                        <div class="row">

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>Nome do tutor</strong>

                                    <?php echo textoAnimal($animal["nome_tutor"]); ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>CPF do tutor</strong>

                                    <?php echo textoAnimal(
                                        formatarCpfAnimal($animal["cpf_tutor"])
                                    ); ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>Telefone do tutor</strong>

                                    <?php echo textoAnimal($animal["telefone_tutor"]); ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>E-mail do tutor</strong>

                                    <?php echo textoAnimal($animal["email_tutor"] ?: "Não informado"); ?>

                                </div>

                            </div>

                        </div>

                        <!-- Observações -->
                        <h5 class="animais-modal-secao">

                            Informações Adicionais

                        </h5>

                        <div class="animais-dado">

                            <strong>Observações</strong>

                            <?php echo nl2br(
                                textoAnimal(
                                    $animal["observacoes"] ?: "Nenhuma observação."
                                )
                            ); ?>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <button type="button"
                                class="animais-voltar"
                                data-bs-dismiss="modal">

                            Fechar

                        </button>

                    </div>

                </div>

            </div>

        </div>

        <!-- ======================================== -->
        <!-- JANELA DE EDIÇÃO -->
        <!-- ======================================== -->

        <?php if ($statusAtual == "Ativo"): ?>

            <div class="modal fade"
                 id="editarAnimal<?php echo $idAnimal; ?>"
                 tabindex="-1"
                 aria-hidden="true">

                <div class="modal-dialog modal-lg modal-dialog-scrollable">

                    <div class="modal-content">

                        <form method="POST" action="animais.php">

                            <input type="hidden"
                                   name="acao"
                                   value="editar">

                            <input type="hidden"
                                   name="id"
                                   value="<?php echo $idAnimal; ?>">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?php echo textoAnimal($token); ?>">

                            <div class="modal-header">

                                <h5 class="modal-title animais-modal-titulo">

                                    Editar Animal

                                </h5>

                                <button type="button"
                                        class="btn-close"
                                        data-bs-dismiss="modal"
                                        aria-label="Fechar">
                                </button>

                            </div>

                            <div class="modal-body">

                                <div class="animais-info-edicao">

                                    <strong>

                                        <?php echo textoAnimal($animal["nome"]); ?>

                                        — #<?php echo $idAnimal; ?>

                                    </strong>

                                    <br>

                                    Tutor:

                                    <?php echo textoAnimal($animal["nome_tutor"]); ?>

                                    <br>

                                    CPF:

                                    <?php echo textoAnimal(
                                        formatarCpfAnimal($animal["cpf_tutor"])
                                    ); ?>

                                </div>

                                <h5 class="animais-modal-secao">

                                    Atualizar informações

                                </h5>

                                <div class="row">

                                    <!-- Peso -->
                                    <div class="col-md-6 mb-4">

                                        <label>

                                            Peso atual registrado

                                        </label>

                                        <input type="text"
                                               class="form-control"
                                               value="<?php echo formatarPesoAnimal($animal["peso"]); ?>"
                                               readonly>

                                    </div>

                                    <div class="col-md-6 mb-4">

                                        <label for="peso<?php echo $idAnimal; ?>">

                                            Novo peso (kg)

                                        </label>

                                        <input type="number"
                                               class="form-control"
                                               id="peso<?php echo $idAnimal; ?>"
                                               name="peso"
                                               min="0.01"
                                               max="9999.99"
                                               step="0.01"
                                               placeholder="Ex.: 15.50"
                                               value="<?php echo textoAnimal($animal["peso"]); ?>">

                                        <small class="text-muted">

                                            Informe o peso mais recente do animal.
                                            Deixe em branco se não souber.

                                        </small>

                                    </div>

                                    <!-- Raça -->
                                    <div class="col-md-6 mb-4">

                                        <label for="raca<?php echo $idAnimal; ?>">

                                            Raça

                                        </label>

                                        <input type="text"
                                               class="form-control"
                                               id="raca<?php echo $idAnimal; ?>"
                                               name="raca"
                                               maxlength="100"
                                               value="<?php echo textoAnimal($animal["raca"]); ?>">

                                    </div>

                                    <!-- Cor -->
                                    <div class="col-md-6 mb-4">

                                        <label for="cor<?php echo $idAnimal; ?>">

                                            Cor / Pelagem

                                        </label>

                                        <input type="text"
                                               class="form-control"
                                               id="cor<?php echo $idAnimal; ?>"
                                               name="cor"
                                               maxlength="100"
                                               value="<?php echo textoAnimal($animal["cor"]); ?>">

                                    </div>

                                    <!-- Castração -->
                                    <div class="col-md-12 mb-4">

                                        <label for="castrado<?php echo $idAnimal; ?>">

                                            O animal é castrado?

                                        </label>

                                        <select class="form-select"
                                                id="castrado<?php echo $idAnimal; ?>"
                                                name="castrado"
                                                required>

                                            <?php foreach ($opcoesCastrado as $opcao): ?>

                                                <option
                                                    value="<?php echo textoAnimal($opcao); ?>"
                                                    <?php echo ($animal["castrado"] ?: "Não informado") == $opcao ? "selected" : ""; ?>>

                                                    <?php echo textoAnimal($opcao); ?>

                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>

                                    <!-- Observações -->
                                    <div class="col-md-12 mb-3">

                                        <label for="observacoes<?php echo $idAnimal; ?>">

                                            Observações

                                        </label>

                                        <textarea class="form-control"
                                                  id="observacoes<?php echo $idAnimal; ?>"
                                                  name="observacoes"
                                                  maxlength="2000"
                                                  rows="5"
                                                  placeholder="Atualize as informações sobre o animal..."><?php echo textoAnimal($animal["observacoes"]); ?></textarea>

                                    </div>

                                </div>

                            </div>

                            <div class="modal-footer">

                                <button type="button"
                                        class="animais-voltar"
                                        data-bs-dismiss="modal">

                                    Cancelar

                                </button>

                                <button type="submit"
                                        class="animais-botao">

                                    Salvar alterações

                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        <?php endif; ?>

        <!-- ======================================== -->
        <!-- JANELA DE REGISTRO DE ÓBITO -->
        <!-- ======================================== -->

        <?php if ($statusAtual != "Falecido"): ?>

            <div class="modal fade"
                 id="obitoAnimal<?php echo $idAnimal; ?>"
                 tabindex="-1"
                 aria-hidden="true">

                <div class="modal-dialog">

                    <div class="modal-content">

                        <form method="POST" action="animais.php">

                            <input type="hidden"
                                   name="acao"
                                   value="obito">

                            <input type="hidden"
                                   name="id"
                                   value="<?php echo $idAnimal; ?>">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?php echo textoAnimal($token); ?>">

                            <div class="modal-header">

                                <h5 class="modal-title text-danger">

                                    Registrar óbito

                                </h5>

                                <button type="button"
                                        class="btn-close"
                                        data-bs-dismiss="modal"
                                        aria-label="Fechar">
                                </button>

                            </div>

                            <div class="modal-body">

                                <div class="animais-aviso-obito">

                                    <strong>Atenção!</strong>

                                    <br><br>

                                    Ao registrar o falecimento,
                                    o animal será retirado da lista
                                    de animais ativos.

                                    <br><br>

                                    Seus dados e seu histórico
                                    continuarão armazenados no sistema.

                                </div>

                                <div class="animais-dado">

                                    <strong>Animal</strong>

                                    <?php echo textoAnimal($animal["nome"]); ?>

                                    — #<?php echo $idAnimal; ?>

                                </div>

                                <div class="animais-dado">

                                    <strong>Tutor responsável</strong>

                                    <?php echo textoAnimal($animal["nome_tutor"]); ?>

                                </div>

                                <div class="animais-dado">

                                    <strong>CPF do tutor</strong>

                                    <?php echo textoAnimal(
                                        formatarCpfAnimal($animal["cpf_tutor"])
                                    ); ?>

                                </div>

                                <div class="mb-4">

                                    <label for="dataObito<?php echo $idAnimal; ?>">

                                        Data do falecimento *

                                    </label>

                                    <input type="date"
                                           class="form-control"
                                           id="dataObito<?php echo $idAnimal; ?>"
                                           name="data_obito"
                                           min="<?php echo textoAnimal($animal["data_nascimento"] ?: ""); ?>"
                                           max="<?php echo date('Y-m-d'); ?>"
                                           value="<?php echo date('Y-m-d'); ?>"
                                           required>

                                </div>

                                <div class="form-check">

                                    <input type="checkbox"
                                           class="form-check-input"
                                           id="confirmarObito<?php echo $idAnimal; ?>"
                                           required>

                                    <label class="form-check-label"
                                           for="confirmarObito<?php echo $idAnimal; ?>">

                                        Confirmo o falecimento deste animal.

                                    </label>

                                </div>

                            </div>

                            <div class="modal-footer">

                                <button type="button"
                                        class="animais-voltar"
                                        data-bs-dismiss="modal">

                                    Cancelar

                                </button>

                                <button type="submit"
                                        class="animais-confirmar-obito">

                                    Confirmar óbito

                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    <?php endforeach; ?>

</div>

<script>

    // Evitar cliques repetidos ao salvar alterações
    document.querySelectorAll(
        '.animais-container form[method="POST"]'
    ).forEach(function (formulario) {

        formulario.addEventListener('submit', function () {

            const botao = formulario.querySelector(
                'button[type="submit"]'
            );

            if (botao) {

                botao.disabled = true;

                botao.textContent = 'Salvando...';

            }

        });

    });

</script>

<?php include __DIR__ . '/../includes/rodape.php'; ?>