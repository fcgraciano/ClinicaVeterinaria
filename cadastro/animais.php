<?php

require_once __DIR__ . '/../includes/conexao.php';

// Proteger os textos exibidos
function textoAnimal($valor) {

    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );

}

// Pesquisar animais
$pesquisa = trim($_GET["pesquisa"] ?? "");

// Buscar animais e seus respectivos tutores
$sql = "SELECT
            animais.*,
            tutores.nome AS nome_tutor,
            tutores.cpf AS cpf_tutor,
            tutores.telefone AS telefone_tutor,
            tutores.email AS email_tutor
        FROM animais
        INNER JOIN tutores
            ON animais.tutor_id = tutores.id";

if ($pesquisa != "") {

    $sql .= " WHERE (
                animais.nome LIKE ?
                OR tutores.nome LIKE ?
                OR tutores.cpf LIKE ?
                OR animais.especie LIKE ?
              )";

}

$sql .= " ORDER BY animais.nome ASC";

$stmt = mysqli_prepare($conexao, $sql);

if ($pesquisa != "") {

    $termo = "%" . $pesquisa . "%";

    $cpfPesquisa = preg_replace('/\D/', '', $pesquisa);

    $termoCpf = $cpfPesquisa != ""
        ? "%" . $cpfPesquisa . "%"
        : $termo;

    mysqli_stmt_bind_param(
        $stmt,
        "ssss",
        $termo,
        $termo,
        $termoCpf,
        $termo
    );

}

mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);

// Guardar os animais encontrados
$animais = [];

while ($animal = mysqli_fetch_assoc($resultado)) {

    $animais[] = $animal;

}

mysqli_stmt_close($stmt);

// Contar todos os animais
$sqlTotal = "SELECT COUNT(*) AS total FROM animais";

$resultadoTotal = mysqli_query($conexao, $sqlTotal);

$total = mysqli_fetch_assoc($resultadoTotal)["total"];

// Contar animais ativos
$sqlAtivos = "SELECT COUNT(*) AS total
              FROM animais
              WHERE ativo = 1";

$resultadoAtivos = mysqli_query($conexao, $sqlAtivos);

$totalAtivos = mysqli_fetch_assoc($resultadoAtivos)["total"];

// Contar animais inativos
$totalInativos = $total - $totalAtivos;

// Formatar CPF do tutor
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

    .animais-container .form-control {
        border-radius: 7px;
        padding: 10px 12px;
    }

    .animais-container .form-control:focus {
        border-color: #11796f;
        box-shadow: 0 0 0 0.2rem rgba(17, 121, 111, 0.15);
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
    }

    .animais-acao {
        border: none;
        border-radius: 6px;
        padding: 8px 13px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: 0.3s;
    }

    .animais-visualizar {
        background-color: #e0f2ef;
        color: #11796f;
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
            Consulte e gerencie os animais cadastrados
            na clínica e seus respectivos tutores.
        </p>

    </div>

    <!-- Contadores -->
    <div class="row">

        <div class="col-md-4">

            <div class="animais-total">

                <p>Total de animais cadastrados</p>

                <h2><?php echo $total; ?></h2>

            </div>

        </div>

        <div class="col-md-4">

            <div class="animais-total">

                <p>Animais ativos</p>

                <h2><?php echo $totalAtivos; ?></h2>

            </div>

        </div>

        <div class="col-md-4">

            <div class="animais-total animais-total-inativos">

                <p>Animais inativos</p>

                <h2><?php echo $totalInativos; ?></h2>

            </div>

        </div>

    </div>

    <!-- Pesquisa -->
    <div class="animais-card">

        <form method="GET" action="animais.php">

            <div class="row align-items-center">

                <div class="col-md-7 mb-2">

                    <input type="text"
                           class="form-control"
                           name="pesquisa"
                           placeholder="Pesquisar animal, tutor, CPF ou espécie..."
                           value="<?php echo textoAnimal($pesquisa); ?>">

                </div>

                <div class="col-md-5 mb-2">

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

        <?php if ($pesquisa != ""): ?>

            <p>

                Resultados encontrados:

                <strong>
                    <?php echo count($animais); ?>
                </strong>

            </p>

        <?php endif; ?>

        <div class="table-responsive">

            <table class="animais-tabela">

                <thead>

                    <tr>

                        <th>Nome do animal</th>
                        <th>Espécie</th>
                        <th>Raça</th>
                        <th>Sexo</th>
                        <th>Tutor responsável</th>
                        <th>Status</th>
                        <th>Ações</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (count($animais) > 0): ?>

                        <?php foreach ($animais as $animal): ?>

                            <tr>

                                <td>

                                    <strong>

                                        <?php echo textoAnimal($animal["nome"]); ?>

                                    </strong>

                                </td>

                                <td>

                                    <?php echo textoAnimal($animal["especie"]); ?>

                                </td>

                                <td>

                                    <?php echo textoAnimal($animal["raca"] ?: "Não informado"); ?>

                                </td>

                                <td>

                                    <?php echo textoAnimal($animal["sexo"]); ?>

                                </td>

                                <td>

                                    <span class="animais-tutor">

                                        <?php echo textoAnimal($animal["nome_tutor"]); ?>

                                    </span>

                                </td>

                                <td>

                                    <?php if ($animal["ativo"] == 1): ?>

                                        <span class="animais-status animais-ativo">

                                            Ativo

                                        </span>

                                    <?php else: ?>

                                        <span class="animais-status animais-inativo">

                                            Inativo

                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <div class="animais-acoes">

                                        <button type="button"
                                                class="animais-acao animais-visualizar"
                                                data-bs-toggle="modal"
                                                data-bs-target="#visualizarAnimal<?php echo (int) $animal["id"]; ?>">

                                            Visualizar

                                        </button>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="7"
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

        <!-- Janela de visualização -->
        <div class="modal fade"
             id="visualizarAnimal<?php echo (int) $animal["id"]; ?>"
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

                        <!-- Dados do animal -->
                        <h5 class="animais-modal-secao">

                            Informações do Animal

                        </h5>

                        <div class="row">

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

                                    <strong>Peso</strong>

                                    <?php

                                    echo $animal["peso"] !== null
                                        ? number_format($animal["peso"], 2, ",", ".") . " kg"
                                        : "Não informado";

                                    ?>

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

                                    <?php echo $animal["ativo"] == 1 ? "Ativo" : "Inativo"; ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="animais-dado">

                                    <strong>Data de cadastro</strong>

                                    <?php echo formatarDataAnimal($animal["data_cadastro"]); ?>

                                </div>

                            </div>

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

                                    <?php echo textoAnimal(formatarCpfAnimal($animal["cpf_tutor"])); ?>

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

                            <?php echo nl2br(textoAnimal($animal["observacoes"] ?: "Nenhuma observação.")); ?>

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

    <?php endforeach; ?>

</div>

<?php include __DIR__ . '/../includes/rodape.php'; ?>