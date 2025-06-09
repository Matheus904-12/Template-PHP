<?php
// includes/atualizar_dados.php
session_start();
require_once '../../../adminView/config/dbconnect.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    echo "Você precisa estar logado para atualizar seus dados.";
    exit;
}

// Processar apenas solicitações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $cep = trim($_POST['cep'] ?? '');
    $numeroCasa = trim($_POST['numero_casa'] ?? '');

    // Validar entrada
    if (empty($nome) || empty($email)) {
        echo "Nome e email são obrigatórios.";
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Por favor, forneça um email válido.";
        exit;
    }

    // Verificar se o email já está em uso por outro usuário
    $query = "SELECT id FROM usuarios WHERE email = ? AND id != ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $email, $userId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "Este email já está sendo usado por outro usuário.";
        $stmt->close();
        exit;
    }

    // Atualizar os dados do usuário
    $query = "UPDATE usuarios SET name = ?, email = ?, telefone = ?, endereco = ?, cep = ?, numero_casa = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssssi", $nome, $email, $telefone, $endereco, $cep, $numeroCasa, $userId);

    if ($stmt->execute()) {
        // Atualizar os dados na sessão
        $_SESSION['username'] = $nome;
        $_SESSION['user_email'] = $email;
        
        echo "Dados atualizados com sucesso!";
    } else {
        echo "Erro ao atualizar os dados: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "Método de requisição inválido.";
}

$conn->close();
?>