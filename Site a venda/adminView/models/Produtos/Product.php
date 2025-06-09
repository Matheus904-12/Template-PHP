<?php
// Caminho: adminView/model/Product.php
class Product
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getConnection()
    {
        return $this->conn;
    }

    // Criar um novo produto
    public function createProduct($nome, $descricao, $imagem, $preco, $categoria)
    {
        // Definindo valores padrão
        $quantidade = 0;
        $ativo = 1;
        
        $query = "INSERT INTO produtos (nome, descricao, imagem, preco, categoria, quantidade, ativo, data_cadastro) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

        $stmt = $this->conn->prepare($query);

        if ($stmt === false) {
            error_log("Erro ao preparar a consulta: " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("sssdssi", $nome, $descricao, $imagem, $preco, $categoria, $quantidade, $ativo);

        if ($stmt->execute()) {
            return $stmt->insert_id; // Retorna o ID do último registro inserido
        } else {
            error_log("Erro ao executar a inserção: " . $stmt->error);
            return false;
        }
    }

    // Obter todos os produtos (incluindo apenas produtos ativos)
    public function getAllProducts()
    {
        $query = "SELECT * FROM produtos WHERE ativo = 1 ORDER BY data_cadastro DESC";
        $result = $this->conn->query($query);
        
        if ($result === false) {
            error_log("Erro ao executar consulta: " . $this->conn->error);
            return [];
        }
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Obter produtos por categoria
    public function getProductsByCategory($categoria)
    {
        $query = "SELECT * FROM produtos WHERE categoria = ? AND ativo = 1 ORDER BY data_cadastro DESC";
        $stmt = $this->conn->prepare($query);

        if ($stmt === false) {
            error_log("Erro ao preparar a consulta: " . $this->conn->error);
            return [];
        }

        $stmt->bind_param("s", $categoria);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Obter produtos ordenados
    public function getProductsOrderedBy($orderBy = 'destaque')
    {
        $query = "SELECT * FROM produtos WHERE ativo = 1";

        switch ($orderBy) {
            case 'promocao':
                $query .= " AND categoria = 'promocao' ORDER BY data_cadastro DESC";
                break;
            case 'baratos':
                $query .= " ORDER BY preco ASC";
                break;
            case 'caros':
                $query .= " ORDER BY preco DESC";
                break;
            default: // destaque
                $query .= " ORDER BY data_cadastro DESC";
                break;
        }

        $result = $this->conn->query($query);
        
        if ($result === false) {
            error_log("Erro ao executar consulta: " . $this->conn->error);
            return [];
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Obter um produto por ID
    public function getProductById($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            return false;
        }

        $query = "SELECT * FROM produtos WHERE id = ?";
        $stmt = $this->conn->prepare($query);

        if ($stmt === false) {
            error_log("Erro ao preparar a consulta: " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // Atualizar um produto
    public function updateProduct($id, $nome, $descricao, $imagem, $preco, $categoria)
    {
        if (!is_numeric($id) || $id <= 0) {
            return false;
        }

        if (empty($imagem)) {
            $query = "UPDATE produtos SET nome = ?, descricao = ?, preco = ?, categoria = ? WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            
            if ($stmt === false) {
                error_log("Erro ao preparar a consulta: " . $this->conn->error);
                return false;
            }
            
            $stmt->bind_param("ssdsi", $nome, $descricao, $preco, $categoria, $id);
        } else {
            $query = "UPDATE produtos SET nome = ?, descricao = ?, imagem = ?, preco = ?, categoria = ? WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            
            if ($stmt === false) {
                error_log("Erro ao preparar a consulta: " . $this->conn->error);
                return false;
            }
            
            $stmt->bind_param("sssdsi", $nome, $descricao, $imagem, $preco, $categoria, $id);
        }

        return $stmt->execute();
    }

    // Exclusão lógica (recomendado)
    public function deleteProduct($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            return false;
        }

        $query = "UPDATE produtos SET ativo = 0 WHERE id = ?";
        $stmt = $this->conn->prepare($query);

        if ($stmt === false) {
            error_log("Erro ao preparar a consulta: " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    // Buscar produtos
    public function searchProducts($termo)
    {
        $termo = "%$termo%";
        $query = "SELECT * FROM produtos WHERE (nome LIKE ? OR descricao LIKE ? OR categoria LIKE ?) AND ativo = 1";
        $stmt = $this->conn->prepare($query);

        if ($stmt === false) {
            error_log("Erro ao preparar a consulta: " . $this->conn->error);
            return [];
        }

        $stmt->bind_param("sss", $termo, $termo, $termo);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}