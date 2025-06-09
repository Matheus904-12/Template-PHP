<?php
/**
 * Delete a saved credit card
 * 
 * This script handles deleting saved credit cards securely
 * by validating the request and removing the card from the database.
 */

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

// Get the user ID from session
$userId = $_SESSION['user_id'];

// Check if card_id was provided
if (!isset($_POST['card_id']) || empty($_POST['card_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID do cartão não fornecido'
    ]);
    exit;
}

// Get the card ID
$cardId = $_POST['card_id'];

// Include database connection
require_once '../../../adminView/config/dbconnect.php';

try {
    // Prepare the delete statement
    $stmt = $conn->prepare("DELETE FROM saved_cards WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $cardId, $userId);
    
    // Execute the statement
    $result = $stmt->execute();
    
    if ($result && $stmt->affected_rows > 0) {
        // Card was successfully deleted
        echo json_encode([
            'success' => true,
            'message' => 'Cartão excluído com sucesso'
        ]);
    } else {
        // No card was deleted (might not exist or doesn't belong to the user)
        echo json_encode([
            'success' => false,
            'message' => 'Cartão não encontrado ou você não tem permissão para excluí-lo'
        ]);
    }
    
    // Close statement
    $stmt->close();
    
} catch (Exception $e) {
    // Handle any errors
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir o cartão: ' . $e->getMessage()
    ]);
}

// Close connection
$conn->close();
?>