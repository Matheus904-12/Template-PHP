<?php
// checkout.php
date_default_timezone_set('America/Sao_Paulo');

require_once '../adminView/config/dbconnect.php';
require_once '../adminView/controller/Produtos/UserCartController.php';
require_once '../adminView/controller/Produtos/ProductController.php';
require_once '../adminView/controller/Produtos/OrderController.php';

$userCartController = new UserCartController($conn);
$productController = new ProductController($conn);
$orderController = new OrderController($conn);

// Função para calcular o total do pedido
function calculateOrderTotal($subtotal, $shipping, $discount = 0)
{
    return $subtotal + $shipping - $discount;
}

session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    header('Location: login_site.php?redirect=checkout');
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['username'];
$userPicture = $_SESSION['user_picture'] ?? 'img/icons/perfil.png';
$userEmail = $_SESSION['user_email'] ?? '';

// Obter itens do carrinho
$cartItems = $userCartController->getCartItems($userId);
error_log("Cart Items in checkout.php: " . print_r($cartItems, true));
if (empty($cartItems)) {
    header('Location: compras.php');
    exit;
}

// Obter dados do usuário
$query = "SELECT name, telefone, endereco, cep, numero_casa FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

// Calcular totais
$subtotal = 0;
$shipping = 0;
$discount = 0;

foreach ($cartItems as $item) {
    $subtotal += $item['preco'] * $item['quantity'];
}
error_log("Subtotal calculado: $subtotal");

// Cálculo do frete
if (!empty($userData['cep'])) {
    $cepBase = substr(preg_replace('/\D/', '', $userData['cep']), 0, 5);
    $spCeps = ['01', '02', '03', '04', '05', '06', '07', '08', '09'];
    $cepInicial = substr($cepBase, 0, 2);

    if ($subtotal >= 350 || in_array($cepInicial, $spCeps)) {
        $shipping = 0;
    } else if ($cepBase >= '08000' && $cepBase <= '08499') {
        $shipping = 30.00;
    } elseif ($cepBase >= '08500' && $cepBase <= '08999') {
        $shipping = 45.00;
    } elseif ($cepBase >= '09000' && $cepBase <= '09999') {
        $shipping = 60.00;
    } else {
        $shipping = 100.00;
    }
}

$total = calculateOrderTotal($subtotal, $shipping, $discount);

// Processar o pedido se o formulário foi enviado
$orderPlaced = false;
$orderId = null;
$orderError = null;

