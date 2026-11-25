<?php

require_once __DIR__ . '/../includes/conexao.php';

$mensagem = "";
$tipo = "";

// Proteger os textos exibidos na página
function textoCadastroAnimal($valor) {
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Opções de cadastro
$especies = [
    "Canina",
    "Felina",
    "Ave",
    "Roedor",
    "Coelho",
    "Réptil",
    "Outra"
];

$sexos = [
    "Macho",
    "Fêmea",
    "Não identificado"
];

$opcoesCastrado = [
    "Sim",
    "Não",
    "Não informado"
];

// Valores iniciais
$tutor_id = 0;
$nome = "";
$especie = "";
$raca = "";
$sexo = "";
$data_nascimento = "";
$peso = "";
$cor = "";
$castrado = "Não informado";
$observacoes = "";

// Receber os dados do formulário
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $tutor_id = (int) ($_POST["tutor_id"] ?? 0);
    $nome = trim($_POST["nome"] ?? "");
    $especie = trim($_POST["especie"] ?? "");
    $raca = trim($_POST["raca"] ?? "");
    $sexo = trim($_POST["sexo"] ?? "");
    $data_nascimento = trim($_POST["data_nascimento"] ?? "");
    $peso = trim($_POST["peso"] ?? "");
    $cor = trim($_POST["cor"] ?? "");
    $castrado = trim($_POST["castrado"] ?? "Não informado");
    $observacoes = trim($_POST["observacoes"] ?? "");

    // Validar data de nascimento
    $dataValida = true;

    if ($data_nascimento != "") {

        $data = DateTime::createFromFormat(
            '!Y-m-d',
            $data_nascimento
        );

        $dataValida = $data !== false
            && $data->format('Y-m-d') === $data_nascimento
            && $data_nascimento <= date("Y-m-d");

    }

    // Validar os campos obrigatórios
    if ($tutor_id <= 0) {

        $mensagem = "Pesquise e selecione o tutor responsável.";
        $tipo = "danger";

    } elseif ($nome == "") {

        $mensagem = "Informe o nome do animal.";
        $tipo = "danger";

    } elseif (!in_array($especie, $especies, true)) {

        $mensagem = "Selecione uma espécie válida.";
        $tipo = "danger";

    } elseif (!in_array($sexo, $sexos, true)) {

        $mensagem = "Selecione o sexo do animal.";
        $tipo = "danger";

    } elseif (!$dataValida) {

        $mensagem = "Informe uma data de nascimento válida.";
        $tipo = "danger";

    } elseif (
        $peso != "" &&
        (!is_numeric($peso) || $peso < 0.01 || $peso > 9999.99)
    ) {

        $mensagem = "Informe um peso válido.";
        $tipo = "danger";

    } elseif (!in_array($castrado, $opcoesCastrado, true)) {

        $mensagem = "Selecione uma opção de castração válida.";
        $tipo = "danger";

    } elseif (
        mb_strlen($nome) > 100 ||
        mb_strlen($raca) > 100 ||
        mb_strlen($cor) > 100 ||
        mb_strlen($observacoes) > 2000
    ) {

        $mensagem = "Um dos campos ultrapassou o limite de caracteres.";
        $tipo = "danger";

    } else {

        try {

            // Verificar se o tutor existe no banco
            $sqlTutor = "SELECT id
                         FROM tutores
                         WHERE id = ?";

            $stmtTutor = mysqli_prepare(
                $conexao,
                $sqlTutor
            );

            mysqli_stmt_bind_param(
                $stmtTutor,
                "i",
                $tutor_id
            );

            mysqli_stmt_execute($stmtTutor);
            mysqli_stmt_store_result($stmtTutor);

            $tutorExiste = mysqli_stmt_num_rows(
                $stmtTutor
            ) > 0;

            mysqli_stmt_close($stmtTutor);

            if (!$tutorExiste) {

                $mensagem = "O tutor selecionado não existe.";
                $tipo = "danger";

            } else {

                // Campos opcionais
                $dataBanco = $data_nascimento != ""
                    ? $data_nascimento
                    : null;

                $pesoBanco = $peso != ""
                    ? (float) $peso
                    : null;

                // Salvar o animal no banco de dados
                $sql = "INSERT INTO animais (
                            tutor_id,
                            nome,
                            especie,
                            raca,
                            sexo,
                            data_nascimento,
                            peso,
                            cor,
                            castrado,
                            observacoes
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                        )";

                $stmt = mysqli_prepare(
                    $conexao,
                    $sql
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "isssssdsss",
                    $tutor_id,
                    $nome,
                    $especie,
                    $raca,
                    $sexo,
                    $dataBanco,
                    $pesoBanco,
                    $cor,
                    $castrado,
                    $observacoes
                );

                if (mysqli_stmt_execute($stmt)) {

                    mysqli_stmt_close($stmt);

                    // Evitar cadastro duplicado ao atualizar a página
                    header("Location: cadastro_animal.php?sucesso=1");
                    exit;

                } else {

                    $mensagem = "Erro ao cadastrar o animal.";
                    $tipo = "danger";

                }

                mysqli_stmt_close($stmt);

            }

        } catch (Exception $erro) {

            $mensagem = "Não foi possível cadastrar o animal.";
            $tipo = "danger";

            error_log($erro->getMessage());

        }

    }

}

