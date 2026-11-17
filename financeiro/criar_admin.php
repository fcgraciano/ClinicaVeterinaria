
<?php

require_once "conexaoBD.php";

$nome = "Administrador";
$cpf = "000.000.000-00";
$email = "admin@patascia.com";
$senha = password_hash("Admin123!", PASSWORD_DEFAULT);
$tipo = "admin";

$sql = "INSERT INTO usuarios
        (nome, cpf, email, senha, tipo)
        VALUES (?, ?, ?, ?, ?)";

$stmt = $pdo->prepare($sql);

try {

    $stmt->execute([
        $nome,
        $cpf,
        $email,
        $senha,
        $tipo
    ]);

    echo "Administrador cadastrado com sucesso!";

} catch (PDOException $e) {

    echo "Erro ao cadastrar. Verifique se o usuário já existe.";

}

?>
