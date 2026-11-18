<?php
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/../includes/autenticacao.php';

$total_resultado = mysqli_query($conexao, 'SELECT COUNT(*) AS total FROM usuarios');
$total_usuarios = (int) mysqli_fetch_assoc($total_resultado)['total'];
$erro = '';
if ($total_usuarios > 0) {
    http_response_code(403);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim(isset($_POST['nome']) ? $_POST['nome'] : '');
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $senha_nova = isset($_POST['senha']) ? $_POST['senha'] : '';
    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha_nova) < 10) {
        $erro = 'Informe nome, e-mail válido e uma senha com pelo menos 10 caracteres.';
    } else {
        mysqli_begin_transaction($conexao);
        $hash = password_hash($senha_nova, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conexao, 'INSERT INTO usuarios (nome, email, senha_hash) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'sss', $nome, $email, $hash);
        $ok = mysqli_stmt_execute($stmt);
        $id_usuario = mysqli_insert_id($conexao);
        mysqli_stmt_close($stmt);
        if ($ok) {
            $id_grupo = 0;
            $stmt = mysqli_prepare($conexao, "SELECT id FROM grupos_usuario WHERE role = 'admin' LIMIT 1");
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $id_grupo);
            $grupo_existe = mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            if ($grupo_existe) {
                $stmt = mysqli_prepare($conexao, 'INSERT INTO usuario_grupos (usuario_id, grupo_id) VALUES (?, ?)');
                mysqli_stmt_bind_param($stmt, 'ii', $id_usuario, $id_grupo);
                $ok = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            } else {
                $ok = false;
            }
        }
        if ($ok) {
            mysqli_commit($conexao);
            header('Location: Login.php?criado=1');
            exit;
        }
        mysqli_rollback($conexao);
        $erro = 'Não foi possível criar o administrador. Verifique se o e-mail já está cadastrado e se instalou banco/01_autenticacao.sql.';
    }
}
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Primeiro acesso — Patas &amp; Cia</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-5" style="max-width:560px"><div class="card shadow-sm"><div class="card-body p-4">
<h1 class="h3">Configurar administrador</h1>
<?php if ($total_usuarios > 0): ?><div class="alert alert-warning">A configuração inicial já foi utilizada. Entre com uma conta existente.</div><a href="Login.php" class="btn btn-primary">Ir para o login</a>
<?php else: ?>
<p class="text-muted">Esta página só funciona enquanto não houver usuários cadastrados. Escolha uma senha com pelo menos 10 caracteres.</p>
<?php if ($erro !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<form method="post"><label class="form-label" for="nome">Nome</label><input class="form-control mb-3" id="nome" name="nome" maxlength="120" required><label class="form-label" for="email">E-mail</label><input class="form-control mb-3" id="email" name="email" type="email" maxlength="190" required><label class="form-label" for="senha">Senha</label><input class="form-control mb-3" id="senha" name="senha" type="password" minlength="10" required><button class="btn btn-primary" type="submit">Criar administrador</button> <a class="btn btn-link" href="Login.php">Voltar</a></form>
<?php endif; ?></div></div></main></body></html>
