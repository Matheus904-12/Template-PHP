<?php
// Iniciar a sessão (necessário para acessar o carrinho e o usuário logado)
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    $response = [
        'status' => 'error',
        'message' => 'Usuário não autenticado'
    ];
    echo json_encode($response);
    exit;
}

// Configurações do banco de dados
require_once '../adminView/config/dbconnect.php';

// Verificar se é uma solicitação POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response = [
        'status' => 'error',
        'message' => 'Método não permitido'
    ];
    echo json_encode($response);
    exit;
}

// Verificar se há itens no carrinho
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    $response = [
        'status' => 'error',
        'message' => 'Carrinho vazio'
    ];
    echo json_encode($response);
    exit;
}

// Recuperar os dados do formulário
$shipping_address = $_POST['shipping_address'] ?? '';
$shipping_number = $_POST['shipping_number'] ?? '';
$shipping_complement = $_POST['shipping_complement'] ?? '';
$shipping_cep = $_POST['shipping_cep'] ?? '';
$shipping_phone = $_POST['shipping_phone'] ?? '';
$payment_method = $_POST['payment_method'] ?? '';

// Validações básicas
if (empty($shipping_address) || empty($shipping_number) || empty($shipping_cep) || empty($shipping_phone)) {
    $response = [
        'status' => 'error',
        'message' => 'Preencha todos os campos obrigatórios'
    ];
    echo json_encode($response);
    exit;
}

// Validar método de pagamento
if ($payment_method !== 'credit_card' && $payment_method !== 'pix') {
    $response = [
        'status' => 'error',
        'message' => 'Método de pagamento inválido'
    ];
    echo json_encode($response);
    exit;
}

