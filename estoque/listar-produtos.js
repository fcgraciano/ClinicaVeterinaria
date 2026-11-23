'use strict';

const formulario = document.querySelector('#busca-produtos');
const campoBusca = formulario.elements.busca;
const situacao = document.querySelector('#situacao-produtos');
const tabela = document.querySelector('#tabela-produtos');
const corpoTabela = tabela.querySelector('tbody');
let consultaEmAndamento;

async function carregarProdutos() {
    consultaEmAndamento?.abort();
    const consulta = new AbortController();
    consultaEmAndamento = consulta;
    const url = new URL(formulario.action);
    url.searchParams.set('busca', campoBusca.value.trim());

    tabela.hidden = true;
    corpoTabela.replaceChildren();
    situacao.classList.remove('text-danger');
    situacao.textContent = 'Consultando produtos…';

    try {
        const resposta = await fetch(url, { signal: consulta.signal });
        const dados = await resposta.json();
        if (consulta.signal.aborted) {
            return;
        }
        if (!resposta.ok || !Array.isArray(dados.produtos)) {
            throw new Error(dados.erro || 'Resposta inválida da consulta.');
        }
        if (dados.produtos.length === 0) {
            situacao.textContent = 'Nenhum produto encontrado.';
            return;
        }

        const linhas = document.createDocumentFragment();
        for (const produto of dados.produtos) {
            const linha = document.createElement('tr');
            const nome = document.createElement('th');
            nome.scope = 'row';
            nome.textContent = produto.nome;
            linha.append(nome);
            linha.insertCell().textContent = produto.codigo_barras;
            linha.insertCell().textContent = produto.categoria;
            const saldo = linha.insertCell();
            saldo.className = 'text-end';
            const valor = String(produto.saldo);
            saldo.textContent = valor.includes('.') ? valor.replace(/0+$/, '').replace(/\.$/, '').replace('.', ',') : valor;
            linhas.append(linha);
        }
        corpoTabela.append(linhas);
        tabela.hidden = false;
        situacao.textContent = dados.produtos.length === 1 ? '1 produto encontrado.' : `${dados.produtos.length} produtos encontrados.`;
    } catch (erro) {
        if (!consulta.signal.aborted) {
            situacao.classList.add('text-danger');
            situacao.textContent = 'Não foi possível consultar os produtos. Tente novamente.';
        }
    }
}

formulario.addEventListener('submit', (evento) => {
    evento.preventDefault();
    carregarProdutos();
});
carregarProdutos();