// Mensagem de sucesso
if (
    $_SERVER["REQUEST_METHOD"] == "GET" &&
    isset($_GET["sucesso"]) &&
    $_GET["sucesso"] == "1"
) {

    $mensagem = "Animal cadastrado com sucesso!";
    $tipo = "success";

}

// Buscar todos os tutores cadastrados
$sqlTutores = "SELECT id, nome, cpf
               FROM tutores
               ORDER BY nome ASC";

$resultadoTutores = mysqli_query(
    $conexao,
    $sqlTutores
);

$tutores = [];

while ($tutor = mysqli_fetch_assoc($resultadoTutores)) {

    $tutores[] = $tutor;

}

include __DIR__ . '/../includes/cabecalho.php';

?>

<style>

    .animal-container {
        padding-top: 25px;
        padding-bottom: 40px;
    }

    .animal-banner {
        background: linear-gradient(135deg, #11796f, #159589);
        color: white;
        padding: 40px;
        border-radius: 16px;
        margin-bottom: 30px;
        box-shadow: 0 4px 12px rgba(17, 121, 111, 0.12);
    }

    .animal-banner h1 {
        font-size: 32px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .animal-banner p {
        font-size: 17px;
        margin-bottom: 0;
        opacity: 0.95;
    }

    .animal-form-card {
        background-color: white;
        border: 1px solid #dedede;
        border-radius: 12px;
        padding: 30px;
        margin-bottom: 25px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
    }

    .animal-subtitulo {
        color: #11796f;
        font-size: 21px;
        font-weight: 600;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eeeeee;
    }

    .animal-container label {
        font-size: 14px;
        font-weight: 500;
        color: #333;
        margin-bottom: 8px;
    }

    .animal-container .form-control,
    .animal-container .form-select {
        border: 1px solid #d6d6d6;
        border-radius: 7px;
        padding: 12px;
        font-size: 14px;
    }

    .animal-container .form-control:focus,
    .animal-container .form-select:focus {
        border-color: #11796f;
        box-shadow: 0 0 0 0.2rem rgba(17, 121, 111, 0.15);
    }

    .animal-container textarea {
        resize: vertical;
        min-height: 120px;
    }

    .animal-obrigatorio {
        color: #dc3545;
    }

    .animal-ajuda {
        font-size: 13px;
        color: #777;
        margin-top: 8px;
        display: block;
    }

    .animal-info {
        background-color: #eaf5f3;
        color: #11796f;
        border-left: 4px solid #11796f;
        border-radius: 8px;
        padding: 15px;
        margin-top: 20px;
        font-size: 14px;
    }

    .animal-botao-salvar {
        background-color: #11796f;
        color: white;
        border: none;
        padding: 13px 25px;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: 0.3s;
        text-decoration: none;
        display: inline-block;
    }

    .animal-botao-salvar:hover {
        background-color: #0d625a;
        color: white;
    }

    .animal-botao-salvar:disabled {
        background-color: #a1b9b6;
        cursor: not-allowed;
    }

    .animal-botao-voltar {
        background-color: white;
        color: #11796f;
        border: 1px solid #11796f;
        padding: 12px 25px;
        border-radius: 8px;
        font-weight: 500;
        text-decoration: none;
        display: inline-block;
        transition: 0.3s;
    }

    .animal-botao-voltar:hover {
        background-color: #eaf5f3;
        color: #11796f;
    }

    .animal-link {
        color: #11796f;
        font-weight: 600;
        text-decoration: none;
    }

    .animal-link:hover {
        text-decoration: underline;
    }

    .animal-botoes {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .animal-contador {
        text-align: right;
        color: #777;
        font-size: 12px;
        margin-top: 5px;
    }

    /* Pesquisa de tutores */

    .pesquisa-tutor-container {
        position: relative;
    }

    .pesquisa-tutor-container input {
        padding-left: 42px !important;
    }

    .pesquisa-tutor-icone {
        position: absolute;
        top: 50%;
        left: 15px;
        transform: translateY(-50%);
        color: #11796f;
        pointer-events: none;
    }

    #resultadosTutores {
        max-height: 280px;
        overflow-y: auto;
        margin-top: 10px;
    }

    .tutor-resultado {
        display: block;
        width: 100%;
        text-align: left;
        background-color: white;
        border: 1px solid #dedede;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 8px;
        cursor: pointer;
        transition: 0.2s;
    }

    .tutor-resultado:hover {
        background-color: #eaf5f3;
        border-color: #11796f;
    }

    .tutor-resultado strong {
        color: #11796f;
        font-size: 15px;
        display: block;
        margin-bottom: 5px;
    }

    .tutor-resultado small {
        color: #666;
        font-size: 13px;
    }

    .tutor-selecionado {
        background-color: #eaf5f3;
        border: 1px solid #11796f;
        border-radius: 10px;
        padding: 20px;
        margin-top: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .tutor-selecionado strong {
        color: #11796f;
    }

    #nomeTutorSelecionado {
        font-size: 18px;
        font-weight: 600;
        margin-top: 5px;
        color: #333;
    }

    #cpfTutorSelecionado {
        font-size: 14px;
        color: #666;
        margin-top: 5px;
    }

    #codigoTutorSelecionado {
        font-size: 13px;
        color: #666;
        margin-top: 5px;
    }

    .tutor-nao-encontrado {
        padding: 15px;
        background-color: #f8f9fa;
        border: 1px solid #dedede;
        border-radius: 8px;
        color: #666;
        text-align: center;
    }

    .tutor-quantidade {
        font-size: 13px;
        color: #777;
        margin-bottom: 10px;
    }

    @media (max-width: 768px) {

        .animal-banner {
            padding: 25px;
        }

        .animal-banner h1 {
            font-size: 26px;
        }

        .animal-form-card {
            padding: 20px;
        }

        .animal-botoes {
            flex-direction: column;
            align-items: stretch;
        }

        .animal-botoes a,
        .animal-botoes button {
            text-align: center;
        }

        .tutor-selecionado {
            flex-direction: column;
            align-items: stretch;
        }

    }

