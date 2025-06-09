<?php
session_start();
require_once '../adminView/config/dbconnect.php';
require_once '../vendor/autoload.php';

use League\OAuth2\Client\Provider\Google;

// Configurar para apenas registrar erros e não exibi-los
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Garantir envio de JSON como resposta
header('Content-Type: application/json');

// Registrar detalhes no log para depuração
error_log("===== Iniciando processo de login Google =====");

try {
    // Configurações do Google OAuth
    $provider = new Google([
        'clientId'      => '###',
        'clientSecret'  => '###',
        'redirectUri'   => 'https://cristaisgoldlar.com.br/Site/google_login.php' // Ajuste para seu URI
    ]);

    // Verificar se recebemos o código de autorização
    if (!isset($_GET['code'])) {
        // Redirecionar para o Google para autenticação
        $authorizationUrl = $provider->getAuthorizationUrl();
        $_SESSION['oauth2state'] = $provider->getState();
        header('Location: ' . $authorizationUrl);
        exit;
    } elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
        // Estado inválido, possível ataque CSRF
        unset($_SESSION['oauth2state']);
        throw new Exception('Estado inválido');
    } else {
        // Obter token de acesso
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        // Obter informações do usuário
        $user = $provider->getResourceOwner($token);

        $email = $user->getEmail();
        $name = $user->getName();
        $picture = $user->getAvatar();

        error_log("Dados do usuário: Email=$email, Nome=$name");

        // Verificar conexão com o banco de dados
        if (!$conn) {
            throw new Exception("Conexão com o banco de dados não está disponível");
        }

        // Procurar usuário no banco de dados
        $query = "SELECT id, name FROM usuarios WHERE email = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Erro na preparação da consulta: " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        $success = $stmt->execute();

        if (!$success) {
            throw new Exception("Falha ao executar consulta: " . $stmt->error);
        }

        $result = $stmt->get_result();

        // Verificar se usuário existe
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['name'];
            error_log("URL da foto de perfil: " . $picture);
            $_SESSION['user_picture'] = $picture; // Salva a foto de perfil na sessão
            $_SESSION['logged_in'] = true;

            // Atualizar a foto de perfil no banco de dados
            $updateQuery = "UPDATE usuarios SET profile_picture = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("si", $picture, $row['id']);
            $updateStmt->execute();

            error_log("Login bem-sucedido para usuário existente ID=" . $_SESSION['user_id']);

            echo json_encode([
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'name' => $name,
                'picture' => $picture
            ]);
            header('Location: index.php'); // Redireciona para index.php
            exit;
        } else {
            // Criar novo usuário
            error_log("Usuário não encontrado, criando novo registro");

            $password = password_hash(uniqid(), PASSWORD_BCRYPT);
            // Inserir a foto de perfil no banco de dados
            $query = "INSERT INTO usuarios (name, email, password, profile_picture) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);

            if (!$stmt) {
                throw new Exception("Erro na preparação da inserção: " . $conn->error);
            }

            $stmt->bind_param("ssss", $name, $email, $password, $picture);
            $success = $stmt->execute();

            if (!$success) {
                throw new Exception("Falha ao inserir usuário: " . $stmt->error);
            }

            $id = $stmt->insert_id;
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $name;
            error_log("URL da foto de perfil: " . $picture);
            $_SESSION['user_picture'] = $picture; // Salva a foto de perfil na sessão
            $_SESSION['logged_in'] = true;

            error_log("Novo usuário criado e logado com sucesso ID=$id");

            echo json_encode([
                'success' => true,
                'message' => 'Conta criada e login realizado com sucesso',
                'name' => $name,
                'picture' => $picture
            ]);
            header('Location: index.php'); // Redireciona para index.php
            exit;
        }
    }
} catch (Exception $e) {
    error_log("ERRO: " . $e->getMessage());
    error_log("Arquivo: " . $e->getFile() . ", Linha: " . $e->getLine());

    echo json_encode([
        'success' => false,
        'message' => 'Erro no processamento: ' . $e->getMessage()
    ]);
}
?>