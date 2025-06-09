<?php
// save_checkout_data.php
header('Content-Type: application/json');

// Verificar se a requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

// Obter dados do formulário
$field = $_POST['field'] ?? '';
$value = $_POST['value'] ?? '';
$sessionId = $_POST['session_id'] ?? '';

// Validar dados
if (empty($field) || empty($sessionId)) {
    echo json_encode(['status' => 'error', 'message' => 'Campos obrigatórios não preenchidos']);
    exit;
}

// Lista de campos permitidos
$allowedFields = [
    'shipping_address', 'shipping_number', 'shipping_complement', 'shipping_cep',
    'shipping_phone', 'shipping_name', 'shipping_email', 'shipping_city',
    'shipping_state', 'billing_same_as_shipping', 'notes', 'payment_method',
    'payment_preference', 'card_preference'
];

// Verificar se o campo é permitido
if (!in_array($field, $allowedFields)) {
    echo json_encode(['status' => 'error', 'message' => 'Campo não permitido']);
    exit;
}

// Iniciar sessão para armazenar dados temporariamente
session_start();

// Criar array para dados de checkout se não existir
if (!isset($_SESSION['checkout_data'])) {
    $_SESSION['checkout_data'] = [];
}

// Criar array para a sessão específica se não existir
if (!isset($_SESSION['checkout_data'][$sessionId])) {
    $_SESSION['checkout_data'][$sessionId] = [];
}

// Salvar valor no array de sessão
$_SESSION['checkout_data'][$sessionId][$field] = $value;

// Opcionalmente, salvar em banco de dados para persistência
try {
    // Conectar ao banco de dados (substitua pelos seus dados)
    $pdo = new PDO('mysql:host=localhost;dbname=seu_banco', 'usuario', 'senha');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar se já existe um registro para atualizar
    $stmt = $pdo->prepare("SELECT id FROM checkout_data WHERE session_id = ? AND field_name = ?");
    $stmt->execute([$sessionId, $field]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Atualizar registro existente
        $stmt = $pdo->prepare("UPDATE checkout_data SET field_value = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$value, $existing['id']]);
    } else {
        // Inserir novo registro
        $stmt = $pdo->prepare("INSERT INTO checkout_data (session_id, field_name, field_value, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$sessionId, $field, $value]);
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Dados salvos com sucesso']);
} catch (PDOException $e) {
    // Em caso de erro no banco de dados, ainda retornamos sucesso
    // porque os dados foram salvos na sessão
    error_log('Erro ao salvar no banco: ' . $e->getMessage());
    echo json_encode(['status' => 'success', 'message' => 'Dados salvos temporariamente']);
}
?>