<?php
// Autenticação procedural compartilhada pelas páginas do sistema.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function usuario_logado()
{
    return isset($_SESSION['usuario_id']);
}

function exigir_login()
{
    if (!usuario_logado()) {
        header('Location: /ClinicaVeterinaria/Usuarios/Login.php');
        exit;
    }
}

function usuario_tem_role($roles)
{
    if (!usuario_logado()) {
        return false;
    }
    if (!is_array($roles)) {
        $roles = array($roles);
    }
    $roles_usuario = isset($_SESSION['roles']) ? $_SESSION['roles'] : array();
    return in_array('admin', $roles_usuario, true) || count(array_intersect($roles, $roles_usuario)) > 0;
}

function exigir_role($roles)
{
    exigir_login();
    if (!usuario_tem_role($roles)) {
        http_response_code(403);
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Acesso negado</title>';
        echo '<h1>Acesso negado</h1><p>Seu grupo não tem permissão para acessar esta página.</p>';
        echo '<p><a href="/ClinicaVeterinaria/inicio/index.php">Voltar ao início</a></p></html>';
        exit;
    }
}

function encerrar_sessao()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']);
    }
    session_destroy();
}
