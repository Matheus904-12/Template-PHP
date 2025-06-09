<?php
// controller/pedidosController.php

// Define o arquivo que estamos procurando
$connectionFile = 'dbconnect.php';

// Array com diferentes possíveis caminhos para encontrar o arquivo
$paths = [
    // Caminho original
    '../config/',
    'config/',
    
    // Caminho absoluto baseado na raiz do documento
    $_SERVER['DOCUMENT_ROOT'] . '/config/',
    $_SERVER['DOCUMENT_ROOT'] . '/app/config/',
    
    // Caminho relativo a partir do diretório atual
    './',
    './config/',
    '../',
    '../../config/',
    
    // Diretórios comuns de sistema
    'includes/',
    '../includes/',
    'lib/',
    '../lib/',
    
    // Diretórios comuns em frameworks
    'app/config/',
    '../app/config/',
    'src/config/',
    '../src/config/',
    
    // Possibilidades com vendor (para projetos Composer)
    'vendor/config/',
    '../vendor/config/'
];

// Flag para verificar se o arquivo foi encontrado
$fileFound = false;

// Tenta cada caminho
foreach ($paths as $path) {
    $fullPath = $path . $connectionFile;
    
    if (file_exists($fullPath)) {
        include_once $fullPath;
        $fileFound = true;
        // Registra o caminho encontrado (opcional)
        $foundPath = $fullPath;
        break;
    }
}

// Se não encontrou o arquivo, tenta um arquivo de backup ou mostra erro
if (!$fileFound) {
    // Tenta usar um arquivo de conexão de backup/default
    if (file_exists('default_dbconnect.php')) {
        include_once 'default_dbconnect.php';
        // Opcional: Registre em log que está usando configuração padrão
        error_log('Usando arquivo de conexão padrão, o original não foi encontrado');
    } else {
        // Erro se nenhum arquivo for encontrado
        die("Erro: Não foi possível encontrar o arquivo de conexão com o banco de dados em nenhum dos caminhos conhecidos.");
    }
}

/**
 * Obtém todos os pedidos de um usuário específico
 * 
 * @param int $userId ID do usuário (opcional, se não informado traz todos os pedidos)
 * @return array Lista de pedidos
 */
