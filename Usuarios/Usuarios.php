
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: #0d6efd;
        }

        .main-container {
            margin-top: 40px;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,.06);
        }

        .table thead {
            background: #f1f4f9;
        }

        .table th {
            color: #495057;
            font-size: 14px;
        }

        .table td {
            vertical-align: middle;
        }

        .search-box {
            max-width: 350px;
        }
    </style>
</head>

<body>

<!-- MENU -->
<nav class="navbar navbar-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">
            Sistema de Usuários
        </a>

        <span class="text-white">
            Administração
        </span>
    </div>
</nav>

<div class="container main-container">

    <!-- CABEÇALHO -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold">Usuários</h3>
            <p class="text-muted mb-0">
                Gerencie os usuários cadastrados no sistema.
            </p>
        </div>

        <button class="btn btn-primary" onclick="novoUsuario()">
            + Novo usuário
        </button>
    </div>

    <!-- CARD -->
    <div class="card">
        <div class="card-body">

            <!-- PESQUISA -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">

                <h5 class="fw-bold mb-0">Lista de usuários</h5>

                <input
                    type="text"
                    id="pesquisa"
                    class="form-control search-box"
                    placeholder="Pesquisar código ou login..."
                    onkeyup="pesquisarUsuarios()">

            </div>

            <!-- TABELA -->
            <div class="table-responsive">

                <table class="table table-hover" id="tabelaUsuarios">

                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Login</th>
                            <th>Senha</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>

                    <tbody id="listaUsuarios">

                        <tr>
                            <td>001</td>
                            <td>admin</td>
                            <td>••••••••</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-warning"
                                    onclick="editarUsuario(this)">
                                    Editar
                                </button>

                                <button class="btn btn-sm btn-danger"
                                    onclick="excluirUsuario(this)">
                                    Excluir
                                </button>
                            </td>
                        </tr>

                        <tr>
                            <td>002</td>
                            <td>joao</td>
                            <td>••••••••</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-warning"
                                    onclick="editarUsuario(this)">
                                    Editar
                                </button>

                                <button class="btn btn-sm btn-danger"
                                    onclick="excluirUsuario(this)">
                                    Excluir
                                </button>
                            </td>
                        </tr>

                        <tr>
                            <td>003</td>
                            <td>maria</td>
                            <td>••••••••</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-warning"
                                    onclick="editarUsuario(this)">
                                    Editar
                                </button>

                                <button class="btn btn-sm btn-danger"
                                    onclick="excluirUsuario(this)">
                                    Excluir
                                </button>
                            </td>
                        </tr>

                    </tbody>
                </table>

            </div>

            <div class="text-muted small mt-3">
                Total de usuários: <span id="totalUsuarios">3</span>
            </div>

        </div>
    </div>

</div>

<!-- MODAL CADASTRO / EDIÇÃO -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="tituloModal">
                    Novo usuário
                </h5>

                <button type="button" class="btn-close"
                    data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="indice">

                <div class="mb-3">
                    <label class="form-label">Código</label>
                    <input type="text" id="codigo"
                        class="form-control" placeholder="Código">
                </div>

                <div class="mb-3">
                    <label class="form-label">Login</label>
                    <input type="text" id="login"
                        class="form-control" placeholder="Login">
                </div>

                <div class="mb-3">
                    <label class="form-label">Senha</label>
                    <input type="password" id="senha"
                        class="form-control" placeholder="Senha">
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary"
                    data-bs-dismiss="modal">
                    Cancelar
                </button>

                <button class="btn btn-primary"
                    onclick="salvarUsuario()">
                    Salvar
                </button>
            </div>

        </div>
    </div>
</div>

<!-- BOOTSTRAP -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>

    const modal = new bootstrap.Modal(
        document.getElementById('modalUsuario')
    );

    function novoUsuario() {
        document.getElementById('tituloModal').innerText = 'Novo usuário';

        document.getElementById('indice').value = '';
        document.getElementById('codigo').value = '';
        document.getElementById('login').value = '';
        document.getElementById('senha').value = '';

        modal.show();
    }

    function salvarUsuario() {

        const codigo = document.getElementById('codigo').value.trim();
        const login = document.getElementById('login').value.trim();
        const senha = document.getElementById('senha').value;

        if (!codigo || !login || !senha) {
            alert('Preencha todos os campos!');
            return;
        }

        const indice = document.getElementById('indice').value;
        const tabela = document.getElementById('listaUsuarios');

        if (indice !== '') {

            const linha = tabela.rows[indice];

            linha.cells[0].innerText = codigo;
            linha.cells[1].innerText = login;

        } else {

            const linha = tabela.insertRow();

            linha.innerHTML = `
                <td>${escapar(codigo)}</td>
                <td>${escapar(login)}</td>
                <td>••••••••</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-warning"
                        onclick="editarUsuario(this)">Editar</button>

                    <button class="btn btn-sm btn-danger"
                        onclick="excluirUsuario(this)">Excluir</button>
                </td>
            `;
        }

        modal.hide();
        atualizarTotal();
        pesquisarUsuarios();
    }

    function editarUsuario(botao) {

        const linha = botao.closest('tr');
        const tabela = document.getElementById('listaUsuarios');

        const indice = Array.from(tabela.rows).indexOf(linha);

        document.getElementById('tituloModal').innerText = 'Editar usuário';
        document.getElementById('indice').value = indice;
        document.getElementById('codigo').value = linha.cells[0].innerText;
        document.getElementById('login').value = linha.cells[1].innerText;
        document.getElementById('senha').value = '';

        modal.show();
    }

    function excluirUsuario(botao) {

        if (confirm('Deseja realmente excluir este usuário?')) {
            botao.closest('tr').remove();
            atualizarTotal();
            pesquisarUsuarios();
        }
    }

    function pesquisarUsuarios() {

        const filtro = document.getElementById('pesquisa')
            .value.toLowerCase();

        const linhas = document.querySelectorAll('#listaUsuarios tr');

        linhas.forEach(linha => {

            const codigo = linha.cells[0].innerText.toLowerCase();
            const login = linha.cells[1].innerText.toLowerCase();

            linha.style.display =
                codigo.includes(filtro) || login.includes(filtro)
                ? ''
                : 'none';
        });
    }

    function atualizarTotal() {
        document.getElementById('totalUsuarios').innerText =
            document.getElementById('listaUsuarios').rows.length;
    }

    function escapar(valor) {
        return valor.replace(/[&<>"']/g, caractere => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[caractere]);
    }

</script>

</body>
</html>
