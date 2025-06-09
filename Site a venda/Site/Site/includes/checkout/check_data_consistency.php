<?php
// includes/check_data_consistency.php
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

// Obter o corpo da requisição
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Verificar se os dados necessários foram enviados
if (!isset($data['session_id']) || !isset($data['local_data'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Parâmetros insuficientes'
    ]);
    exit;
}

$session_id = $data['session_id'];
$local_data = $data['local_data'];

// Conexão com o banco de dados
require_once '../../../adminView/config/dbconnect.php';

try {
    // Buscar dados do servidor
    $stmt = $pdo->prepare("SELECT data FROM checkout_sessions WHERE session_id = ?");
    $stmt->execute([$session_id]);
    $server_data_row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$server_data_row) {
        // Não há dados no servidor, cliente deve enviar tudo
        echo json_encode([
            'status' => 'inconsistent',
            'message' => 'Dados não encontrados no servidor'
        ]);
        exit;
    }
    
    $server_data = json_decode($server_data_row['data'], true);
    
    // Verificar consistência dos dados importantes
    $is_consistent = true;
    $inconsistent_fields = [];
    
    // Verificar método de pagamento
    if (isset($local_data['payment_method']) && isset($server_data['payment_method'])) {
        if ($local_data['payment_method'] !== $server_data['payment_method']) {
            $is_consistent = false;
            $inconsistent_fields[] = 'payment_method';
        }
    }
    
    // Verificar outros campos importantes
    // ... adicione mais verificações conforme necessário
    
    if ($is_consistent) {
        echo json_encode([
            'status' => 'consistent',
            'message' => 'Dados consistentes'
        ]);
    } else {
        echo json_encode([
            'status' => 'inconsistent',
            'message' => 'Inconsistência detectada',
            'fields' => $inconsistent_fields
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao verificar consistência: ' . $e->getMessage()
    ]);
}
?>