function isValidLuhn($cardNumber) {
    $cardNumber = preg_replace('/\D/', '', $cardNumber);
    $sum = 0;
    $isEven = false;
    for ($i = strlen($cardNumber) - 1; $i >= 0; $i--) {
        $digit = (int)$cardNumber[$i];
        if ($isEven) {
            $digit *= 2;
            if ($digit > 9) $digit -= 9;
        }
        $sum += $digit;
        $isEven = !$isEven;
    }
    return $sum % 10 === 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'] ?? '';
    $shippingAddress = $_POST['shipping_address'] ?? '';
    $shippingNumber = $_POST['shipping_number'] ?? '';
    $shippingCep = $_POST['shipping_cep'] ?? '';
    $shippingComplement = $_POST['shipping_complement'] ?? '';
    $shippingPhone = $_POST['shipping_phone'] ?? '';
    $savedCardId = $_POST['saved_card_id'] ?? '';
    $saveCard = isset($_POST['save_card']) && $_POST['save_card'] === 'on';
    $cardNumber = $_POST['card_number'] ?? '';
    $cardName = $_POST['card_name'] ?? '';
    $cardExpiry = $_POST['card_expiry'] ?? '';
    $cardCvv = $_POST['card_cvv'] ?? '';
    $installments = $_POST['cc_installments'] ?? 1;
    $paymentId = $_POST['credit_card_payment_id'] ?? '';

    if ($paymentMethod === 'credit_card' && empty($savedCardId)) {
        if (!isValidLuhn($cardNumber)) {
            $orderError = "Número do cartão inválido (Luhn check failed).";
        } elseif (empty($cardNumber) || empty($cardName) || empty($cardExpiry) || empty($cardCvv)) {
            $orderError = "Informe todos os dados do cartão";
        }
    }

    // Recalcular o frete
    if (!empty($shippingCep)) {
        $cepBase = substr(preg_replace('/\D/', '', $shippingCep), 0, 5);
        $spCeps = ['01', '02', '03', '04', '05', '06', '07', '08', '09'];
        $cepInicial = substr($cepBase, 0, 2);

        if ($subtotal >= 350 || in_array($cepInicial, $spCeps)) {
            $shipping = 0;
        } else if ($cepBase >= '08000' && $cepBase <= '08499') {
            $shipping = 30.00;
        } elseif ($cepBase >= '08500' && $cepBase <= '08999') {
            $shipping = 45.00;
        } elseif ($cepBase >= '09000' && $cepBase <= '09999') {
            $shipping = 60.00;
        } else {
            $shipping = 100.00;
        }
    }

    $total = calculateOrderTotal($subtotal, $shipping, $discount);

    // Validação
    if (empty($paymentMethod)) {
        $orderError = "Selecione um método de pagamento";
    } elseif (empty($shippingAddress)) {
        $orderError = "Informe o endereço de entrega";
    } elseif (empty($shippingCep)) {
        $orderError = "Informe o CEP";
    } elseif ($paymentMethod === 'credit_card' && empty($savedCardId) && (empty($cardNumber) || empty($cardName) || empty($cardExpiry) || empty($cardCvv))) {
        $orderError = "Informe todos os dados do cartão";
    } else {
        try {
            // Dados do pedido
            $orderData = [
                'user_id' => $userId,
                'total' => $total,
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'discount' => $discount,
                'payment_method' => $paymentMethod,
                'status' => $paymentMethod === 'credit_card' ? 'aprovado' : 'aguardando_pagamento',
                'shipping_address' => $shippingAddress,
                'shipping_number' => $shippingNumber,
                'shipping_cep' => $shippingCep,
                'shipping_complement' => $shippingComplement,
                'tracking_code' => '',
                'installments' => $installments,
                'payment_id' => $paymentId
            ];

            error_log("Dados do pedido a serem inseridos: " . print_r($orderData, true));

            // Criar pedido
            $orderId = $orderController->createOrder($orderData);

            if ($orderId) {
                foreach ($cartItems as $item) {
                    $orderController->addOrderItem($orderId, $item['product_id'], $item['quantity'], $item['preco']);
                }

                $trackingCode = 'CG' . strtoupper(substr(md5($orderId . time()), 0, 8));
                $orderController->updateOrder($orderId, ['tracking_code' => $trackingCode]);

                $userCartController->clearCart($userId);

                $orderPlaced = true;
            } else {
                $orderError = "Erro ao processar o pedido. Tente novamente.";
            }
        } catch (Exception $e) {
            $orderError = "Erro: " . $e->getMessage();
        }
    }
}

// Obter cartões salvos
$savedCards = [];
$cardsQuery = "SELECT id, card_last4, card_name, card_expiry FROM user_cards WHERE user_id = ? ORDER BY id DESC";
$cardsStmt = $conn->prepare($cardsQuery);
$cardsStmt->bind_param("i", $userId);
$cardsStmt->execute();
$cardsResult = $cardsStmt->get_result();

while ($card = $cardsResult->fetch_assoc()) {
    $savedCards[] = $card;
}

// Funções auxiliares
function fetchProducts($productController, $conn)
{
    $categoria = isset($_GET['categoria']) ? htmlspecialchars($_GET['categoria']) : '';
    $orderBy = isset($_GET['orderBy']) ? htmlspecialchars($_GET['orderBy']) : '';
    $search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

    if (!empty($categoria)) {
        return $productController->getProductsByCategory($categoria);
    } elseif (!empty($orderBy)) {
        return $productController->getProductsOrderedBy($orderBy);
    } elseif (!empty($search)) {
        return $productController->searchProducts($search);
    } else {
        return $productController->getAllProducts();
    }
}

