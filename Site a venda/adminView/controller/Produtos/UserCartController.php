<?php
class UserCartController
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function addToCart($userId, $productId, $quantity = 1)
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO user_cart (user_id, product_id, quantity)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + ?
            ");
            $stmt->bind_param("iiii", $userId, $productId, $quantity, $quantity);
            $result = $stmt->execute();

            return $result ? true : false;
        } catch (Exception $e) {
            error_log("Erro ao adicionar ao carrinho: " . $e->getMessage());
            return false;
        }
    }

    public function clearCart($userId)
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM user_cart WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $result = $stmt->execute();
    
            return $result ? true : false;
        } catch (Exception $e) {
            error_log("Erro ao limpar carrinho: " . $e->getMessage());
            return false;
        }
    }

    public function removeFromCart($userId, $productId)
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM user_cart WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param("ii", $userId, $productId);
            $result = $stmt->execute();

            return $result ? true : false;
        } catch (Exception $e) {
            error_log("Erro ao remover do carrinho: " . $e->getMessage());
            return false;
        }
    }

    public function updateCartItemQuantity($userId, $productId, $quantityChange)
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE user_cart
                SET quantity = quantity + ?
                WHERE user_id = ? AND product_id = ?
            ");
            $stmt->bind_param("iii", $quantityChange, $userId, $productId);
            $result = $stmt->execute();

            return $result ? true : false;
        } catch (Exception $e) {
            error_log("Erro ao atualizar quantidade: " . $e->getMessage());
            return false;
        }
    }

    // Novo método para buscar itens do carrinho
    public function getCartItems($userId)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT uc.product_id, uc.quantity, p.nome, p.preco, p.imagem
                FROM user_cart uc
                JOIN produtos p ON uc.product_id = p.id
                WHERE uc.user_id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            $cartItems = $result->fetch_all(MYSQLI_ASSOC);
            error_log("Itens do carrinho para o usuário " . $userId . ": " . print_r($cartItems, true)); // Adicione este log

            return $cartItems;
        } catch (Exception $e) {
            error_log("Erro ao buscar itens do carrinho: " . $e->getMessage());
            return [];
        }
    }
}