try {
    // Iniciar transação
    $db->beginTransaction();
    
    // Dados do usuário
    $user_id = $_SESSION['user_id'];
    
    // Calcular valores do pedido
    $cart_items = $_SESSION['cart'];
    $subtotal = 0;
    $shipping_cost = 10.00; // Valor padrão de frete
    $discount = 0.00;       // Desconto
    
    // Calcular subtotal
    foreach ($cart_items as $item_id => $item) {
        $subtotal += $item['preco'] * $item['quantity'];
    }
    
    // Aplicar possíveis descontos (pode ser implementado futuramente)
    // if (isset($_SESSION['discount'])) { $discount = $_SESSION['discount']; }
    
    // Calcular total
    $total = $subtotal + $shipping_cost - $discount;
    
    // Determinar status do pedido baseado no método de pagamento
    $order_status = ($payment_method === 'pix') ? 'pending_payment' : 'processing';
    
    // Gerar código de rastreio único
    $tracking_code = 'TRK' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    
    // Inserir pedido na tabela "pedidos"
    $sql_insert_order = "INSERT INTO pedidos (
        user_id, 
        endereco_entrega, 
        numero_entrega, 
        complemento_entrega, 
        cep_entrega, 
        telefone_contato, 
        forma_pagamento, 
        subtotal, 
        frete, 
        desconto, 
        total, 
        codigo_rastreio, 
        status
    ) VALUES (
        :user_id, 
        :endereco, 
        :numero, 
        :complemento, 
        :cep, 
        :telefone, 
        :pagamento, 
        :subtotal, 
        :frete, 
        :desconto, 
        :total, 
        :rastreio, 
        :status
    )";
    
    $stmt = $db->prepare($sql_insert_order);
    $stmt->execute([
        ':user_id' => $user_id,
        ':endereco' => $shipping_address,
        ':numero' => $shipping_number,
        ':complemento' => $shipping_complement,
        ':cep' => $shipping_cep,
        ':telefone' => $shipping_phone,
        ':pagamento' => $payment_method,
        ':subtotal' => $subtotal,
        ':frete' => $shipping_cost,
        ':desconto' => $discount,
        ':total' => $total,
        ':rastreio' => $tracking_code,
        ':status' => $order_status
    ]);
    
    // Obter o ID do pedido inserido
    $order_id = $db->lastInsertId();
    
    // Processar informações do cartão se o método for cartão de crédito
    if ($payment_method === 'credit_card') {
        // Se o usuário escolheu um cartão já salvo
        if (isset($_POST['card_id']) && !empty($_POST['card_id'])) {
            $card_id = $_POST['card_id'];
            
            // Registrar transação usando o cartão salvo
            $sql_insert_transaction = "INSERT INTO transacoes (
                pedido_id, cartao_id, status, valor
            ) VALUES (
                :pedido_id, :cartao_id, 'approved', :valor
            )";
            
            $stmt = $db->prepare($sql_insert_transaction);
            $stmt->execute([
                ':pedido_id' => $order_id,
                ':cartao_id' => $card_id,
                ':valor' => $total
            ]);
        } 
        // Se o usuário está usando um novo cartão
        else if (isset($_POST['card_number']) && !empty($_POST['card_number'])) {
            $card_number = preg_replace('/\D/', '', $_POST['card_number']);
            $card_last4 = substr($card_number, -4);
            $card_name = $_POST['card_name'] ?? '';
            $card_expiry = $_POST['card_expiry'] ?? '';
            
            // Se o usuário deseja salvar o cartão
            if (isset($_POST['save_card']) && $_POST['save_card'] == 'on') {
                $sql_insert_card = "INSERT INTO cartoes (
                    user_id, card_name, card_last4, card_expiry
                ) VALUES (
                    :user_id, :card_name, :card_last4, :card_expiry
                )";
                
                $stmt = $db->prepare($sql_insert_card);
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':card_name' => $card_name,
                    ':card_last4' => $card_last4,
                    ':card_expiry' => $card_expiry
                ]);
                
                $card_id = $db->lastInsertId();
            } else {
                $card_id = null;
            }
            
            // Registrar transação
            $sql_insert_transaction = "INSERT INTO transacoes (
                pedido_id, card_last4, card_expiry, status, valor
            ) VALUES (
                :pedido_id, :card_last4, :card_expiry, 'approved', :valor
            )";
            
            $stmt = $db->prepare($sql_insert_transaction);
            $stmt->execute([
                ':pedido_id' => $order_id,
                ':card_last4' => $card_last4,
                ':card_expiry' => $card_expiry,
                ':valor' => $total
            ]);
        }
    } 
    // Se for PIX, gera uma transação pendente
    else if ($payment_method === 'pix') {
        // Gerar código PIX único para pagamento
        $pix_code = 'PIX' . uniqid();
        
        // Registrar transação PIX pendente
        $sql_insert_pix = "INSERT INTO transacoes_pix (
            pedido_id, codigo_pix, status, valor, data_expiracao
        ) VALUES (
            :pedido_id, :codigo_pix, 'pending', :valor, DATE_ADD(NOW(), INTERVAL 30 MINUTE)
        )";
        
        $stmt = $db->prepare($sql_insert_pix);
        $stmt->execute([
            ':pedido_id' => $order_id,
            ':codigo_pix' => $pix_code,
            ':valor' => $total
        ]);
    }
    
    // Inserir itens do pedido na tabela "itens_pedido"
    foreach ($cart_items as $item_id => $item) {
        $sql_insert_item = "INSERT INTO itens_pedido (
            pedido_id, produto_id, quantidade, preco_unitario
        ) VALUES (
            :pedido_id, :produto_id, :quantidade, :preco_unitario
        )";
        
        $stmt = $db->prepare($sql_insert_item);
        $stmt->execute([
            ':pedido_id' => $order_id,
            ':produto_id' => $item_id,
            ':quantidade' => $item['quantity'],
            ':preco_unitario' => $item['preco']
        ]);
        
        // Atualizar estoque do produto
        $sql_update_stock = "UPDATE produtos 
                             SET estoque = estoque - :quantidade 
                             WHERE id = :produto_id";
        
        $stmt = $db->prepare($sql_update_stock);
        $stmt->execute([
            ':quantidade' => $item['quantity'],
            ':produto_id' => $item_id
        ]);
    }
    
    // Completar transação
    $db->commit();
    
    // Limpar carrinho após finalização bem-sucedida
    $_SESSION['cart'] = [];
    
    // Preparar resposta com base no método de pagamento
    if ($payment_method === 'pix') {
        // Gerar QR Code para PIX (exemplo)
        $qrcode_url = 'img/qrcode/' . $pix_code . '.png';
        
        // Simulação da geração do QR code - em um sistema real, isso seria gerado pela API do banco
        // Por enquanto, vamos usar um placeholder
        $qrcode_url = 'img/qrcode-placeholder.png';
        
        $response = [
            'status' => 'success',
            'message' => 'Pedido criado com sucesso! Aguardando pagamento via PIX.',
            'order_id' => $order_id,
            'pix_qrcode' => $qrcode_url,
            'pix_code' => $pix_code
        ];
    } else {
        $response = [
            'status' => 'success',
            'message' => 'Pedido processado com sucesso!',
            'order_id' => $order_id,
            'redirect_url' => 'confirmacao.php?order_id=' . $order_id
        ];
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    // Reverter transação em caso de erro
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    $response = [
        'status' => 'error',
        'message' => 'Erro ao processar pedido: ' . $e->getMessage()
    ];
    
    echo json_encode($response);
}
?>