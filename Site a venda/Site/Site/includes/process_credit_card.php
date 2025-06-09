<?php
// includes/checkout/process_credit_card.php
header('Content-Type: application/json');
require_once '../../../adminView/config/dbconnect.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$userId = $_SESSION['user_id'];
$cardNumber = $input['card_number'] ?? '';
$cardName = $input['card_name'] ?? '';
$cardExpiry = $input['card_expiry'] ?? '';
$cardCvv = $input['card_cvv'] ?? '';
$saveCard = $input['save_card'] ?? false;
$savedCardId = $input['saved_card_id'] ?? '';
$orderTotal = $input['order_total'] ?? 0;
$installments = $input['installments'] ?? 1;

// Validação
if (empty($savedCardId)) {
    if (empty($cardNumber) || strlen(preg_replace('/\D/', '', $cardNumber)) < 13) {
        echo json_encode(['success' => false, 'message' => 'Número do cartão inválido']);
        exit;
    }
    if (empty($cardName)) {
        echo json_encode(['success' => false, 'message' => 'Nome no cartão é obrigatório']);
        exit;
    }
    if (empty($cardExpiry) || !preg_match('/^\d{2}\/\d{2}$/', $cardExpiry)) {
        echo json_encode(['success' => false, 'message' => 'Data de validade inválida']);
        exit;
    }
    if (empty($cardCvv) || strlen($cardCvv) < 3) {
        echo json_encode(['success' => false, 'message' => 'CVV inválido']);
        exit;
    }
}

if ($orderTotal <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valor do pedido inválido']);
    exit;
}

// Simular processamento do pagamento com cartão
// Em produção, aqui você integraria com uma gateway de pagamento
$paymentApproved = true; // Simulação de aprovação

if ($paymentApproved) {
    if ($saveCard && empty($savedCardId)) {
        $cardLast4 = substr(preg_replace('/\D/', '', $cardNumber), -4);
        $query = "INSERT INTO user_cards (user_id, card_last4, card_name, card_expiry) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isss", $userId, $cardLast4, $cardName, $cardExpiry);
        $stmt->execute();
    }

    echo json_encode(['success' => true, 'message' => 'Pagamento com cartão aprovado']);
} else {
    echo json_encode(['success' => false, 'message' => 'Pagamento com cartão recusado']);
}

$conn->close();
?>