<?php
// inativar_produto.php
// Processamento para desativação lógica de produto (RNF05)
// Usa conexão já configurada em ../includes/conexao.php

require_once __DIR__ . '/../includes/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redireciona para a lista de produtos se acessado diretamente
    header('Location: listar_produtos.php');
    exit;
}

// Recebe o ID do produto do formulário
$produto_id = $_POST['produto_id'] ?? null;

if (!$produto_id || !ctype_digit($produto_id)) {
    // ID inválido
    $_SESSION['msg'] = 'ID do produto inválido.';
    header('Location: listar_produtos.php');
    exit;
}

// Consulta preparada para atualizar o status do produto
$sql = "UPDATE produtos SET ativo = 0 WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    // Erro na preparação da query
    $_SESSION['msg'] = 'Erro interno ao preparar a operação.';
    header('Location: listar_produtos.php');
    exit;
}

$stmt->bind_param('i', $produto_id);
if ($stmt->execute()) {
    // Sucesso - o produto ficará invisível nas listagens ativas
    $_SESSION['msg'] = 'Produto desativado com sucesso.';
} else {
    // Falha na execução
    $_SESSION['msg'] = 'Falha ao desativar o produto.';
}

$stmt->close();
$conn->close();

// Redireciona de volta para a página de listagem
header('Location: listar_produtos.php');
exit;
?>
