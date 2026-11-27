<?php include __DIR__ . '/../includes/cabecalho.php'; ?>

<h1 class="mb-3">Editar produto</h1>
<p id="situacao-edicao" role="status">Carregando produto…</p>
<noscript><p>Ative o JavaScript para carregar e atualizar o produto pela API.</p></noscript>

<form id="form-edicao-produto" action="api-editar-produto.php" method="post" class="col-lg-7 mb-3" hidden>
    <input type="hidden" name="token">
    <div class="mb-3">
        <label class="form-label" for="nome">Nome</label>
        <input class="form-control" id="nome" name="nome" maxlength="150" required>
    </div>
    <div class="mb-3">
        <label class="form-label" for="codigo_barras">Código de barras</label>
        <input class="form-control" id="codigo_barras" name="codigo_barras" maxlength="50" required>
    </div>
    <div class="mb-3">
        <label class="form-label" for="categoria_id">Categoria</label>
        <select class="form-select" id="categoria_id" name="categoria_id" required></select>
    </div>
    <fieldset class="mb-3">
        <legend class="fs-6">Fornecedores</legend>
        <div id="fornecedores-edicao"></div>
    </fieldset>
    <div class="mb-3">
        <label class="form-label" for="estoque_minimo">Estoque mínimo</label>
        <input class="form-control" type="number" id="estoque_minimo" name="estoque_minimo"
            min="0" max="4294967295" step="1" required>
    </div>
    <fieldset class="mb-3">
        <legend class="fs-6">Unidades e fracionamento</legend>
        <div class="mb-3">
            <label class="form-label" for="unidade_padrao">Unidade padrão do saldo</label>
            <input class="form-control" id="unidade_padrao" value="Unidade" readonly aria-readonly="true">
            <div class="form-text">O saldo do lote e as movimentações são armazenados em unidades do produto.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="fracionamento_habilitado">Permitir baixa fracionada</label>
            <select class="form-select" id="fracionamento_habilitado" name="fracionamento_habilitado" required>
                <option value="0">Não</option>
                <option value="1">Sim</option>
            </select>
        </div>
        <div id="unidades-fracionadas" hidden>
            <p class="form-text">Informe quantas unidades padrão são consumidas por 1 mL ou por 1 dose. Preencha ao menos uma unidade fracionada.</p>
            <div class="mb-3">
                <label class="form-label" for="fator_ml">Unidade fracionada: mL — unidades padrão por 1 mL</label>
                <input class="form-control" type="number" id="fator_ml" name="fator_ml"
                    min="0.000001" max="1" step="0.000001" inputmode="decimal">
            </div>
            <div class="mb-3">
                <label class="form-label" for="fator_dose">Unidade fracionada: dose — unidades padrão por 1 dose</label>
                <input class="form-control" type="number" id="fator_dose" name="fator_dose"
                    min="0.000001" max="1" step="0.000001" inputmode="decimal">
            </div>
        </div>
    </fieldset>
    <button class="btn btn-primary" type="submit">Salvar alterações</button>
</form>

<a class="btn btn-outline-secondary" href="listar-produtos.php">Voltar aos produtos</a>
<script src="editar-produto.js" defer></script>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
