<?php include '../includes/cabecalho.php'; ?>
<?php include '../includes/conexao.php'; ?>

<div class="boas-vindas">
    <h1>Vacinas</h1>
    <p class="lead mb-0">Controle de vacinação</p>
</div>

<h2 class="h5 mb-3">Opções do módulo</h2>

<div class="row g-3">

    <div class="col-6 col-lg-3">
        <a href="lote/index.php" class="card card-modulo h-100 text-decoration-none">
            <div class="card-body">
                <i class="bi bi-upc-scan"></i>
                <h3 class="h6 mt-2 text-dark">Lote Vacinas</h3>
                <p class="small text-secondary mb-0">Cadastro dos lotes recebidos</p>
            </div>
        </a>
    </div>

    <div class="col-6 col-lg-3">
        <a href="aplicacao/index.php" class="card card-modulo h-100 text-decoration-none">
            <div class="card-body">
                <i class="bi bi-syringe"></i>
                <h3 class="h6 mt-2 text-dark">Aplicação Vacinas</h3>
                <p class="small text-secondary mb-0">Registro das doses aplicadas</p>
            </div>
        </a>
    </div>

</div>

<?php include '../includes/rodape.php'; ?>
