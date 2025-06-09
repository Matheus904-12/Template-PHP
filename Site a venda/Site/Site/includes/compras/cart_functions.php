<?php
session_start();

function addToCart($user_id, $product_id, $quantity = 1) {
    global $conexao;
    
    // Verificar se o produto existe
    $stmt = $conexao->prepare("SELECT id FROM produtos WHERE id = :product_id");
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        throw new Exception('Produto não encontrado');
    }
    
    // Inserir ou atualizar no carrinho
    $stmt = $conexao->prepare("
        INSERT INTO user_cart (user_id, product_id, quantity) 
        VALUES (:user_id, :product_id, :quantity)
        ON DUPLICATE KEY UPDATE quantity = quantity + :quantity
    ");

    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);

    $result = $stmt->execute();

    if ($result) {
        return [
            'success' => true, 
            'message' => 'Produto adicionado ao carrinho',
            'cart_item_id' => $conexao->lastInsertId()
        ];
    } else {
        throw new Exception('Erro ao adicionar produto ao carrinho');
    }
}

function removeFromCart($user_id, $cart_item_id) {
    global $conexao;
    
    // Verificar se o item pertence ao usuário
    $stmt = $conexao->prepare("
        DELETE FROM user_cart 
        WHERE id = :cart_item_id AND user_id = :user_id
    ");

    $stmt->bindParam(':cart_item_id', $cart_item_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

    $result = $stmt->execute();

    if ($result && $stmt->rowCount() > 0) {
        return [
            'success' => true, 
            'message' => 'Produto removido do carrinho'
        ];
    } else {
        throw new Exception('Erro ao remover produto do carrinho ou item não encontrado');
    }
}

function updateCartItemQuantity($user_id, $cart_item_id, $quantity) {
    global $conexao;
    
    // Verificar se o item pertence ao usuário
    $stmt = $conexao->prepare("
        UPDATE user_cart 
        SET quantity = :quantity 
        WHERE id = :cart_item_id AND user_id = :user_id
    ");

    $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
    $stmt->bindParam(':cart_item_id', $cart_item_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

    $result = $stmt->execute();

    if ($result && $stmt->rowCount() > 0) {
        return [
            'success' => true, 
            'message' => 'Quantidade atualizada'
        ];
    } else {
        throw new Exception('Erro ao atualizar quantidade do item');
    }
}

function getCartItems($user_id) {
    global $conexao;
    
    $stmt = $conexao->prepare("
        SELECT uc.id, uc.product_id, p.nome, p.preco, uc.quantity, 
               (p.preco * uc.quantity) as subtotal
        FROM user_cart uc
        JOIN produtos p ON uc.product_id = p.id
        WHERE uc.user_id = :user_id
    ");

    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addToFavorites($user_id, $product_id) {
    global $conexao;
    
    // Verificar se o produto existe
    $stmt = $conexao->prepare("SELECT id FROM produtos WHERE id = :product_id");
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        throw new Exception('Produto não encontrado');
    }
    
    // Adicionar aos favoritos
    $stmt = $conexao->prepare("
        INSERT IGNORE INTO user_favorites (user_id, product_id) 
        VALUES (:user_id, :product_id)
    ");

    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);

    $result = $stmt->execute();

    if ($result) {
        return [
            'success' => true, 
            'message' => 'Produto adicionado aos favoritos',
            'favorite_id' => $conexao->lastInsertId()
        ];
    } else {
        throw new Exception('Erro ao adicionar produto aos favoritos');
    }
}

function removeFromFavorites($user_id, $favorite_id) {
    global $conexao;
    
    // Verificar se o favorito pertence ao usuário
    $stmt = $conexao->prepare("
        DELETE FROM user_favorites 
        WHERE id = :favorite_id AND user_id = :user_id
    ");

    $stmt->bindParam(':favorite_id', $favorite_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

    $result = $stmt->execute();

    if ($result && $stmt->rowCount() > 0) {
        return [
            'success' => true, 
            'message' => 'Produto removido dos favoritos'
        ];
    } else {
        throw new Exception('Erro ao remover produto dos favoritos ou item não encontrado');
    }
}

function getFavorites($user_id) {
    global $conexao;
    
    $stmt = $conexao->prepare("
        SELECT uf.id, uf.product_id, p.nome, p.preco
        FROM user_favorites uf
        JOIN produtos p ON uf.product_id = p.id
        WHERE uf.user_id = :user_id
    ");

    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}