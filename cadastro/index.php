<?php require_once __DIR__ . '/../includes/autenticacao.php'; exigir_role(array('recepcao')); include '../includes/cabecalho.php'; ?>


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


>>>>>>> origin/Vacinas

    .cadastro-banner h1 {
        font-size: 32px;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .cadastro-banner p {
        font-size: 17px;
        margin-bottom: 0;
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

    .cadastro-divisor {
        margin-top: 15px;
        margin-bottom: 30px;
        border-top: 1px solid #dedede;
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
            Escolha abaixo o que deseja fazer.
        </p>

    </div>

    <!-- Seção de cadastro -->
    <div class="mb-4">

        <h2 class="cadastro-subtitulo">
            Novos cadastros
        </h2>

        <p class="cadastro-descricao">
            Cadastre novos tutores e animais na clínica.
        </p>

    </div>

    <div class="row">

        <!-- Cadastrar Tutor -->
        <div class="col-md-6 mb-4">

            <div class="cadastro-card">

                <div class="cadastro-icone">
                    👤
                </div>

                <h3>Cadastrar Tutor</h3>

                <p>
                    Registre os responsáveis pelos animais,
                    incluindo nome, CPF, telefone, endereço
                    e outras informações importantes.
                </p>

                <a href="cadastro_tutor.php" class="cadastro-botao">
                    Novo Tutor <span>→</span>
                </a>

            </div>

        </div>

        <!-- Cadastrar Animal -->
        <div class="col-md-6 mb-4">

            <div class="cadastro-card">

                <div class="cadastro-icone">
                    🐾
                </div>

                <h3>Cadastrar Animal</h3>

                <p>
                    Registre os animais atendidos pela clínica,
                    com nome, espécie, raça, idade, sexo
                    e tutor responsável.
                </p>

                <a href="cadastro_animal.php" class="cadastro-botao">
                    Novo Animal <span>→</span>
                </a>

            </div>

        </div>

    </div>

    <hr class="cadastro-divisor">

    <!-- Seção de gerenciamento -->
    <div class="mb-4">

        <h2 class="cadastro-subtitulo">
            Gerenciar cadastros
        </h2>

        <p class="cadastro-descricao">
            Consulte os registros existentes e gerencie suas informações.
        </p>

    </div>

    <div class="row">

        <!-- Gerenciar Tutores -->
        <div class="col-md-6 mb-4">

            <div class="cadastro-card">

                <div class="cadastro-icone">
                    📋
                </div>

                <h3>Tutores Cadastrados</h3>

                <p>
                    Visualize os tutores cadastrados, consulte seus dados,
                    edite informações ou exclua registros.
                </p>

                <a href="tutores.php" class="cadastro-botao">
                    Ver Tutores <span>→</span>
                </a>

            </div>

        </div>

        <!-- Gerenciar Animais -->
        <div class="col-md-6 mb-4">

            <div class="cadastro-card">

                <div class="cadastro-icone">
                    🐶
                </div>

                <h3>Animais Cadastrados</h3>

                <p>
                    Visualize os animais cadastrados, confira seus dados,
                    edite informações ou exclua registros.
                </p>

                <a href="animais.php" class="cadastro-botao">
                    Ver Animais <span>→</span>
                </a>

            </div>

        </div>

    </div>

    <!-- Informação -->
    <div class="cadastro-info">

        <strong>Informação:</strong>

        Para cadastrar um animal, é importante que o tutor
        responsável já esteja cadastrado no sistema.

    </div>

</div>

<div class="boas-vindas">
    <h1>Cadastro</h1>
    <p class="lead mb-0">Tutores e animais</p>
</div>

<h2 class="h5 mb-3">Opções do módulo</h2>

<div class="row g-3">

    <div class="col-6 col-lg-3">
        <a href="animais/index.php" class="card card-modulo h-100 text-decoration-none">
            <div class="card-body">
                <i class="bi bi-heart-pulse"></i>
                <h3 class="h6 mt-2 text-dark">Animais</h3>
                <p class="small text-secondary mb-0">Cadastro dos animais</p>
            </div>
        </a>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card card-modulo h-100 opacity-75">
            <div class="card-body">
                <i class="bi bi-people"></i>
                <h3 class="h6 mt-2 text-dark">Tutores</h3>
                <p class="small text-secondary mb-0">Em construção</p>
            </div>
        </div>
    </div>

</div>

<?php include '../includes/rodape.php'; ?>