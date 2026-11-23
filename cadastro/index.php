
<?php include '../includes/cabecalho.php'; ?>

<div class="container mt-4">

    <h1>Cadastro</h1>

    <p>Escolha o que deseja cadastrar:</p>

    <div class="row mt-4">

        <!-- Cadastro de Tutor -->
        <div class="col-md-6 mb-3">
            <div class="card p-3">

                <h4>Cadastro de Tutores</h4>

                <p>Cadastro dos responsáveis pelos animais.</p>

                <a href="cadastro_tutor.php" class="btn btn-success">
                    Cadastrar Tutor
                </a>

            </div>
        </div>

        <!-- Cadastro de Animal -->
        <div class="col-md-6 mb-3">
            <div class="card p-3">

                <h4>Cadastro de Animais</h4>

                <p>Cadastro dos animais da clínica veterinária.</p>

                <a href="cadastro_animal.php" class="btn btn-success">
                    Cadastrar Animal
                </a>

            </div>
        </div>

    </div>

</div>

<?php include '../includes/rodape.php'; ?>
