<?php
// ===============================================
//  CONEXÃO COM O BANCO DE DADOS
//  Use nas páginas assim:
//      include '../includes/conexao.php';
//  Depois disso a variável $conexao já existe.
// ===============================================

$servidor = "localhost";
$usuario  = "root";
$senha    = "";
$banco    = "patas_db";

$conexao = mysqli_connect($servidor, $usuario, $senha, $banco);

if (!$conexao) {
    die("Erro ao conectar no banco: " . mysqli_connect_error());
}

// Para os acentos aparecerem certinho
mysqli_set_charset($conexao, "utf8mb4");
