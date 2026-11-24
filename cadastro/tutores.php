<?php

require_once __DIR__ . '/../includes/conexao.php';

$mensagem = "";
$tipo = "";

// Atualizar telefone, e-mail e endereço
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["editar"])) {

    $id = (int) ($_POST["id"] ?? 0);

    $telefone = trim($_POST["telefone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $cep = trim($_POST["cep"] ?? "");
    $logradouro = trim($_POST["logradouro"] ?? "");
    $numero = trim($_POST["numero"] ?? "");
    $bairro = trim($_POST["bairro"] ?? "");
    $cidade = trim($_POST["cidade"] ?? "");
    $estado = trim($_POST["estado"] ?? "");
    $complemento = trim($_POST["complemento"] ?? "");

    // Validar os dados
    if ($id <= 0) {

        $mensagem = "Tutor inválido.";
        $tipo = "danger";

    } elseif ($telefone == "") {

        $mensagem = "O telefone é obrigatório.";
        $tipo = "danger";

    } elseif ($email != "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $mensagem = "Informe um e-mail válido.";
        $tipo = "danger";

    } else {

        try {

            // Atualizar somente os campos permitidos
            $sql = "UPDATE tutores SET
                        telefone = ?,
                        email = ?,
                        cep = ?,
                        logradouro = ?,
                        numero = ?,
                        bairro = ?,
                        cidade = ?,
                        estado = ?,
                        complemento = ?
                    WHERE id = ?";

            $stmt = mysqli_prepare($conexao, $sql);

            if (!$stmt) {
                throw new RuntimeException("Erro ao preparar atualização.");
            }

            mysqli_stmt_bind_param(
                $stmt,
                "sssssssssi",
                $telefone,
                $email,
                $cep,
                $logradouro,
                $numero,
                $bairro,
                $cidade,
                $estado,
                $complemento,
                $id
            );

            if (mysqli_stmt_execute($stmt)) {

                $mensagem = "Dados do tutor salvos com sucesso!";
                $tipo = "success";

            } else {

                $mensagem = "Erro ao atualizar os dados do tutor.";
                $tipo = "danger";

            }

            mysqli_stmt_close($stmt);

        } catch (Exception $erro) {

            $mensagem = "Não foi possível atualizar os dados.";
            $tipo = "danger";

            error_log($erro->getMessage());

        }

    }

}

// Pesquisar tutores
$pesquisa = trim($_GET["pesquisa"] ?? "");

if ($pesquisa != "") {

    $termo = "%" . $pesquisa . "%";

    $cpfPesquisa = preg_replace('/\D/', '', $pesquisa);

    $termoCpf = $cpfPesquisa != ""
        ? "%" . $cpfPesquisa . "%"
        : $termo;

    $sql = "SELECT * FROM tutores
            WHERE nome LIKE ?
            OR cpf LIKE ?
            ORDER BY nome ASC";

    $stmt = mysqli_prepare($conexao, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ss",
        $termo,
        $termoCpf
    );

    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);

    mysqli_stmt_close($stmt);

} else {

    $sql = "SELECT * FROM tutores ORDER BY nome ASC";

    $resultado = mysqli_query($conexao, $sql);

}

// Contar todos os tutores cadastrados
$sqlTotal = "SELECT COUNT(*) AS total FROM tutores";

$resultadoTotal = mysqli_query($conexao, $sqlTotal);

$total = mysqli_fetch_assoc($resultadoTotal)["total"];

// Guardar os tutores encontrados
$tutores = [];

while ($tutor = mysqli_fetch_assoc($resultado)) {

    $tutores[] = $tutor;

}

// Proteger os textos exibidos na página
function textoTutor($valor) {

    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );

}

// Formatar CPF
function formatarCpfTutor($cpf) {

    $cpf = preg_replace('/\D/', '', $cpf);

    if (strlen($cpf) == 11) {

        return substr($cpf, 0, 3) . "." .
               substr($cpf, 3, 3) . "." .
               substr($cpf, 6, 3) . "-" .
               substr($cpf, 9, 2);

    }

    return $cpf;

}

