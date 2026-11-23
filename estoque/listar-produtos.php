<?php include __DIR__ . '/../includes/cabecalho.php'; ?>

<h1 class="mb-3">Consultar produtos</h1>

<form id="busca-produtos" class="row g-3 align-items-end mb-3" action="api-produtos.php" method="get">
    <div class="col-md-6">
        <label class="form-label" for="busca">Nome ou código de barras</label>
        <input class="form-control" type="search" id="busca" name="busca" maxlength="150">
    </div>
    <div class="col-md-auto">
        <button class="btn btn-primary" type="submit">Buscar</button>
    </div>
</form>

<p id="situacao-produtos" role="status"></p>
<noscript><p>Ative o JavaScript para consultar os produtos nesta tela.</p></noscript>

<div id="tabela-produtos" class="table-responsive mb-3" hidden>
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th scope="col">Nome</th>
                <th scope="col">Código de barras</th>
                <th scope="col">Categoria</th>
                <th scope="col" class="text-end">Saldo</th>
                <th scope="col">Ações</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<a class="btn btn-outline-secondary" href="index.php">Voltar ao estoque</a>
<script src="listar-produtos.js" defer></script>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
