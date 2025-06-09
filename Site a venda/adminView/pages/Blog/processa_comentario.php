<?php
// Database connection
include '../../config/dbconnect.php';

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $nome = isset($_POST['nome']) ? $_POST['nome'] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $comentario = isset($_POST['comentario']) ? $_POST['comentario'] : '';
    $data_comentario = date('Y-m-d H:i:s');
    
    // Validate data
    $errors = [];
    
    if (empty($post_id)) {
        $errors[] = "ID do post inválido.";
    }
    
    if (empty($nome)) {
        $errors[] = "O nome é obrigatório.";
    }
    
    if (empty($email)) {
        $errors[] = "O e-mail é obrigatório.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "E-mail inválido.";
    }
    
    if (empty($comentario)) {
        $errors[] = "O comentário é obrigatório.";
    }
    
    // If no errors, proceed with insertion
    if (empty($errors)) {
        // Prepare and execute the query
        $stmt = $conn->prepare("INSERT INTO blog_comentarios (post_id, nome, email, comentario, data_comentario) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $post_id, $nome, $email, $comentario, $data_comentario);
        
        if ($stmt->execute()) {
            // Redirect back to the post page with success message
            header("Location: ../../../Site/blog.php?post_id=" . $post_id . "&comment=success");
            exit();
        } else {
            // Redirect back with error
            header("Location: ../../../Site/blog.php?post_id=" . $post_id . "&comment=error&msg=Erro ao salvar o comentário.");
            exit();
        }
    } else {
        // Redirect back with validation errors
        $error_string = implode(", ", $errors);
        header("Location: ../../../Site/blog.php?post_id=" . $post_id . "&comment=error&msg=" . urlencode($error_string));
        exit();
    }
} else {
    // If someone tries to access this file directly, redirect to blog
    header("Location: ../../../Site/blog.php");
    exit();
}
?>