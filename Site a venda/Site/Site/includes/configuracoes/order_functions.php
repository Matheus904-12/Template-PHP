<?php

declare(strict_types=1);

// Garante que este arquivo não seja acessado diretamente
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    die('Acesso direto não permitido.');
}

// Incluir dependências se necessário (ex: getDbConnection se não for global)
require_once '../../../adminView/config/getDbConnection.php'; // Ajuste o caminho se necessário

/**
 * Cria um novo pedido no banco de dados com base nos dados do carrinho e envio.
 *
 * @param int $user_id ID do usuário.
 * @param array $cart Dados do carrinho (contendo 'items' e 'subtotal').
 * @param array $shipping_data Dados de envio.
 * @param float $total_amount Valor total do pedido (incluindo frete).
 * @param string $payment_type Método de pagamento selecionado.
 *
 * @return int|false ID do pedido criado ou false em caso de falha.
 */
function createOrder(int $user_id, array $cart, array $shipping_data, float $total_amount, string $payment_type): int|false
{
    if ($user_id <= 0 || empty($cart['items']) || empty($shipping_data) || $total_amount <= 0) {
        error_log("Tentativa de criar pedido com dados inválidos para user_id: $user_id");
        return false;
    }

    $db = getDbConnection(); // Assume que getDbConnection está disponível

    try {
        // Os nomes das colunas aqui devem corresponder EXATAMENTE aos da sua tabela 'orders'
        $stmtOrder = $db->prepare("
            INSERT INTO orders (
                user_id, total, subtotal, shipping, discount, payment_method, status, payment_status,
                shipping_address, shipping_number, shipping_cep, shipping_complement,
                -- Adicione outras colunas de endereço se necessário (city, state, name, email, phone)
                shipping_name, shipping_email, shipping_phone, shipping_city, shipping_state,
                order_date, updated_at
            ) VALUES (
                :user_id, :total, :subtotal, :shipping, :discount, :payment_method, :status, :payment_status,
                :shipping_address, :shipping_number, :shipping_cep, :shipping_complement,
                :shipping_name, :shipping_email, :shipping_phone, :shipping_city, :shipping_state,
                NOW(), NOW()
            )
        ");

        // Calcular frete novamente ou pegar de $cart se já calculado
        $shipping_cost = calculateShippingCost($shipping_data['cep'] ?? ''); // Ou use um valor fixo/calculado previamente
        $subtotal = $cart['subtotal'] ?? 0.00; // Pegar subtotal do carrinho
        $discount = $cart['discount'] ?? 0.00; // Pegar desconto do carrinho

        // Definir status inicial
        $initial_status = 'pending'; // Status geral do pedido
        $initial_payment_status = 'pending'; // Status específico do pagamento

        $stmtOrder->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmtOrder->bindValue(':total', $total_amount, PDO::PARAM_STR); // PDO prefere string para decimais
        $stmtOrder->bindValue(':subtotal', $subtotal, PDO::PARAM_STR);
        $stmtOrder->bindValue(':shipping', $shipping_cost, PDO::PARAM_STR);
        $stmtOrder->bindValue(':discount', $discount, PDO::PARAM_STR);
        $stmtOrder->bindValue(':payment_method', $payment_type, PDO::PARAM_STR);
        $stmtOrder->bindValue(':status', $initial_status, PDO::PARAM_STR);
        $stmtOrder->bindValue(':payment_status', $initial_payment_status, PDO::PARAM_STR);
        $stmtOrder->bindValue(':shipping_address', $shipping_data['address'], PDO::PARAM_STR);
        $stmtOrder->bindValue(':shipping_number', $shipping_data['number'], PDO::PARAM_STR);
        $stmtOrder->bindValue(':shipping_cep', $shipping_data['cep'], PDO::PARAM_STR);
        $stmtOrder->bindValue(':shipping_complement', $shipping_data['complement'], PDO::PARAM_STR);
        $stmtOrder->bindValue(':shipping_name', $shipping_data['name'], PDO::PARAM_STR);
        $stmtOrder->bindValue(':shipping_email', $shipping_data['email'], PDO::PARAM_STR);
        $stmtOrder->bindValue(':shipping_phone', $shipping_data['phone'], PDO::PARAM_STR);
        $stmtOrder->bindValue(':shipping_city', $shipping_data['city'], PDO::PARAM_STR);
        $stmtOrder->bindValue(':shipping_state', $shipping_data['state'], PDO::PARAM_STR);

        if (!$stmtOrder->execute()) {
            error_log("Falha ao executar inserção na tabela orders.");
            return false;
        }

        $order_id = (int)$db->lastInsertId();

        // Inserir itens do pedido na tabela 'order_items' (ou similar)
        // Você PRECISA ter uma tabela para os itens de cada pedido
        $stmtItems = $db->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase)
            VALUES (:order_id, :product_id, :quantity, :price)
        ");

        foreach ($cart['items'] as $item) {
            if (!isset($item['id']) || !isset($item['quantity']) || !isset($item['price'])) continue; // Pular itens malformados

            $stmtItems->bindValue(':order_id', $order_id, PDO::PARAM_INT);
            $stmtItems->bindValue(':product_id', $item['id'], PDO::PARAM_INT);
            $stmtItems->bindValue(':quantity', $item['quantity'], PDO::PARAM_INT);
            $stmtItems->bindValue(':price', $item['price'], PDO::PARAM_STR); // Preço no momento da compra
            $stmtItems->execute();
        }

        return $order_id;
    } catch (PDOException $e) {
        error_log("Erro de banco de dados ao criar pedido: " . $e->getMessage());
        // Se estiver dentro de uma transação no script principal, o rollback cuidará disso.
        return false;
    } catch (Exception $e) {
        error_log("Erro geral ao criar pedido: " . $e->getMessage());
        return false;
    }
}

