<?php
session_start();
header('Content-Type: application/json');
require_once('../../../adminView/config/dbconnect.php');

// Configurar logs de erro
ini_set('display_errors', 0);
ini_set('log_errors', 1);


// Obter os dados JSON da requisição
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Log da requisição
error_log("Requisição recebida: " . print_r($data, true));

// Verificar se o JSON foi decodificado corretamente
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Erro ao decodificar JSON: " . json_last_error_msg());
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit;
}

// Verificar parâmetros obrigatórios
if (!isset($data['product_id']) || !isset($data['action'])) {
    error_log("Parâmetros obrigatórios ausentes: " . print_r($data, true));
    echo json_encode(['success' => false, 'message' => 'Parâmetros obrigatórios não fornecidos']);
    exit;
}

$productId = (int)$data['product_id'];
$quantity = isset($data['quantity']) ? (int)$data['quantity'] : 0;
$action = $data['action'];

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    error_log("Usuário não logado. Session data: " . print_r($_SESSION, true));
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
error_log("Processando carrinho para user_id: $userId, product_id: $productId, action: $action");

try {
    $conn->begin_transaction();

    switch ($action) {
        case 'add':
            // Verificar se o produto já existe no carrinho
            $stmt = $conn->prepare("SELECT id, quantity FROM carrinho WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param("ii", $userId, $productId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $newQuantity = $row['quantity'] + $quantity;
                error_log("Produto $productId encontrado no carrinho. Atualizando quantidade para $newQuantity");

                // Atualizar quantidade
                $updateStmt = $conn->prepare("UPDATE carrinho SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $updateStmt->bind_param("ii", $newQuantity, $row['id']);
                $updateStmt->execute();
            } else {
                error_log("Produto $productId não encontrado. Inserindo novo item com quantidade $quantity");
                // Inserir novo item
                $insertStmt = $conn->prepare("INSERT INTO carrinho (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $insertStmt->bind_param("iii", $userId, $productId, $quantity);
                $insertStmt->execute();
            }
            break;

        case 'update':
            error_log("Atualizando quantidade do produto $productId para $quantity");
            $stmt = $conn->prepare("UPDATE carrinho SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param("iii", $quantity, $userId, $productId);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                error_log("Produto $productId não encontrado. Inserindo novo item com quantidade $quantity");
                $insertStmt = $conn->prepare("INSERT INTO carrinho (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $insertStmt->bind_param("iii", $userId, $productId, $quantity);
                $insertStmt->execute();
            }
            break;

        case 'remove':
            error_log("Removendo produto $productId do carrinho");
            $stmt = $conn->prepare("DELETE FROM carrinho WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param("ii", $userId, $productId);
            $stmt->execute();
            break;

        default:
            $conn->rollback();
            error_log("Ação não reconhecida: $action");
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
            exit;
    }

    $conn->commit();
    error_log("Operação $action concluída com sucesso para product_id: $productId");
    echo json_encode(['success' => true, 'message' => 'Operação realizada com sucesso']);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Erro no carrinho: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
}
?>