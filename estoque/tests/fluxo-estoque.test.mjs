import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import test from 'node:test';

// Configure ESTOQUE_BASE_URL to point to a disposable test installation with
// at least one category and one supplier. The flow creates persistent audit data.
// Run with: node --test estoque/tests/fluxo-estoque.test.mjs
const baseUrl = process.env.ESTOQUE_BASE_URL;

function atributos(html) {
    return Object.fromEntries([...html.matchAll(/([\w-]+)="([^"]*)"/g)].map(([, nome, valor]) => [nome, valor]));
}

function opcoesSelect(html, id) {
    const select = html.match(new RegExp(`<select\\b(?=[^>]*\\bid="${id}")[^>]*>([\\s\\S]*?)<\\/select>`, 'i'));
    assert.ok(select, `Campo select ${id} não encontrado`);
    return [...select[1].matchAll(/<option\b([^>]*)>([\s\S]*?)<\/option>/gi)]
        .map(([, attrs, label]) => ({ ...atributos(attrs), label: label.replace(/<[^>]*>/g, '').trim() }))
        .filter((opcao) => opcao.value);
}

function valorCampo(html, nome) {
    const campo = [...html.matchAll(/<input\b[^>]*>/gi)]
        .map(([tag]) => atributos(tag))
        .find((item) => item.name === nome);
    return campo?.value ?? '';
}

function dataSaoPaulo() {
    const partes = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'America/Sao_Paulo', year: 'numeric', month: '2-digit', day: '2-digit'
    }).formatToParts(new Date());
    const campos = Object.fromEntries(partes.map(({ type, value }) => [type, value]));
    return `${campos.year}-${campos.month}-${campos.day}`;
}

function adicionarDias(data, dias) {
    const dataUTC = new Date(`${data}T12:00:00Z`);
    dataUTC.setUTCDate(dataUTC.getUTCDate() + dias);
    return dataUTC.toISOString().slice(0, 10);
}

