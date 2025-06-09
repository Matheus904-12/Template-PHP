<?php
class UserFavoritesController
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function addToFavorites($userId, $productId)
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT IGNORE INTO user_favorites (user_id, product_id)
                VALUES (?, ?)
            ");
            $stmt->bind_param("ii", $userId, $productId);
            $result = $stmt->execute();

            return $result ? true : false;
        } catch (Exception $e) {
            error_log("Erro ao adicionar aos favoritos: " . $e->getMessage());
            return false;
        }
    }

    public function removeFromFavorites($userId, $productId)
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM user_favorites WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param("ii", $userId, $productId);
            $result = $stmt->execute();

            return $result ? true : false;
        } catch (Exception $e) {
            error_log("Erro ao remover dos favoritos: " . $e->getMessage());
            return false;
        }
    }

    // Novo método para buscar favoritos
    public function getFavoriteItems($userId)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT p.*
                FROM user_favorites uf
                JOIN produtos p ON uf.product_id = p.id
                WHERE uf.user_id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            $favoriteItems = $result->fetch_all(MYSQLI_ASSOC);
            error_log("Itens favoritos para o usuário " . $userId . ": " . print_r($favoriteItems, true)); // Adicione este log

            return $favoriteItems;
        } catch (Exception $e) {
            error_log("Erro ao buscar favoritos: " . $e->getMessage());
            return [];
        }
    }
}
