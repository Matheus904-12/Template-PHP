<?php
session_start();

header('Content-Type: application/json');

// Verifica se o usuário está logado
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userId = $isLoggedIn ? intval($_SESSION['user_id']) : null;

// Retorna o status de login como JSON
echo json_encode([
    'isLoggedIn' => $isLoggedIn,
    'userId' => $userId
]);
?>