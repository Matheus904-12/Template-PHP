<?php
session_start();
require_once '../../adminView/config/dbconnect.php';
require_once '../../adminView/controller/UserCartController.php';

header('Content-Type: application/json');

// Inicializar o controlador
$userCartController = new UserCartController($conn);

// Verificar se usuário está logado
$isLoggedIn = isset($_SESSION['user_id']) && $_SESSION['logged_in'] === true;
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Processar a requisição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decodificar os dados JSON da requisição
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
        exit;
    }
    
    $action = $data['action'] ?? '';
    $productId = isset($data['productId']) ? intval($data['productId']) : 0;
    $quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;
    
    $response = ['status' => 'error', 'message' => 'Ação inválida'];
    
    switch ($action) {
        case 'add':
            if ($isLoggedIn) {
                // Adicionar ao banco de dados
                $success = $userCartController->addToCart($userId, $productId, $quantity);
                $cart = $userCartController->getUserCart($userId);
                $response = ['status' => 'success', 'message' => 'Produto adicionado ao carrinho', 'cart' => $cart];
            } else {
                // Adicionar à sessão
                if (!isset($_SESSION['session_cart'])) {
                    $_SESSION['session_cart'] = [];
                }
                
                // Verificar se o produto já está no carrinho
                $found = false;
                foreach ($_SESSION['session_cart'] as &$item) {
                    if ($item['id'] == $productId) {
                        $item['quantity'] += $quantity;
                        $found = true;
                        break;
                    }
                }
                
                // Se o produto não estiver no carrinho, adicione-o
                if (!$found) {
                    // Buscar informações do produto
                    $stmt = $conn->prepare("SELECT id, nome as name, preco as price, imagem as image FROM produtos WHERE id = ?");
                    $stmt->bind_param("i", $productId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($product = $result->fetch_assoc()) {
                        $product['quantity'] = $quantity;
                        $product['price'] = floatval($product['price']);
                        $_SESSION['session_cart'][] = $product;
                    }
                }
                
                $response = ['status' => 'success', 'message' => 'Produto adicionado ao carrinho', 'cart' => $_SESSION['session_cart']];
            }
            break;
            
        case 'update':
            if ($isLoggedIn) {
                // Atualizar no banco de dados
                $success = $userCartController->updateCartQuantity($userId, $productId, $quantity);
                $cart = $userCartController->getUserCart($userId);
                $response = ['status' => 'success', 'message' => 'Carrinho atualizado', 'cart' => $cart];
            } else {
                // Atualizar na sessão
                if (isset($_SESSION['session_cart'])) {
                    foreach ($_SESSION['session_cart'] as $key => &$item) {
                        if ($item['id'] == $productId) {
                            if ($quantity <= 0) {
                                // Remover o item se a quantidade for 0 ou menos
                                unset($_SESSION['session_cart'][$key]);
                                $_SESSION['session_cart'] = array_values($_SESSION['session_cart']); // Reindexar o array
                            } else {
                                // Atualizar a quantidade
                                $item['quantity'] = $quantity;
                            }
                            break;
                        }
                    }
                }
                
                $response = ['status' => 'success', 'message' => 'Carrinho atualizado', 'cart' => $_SESSION['session_cart'] ?? []];
            }
            break;
            
        case 'remove':
            if ($isLoggedIn) {
                // Remover do banco de dados
                $success = $userCartController->removeFromCart($userId, $productId);
                $cart = $userCartController->getUserCart($userId);
                $response = ['status' => 'success', 'message' => 'Produto removido do carrinho', 'cart' => $cart];
            } else {
                // Remover da sessão
                if (isset($_SESSION['session_cart'])) {
                    foreach ($_SESSION['session_cart'] as $key => $item) {
                        if ($item['id'] == $productId) {
                            unset($_SESSION['session_cart'][$key]);
                            $_SESSION['session_cart'] = array_values($_SESSION['session_cart']); // Reindexar o array
                            break;
                        }
                    }
                }
                
                $response = ['status' => 'success', 'message' => 'Produto removido do carrinho', 'cart' => $_SESSION['session_cart'] ?? []];
            }
            break;
            
        case 'get':
            if ($isLoggedIn) {
                // Obter do banco de dados
                $cart = $userCartController->getUserCart($userId);
            } else {
                // Obter da sessão
                $cart = $_SESSION['session_cart'] ?? [];
            }
            
            $response = ['status' => 'success', 'cart' => $cart];
            break;
    }
    
    // Retornar a resposta como JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Se não for uma requisição POST, retornar erro
header('HTTP/1.1 405 Method Not Allowed');
echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);