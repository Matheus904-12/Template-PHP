<?php
header('Content-Type: application/json');
require_once('../../../adminView/config/dbconnect.php');

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário não identificado']);
    exit;
}

$stmt = $conn->prepare("
    SELECT p.id, p.nome as name, p.preco as price, p.imagem as image, uc.quantity 
    FROM user_cart uc
    JOIN produtos p ON uc.product_id = p.id
    WHERE uc.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

echo json_encode([
    'status' => 'success',
    'items' => $items
]);
exit;