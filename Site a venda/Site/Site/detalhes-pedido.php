<?php
// Iniciar sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login_site.php');
    exit;
}

// Configurações do banco de dados
require_once '../adminView/config/dbconnect.php';
require_once '../adminView/controller/Produtos/UserCartController.php';
require_once '../adminView/controller/Produtos/UserFavoritesController.php';
require_once '../adminView/controller/Produtos/ProductController.php';
$userCartController = new UserCartController($conn);
$UserFavoritesController = new UserFavoritesController($conn);
$productController = new ProductController($conn);

// Verificar se o ID do pedido foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: profile.php');
    exit;
}

$orderId = (int)$_GET['id'];
$userId = $_SESSION['user_id'];

// Buscar informações do pedido
$orderQuery = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($orderQuery);
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

// Buscar os itens do pedido
$itemsQuery = "SELECT oi.*, p.nome, p.imagem, oi.price_at_purchase 
              FROM order_items oi 
              JOIN produtos p ON oi.product_id = p.id 
              WHERE oi.order_id = ?";
$stmtItems = $conn->prepare($itemsQuery);
$stmtItems->bind_param("i", $orderId);
$stmtItems->execute();
$resultItems = $stmtItems->get_result();
$orderItems = $resultItems->fetch_all(MYSQLI_ASSOC);

// Verificar se o pedido existe e pertence ao usuário
if (!$order) {
    header('Location: profile.php');
    exit;
}

// Adicionar o caminho da imagem para cada item do pedido
foreach ($orderItems as &$item) {
    if (!empty($item['imagem'])) {
        if (strpos($item['imagem'], '/') === false && strpos($item['imagem'], '\\') === false) {
            $item['imagem_path'] = '../adminView/uploads/produtos/' . $item['imagem'];
        } else {
            $item['imagem_path'] = $item['imagem'];
        }
    } else {
        $item['imagem_path'] = '../adminView/uploads/produtos/placeholder.jpeg';
    }
}

// Incluir cabeçalho
$pageTitle = "Detalhes do Pedido #" . $orderId;

/**
 * Fetches products using the ProductController
 * 
 * @param ProductController $productController The product controller instance
 * @param PDO $conn The database connection
 * @return array Array of products
 */