function getPedidos($userId = null) {
    global $pdo;
    
    if (!$pdo) {
        die("Erro: Conexão com o banco de dados não está disponível.");
    }
    
    try {
        // Verifica se estamos usando MySQL ou PostgreSQL
        $isPostgreSQL = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'pgsql') !== false);
        
        if ($isPostgreSQL) {
            // Verificação para PostgreSQL
            $checkTableQuery = "SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = 'order_items'
            )";
        } else {
            // Verificação para MySQL
            $checkTableQuery = "SELECT COUNT(*) 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = 'order_items'";
        }
        
        $checkTableStmt = $pdo->query($checkTableQuery);
        $tableExists = $checkTableStmt->fetchColumn() > 0;
        
        if (!$tableExists) {
            die("Erro: A tabela order_items não existe no banco de dados.");
        }
        
        // Verificar as colunas da tabela order_items
        if ($isPostgreSQL) {
            $checkColumnsQuery = "SELECT column_name FROM information_schema.columns 
                                  WHERE table_schema = 'public' AND table_name = 'order_items'";
        } else {
            $checkColumnsQuery = "SELECT column_name FROM information_schema.columns 
                                  WHERE table_schema = DATABASE() AND table_name = 'order_items'";
        }
        
        $checkColumnsStmt = $pdo->query($checkColumnsQuery);
        $columnNames = $checkColumnsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Nomes alternativos possíveis para a coluna de ID do produto
        $possibleNames = ['produto_id', 'product_id', 'prod_id', 'id_produto', 'id_product'];
        
        // Variável para armazenar o nome da coluna que se refere ao produto
        $produtoColName = null;
        
        // Verifica se algum dos nomes possíveis existe na tabela
        foreach ($possibleNames as $name) {
            if (in_array($name, $columnNames)) {
                $produtoColName = $name;
                break;
            }
        }
        
        // Se nenhum nome conhecido for encontrado, vamos verificar se há alguma coluna com "produto" ou "product" no nome
        if ($produtoColName === null) {
            foreach ($columnNames as $colName) {
                if (strpos($colName, 'produto') !== false || strpos($colName, 'product') !== false) {
                    $produtoColName = $colName;
                    break;
                }
            }
        }
        
        // Se ainda não encontrou, usa um valor padrão e registra o erro
        if ($produtoColName === null) {
            $produtoColName = 'product_id'; // Tenta um valor padrão comum
            error_log('Aviso: Não foi possível determinar o nome da coluna ID do produto. Usando ' . $produtoColName);
        }
        
        // Consulta adaptada para MySQL ou PostgreSQL
        if ($isPostgreSQL) {
            // No PostgreSQL, a concatenação de strings é feita com ||
            $sql = "SELECT 
                    o.id as order_id, 
                    o.user_id as customer_id, 
                    o.order_date as created_at, 
                    o.total, 
                    o.payment_method,
                    o.status,
                    (SELECT string_agg(p.nome, ', ') 
                     FROM order_items oi 
                     JOIN produtos p ON oi." . $produtoColName . " = p.id 
                     WHERE oi.order_id = o.id) as produtos_nomes
                FROM orders o";
        } else {
            // No MySQL, usamos GROUP_CONCAT
            $sql = "SELECT 
                    o.id as order_id, 
                    o.user_id as customer_id, 
                    o.order_date as created_at, 
                    o.total, 
                    o.payment_method,
                    o.status,
                    (SELECT GROUP_CONCAT(p.nome SEPARATOR ', ') 
                     FROM order_items oi 
                     JOIN produtos p ON oi." . $produtoColName . " = p.id 
                     WHERE oi.order_id = o.id) as produtos_nomes
                FROM orders o";
        }
        
        // Se um ID de usuário for especificado, adiciona a condição WHERE
        $params = [];
        if ($userId !== null) {
            $userId = filter_var($userId, FILTER_VALIDATE_INT);
            if ($userId) {
                $sql .= " WHERE o.user_id = :user_id";
                $params[':user_id'] = $userId;
            }
        }
        
        // Ordenação por data mais recente
        $sql .= " ORDER BY o.order_date DESC";
        
        // Prepara e executa a query
        $stmt = $pdo->prepare($sql);
        
        if (!$stmt) {
            die("Erro na preparação da consulta: " . $pdo->errorInfo()[2]);
        }
        
        // Executa a query com os parâmetros
        $stmt->execute($params);
        
        // Coleta os resultados
        $pedidos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Formata a data para exibição
            $row['created_at'] = date('d/m/Y H:i', strtotime($row['created_at']));
            
            // Traduz o status para português mais amigável
            $row['status'] = traduzirStatus($row['status']);
            
            // Traduz o método de pagamento
            $row['payment_method'] = traduzirMetodoPagamento($row['payment_method']);
            
            $pedidos[] = $row;
        }
        
        return $pedidos;
    } catch (PDOException $e) {
        die("Erro ao obter pedidos: " . $e->getMessage());
    }
}

/**
 * Obtém detalhes de um pedido específico
 * 
 * @param int $orderId ID do pedido
 * @return array|null Detalhes do pedido ou null se não encontrado
 */