/**
 * PLACEHOLDER: Recupera os itens do carrinho do usuário.
 * IMPLEMENTE a lógica para buscar do banco de dados (tabela 'carrinho' ou 'user_cart').
 *
 * @param int $user_id ID do usuário.
 * @return array Array com 'items' e 'subtotal', ou array vazio.
 */
function getCartItems(int $user_id): array
{
    error_log(">>> PLACEHOLDER: Simulando busca de itens do carrinho para user_id: $user_id");
    // IMPLEMENTAR LÓGICA REAL AQUI:
    // 1. Conectar ao DB.
    // 2. SELECT c.product_id, c.quantity, p.nome, p.preco, p.imagem
    //    FROM carrinho c JOIN produtos p ON c.product_id = p.id WHERE c.user_id = :user_id
    // 3. Calcular subtotal.
    // 4. Retornar ['items' => [...], 'subtotal' => X.XX]

    // Exemplo de retorno simulado:
    return [
        'items' => [
            ['id' => 1, 'name' => 'Produto Teste 1', 'quantity' => 2, 'price' => 50.00, 'image' => 'img/prod1.jpg'],
            ['id' => 5, 'name' => 'Produto Teste 2', 'quantity' => 1, 'price' => 120.50, 'image' => 'img/prod2.jpg']
        ],
        'subtotal' => (2 * 50.00) + 120.50 // 220.50
    ];
}

/**
 * PLACEHOLDER: Calcula o valor total do pedido (Subtotal + Frete - Desconto).
 * Esta função pode ser simples ou complexa dependendo das suas regras.
 *
 * @param array $cart Carrinho retornado por getCartItems.
 * @return float Valor total.
 */
function calculateOrderTotal(array $cart): float
{
    error_log(">>> PLACEHOLDER: Calculando total do pedido");
    // IMPLEMENTAR LÓGICA REAL AQUI:
    // Pode já incluir frete e desconto se calculados em getCartItems, ou calcular aqui.
    $subtotal = $cart['subtotal'] ?? 0.00;
    $discount = $cart['discount'] ?? 0.00; // Ex: buscar cupom aplicado
    // $shipping = calcular frete aqui ou pegar do $cart se já tiver
    return (float)($subtotal - $discount); // Exemplo simples SEM frete
}

/**
 * PLACEHOLDER: Calcula o custo do frete baseado no CEP.
 * IMPLEMENTE a lógica real (consulta a API dos Correios, tabela de frete, etc.).
 *
 * @param string $cep CEP de destino.
 * @return float Custo do frete.
 */
function calculateShippingCost(string $cep): float
{
    error_log(">>> PLACEHOLDER: Simulando cálculo de frete para CEP: $cep");
    // IMPLEMENTAR LÓGICA REAL AQUI:
    // 1. Limpar CEP.
    // 2. Consultar API externa (Correios, Melhor Envio) OU tabela interna de frete.
    // 3. Retornar o valor calculado.

    // Exemplo de valor fixo simulado:
    return 15.50;
}


/**
 * PLACEHOLDER: Limpa o carrinho do usuário após o pedido ser concluído.
 * IMPLEMENTE a lógica para remover itens da tabela 'carrinho' ou 'user_cart'.
 *
 * @param int $user_id ID do usuário.
 * @return bool True se limpou com sucesso, false caso contrário.
 */
