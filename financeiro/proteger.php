
<?php

require_once "sessao.php";

if (!isset($_SESSION['usuario_id'])) {

    header("Location: login.php");
    exit;

}

?>
