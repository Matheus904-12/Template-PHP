<?php
include './config/dbconnect.php'; // Caminho corrigido

function getClientes() {
    global $conn;
    
    if (!$conn) {
        die("Erro na conexão com o banco de dados.");
    }

    $sql = "SELECT * FROM usuarios"; 
    $result = mysqli_query($conn, $sql);

    $clientes = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $clientes[] = $row;
    }
    return $clientes;
}
?>
