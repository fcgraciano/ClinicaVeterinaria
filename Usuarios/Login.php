<?php
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/autenticacao.php';

if (usuario_logado()) {
    header('Location: ../inicio/index.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $senha_login = isset($_POST['senha']) ? $_POST['senha'] : '';
    $consulta = mysqli_prepare($conexao, 'SELECT id, nome, senha_hash FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1');
    mysqli_stmt_bind_param($consulta, 's', $email);
    mysqli_stmt_execute($consulta);
    mysqli_stmt_bind_result($consulta, $id, $nome, $senha_hash);
    $encontrado = mysqli_stmt_fetch($consulta);
    mysqli_stmt_close($consulta);

    if ($encontrado && password_verify($senha_login, $senha_hash)) {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $id;
        $_SESSION['usuario_nome'] = $nome;
        $_SESSION['roles'] = array();
        $consulta_roles = mysqli_prepare($conexao, 'SELECT g.role FROM grupos_usuario g INNER JOIN usuario_grupos ug ON ug.grupo_id = g.id WHERE ug.usuario_id = ?');
        mysqli_stmt_bind_param($consulta_roles, 'i', $id);
        mysqli_stmt_execute($consulta_roles);
        mysqli_stmt_bind_result($consulta_roles, $role);
        while (mysqli_stmt_fetch($consulta_roles)) {
            $_SESSION['roles'][] = $role;
        }
        mysqli_stmt_close($consulta_roles);
        header('Location: ../inicio/index.php');
        exit;
    }
    $erro = 'E-mail ou senha inválidos.';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar — Patas &amp; Cia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{min-height:100vh;background:#f6f8f7;display:grid;place-items:center;font-family:Arial,sans-serif}.login-card{width:min(100% - 2rem,420px);background:#fff;padding:2.5rem;border-radius:15px;box-shadow:0 10px 35px #0f766e26;border-top:6px solid #0f766e}.login-card h1{color:#0f766e}.btn-login{background-color:#0f766e;border-color:#0f766e}.btn-login:hover,.btn-login:focus{background-color:#0b5f59;border-color:#0b5f59}</style>
</head>
<body>
<main class="login-card">
    <h1 class="h3 text-center">Patas &amp; Cia</h1>
    <p class="text-center text-muted">Entre com sua conta para continuar</p>
    <?php if ($erro !== ''): ?><div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <form method="post" action="" autocomplete="on">
        <div class="mb-3"><label class="form-label" for="email">E-mail</label><input class="form-control" id="email" name="email" type="email" maxlength="190" required autofocus></div>
        <div class="mb-4"><label class="form-label" for="senha">Senha</label><input class="form-control" id="senha" name="senha" type="password" required></div>
        <button class="btn btn-login text-white w-100" type="submit">Entrar</button>
    </form>
    <p class="small text-center mt-3 mb-0"><a href="PrimeiroAcesso.php">Configurar primeiro administrador</a></p>
</main>
</body>
</html>
