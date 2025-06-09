<?php
// api/merge_session_cart.php
session_start();
require_once('../../../adminView/config/dbconnect.php');
header('Content-Type: application/json');

// Esta função é chamada quando um usuário faz login e tem itens no carrinho de sessão
if (isset($_SESSION['user_id']) && isset($_SESSION['session_cart']) && !empty($_SESSION['session_cart'])) {
    $userId = $_SESSION['user_id'];
    $sessionCart = $_SESSION['session_cart'];
    
    foreach ($sessionCart as $item) {
        $productId = $item['product_id'];
        $quantity = $item['quantity'];
        
        // Verificar se o produto já existe no carrinho do usuário
        $stmt = $conn->prepare("SELECT id, quantity FROM carrinho WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $userId, $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Atualizar quantidade existente
            $row = $result->fetch_assoc();
            $newQuantity = $row['quantity'] + $quantity;
            
            $updateStmt = $conn->prepare("UPDATE carrinho SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->bind_param("ii", $newQuantity, $row['id']);
            $updateStmt->execute();
        } else {
            // Inserir novo item
            $insertStmt = $conn->prepare("INSERT INTO carrinho (user_id, product_id, quantity, created_at, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $insertStmt->bind_param("iii", $userId, $productId, $quantity);
            $insertStmt->execute();
        }
    }
    
    // Limpar o carrinho da sessão após a mesclagem
    unset($_SESSION['session_cart']);
    
    // Redirecionar ou retornar resposta JSON
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Carrinho mesclado com sucesso']);
} else {
    // Retornar erro se dados necessários não estiverem disponíveis
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Dados insuficientes para mesclar carrinho']);
}
?>