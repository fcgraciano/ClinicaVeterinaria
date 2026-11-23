'use strict';

const formEdicao = document.querySelector('#form-edicao-produto');
const situacaoEdicao = document.querySelector('#situacao-edicao');
const botaoSalvar = formEdicao.querySelector('button[type="submit"]');
const urlEdicao = new URL(formEdicao.action);
urlEdicao.searchParams.set('id', new URLSearchParams(location.search).get('id') ?? '');

async function consultarEdicao(opcoes) {
    const resposta = await fetch(urlEdicao, opcoes);
    const dados = await resposta.json();
    if (!resposta.ok) {
        throw new Error(dados.erro ?? 'Não foi possível acessar o produto.');
    }
    return dados;
}

async function carregarEdicao() {
    try {
        const dados = await consultarEdicao();
        formEdicao.elements.token.value = dados.token;
        for (const nome of ['nome', 'codigo_barras', 'estoque_minimo']) {
            formEdicao.elements[nome].value = dados.produto[nome];
        }
        for (const categoria of dados.categorias) {
            formEdicao.elements.categoria_id.add(new Option(categoria.nome, categoria.id));
        }
        formEdicao.elements.categoria_id.value = dados.produto.categoria_id;

        const fornecedores = document.querySelector('#fornecedores-edicao');
        const selecionados = new Set(dados.fornecedores_ids.map(String));
        if (dados.fornecedores.length === 0) {
            fornecedores.textContent = 'Nenhum fornecedor cadastrado.';
        }
        for (const fornecedor of dados.fornecedores) {
            const rotulo = document.createElement('label');
            rotulo.className = 'form-check d-block';
            const opcao = document.createElement('input');
            opcao.className = 'form-check-input';
            opcao.type = 'checkbox';
            opcao.name = 'fornecedores[]';
            opcao.value = fornecedor.id;
            opcao.checked = selecionados.has(String(fornecedor.id));
            rotulo.append(opcao, fornecedor.nome);
            fornecedores.append(rotulo);
        }
        formEdicao.hidden = false;
        situacaoEdicao.textContent = '';
    } catch (erro) {
        situacaoEdicao.className = 'text-danger';
        situacaoEdicao.textContent = erro instanceof SyntaxError ? 'Não foi possível carregar o produto.' : erro.message;
    }
}

formEdicao.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    botaoSalvar.disabled = true;
    situacaoEdicao.className = '';
    situacaoEdicao.textContent = 'Salvando alterações…';
    try {
        const dados = await consultarEdicao({ method: 'POST', body: new FormData(formEdicao) });
        situacaoEdicao.className = 'text-success';
        situacaoEdicao.textContent = dados.mensagem;
    } catch (erro) {
        situacaoEdicao.className = 'text-danger';
        situacaoEdicao.textContent = erro instanceof SyntaxError ? 'Não foi possível salvar o produto.' : erro.message;
    } finally {
        botaoSalvar.disabled = false;
    }
});

carregarEdicao();
