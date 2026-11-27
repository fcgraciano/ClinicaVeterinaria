<?php
// fornecedor_form.php
// Tela/modal de cadastro de fornecedor (razão social, CNPJ, contatos)
// Usa Bootstrap 4/5 (dependendo do projeto) e a conexão existente em ../includes/conexao.php

require_once __DIR__ . '/../includes/conexao.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Cadastro de Fornecedor</title>
    <!-- Inclua o CSS do Bootstrap do seu projeto -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2>Cadastro de Fornecedor</h2>
    <!-- Botão que abre o modal -->
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalFornecedor">
        Novo fornecedor
    </button>
</div>

<!-- Modal -->
<div class="modal fade" id="modalFornecedor" tabindex="-1" aria-labelledby="modalFornecedorLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="processa_fornecedor.php" id="formFornecedor">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFornecedorLabel">Cadastrar Fornecedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="razao_social" class="form-label">Razão Social</label>
                        <input type="text" class="form-control" id="razao_social" name="razao_social" required maxlength="150">
                    </div>
                    <div class="mb-3">
                        <label for="cnpj" class="form-label">CNPJ</label>
                        <input type="text" class="form-control" id="cnpj" name="cnpj" required pattern="\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}" placeholder="00.000.000/0000-00">
                    </div>
                    <div class="mb-3">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" required placeholder="(99) 99999-9999">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E‑mail</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="exemplo@dominio.com">
                    </div>
                    <!-- Adicione outros campos de contato que estejam no dicionário, se houver -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
?>