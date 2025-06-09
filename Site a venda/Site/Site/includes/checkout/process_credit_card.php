<?php
session_start();
require_once __DIR__ . '../../../config/dbconnect.php'; // Conexão com o banco

header('Content-Type: application/json');

try {
    // Recebe os dados do checkout.js
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Dados de entrada inválidos');
    }

    // Validação dos campos obrigatórios
    $requiredFields = ['order_id', 'amount', 'installments'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Campo '$field' é obrigatório");
        }
    }

    $orderId = $input['order_id'];
    $amount = floatval($input['amount']);
    $installments = intval($input['installments']);
    $email = $input['cardholder_email'] ?? '';
    $cpf = $input['identification_number'] ?? '';
    $savedCardId = $input['saved_card_id'] ?? '';
    $saveCard = isset($input['save_card']) && $input['save_card'] ? 1 : 0;

    // Validações adicionais
    if ($amount <= 0) {
        throw new Exception('Valor do pedido inválido');
    }
    if ($installments < 1 || $installments > 12) {
        throw new Exception('Número de parcelas inválido (1 a 12)');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('E-mail inválido');
    }
    if (!preg_match('/^\d{11}$/', $cpf)) {
        throw new Exception('CPF inválido');
    }

    // Configurações da Cielo
    $merchantId = 'SEU_MERCHANT_ID'; // Substitua pelo Merchant ID
    $merchantSecret = 'SEU_MERCHANT_SECRET'; // Substitua pelo Merchant Secret
    $apiUrl = 'https://apisandbox.cieloecommerce.cielo.com.br/1/sales'; // Sandbox, mude para produção após testes

    // Preparar dados do pagamento
    $paymentData = [
        'MerchantOrderId' => $orderId,
        'Customer' => [
            'Name' => 'Cliente',
            'Email' => $email,
            'Identity' => $cpf,
            'IdentityType' => 'CPF'
        ],
        'Payment' => [
            'Type' => 'CreditCard',
            'Amount' => (int)($amount * 100), // Cielo usa centavos
            'Installments' => $installments,
            'Capture' => true,
            'CreditCard' => []
        ]
    ];

    // Configura cartão (novo ou salvo)
    if ($savedCardId) {
        // Cartão salvo: recuperar da tabela user_cards
        $stmt = $conn->prepare("SELECT card_token, card_brand, card_last4 FROM user_cards WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $savedCardId, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $cardData = $result->fetch_assoc();
        $stmt->close();

        if (!$cardData) {
            throw new Exception('Cartão salvo não encontrado');
        }

        $paymentData['Payment']['CreditCard'] = [
            'CardToken' => $cardData['card_token'],
            'SecurityCode' => $input['card_cvv'],
            'Brand' => $cardData['card_brand']
        ];
    } else {
        // Novo cartão: usar token gerado pelo cielo.js
        if (!isset($input['card_token']) || !isset($input['card_brand'])) {
            throw new Exception('Token ou bandeira do cartão não fornecidos');
        }

        $paymentData['Payment']['CreditCard'] = [
            'CardToken' => $input['card_token'],
            'SecurityCode' => $input['card_cvv'],
            'Brand' => $input['card_brand']
        ];

        // Salvar cartão, se solicitado
        if ($saveCard) {
            $cardLast4 = substr($input['card_number'], -4);
            $stmt = $conn->prepare("INSERT INTO user_cards (user_id, card_token, card_last4, card_brand, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param('isss', $_SESSION['user_id'], $input['card_token'], $cardLast4, $input['card_brand']);
            if (!$stmt->execute()) {
                throw new Exception('Erro ao salvar cartão: ' . $stmt->error);
            }
            $stmt->close();
        }
    }

    // Enviar transação para a Cielo via curl
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "MerchantId: $merchantId",
        "MerchantKey: $merchantSecret"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Processar resposta
    $responseData = json_decode($response, true);
    if ($httpCode !== 201 || !isset($responseData['Payment']['PaymentId'])) {
        $errorMessage = $responseData['Message'] ?? ($responseData['Payment']['ReturnMessage'] ?? 'Erro desconhecido');
        throw new Exception('Falha na transação: ' . $errorMessage);
    }

    $paymentId = $responseData['Payment']['PaymentId'];

    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'payment_id' => $paymentId
    ]);

} catch (Exception $e) {
    // Log do erro
    $logMessage = '[' . date('Y-m-d H:i:s') . '] Erro ao processar pagamento: ' . $e->getMessage() . PHP_EOL;
    file_put_contents(__DIR__ . '/../../logs/payments_' . date('Y-m') . '.log', $logMessage, FILE_APPEND);

    // Resposta de erro
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>