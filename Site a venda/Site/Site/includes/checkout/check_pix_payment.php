<?php
/**
 * Verificar status de pagamento PIX
 * 
 * Este script verifica o status atual de um pagamento PIX com base no ID do pedido
 * e retorna o resultado em formato JSON.
 * 
 * @return JSON {"status": "paid|pending|expired", "message": "mensagem descritiva"}
 */

// Configurações iniciais
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

// Função para registrar logs
function logError($message) {
    $logFile = __DIR__ . '/../../logs/pix_payment.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    
    // Verificar se o diretório de logs existe
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Escrever no arquivo de log
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Função para retornar respostas JSON
function jsonResponse($status, $message = '', $data = []) {
    $response = [
        'status' => $status,
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    echo json_encode($response);
    exit;
}

// Verificar se o ID do pedido foi enviado
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    jsonResponse('error', 'ID do pedido não fornecido');
}

$orderId = $_GET['order_id'];

// Validar formato do ID do pedido (prevenção básica de injeção)
if (!preg_match('/^[A-Za-z0-9_-]+$/', $orderId)) {
    jsonResponse('error', 'Formato de ID inválido');
}

try {
    // Conectar ao banco de dados
    require_once '../../../adminView/config/dbconnect.php';
    
    // Se o arquivo de configuração do banco de dados não seguir esse padrão,
    // use o código abaixo para estabelecer a conexão diretamente:
    /*
    $host = 'localhost';
    $dbname = 'goldlar_db'; // Ajuste para o nome correto do seu banco
    $username = 'root';     // Ajuste para seu usuário
    $password = '';        // Ajuste para sua senha
    
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    */
    
    // Consultar status do pedido
    $query = "SELECT status, payment_status, created_at FROM orders WHERE order_id = :order_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar se o pedido existe
    if (!$order) {
        // Tentar buscar por um número de pedido alternativo
        $queryAlt = "SELECT status, payment_status, created_at FROM orders WHERE id = :order_id OR reference = :order_id LIMIT 1";
        $stmtAlt = $db->prepare($queryAlt);
        $stmtAlt->bindParam(':order_id', $orderId);
        $stmtAlt->execute();
        
        $order = $stmtAlt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            logError("Pedido não encontrado: $orderId");
            jsonResponse('error', 'Pedido não encontrado');
        }
    }
    
    // Verificar status de pagamento do pedido
    if ($order['payment_status'] === 'paid' || $order['payment_status'] === 'approved') {
        // Pagamento confirmado
        jsonResponse('paid', 'Pagamento confirmado com sucesso');
    } else if ($order['payment_status'] === 'expired' || $order['payment_status'] === 'cancelled') {
        // Pagamento expirado ou cancelado
        jsonResponse('expired', 'Pagamento expirado ou cancelado');
    } else {
        // Verificar tempo limite para PIX (30 minutos)
        $createdAt = strtotime($order['created_at']);
        $now = time();
        $timeDiff = $now - $createdAt;
        
        // Se passaram mais de 30 minutos (1800 segundos)
        if ($timeDiff > 1800) {
            // Atualizar status para expirado no banco
            $updateQuery = "UPDATE orders SET payment_status = 'expired', status = 'cancelled' WHERE order_id = :order_id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':order_id', $orderId);
            $updateStmt->execute();
            
            logError("Pagamento PIX expirado por tempo: $orderId");
            jsonResponse('expired', 'Tempo para pagamento expirado');
        } else {
            // Consultar API de pagamento externa para verificar status atual
            // Simulando consulta a uma API externa de pagamento
            $paymentResult = checkExternalPaymentStatus($orderId);
            
            if ($paymentResult['status'] === 'paid') {
                // Atualizar status no banco para pago
                $updateQuery = "UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE order_id = :order_id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':order_id', $orderId);
                $updateStmt->execute();
                
                jsonResponse('paid', 'Pagamento confirmado com sucesso');
            } else {
                // Pagamento ainda pendente
                jsonResponse('pending', 'Aguardando confirmação de pagamento');
            }
        }
    }
} catch (PDOException $e) {
    logError("Erro de banco de dados: " . $e->getMessage());
    jsonResponse('error', 'Erro ao verificar pagamento');
} catch (Exception $e) {
    logError("Erro geral: " . $e->getMessage());
    jsonResponse('error', 'Erro ao processar a solicitação');
}

/**
 * Simula consulta a uma API externa de pagamento
 * Na implementação real, você substituiria isso pela chamada real à sua API de pagamento
 * 
 * @param string $orderId ID do pedido
 * @return array Resultado da consulta
 */
function checkExternalPaymentStatus($orderId) {
    // Simulação: 10% de chance de o pagamento ter sido confirmado
    // Em um ambiente real, isso seria substituído pela consulta à API do seu gateway de pagamento
    $random = mt_rand(1, 100);
    
    // Verificar se existe um arquivo de simulação para este pedido
    $simulationFile = __DIR__ . "/../../temp/pix_simulation_{$orderId}.txt";
    
    if (file_exists($simulationFile)) {
        $simulationData = file_get_contents($simulationFile);
        if (strpos($simulationData, 'PAID') !== false) {
            return ['status' => 'paid'];
        }
    }
    
    // Probabilidade de 10% de pagamento ser confirmado a cada verificação
    if ($random <= 10) {
        // Para fins de simulação, vamos criar um arquivo para manter este pedido como pago
        file_put_contents($simulationFile, "PAID " . date('Y-m-d H:i:s'));
        return ['status' => 'paid'];
    }
    
    return ['status' => 'pending'];
}
?>