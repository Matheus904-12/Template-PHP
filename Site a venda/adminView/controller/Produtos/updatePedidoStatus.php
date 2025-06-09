<?php
// Desativar a exibição de erros para evitar que mensagens de erro HTML se misturem com JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Definir cabeçalho JSON antes de qualquer saída
header('Content-Type: application/json');

try {
    // Verificar se é uma requisição POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido. Apenas POST é permitido.');
    }

    // Verificar se os parâmetros necessários estão presentes
    if (!isset($_POST['order_id']) || !isset($_POST['status'])) {
        throw new Exception('Parâmetros order_id e status são obrigatórios.');
    }

    // Incluir o arquivo de conexão e funções - verificando se os arquivos existem
    $dbconnectFile = '../../config/dbconnect.php';
    $controllerFile = 'pedidosController.php';

    if (!file_exists($dbconnectFile)) {
        throw new Exception("Arquivo de conexão não encontrado: $dbconnectFile");
    }
    
    if (!file_exists($controllerFile)) {
        throw new Exception("Arquivo do controlador não encontrado: $controllerFile");
    }

    // Incluir os arquivos necessários
    require_once $dbconnectFile;
    require_once $controllerFile;

    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $status = $_POST['status'];

    // Validar os dados recebidos
    if (!$orderId) {
        throw new Exception('ID do pedido inválido.');
    }

    // Lista de status permitidos
    $statusPermitidos = ['Aceito', 'Em Alerta', 'Processando', 'Aguardando Pagamento', 'Pago', 'Enviado', 'Entregue', 'Cancelado'];

    if (!in_array($status, $statusPermitidos)) {
        throw new Exception('Status inválido.');
    }

    // Mapear o status exibido para o valor armazenado no banco
    $statusMap = [
        'Processando' => 'processando',
        'Aguardando Pagamento' => 'aguardando_pagamento',
        'Pago' => 'pago',
        'Enviado' => 'enviado',
        'Entregue' => 'entregue',
        'Cancelado' => 'cancelado',
        'Aceito' => 'aceito',
        'Em Alerta' => 'em_alerta'
    ];

    // Converter o status para o formato do banco de dados
    $dbStatus = isset($statusMap[$status]) ? $statusMap[$status] : strtolower(str_replace(' ', '_', $status));

    // Verificar se a função existe
    if (!function_exists('getPedidoDetalhes')) {
        throw new Exception('Função getPedidoDetalhes não encontrada.');
    }

    // Verificar se o pedido existe
    $pedido = getPedidoDetalhes($orderId);
    if (!$pedido) {
        throw new Exception('Pedido não encontrado.');
    }

    // Verificar se a função existe
    if (!function_exists('atualizarStatusPedido')) {
        throw new Exception('Função atualizarStatusPedido não encontrada.');
    }

    // Atualizar o status do pedido
    $resultado = atualizarStatusPedido($orderId, $dbStatus);

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => "Status do pedido #$orderId atualizado para $status com sucesso."
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao atualizar o status do pedido.'
        ]);
    }

} catch (Exception $e) {
    // Capturar qualquer erro e retornar como JSON
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => 'Erro no processamento da requisição'
    ]);
}
?>