// Estados brasileiros
$estados = [

    "AC" => "Acre",
    "AL" => "Alagoas",
    "AP" => "Amapá",
    "AM" => "Amazonas",
    "BA" => "Bahia",
    "CE" => "Ceará",
    "DF" => "Distrito Federal",
    "ES" => "Espírito Santo",
    "GO" => "Goiás",
    "MA" => "Maranhão",
    "MT" => "Mato Grosso",
    "MS" => "Mato Grosso do Sul",
    "MG" => "Minas Gerais",
    "PA" => "Pará",
    "PB" => "Paraíba",
    "PR" => "Paraná",
    "PE" => "Pernambuco",
    "PI" => "Piauí",
    "RJ" => "Rio de Janeiro",
    "RN" => "Rio Grande do Norte",
    "RS" => "Rio Grande do Sul",
    "RO" => "Rondônia",
    "RR" => "Roraima",
    "SC" => "Santa Catarina",
    "SP" => "São Paulo",
    "SE" => "Sergipe",
    "TO" => "Tocantins"

];

include '../includes/cabecalho.php';

?>

<style>

    .tutores-container {
        padding-top: 25px;
        padding-bottom: 40px;
    }

    .tutores-banner {
        background: linear-gradient(135deg, #11796f, #159589);
        color: white;
        padding: 35px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 4px 12px rgba(17, 121, 111, 0.15);
    }

    .tutores-banner h1 {
        font-size: 30px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .tutores-banner p {
        margin-bottom: 0;
        font-size: 16px;
    }

    .tutores-card {
        background-color: white;
        border: 1px solid #dedede;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
    }

    .tutores-total {
        background-color: #eaf5f3;
        border-left: 5px solid #11796f;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 25px;
    }

    .tutores-total p {
        color: #555;
        margin-bottom: 5px;
    }

    .tutores-total h2 {
        color: #11796f;
        font-size: 32px;
        font-weight: 600;
        margin-bottom: 0;
    }

    .tutores-container .form-control,
    .tutores-container .form-select {
        border-radius: 7px;
        padding: 10px 12px;
    }

    .tutores-container .form-control:focus,
    .tutores-container .form-select:focus {
        border-color: #11796f;
        box-shadow: 0 0 0 0.2rem rgba(17, 121, 111, 0.15);
    }

    .tutores-container .form-control:disabled {
        background-color: #eeeeee;
        color: #777;
        cursor: not-allowed;
    }

    .tutores-botao {
        background-color: #11796f;
        color: white;
        border: none;
        border-radius: 7px;
        padding: 10px 20px;
        text-decoration: none;
        display: inline-block;
        transition: 0.3s;
    }

    .tutores-botao:hover {
        background-color: #0d625a;
        color: white;
    }

    .tutores-voltar {
        background-color: white;
        color: #11796f;
        border: 1px solid #11796f;
        padding: 10px 20px;
        border-radius: 7px;
        text-decoration: none;
        display: inline-block;
        transition: 0.3s;
    }

    .tutores-voltar:hover {
        background-color: #eaf5f3;
        color: #11796f;
    }

    .tutores-tabela {
        width: 100%;
        margin-bottom: 0;
        border-collapse: collapse;
    }

    .tutores-tabela th {
        background-color: #11796f;
        color: white;
        padding: 15px;
        white-space: nowrap;
    }

    .tutores-tabela td {
        padding: 15px;
        border-bottom: 1px solid #eeeeee;
        vertical-align: middle;
    }

    .tutores-tabela tbody tr:hover {
        background-color: #f4faf9;
    }

    .tutores-acoes {
        display: flex;
        gap: 7px;
        flex-wrap: wrap;
    }

    .tutores-acao {
        border: none;
        border-radius: 6px;
        padding: 8px 13px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: 0.3s;
    }

    .tutores-visualizar {
        background-color: #e0f2ef;
        color: #11796f;
    }

    .tutores-editar {
        background-color: #e8efff;
        color: #2458b8;
    }

    .tutores-acao:hover {
        opacity: 0.8;
    }

    .tutores-tabela .tutores-vazio {
        text-align: center;
        padding: 40px;
        color: #777;
    }

    .tutores-modal-titulo {
        color: #11796f;
        font-weight: 600;
    }

    .tutores-modal-secao {
        color: #11796f;
        font-size: 18px;
        font-weight: 600;
        border-bottom: 1px solid #eeeeee;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .tutores-modal label {
        font-weight: 500;
        margin-bottom: 7px;
    }

    .tutores-dado {
        background-color: #f5f7f7;
        padding: 12px;
        border-radius: 7px;
        margin-bottom: 15px;
        overflow-wrap: anywhere;
    }

    .tutores-dado strong {
        display: block;
        color: #11796f;
        font-size: 13px;
        margin-bottom: 5px;
    }

    .tutores-aviso {
        background-color: #eaf5f3;
        color: #11796f;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 25px;
        border-left: 4px solid #11796f;
    }

    .status-cep {
        display: block;
        font-size: 12px;
        margin-top: 5px;
    }

    @media (max-width: 768px) {

        .tutores-banner {
            padding: 25px;
        }

        .tutores-banner h1 {
            font-size: 25px;
        }

        .tutores-card {
            padding: 15px;
        }

    }

</style>

<div class="container tutores-container">

    <!-- Banner -->
    <div class="tutores-banner">

        <h1>Tutores Cadastrados</h1>

        <p>
            Consulte e gerencie os responsáveis pelos
            animais cadastrados na clínica.
        </p>

    </div>

    <!-- Mensagem de sucesso ou erro -->
    <?php if ($mensagem != ""): ?>

        <div class="alert alert-<?php echo $tipo; ?>"
             role="alert">

            <?php echo textoTutor($mensagem); ?>

        </div>

    <?php endif; ?>

    <!-- Total de tutores -->
    <div class="tutores-total">

        <p>Total de tutores cadastrados</p>

        <h2><?php echo $total; ?></h2>

    </div>

    <!-- Pesquisa -->
    <div class="tutores-card">

        <form method="GET" action="tutores.php">

            <div class="row align-items-center">

                <div class="col-md-7 mb-2">

                    <input type="text"
                           class="form-control"
                           name="pesquisa"
                           placeholder="Pesquisar por nome ou CPF..."
                           value="<?php echo textoTutor($pesquisa); ?>">

                </div>

                <div class="col-md-5 mb-2">

                    <div class="d-flex flex-wrap gap-2">

                        <button type="submit"
                                class="tutores-botao">

                            Pesquisar

                        </button>

                        <a href="tutores.php"
                           class="tutores-voltar">

                            Limpar

                        </a>

                        <a href="cadastro_tutor.php"
                           class="tutores-botao">

                            + Novo Tutor

                        </a>

                    </div>

                </div>

            </div>

        </form>

    </div>

    <!-- Tabela de tutores -->
    <div class="tutores-card">

        <h4 class="tutores-modal-secao">

            Lista de Tutores

        </h4>

        <?php if ($pesquisa != ""): ?>

            <p>

                Resultados encontrados:

                <strong>
                    <?php echo count($tutores); ?>
                </strong>

            </p>

        <?php endif; ?>

        <div class="table-responsive">

            <table class="tutores-tabela">

                <thead>

                    <tr>

                        <th>Nome completo</th>
                        <th>CPF</th>
                        <th>Telefone</th>
                        <th>E-mail</th>
                        <th>Ações</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (count($tutores) > 0): ?>

                        <?php foreach ($tutores as $tutor): ?>

                            <tr>

                                <td>

                                    <?php echo textoTutor($tutor["nome"]); ?>

                                </td>

                                <td>

                                    <?php echo textoTutor(formatarCpfTutor($tutor["cpf"])); ?>

                                </td>

                                <td>

                                    <?php echo textoTutor($tutor["telefone"]); ?>

                                </td>

                                <td>

                                    <?php echo textoTutor($tutor["email"] ?: "Não informado"); ?>

                                </td>

                                <td>

                                    <div class="tutores-acoes">

                                        <!-- Botão visualizar -->
                                        <button type="button"
                                                class="tutores-acao tutores-visualizar"
                                                data-bs-toggle="modal"
                                                data-bs-target="#visualizar<?php echo (int) $tutor["id"]; ?>">

                                            Visualizar

                                        </button>

                                        <!-- Botão editar -->
                                        <button type="button"
                                                class="tutores-acao tutores-editar"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editar<?php echo (int) $tutor["id"]; ?>">

                                            Editar

                                        </button>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="5"
                                class="tutores-vazio">

                                Nenhum tutor encontrado.

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- Botão voltar -->
    <a href="index.php"
       class="tutores-voltar">

        ← Voltar

    </a>

    <?php foreach ($tutores as $tutor): ?>

        <!-- Janela de visualização -->
        <div class="modal fade tutores-modal"
             id="visualizar<?php echo (int) $tutor["id"]; ?>"
             tabindex="-1"
             aria-hidden="true">

            <div class="modal-dialog modal-lg modal-dialog-scrollable">

                <div class="modal-content">

                    <div class="modal-header">

                        <h5 class="modal-title tutores-modal-titulo">

                            Dados do Tutor

                        </h5>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Fechar">

                        </button>

                    </div>

                    <div class="modal-body">

                        <!-- Dados pessoais -->
                        <h5 class="tutores-modal-secao">

                            Dados pessoais

                        </h5>

                        <div class="row">

                            <div class="col-md-8">

                                <div class="tutores-dado">

                                    <strong>Nome completo</strong>

                                    <?php echo textoTutor($tutor["nome"]); ?>

                                </div>

                            </div>

                            <div class="col-md-4">

                                <div class="tutores-dado">

                                    <strong>CPF</strong>

                                    <?php echo textoTutor(formatarCpfTutor($tutor["cpf"])); ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="tutores-dado">

                                    <strong>Telefone</strong>

                                    <?php echo textoTutor($tutor["telefone"]); ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="tutores-dado">

                                    <strong>E-mail</strong>

                                    <?php echo textoTutor($tutor["email"] ?: "Não informado"); ?>

                                </div>

                            </div>

                        </div>

                        <!-- Endereço -->
                        <h5 class="tutores-modal-secao">

                            Endereço

                        </h5>

                        <div class="row">

                            <?php

                            $camposEndereco = [

                                "cep" => "CEP",
                                "logradouro" => "Rua / Avenida",
                                "numero" => "Número",
                                "bairro" => "Bairro",
                                "cidade" => "Cidade",
                                "estado" => "Estado",
                                "complemento" => "Complemento"

                            ];

                            ?>

                            <?php foreach ($camposEndereco as $campo => $titulo): ?>

                                <div class="col-md-6">

                                    <div class="tutores-dado">

                                        <strong>

                                            <?php echo textoTutor($titulo); ?>

                                        </strong>

                                        <?php echo textoTutor($tutor[$campo] ?: "Não informado"); ?>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                        <!-- Informações adicionais -->
                        <h5 class="tutores-modal-secao">

                            Informações adicionais

                        </h5>

                        <div class="tutores-dado">

                            <strong>Observações</strong>

                            <?php echo nl2br(textoTutor($tutor["observacoes"] ?: "Nenhuma observação.")); ?>

                        </div>

                        <div class="tutores-dado">

                            <strong>Data de cadastro</strong>

                            <?php echo date("d/m/Y H:i", strtotime($tutor["data_cadastro"])); ?>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <button type="button"
                                class="tutores-voltar"
                                data-bs-dismiss="modal">

                            Fechar

                        </button>

                    </div>

                </div>

            </div>

        </div>

        <!-- Janela de edição -->
        <div class="modal fade tutores-modal"
             id="editar<?php echo (int) $tutor["id"]; ?>"
             tabindex="-1"
             aria-hidden="true">

            <div class="modal-dialog modal-lg modal-dialog-scrollable">

                <div class="modal-content">

                    <form method="POST"
                          action="tutores.php"
                          class="form-editar-tutor">

                        <div class="modal-header">

                            <h5 class="modal-title tutores-modal-titulo">

                                Editar Tutor

                            </h5>

                            <button type="button"
                                    class="btn-close"
                                    data-bs-dismiss="modal"
                                    aria-label="Fechar">

                            </button>

                        </div>

                        <div class="modal-body">

                            <!-- ID do tutor -->
                            <input type="hidden"
                                   name="id"
                                   value="<?php echo (int) $tutor["id"]; ?>">

                            <!-- Aviso -->
                            <div class="tutores-aviso">

                                Somente o telefone, o e-mail e o endereço
                                podem ser alterados.

                            </div>

                            <!-- Dados pessoais -->
                            <h5 class="tutores-modal-secao">

                                Dados pessoais

                            </h5>

                            <div class="row">

                                <!-- Nome bloqueado -->
                                <div class="col-md-8 mb-3">

                                    <label>Nome completo</label>

                                    <input type="text"
                                           class="form-control"
                                           value="<?php echo textoTutor($tutor["nome"]); ?>"
                                           disabled>

                                </div>

                                <!-- CPF bloqueado -->
                                <div class="col-md-4 mb-3">

                                    <label>CPF</label>

                                    <input type="text"
                                           class="form-control"
                                           value="<?php echo textoTutor(formatarCpfTutor($tutor["cpf"])); ?>"
                                           disabled>

                                </div>

                                <!-- E-mail editável -->
                                <div class="col-md-6 mb-3">

                                    <label>E-mail</label>

                                    <input type="email"
                                           class="form-control"
                                           name="email"
                                           maxlength="150"
                                           placeholder="exemplo@email.com"
                                           value="<?php echo textoTutor($tutor["email"]); ?>">

                                </div>

                                <!-- Telefone editável -->
                                <div class="col-md-6 mb-3">

                                    <label>Telefone *</label>

                                    <input type="tel"
                                           class="form-control campo-telefone"
                                           name="telefone"
                                           maxlength="15"
                                           placeholder="(00) 00000-0000"
                                           value="<?php echo textoTutor($tutor["telefone"]); ?>"
                                           required>

                                </div>

                            </div>

                            <!-- Endereço editável -->
                            <h5 class="tutores-modal-secao">

                                Endereço

                            </h5>

                            <div class="row">

                                <!-- CEP -->
                                <div class="col-md-3 mb-3">

                                    <label>CEP</label>

                                    <input type="text"
                                           class="form-control campo-cep"
                                           name="cep"
                                           maxlength="9"
                                           placeholder="00000-000"
                                           value="<?php echo textoTutor($tutor["cep"]); ?>">

                                    <small class="status-cep"></small>

                                </div>

                                <!-- Rua -->
                                <div class="col-md-7 mb-3">

                                    <label>Rua / Avenida</label>

                                    <input type="text"
                                           class="form-control"
                                           name="logradouro"
                                           maxlength="150"
                                           value="<?php echo textoTutor($tutor["logradouro"]); ?>">

                                </div>

                                <!-- Número -->
                                <div class="col-md-2 mb-3">

                                    <label>Número</label>

                                    <input type="text"
                                           class="form-control"
                                           name="numero"
                                           maxlength="20"
                                           value="<?php echo textoTutor($tutor["numero"]); ?>">

                                </div>

                                <!-- Bairro -->
                                <div class="col-md-4 mb-3">

                                    <label>Bairro</label>

                                    <input type="text"
                                           class="form-control"
                                           name="bairro"
                                           maxlength="100"
                                           value="<?php echo textoTutor($tutor["bairro"]); ?>">

                                </div>

                                <!-- Cidade -->
                                <div class="col-md-5 mb-3">

                                    <label>Cidade</label>

                                    <input type="text"
                                           class="form-control"
                                           name="cidade"
                                           maxlength="100"
                                           value="<?php echo textoTutor($tutor["cidade"]); ?>">

                                </div>

                                <!-- Estado -->
                                <div class="col-md-3 mb-3">

                                    <label>Estado</label>

                                    <select class="form-select"
                                            name="estado">

                                        <option value="">

                                            Selecione

                                        </option>

                                        <?php foreach ($estados as $sigla => $nomeEstado): ?>

                                            <option value="<?php echo $sigla; ?>"
                                                <?php echo $tutor["estado"] == $sigla ? "selected" : ""; ?>>

                                                <?php echo textoTutor($nomeEstado); ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                                <!-- Complemento -->
                                <div class="col-md-12 mb-3">

                                    <label>Complemento</label>

                                    <input type="text"
                                           class="form-control"
                                           name="complemento"
                                           maxlength="150"
                                           value="<?php echo textoTutor($tutor["complemento"]); ?>">

                                </div>

                            </div>

                        </div>

                        <!-- Botões da edição -->
                        <div class="modal-footer">

                            <button type="button"
                                    class="tutores-voltar"
                                    data-bs-dismiss="modal">

                                Cancelar

                            </button>

                            <button type="submit"
                                    name="editar"
                                    class="tutores-botao">

                                Salvar Alterações

                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    <?php endforeach; ?>

</div>

<script>

    // Máscara automática de telefone
    document.querySelectorAll('.campo-telefone').forEach(campo => {

        campo.addEventListener('input', function () {

            let telefone = this.value.replace(/\D/g, '');

            telefone = telefone.substring(0, 11);

            if (telefone.length > 10) {

                telefone = telefone.replace(
                    /^(\d{2})(\d{5})(\d{4})$/,
                    '($1) $2-$3'
                );

            } else {

                telefone = telefone.replace(
                    /^(\d{2})(\d{4})(\d{0,4})$/,
                    '($1) $2-$3'
                );

            }

            this.value = telefone;

        });

    });

    // Consulta automática de CEP
    document.querySelectorAll('.form-editar-tutor').forEach(formulario => {

        const campoCEP = formulario.querySelector('.campo-cep');

        const statusCEP = formulario.querySelector('.status-cep');

        let cepAnterior = campoCEP.value.replace(/\D/g, '');

        // Máscara de CEP
        campoCEP.addEventListener('input', function () {

            let cep = this.value.replace(/\D/g, '');

            cep = cep.substring(0, 8);

            if (cep.length > 5) {

                cep = cep.replace(
                    /^(\d{5})(\d{1,3})$/,
                    '$1-$2'
                );

            }

            this.value = cep;

            statusCEP.textContent = '';

        });

        // Buscar endereço pelo CEP
        campoCEP.addEventListener('blur', function () {

            let cep = this.value.replace(/\D/g, '');

            // Verificar quantidade de números
            if (cep.length !== 8) {

                statusCEP.textContent = '';

                return;

            }

            // Não consultar novamente se o CEP não mudou
            if (cep === cepAnterior) {

                return;

            }

            statusCEP.style.color = '#11796f';

            statusCEP.textContent = 'Consultando CEP...';

            fetch('https://viacep.com.br/ws/' + cep + '/json/')

                .then(response => {

                    if (!response.ok) {

                        throw new Error('Erro na consulta');

                    }

                    return response.json();

                })

                .then(dados => {

                    // Evitar preenchimento com consulta antiga
                    if (campoCEP.value.replace(/\D/g, '') !== cep) {

                        return;

                    }

                    // Verificar se o CEP existe
                    if (dados.erro) {

                        statusCEP.style.color = '#dc3545';

                        statusCEP.textContent =
                            'CEP não encontrado.';

                        return;

                    }

                    // Preencher os campos automaticamente
                    formulario.querySelector('[name="logradouro"]').value =
                        dados.logradouro || '';

                    formulario.querySelector('[name="bairro"]').value =
                        dados.bairro || '';

                    formulario.querySelector('[name="cidade"]').value =
                        dados.localidade || '';

                    formulario.querySelector('[name="estado"]').value =
                        dados.uf || '';

                    cepAnterior = cep;

                    statusCEP.style.color = '#198754';

                    statusCEP.textContent =
                        'Endereço preenchido automaticamente!';

                })

                .catch(erro => {

                    if (campoCEP.value.replace(/\D/g, '') !== cep) {

                        return;

                    }

                    statusCEP.style.color = '#dc3545';

                    statusCEP.textContent =
                        'Não foi possível consultar o CEP.';

                    console.log(erro);

                });

        });

    });

</script>

<?php include '../includes/rodape.php'; ?>