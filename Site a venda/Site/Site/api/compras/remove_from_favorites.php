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
if (!isset($data['favorite_id']) || !is_numeric($data['favorite_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID do favorito inválido']);
    exit();
}

$user_id = $_SESSION['user_id'];
$favorite_id = $data['favorite_id'];

try {
    $result = removeFromFavorites($user_id, $favorite_id);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}