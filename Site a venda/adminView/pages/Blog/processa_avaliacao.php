<?php
// Database connection
include '../../config/dbconnect.php';

// Check if request is AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Check method and content type
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get data
    if ($isAjax) {
        // Handle AJAX request
        $input = json_decode(file_get_contents('php://input'), true);
        $post_id = isset($input['post_id']) ? intval($input['post_id']) : 0;
        $rating = isset($input['rating']) ? intval($input['rating']) : 0;
    } else {
        // Handle regular form submission
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    }
    
    // Validate data
    $response = ['success' => false, 'message' => ''];
    
    if (empty($post_id)) {
        $response['message'] = "ID do post inválido.";
    } elseif ($rating < 1 || $rating > 5) {
        $response['message'] = "Avaliação inválida. Deve ser entre 1 e 5.";
    } else {
        // Get user IP address for uniqueness check
        $ip_usuario = $_SERVER['REMOTE_ADDR'];
        
        // Check if user already rated this post
        $stmt = $conn->prepare("SELECT id FROM blog_avaliacoes WHERE post_id = ? AND ip_usuario = ?");
        $stmt->bind_param("is", $post_id, $ip_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // User already rated this post, update their rating
            $row = $result->fetch_assoc();
            $rating_id = $row['id'];
            
            $stmt = $conn->prepare("UPDATE blog_avaliacoes SET avaliacao = ?, data_avaliacao = NOW() WHERE id = ?");
            $stmt->bind_param("ii", $rating, $rating_id);
        } else {
            // First-time rating, insert new record
            $stmt = $conn->prepare("INSERT INTO blog_avaliacoes (post_id, avaliacao, ip_usuario) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $post_id, $rating, $ip_usuario);
        }
        
        // Execute the query
        if ($stmt->execute()) {
            // Calculate average rating
            $avgStmt = $conn->prepare("SELECT AVG(avaliacao) as avg_rating FROM blog_avaliacoes WHERE post_id = ?");
            $avgStmt->bind_param("i", $post_id);
            $avgStmt->execute();
            $avgResult = $avgStmt->get_result();
            $avgRow = $avgResult->fetch_assoc();
            
            $response['success'] = true;
            $response['message'] = "Avaliação registrada com sucesso!";
            $response['avg_rating'] = round($avgRow['avg_rating'], 1);
        } else {
            $response['message'] = "Erro ao registrar avaliação: " . $conn->error;
        }
    }
    
    if ($isAjax) {
        // Return JSON for AJAX requests
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        // Redirect for regular form submissions
        if ($response['success']) {
            header("Location: ../Site/blog.php?post_id=" . $post_id . "&rating=success");
        } else {
            header("Location: ../Site/blog.php?post_id=" . $post_id . "&rating=error&msg=" . urlencode($response['message']));
        }
    }
    exit();
} else {
    // If someone tries to access this file directly, redirect to blog
    header("Location: ../Site/blog.php");
    exit();
}
?>