</style>

<div class="container animal-container">

    <!-- Banner -->
    <div class="animal-banner">

        <h1>Cadastro de Animal</h1>

        <p>
            Preencha os dados do animal e vincule-o
            ao seu tutor responsável.
        </p>

    </div>

    <!-- Mensagem de sucesso ou erro -->
    <?php if ($mensagem != ""): ?>

        <div class="alert alert-<?php echo $tipo; ?>"
             role="alert">

            <?php echo textoCadastroAnimal($mensagem); ?>

            <?php if ($tipo == "success"): ?>

                <br>

                <a href="animais.php"
                   class="animal-link">

                    Visualizar animais cadastrados

                </a>

            <?php endif; ?>

        </div>

    <?php endif; ?>

    <!-- Aviso caso não existam tutores -->
    <?php if (count($tutores) == 0): ?>

        <div class="alert alert-warning">

            Nenhum tutor foi cadastrado ainda.

            Para cadastrar um animal, é necessário
            cadastrar seu tutor primeiro.

            <br><br>

            <a href="cadastro_tutor.php"
               class="animal-botao-salvar">

                Cadastrar Tutor

            </a>

        </div>

    <?php endif; ?>

    <!-- Formulário -->
    <form method="POST"
          action="cadastro_animal.php"
          id="formCadastroAnimal">

        <!-- Tutor responsável -->
        <div class="animal-form-card">

            <h2 class="animal-subtitulo">

                Tutor responsável

            </h2>

            <div class="mb-3">

                <label for="pesquisaTutor">

                    Pesquisar tutor por nome ou CPF

                    <span class="animal-obrigatorio">
                        *
                    </span>

                </label>

                <div class="pesquisa-tutor-container">

                    <span class="pesquisa-tutor-icone">

                        <svg xmlns="http://www.w3.org/2000/svg"
                             width="18"
                             height="18"
                             viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round">

                            <circle cx="11" cy="11" r="8"></circle>

                            <line x1="21"
                                  y1="21"
                                  x2="16.65"
                                  y2="16.65"></line>

                        </svg>

                    </span>

                    <input type="text"
                           class="form-control"
                           id="pesquisaTutor"
                           placeholder="Digite o nome ou CPF do tutor..."
                           maxlength="150"
                           autocomplete="off"
                           <?php echo count($tutores) == 0 ? "disabled" : ""; ?>>

                </div>

                <small class="animal-ajuda">

                    Digite o nome ou CPF para localizar
                    o tutor responsável pelo animal.

                </small>

            </div>

            <!-- ID do tutor que será salvo no banco -->
            <input type="hidden"
                   name="tutor_id"
                   id="tutor_id"
                   value="<?php echo (int) $tutor_id; ?>">

            <!-- Quantidade de tutores encontrados -->
            <div id="quantidadeTutores"
                 class="tutor-quantidade"
                 aria-live="polite">
            </div>

            <!-- Resultados da pesquisa -->
            <div id="resultadosTutores"></div>

            <!-- Informações do tutor selecionado -->
            <div id="tutorSelecionado"
                 class="tutor-selecionado"
                 style="display: none;">

                <div>

                    <strong>

                        ✓ Tutor selecionado com sucesso

                    </strong>

                    <div id="nomeTutorSelecionado"></div>

                    <div id="cpfTutorSelecionado"></div>

                    <div id="codigoTutorSelecionado"></div>

                </div>

                <button type="button"
                        class="animal-botao-voltar"
                        id="alterarTutor">

                    Alterar tutor

                </button>

            </div>

            <small class="animal-ajuda mt-3">

                Não encontrou o tutor?

                <a href="cadastro_tutor.php"
                   class="animal-link">

                    Cadastrar novo tutor

                </a>

            </small>

            <div class="animal-info">

                Um tutor pode possuir vários animais.
                Caso ele já esteja cadastrado, basta
                pesquisá-lo e selecioná-lo novamente.

            </div>

        </div>

        <!-- Dados do animal -->
        <div class="animal-form-card">

            <h2 class="animal-subtitulo">

                Dados do animal

            </h2>

            <div class="row">

                <!-- Nome -->
                <div class="col-md-12 mb-4">

                    <label for="nome">

                        Nome do animal

                        <span class="animal-obrigatorio">
                            *
                        </span>

                    </label>

                    <input type="text"
                           class="form-control"
                           id="nome"
                           name="nome"
                           maxlength="100"
                           placeholder="Ex.: Rex"
                           value="<?php echo textoCadastroAnimal($nome); ?>"
                           required>

                </div>

                <!-- Espécie -->
                <div class="col-md-6 mb-4">

                    <label for="especie">

                        Espécie

                        <span class="animal-obrigatorio">
                            *
                        </span>

                    </label>

                    <select class="form-select"
                            id="especie"
                            name="especie"
                            required>

                        <option value="">

                            Selecione a espécie

                        </option>

                        <?php foreach ($especies as $opcao): ?>

                            <option
                                value="<?php echo textoCadastroAnimal($opcao); ?>"
                                <?php echo $especie == $opcao ? "selected" : ""; ?>>

                                <?php echo textoCadastroAnimal($opcao); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- Raça -->
                <div class="col-md-6 mb-4">

                    <label for="raca">

                        Raça

                    </label>

                    <input type="text"
                           class="form-control"
                           id="raca"
                           name="raca"
                           maxlength="100"
                           placeholder="Ex.: Labrador ou SRD"
                           value="<?php echo textoCadastroAnimal($raca); ?>">

                    <small class="animal-ajuda">

                        SRD significa sem raça definida.

                    </small>

                </div>

                <!-- Sexo -->
                <div class="col-md-6 mb-4">

                    <label for="sexo">

                        Sexo

                        <span class="animal-obrigatorio">
                            *
                        </span>

                    </label>

                    <select class="form-select"
                            id="sexo"
                            name="sexo"
                            required>

                        <option value="">

                            Selecione

                        </option>

                        <?php foreach ($sexos as $opcao): ?>

                            <option
                                value="<?php echo textoCadastroAnimal($opcao); ?>"
                                <?php echo $sexo == $opcao ? "selected" : ""; ?>>

                                <?php echo textoCadastroAnimal($opcao); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- Data de nascimento -->
                <div class="col-md-6 mb-4">

                    <label for="data_nascimento">

                        Data de nascimento

                    </label>

                    <input type="date"
                           class="form-control"
                           id="data_nascimento"
                           name="data_nascimento"
                           max="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo textoCadastroAnimal($data_nascimento); ?>">

                    <small class="animal-ajuda"
                           id="idadeAnimal">

                        Caso não saiba, deixe em branco.

                    </small>

                </div>

                <!-- Peso -->
                <div class="col-md-6 mb-4">

                    <label for="peso">

                        Peso (kg)

                    </label>

                    <input type="number"
                           class="form-control"
                           id="peso"
                           name="peso"
                           min="0.01"
                           max="9999.99"
                           step="0.01"
                           placeholder="Ex.: 12.50"
                           value="<?php echo textoCadastroAnimal($peso); ?>">

                </div>

                <!-- Cor -->
                <div class="col-md-6 mb-4">

                    <label for="cor">

                        Cor / Pelagem

                    </label>

                    <input type="text"
                           class="form-control"
                           id="cor"
                           name="cor"
                           maxlength="100"
                           placeholder="Ex.: Caramelo"
                           value="<?php echo textoCadastroAnimal($cor); ?>">

                </div>

                <!-- Castração -->
                <div class="col-md-12 mb-3">

                    <label>

                        O animal é castrado?

                    </label>

                    <div class="d-flex flex-wrap gap-4">

                        <?php foreach ($opcoesCastrado as $indice => $opcao): ?>

                            <div class="form-check">

                                <input class="form-check-input"
                                       type="radio"
                                       name="castrado"
                                       id="castrado<?php echo $indice; ?>"
                                       value="<?php echo textoCadastroAnimal($opcao); ?>"
                                       <?php echo $castrado == $opcao ? "checked" : ""; ?>>

                                <label class="form-check-label"
                                       for="castrado<?php echo $indice; ?>">

                                    <?php echo textoCadastroAnimal($opcao); ?>

                                </label>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>

        </div>

        <!-- Informações adicionais -->
        <div class="animal-form-card">

            <h2 class="animal-subtitulo">

                Informações adicionais

            </h2>

            <div class="row">

                <div class="col-md-12">

                    <label for="observacoes">

                        Observações

                    </label>

                    <textarea class="form-control"
                              id="observacoes"
                              name="observacoes"
                              maxlength="2000"
                              rows="5"
                              placeholder="Informe características, comportamento, necessidades especiais ou outras informações importantes sobre o animal."><?php echo textoCadastroAnimal($observacoes); ?></textarea>

                    <div class="animal-contador">

                        <span id="contadorObservacoes">0</span>
                        / 2000 caracteres

                    </div>

                </div>

            </div>

        </div>

        <!-- Campos obrigatórios -->
        <p class="animal-ajuda mb-4">

            <span class="animal-obrigatorio">*</span>

            Campos de preenchimento obrigatório.

        </p>

        <!-- Botões -->
        <div class="animal-botoes">

            <a href="index.php"
               class="animal-botao-voltar">

                ← Voltar

            </a>

            <div class="d-flex flex-wrap gap-2">

                <a href="animais.php"
                   class="animal-botao-voltar">

                    Animais Cadastrados

                </a>

                <button type="submit"
                        class="animal-botao-salvar"
                        id="botaoCadastrarAnimal"
                        <?php echo count($tutores) == 0 ? "disabled" : ""; ?>>

                    Cadastrar Animal

                </button>

            </div>

        </div>

    </form>