function getPedidoDetalhes($orderId) {
    global $pdo;
    
    if (!$pdo) {
        die("Erro: Conexão com o banco de dados não está disponível.");
    }
    
    $orderId = filter_var($orderId, FILTER_VALIDATE_INT);
    if (!$orderId) {
        return null;
    }
    
    try {
        // Verifica se estamos usando MySQL ou PostgreSQL
        $isPostgreSQL = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'pgsql') !== false);
        
        // Consulta principal do pedido
        $sql = "SELECT * FROM orders WHERE id = :order_id";
        $stmt = $pdo->prepare($sql);
        
        if (!$stmt) {
            die("Erro na preparação da consulta: " . $pdo->errorInfo()[2]);
        }
        
        $stmt->execute([':order_id' => $orderId]);
        
        if ($stmt->rowCount() === 0) {
            return null;
        }
        
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Formata a data
        $pedido['order_date'] = date('d/m/Y H:i', strtotime($pedido['order_date']));
        
        // Traduz o status
        $pedido['status_display'] = traduzirStatus($pedido['status']);
        
        // Verificar as colunas da tabela order_items
        if ($isPostgreSQL) {
            $checkColumnsQuery = "SELECT column_name FROM information_schema.columns 
                                  WHERE table_schema = 'public' AND table_name = 'order_items'";
        } else {
            $checkColumnsQuery = "SELECT column_name FROM information_schema.columns 
                                  WHERE table_schema = DATABASE() AND table_name = 'order_items'";
        }
        
        $checkColumnsStmt = $pdo->query($checkColumnsQuery);
        $columnNames = $checkColumnsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Nomes alternativos possíveis para a coluna de ID do produto
        $possibleNames = ['produto_id', 'product_id', 'prod_id', 'id_produto', 'id_product'];
        
        // Variável para armazenar o nome da coluna que se refere ao produto
        $produtoColName = null;
        
        // Verifica se algum dos nomes possíveis existe na tabela
        foreach ($possibleNames as $name) {
            if (in_array($name, $columnNames)) {
                $produtoColName = $name;
                break;
            }
        }
        
        // Se nenhum nome conhecido for encontrado, vamos verificar se há alguma coluna com "produto" ou "product" no nome
        if ($produtoColName === null) {
            foreach ($columnNames as $colName) {
                if (strpos($colName, 'produto') !== false || strpos($colName, 'product') !== false) {
                    $produtoColName = $colName;
                    break;
                }
            }
        }
        
        // Se ainda não encontrou, registra o erro e mostra as colunas disponíveis
        if ($produtoColName === null) {
            error_log('Colunas disponíveis na tabela order_items: ' . implode(', ', $columnNames));
            die("Erro: Não foi possível encontrar a coluna de ID do produto na tabela order_items. Colunas disponíveis: " . implode(', ', $columnNames));
        }
        
        // Consulta para obter os itens do pedido com informações dos produtos
        $sqlItems = "SELECT 
                        oi.*, 
                        p.nome as produto_nome, 
                        p.preco as produto_preco,
                        p.imagem as produto_imagem
                    FROM order_items oi
                    LEFT JOIN produtos p ON oi." . $produtoColName . " = p.id
                    WHERE oi.order_id = :order_id";
        
        $stmtItems = $pdo->prepare($sqlItems);
        
        if (!$stmtItems) {
            die("Erro na preparação da consulta de itens: " . $pdo->errorInfo()[2]);
        }
        
        $stmtItems->execute([':order_id' => $orderId]);
        
        $pedido['itens'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        
        return $pedido;
    } catch (PDOException $e) {
        die("Erro ao obter detalhes do pedido: " . $e->getMessage());
    }
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
 * Traduz os métodos de pagamento para termos mais amigáveis
 * 
 * @param string $method Método de pagamento
 * @return string Método traduzido
 */
function traduzirMetodoPagamento($method) {
    $methodMap = [
        'credit_card' => 'Cartão de Crédito',
        'pix' => 'PIX',
        'boleto' => 'Boleto',
        'bank_transfer' => 'Transferência Bancária'
    ];
    
    return isset($methodMap[$method]) ? $methodMap[$method] : ucfirst(str_replace('_', ' ', $method));
}

/**
 * Atualiza o status de um pedido
 * 
 * @param int $orderId ID do pedido
 * @param string $status Novo status
 * @return bool Sucesso da operação
 */
function atualizarStatusPedido($orderId, $status) {
    global $pdo;
    
    if (!$pdo) {
        return false;
    }
    
    $orderId = filter_var($orderId, FILTER_VALIDATE_INT);
    if (!$orderId) {
        return false;
    }
    
    // Lista de status válidos
    $statusValidos = [
        'processando', 'aguardando_pagamento', 'pago', 'enviado', 
        'entregue', 'cancelado', 'aceito', 'em_alerta'
    ];
    
    if (!in_array($status, $statusValidos)) {
        return false;
    }
    
    try {
        // Verifica se estamos usando MySQL ou PostgreSQL
        $isPostgreSQL = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'pgsql') !== false);
        
        if ($isPostgreSQL) {
            // No PostgreSQL, usamos CURRENT_TIMESTAMP
            $sql = "UPDATE orders SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :order_id";
        } else {
            // No MySQL, podemos usar NOW()
            $sql = "UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :order_id";
        }
        
        $stmt = $pdo->prepare($sql);
        
        if (!$stmt) {
            return false;
        }
        
        $params = [
            ':status' => $status,
            ':order_id' => $orderId
        ];
        
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Erro ao atualizar status do pedido: " . $e->getMessage());
        return false;
    }
}
?>