<?php
session_start();
require_once '../adminView/config/dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Preencha todos os campos']);
        exit;
    }

    $query = "SELECT id, name, password, profile_picture FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['name'];

            // Verifica se o usuário tem uma foto de perfil
            if (empty($user['profile_picture'])) {
                $_SESSION['user_picture'] = 'img/icons/perfil.png'; // Caminho da imagem padrão
            } else {
                $_SESSION['user_picture'] = $user['profile_picture'];
            }

            $_SESSION['logged_in'] = true;

            // Obter o IP do usuário
            $user_ip = $_SERVER['REMOTE_ADDR'];

            // Inserir os dados do login na tabela 'logins'
            $login_query = "INSERT INTO logins (usuario_id, ip) VALUES (?, ?)";
            $login_stmt = $conn->prepare($login_query);
            $login_stmt->bind_param("is", $user['id'], $user_ip);
            $login_stmt->execute();

            echo json_encode(['success' => true, 'message' => 'Login realizado com sucesso']);
            header('Location: index.php');
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Senha incorreta']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método de requisição inválido']);
    exit;
}
