<?php
require_once './config/dbconnect.php';
require_once './controller/ProductController.php';

$productController = new ProductController($conn);

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $produtoId = intval($_GET['id']);
    $produto = $productController->getProductById($produtoId);

    if ($produto) {
        echo json_encode(['success' => true, 'produto' => $produto]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Produto não encontrado.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID do produto não fornecido.']);
}
?>