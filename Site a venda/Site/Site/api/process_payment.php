<?php
/**
 * API de Processamento de Pagamentos
 * 
 * Este arquivo processa diferentes métodos de pagamento (PIX, Cartão de Crédito e outros)
 * e retorna as informações necessárias para continuar o fluxo de checkout
 */

// Iniciar sessão para manter os dados do usuário
session_start();
try {
// Configurações
$paymentType = $_POST['payment_type'] ?? '';
$sessionId = $_POST['session_id'] ?? '';

if ($paymentType === 'pix') {
    // Process PIX payment logic
    // ...
    
    // Example of generating a PIX QR code (replace with your actual logic)
    $orderId = "ORDER" . time();
    $pixQrcode = "https://example.com/pix-qrcode/$orderId.png"; 
    $pixCode = "PIXCODE" . rand(10000, 99999);
    
    // Send success response
    $response = [
        'status' => 'success',
        'order_id' => $orderId,
        'pix_qrcode' => $pixQrcode,
        'pix_code' => $pixCode
    ];
} else {
    // Handle other payment types
    $response = [
        'status' => 'error',
        'message' => 'Tipo de pagamento não suportado'
    ];
}

// Discard any output generated before this point
ob_end_clean();

// Send the JSON response
echo json_encode($response);

} catch (Exception $e) {
// Discard any buffered output
if (ob_get_length()) ob_end_clean();

// Send error as JSON
header('Content-Type: application/json');
echo json_encode([
    'status' => 'error',
    'message' => 'Erro interno no servidor',
    'error_details' => $e->getMessage()
]);
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir arquivos necessários
require_once '../../adminView/config/dbconnect.php';
require_once '../../adminView/config/getDbConnection.php';
require_once '../../vendor/autoload.php';
require_once '../includes/auth.php';
require_once '../includes/payment_gateway.php';
require_once '../includes/order_functions.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Verificar se a requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Método não permitido'
    ]);
    exit;
}

// Verificar autenticação do usuário
$user_id = getCurrentUserId();
if (!$user_id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

// Obter e validar dados essenciais
$payment_type = $_POST['payment_type'] ?? '';
$session_id = $_POST['session_id'] ?? '';

if (empty($payment_type) || empty($session_id)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Dados de pagamento insuficientes'
    ]);
    exit;
}

// Recuperar informações do carrinho
$cart = getCartItems($user_id);
if (empty($cart['items'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Carrinho vazio'
    ]);
    exit;
}

// Obter dados de endereço e contato
$shipping_data = [
    'name' => $_POST['shipping_name'] ?? '',
    'email' => $_POST['shipping_email'] ?? '',
    'address' => $_POST['shipping_address'] ?? '',
    'number' => $_POST['shipping_number'] ?? '',
    'complement' => $_POST['shipping_complement'] ?? '',
    'cep' => $_POST['shipping_cep'] ?? '',
    'city' => $_POST['shipping_city'] ?? '',
    'state' => $_POST['shipping_state'] ?? '',
    'phone' => $_POST['shipping_phone'] ?? '',
];

// Validar dados de envio
foreach ($shipping_data as $key => $value) {
    if (empty($value) && $key != 'complement') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Dados de envio incompletos. Preencha todos os campos obrigatórios.'
        ]);
        exit;
    }
}

// Calcular valor total do pedido com frete
$order_total = calculateOrderTotal($cart);
$shipping_cost = calculateShippingCost($_POST['shipping_cep'] ?? '');
$total_amount = $order_total + $shipping_cost;

