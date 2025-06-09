<?php
// favorites_operations.php
session_start();
require_once '../../../adminView/config/dbconnect.php';

// Função para log (facilita a depuração)
function logAction($action, $data) {
    $logFile = __DIR__ . '/favorites_log.txt';
    $logData = date('Y-m-d H:i:s') . " | " . $action . " | " . json_encode($data) . "\n";
    file_put_contents($logFile, $logData, FILE_APPEND);
}

// Função de resposta padronizada - ajustada para manter consistência com cart_operations.php
function sendResponse($status, $message = '', $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Verifica se o usuário está logado
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_id = $isLoggedIn ? intval($_SESSION['user_id']) : null;

// Aceitar dados JSON do frontend
$requestData = json_decode(file_get_contents('php://input'), true);
if ($requestData) {
    $_POST = array_merge($_POST, $requestData);
}

// Verifica se é um pedido de verificação de login
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check_login') {
    sendResponse('success', '', [
        'isLoggedIn' => $isLoggedIn,
        'userId' => $user_id
    ]);
    exit;
}

// Validar action para todas as operações
if (!isset($_POST['action'])) {
    sendResponse('error', 'Ação não especificada');
}

$action = $_POST['action'];
logAction('favorite_operation', ['action' => $action, 'data' => $_POST, 'isLoggedIn' => $isLoggedIn, 'userId' => $user_id]);

// Para operações que necessitam de login
$requiresLogin = [
    'add_to_favorites', 'remove_from_favorites', 'move_to_cart',
    'get_favorites', 'move_all_to_cart'
];

if (in_array($action, $requiresLogin) && !$isLoggedIn) {
    sendResponse('error', 'Usuário não autenticado');
}

