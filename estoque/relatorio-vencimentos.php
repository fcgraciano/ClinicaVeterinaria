<?php
$intervalos = ['30', '60', '90'];
$intervalo = $_GET['dias'] ?? '30';
if (!is_string($intervalo) || !in_array($intervalo, $intervalos, true)) {
    $intervalo = '30';
}

include __DIR__ . '/../includes/cabecalho.php';
?>

<h1 class="mb-3">Relatório de vencimentos</h1>

<form method="get" action="relatorio-vencimentos.php" class="row g-3 align-items-end mb-4" id="filtro-vencimentos">
    <div class="col-sm-auto">
        <label class="form-label" for="dias">Intervalo a partir de hoje</label>
        <select class="form-select" id="dias" name="dias">
            <?php foreach ($intervalos as $dias): ?>
                <option value="<?= $dias ?>" <?= $intervalo === $dias ? 'selected' : '' ?>>
                    Até <?= $dias ?> dias
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-sm-auto">
        <button class="btn btn-primary" type="submit">Consultar</button>
    </div>
</form>

<div id="erro-vencimentos" class="alert alert-danger d-none" role="alert"></div>
<p id="periodo-vencimentos" aria-live="polite"></p>
<div id="resultado-vencimentos" aria-live="polite">
    <p class="text-muted">Carregando lotes próximos ao vencimento…</p>
</div>

<a class="btn btn-outline-secondary" href="index.php">Voltar ao estoque</a>

<script>
(() => {
    const form = document.getElementById('filtro-vencimentos');
    const seletor = document.getElementById('dias');
    const erro = document.getElementById('erro-vencimentos');
    const periodo = document.getElementById('periodo-vencimentos');
    const resultado = document.getElementById('resultado-vencimentos');

    const escapar = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caractere) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[caractere]);

    const dataPtBr = (data) => {
        const [ano, mes, dia] = data.split('-');
        return `${dia}/${mes}/${ano}`;
    };

    async function consultarVencimentos(dias) {
        erro.classList.add('d-none');
        erro.textContent = '';
        periodo.textContent = '';
        resultado.innerHTML = '<p class="text-muted">Carregando lotes próximos ao vencimento…</p>';
        try {
            const resposta = await fetch(`api-relatorio-vencimentos.php?dias=${encodeURIComponent(dias)}`, {
                headers: { Accept: 'application/json' }
            });
            const dados = await resposta.json();
            if (!resposta.ok) {
                throw new Error(dados.erro || 'Não foi possível consultar os vencimentos.');
            }

            periodo.textContent = `Vencimentos de ${dataPtBr(dados.inicio)} a ${dataPtBr(dados.fim)}.`;
            if (dados.lotes.length === 0) {
                resultado.innerHTML = '<div class="alert alert-info" role="status">Nenhum lote com saldo vence no intervalo selecionado.</div>';
                return;
            }

            const linhas = dados.lotes.map((lote) => `
                <tr>
                    <th scope="row">${escapar(lote.produto)}</th>
                    <td>${escapar(lote.numero_lote)}</td>
                    <td>${escapar(lote.fornecedor)}</td>
                    <td>${escapar(dataPtBr(lote.data_validade))}</td>
                    <td class="text-end">${escapar(lote.dias_restantes)}</td>
                </tr>`).join('');
            resultado.innerHTML = `
                <div class="table-responsive mb-3">
                    <table class="table table-striped align-middle">
                        <thead><tr>
                            <th scope="col">Produto</th><th scope="col">Lote</th>
                            <th scope="col">Fornecedor</th><th scope="col">Data de validade</th>
                            <th scope="col" class="text-end">Dias restantes</th>
                        </tr></thead>
                        <tbody>${linhas}</tbody>
                    </table>
                </div>`;
        } catch (falha) {
            resultado.innerHTML = '';
            erro.textContent = falha.message || 'Não foi possível consultar os vencimentos. Tente novamente.';
            erro.classList.remove('d-none');
        }
    }

    form.addEventListener('submit', (evento) => {
        evento.preventDefault();
        const url = new URL(window.location.href);
        url.searchParams.set('dias', seletor.value);
        window.history.replaceState({}, '', url);
        consultarVencimentos(seletor.value);
    });

    consultarVencimentos(seletor.value);
})();
</script>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
