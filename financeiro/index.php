<?php
require_once "proteger.php";
?>
<?php
require_once "../proteger_financeiro.php";
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Patas & Cia</title>
</head>

<body>

    <h1>Bem-vindo ao Patas & Cia!</h1>

    <p>
        Olá, <?= htmlspecialchars($_SESSION['usuario_nome']) ?>!
    </p>

    <a href="logout.php">Sair da conta</a>

</body>
</html>