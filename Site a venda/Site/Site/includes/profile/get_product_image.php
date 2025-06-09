<?php
// This file should be placed in the 'includes' directory
//get_product_image.php
// Include the database connection
require_once '../../../adminView/config/dbconnect.php';

// Check if product ID is provided
if (isset($_GET['id'])) {
    $productId = intval($_GET['id']);
    
    // Create a prepared statement to fetch the product image
    $query = "SELECT imagem FROM produtos WHERE id = :product_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':product_id' => $productId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $image = $result['imagem'];
        
        // Check if the image path has a directory structure
        if (!empty($image)) {
            if (strpos($image, '/') === false && strpos($image, '\\') === false) {
                // Add the correct path prefix
                $imagePath = '../adminView/uploads/produtos/' . $image;
            } else {
                // Image already has a path
                $imagePath = $image;
            }
            
            // Return the image path
            echo $imagePath;
        } else {
            // Return placeholder if no image found
            echo '../adminView/uploads/produtos/placeholder.jpeg';
        }
    } else {
        // Return placeholder if product not found
        echo '../adminView/uploads/produtos/placeholder.jpeg';
    }
} else {
    // Return placeholder if no product ID provided
    echo '../adminView/uploads/produtos/placeholder.jpeg';
}
?>