</div>

<script>

    // ========================================
    // PESQUISA DE TUTORES
    // ========================================

    // Receber os tutores cadastrados no banco
    const listaTutores = <?php echo json_encode(
        $tutores,
        JSON_UNESCAPED_UNICODE |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_INVALID_UTF8_SUBSTITUTE
    ); ?>;

    const pesquisaTutor =
        document.getElementById('pesquisaTutor');

    const resultadosTutores =
        document.getElementById('resultadosTutores');

    const quantidadeTutores =
        document.getElementById('quantidadeTutores');

    const campoTutorId =
        document.getElementById('tutor_id');

    const tutorSelecionado =
        document.getElementById('tutorSelecionado');

    const alterarTutor =
        document.getElementById('alterarTutor');

    // Remover acentos e letras maiúsculas
    function normalizarTexto(texto) {

        return String(texto)
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();

    }

    // Formatar CPF
    function formatarCpfTutor(cpf) {

        cpf = String(cpf).replace(/\D/g, '');

        if (cpf.length === 11) {

            return cpf.replace(
                /(\d{3})(\d{3})(\d{3})(\d{2})/,
                '$1.$2.$3-$4'
            );

        }

        return cpf;

    }

    // Pesquisar tutor automaticamente
    function pesquisarTutores() {

        const pesquisa = pesquisaTutor.value.trim();

        const pesquisaNome =
            normalizarTexto(pesquisa);

        const pesquisaCpf =
            pesquisa.replace(/\D/g, '');

        // Limpar resultados anteriores
        resultadosTutores.innerHTML = '';

        quantidadeTutores.textContent = '';

        campoTutorId.value = '';

        tutorSelecionado.style.display = 'none';

        // Não pesquisar se o campo estiver vazio
        if (pesquisa === '') {

            return;

        }

        // Evitar muitos resultados com apenas um caractere
        if (pesquisa.length < 2) {

            quantidadeTutores.textContent =
                'Digite pelo menos 2 caracteres para pesquisar.';

            return;

        }

        // Buscar tutores pelo nome ou CPF
        const encontrados = listaTutores.filter(function (tutor) {

            const nome = normalizarTexto(tutor.nome);

            const cpf = String(tutor.cpf)
                .replace(/\D/g, '');

            const encontrouNome =
                nome.includes(pesquisaNome);

            const encontrouCpf =
                /^[\d.\-\s]+$/.test(pesquisa) &&
                pesquisaCpf.length > 0 &&
                cpf.includes(pesquisaCpf);

            return encontrouNome || encontrouCpf;

        });

        // Exibir quantidade encontrada
        quantidadeTutores.textContent =
            encontrados.length + ' tutor(es) encontrado(s).';

        // Caso não encontre nenhum tutor
        if (encontrados.length === 0) {

            const aviso = document.createElement('div');

            aviso.className = 'tutor-nao-encontrado';

            aviso.textContent =
                'Nenhum tutor encontrado. Verifique o nome ou CPF informado.';

            resultadosTutores.appendChild(aviso);

            return;

        }

        // Limitar a quantidade de resultados exibidos
        const limiteResultados = 20;

        const tutoresExibidos =
            encontrados.slice(0, limiteResultados);

        if (encontrados.length > limiteResultados) {

            quantidadeTutores.textContent =
                encontrados.length +
                ' tutores encontrados. Exibindo os primeiros ' +
                limiteResultados +
                '. Continue digitando para refinar a pesquisa.';

        }

        // Criar os resultados da pesquisa
        tutoresExibidos.forEach(function (tutor) {

            const botao = document.createElement('button');

            botao.type = 'button';

            botao.className = 'tutor-resultado';

            const nome = document.createElement('strong');

            nome.textContent = tutor.nome;

            const cpf = document.createElement('small');

            cpf.textContent =
                'CPF: ' + formatarCpfTutor(tutor.cpf);

            botao.appendChild(nome);

            botao.appendChild(cpf);

            botao.addEventListener('click', function () {

                selecionarTutor(tutor);

            });

            resultadosTutores.appendChild(botao);

        });

    }

    // Executar a pesquisa conforme o usuário digita
    pesquisaTutor.addEventListener(
        'input',
        pesquisarTutores
    );

    // Selecionar o tutor encontrado
    function selecionarTutor(tutor) {

        // Salvar o ID do tutor no formulário
        campoTutorId.value = tutor.id;

        // Exibir o nome
        document.getElementById(
            'nomeTutorSelecionado'
        ).textContent = tutor.nome;

        // Exibir o CPF
        document.getElementById(
            'cpfTutorSelecionado'
        ).textContent =
            'CPF: ' + formatarCpfTutor(tutor.cpf);

        // Exibir o código de identificação
        document.getElementById(
            'codigoTutorSelecionado'
        ).textContent =
            'Código do tutor: #' + tutor.id;

        // Mostrar o tutor selecionado
        tutorSelecionado.style.display = 'flex';

        // Limpar os resultados
        resultadosTutores.innerHTML = '';

        quantidadeTutores.textContent = '';

        // Bloquear a pesquisa até alterar o tutor
        pesquisaTutor.value = tutor.nome;

        pesquisaTutor.disabled = true;

    }

    // Alterar o tutor selecionado
    alterarTutor.addEventListener('click', function () {

        campoTutorId.value = '';

        tutorSelecionado.style.display = 'none';

        resultadosTutores.innerHTML = '';

        quantidadeTutores.textContent = '';

        pesquisaTutor.disabled = false;

        pesquisaTutor.value = '';

        pesquisaTutor.focus();

    });

    // Permitir selecionar com Enter quando houver
    // somente um resultado correspondente
    pesquisaTutor.addEventListener('keydown', function (event) {

        if (event.key === 'Enter') {

            event.preventDefault();

            const resultados =
                resultadosTutores.querySelectorAll('.tutor-resultado');

            if (resultados.length === 1) {

                resultados[0].click();

            }

        }

    });

    // Restaurar o tutor caso ocorra um erro no cadastro
    const tutorAnterior = listaTutores.find(function (tutor) {

        return String(tutor.id) === campoTutorId.value;

    });

    if (tutorAnterior) {

        selecionarTutor(tutorAnterior);

    } else {

        campoTutorId.value = '';

    }

    // ========================================
    // CALCULAR IDADE AUTOMATICAMENTE
    // ========================================

    const campoNascimento =
        document.getElementById('data_nascimento');

    const campoIdade =
        document.getElementById('idadeAnimal');

    function calcularIdade() {

        if (!campoNascimento.value) {

            campoIdade.style.color = '#777';

            campoIdade.textContent =
                'Caso não saiba, deixe em branco.';

            return;

        }

        const partes = campoNascimento.value.split('-');

        const nascimento = new Date(
            Number(partes[0]),
            Number(partes[1]) - 1,
            Number(partes[2])
        );

        const hoje = new Date();

        if (nascimento > hoje) {

            campoIdade.textContent =
                'A data não pode ser futura.';

            campoIdade.style.color = '#dc3545';

            return;

        }

        let anos =
            hoje.getFullYear() - nascimento.getFullYear();

        let meses =
            hoje.getMonth() - nascimento.getMonth();

        if (hoje.getDate() < nascimento.getDate()) {

            meses--;

        }

        if (meses < 0) {

            anos--;

            meses += 12;

        }

        let idade = '';

        if (anos > 0) {

            idade += anos + (anos === 1 ? ' ano' : ' anos');

        }

        if (meses > 0) {

            if (idade !== '') {

                idade += ' e ';

            }

            idade += meses + (meses === 1 ? ' mês' : ' meses');

        }

        if (idade === '') {

            idade = 'Menos de 1 mês';

        }

        campoIdade.style.color = '#11796f';

        campoIdade.textContent =
            'Idade aproximada: ' + idade;

    }

    campoNascimento.addEventListener(
        'change',
        calcularIdade
    );

    calcularIdade();

    // ========================================
    // CONTADOR DE CARACTERES
    // ========================================

    const observacoes =
        document.getElementById('observacoes');

    const contador =
        document.getElementById('contadorObservacoes');

    function atualizarContador() {

        contador.textContent =
            observacoes.value.length;

    }

    observacoes.addEventListener(
        'input',
        atualizarContador
    );

    atualizarContador();

    // ========================================
    // VALIDAÇÃO DO FORMULÁRIO
    // ========================================

    const formulario =
        document.getElementById('formCadastroAnimal');

    const botaoCadastrar =
        document.getElementById('botaoCadastrarAnimal');

    formulario.addEventListener('submit', function (event) {

        // Impedir cadastro sem tutor selecionado
        if (
            !campoTutorId.value ||
            Number(campoTutorId.value) <= 0
        ) {

            event.preventDefault();

            alert(
                'Pesquise e selecione o tutor responsável antes de cadastrar o animal.'
            );

            pesquisaTutor.focus();

            return;

        }

        // Evitar cliques repetidos
        botaoCadastrar.disabled = true;

        botaoCadastrar.textContent = 'Cadastrando...';

    });

</script>

<?php include __DIR__ . '/../includes/rodape.php'; ?>