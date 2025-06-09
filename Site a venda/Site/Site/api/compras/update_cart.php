<?php
// api/update_cart.php
session_start();
header('Content-Type: application/json');
require_once('../../../adminView/config/dbconnect.php');
require_once('../../../adminView/controller/Produtos/UserCartController.php');
require_once('../../../adminView/controller/Produtos/ProductController.php');

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    // Processar carrinho baseado em sessão para usuários não logados
    processSessionCart();
} else {
    // Processar carrinho baseado em banco de dados para usuários logados
    processDatabaseCart($_SESSION['user_id']);
}

function processSessionCart() {
    // Verificar se recebemos um ID de produto válido
    if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
        echo json_encode(['success' => false, 'message' => 'ID do produto não fornecido']);
        exit;
    }

    global $conn;
    $productController = new ProductController($conn);
    
    $productId = (int)$_POST['product_id'];
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    // Verificar se o produto existe
    $product = $productController->getProductById($productId);
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
        exit;
    }

    // Inicializar o carrinho de sessão se não existir
    if (!isset($_SESSION['session_cart'])) {
        $_SESSION['session_cart'] = [];
    }

    // Executar ação apropriada
    switch ($action) {
        case 'add':
            // Verificar se o produto já existe no carrinho
            $found = false;
            foreach ($_SESSION['session_cart'] as &$item) {
                if ($item['product_id'] == $productId) {
                    $item['quantity'] += $quantity;
                    $found = true;
                    break;
                }
            }
            
            // Se não encontrou, adicionar ao carrinho
            if (!$found) {
                $_SESSION['session_cart'][] = [
                    'product_id' => $productId,
                    'quantity' => $quantity
                ];
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Produto adicionado ao carrinho',
                'cartCount' => count($_SESSION['session_cart'])
            ]);
            break;
            
        case 'update':
            // Atualizar quantidade do produto
            foreach ($_SESSION['session_cart'] as &$item) {
                if ($item['product_id'] == $productId) {
                    $item['quantity'] = $quantity;
                    break;
                }
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Quantidade atualizada',
                'cartCount' => count($_SESSION['session_cart'])
            ]);
            break;
            
        case 'remove':
            // Remover produto do carrinho
            foreach ($_SESSION['session_cart'] as $key => $item) {
                if ($item['product_id'] == $productId) {
                    unset($_SESSION['session_cart'][$key]);
                    break;
                }
            }
            
            // Reindexar array
            $_SESSION['session_cart'] = array_values($_SESSION['session_cart']);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Produto removido do carrinho',
                'cartCount' => count($_SESSION['session_cart'])
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
    }
}

function processDatabaseCart($userId) {
    global $conn;
    $userCartController = new UserCartController($conn);
    $productController = new ProductController($conn);
    
    // Verificar se recebemos um ID de produto válido
    if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
        echo json_encode(['success' => false, 'message' => 'ID do produto não fornecido']);
        exit;
    }

    $productId = (int)$_POST['product_id'];
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    // Verificar se o produto existe
    $product = $productController->getProductById($productId);
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
        exit;
    }
    
    // Executar ação apropriada
    switch ($action) {
        case 'add':
            // Adicionar ao carrinho (o método addToCart já verifica se o produto existe)
            $result = $userCartController->addToCart($userId, $productId, $quantity);
            
            if ($result) {
                // Obter o carrinho atualizado
                $cartItems = $userCartController->getCartItems($userId);
                $cartCount = count($cartItems);
                
                // Calcular o novo total
                $cartTotal = 0;
                foreach ($cartItems as $item) {
                    $cartTotal += ($item['preco'] * $item['quantity']);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Produto adicionado ao carrinho com sucesso',
                    'cartCount' => $cartCount,
                    'cartItems' => $cartItems,
                    'cartTotal' => $cartTotal
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao adicionar produto ao carrinho']);
            }
            break;
            
        case 'update':
            // Obter itens do carrinho para verificar a quantidade atual
            $cartItems = $userCartController->getCartItems($userId);
            $currentQuantity = 0;
            
            foreach ($cartItems as $item) {
                if ($item['product_id'] == $productId) {
                    $currentQuantity = $item['quantity'];
                    break;
                }
            }
            
            // Calcular a mudança na quantidade
            $quantityChange = $quantity - $currentQuantity;
            
            // Atualizar quantidade
            $result = $userCartController->updateCartItemQuantity($userId, $productId, $quantityChange);
            
            if ($result) {
                // Obter o carrinho atualizado
                $cartItems = $userCartController->getCartItems($userId);
                $cartCount = count($cartItems);
                
                // Calcular o novo total
                $cartTotal = 0;
                foreach ($cartItems as $item) {
                    $cartTotal += ($item['preco'] * $item['quantity']);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Quantidade atualizada com sucesso',
                    'cartCount' => $cartCount,
                    'cartItems' => $cartItems,
                    'cartTotal' => $cartTotal
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar quantidade']);
            }
            break;
            
        case 'remove':
            // Remover produto do carrinho
            $result = $userCartController->removeFromCart($userId, $productId);
            
            if ($result) {
                // Obter o carrinho atualizado
                $cartItems = $userCartController->getCartItems($userId);
                $cartCount = count($cartItems);
                
                // Calcular o novo total
                $cartTotal = 0;
                foreach ($cartItems as $item) {
                    $cartTotal += ($item['preco'] * $item['quantity']);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Produto removido do carrinho com sucesso',
                    'cartCount' => $cartCount,
                    'cartItems' => $cartItems,
                    'cartTotal' => $cartTotal
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao remover produto do carrinho']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
    }
}
?>