<?php
// includes/save_checkout_data_batch.php
header('Content-Type: application/json');

// Iniciar sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se a requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Método não permitido'
    ]);
    exit;
}

// Verificar se todos os parâmetros necessários foram enviados
if (!isset($_POST['session_id']) || !isset($_POST['all_data']) || !isset($_POST['action'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Parâmetros insuficientes'
    ]);
    exit;
}

// Obter os dados
$session_id = $_POST['session_id'];
$all_data = json_decode($_POST['all_data'], true);
$action = $_POST['action'];

// Verificar se o decode do JSON foi bem-sucedido
if ($all_data === null && json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Falha ao decodificar dados JSON: ' . json_last_error_msg()
    ]);
    exit;
}

// Conexão com o banco de dados (ajuste as credenciais conforme necessário)
require_once '../../adminView/config/dbconnect.php';

// Verificar a ação
if ($action === 'save_all') {
    // Registrar os dados em lote
    try {
        // Iniciar transação para garantir consistência
        $pdo->beginTransaction();
        
        // Primeiro verificar se o registro da sessão já existe
        $stmt = $pdo->prepare("SELECT id FROM checkout_sessions WHERE session_id = ?");
        $stmt->execute([$session_id]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            // Atualizar registro existente
            $stmt = $pdo->prepare("UPDATE checkout_sessions SET data = ?, updated_at = NOW() WHERE session_id = ?");
            $stmt->execute([json_encode($all_data), $session_id]);
        } else {
            // Criar novo registro
            $stmt = $pdo->prepare("INSERT INTO checkout_sessions (session_id, data, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $stmt->execute([$session_id, json_encode($all_data)]);
        }
        
        // Também registrar cada campo individualmente para facilitar consultas específicas
        foreach ($all_data as $field => $value) {
            // Verificar se o campo já existe para esta sessão
            $stmt = $pdo->prepare("SELECT id FROM checkout_fields WHERE session_id = ? AND field_name = ?");
            $stmt->execute([$session_id, $field]);
            $fieldExists = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($fieldExists) {
                // Atualizar campo existente
                $stmt = $pdo->prepare("UPDATE checkout_fields SET field_value = ?, updated_at = NOW() WHERE session_id = ? AND field_name = ?");
                $stmt->execute([$value, $session_id, $field]);
            } else {
                // Inserir novo campo
                $stmt = $pdo->prepare("INSERT INTO checkout_fields (session_id, field_name, field_value, created_at, updated_at) 
                                      VALUES (?, ?, ?, NOW(), NOW())");
                $stmt->execute([$session_id, $field, $value]);
            }
        }
        
        // Commit da transação
        $pdo->commit();
        
        // Retornar sucesso
        echo json_encode([
            'status' => 'success',
            'message' => 'Dados salvos com sucesso'
        ]);
    } catch (PDOException $e) {
        // Rollback em caso de erro
        $pdo->rollBack();
        
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao salvar dados: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Ação desconhecida'
    ]);
}
?>