function fetchProducts($productController, $conn)
{
    $products = $productController->getAllProducts();
    if (empty($products)) {
        $checkColumnQuery = "SELECT column_name FROM information_schema.columns 
                            WHERE table_name = 'produtos' AND column_name = 'status'";
        $columnStmt = $conn->query($checkColumnQuery);
        $statusColumnExists = $columnStmt && $columnStmt->fetch(PDO::FETCH_ASSOC);
        if ($statusColumnExists) {
            $query = "SELECT * FROM produtos WHERE status = 1";
        } else {
            $query = "SELECT * FROM produtos";
        }
        $stmt = $conn->query($query);
        if ($stmt) {
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $products = [];
        }
    }
    return $products;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../adminView/assets/images/logo.png" type="image/x-icon">
    <title>Cristais Gold Lar - Detalhes do Produto</title>
    <link rel="stylesheet" href="css/elements.css">
    <link rel="stylesheet" href="css/detalhes/detalhes.css">
    <link rel="stylesheet" href="css/index/index-responsivo.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Estilos para o modal de pagamento */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            backdrop-filter: blur(3px);
        }
        .modal-content {
            background-color: #ffffff;
            margin: 5% auto;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            position: relative;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            cursor: pointer;
            font-size: 24px;
            color: #333;
            transition: color 0.2s;
        }
        .modal-close:hover {
            color: #dc3545;
        }
        .payment-option {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        .payment-option h3 {
            margin: 0 0 10px;
            font-size: 1.2em;
            color: #333;
        }
        .pix-qrcode {
            text-align: center;
            margin: 15px 0;
        }
        .pix-qrcode img {
            max-width: 200px;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
            background-color: #fff;
        }
        .credit-card-form {
            display: none;
            margin-top: 15px;
        }
        .credit-card-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        .credit-card-form input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1em;
            transition: border-color 0.2s;
        }
        .credit-card-form input:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }
        .credit-card-form .form-row {
            display: flex;
            gap: 15px;
        }
        .credit-card-form .form-row input {
            flex: 1;
        }
        .credit-card-form button {
            background-color: #007bff;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 1em;
            transition: background-color 0.3s;
        }
        .credit-card-form button:hover {
            background-color: #0056b3;
        }
        .payment-option > button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s;
        }
        .payment-option > button:hover {
            background-color: #0056b3;
        }
        /* Estilos para botões na seção de ações */
        .pedido-secao.acoes {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: flex-start;
            margin-top: 20px;
        }
        .btn-fazer-pagamento,
        .btn-cancelar,
        .btn-rastrear,
        .btn-voltar {
            display: inline-block;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }
        .btn-fazer-pagamento {
            background-color: #28a745;
            color: white;
            border: none;
        }
        .btn-fazer-pagamento:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        .btn-cancelar {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        .btn-cancelar:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }
        .btn-rastrear {
            background-color: #007bff;
            color: white;
            border: none;
        }
        .btn-rastrear:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        .btn-voltar {
            background-color: #6c757d;
            color: white;
            border: none;
        }
        .btn-voltar:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <main class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Início</a></li>
                        <li class="breadcrumb-item"><a href="profile.php">Minha Conta</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Pedido #<?php echo $orderId; ?></li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="pedido-detalhes">
            <div class="pedido-cabecalho">
                <h1>Detalhes do Pedido #<?php echo $orderId; ?></h1>
                <div class="pedido-data">
                    Realizado em: <?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?>
                </div>

                <?php
                $statusTexto = '';
                switch ($order['status']) {
                    case 'processando':
                        $statusTexto = 'Processando';
                        $statusClass = 'status-processando';
                        break;
                    case 'aguardando_pagamento':
                        $statusTexto = 'Aguardando Pagamento';
                        $statusClass = 'status-aguardando';
                        break;
                    case 'enviado':
                        $statusTexto = 'Enviado';
                        $statusClass = 'status-enviado';
                        break;
                    case 'entregue':
                        $statusTexto = 'Entregue';
                        $statusClass = 'status-entregue';
                        break;
                    case 'cancelado':
                        $statusTexto = 'Cancelado';
                        $statusClass = 'status-cancelado';
                        break;
                    case 'aceito':
                        $statusTexto = 'Aceito';
                        $statusClass = 'status-aceito';
                        break;
                    case 'aprovado':
                        $statusTexto = 'Aprovado';
                        $statusClass = 'status-aprovado';
                        break;
                    default:
                        $statusTexto = ucfirst($order['status']);
                        $statusClass = 'status-padrao';
                }
                ?>

                <div class="pedido-status-atual">
                    Status: <span class="<?php echo $statusClass; ?>"><?php echo $statusTexto; ?></span>
                </div>
            </div>

            <div class="pedido-secoes">
                <!-- Seção de Informações do Pedido -->
                <div class="pedido-secao informacoes">
                    <h2>Informações do Pedido</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Método de Pagamento:</span>
                            <span class="info-valor">
                                <?php
                                switch ($order['payment_method']) {
                                    case 'credit_card':
                                        echo 'Cartão de Crédito';
                                        if (!empty($order['card_last4'])) {
                                            echo ' (Final ' . $order['card_last4'] . ')';
                                        }
                                        break;
                                    case 'pix':
                                        echo 'PIX';
                                        break;
                                    default:
                                        echo ucfirst($order['payment_method']);
                                }
                                ?>
                            </span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Data do Pedido:</span>
                            <span class="info-valor"><?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></span>
                        </div>

                        <?php if (!empty($order['tracking_code'])): ?>
                            <div class="info-item">
                                <span class="info-label">Código de Rastreio:</span>
                                <span class="info-valor rastreio-code"><?php echo htmlspecialchars($order['tracking_code']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Seção de Endereço de Entrega -->
                <div class="pedido-secao endereco">
                    <h2>Endereço de Entrega</h2>
                    <address>
                        <?php echo htmlspecialchars($order['shipping_address']); ?>,
                        <?php echo htmlspecialchars($order['shipping_number']); ?>
                        <?php if (!empty($order['shipping_complement'])): ?>
                            - <?php echo htmlspecialchars($order['shipping_complement']); ?>
                        <?php endif; ?>
                        <br>
                        CEP: <?php echo htmlspecialchars($order['shipping_cep']); ?>
                    </address>
                </div>

                <!-- Seção de Itens do Pedido -->
                <div class="pedido-secao itens">
                    <h2>Itens do Pedido</h2>
                    <div class="itens-lista">
                        <?php if (count($orderItems) > 0): ?>
                            <?php foreach ($orderItems as $item): ?>
                                <div class="item-pedido">
                                    <div class="item-imagem">
                                        <img src="<?php echo htmlspecialchars($item['imagem_path']); ?>" alt="<?php echo htmlspecialchars($item['nome']); ?>">
                                    </div>
                                    <div class="item-detalhes">
                                        <h3><?php echo htmlspecialchars($item['nome']); ?></h3>
                                        <div class="item-meta">
                                            <span class="item-quantidade">Quantidade: <?php echo $item['quantity']; ?></span>
                                            <span class="item-preco">
                                                Preço unitário: R$ <?php echo number_format($item['price_at_purchase'], 2, ',', '.'); ?>
                                            </span>
                                        </div>
                                        <div class="item-subtotal">
                                            Subtotal: <strong>R$ <?php echo number_format($item['price_at_purchase'] * $item['quantity'], 2, ',', '.'); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="sem-itens">Nenhum item encontrado para este pedido.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Seção de Resumo do Pedido -->
                <div class="pedido-secao resumo">
                    <h2>Resumo do Pedido</h2>
                    <div class="resumo-valores">
                        <div class="resumo-linha">
                            <span>Subtotal:</span>
                            <span>R$ <?php echo number_format($order['subtotal'], 2, ',', '.'); ?></span>
                        </div>
                        <?php if ($order['shipping'] > 0): ?>
                            <div class="resumo-linha">
                                <span>Frete:</span>
                                <span>R$ <?php echo number_format($order['shipping'], 2, ',', '.'); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($order['discount'] > 0): ?>
                            <div class="resumo-linha desconto">
                                <span>Desconto:</span>
                                <span>-R$ <?php echo number_format($order['discount'], 2, ',', '.'); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="resumo-linha total">
                            <span>Total:</span>
                            <span>R$ <?php echo number_format($order['total'], 2, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Seção de Ações -->
                <div class="pedido-secao acoes">
                    <?php if ($order['status'] == 'aguardando_pagamento'): ?>
                        <button class="btn-fazer-pagamento" onclick="openPaymentModal()">Efetuar Pagamento</button>
                        <button class="btn-cancelar" onclick="cancelOrder()">Cancelar Compra</button>
                    <?php endif; ?>
                    <?php if (!empty($order['tracking_code']) && ($order['status'] == 'enviado' || $order['status'] == 'entregue' || $order['status'] == 'aceito' || $order['status'] == 'aprovado')): ?>
                        <a href="rastreio.php?code=<?php echo urlencode($order['tracking_code']); ?>" class="btn-rastrear">Rastrear Pedido</a>
                    <?php endif; ?>
                    <a href="profile.php" class="btn-voltar">Voltar para Minha Conta</a>
                </div>
            </div>
        </div>

        <!-- Modal de Pagamento -->
        <div id="paymentModal" class="modal">
            <div class="modal-content">
                <span class="modal-close" onclick="closePaymentModal()">×</span>
                <h2>Efetuar Pagamento</h2>
                <div class="payment-option">
                    <h3>Pagar com PIX</h3>
                    <div class="pix-qrcode">
                        <img src="img/pagamento/qr-code-plus.png" alt="QR Code PIX">
                    </div>
                    <p>Escaneie o QR Code acima para pagar com PIX.</p>
                </div>
                <div class="payment-option">
                    <h3>Pagar com Cartão de Crédito</h3>
                    <button onclick="toggleCreditCardForm()">Pagar com Cartão</button>
                    <div id="creditCardForm" class="credit-card-form">
                        <label for="cardNumber">Número do Cartão</label>
                        <input type="text" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="16" required>
                        <label for="cardHolder">Nome no Cartão</label>
                        <input type="text" id="cardHolder" placeholder="Nome Completo" required>
                        <div class="form-row">
                            <div>
                                <label for="cardExpiry">Validade</label>
                                <input type="text" id="cardExpiry" placeholder="MM/AA" maxlength="5" required>
                            </div>
                            <div>
                                <label for="cardCVC">CVC</label>
                                <input type="text" id="cardCVC" placeholder="123" maxlength="4" required>
                            </div>
                        </div>
                        <button onclick="processCreditCardPayment()">Confirmar Pagamento</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Função para abrir o modal de pagamento
        function openPaymentModal() {
            const modal = document.getElementById('paymentModal');
            modal.style.display = 'block';
        }

        // Função para fechar o modal de pagamento
        function closePaymentModal() {
            const modal = document.getElementById('paymentModal');
            modal.style.display = 'none';
            document.getElementById('creditCardForm').style.display = 'none';
            document.getElementById('cardNumber').value = '';
            document.getElementById('cardHolder').value = '';
            document.getElementById('cardExpiry').value = '';
            document.getElementById('cardCVC').value = '';
        }

        // Função para mostrar/esconder o formulário de cartão de crédito
        function toggleCreditCardForm() {
            const form = document.getElementById('creditCardForm');
            form.style.display = form.style.display === 'block' ? 'none' : 'block';
        }

        // Função para processar pagamento com cartão de crédito
        function processCreditCardPayment() {
            const cardNumber = document.getElementById('cardNumber').value;
            const cardHolder = document.getElementById('cardHolder').value;
            const cardExpiry = document.getElementById('cardExpiry').value;
            const cardCVC = document.getElementById('cardCVC').value;

            if (!cardNumber || !cardHolder || !cardExpiry || !cardCVC) {
                alert('Por favor, preencha todos os campos do cartão.');
                return;
            }

            // Placeholder para integração com checkout.js (removido para PIX, mantido para cartão)
            alert('Pagamento com cartão processado com sucesso! (Simulação)');
            window.location.reload();
        }

        // Função para cancelar o pedido
        function cancelOrder() {
            if (confirm('Tem certeza que deseja cancelar este pedido?')) {
                fetch('cancel_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'order_id=<?php echo $orderId; ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Pedido cancelado com sucesso!');
                        window.location.reload();
                    } else {
                        alert('Erro ao cancelar pedido: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Erro ao cancelar pedido: ' + error.message);
                });
            }
        }
    </script>
</body>
</html>