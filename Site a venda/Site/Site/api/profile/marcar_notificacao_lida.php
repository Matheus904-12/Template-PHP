<?php
session_start();
require_once '../../../adminView/config/dbconnect.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar se o ID da notificação foi fornecido
if (!isset($_POST['notificacao_id']) || empty($_POST['notificacao_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID da notificação não fornecido']);
    exit;
}

$notificacaoId = (int) $_POST['notificacao_id'];
$userId = $_SESSION['user_id'];

// Atualizar a notificação como lida
$query = "UPDATE notificacoes SET lida = 1 WHERE id = ? AND usuario_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $notificacaoId, $userId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Notificação marcada como lida']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar notificação: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>