function fetchUserCartAndFavorites($conn, $userId)
{
    $cart = [];
    $favorites = [];
    $uploadPath = '../adminView/uploads/produtos/';

    $cartQuery = "
        SELECT uc.product_id as id, p.nome as name, p.preco as price,
               p.imagem as image, uc.quantity, p.descricao as description
        FROM user_cart uc
        JOIN produtos p ON uc.product_id = p.id
        WHERE uc.user_id = ?
    ";
    $stmt = $conn->prepare($cartQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $row['image'] = $uploadPath . $row['image'];
        $cart[] = $row;
    }

    $favoritesQuery = "
        SELECT p.id, p.nome as name, p.preco as price,
               p.imagem as image, p.descricao as description
        FROM user_favorites uf
        JOIN produtos p ON uf.product_id = p.id
        WHERE uf.user_id = ?
    ";
    $stmt = $conn->prepare($favoritesQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $row['image'] = $uploadPath . $row['image'];
        $favorites[] = $row;
    }

    return ['cart' => $cart, 'favorites' => $favorites];
}

function getProductImagePath($productId, $conn)
{
    $query = "SELECT imagem FROM produtos WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (!empty($row['imagem'])) {
            if (strpos($row['imagem'], '/') === false && strpos($row['imagem'], '\\') === false) {
                return '../adminView/uploads/produtos/' . $row['imagem'];
            } else {
                return $row['imagem'];
            }
        }
    }

    return '../adminView/uploads/produtos/placeholder.jpeg';
}

function loadSiteConfig($configPath)
{
    if (!file_exists($configPath)) {
        throw new Exception("Erro ao carregar as configurações do site.");
    }

    $jsonContent = file_get_contents($configPath);
    if ($jsonContent === false) {
        throw new Exception("Erro ao carregar as configurações do site.");
    }

    $configData = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao carregar as configurações do site.");
    }

    return $configData;
}

$siteConfigPath = __DIR__ . '/../adminView/config_site.json';
$jsonContent = file_get_contents($siteConfigPath);
$configData = json_decode($jsonContent, true);

$getConfigValue = function ($config, $keys, $default = '') {
    $value = $config;
    foreach ($keys as $key) {
        if (!isset($value[$key])) {
            return $default;
        }
        $value = $value[$key];
    }
    return is_string($value) ? htmlspecialchars($value) : $value;
};