try {
    // Iniciar transação no banco de dados
    $db = getDbConnection();
    $db->beginTransaction();

    // Criar pedido no banco de dados
    $order_id = createOrder($user_id, $cart, $shipping_data, $total_amount, $payment_type);
    
    if (!$order_id) {
        throw new Exception("Falha ao criar o pedido");
    }

    // Log da criação do pedido
    logOrderActivity($order_id, "Pedido criado com método de pagamento: $payment_type");
    
    // Processar pagamento com base no método selecionado
    switch ($payment_type) {
        case 'pix':
            $result = processPIXPayment($order_id, $total_amount, $user_id);
            break;
            
        case 'credit_card':
            $card_data = [
                'number' => $_POST['card_number'] ?? '',
                'name' => $_POST['card_name'] ?? '',
                'expiry' => $_POST['card_expiry'] ?? '',
                'cvv' => $_POST['card_cvv'] ?? '',
                'save_card' => isset($_POST['save_card']) && $_POST['save_card'] == '1',
            ];
            
            // Verificar se está usando cartão salvo
            $saved_card_id = $_POST['saved_card_id'] ?? null;
            
            if ($saved_card_id) {
                $result = processCreditCardPaymentWithSavedCard($order_id, $total_amount, $user_id, $saved_card_id);
            } else {
                $result = processCreditCardPayment($order_id, $total_amount, $user_id, $card_data);
            }
            break;
            
        case 'boleto':
            $result = processBoletoPayment($order_id, $total_amount, $user_id, $shipping_data);
            break;
            
        default:
            throw new Exception("Método de pagamento não suportado");
    }
    
    // Finalizar a transação
    $db->commit();
    
    // Limpar o carrinho após pedido bem-sucedido
    clearCart($user_id);
    
    // Enviar email de confirmação
    sendOrderConfirmationEmail($order_id, $user_id);
    
    // Retornar resposta com base no tipo de pagamento
    echo json_encode(array_merge([
        'status' => 'success',
        'order_id' => $order_id,
    ], $result));
    
} catch (Exception $e) {
    // Reverter transação em caso de erro
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Log de erro
    error_log("Erro no processamento do pagamento: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Não foi possível processar o pagamento: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
}

/**
 * Processa pagamento via PIX
 */
function processPIXPayment($order_id, $amount, $user_id) {
    // Integração com gateway de pagamento para gerar PIX
    $gateway = new PaymentGateway();
    
    $pix_data = [
        'order_id' => $order_id,
        'amount' => $amount,
        'description' => "Pedido #$order_id",
        'expiration' => 30, // minutos para expiração
        'customer_id' => $user_id
    ];
    
    $pix_response = $gateway->generatePIX($pix_data);
    
    if (!$pix_response['success']) {
        throw new Exception("Falha ao gerar PIX: " . $pix_response['message']);
    }
    
    // Atualizar pedido com informações do PIX
    updateOrderPaymentInfo($order_id, [
        'pix_code' => $pix_response['pix_code'],
        'pix_expiration' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
        'payment_status' => 'pending'
    ]);
    
    // Gerar QR Code usando a biblioteca PHP QR Code
    $qrcode_image_path = generatePIXQRCode($pix_response['pix_code'], $order_id);
    
    return [
        'pix_qrcode' => $qrcode_image_path,
        'pix_code' => $pix_response['pix_code'],
        'expiration_minutes' => 30
    ];
}

/**
 * Processa pagamento com cartão de crédito
 */
function processCreditCardPayment($order_id, $amount, $user_id, $card_data) {
    // Validar dados do cartão
    if (!validateCreditCardData($card_data)) {
        throw new Exception("Dados do cartão inválidos", 'invalid_card');
    }
    
    // Integração com gateway de pagamento
    $gateway = new PaymentGateway();
    
    $payment_data = [
        'order_id' => $order_id,
        'amount' => $amount,
        'description' => "Pedido #$order_id",
        'card' => [
            'number' => preg_replace('/\D/', '', $card_data['number']),
            'name' => $card_data['name'],
            'expiry_month' => substr($card_data['expiry'], 0, 2),
            'expiry_year' => '20' . substr($card_data['expiry'], 3, 2),
            'cvv' => $card_data['cvv']
        ],
        'customer_id' => $user_id
    ];
    
    $payment_response = $gateway->processCardPayment($payment_data);
    
    if (!$payment_response['success']) {
        // Mapear erros do gateway para códigos mais amigáveis
        $error_code = mapGatewayErrorToCode($payment_response['error_code']);
        throw new Exception($payment_response['message'], $error_code);
    }
    
    // Atualizar pedido com informações do pagamento
    updateOrderPaymentInfo($order_id, [
        'transaction_id' => $payment_response['transaction_id'],
        'payment_status' => $payment_response['status'],
        'last_4_digits' => substr(preg_replace('/\D/', '', $card_data['number']), -4)
    ]);
    
    // Salvar cartão se solicitado
    if ($card_data['save_card'] && $payment_response['status'] === 'approved') {
        saveCardForUser(
            $user_id, 
            $payment_response['card_token'], 
            substr(preg_replace('/\D/', '', $card_data['number']), -4),
            $card_data['name'],
            $payment_response['card_brand']
        );
    }
    
    return [
        'transaction_id' => $payment_response['transaction_id'],
        'status' => $payment_response['status'] === 'approved' ? 'success' : 'pending'
    ];
}

/**
 * Processa pagamento com cartão salvo
 */
function processCreditCardPaymentWithSavedCard($order_id, $amount, $user_id, $saved_card_id) {
    // Verificar se o cartão pertence ao usuário
    if (!verifyCardOwnership($user_id, $saved_card_id)) {
        throw new Exception("Cartão não pertence ao usuário");
    }
    
    // Obter token do cartão salvo
    $card_token = getSavedCardToken($saved_card_id);
    if (!$card_token) {
        throw new Exception("Cartão não encontrado");
    }
    
    // Integração com gateway de pagamento
    $gateway = new PaymentGateway();
    
    $payment_data = [
        'order_id' => $order_id,
        'amount' => $amount,
        'description' => "Pedido #$order_id",
        'card_token' => $card_token,
        'customer_id' => $user_id
    ];
    
    $payment_response = $gateway->processTokenizedPayment($payment_data);
    
    if (!$payment_response['success']) {
        $error_code = mapGatewayErrorToCode($payment_response['error_code']);
        throw new Exception($payment_response['message'], $error_code);
    }
    
    // Atualizar pedido com informações do pagamento
    updateOrderPaymentInfo($order_id, [
        'transaction_id' => $payment_response['transaction_id'],
        'payment_status' => $payment_response['status'],
        'card_id' => $saved_card_id
    ]);
    
    return [
        'transaction_id' => $payment_response['transaction_id'],
        'status' => $payment_response['status'] === 'approved' ? 'success' : 'pending'
    ];
}

/**
 * Processa pagamento via boleto
 */
function processBoletoPayment($order_id, $amount, $user_id, $customer_data) {
    // Integração com gateway de pagamento
    $gateway = new PaymentGateway();
    
    $boleto_data = [
        'order_id' => $order_id,
        'amount' => $amount,
        'description' => "Pedido #$order_id",
        'customer' => [
            'name' => $customer_data['name'],
            'email' => $customer_data['email'],
            'document' => $_POST['document'] ?? '', // CPF/CNPJ
            'address' => $customer_data['address'],
            'number' => $customer_data['number'],
            'zipcode' => $customer_data['cep'],
            'city' => $customer_data['city'],
            'state' => $customer_data['state'],
            'phone' => $customer_data['phone']
        ],
        'expiration_date' => date('Y-m-d', strtotime('+3 days'))
    ];
    
    $boleto_response = $gateway->generateBoleto($boleto_data);
    
    if (!$boleto_response['success']) {
        throw new Exception("Falha ao gerar boleto: " . $boleto_response['message']);
    }
    
    // Atualizar pedido com informações do boleto
    updateOrderPaymentInfo($order_id, [
        'boleto_url' => $boleto_response['boleto_url'],
        'boleto_barcode' => $boleto_response['barcode'],
        'boleto_expiration' => $boleto_data['expiration_date'],
        'payment_status' => 'pending'
    ]);
    
    return [
        'boleto_url' => $boleto_response['boleto_url'],
        'barcode' => $boleto_response['barcode'],
        'expiration_date' => $boleto_data['expiration_date']
    ];
}

/**
 * Gera QR Code para PIX
 */
function generatePIXQRCode($pix_code, $order_id) {
    $qr_folder = '../img/qrcodes/';
    if (!is_dir($qr_folder)) {
        mkdir($qr_folder, 0755, true);
    }

    $filename = $qr_folder . 'pix_' . $order_id . '_' . time() . '.png';

    $qrCode = new QrCode($pix_code);
    $writer = new PngWriter();
    $result = $writer->write($qrCode);
    $result->saveToFile($filename);

    return str_replace('../', '', $filename);
}

/**
 * Valida dados do cartão de crédito
 */
function validateCreditCardData($card_data) {
    // Verificar se todos os campos foram preenchidos
    if (empty($card_data['number']) || empty($card_data['name']) || 
        empty($card_data['expiry']) || empty($card_data['cvv'])) {
        return false;
    }
    
    // Limpar número do cartão
    $number = preg_replace('/\D/', '', $card_data['number']);
    
    // Verificar comprimento do número
    if (strlen($number) < 13 || strlen($number) > 19) {
        return false;
    }
    
    // Verificar formato da data de validade
    if (!preg_match('/^\d{2}\/\d{2}$/', $card_data['expiry'])) {
        return false;
    }
    
    // Verificar se o cartão não está expirado
    list($month, $year) = explode('/', $card_data['expiry']);
    $expiry_date = \DateTime::createFromFormat('my', $month . $year);
    $current_date = new \DateTime();
    
    if ($expiry_date < $current_date) {
        return false;
    }
    
    // Verificar CVV
    $cvv = preg_replace('/\D/', '', $card_data['cvv']);
    if (strlen($cvv) < 3 || strlen($cvv) > 4) {
        return false;
    }
    
    return true;
}

/**
 * Mapeia erros do gateway para códigos mais amigáveis
 */
function mapGatewayErrorToCode($gateway_error) {
    $error_map = [
        'invalid_card_number' => 'invalid_number',
        'invalid_expiry_date' => 'invalid_expiry',
        'invalid_security_code' => 'invalid_cvv',
        'card_declined' => 'card_declined',
        'insufficient_funds' => 'insufficient_funds',
        'expired_card' => 'expired_card',
        'processing_error' => 'processing_error'
    ];
    
    return $error_map[$gateway_error] ?? 'processing_error';
}