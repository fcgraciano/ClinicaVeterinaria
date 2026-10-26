<?php include '../includes/cabecalho.php'; ?>

<div class="boas-vindas">
    <h1>Bem-vindo à Patas & Cia</h1>
    <p class="lead mb-0">Sistema da Clínica Veterinária</p>
</div>

<h2 class="h5 mb-3">Módulos do sistema</h2>

<div class="row g-3">

    <div class="col-6 col-lg-3">
        <a href="../cadastro/index.php" class="card card-modulo h-100 text-decoration-none">
            <div class="card-body">
                <i class="bi bi-person-vcard"></i>
                <h3 class="h6 mt-2 text-dark">Cadastro</h3>
                <p class="small text-secondary mb-0">Tutores e animais</p>
            </div>
        </a>
    </div>

    <div class="col-6 col-lg-3">
        <a href="../agendamento/index.php" class="card card-modulo h-100 text-decoration-none">
            <div class="card-body">
                <i class="bi bi-calendar-check"></i>
                <h3 class="h6 mt-2 text-dark">Agendamento</h3>
                <p class="small text-secondary mb-0">Agenda de consultas</p>
            </div>
        </a>
    </div>

    <div class="col-6 col-lg-3">
        <a href="../prontuario/index.php" class="card card-modulo h-100 text-decoration-none">
            <div class="card-body">
                <i class="bi bi-journal-medical"></i>
                <h3 class="h6 mt-2 text-dark">Prontuário</h3>
                <p class="small text-secondary mb-0">Histórico de cada animal</p>
            </div>
        </a>
    </div>

    <div class="col-6 col-lg-3">
        <a href="../vacinas/index.php" class="card card-modulo h-100 text-decoration-none">
            <div class="card-body">
                <i class="bi bi-shield-plus"></i>
                <h3 class="h6 mt-2 text-dark">Vacinas</h3>
                <p class="small text-secondary mb-0">Controle de vacinação</p>
            </div>
        </a>
    </div>

    <div class="col-6 col-lg-3">
        <a href="../estoque/index.php" class="card card-modulo h-100 text-decoration-none">
            <div class="card-body">
                <i class="bi bi-box-seam"></i>
                <h3 class="h6 mt-2 text-dark">Estoque</h3>
                <p class="small text-secondary mb-0">Medicamentos e produtos</p>
            </div>
        </a>
    </div>

    <div class="col-6 col-lg-3">
        <a href="../financeiro/index.php" class="card card-modulo h-100 text-decoration-none">
            <div class="card-body">
                <i class="bi bi-cash-coin"></i>
                <h3 class="h6 mt-2 text-dark">Financeiro</h3>
                <p class="small text-secondary mb-0">Pagamentos e caixa</p>
            </div>
        </a>
    </div>

    <div class="col-6 col-lg-3">
        <a href="../relatorios/index.php" class="card card-modulo h-100 text-decoration-none">
            <div class="card-body">
                <i class="bi bi-bar-chart-line"></i>
                <h3 class="h6 mt-2 text-dark">Relatórios</h3>
                <p class="small text-secondary mb-0">Relatórios da clínica</p>
            </div>
        </a>
    </div>

    <div class="col-6 col-lg-3">
        <a href="../seguranca/index.php" class="card card-modulo h-100 text-decoration-none">
            <div class="card-body">
                <i class="bi bi-shield-lock"></i>
                <h3 class="h6 mt-2 text-dark">Segurança</h3>
                <p class="small text-secondary mb-0">Login e usuários</p>
            </div>
        </a>
    </div>

</div>

<?php include '../includes/rodape.php'; ?>
