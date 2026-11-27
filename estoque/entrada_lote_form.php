<?php
// entrada_lote_form.php
// Tela de cadastro de entrada de lote (produto, número, validade, quantidade)
// Utiliza Bootstrap e a conexão já disponível em ../includes/conexao.php

require_once __DIR__ . '/../includes/conexao.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Entrada de Lote</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2>Registrar Entrada de Lote</h2>
    <form id="formEntradaLote">
        <div class="mb-3">
            <label for="produto_id" class="form-label">Produto</label>
            <select class="form-select" id="produto_id" name="produto_id" required>
                <option value="" selected>Selecione o produto</option>
                <?php
                // Carrega lista de produtos ativos
                $sql = "SELECT id, nome FROM produto WHERE ativo = 1 ORDER BY nome";
                $res = $conn->query($sql);
                if ($res && $res->num_rows > 0) {
                    while ($row = $res->fetch_assoc()) {
                        echo "<option value='" . htmlspecialchars($row['id']) . "'>" . htmlspecialchars($row['nome']) . "</option>";
                    }
                }
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="numero_lote" class="form-label">Número do Lote</label>
            <input type="text" class="form-control" id="numero_lote" name="numero_lote" required maxlength="50">
        </div>
        <div class="mb-3">
            <label for="validade" class="form-label">Validade</label>
            <input type="date" class="form-control" id="validade" name="validade" required>
        </div>
        <div class="mb-3">
            <label for="quantidade" class="form-label">Quantidade</label>
            <input type="number" class="form-control" id="quantidade" name="quantidade" required min="1">
        </div>
        <button type="submit" class="btn btn-primary">Salvar</button>
    </form>
    <div id="msg" class="mt-3"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('formEntradaLote').addEventListener('submit', function (e) {
    e.preventDefault();
    const data = {
        produto_id: document.getElementById('produto_id').value,
        numero_lote: document.getElementById('numero_lote').value,
        validade: document.getElementById('validade').value,
        quantidade: document.getElementById('quantidade').value
    };
    fetch('api_entrada_lote.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(resp => {
        const msgDiv = document.getElementById('msg');
        if (resp.success) {
            msgDiv.innerHTML = '<div class="alert alert-success">' + resp.message + '</div>';
            document.getElementById('formEntradaLote').reset();
        } else {
            msgDiv.innerHTML = '<div class="alert alert-danger">' + resp.message + '</div>';
        }
    })
    .catch(err => {
        document.getElementById('msg').innerHTML = '<div class="alert alert-danger">Erro ao comunicar com o servidor.</div>';
    });
});
</script>
</body>
</html>
?>