try {
    switch ($action) {
        case 'get_favorites':
            // Get favorite items with product details
            $stmt = $conn->prepare("
                SELECT f.product_id AS id, p.nome AS name, p.preco AS price, 
                       p.imagem AS image, p.descricao AS description
                FROM user_favorites f
                JOIN produtos p ON f.product_id = p.id
                WHERE f.user_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $favorite_items = [];
            $upload_path = '../../adminView/uploads/produtos/';
            
            while ($row = $result->fetch_assoc()) {
                // Add full image path
                $row['image'] = '../adminView/uploads/produtos/' . $row['image']; // Corrige o caminho da imagem
                $favorite_items[] = $row;
            }
            
            sendResponse('success', '', ['items' => $favorite_items]);
            $stmt->close();
            break;

        case 'add_to_favorites':
            if (!isset($_POST['product_id'])) {
                sendResponse('error', 'ID do produto não fornecido');
            }
            
            $product_id = intval($_POST['product_id']);
            
            // Check if product exists
            $stmt = $conn->prepare("SELECT id FROM produtos WHERE id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                sendResponse('error', 'Produto não encontrado');
            }
            $stmt->close();

            // Check if already in favorites
            $check_stmt = $conn->prepare("SELECT id FROM user_favorites WHERE user_id = ? AND product_id = ?");
            $check_stmt->bind_param("ii", $user_id, $product_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Already in favorites
                $check_stmt->close();
                sendResponse('success', 'Produto já está nos favoritos');
            }
            $check_stmt->close();

            // Insert into favorites
            $stmt = $conn->prepare("INSERT INTO user_favorites (user_id, product_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $product_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                sendResponse('success', 'Produto adicionado aos favoritos');
            } else {
                sendResponse('error', 'Erro ao adicionar aos favoritos');
            }
            $stmt->close();
            break;

        case 'remove_from_favorites':
            if (!isset($_POST['product_id'])) {
                sendResponse('error', 'ID do produto não fornecido');
            }
            
            $product_id = intval($_POST['product_id']);
            
            $stmt = $conn->prepare("DELETE FROM user_favorites WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param("ii", $user_id, $product_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                sendResponse('success', 'Produto removido dos favoritos');
            } else {
                sendResponse('error', 'Produto não encontrado nos favoritos');
            }
            $stmt->close();
            break;

        case 'move_to_cart':
            if (!isset($_POST['product_id'])) {
                sendResponse('error', 'ID do produto não fornecido');
            }
            
            $product_id = intval($_POST['product_id']);
            
            // Inicia uma transação
            $conn->begin_transaction();
            
            try {
                // Verifica se está nos favoritos
                $stmt = $conn->prepare("SELECT 1 FROM user_favorites WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param("ii", $user_id, $product_id);
                $stmt->execute();
                $inFavorites = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                
                if (!$inFavorites) {
                    $conn->rollback();
                    sendResponse('error', 'Produto não encontrado nos favoritos');
                }
                
                // Verifica se já existe no carrinho
                $stmt = $conn->prepare("SELECT quantity FROM user_cart WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param("ii", $user_id, $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Atualiza quantidade
                    $row = $result->fetch_assoc();
                    $new_quantity = $row['quantity'] + 1;
                    
                    $update_stmt = $conn->prepare("UPDATE user_cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                    $update_stmt->bind_param("iii", $new_quantity, $user_id, $product_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                } else {
                    // Adiciona ao carrinho com quantidade 1
                    $quantity = 1;
                    $insert_stmt = $conn->prepare("INSERT INTO user_cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    $insert_stmt->bind_param("iii", $user_id, $product_id, $quantity);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                }
                
                // Remove dos favoritos
                $delete_stmt = $conn->prepare("DELETE FROM user_favorites WHERE user_id = ? AND product_id = ?");
                $delete_stmt->bind_param("ii", $user_id, $product_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                $conn->commit();
                sendResponse('success', 'Produto movido para o carrinho');
            } catch (Exception $e) {
                $conn->rollback();
                sendResponse('error', 'Erro ao mover produto: ' . $e->getMessage());
            }
            break;

        case 'move_all_to_cart':
            // Get all favorited items
            $stmt = $conn->prepare("
                SELECT product_id FROM user_favorites WHERE user_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $moved_count = 0;
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                while ($row = $result->fetch_assoc()) {
                    $fav_product_id = $row['product_id'];
                    
                    // Check if product already exists in cart
                    $check_stmt = $conn->prepare("SELECT quantity FROM user_cart WHERE user_id = ? AND product_id = ?");
                    $check_stmt->bind_param("ii", $user_id, $fav_product_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        // Update existing cart item
                        $cart_row = $check_result->fetch_assoc();
                        $new_quantity = $cart_row['quantity'] + 1;
                        
                        $update_stmt = $conn->prepare("UPDATE user_cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                        $update_stmt->bind_param("iii", $new_quantity, $user_id, $fav_product_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    } else {
                        // Insert new cart item
                        $quantity = 1;
                        $insert_stmt = $conn->prepare("INSERT INTO user_cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                        $insert_stmt->bind_param("iii", $user_id, $fav_product_id, $quantity);
                        $insert_stmt->execute();
                        $insert_stmt->close();
                    }
                    $check_stmt->close();
                    $moved_count++;
                }
                
                // Remove all favorites after adding to cart
                if ($moved_count > 0) {
                    $delete_stmt = $conn->prepare("DELETE FROM user_favorites WHERE user_id = ?");
                    $delete_stmt->bind_param("i", $user_id);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                }
                
                $conn->commit();
                
                if ($moved_count > 0) {
                    sendResponse('success', 'Produtos movidos para o carrinho', ['count' => $moved_count]);
                } else {
                    sendResponse('error', 'Nenhum produto foi movido para o carrinho');
                }
            } catch (Exception $e) {
                $conn->rollback();
                sendResponse('error', 'Erro ao mover produtos: ' . $e->getMessage());
            }
            break;

        default:
            sendResponse('error', 'Ação inválida');
    }
} catch (Exception $e) {
    sendResponse('error', 'Erro interno: ' . $e->getMessage());
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>