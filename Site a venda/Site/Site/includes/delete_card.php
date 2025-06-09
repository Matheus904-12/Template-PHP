<?php
require_once '../../../adminView/config/dbconnect.php';
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar se o ID do cartão foi enviado
if (!isset($_POST['card_id']) || empty($_POST['card_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'ID do cartão não fornecido']);
    exit;
}

$userId = $_SESSION['user_id'];
$cardId = $_POST['card_id'];

// Verificar se o cartão pertence ao usuário
$checkQuery = "SELECT id FROM user_cards WHERE id = ? AND user_id = ?";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bind_param("ii", $cardId, $userId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['success' => false, 'message' => 'Cartão não encontrado ou não pertence ao usuário']);
    exit;
}

// Deletar o cartão
$deleteQuery = "DELETE FROM user_cards WHERE id = ? AND user_id = ?";
$deleteStmt = $conn->prepare($deleteQuery);
$deleteStmt->bind_param("ii", $cardId, $userId);

if ($deleteStmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Erro ao deletar cartão']);
}

$deleteStmt->close();
$conn->close();
?>