$whatsapp = $getConfigValue($configData, ['contato', 'whatsapp']);
$instagram = $getConfigValue($configData, ['contato', 'instagram'], '#');
$email = $getConfigValue($configData, ['contato', 'email'], '#');
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../adminView/assets/images/logo.png" type="image/x-icon">
    <title>Finalizar Compra - Cristais Gold Lar</title>
    <link rel="stylesheet" href="css/index/index.css">
    <link rel="stylesheet" href="css/checkout/checkout.css">
    <link rel="stylesheet" href="css/checkout/checkout-responsivo.css">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>
    <div id="loading-screen">
        <div class="loader"></div>
    </div>

    <div class="notification-bar">
        <div class="message active" id="message1">Até 6x Sem Juros</div>
        <div class="message" id="message2">Frete Grátis para São Paulo!</div>
    </div>

    <!-- Cabeçalho principal -->
    <header class="navbar">
        <nav>
            <div class="logo-container">
                <div class="logo">
                    <img src="img/logo.png" alt="Cristais Gold Lar Logo">
                </div>
                <div class="store-name">Cristais Gold Lar</div>
            </div>
            <div class="nav-links">
                <a href="index.php" class="nav-link">Início</a>
                <a href="blog.php" class="nav-link">Avaliações</a>
                <a href="#footer" class="nav-link scroll-to-footer">Contato</a>
            </div>
            <div class="nav-icons">
                <a href="meusItens.php" class="cart-icon">
                    <img src="img/icons/compras.png" alt="Carrinho">
                    <span class="counter cart-counter"><?= count($cartItems) ?></span>
                </a>
                <a href="meusItens.php" class="cart-icon">
                    <img src="img/icons/salvar preto.png" alt="Favoritos">
                    <span class="counter favorites-counter"><?= count(fetchUserCartAndFavorites($conn, $userId)['favorites']) ?></span>
                </a>
                <div class="dropdown">
                    <a href="#" id="profile-btn">
                        <span class="profile-toggle">
                            <img src="includes/configuracoes/image_proxy.php?url=<?= urlencode($userPicture) ?>" alt="Foto de Perfil">
                            <?= htmlspecialchars($userName) ?>
                            <img src="img/icons/seta.png" alt="Seta" class="arrow">
                        </span>
                    </a>
                    <div class="dropdown-menu">
                        <a href="includes/configuracoes/logout.php" class="logout-btn">Sair</a>
                        <a href="profile.php" class="config-btn">Configurações</a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Cabeçalho secundário para mobile -->
    <header class="secondary-navbar">
        <nav>
            <div class="logo-container">
                <div class="logo">
                    <img src="img/logo.png" alt="Cristais Gold Lar Logo">
                </div>
                <div class="store-name">Cristais Gold Lar</div>
            </div>
            <button class="menu-toggle" aria-label="Abrir menu">
                <span class="hamburger"></span>
            </button>
        </nav>
    </header>

    <!-- Menu lateral -->
    <div class="side-menu" id="side-menu">
        <div class="side-menu-header">
            <button class="close-menu" aria-label="Fechar menu">✕</button>
        </div>
        <ul class="side-menu-items">
            <li class="side-menu-item"><a href="index.php">Início</a></li>
            <li class="side-menu-item"><a href="blog.php">Avaliações</a></li>
            <li class="side-menu-item"><a href="#footer" class="scroll-to-footer">Contato</a></li>
            <li class="side-menu-item"><a href="profile.php">Perfil</a></li>
            <li class="side-menu-item"><a href="includes/configuracoes/logout.php">Sair</a></li>
        </ul>
    </div>

    <!-- Barra inferior para mobile -->
    <div class="tabbar">
        <a href="meusItens.php">
            <img src="img/icons/salvar preto.png" alt="Favoritos">
            <span>Favoritos</span>
            <span class="counter favorites-counter"><?= count(fetchUserCartAndFavorites($conn, $userId)['favorites']) ?></span>
        </a>
        <a href="profile.php">
            <img src="includes/configuracoes/image_proxy.php?url=<?= urlencode($userPicture) ?>" alt="Perfil">
            <span>Perfil</span>
        </a>
        <a href="meusItens.php">
            <img src="img/icons/compras.png" alt="Carrinho">
            <span>Carrinho</span>
            <span class="counter cart-counter"><?= count($cartItems) ?></span>
        </a>
    </div>


    <?php if ($orderPlaced): ?>
        <div class="checkout-container">
            <div class="order-confirmation">
                <div class="confirmation-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>Pedido Confirmado!</h1>
                <p>Seu pedido foi processado com sucesso. Obrigado pela compra!</p>

                <div class="order-details">
                    <h3>Detalhes do Pedido:</h3>
                    <div class="detail-row">
                        <span>Número do Pedido:</span>
                        <span>#<?= htmlspecialchars($orderId) ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Data:</span>
                        <span><?= date('d/m/Y H:i', time()) ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Total:</span>
                        <span>R$<?= number_format($total, 2, ',', '.') ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Método de Pagamento:</span>
                        <span class="payment-method-display">
                            <?php
                            if ($paymentMethod === 'credit_card') {
                                echo '<i class="fas fa-credit-card"></i> Cartão de Crédito';
                                if (!empty($cardNumber)) {
                                    echo ' <span class="card-number">(final ' . substr(preg_replace('/\D/', '', $cardNumber), -4) . ')</span>';
                                } elseif (!empty($savedCardId)) {
                                    foreach ($savedCards as $card) {
                                        if ($card['id'] == $savedCardId) {
                                            echo ' <span class="card-number">(final ' . $card['card_last4'] . ')</span>';
                                        }
                                    }
                                }
                            } elseif ($paymentMethod === 'pix') {
                                echo '<i class="fas fa-qrcode"></i> PIX';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span>Status:</span>
                        <span>
                            <?php
                            if ($paymentMethod === 'credit_card') {
                                echo '<span style="color: #4e8d7c;">Pagamento Aprovado</span>';
                            } elseif ($paymentMethod === 'pix') {
                                echo '<span style="color: #f0ad4e;">Aguardando Pagamento</span>';
                            }
                            ?>
                        </span>
                    </div>
                </div>

                <div class="tracking-section">
                    <h3>Acompanhe seu Pedido</h3>
                    <p>Código de Rastreio: <strong><?= $trackingCode ?></strong></p>

                    <div class="confirmation-buttons">
                        <a href="compras.php" class="btn-continue-shopping">Continuar Comprando</a>
                        <a href="profile.php?tab=orders" class="btn-view-order">Ver Meus Pedidos</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="checkout-container">
                <div class="checkout-header">
                    <h1>Finalizar Compra</h1>
                    <div class="checkout-progress">
                        <div class="progress-step">
                            <div class="step-circle completed"></div>
                            <div class="step-line active"></div>
                            <div class="step-label">Carrinho</div>
                        </div>
                        <div class="progress-step">
                            <div class="step-circle active"></div>
                            <div class="step-line"></div>
                            <div class="step-label">Pagamento</div>
                        </div>
                        <div class="progress-step">
                            <div class="step-circle"></div>
                            <div class="step-line"></div>
                            <div class="step-label">Confirmação</div>
                        </div>
                        <div class="progress-step">
                            <div class="step-circle"></div>
                            <div class="step-label">Entrega</div>
                        </div>
                    </div>
                </div>

                <div class="checkout-content">
                    <div class="checkout-section">
                        <h2 class="section-title">Informações de Entrega</h2>

                        <form id="checkout-form" method="POST" action="">
                            <div class="form-group">
                                <label for="shipping_address">Endereço de Entrega</label>
                                <input type="text" id="shipping_address" name="shipping_address" class="form-control" value="<?= htmlspecialchars($userData['endereco'] ?? '') ?>" required>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="shipping_number">Número</label>
                                    <input type="text" id="shipping_number" name="shipping_number" class="form-control" value="<?= htmlspecialchars($userData['numero_casa'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="shipping_complement">Complemento</label>
                                    <input type="text" id="shipping_complement" name="shipping_complement" class="form-control" value="<?= htmlspecialchars($userData['complemento'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="shipping_cep">CEP</label>
                                <div class="cep-finder">
                                    <input type="text" id="shipping_cep" name="shipping_cep" class="form-control" value="<?= htmlspecialchars($userData['cep'] ?? '') ?>" maxlength="9" placeholder="00000-000" required>
                                    <button type="button" id="btn-find-cep" class="btn-find-cep">Buscar</button>
                                </div>
                                <small class="shipping-info text-muted">Frete grátis para todo o estado de São Paulo ou compras acima de R$350,00</small>
                            </div>

                            <div class="form-group">
                                <label for="shipping_phone">Telefone para Contato</label>
                                <input type="tel" id="shipping_phone" name="shipping_phone" class="form-control" value="<?= htmlspecialchars($userData['telefone'] ?? '') ?>" placeholder="(00) 00000-0000" required>
                            </div>

                            <h2 class="section-title">Método de Pagamento</h2>

                            <div class="payment-methods">
                                <div class="payment-method" data-method="credit_card">
                                    <i class="fas fa-credit-card"></i>
                                    <div>Cartão de Crédito</div>
                                </div>
                                <div class="payment-method" data-method="debit_card">
                                    <i class="fas fa-credit-card"></i>
                                    <div>Cartão de Débito</div>
                                </div>
                                <div class="payment-method" data-method="pix">
                                    <i class="fas fa-qrcode"></i>
                                    <div>PIX</div>
                                </div>
                            </div>

                            <input type="hidden" name="payment_method" id="payment_method" value="">

                            <div class="payment-details" id="credit_card_details">
                                <?php if (!empty($savedCards)): ?>
                                    <div class="saved-cards">
                                        <h3>Cartões Salvos</h3>
                                        <?php foreach ($savedCards as $card): ?>
                                            <div class="saved-card" data-card-id="<?= htmlspecialchars($card['id']) ?>">
                                                <i class="fas fa-credit-card card-icon"></i>
                                                <span class="card-last4">•••• <?= htmlspecialchars($card['card_last4']) ?></span>
                                                <span class="card-expiry"><?= htmlspecialchars($card['card_expiry']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="saved-card new-card">
                                            <i class="fas fa-plus-circle card-icon"></i>
                                            <span>Usar novo cartão</span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="credit-card-form" id="new_card_form" <?= !empty($savedCards) ? 'style="display:none;"' : '' ?>>
                                    <div class="form-group">
                                        <label for="card_number">Número do Cartão</label>
                                        <div class="card-input-container">
                                            <input type="text" id="card_number" name="card_number" class="form-control" placeholder="0000 0000 0000 0000" maxlength="19">
                                            <span class="card-type-icon"><i class="fas fa-credit-card"></i></span>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="card_name">Nome no Cartão</label>
                                        <input type="text" id="card_name" name="card_name" class="form-control" placeholder="Como aparece no cartão">
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="card_expiry">Validade</label>
                                            <input type="text" id="card_expiry" name="card_expiry" class="form-control" placeholder="MM/AA" maxlength="5">
                                        </div>
                                        <div class="form-group">
                                            <label for="card_cvv">CVV</label>
                                            <input type="text" id="card_cvv" name="card_cvv" class="form-control" placeholder="000" maxlength="4">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="cardholder_email">E-mail do Pagador</label>
                                        <input type="email" id="cardholder_email" name="cardholder_email" class="form-control" value="<?= htmlspecialchars($userEmail) ?>" placeholder="E-mail do pagador" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="identification_number">CPF do Pagador</label>
                                        <input type="text" id="identification_number" name="identification_number" class="form-control" placeholder="000.000.000-00" maxlength="14" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="cc_installments">Parcelas</label>
                                        <select id="cc_installments" name="cc_installments" class="form-control"></select>
                                    </div>
                                    <div class="form-group">
                                        <label class="checkbox-container">
                                            <input type="checkbox" name="save_card" id="save_card">
                                            <span class="checkbox-text">Salvar cartão para futuras compras</span>
                                        </label>
                                    </div>
                                    <input type="hidden" id="card_token" name="card_token">
                                    <input type="hidden" id="card_brand" name="card_brand">
                                </div>
                            </div>

                            <div class="payment-details" id="pix_details">
                                <div class="pix-container">
                                    <p>Ao finalizar a compra, você receberá um QR Code para pagamento via PIX.</p>
                                    <p>O prazo para pagamento é de 30 minutos. Após este período, o pedido será cancelado automaticamente.</p>
                                </div>
                            </div>

                            <?php if ($orderError): ?>
                                <div class="error-message">
                                    <?= htmlspecialchars($orderError) ?>
                                </div>
                            <?php endif; ?>

                            <button type="submit" class="btn-checkout">Finalizar Compra</button>
                        </form>
                    </div>

                    <div class="cart-summary">
                        <h2 class="section-title">Resumo do Pedido</h2>

                        <div class="cart-items">
                            <?php foreach ($cartItems as $item): ?>
                                <div class="cart-item">
                                    <img src="<?= !empty($item['imagem']) ? htmlspecialchars('../adminView/uploads/produtos/' . $item['imagem']) : getProductImagePath($item['product_id'], $conn) ?>" alt="<?= htmlspecialchars($item['nome']) ?>" class="cart-item-image">
                                    <div class="cart-item-details">
                                        <div class="cart-item-name"><?= htmlspecialchars($item['nome']) ?></div>
                                        <div class="cart-item-price">R$<?= number_format($item['preco'], 2, ',', '.') ?></div>
                                        <div class="cart-item-quantity">Quantidade: <?= $item['quantity'] ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="price-details">
                            <div class="price-row">
                                <span>Subtotal</span>
                                <span>R$<?= number_format($subtotal, 2, ',', '.') ?></span>
                            </div>
                            <div class="price-row">
                                <span>Frete</span>
                                <span class="shipping-cost">R$<?= number_format($shipping, 2, ',', '.') ?></span>
                                <input type="hidden" id="shipping_cost" name="shipping_cost" value="<?= $shipping ?>">
                            </div>
                            <?php if ($discount > 0): ?>
                                <div class="price-row">
                                    <span>Desconto</span>
                                    <span>-R$<?= number_format($discount, 2, ',', '.') ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="price-row price-total">
                                <span>Total</span>
                                <span>R$<?= number_format($total, 2, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div id="processing-overlay" class="processing-overlay" style="display: none;">
            <div class="processing-spinner"></div>
            <div class="processing-message">Processando seu pedido...</div>
        </div>

        <div id="pix-modal" class="modal-overlay" style="display: none;">
            <div class="modal-content">
                <span class="modal-close">×</span>
                <h2>Pagamento via PIX</h2>
                <br>
                <div class="pix-qrcode"></div>
                <div class="pix-container">
                    <div class="pix-code">
                        <span id="pix-code-text"></span>
                    </div>
                </div>
                <p class="pix-instructions">
                    Escaneie o QR Code com o aplicativo do seu banco ou copie o código PIX.
                    <br>
                </p>
                <button class="copy-button" id="copy-pix-code">Copiar Código do PIX</button>
                <button class="pix-confirm-btn" id="pix-confirm-payment">Confirmar Pagamento</button>
            </div>
        </div>

        <!-- Rodapé -->
        <footer>
            <div id="content">
                <div id="contacts">
                    <div class="logo2">
                        <img src="img/logo2.png" alt="Logo">
                    </div>
                    <p>Transformando ambientes com beleza natural.</p>
                </div>
                <ul class="list">
                    <li>
                        <h3>Avaliação</h3>
                    </li>
                    <li><a href="blog.php" class="link">Cuidados com Flores</a></li>
                    <li><a href="blog.php" class="link">Manutenção de Aquários</a></li>
                    <li><a href="blog.php" class="link">Dicas de Decoração</a></li>
                </ul>
                <ul class="list">
                    <li>
                        <h3>Contatos</h3>
                    </li>
                    <li><a href="<?= htmlspecialchars($instagram) ?>" class="link">Instagram</a></li>
                    <li><a href="mailto:<?= htmlspecialchars($email) ?>" class="link">Email</a></li>
                    <li><a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>" class="link">WhatsApp</a></li>
                </ul>
                <ul class="list">
                    <li>
                        <h3>Termos de Segurança</h3>
                    </li>
                    <li><a href="../politica-de-privacidade.php" class="link">Política de Privacidade</a></li>
                    <li><a href="../termos-de-servico.php" class="link">Termos de Serviço</a></li>
                </ul>
                <ul class="list">
                    <li>
                        <h3>Newsletter</h3>
                    </li>
                    <li>
                        <form class="newsletter-form">
                            <input type="email" placeholder="Digite seu e-mail" class="email-input" required>
                            <button type="submit" class="subscribe-btn">Inscrever-se</button>
                        </form>
                    </li>
                </ul>
            </div>
            <div class="cnpj-section">
                <p>CNPJ: 37.804.018/0001-56</p>
            </div>
            <div class="payment-section">
                <h3>Formas de Pagamento</h3>
                <li class="payment-methods">
                    <img src="img/pagamento/visa.png" alt="Visa" class="payment-icon">
                    <img src="img/pagamento/master.png" alt="Mastercard" class="payment-icon">
                    <img src="img/pagamento/amex.png" alt="American Express" class="payment-icon">
                    <img src="img/pagamento/elo.png" alt="Paypal" class="payment-icon">
                    <img src="img/pagamento/pix.png" alt="Pix" class="payment-icon">
                    <img src="img/pagamento/bradesco.png" alt="Bradesco" class="payment-icon">
                </li>
            </div>
            <div id="copyright"></div>
        </footer>

        <!-- Scripts -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/js/all.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script src="js/checkout/checkout.js"></script>
        <script src="js/elementos/element.js"></script>
        <script>
            // Esconder a tela de carregamento quando a página estiver pronta
            window.addEventListener('load', function() {
                document.getElementById('loading-screen').style.display = 'none';
            });
        </script>
</body>

</html>