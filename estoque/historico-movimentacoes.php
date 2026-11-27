<?php include __DIR__ . '/../includes/cabecalho.php';
?>

<h1 class="mb-3">Histórico de movimentações</h1>

<form id="filtros-historico" class="row g-3 align-items-end mb-4" action="historico-movimentacoes.php" method="get">
    <div class="col-md-4">
        <label class="form-label" for="produto_id">Produto</label>
        <select class="form-select" id="produto_id" name="produto_id">
            <option value="">Todos os produtos</option>
        </select>
    </div>
    <div class="col-md-5">
        <label class="form-label" for="lote_id">Lote</label>
        <select class="form-select" id="lote_id" name="lote_id">
            <option value="">Todos os lotes</option>
        </select>
    </div>
    <div class="col-md-auto">
        <button class="btn btn-primary" type="submit">Consultar</button>
    </div>
</form>

<p id="situacao-historico" role="status" aria-live="polite">Carregando histórico…</p>
<div id="erro-historico" class="alert alert-danger d-none" role="alert"></div>
<div id="tabela-historico" class="table-responsive mb-3" hidden>
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th scope="col">Data e hora</th>
                <th scope="col">Operador</th>
                <th scope="col">Lote</th>
                <th scope="col">Tipo</th>
                <th scope="col" class="text-end">Quantidade</th>
                <th scope="col">Justificativa</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<a class="btn btn-outline-secondary" href="index.php">Voltar ao estoque</a>

<script>
(() => {
    const formulario = document.getElementById('filtros-historico');
    const filtroProduto = document.getElementById('produto_id');
    const filtroLote = document.getElementById('lote_id');
    const situacao = document.getElementById('situacao-historico');
    const erro = document.getElementById('erro-historico');
    const tabela = document.getElementById('tabela-historico');
    const corpoTabela = tabela.querySelector('tbody');
    const tipos = { entrada: 'Entrada', saida: 'Saída', perda: 'Perda', descarte: 'Descarte' };
    const parametros = new URLSearchParams(window.location.search);
    let lotesDisponiveis = [];
    let primeiraConsulta = true;

    function preencher(select, itens, rotuloVazio, valorInicial, label) {
        select.replaceChildren(new Option(rotuloVazio, ''));
        for (const item of itens) {
            select.add(new Option(label(item), item.id));
        }
        select.value = valorInicial;
        if (select.value !== valorInicial) select.value = '';
    }

    function filtrarLotes() {
        const produtoId = filtroProduto.value;
        const lotes = produtoId ? lotesDisponiveis.filter((lote) => lote.produto_id === produtoId) : lotesDisponiveis;
        const atual = filtroLote.value;
        preencher(filtroLote, lotes, 'Todos os lotes', atual, (lote) => lote.rotulo);
    }

    function formatarQuantidade(valor) {
        const quantidade = String(valor ?? '0');
        return quantidade.includes('.')
            ? quantidade.replace(/0+$/, '').replace(/\.$/, '').replace('.', ',')
            : quantidade;
    }

    function adicionarCelula(linha, texto, cabecalho = false, alinhamento = '') {
        const celula = document.createElement(cabecalho ? 'th' : 'td');
        celula.textContent = texto;
        if (cabecalho) celula.scope = 'row';
        if (alinhamento) celula.className = alinhamento;
        linha.append(celula);
    }

    async function consultar() {
        erro.classList.add('d-none');
        erro.textContent = '';
        tabela.hidden = true;
        corpoTabela.replaceChildren();
        situacao.textContent = 'Consultando histórico…';
        const url = new URL('api-historico-movimentacoes.php', window.location.href);
        const produtoSelecionado = filtroProduto.value || (primeiraConsulta ? (parametros.get('produto_id') ?? '') : '');
        const loteSelecionado = filtroLote.value || (primeiraConsulta ? (parametros.get('lote_id') ?? '') : '');
        if (produtoSelecionado) url.searchParams.set('produto_id', produtoSelecionado);
        if (loteSelecionado) url.searchParams.set('lote_id', loteSelecionado);

        try {
            const resposta = await fetch(url, { headers: { Accept: 'application/json' } });
            const dados = await resposta.json();
            if (!resposta.ok) throw new Error(dados.erro || 'Não foi possível consultar o histórico.');

            preencher(filtroProduto, dados.produtos, 'Todos os produtos', produtoSelecionado,
                (produto) => produto.nome);
            lotesDisponiveis = dados.lotes;
            preencher(filtroLote, dados.lotes.filter((lote) => !filtroProduto.value || lote.produto_id === filtroProduto.value),
                'Todos os lotes', loteSelecionado, (lote) => lote.rotulo);
            filtrarLotes();
            primeiraConsulta = false;

            if (dados.movimentacoes.length === 0) {
                situacao.textContent = 'Nenhuma movimentação encontrada para os filtros selecionados.';
                return;
            }

            for (const movimentacao of dados.movimentacoes) {
                const linha = document.createElement('tr');
                const data = String(movimentacao.data_movimentacao ?? '').replace('T', ' ').split('.')[0];
                const tipo = tipos[movimentacao.tipo_exibicao] ?? movimentacao.tipo_exibicao;
                adicionarCelula(linha, data);
                adicionarCelula(linha, movimentacao.operador || '—');
                adicionarCelula(linha, `${movimentacao.produto} — ${movimentacao.numero_lote} / ${movimentacao.fornecedor}`, true);
                adicionarCelula(linha, tipo);
                adicionarCelula(linha, formatarQuantidade(movimentacao.quantidade), false, 'text-end');
                adicionarCelula(linha, movimentacao.justificativa || '—');
                corpoTabela.append(linha);
            }
            tabela.hidden = false;
            situacao.textContent = `${dados.movimentacoes.length} movimentações encontradas.`;
        } catch (falha) {
            situacao.textContent = '';
            erro.textContent = falha.message || 'Não foi possível consultar o histórico. Tente novamente.';
            erro.classList.remove('d-none');
        }
    }

    filtroProduto.addEventListener('change', filtrarLotes);
    formulario.addEventListener('submit', (evento) => {
        evento.preventDefault();
        const url = new URL(window.location.href);
        for (const campo of ['produto_id', 'lote_id']) {
            const valor = document.getElementById(campo).value;
            if (valor) url.searchParams.set(campo, valor);
            else url.searchParams.delete(campo);
        }
        window.history.replaceState({}, '', url);
        consultar();
    });

    consultar();
})();
</script>

<?php include __DIR__ . '/../includes/rodape.php'; ?>
