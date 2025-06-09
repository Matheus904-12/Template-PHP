<?php
// ../controller/rastreioController.php
$path1 = '../config/dbconnect.php';
$path2 = './config/dbconnect.php';

if (file_exists($path1)) {
    include_once $path1;
} elseif (file_exists($path2)) {
    include_once $path2;
} else {
    die("Erro: Arquivo dbconnect.php não encontrado em nenhum dos caminhos especificados.");
}

/**
 * Obtém todos os dados de rastreamento dos pedidos
 *
 * @return array Lista de informações de rastreamento
 */
function getRastreioPedidos() {
    global $conn;

    $sql = "SELECT o.id as order_id, o.tracking_code, o.status, o.order_date, o.shipping_address, o.user_id
            FROM orders o
            WHERE o.tracking_code IS NOT NULL AND o.tracking_code != ''
            ORDER BY o.order_date DESC";

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        die("Erro na consulta SQL: " . mysqli_error($conn));
    }

    $rastreio = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Formatar a data para exibição
        $row['order_date'] = date('d/m/Y H:i', strtotime($row['order_date']));

        // Traduzir o status para um formato amigável
        $row['status'] = traduzirStatus($row['status']);

        $rastreio[] = $row;
    }

    return $rastreio;
}

/**
 * Obtém informações de rastreamento de um pedido específico
 *
 * @param int $orderId ID do pedido
 * @return array|null Informações de rastreamento ou null se não encontrado
 */
function getRastreioPedido($orderId) {
    global $conn;

    $orderId = filter_var($orderId, FILTER_VALIDATE_INT);
    if (!$orderId) {
        return null;
    }

    $sql = "SELECT o.id as order_id, o.tracking_code, o.status, o.order_date,
                    o.shipping_address, o.shipping_number, o.shipping_cep, o.shipping_complement,
                    o.total, o.subtotal, o.shipping, o.discount, o.payment_method, o.card_last4, o.updated_at
            FROM orders o
            WHERE o.id = ? AND o.tracking_code IS NOT NULL AND o.tracking_code != ''";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Erro na preparação da consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows === 0) {
        return null;
    }

    $rastreio = $result->fetch_assoc();

    // Formatar a data
    $rastreio['order_date'] = date('d/m/Y H:i', strtotime($rastreio['order_date']));
    if (isset($rastreio['updated_at'])) {
        $rastreio['updated_at'] = date('d/m/Y H:i', strtotime($rastreio['updated_at']));
    }

    // Traduzir o status
    $rastreio['status'] = traduzirStatus($rastreio['status']);

    $stmt->close();
    return $rastreio;
}

/**
 * Atualiza o código de rastreamento de um pedido
 *
 * @param int $orderId ID do pedido
 * @param string $trackingCode Novo código de rastreamento
 * @return bool Sucesso da operação
 */
function atualizarCodigoRastreio($orderId, $trackingCode) {
    global $conn;

    $orderId = filter_var($orderId, FILTER_VALIDATE_INT);
    if (!$orderId) {
        return false;
    }

    // Validação básica do código de rastreamento
    $trackingCode = trim(filter_var($trackingCode, FILTER_SANITIZE_STRING));
    if (empty($trackingCode)) {
        return false;
    }

    $sql = "UPDATE orders SET tracking_code = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("si", $trackingCode, $orderId);
    $result = $stmt->execute();

    $stmt->close();
    return $result;
}

/**
 * Traduz os status dos pedidos para termos mais amigáveis
 *
 * @param string $status Status do pedido no banco de dados
 * @return string Status traduzido
 */
function traduzirStatus($status) {
    $statusMap = [
        'processando' => 'Processando',
        'aguardando_pagamento' => 'Aguardando Pagamento',
        'pago' => 'Pago',
        'enviado' => 'Enviado',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
        'aceito' => 'Aceito',
        'em_alerta' => 'Em Alerta'
    ];

    return isset($statusMap[$status]) ? $statusMap[$status] : ucfirst(str_replace('_', ' ', $status));
}

/**
 * Gera URL para rastreamento de pacote nos Correios
 *
 * @param string $trackingCode Código de rastreamento
 * @return string URL de rastreamento
 */
function getURLRastreio($trackingCode) {
    if (empty($trackingCode)) {
        return '#';
    }

    // URL para rastreamento nos Correios
    return 'https://rastreamento.correios.com.br/app/index.php?codigo=' . urlencode($trackingCode);
}
?>