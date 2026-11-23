
<?php

// Conexão com o banco de dados
$conexao = new PDO(
    "mysql:host=localhost;dbname=patas_cia;charset=utf8",
    "root",
    ""
);

$conexao->setAttribute(
    PDO::ATTR_ERRMODE,
    PDO::ERRMODE_EXCEPTION
);

// CADASTRAR MOVIMENTAÇÃO
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $descricao = $_POST["descricao"];
    $categoria = $_POST["categoria"];
    $tipo = $_POST["tipo"];
    $valor = $_POST["valor"];
    $data = $_POST["data"];
    $status = $_POST["status"];

    if (!is_numeric($valor) || $valor <= 0) {
        die("Informe um valor válido.");
    }

    $sql = "INSERT INTO movimentacoes
    (descricao, categoria, tipo, valor, data_movimentacao, status)
    VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $conexao->prepare($sql);

    $stmt->execute([
        $descricao,
        $categoria,
        $tipo,
        $valor,
        $data,
        $status
    ]);

    echo "Movimentação cadastrada com sucesso!";
}

// DEFINIR O MÊS DA CONSULTA
$mes = $_GET["mes"] ?? date("Y-m");

// Validar o formato do mês
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    die("Mês inválido.");
}

$inicio = $mes . "-01";
$fim = date("Y-m-d", strtotime($inicio . " +1 month"));

// CALCULAR RECEITAS PAGAS
$sqlReceitas = "SELECT SUM(valor) AS total
FROM movimentacoes
WHERE tipo = 'Receita'
AND status = 'Pago'
AND data_movimentacao >= ?
AND data_movimentacao < ?";

$stmt = $conexao->prepare($sqlReceitas);
$stmt->execute([$inicio, $fim]);

$receitas = $stmt->fetch(PDO::FETCH_ASSOC);
$totalReceitas = $receitas["total"] ?? 0;

// CALCULAR DESPESAS PAGAS
$sqlDespesas = "SELECT SUM(valor) AS total
FROM movimentacoes
WHERE tipo = 'Despesa'
AND status = 'Pago'
AND data_movimentacao >= ?
AND data_movimentacao < ?";

$stmt = $conexao->prepare($sqlDespesas);
$stmt->execute([$inicio, $fim]);

$despesas = $stmt->fetch(PDO::FETCH_ASSOC);
$totalDespesas = $despesas["total"] ?? 0;

// CALCULAR VALORES PENDENTES
$sqlPendentes = "SELECT SUM(valor) AS total
FROM movimentacoes
WHERE tipo = 'Receita'
AND status = 'Pendente'
AND data_movimentacao >= ?
AND data_movimentacao < ?";

$stmt = $conexao->prepare($sqlPendentes);
$stmt->execute([$inicio, $fim]);

$pendentes = $stmt->fetch(PDO::FETCH_ASSOC);
$totalPendentes = $pendentes["total"] ?? 0;

// CALCULAR SALDO FINANCEIRO
$faturamento = $totalReceitas - $totalDespesas;

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Financeiro - Patas & Cia</title>
</head>

<body>

<h1>Clínica Veterinária Patas & Cia</h1>

<h2>Cadastro de movimentações</h2>

<form method="POST">

    Descrição:
    <input type="text" name="descricao" required>
    <br><br>

    Categoria:
    <select name="categoria">
        <option>Consulta</option>
        <option>Vacina</option>
        <option>Banho e Tosa</option>
        <option>Venda</option>
        <option>Outra</option>
    </select>
    <br><br>

    Tipo:
    <select name="tipo">
        <option>Receita</option>
        <option>Despesa</option>
    </select>
    <br><br>

    Valor:
    <input type="number" name="valor"
    step="0.01" min="0.01" required>
    <br><br>

    Data:
    <input type="date" name="data" required>
    <br><br>

    Status:
    <select name="status">
        <option>Pago</option>
        <option>Pendente</option>
    </select>
    <br><br>

    <button type="submit">Cadastrar</button>

</form>

<hr>

<h2>Faturamento mensal</h2>

<form method="GET">
    Escolha o mês:
    <input type="month" name="mes"
    value="<?php echo htmlspecialchars($mes); ?>">

    <button type="submit">Consultar</button>
</form>

<h3>Total de receitas:
R$ <?php echo number_format($totalReceitas, 2, ',', '.'); ?>
</h3>

<h3>Total de despesas:
R$ <?php echo number_format($totalDespesas, 2, ',', '.'); ?>
</h3>

<h3>Contas a receber:
R$ <?php echo number_format($totalPendentes, 2, ',', '.'); ?>
</h3>

<h2>Saldo financeiro do mês:
R$ <?php echo number_format($faturamento, 2, ',', '.'); ?>
</h2>

</body>
</html>
