
<?php

require_once "conexao.php";
require_once "sessao.php";

if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $sql = "SELECT * FROM usuarios
            WHERE email = :email AND ativo = 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => $email]);

    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario['senha'])) {

        session_regenerate_id(true);

        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];
        $_SESSION['ultimo_acesso'] = time();

        header("Location: index.php");
        exit;

    } else {

        $erro = "E-mail ou senha incorretos.";

    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Login - Patas & Cia</title>
</head>

<body>

    <h2>Clínica Veterinária Patas & Cia</h2>
    <h3>Entrar no sistema</h3>

    <?php if (isset($_GET['erro']) && $_GET['erro'] === 'expirada'): ?>
        <p>Sua sessão expirou por inatividade. Entre novamente.</p>
    <?php endif; ?>

    <p style="color:red;">
        <?= htmlspecialchars($erro) ?>
    </p>

    <form method="POST">

        <label>E-mail:</label><br>
        <input type="email" name="email" required>
        <br><br>

        <label>Senha:</label><br>
        <input type="password" name="senha" required>
        <br><br>

        <button type="submit">Entrar</button>

    </form>

</body>
</html>
