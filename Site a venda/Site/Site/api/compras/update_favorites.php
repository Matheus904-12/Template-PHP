<?php
session_start();
header('Content-Type: application/json');
require_once('../../../adminView/config/dbconnect.php');

// Obter os dados JSON da requisição
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Verificar se o JSON foi decodificado corretamente
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit;
}

// Verificar parâmetros obrigatórios
if (!isset($data['product_id']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros obrigatórios não fornecidos']);
    exit;
}

$productId = (int)$data['product_id'];
$action = $data['action'];

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    processSessionFavorites($data);
} else {
    processDatabaseFavorites($_SESSION['user_id'], $data);
}

function processSessionFavorites($data) {
    $productId = (int)$data['product_id'];
    $action = $data['action'];

    // Inicializar lista de favoritos na sessão se não existir
    if (!isset($_SESSION['session_favorites'])) {
        $_SESSION['session_favorites'] = [];
    }

    try {
        switch ($action) {
            case 'add':
                // Verificar se o produto já existe nos favoritos
                if (!in_array($productId, $_SESSION['session_favorites'])) {
                    $_SESSION['session_favorites'][] = $productId;
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Produto adicionado aos favoritos',
                    'favoritesCount' => count($_SESSION['session_favorites'])
                ]);
                break;
                
            case 'remove':
                // Remover produto dos favoritos
                $key = array_search($productId, $_SESSION['session_favorites']);
                if ($key !== false) {
                    unset($_SESSION['session_favorites'][$key]);
                    // Reindexar array
                    $_SESSION['session_favorites'] = array_values($_SESSION['session_favorites']);
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Produto removido dos favoritos',
                    'favoritesCount' => count($_SESSION['session_favorites'])
                ]);
                break;
                
            case 'check':
                // Verificar se o produto está nos favoritos
                $isFavorite = in_array($productId, $_SESSION['session_favorites']);
                echo json_encode([
                    'success' => true, 
                    'isFavorite' => $isFavorite,
                    'favoritesCount' => count($_SESSION['session_favorites'])
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
        }
    } catch (Exception $e) {
        error_log("Erro nos favoritos de sessão: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro no servidor']);
    }
}

function processDatabaseFavorites($userId, $data) {
    global $conn;
    
    $productId = (int)$data['product_id'];
    $action = $data['action'];
    
    try {
        $conn->begin_transaction();
        
        switch ($action) {
            case 'add':
                // Verificar se o produto já existe nos favoritos
                $stmt = $conn->prepare("SELECT id FROM favoritos WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param("ii", $userId, $productId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 0) {
                    // Inserir novo favorito
                    $insertStmt = $conn->prepare("INSERT INTO favoritos (user_id, product_id) VALUES (?, ?)");
                    $insertStmt->bind_param("ii", $userId, $productId);
                    $insertStmt->execute();
                    
                    // Registrar ação do usuário
                    $actionDetails = "Produto ID: $productId adicionado aos favoritos";
                    logUserAction($userId, "add_favorite", $actionDetails);
                }
                
                // Obter contagem atual de favoritos
                $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM favoritos WHERE user_id = ?");
                $countStmt->bind_param("i", $userId);
                $countStmt->execute();
                $countResult = $countStmt->get_result()->fetch_assoc();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Produto adicionado aos favoritos',
                    'favoritesCount' => $countResult['count']
                ]);
                break;
                
            case 'remove':
                // Remover dos favoritos
                $stmt = $conn->prepare("DELETE FROM favoritos WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param("ii", $userId, $productId);
                $stmt->execute();
                
                // Registrar ação do usuário
                $actionDetails = "Produto ID: $productId removido dos favoritos";
                logUserAction($userId, "remove_favorite", $actionDetails);
                
                // Obter contagem atual de favoritos
                $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM favoritos WHERE user_id = ?");
                $countStmt->bind_param("i", $userId);
                $countStmt->execute();
                $countResult = $countStmt->get_result()->fetch_assoc();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Produto removido dos favoritos',
                    'favoritesCount' => $countResult['count']
                ]);
                break;
                
            case 'check':
                // Verificar se o produto está nos favoritos
                $stmt = $conn->prepare("SELECT id FROM favoritos WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param("ii", $userId, $productId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $isFavorite = ($result->num_rows > 0);
                
                // Obter contagem total de favoritos
                $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM favoritos WHERE user_id = ?");
                $countStmt->bind_param("i", $userId);
                $countStmt->execute();
                $countResult = $countStmt->get_result()->fetch_assoc();
                
                echo json_encode([
                    'success' => true, 
                    'isFavorite' => $isFavorite,
                    'favoritesCount' => $countResult['count']
                ]);
                break;
                
            default:
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
                exit;
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Erro nos favoritos do banco de dados: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro no servidor']);
    }
}

// Função para registrar ações do usuário
function logUserAction($userId, $action, $details = null) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("INSERT INTO user_actions_log (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userId, $action, $details);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Erro ao registrar ação do usuário: " . $e->getMessage());
    }
}