test('consulta, cadastro, entrada, saída, saldo, histórico e vencimentos do estoque', {
    skip: !baseUrl && 'Defina ESTOQUE_BASE_URL para executar contra uma instalação descartável do estoque.'
}, async () => {
    const urlBase = new URL(baseUrl);
    if (!urlBase.pathname.endsWith('/')) urlBase.pathname += '/';
    const cookies = new Map();

    async function requisitar(caminho, opcoes = {}, redirecionamentos = 0) {
        assert.ok(redirecionamentos <= 5, 'Redirecionamento excedeu o limite esperado');
        const url = new URL(caminho, urlBase);
        const headers = new Headers(opcoes.headers ?? {});
        if (cookies.size) headers.set('Cookie', [...cookies].map(([nome, valor]) => `${nome}=${valor}`).join('; '));
        const resposta = await fetch(url, { ...opcoes, headers, redirect: 'manual' });
        const setCookies = resposta.headers.getSetCookie?.() ?? [resposta.headers.get('set-cookie')].filter(Boolean);
        for (const valor of setCookies) {
            const [par] = valor.split(';', 1);
            const separador = par.indexOf('=');
            if (separador > 0) cookies.set(par.slice(0, separador), par.slice(separador + 1));
        }
        if ([301, 302, 303, 307, 308].includes(resposta.status)) {
            const destino = resposta.headers.get('location');
            assert.ok(destino, `Resposta ${resposta.status} sem Location`);
            const metodo = resposta.status === 303 ? 'GET' : (opcoes.method ?? 'GET');
            const corpo = metodo === 'GET' ? undefined : opcoes.body;
            return requisitar(destino, { method: metodo, body: corpo, headers: opcoes.headers }, redirecionamentos + 1);
        }
        return resposta;
    }

    async function texto(caminho, opcoes) {
        const resposta = await requisitar(caminho, opcoes);
        assert.equal(resposta.status, 200, `${caminho} respondeu HTTP ${resposta.status}`);
        return resposta.text();
    }

    const tag = randomUUID().replaceAll('-', '').slice(0, 12);
    const nomeProduto = `E2E Estoque ${tag}`;
    const codigoBarras = `${Date.now()}${Math.floor(Math.random() * 1000)}`.slice(0, 20);
    const numeroLote = `E2E-${tag}`;
    const validade = adicionarDias(dataSaoPaulo(), 14);

    // Consultar produtos pela API do próprio módulo.
    const consultaVazia = await requisitar(`api-produtos.php?busca=${encodeURIComponent(nomeProduto)}`);
    assert.equal(consultaVazia.status, 200);
    const dadosVazios = await consultaVazia.json();
    assert.ok(Array.isArray(dadosVazios.produtos));
    assert.equal(dadosVazios.produtos.some((produto) => produto.nome === nomeProduto), false);

    // Cadastrar produto pela tela de estoque e recuperar o token de sessão.
    const cadastroHtml = await texto('adicionar-produto.php');
    const categorias = opcoesSelect(cadastroHtml, 'categoria_id');
    const fornecedores = opcoesSelect(cadastroHtml, 'fornecedor_id');
    assert.ok(categorias.length, 'A instalação de teste precisa ter ao menos uma categoria cadastrada');
    assert.ok(fornecedores.length, 'A instalação de teste precisa ter ao menos um fornecedor cadastrado');
    const tokenProduto = valorCampo(cadastroHtml, 'token');
    assert.ok(tokenProduto, 'Token do cadastro de produto não encontrado');

    const cadastroProduto = await texto('adicionar-produto.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            token: tokenProduto,
            nome: nomeProduto,
            categoria_id: categorias[0].value,
            fornecedor_id: fornecedores[0].value,
            codigo_barras: codigoBarras,
            estoque_minimo: '0'
        })
    });
    assert.match(cadastroProduto, /cadastrado com sucesso/i);

    const buscaProduto = await requisitar(`api-produtos.php?busca=${encodeURIComponent(nomeProduto)}`);
    assert.equal(buscaProduto.status, 200);
    const produto = (await buscaProduto.json()).produtos.find((item) => item.nome === nomeProduto);
    assert.ok(produto, 'Produto cadastrado não apareceu na API de consulta');

    // Criar lote e entrada pela tela de movimentação.
    const entradaHtml = await texto('registrar-entrada-de-lote.php');
    const vinculos = opcoesSelect(entradaHtml, 'vinculo');
    const vinculo = vinculos.find((item) => item.value.startsWith(`${produto.id}:`));
    assert.ok(vinculo, 'Vínculo de produto e fornecedor não apareceu no cadastro de lote');
    const tokenEntrada = valorCampo(entradaHtml, 'token');
    assert.ok(tokenEntrada, 'Token de entrada de lote não encontrado');

    const entrada = await texto('registrar-entrada-de-lote.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            token: tokenEntrada,
            vinculo: vinculo.value,
            numero_lote: numeroLote,
            quantidade: '10',
            data_entrada: dataSaoPaulo(),
            data_validade: validade
        })
    });
    assert.match(entrada, /entrada registrada/i);

    // Usar o lote cadastrado para dar saída e conferir o saldo apresentado.
    const saidaHtml = await texto('registrar-saida-de-estoque.php');
    const lotes = opcoesSelect(saidaHtml, 'lote_id');
    const lote = lotes.find((item) => item.label.includes(nomeProduto) && item.label.includes(numeroLote));
    assert.ok(lote, 'Lote cadastrado não apareceu na tela de saída');
    const tokenSaida = valorCampo(saidaHtml, 'token');
    assert.ok(tokenSaida, 'Token de saída não encontrado');
    const loteId = lote.value;

    const saida = await texto('registrar-saida-de-estoque.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            token: tokenSaida,
            lote_id: loteId,
            unidade: 'unidade',
            quantidade: '3',
            responsavel_tecnico: '',
            registro_responsavel: ''
        })
    });
    assert.match(saida, /saída registrada/i);
    assert.match(saida, /7 unidade\(s\)/i);

    // Confirmar saldo pela API e as duas movimentações no histórico filtrado.
    const saldoResposta = await requisitar(`api-produtos.php?busca=${encodeURIComponent(nomeProduto)}`);
    const saldoProduto = (await saldoResposta.json()).produtos.find((item) => item.nome === nomeProduto);
    assert.equal(Number(saldoProduto.saldo), 7);

    const historico = await texto(`historico-movimentacoes.php?produto_id=${produto.id}&lote_id=${loteId}`);
    assert.match(historico, new RegExp(numeroLote));
    assert.match(historico, /Entrada/);
    assert.match(historico, /Saída/);
    assert.match(historico, />7<|>7,0+</);

    // Confirmar que o lote aparece no relatório do período de 30 dias.
    const vencimentos = await texto('relatorio-vencimentos.php?dias=30');
    assert.match(vencimentos, new RegExp(nomeProduto));
    assert.match(vencimentos, new RegExp(numeroLote));
});
