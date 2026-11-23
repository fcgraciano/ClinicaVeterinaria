
<?php include '../includes/cabecalho.php'; ?>

<style>
    .cadastro-container {
        padding-top: 25px;
        padding-bottom: 40px;
    }

    .cadastro-banner {
        background: linear-gradient(135deg, #11796f, #159589);
        color: white;
        padding: 40px;
        border-radius: 16px;
        margin-bottom: 35px;
        box-shadow: 0 4px 12px rgba(17, 121, 111, 0.12);
    }

    .cadastro-banner h1 {
        font-size: 32px;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .cadastro-banner p {
        font-size: 17px;
        margin-bottom: 0;
        opacity: 0.95;
    }

    .cadastro-subtitulo {
        font-size: 23px;
        font-weight: 600;
        margin-bottom: 8px;
        color: #252525;
    }

    .cadastro-descricao {
        color: #6c757d;
        margin-bottom: 25px;
    }

    .cadastro-card {
        background: #ffffff;
        border: 1px solid #dedede;
        border-radius: 13px;
        padding: 30px;
        height: 100%;
        transition: all 0.3s ease;
    }

    .cadastro-card:hover {
        transform: translateY(-5px);
        border-color: #11796f;
        box-shadow: 0 8px 22px rgba(0, 0, 0, 0.08);
    }

    .cadastro-icone {
        width: 65px;
        height: 65px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #e5f4f1;
        border-radius: 12px;
        font-size: 32px;
        margin-bottom: 22px;
    }

    .cadastro-card h3 {
        font-size: 21px;
        font-weight: 600;
        color: #11796f;
        margin-bottom: 12px;
    }

    .cadastro-card p {
        color: #6c757d;
        font-size: 15px;
        line-height: 1.6;
        min-height: 48px;
    }

    .cadastro-botao {
        display: inline-block;
        background-color: #11796f;
        color: white !important;
        text-decoration: none !important;
        padding: 12px 22px;
        border-radius: 7px;
        font-weight: 500;
        transition: 0.3s;
        margin-top: 12px;
    }

    .cadastro-botao:hover {
        background-color: #0d625a;
        color: white;
    }

    .cadastro-botao span {
        margin-left: 8px;
    }

    .cadastro-info {
        background-color: #eaf5f3;
        border-left: 4px solid #11796f;
        padding: 16px 20px;
        border-radius: 7px;
        margin-top: 25px;
        color: #35645e;
    }

    @media (max-width: 768px) {
        .cadastro-banner {
            padding: 25px;
        }

        .cadastro-banner h1 {
            font-size: 26px;
        }

        .cadastro-card {
            padding: 23px;
        }
    }
</style>

<div class="container cadastro-container">

    <!-- Banner -->
    <div class="cadastro-banner">
        <h1>Cadastro</h1>

        <p>
            Bem-vindo ao módulo de cadastro da Patas & Cia.
            Escolha abaixo o que deseja cadastrar.
        </p>
    </div>

    <!-- Título -->
    <div class="mb-4">
        <h2 class="cadastro-subtitulo">O que deseja cadastrar?</h2>

        <p class="cadastro-descricao">
            Selecione uma das opções para continuar.
        </p>
    </div>

    <!-- Cards -->
    <div class="row">

        <!-- Card Tutor -->
        <div class="col-md-6 mb-4">

            <div class="cadastro-card">

                <div class="cadastro-icone">
                    👤
                </div>

                <h3>Cadastro de Tutores</h3>

                <p>
                    Registre os responsáveis pelos animais,
                    incluindo nome, documentos, telefone,
                    endereço e outras informações importantes.
                </p>

                <a href="cadastro_tutor.php" class="cadastro-botao">
                    Cadastrar Tutor <span>→</span>
                </a>

            </div>

        </div>

        <!-- Card Animal -->
        <div class="col-md-6 mb-4">

            <div class="cadastro-card">

                <div class="cadastro-icone">
                    🐾
                </div>

                <h3>Cadastro de Animais</h3>

                <p>
                    Registre os animais atendidos pela clínica,
                    com informações como nome, espécie, raça,
                    idade, sexo e tutor responsável.
                </p>

                <a href="cadastro_animal.php" class="cadastro-botao">
                    Cadastrar Animal <span>→</span>
                </a>

            </div>

        </div>

    </div>

    <!-- Aviso informativo -->
    <div class="cadastro-info">
        <strong>Informação:</strong>
        Para cadastrar um animal, é importante que o tutor
        responsável já esteja cadastrado no sistema.
    </div>

</div>

<?php include '../includes/rodape.php'; ?>
