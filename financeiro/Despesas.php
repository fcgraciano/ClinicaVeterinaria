
<?php

// Conexão com o banco de dados
$conexao = mysqli_connect(
    "localhost",
    "root",
    "",
    "clinica_patas_cia"
);

if (!$conexao) {
    die("Erro na conexão: " . mysqli_connect_error());
}

// CADASTRAR DESPESA
if (isset($_POST['cadastrar'])) {

    $descricao = $_POST['descricao'];
    $valor = $_POST['valor'];
    $data = $_POST['data_despesa'];

    $sql = "INSERT INTO despesas
            (descricao, valor, data_despesa)
            VALUES (?, ?, ?)";

    $stmt = mysqli_prepare($conexao, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "sds",
        $descricao,
        $valor,
        $data
    );

    if (mysqli_stmt_execute($stmt)) {
        echo "Despesa cadastrada com sucesso!";
    } else {
        echo "Erro ao cadastrar despesa.";
    }

    mysqli_stmt_close($stmt);
}

// EXCLUIR DESPESA
if (isset($_GET['excluir'])) {

    $id = intval($_GET['excluir']);

    $sql = "DELETE FROM despesas WHERE id = ?";
    $stmt = mysqli_prepare($conexao, $sql);

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);

    header("Location: despesas.php");
    exit;
}

// MARCAR DESPESA COMO PAGA
if (isset($_GET['pagar'])) {

    $id = intval($_GET['pagar']);

    $sql = "UPDATE despesas
            SET status = 'Paga'
            WHERE id = ?";

    $stmt = mysqli_prepare($conexao, $sql);

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);

    header("Location: despesas.php");
    exit;
}

// LISTAR DESPESAS
$sql = "SELECT * FROM despesas ORDER BY data_despesa DESC";
$resultado = mysqli_query($conexao, $sql);

// CALCULAR TOTAL DE DESPESAS
$sqlTotal = "SELECT SUM(valor) AS total FROM despesas";
$resultadoTotal = mysqli_query($conexao, $sqlTotal);

$total = mysqli_fetch_assoc($resultadoTotal)['total'] ?? 0;

// CALCULAR CONTAS PENDENTES
$sqlPendente = "SELECT SUM(valor) AS total
                FROM despesas
                WHERE status = 'Pendente'";

$resultadoPendente = mysqli_query($conexao, $sqlPendente);

$pendente = mysqli_fetch_assoc($resultadoPendente)['total'] ?? 0;

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Controle de Despesas</title>
</head>

<body>

<h1>Clínica Veterinária Patas & Cia</h1>

<h2>Cadastro de Despesas</h2>

<form method="POST">

    <label>Descrição da despesa:</label><br>
    <input type="text" name="descricao" required>
    <br><br>

    <label>Valor:</label><br>
    <input type="number" name="valor"
           step="0.01" min="0.01" required>
    <br><br>

    <label>Data da despesa:</label><br>
    <input type="date" name="data_despesa" required>
    <br><br>

    <button type="submit" name="cadastrar">
        Cadastrar Despesa
    </button>

</form>

<hr>

<h2>Resumo Financeiro</h2>

<p>
    Total de despesas:
    R$ <?php echo number_format($total, 2, ',', '.'); ?>
</p>

<p>
    Contas pendentes:
    R$ <?php echo number_format($pendente, 2, ',', '.'); ?>
</p>

<hr>

<h2>Despesas Cadastradas</h2>

<table border="1" cellpadding="10">

    <tr>
        <th>ID</th>
        <th>Descrição</th>
        <th>Valor</th>
        <th>Data</th>
        <th>Status</th>
        <th>Ações</th>
    </tr>

    <?php while ($despesa = mysqli_fetch_assoc($resultado)) { ?>

    <tr>

        <td><?php echo $despesa['id']; ?></td>

        <td>
            <?php echo htmlspecialchars($despesa['descricao']); ?>
        </td>

        <td>
            R$ <?php echo number_format(
                $despesa['valor'], 2, ',', '.'
            ); ?>
        </td>

        <td>
            <?php echo date(
                'd/m/Y',
                strtotime($despesa['data_despesa'])
            ); ?>
        </td>

        <td><?php echo $despesa['status']; ?></td>

        <td>

            <?php if ($despesa['status'] == 'Pendente') { ?>

                <a href="despesas.php?pagar=<?php echo $despesa['id']; ?>">
                    Marcar como paga
                </a>

            <?php } ?>

            |

            <a href="despesas.php?excluir=<?php echo $despesa['id']; ?>"
               onclick="return confirm('Deseja excluir esta despesa?')">
                Excluir
            </a>

        </td>

    </tr>

    <?php } ?>

</table>

</body>
</html>

<?php
mysqli_close($conexao);
?>