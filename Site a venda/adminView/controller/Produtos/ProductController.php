<?php
// Caminho: adminView/controller/ProductController.php
require_once __DIR__ . '/../../models/Produtos/Product.php'; // Caminho mantido conforme original

class ProductController
{
    private $productModel;

    public function __construct($conn)
    {
        $this->productModel = new Product($conn);
    }

    public function getProductSalesHistory($productId)
    {
        $sql = "SELECT o.id, o.order_date as data_venda, o.total as valor_total, 
                oi.price_at_purchase as preco_unitario, oi.quantity as quantidade 
                FROM orders o 
                JOIN order_items oi ON o.id = oi.order_id 
                WHERE oi.product_id = ? 
                ORDER BY o.order_date DESC";

        $conn = $this->productModel->getConnection();
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            throw new Exception("Erro na preparação da consulta MySQLi: " . $conn->error);
        }

        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Função para manipular upload de imagem (sem alterações)
    private function handleImageUpload($file)
    {
        $targetDir = __DIR__ . "../../../uploads/produtos/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        if (empty($file) || empty($file["name"])) {
            return false;
        }

        // Gerar um nome de arquivo único e seguro
        $originalFileName = basename($file["name"]);
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));

        // Sanitizar o nome do arquivo original
        $sanitizedFileName = preg_replace("/[^a-zA-Z0-9.]/", "_", $originalFileName);

        // Adicionar timestamp para evitar sobrescrita
        $uniqueFileName = time() . '_' . $sanitizedFileName;
        $targetFilePath = $targetDir . $uniqueFileName;

        $allowTypes = array('jpg', 'png', 'jpeg', 'gif', 'webp');

        if (in_array($fileExtension, $allowTypes)) {
            // Verificar se o arquivo é realmente uma imagem
            $check = getimagesize($file["tmp_name"]);
            if ($check === false) {
                return false;
            }

            if (move_uploaded_file($file["tmp_name"], $targetFilePath)) {
                // Retorna apenas o nome do arquivo, sem o caminho relativo
                return $uniqueFileName;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    // Criar produto
    public function createProduct($data, $file)
    {
        // Validação básica
        if (empty($data['nome']) || empty($data['preco'])) {
            return array('success' => false, 'message' => 'Todos os campos obrigatórios devem ser preenchidos');
        }

        if (!is_numeric($data['preco']) || $data['preco'] <= 0) {
            return array('success' => false, 'message' => 'Preço inválido');
        }

        // Tratar upload de imagem
        $imagePath = "";
        if (!empty($file['name'])) {
            $imagePath = $this->handleImageUpload($file);
            if (!$imagePath && !empty($file['name'])) {
                return array('success' => false, 'message' => 'Erro ao fazer upload da imagem');
            }
        }

        // Criar produto no banco de dados
        $result = $this->productModel->createProduct(
            $data['nome'],
            $data['descricao'],
            $imagePath,
            $data['preco'],
            $data['categoria']
        );

        if ($result) {
            return array('success' => true, 'message' => 'Produto adicionado com sucesso');
        } else {
            return array('success' => false, 'message' => 'Erro ao adicionar produto');
        }
    }

    // Obter todos os produtos
    public function getAllProducts()
    {
        return $this->productModel->getAllProducts();
    }

    // Obter produtos por categoria
    public function getProductsByCategory($categoria)
    {
        return $this->productModel->getProductsByCategory($categoria);
    }

    // Obter produtos ordenados
    public function getProductsOrderedBy($orderBy)
    {
        return $this->productModel->getProductsOrderedBy($orderBy);
    }

    // Obter um produto por ID
    public function getProductById($id)
    {
        return $this->productModel->getProductById($id);
    }

    // Atualizar produto
    public function updateProduct($id, $data, $file)
    {
        // Validação básica
        if (empty($data['nome']) || empty($data['preco'])) {
            return array('success' => false, 'message' => 'Todos os campos obrigatórios devem ser preenchidos');
        }

        // Verificar se uma nova imagem foi enviada
        $imagePath = '';
        if (isset($file) && !empty($file['name'])) {
            $imagePath = $this->handleImageUpload($file);
            if (!$imagePath) {
                return array('success' => false, 'message' => 'Erro ao fazer upload da imagem');
            }
        }

        // Atualizar produto no banco de dados
        $result = $this->productModel->updateProduct(
            $id,
            $data['nome'],
            $data['descricao'],
            $imagePath,
            $data['preco'],
            $data['categoria']
        );

        if ($result) {
            return array('success' => true, 'message' => 'Produto atualizado com sucesso');
        } else {
            return array('success' => false, 'message' => 'Erro ao atualizar produto');
        }
    }

    // Excluir produto
    public function deleteProduct($id)
    {
        // Validar se o ID é válido
        if (!is_numeric($id) || $id <= 0) {
            return array('success' => false, 'message' => 'ID de produto inválido');
        }

        // Obter informações do produto para excluir a imagem
        $product = $this->productModel->getProductById($id);

        if ($product) {
            // Excluir o produto do banco de dados
            $result = $this->productModel->deleteProduct($id);

            if ($result) {
                // Tentar excluir a imagem do servidor
                if (!empty($product['imagem']) && file_exists($product['imagem'])) {
                    @unlink($product['imagem']);
                }

                return array('success' => true, 'message' => 'Produto excluído com sucesso');
            } else {
                return array('success' => false, 'message' => 'Erro ao excluir produto do banco de dados');
            }
        } else {
            return array('success' => false, 'message' => 'Produto não encontrado');
        }
    }

    // Buscar produtos
    public function searchProducts($termo)
    {
        return $this->productModel->searchProducts($termo);
    }

    public function updateProductStatus($productId, $status)
    {
        $sql = "UPDATE produtos SET ativo = ? WHERE id = ?";
        $conn = $this->productModel->getConnection();
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            error_log("Erro ao preparar consulta: " . $conn->error);
            return false;
        }

        $stmt->bind_param("ii", $status, $productId);
        return $stmt->execute();
    }
}