function clearCart(int $user_id): bool
{
    error_log(">>> PLACEHOLDER: Simulando limpeza do carrinho para user_id: $user_id");
    // IMPLEMENTAR LÓGICA REAL AQUI:
    // 1. Conectar ao DB.
    // 2. DELETE FROM carrinho WHERE user_id = :user_id
    // 3. Retornar true/false baseado no sucesso.
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("DELETE FROM carrinho WHERE user_id = :user_id"); // Use o nome correto da sua tabela de carrinho
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erro ao limpar carrinho para user_id $user_id: " . $e->getMessage());
        return false;
    }
}

/**
 * PLACEHOLDER: Envia email de confirmação do PEDIDO para o cliente.
 * IMPLEMENTE a lógica real usando uma biblioteca de email.
 *
 * @param int $order_id ID do pedido criado.
 * @param int $user_id ID do usuário.
 * @return void
 */
function sendOrderConfirmationEmail(int $order_id, int $user_id): void
{
    error_log(">>> PLACEHOLDER: Simulando envio de email de confirmação de PEDIDO #$order_id para user_id: $user_id");
    // IMPLEMENTAR LÓGICA REAL AQUI:
    // 1. Buscar dados do pedido e do usuário (incluindo email).
    // 2. Montar corpo do email com detalhes do pedido (itens, endereço, total).
    // 3. Usar biblioteca de email para enviar.
}


/**
 * PLACEHOLDER: Registra uma atividade ou log relacionado a um pedido.
 * IMPLEMENTE a lógica para salvar em uma tabela de logs (ex: 'order_logs').
 *
 * @param int $order_id ID do pedido.
 * @param string $activity Descrição da atividade.
 * @return void
 */
function logOrderActivity(int $order_id, string $activity): void
{
    error_log(">>> Log Pedido #$order_id: $activity");
    // IMPLEMENTAR LÓGICA REAL AQUI (Opcional):
    // 1. Conectar ao DB.
    // 2. INSERT INTO order_logs (order_id, activity, log_time) VALUES (:order_id, :activity, NOW())
}

/**
 * Atualiza informações específicas de PAGAMENTO de um pedido no banco de dados.
 * (Esta função já foi definida antes, certifique-se que está aqui ou incluída corretamente)
 * Copiada da resposta anterior para garantir que esteja disponível.
 *
 * @param int $order_id O ID do pedido.
 * @param array $payment_data Um array associativo com os campos a serem atualizados (ex: ['payment_status' => 'approved', 'paid_at' => '...', 'transaction_id' => '...']).
 * @return bool Retorna true se a atualização foi bem-sucedida, false caso contrário.
 */
function updateOrderPaymentInfo(int $order_id, array $payment_data): bool
{
    if ($order_id <= 0 || empty($payment_data)) return false;
    // Campos permitidos para atualização via esta função
    // Adicione 'pix_code', 'pix_expiration', 'boleto_url', 'boleto_barcode', 'boleto_expiration', 'last_4_digits', 'card_id' conforme necessário
    $allowed_fields = [
        'payment_status',
        'paid_at',
        'transaction_id',
        'pix_expiration',
        'pix_code',
        'boleto_url',
        'boleto_barcode',
        'boleto_expiration',
        'last_4_digits',
        'card_id'
    ];
    $fields_to_update = [];
    $params = [':order_id' => $order_id];

    foreach ($payment_data as $key => $value) {
        if (in_array($key, $allowed_fields)) {
            $fields_to_update[] = "`" . $key . "` = :" . $key;
            $params[':' . $key] = $value;
        }
    }

    if (empty($fields_to_update)) {
        error_log("Nenhum campo válido fornecido para atualizar pagamento do pedido ($order_id).");
        return false;
    }
    $set_clause = implode(', ', $fields_to_update);

    try {
        $db = getDbConnection();
        $sql = "UPDATE orders SET " . $set_clause . ", updated_at = NOW() WHERE id = :order_id";
        $stmt = $db->prepare($sql);

        foreach ($params as $param_key => $param_value) {
            $param_type = PDO::PARAM_STR;
            if ($param_key === ':order_id') $param_type = PDO::PARAM_INT;
            elseif ($param_value === null) $param_type = PDO::PARAM_NULL;
            $stmt->bindValue($param_key, $param_value, $param_type);
        }
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erro ao atualizar informações de pagamento do pedido ($order_id): " . $e->getMessage());
        return false;
    }
}
