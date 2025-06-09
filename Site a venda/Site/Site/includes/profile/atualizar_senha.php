<?php
// includes/atualizar_senha.php
session_start();
require_once '../../../adminView/config/dbconnect.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    echo "Você precisa estar logado para alterar a senha.";
    exit;
}

// Processar apenas solicitações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $senhaAtual = $_POST['senha_atual'];
    $novaSenha = $_POST['nova_senha'];
    $confirmarSenha = $_POST['confirmar_senha'];

    // Validar entrada
    if (empty($senhaAtual) || empty($novaSenha) || empty($confirmarSenha)) {
        echo "Todos os campos são obrigatórios.";
        exit;
    }

    if ($novaSenha !== $confirmarSenha) {
        echo "A nova senha e a confirmação não coincidem.";
        exit;
    }

    // Verificar comprimento da senha
    if (strlen($novaSenha) < 6) {
        echo "A nova senha deve ter pelo menos 6 caracteres.";
        exit;
    }

    // Buscar senha atual do usuário no banco de dados
    $query = "SELECT password FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($senhaHash);
        $stmt->fetch();

        // Verificar se a senha atual fornecida está correta
        if (password_verify($senhaAtual, $senhaHash)) {
            // Hash da nova senha
            $novaSenhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);

            // Atualizar a senha no banco de dados
            $query = "UPDATE usuarios SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $novaSenhaHash, $userId);

            if ($stmt->execute()) {
                echo "Senha atualizada com sucesso!";
            } else {
                echo "Erro ao atualizar a senha: " . $stmt->error;
            }
        } else {
            echo "Senha atual incorreta.";
        }
    } else {
        echo "Usuário não encontrado.";
    }

    $stmt->close();
} else {
    echo "Método de requisição inválido.";
}

$conn->close();
?>