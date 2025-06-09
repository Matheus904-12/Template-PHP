<?php
header('Content-Type: application/json');
require_once('../../../adminView/config/dbconnect.php');
require_once('./../includes/compras/cart_functions.php');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

// Validar dados de entrada
if (!isset($data['cart_item_id']) || !is_numeric($data['cart_item_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID do item do carrinho inválido']);
    exit();
}

$user_id = $_SESSION['user_id'];
$cart_item_id = $data['cart_item_id'];

try {
    $result = removeFromCart($user_id, $cart_item_id);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}