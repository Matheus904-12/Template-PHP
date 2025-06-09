<?php
$servername = "goldlar_2025.mysql.dbaas.com.br";
$username = "goldlar_2025";
$password = "FNvVuWRZ#1";
$database = "goldlar_2025";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Erro na conexão com o banco (mysqli): " . $conn->connect_error);
} else {
    error_log("Conexão mysqli estabelecida com sucesso."); // Adicione esta linha
    // Conexão bem-sucedida
}

try {
    $usuario = 'goldlar_2025'; // Seu usuário do MySQL
    $senha = 'FNvVuWRZ#1'; // Sua senha do MySQL
    $banco = 'goldlar_2025'; // Nome do banco de dados
    $servidor = 'goldlar_2025.mysql.dbaas.com.br'; // Servidor do banco de dados

    $pdo = new PDO("mysql:dbname=$banco;host=$servidor;charset=utf8", $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Conexão PDO estabelecida com sucesso."); // Adicione esta linha
} catch (PDOException $e) {
    die("Erro ao conectar (PDO): " . $e->getMessage());
}
?>