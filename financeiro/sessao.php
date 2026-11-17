
<?php

if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'httponly' => true,
        'secure' => false, // true quando utilizar HTTPS
        'samesite' => 'Lax'
    ]);

    session_start();
}

$tempo_limite = 900; // 15 minutos

if (isset($_SESSION['ultimo_acesso'])) {

    if (time() - $_SESSION['ultimo_acesso'] > $tempo_limite) {

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header("Location: login.php?erro=expirada");
        exit;
    }
}

$_SESSION['ultimo_acesso'] = time();

?>
