<?php

class OrderController
{
    private $conn;
    private $logger;

    public function __construct($dbConnection)
    {
        $this->conn = $dbConnection;
        $this->initializeLogger();
    }

    private function initializeLogger()
    {
        $logFile = __DIR__ . '/../../logs/order_controller_' . date('Y-m') . '.log';
        $this->logger = new class($logFile) {
            private $logFile;
            
            public function __construct($logFile)
            {
                $this->logFile = $logFile;
                $this->ensureLogDirectoryExists();
            }
            
            private function ensureLogDirectoryExists()
            {
                $logDir = dirname($this->logFile);
                if (!file_exists($logDir)) {
                    mkdir($logDir, 0755, true);
                }
            }
            
            public function log($message, $context = [])
            {
                $logEntry = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
                if (!empty($context)) {
                    $logEntry .= 'Context: ' . json_encode($context, JSON_PRETTY_PRINT) . "\n";
                }
                file_put_contents($this->logFile, $logEntry, FILE_APPEND);
            }
        };
    }

    /**
     * Cria um novo pedido com todos os itens em uma transação atômica
     */
    public function createCompleteOrder(array $orderData, array $items)
    {
        $this->conn->begin_transaction();
        
        try {
            // Validação básica
            if (empty($items)) {
                throw new InvalidArgumentException("Nenhum item no carrinho");
            }
            
            // Cria o pedido principal
            $orderId = $this->createOrder($orderData);
            if (!$orderId) {
                throw new RuntimeException("Falha ao criar pedido principal");
            }
            
            // Adiciona todos os itens
            foreach ($items as $item) {
                if (!$this->addOrderItem(
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price']
                )) {
                    throw new RuntimeException("Falha ao adicionar item ao pedido");
                }
            }
            
            // Se chegou até aqui, commit na transação
            $this->conn->commit();
            
            $this->logger->log("Pedido criado com sucesso", [
                'order_id' => $orderId,
                'user_id' => $orderData['user_id'],
                'total' => $orderData['total']
            ]);
            
            return $orderId;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            $this->logger->log("Erro ao criar pedido completo", [
                'error' => $e->getMessage(),
                'order_data' => $orderData,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function createOrder(array $orderData)
    {
        try {
            // Validação dos campos obrigatórios
            $required = [
                'user_id' => 'integer',
                'total' => 'float',
                'subtotal' => 'float',
                'shipping' => 'float',
                'discount' => 'float',
                'payment_method' => 'string',
                'status' => 'string',
                'shipping_address' => 'string',
                'shipping_cep' => 'string'
            ];
            
            foreach ($required as $field => $type) {
                if (!isset($orderData[$field])) {
                    throw new InvalidArgumentException("Campo obrigatório faltando: $field");
                }
                
                // Validação de tipo básica
                if ($type === 'integer' && !is_numeric($orderData[$field])) {
                    throw new InvalidArgumentException("Campo $field deve ser numérico");
                }
                
                if ($type === 'float' && !is_numeric($orderData[$field])) {
                    throw new InvalidArgumentException("Campo $field deve ser decimal");
                }
            }
            
            // Garante valores padrão para campos opcionais
            $defaults = [
                'shipping_number' => '',
                'shipping_complement' => '',
                'tracking_code' => '',
                'card_last4' => null,
                'payment_id' => '',
                'installments' => 1,
                'card_brand' => ''
            ];
            
            $orderData = array_merge($defaults, $orderData);
            
            // Prepara a query
            $columns = [];
            $placeholders = [];
            $values = [];
            $types = "";
            
            // Mapeamento de tipos para bind_param
            $typeMap = [
                'user_id' => 'i',
                'total' => 'd',
                'subtotal' => 'd',
                'shipping' => 'd',
                'discount' => 'd',
                'payment_method' => 's',
                'status' => 's',
                'shipping_address' => 's',
                'shipping_number' => 's',
                'shipping_cep' => 's',
                'shipping_complement' => 's',
                'tracking_code' => 's',
                'card_last4' => 's',
                'payment_id' => 's',
                'installments' => 'i',
                'card_brand' => 's'
            ];
            
            foreach ($orderData as $field => $value) {
                if (array_key_exists($field, $typeMap)) {
                    $columns[] = "`$field`";
                    $placeholders[] = "?";
                    $values[] = $value;
                    $types .= $typeMap[$field];
                }
            }
            
            $sql = "INSERT INTO `orders` (" . implode(", ", $columns) . ") 
                    VALUES (" . implode(", ", $placeholders) . ")";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException("Erro ao preparar query: " . $this->conn->error);
            }
            
            // Bind dos parâmetros
            $params = array_merge([$types], $values);
            $this->bindParams($stmt, $params);
            
            if (!$stmt->execute()) {
                throw new RuntimeException("Erro ao executar query: " . $stmt->error);
            }
            
            $orderId = $this->conn->insert_id;
            $stmt->close();
            
            $this->logger->log("Pedido criado", [
                'order_id' => $orderId,
                'user_id' => $orderData['user_id'],
                'payment_id' => $orderData['payment_id'],
                'installments' => $orderData['installments']
            ]);
            
            return $orderId;
            
        } catch (Exception $e) {
            $this->logger->log("Erro ao criar pedido", [
                'error' => $e->getMessage(),
                'order_data' => $orderData,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function addOrderItem($orderId, $productId, $quantity, $price)
    {
        try {
            // Validação básica
            if (!is_numeric($orderId) || $orderId <= 0) {
                throw new InvalidArgumentException("ID do pedido inválido");
            }
            
            if (!is_numeric($productId) || $productId <= 0) {
                throw new InvalidArgumentException("ID do produto inválido");
            }
            
            if (!is_numeric($quantity) || $quantity <= 0) {
                throw new InvalidArgumentException("Quantidade inválida");
            }
            
            if (!is_numeric($price) || $price < 0) {
                throw new InvalidArgumentException("Preço inválido");
            }
            
            $sql = "INSERT INTO `order_items` 
                    (`order_id`, `product_id`, `quantity`, `price_at_purchase`) 
                    VALUES (?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException("Erro ao preparar query: " . $this->conn->error);
            }
            
            $stmt->bind_param("iiid", $orderId, $productId, $quantity, $price);
            
            if (!$stmt->execute()) {
                throw new RuntimeException("Erro ao executar query: " . $stmt->error);
            }
            
            $stmt->close();
            
            $this->logger->log("Item adicionado ao pedido", [
                'order_id' => $orderId,
                'product_id' => $productId,
                'quantity' => $quantity
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->log("Erro ao adicionar item ao pedido", [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'product_id' => $productId,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function updateOrder($orderId, array $updateData)
    {
        try {
            // Validação básica
            if (!is_numeric($orderId) || $orderId <= 0) {
                throw new InvalidArgumentException("ID do pedido inválido");
            }
            
            if (empty($updateData)) {
                throw new InvalidArgumentException("Nenhum dado para atualização");
            }
            
            // Colunas permitidas para atualização
            $allowedColumns = [
                'status', 'tracking_code', 'card_last4',
                'shipping_address', 'shipping_number',
                'shipping_cep', 'shipping_complement',
                'payment_method', 'total', 'subtotal',
                'shipping', 'discount', 'payment_id',
                'installments', 'card_brand'
            ];
            
            $setParts = [];
            $values = [];
            $types = "";
            
            foreach ($updateData as $field => $value) {
                if (in_array($field, $allowedColumns)) {
                    $setParts[] = "`$field` = ?";
                    $values[] = $value;
                    
                    // Determina o tipo para bind_param
                    if (in_array($field, ['total', 'subtotal', 'shipping', 'discount'])) {
                        $types .= 'd';
                    } elseif ($field === 'installments') {
                        $types .= 'i';
                    } else {
                        $types .= 's';
                    }
                }
            }
            
            if (empty($setParts)) {
                throw new InvalidArgumentException("Nenhuma coluna válida para atualização");
            }
            
            $sql = "UPDATE `orders` SET " . implode(", ", $setParts) . " 
                    WHERE `id` = ?";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException("Erro ao preparar query: " . $this->conn->error);
            }
            
            // Adiciona o orderId ao final dos valores
            $values[] = $orderId;
            $types .= 'i';
            
            $params = array_merge([$types], $values);
            $this->bindParams($stmt, $params);
            
            if (!$stmt->execute()) {
                throw new RuntimeException("Erro ao executar query: " . $stmt->error);
            }
            
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            
            $this->logger->log("Pedido atualizado", [
                'order_id' => $orderId,
                'affected_rows' => $affectedRows,
                'updated_fields' => array_keys($updateData)
            ]);
            
            return $affectedRows > 0;
            
        } catch (Exception $e) {
            $this->logger->log("Erro ao atualizar pedido", [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'update_data' => $updateData,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    // Métodos auxiliares

    private function bindParams($stmt, $params)
    {
        // Usa reflection para bind_param com número variável de parâmetros
        $reflection = new ReflectionClass('mysqli_stmt');
        $method = $reflection->getMethod('bind_param');
        $method->invokeArgs($stmt, $params);
    }

    public function getOrderById($orderId)
    {
        try {
            if (!is_numeric($orderId) || $orderId <= 0) {
                throw new InvalidArgumentException("ID do pedido inválido");
            }
            
            $sql = "SELECT * FROM `orders` WHERE `id` = ?";
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new RuntimeException("Erro ao preparar query: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $orderId);
            
            if (!$stmt->execute()) {
                throw new RuntimeException("Erro ao executar query: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $order = $result->fetch_assoc();
            $stmt->close();
            
            if (!$order) {
                $this->logger->log("Pedido não encontrado", ['order_id' => $orderId]);
                return null;
            }
            
            return $order;
            
        } catch (Exception $e) {
            $this->logger->log("Erro ao buscar pedido", [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function getOrderItems($orderId)
    {
        try {
            if (!is_numeric($orderId) || $orderId <= 0) {
                throw new InvalidArgumentException("ID do pedido inválido");
            }
            
            $sql = "SELECT oi.*, p.nome as product_name, p.imagem as product_image
                    FROM `order_items` oi
                    JOIN `produtos` p ON oi.product_id = p.id
                    WHERE oi.order_id = ?";
                    
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new RuntimeException("Erro ao preparar query: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $orderId);
            
            if (!$stmt->execute()) {
                throw new RuntimeException("Erro ao executar query: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $items = [];
            
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            
            $stmt->close();
            
            return $items;
            
        } catch (Exception $e) {
            $this->logger->log("Erro ao buscar itens do pedido", [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function getOrdersByUser($userId)
    {
        try {
            if (!is_numeric($userId) || $userId <= 0) {
                throw new InvalidArgumentException("ID do usuário inválido");
            }
            
            $sql = "SELECT * FROM `orders` 
                    WHERE `user_id` = ? 
                    ORDER BY `created_at` DESC";
                    
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new RuntimeException("Erro ao preparar query: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $userId);
            
            if (!$stmt->execute()) {
                throw new RuntimeException("Erro ao executar query: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $orders = [];
            
            while ($row = $result->fetch_assoc()) {
                $orders[] = $row;
            }
            
            $stmt->close();
            
            return $orders;
            
        } catch (Exception $e) {
            $this->logger->log("Erro ao buscar pedidos do usuário", [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
}
?>