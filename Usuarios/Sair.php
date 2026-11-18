<?php
require_once __DIR__ . '/../includes/autenticacao.php';
encerrar_sessao();
header('Location: Login.php');
exit;
