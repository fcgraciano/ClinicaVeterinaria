<?php
// api_entrada_lote.php
// Endpoint que recebe JSON com dados da entrada de lote e registra no banco.
// Atualiza também o saldo (estoque) do produto.
// Usa conexão já configurada em ../includes/conexao.php

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/conexao.php';

// Recebe o corpo JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
    exit;
}

$produto_id = $data['produto_id'] ?? null;
$numero_lote = $data['numero_lote'] ?? null;
$validade   = $data['validade'] ?? null;
$quantidade = $data['quantidade'] ?? null;

// Validações simples
if (!$produto_id || !ctype_digit((string)$produto_id) ||
    empty($numero_lote) || empty($validade) ||
    !ctype_digit((string)$quantidade) || (int)$quantidade <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos ou inválidos.']);
    exit;
}

$conn->begin_transaction();
try {
    // 1. Insere lote
    $sql = "INSERT INTO lote (produto_id, numero, validade, quantidade, data_entrada) VALUES (?,?,?,?,NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Erro ao preparar inserção do lote.');
    $stmt->bind_param('issi', $produto_id, $numero_lote, $validade, $quantidade);
    $stmt->execute();
    $stmt->close();

    // 2. Atualiza estoque do produto
    $sqlUpd = "UPDATE produto SET estoque_atual = estoque_atual + ? WHERE id = ?";
    $stmtUpd = $conn->prepare($sqlUpd);
    if (!$stmtUpd) throw new Exception('Erro ao preparar atualização de estoque.');
    $stmtUpd->bind_param('ii', $quantidade, $produto_id);
    $stmtUpd->execute();
    $stmtUpd->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Entrada de lote registrada com sucesso.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>