<?php
// This file will transfer data from session tables to user tables after login
session_start();
require_once('../../../adminView/config/dbconnect.php'); // Include your database connection file
header('Content-Type: application/json');

// Check if a user has just logged in
if (isset($_SESSION['just_logged_in']) && $_SESSION['just_logged_in'] === true) {
    // Get the user ID and session ID
    $userId = $_SESSION['user_id'];
    $sessionId = session_id();
    
    // Transfer cart data
    transferCartData($userId, $sessionId);
    
    // Transfer favorites data
    transferFavoritesData($userId, $sessionId);
    
    // Reset the just_logged_in flag
    $_SESSION['just_logged_in'] = false;
}

/**
 * Transfer cart data from session to user account
 */
function transferCartData($userId, $sessionId) {
    global $conn;
    
    try {
        // Get cart items from session
        $stmt = $conn->prepare("SELECT product_id, quantity FROM session_carrinho WHERE session_id = ?");
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Prepare statement for checking if product exists in user cart
        $checkStmt = $conn->prepare("SELECT id, quantity FROM user_cart WHERE user_id = ? AND product_id = ?");
        
        // Prepare statements for updating and inserting
        $updateStmt = $conn->prepare("UPDATE user_cart SET quantity = ? WHERE id = ?");
        $insertStmt = $conn->prepare("INSERT INTO user_cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        
        // Process each session cart item
        while ($row = $result->fetch_assoc()) {
            $productId = $row['product_id'];
            $quantity = $row['quantity'];
            
            // Check if product already exists in user cart
            $checkStmt->bind_param("ii", $userId, $productId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                // Update quantity if product already in cart
                $checkRow = $checkResult->fetch_assoc();
                $newQuantity = $checkRow['quantity'] + $quantity;
                
                $updateStmt->bind_param("ii", $newQuantity, $checkRow['id']);
                $updateStmt->execute();
            } else {
                // Insert new product to user cart
                $insertStmt->bind_param("iii", $userId, $productId, $quantity);
                $insertStmt->execute();
            }
        }
        
        // Delete session cart data after transfer
        $deleteStmt = $conn->prepare("DELETE FROM session_carrinho WHERE session_id = ?");
        $deleteStmt->bind_param("s", $sessionId);
        $deleteStmt->execute();
        
    } catch (Exception $e) {
        // Log error but continue execution
        error_log("Error transferring cart data: " . $e->getMessage());
    }
}

/**
 * Transfer favorites data from session to user account
 */
function transferFavoritesData($userId, $sessionId) {
    global $conn;
    
    try {
        // Get favorite items from session
        $stmt = $conn->prepare("SELECT product_id FROM session_favoritos WHERE session_id = ?");
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Prepare statement for checking if product exists in user favorites
        $checkStmt = $conn->prepare("SELECT id FROM user_favorites WHERE user_id = ? AND product_id = ?");
        
        // Prepare statement for inserting
        $insertStmt = $conn->prepare("INSERT INTO user_favorites (user_id, product_id) VALUES (?, ?)");
        
        // Process each session favorite item
        while ($row = $result->fetch_assoc()) {
            $productId = $row['product_id'];
            
            // Check if product already exists in user favorites
            $checkStmt->bind_param("ii", $userId, $productId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows === 0) {
                // Insert new product to user favorites if not already exists
                $insertStmt->bind_param("ii", $userId, $productId);
                $insertStmt->execute();
            }
        }
        
        // Delete session favorites data after transfer
        $deleteStmt = $conn->prepare("DELETE FROM session_favoritos WHERE session_id = ?");
        $deleteStmt->bind_param("s", $sessionId);
        $deleteStmt->execute();
        
    } catch (Exception $e) {
        // Log error but continue execution
        error_log("Error transferring favorites data: " . $e->getMessage());
    }
}
?>