<?php
session_start();
header('Content-Type: application/json');
require_once('../../../adminView/config/dbconnect.php');
require_once('../../../adminView/controller/Produtos/UserCartController.php');
require_once('../../../adminView/controller/Produtos/UserFavoritesController.php');

// Configurar logs de erro
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Inicializar controladores
$userCartController = new UserCartController($conn);
$userFavoritesController = new UserFavoritesController($conn);

// Obter ação da requisição
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// Log da requisição
error_log("Requisição recebida: action=$action, POST=" . print_r($_POST, true) . ", GET=" . print_r($_GET, true));

// Função para responder
function sendResponse($status, $data = [], $message = '') {
    echo json_encode(['status' => $status, 'data' => $data, 'message' => $message]);
    exit;
}

// Verificar ação
if (!$action) {
    error_log("Ação não fornecida");
    sendResponse('error', [], 'Ação não fornecida');
}

try {
    switch ($action) {
        case 'check_login':
            $isLoggedIn = isset($_SESSION['user_id']) && $_SESSION['logged_in'] === true;
            $userId = $isLoggedIn ? $_SESSION['user_id'] : null;
            sendResponse('success', ['isLoggedIn' => $isLoggedIn, 'userId' => $userId]);
            break;

        case 'get_cart':
            if (!isset($_SESSION['user_id'])) {
                error_log("Usuário não logado para get_cart");
                sendResponse('error', [], 'Usuário não logado');
            }
            $userId = $_SESSION['user_id'];
            $items = $userCartController->getCartItems($userId);
            $formattedItems = array_map(function ($item) {
                return [
                    'id' => $item['product_id'],
                    'name' => $item['nome'],
                    'price' => $item['preco'],
                    'image' => '../adminView/uploads/produtos/' . $item['imagem'],
                    'quantity' => $item['quantity']
                ];
            }, $items);
            sendResponse('success', ['items' => $formattedItems]);
            break;

        case 'get_favorites':
            if (!isset($_SESSION['user_id'])) {
                error_log("Usuário não logado para get_favorites");
                sendResponse('error', [], 'Usuário não logado');
            }
            $userId = $_SESSION['user_id'];
            $items = $userFavoritesController->getFavoriteItems($userId);
            $formattedItems = array_map(function ($item) {
                return [
                    'id' => $item['id'],
                    'name' => $item['nome'],
                    'price' => $item['preco'],
                    'image' => '../adminView/uploads/produtos/' . $item['imagem'],
                    'description' => $item['descricao']
                ];
            }, $items);
            sendResponse('success', ['items' => $formattedItems]);
            break;

        case 'add_to_cart':
            if (!isset($_SESSION['user_id'])) {
                error_log("Usuário não logado para add_to_cart");
                sendResponse('error', [], 'Usuário não logado');
            }
            $userId = $_SESSION['user_id'];
            $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

            if ($productId <= 0) {
                error_log("ID do produto inválido: $productId");
                sendResponse('error', [], 'ID do produto inválido');
            }

            $result = $userCartController->addToCart($userId, $productId, $quantity);
            if ($result) {
                sendResponse('success', [], 'Produto adicionado ao carrinho');
            } else {
                error_log("Falha ao adicionar ao carrinho: userId=$userId, productId=$productId");
                sendResponse('error', [], 'Erro ao adicionar ao carrinho');
            }
            break;

        case 'add_to_favorites':
            if (!isset($_SESSION['user_id'])) {
                error_log("Usuário não logado para add_to_favorites");
                sendResponse('error', [], 'Usuário não logado');
            }
            $userId = $_SESSION['user_id'];
            $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

            if ($productId <= 0) {
                error_log("ID do produto inválido: $productId");
                sendResponse('error', [], 'ID do produto inválido');
            }

            $result = $userFavoritesController->addToFavorites($userId, $productId);
            if ($result) {
                sendResponse('success', [], 'Produto adicionado aos favoritos');
            } else {
                error_log("Falha ao adicionar aos favoritos: userId=$userId, productId=$productId");
                sendResponse('error', [], 'Erro ao adicionar aos favoritos');
            }
            break;

        case 'remove_from_cart':
            if (!isset($_SESSION['user_id'])) {
                error_log("Usuário não logado para remove_from_cart");
                sendResponse('error', [], 'Usuário não logado');
            }
            $userId = $_SESSION['user_id'];
            $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

            if ($productId <= 0) {
                error_log("ID do produto inválido: $productId");
                sendResponse('error', [], 'ID do produto inválido');
            }

            $result = $userCartController->removeFromCart($userId, $productId);
            if ($result) {
                sendResponse('success', [], 'Produto removido do carrinho');
            } else {
                error_log("Falha ao remover do carrinho: userId=$userId, productId=$productId");
                sendResponse('error', [], 'Erro ao remover do carrinho');
            }
            break;

        case 'remove_from_favorites':
            if (!isset($_SESSION['user_id'])) {
                error_log("Usuário não logado para remove_from_favorites");
                sendResponse('error', [], 'Usuário não logado');
            }
            $userId = $_SESSION['user_id'];
            $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

            if ($productId <= 0) {
                error_log("ID do produto inválido: $productId");
                sendResponse('error', [], 'ID do produto inválido');
            }

            $result = $userFavoritesController->removeFromFavorites($userId, $productId);
            if ($result) {
                sendResponse('success', [], 'Produto removido dos favoritos');
            } else {
                error_log("Falha ao remover dos favoritos: userId=$userId, productId=$productId");
                sendResponse('error', [], 'Erro ao remover dos favoritos');
            }
            break;

        case 'update_cart_quantity':
            if (!isset($_SESSION['user_id'])) {
                error_log("Usuário não logado para update_cart_quantity");
                sendResponse('error', [], 'Usuário não logado');
            }
            $userId = $_SESSION['user_id'];
            $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

            if ($productId <= 0) {
                error_log("ID do produto inválido: $productId");
                sendResponse('error', [], 'ID do produto inválido');
            }

            if ($quantity <= 0) {
                error_log("Quantidade inválida: $quantity");
                sendResponse('error', [], 'Quantidade inválida');
            }

            // Atualiza a quantidade diretamente
            $stmt = $conn->prepare("UPDATE user_cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param("iii", $quantity, $userId, $productId);
            $result = $stmt->execute();

            if ($result) {
                sendResponse('success', [], 'Quantidade atualizada');
            } else {
                error_log("Falha ao atualizar quantidade: userId=$userId, productId=$productId, quantity=$quantity");
                sendResponse('error', [], 'Erro ao atualizar quantidade');
            }
            break;

        case 'move_to_cart':
            if (!isset($_SESSION['user_id'])) {
                error_log("Usuário não logado para move_to_cart");
                sendResponse('error', [], 'Usuário não logado');
            }
            $userId = $_SESSION['user_id'];
            $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

            if ($productId <= 0) {
                error_log("ID do produto inválido: $productId");
                sendResponse('error', [], 'ID do produto inválido');
            }

            // Adiciona ao carrinho
            $resultCart = $userCartController->addToCart($userId, $productId, 1);
            // Remove dos favoritos
            $resultFavorites = $userFavoritesController->removeFromFavorites($userId, $productId);

            if ($resultCart && $resultFavorites) {
                sendResponse('success', [], 'Produto movido para o carrinho');
            } else {
                error_log("Falha ao mover para o carrinho: userId=$userId, productId=$productId");
                sendResponse('error', [], 'Erro ao mover para o carrinho');
            }
            break;

        case 'move_all_to_cart':
            if (!isset($_SESSION['user_id'])) {
                error_log("Usuário não logado para move_all_to_cart");
                sendResponse('error', [], 'Usuário não logado');
            }
            $userId = $_SESSION['user_id'];

            // Obter todos os favoritos
            $favorites = $userFavoritesController->getFavoriteItems($userId);
            $count = count($favorites);

            if ($count === 0) {
                sendResponse('success', ['count' => 0], 'Nenhum item nos favoritos');
            }

            $success = true;
            foreach ($favorites as $item) {
                $productId = $item['id'];
                if (!$userCartController->addToCart($userId, $productId, 1)) {
                    $success = false;
                    error_log("Falha ao adicionar ao carrinho: userId=$userId, productId=$productId");
                }
                if (!$userFavoritesController->removeFromFavorites($userId, $productId)) {
                    $success = false;
                    error_log("Falha ao remover dos favoritos: userId=$userId, productId=$productId");
                }
            }

            if ($success) {
                sendResponse('success', ['count' => $count], 'Todos os produtos movidos para o carrinho');
            } else {
                sendResponse('error', [], 'Erro ao mover alguns produtos para o carrinho');
            }
            break;

        default:
            error_log("Ação inválida: $action");
            sendResponse('error', [], 'Ação inválida');
    }
} catch (Exception $e) {
    error_log("Erro geral em cart_operations: " . $e->getMessage());
    sendResponse('error', [], 'Erro interno do servidor');
}
?>