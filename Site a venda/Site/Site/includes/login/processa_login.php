<?php
// processa_login.php
session_start();
require_once '../../../adminView/config/dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        // Se a origem da requisição é AJAX, retornar JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Preencha todos os campos']);
            exit;
        } else {
            $_SESSION['login_error'] = 'Preencha todos os campos';
            header('Location: ../../login_site.php');
            exit;
        }
    }

    // Usando PDO em vez de mysqli
    $query = "SELECT id, name, password, profile_picture FROM usuarios WHERE email = :email";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (password_verify($password, $user['password'])) {
            // Login bem-sucedido
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
            $login_query = "INSERT INTO logins (usuario_id, ip) VALUES (:usuario_id, :ip)";
            $login_stmt = $pdo->prepare($login_query);
            $login_stmt->bindParam(':usuario_id', $user['id'], PDO::PARAM_INT);
            $login_stmt->bindParam(':ip', $user_ip, PDO::PARAM_STR);
            $login_stmt->execute();

            // Se a origem da requisição é AJAX, retornar JSON antes de redirecionar
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Login realizado com sucesso']);
                exit;
            } else {
                // Redirecionar normalmente para requisições de formulário padrão
                header('Location: ../../index.php');
                exit;
            }
        } else {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Senha incorreta']);
                exit;
            } else {
                $_SESSION['login_error'] = 'Senha incorreta';
                header('Location: ../../login_site.php');
                exit;
            }
        }
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
            exit;
        } else {
            $_SESSION['login_error'] = 'Usuário não encontrado';
            header('Location: ../../login_site.php');
            exit;
        }
    }
} else {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Método de requisição inválido']);
        exit;
    } else {
        header('Location: ../../login_site.php');
        exit;
    }
}
?>