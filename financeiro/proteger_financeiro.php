
<?php

require_once "proteger.php";

$tipo = $_SESSION['usuario_tipo'] ?? '';

if (!in_array($tipo, ['admin', 'financeiro'], true)) {

    http_response_code(403);

    exit("Acesso negado! Você não possui permissão para acessar o financeiro.");

}

?>
