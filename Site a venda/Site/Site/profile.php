<?php
require_once '../adminView/config/dbconnect.php';
require_once '../adminView/controller/Produtos/UserCartController.php';
require_once '../adminView/controller/Produtos/UserFavoritesController.php';
require_once '../adminView/controller/Produtos/ProductController.php';
$userCartController = new UserCartController($conn);
$UserFavoritesController = new UserFavoritesController($conn);
$productController = new ProductController($conn);
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    header('Location: login_site.php');
    exit;
}

// Obtém as informações do usuário da sessão
$userId = $_SESSION['user_id'];
$userName = $_SESSION['username'];
$userPicture = $_SESSION['user_picture'] ?? 'img/icons/perfil.png';
$userEmail = $_SESSION['user_email'] ?? '';

// Obter contagens reais de carrinho e favoritos usando os novos métodos
$orderCount = 0; // Mantido como 0 pois não temos um método específico para pedidos ainda
$favoriteItems = $UserFavoritesController->getFavoriteItems($userId);
$cartItems = $userCartController->getCartItems($userId);
$favoriteCount = count($favoriteItems);
$cartCount = count($cartItems);

// Verifica se o usuário está logado
$isLoggedIn = isset($_SESSION['user_id']) && $_SESSION['logged_in'] === true;

if ($isLoggedIn) {
    $userName = strlen($userName) > 16 ? substr($userName, 0, 16) . "..." : $userName;

    // Verifica se a foto de perfil está no banco de dados ou na sessão
    require_once '../adminView/config/dbconnect.php';

    if (empty($_SESSION['user_picture']) || empty($_SESSION['user_email'])) { // Verifica se o email também está na sessão
        $query = "SELECT profile_picture, email, name, telefone, endereco, cep, numero_casa FROM usuarios WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $userPicture = $row['profile_picture'];
            $userEmail = $row['email'];
            $userNome = $row['name']; // Changed from 'nome' to 'name'
            $userTelefone = $row['telefone'];
            $userEndereco = $row['endereco'];
            $userCep = $row['cep'];
            $userNumeroCasa = $row['numero_casa'];

            $_SESSION['user_picture'] = $userPicture;
            $_SESSION['user_email'] = $userEmail;
        } else {
            $userPicture = 'img/icons/perfil.png';
            $userEmail = '';
            $userNome = '';
            $userTelefone = '';
            $userEndereco = '';
            $userCep = '';
            $userNumeroCasa = '';
        }
    } else {
        $userPicture = $_SESSION['user_picture'];
        $userEmail = $_SESSION['user_email']; // Obtém o email da sessão

        // Buscar dados adicionais do usuário
        require_once '../adminView/config/dbconnect.php';
        $query = "SELECT profile_picture, email, name, telefone, endereco, cep, numero_casa FROM usuarios WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $userNome = $row['name'];
            $userTelefone = $row['telefone'];
            $userEndereco = $row['endereco'];
            $userCep = $row['cep'];
            $userNumeroCasa = $row['numero_casa'];
        }
    }

    // Buscar notificações do usuário
    require_once '../adminView/config/dbconnect.php';
    $query = "SELECT id, titulo, mensagem, data_criacao, lida FROM notificacoes WHERE usuario_id = ? ORDER BY data_criacao DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $notificacoes = $stmt->get_result();

    // Buscar os pedidos do usuário
    $queryCompras = "SELECT o.*, 
    (SELECT p.imagem FROM order_items oi JOIN produtos p ON oi.product_id = p.id WHERE oi.order_id = o.id LIMIT 1) as imagem,
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as qtd_itens FROM orders o WHERE o.user_id = ? ORDER BY o.order_date DESC";
    $stmtCompras = $conn->prepare($queryCompras);
    $stmtCompras->bind_param("i", $userId);
    $stmtCompras->execute();
    $resultCompras = $stmtCompras->get_result();
    $compras = $resultCompras->fetch_all(MYSQLI_ASSOC);

    // Converter os dados das compras para JSON
    $comprasJson = json_encode($compras);
}

// Processamento de filtros e busca de produtos
function fetchProducts($productController, $conn)
{
    // Sanitização de entradas
    $categoria = isset($_GET['categoria']) ? htmlspecialchars($_GET['categoria']) : '';
    $orderBy = isset($_GET['orderBy']) ? htmlspecialchars($_GET['orderBy']) : '';
    $search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

    // Lógica de busca de produtos
    if (!empty($categoria)) {
        return $productController->getProductsByCategory($categoria);
    } elseif (!empty($orderBy)) {
        return $productController->getProductsOrderedBy($orderBy);
    } elseif (!empty($search)) {
        return $productController->searchProducts($search);
    } else {
        // Padrão: obter todos os produtos
        return $productController->getAllProducts();
    }
}

// Buscar produtos e adicionar o caminho da imagem
$produtosRaw = fetchProducts($productController, $conn);
$produtos = [];

// Adicionar o caminho correto das imagens
foreach ($produtosRaw as $produto) {
    // Certifique-se de que a imagem existe e não tem já o caminho completo
    if (!empty($produto['imagem'])) {
        // Adiciona o caminho apenas se a imagem não tiver o caminho completo
        if (strpos($produto['imagem'], '/') === false && strpos($produto['imagem'], '\\') === false) {
            $produto['imagem_path'] = '../adminView/uploads/produtos/' . $produto['imagem'];
        } else {
            // Se já for um caminho, apenas copie
            $produto['imagem_path'] = $produto['imagem'];
        }
    } else {
        // Imagem padrão se não existir
        $produto['imagem_path'] = '../adminView/uploads/produtos/placeholder.jpeg';
    }

    $produtos[] = $produto;
}

// Função auxiliar para obter o caminho da imagem do produto
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

$siteConfigPath = __DIR__ . '/../adminView/config_site.json';
error_log("Caminho do arquivo de configuração: " . realpath($siteConfigPath));

if (!file_exists($siteConfigPath)) {
    error_log("Erro: Arquivo de configuração não encontrado.");
    echo "Erro ao carregar as configurações do site.";
    return;
}

$jsonContent = file_get_contents($siteConfigPath);
if ($jsonContent === false) {
    error_log("Erro ao ler o arquivo config_site.json.");
    echo "Erro ao carregar as configurações do site.";
    return;
} else {
    error_log("Conteúdo do config_site.json: $jsonContent");
}

$configData = json_decode($jsonContent, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Erro ao decodificar JSON: " . json_last_error_msg());
    echo "Erro ao carregar as configurações do site.";
    return;
} else {
    error_log("Dados decodificados: " . print_r($configData, true));
}

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

$sobreMidia = $getConfigValue($configData, ['pagina_inicial', 'sobre', 'midia']);
$whatsapp = $getConfigValue($configData, ['contato', 'whatsapp']);
$instagram = $getConfigValue($configData, ['contato', 'instagram'], '#');
$facebook = $getConfigValue($configData, ['contato', 'facebook'], '#');
$email = $getConfigValue($configData, ['contato', 'email'], '#');
$footerTexto = $getConfigValue($configData, ['rodape', 'texto']);
?>


<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../adminView/assets/images/logo.png" type="image/x-icon">
    <title>Cristais Gold Lar - Configurações</title>
    <link rel="stylesheet" href="css/profile/profile.css">
    <link rel="stylesheet" href="css/elements.css">
    <link rel="stylesheet" href="css/index/index-responsivo.css">
    <link rel="stylesheet" href="css/profile/profile-responsivo.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>
    <div id="loading-screen">
        <div class="loader"></div>
    </div>

    <div class="notification-bar">
        <div class="message active" id="message1">Até 5x Sem Juros</div>
        <div class="message" id="message2">Frete Grátis para São Paulo!</div>
    </div>
    <header class="navbar">
        <nav>
            <div class="menu-hamburguer" id="menu-btn">
                <label class="hamburger">
                    <input type="checkbox" id="toggle-menu" />
                    <svg viewBox="0 0 32 32">
                        <path class="line line-top-bottom" d="M27 10 13 10C10.8 10 9 8.2 9 6 9 3.5 10.8 2 13 2 15.2 2 17 3.8 17 6L17 26C17 28.2 18.8 30 21 30 23.2 30 25 28.2 25 26 25 23.8 23.2 22 21 22L7 22">
                        </path>
                        <path class="line" d="M7 16 27 16"></path>
                    </svg>
                </label>
            </div>
            <ul class="nav-links">
                <li><a href="index.php">Início</a></li>
                <li><a href="index.php">Sobre</a></li>
                <li><a href="index.php">Arranjos</a></li>
                <li><a href="galeria.php">Galeria</a></li>
            </ul>
            <div class="logo">
                <img src="img/logo.png" alt="Logo">
            </div>
            <div class="nav-icons">
                <ul class="nav-links">
                    <li><a href="blog.php">Blog</a></li>
                    <li><a href="compras.php">Loja</a></li>
                </ul>
                <div class="dropdown">
                    <a href="#" id="profile-btn">
                        <img src="includes/configuracoes/image_proxy.php?url=<?= urlencode($userPicture) ?>" alt="Foto de Perfil" id="profile-pic">
                        <span class="profile-toggle">
                            <span id="username-display"><?= $isLoggedIn ? htmlspecialchars($userName) : 'Entrar/Cadastrar' ?></span>
                            <img src="img/icons/seta.png" alt="Seta" class="arrow">
                        </span>
                    </a>
                    <div class="dropdown-menu">
                        <?php if ($isLoggedIn) : ?>
                            <a href="includes/configuracoes/logout.php" class="logout-btn">Sair</a>
                            <a href="profile.php" class="config-btn">Configurações</a> <?php else : ?>
                            <button class="google-login" onclick="location.href='google_login.php'">Entrar com Google</button>
                            <a href="profile.php" class="facebook-login">Configurações</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
        <div class="mobile-menu" id="mobile-menu">
            <button class="close-btn" id="close-btn">×</button>
            <div class="dropdown">
                <a href="<?= $isLoggedIn ? 'profile.php' : 'login_site.php' ?>" id="mobile-profile-btn">
                    <img src="<?= $isLoggedIn ? htmlspecialchars($userPicture) : 'img/icons/perfil.png' ?>" alt="Entrar/Cadastrar-se" alt="Foto de Perfil" id="profile-pic">
                    <span class="profile-toggle">
                        <?= $isLoggedIn ? htmlspecialchars($userName) : 'Entrar/Cadastre-se' ?>
                        <img src="img/icons/seta.png" alt="Seta" class="arrow">
                    </span>
                </a>
                <div class="dropdown-menu">
                    <?php if ($isLoggedIn) : ?>
                        <a href="includes/configuracoes/logout.php" class="logout-btn">Sair</a>
                        <a href="profile.php" class="config-btn">Configurações</a> <?php else : ?>
                        <button class="google-login" onclick="location.href='google_login.php'">Entrar com Google</button>
                        <a href="profile.php" class="facebook-login">Configurações</a>
                    <?php endif; ?>
                </div>
            </div>
            <ul class="mobile-nav-links">
                <li><a href="index.php">Início</a></li>
                <li><a href="index.php">Sobre</a></li>
                <li><a href="index.php">Arranjos</a></li>
                <li><a href="galeria.php">Galeria</a></li>
                <li><a href="blog.php">Blog</a></li>
                <li><a href="compras.php">Loja</a></li>
            </ul>
        </div>
    </header>

    <section class="hero1" id="inicio">
        <a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>" class="whatsapp-btn">
            <img src="img/icons/whats.png" alt="WhatsApp">
        </a>
    </section>

    <div class="background-container">

        <div class="profile-header">
            <div class="profile-info">
                <img src="<?= htmlspecialchars($userPicture) ?>" alt="Foto de Perfil" class="profile-picture">
                <h1><?= htmlspecialchars($userName) ?></h1>
                <p class="profile-email"><?= htmlspecialchars($userEmail) ?></p>
            </div>
        </div>

        <div class="button-group">
            <button class="button active" data-section="minhas-compras">Minhas Compras</button>
            <button class="button" data-section="notificacoes">Notificações</button>
            <button class="button" data-section="senha">Senha</button>
            <button class="button" data-section="meus-dados">Meus Dados</button>
        </div>

        <div class="profile-sections">
            <div class="profile-section active" id="minhas-compras">
                <div class="compras-grid">
                    <div class="compras-coluna">
                        <h4>Carrinho: <span class="count-badge"><?php echo $cartCount; ?></span></h4>
                        <div class="item-lista">
                            <?php
                            $cart = $userCartController->getCartItems($userId);
                            if (!empty($cart)) {
                                foreach ($cart as $item) {
                                    echo '<div class="item cart-box" data-id="' . $item['product_id'] . '">';
                                    echo '<div class="item-image">';
                                    echo '<img src="' . htmlspecialchars($produto['imagem_path'] ?? '') . '" alt="' . htmlspecialchars($item['nome'] ?? '') . '">';
                                    echo '</div>';
                                    echo '<div class="item-info">';
                                    echo '<h3>' . htmlspecialchars($item['nome']) . '</h3>';
                                    echo '<p class="item-price">R$ ' . number_format($item['preco'], 2, ',', '.') . '</p>';
                                    echo '<div class="quantidade-controle">';
                                    echo '<button class="decrement"><i class="fas fa-minus"></i></button>';
                                    echo '<span class="number">' . htmlspecialchars($item['quantity']) . '</span>';
                                    echo '<button class="increment"><i class="fas fa-plus"></i></button>';
                                    echo '</div>';
                                    echo '<p class="item-subtotal">Subtotal: R$ ' . number_format($item['preco'] * $item['quantity'], 2, ',', '.') . '</p>';
                                    echo '<div class="item-actions">';
                                    echo '<a href="javascript:void(0)" class="btn-action cart-remove"><i class="fas fa-trash"></i></a>';
                                    echo '</div>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                                // Mostrar total do carrinho
                                $totalCart = array_reduce($cart, function ($carry, $item) {
                                    return $carry + ($item['preco'] * $item['quantity']);
                                }, 0);
                                echo '<div class="cart-total">';
                                echo '<p>Total: <strong class="total-price">R$ ' . number_format($totalCart, 2, ',', '.') . '</strong></p>';
                                echo '<a href="checkout.php" class="btn-checkout">Finalizar Compra</a>';
                                echo '</div>';
                            } else {
                                echo '<div class="empty-message">';
                                echo '<p>Seu carrinho está vazio.</p>';
                                echo '<a href="compras.php" class="btn-shop">Ver Produtos</a>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Parte 2: Ajuste do HTML dos favoritos -->
                    <div class="compras-coluna">
                        <h4>Favoritos: <span class="count-badge"><?php echo $favoriteCount; ?></span></h4>
                        <div class="item-lista">
                            <?php
                            $favorites = $UserFavoritesController->getFavoriteItems($userId);
                            if (!empty($favorites)) {
                                foreach ($favorites as $item) {
                                    echo '<div class="item saved-box" data-id="' . $item['id'] . '">';
                                    echo '<div class="item-image">';
                                    echo '<img src="' . htmlspecialchars($produto['imagem_path'] ?? '') . '" alt="' . htmlspecialchars($item['nome']) . '">';
                                    echo '</div>';
                                    echo '<div class="item-info">';
                                    echo '<h3>' . htmlspecialchars($item['nome']) . '</h3>';
                                    echo '<p class="item-price">R$ ' . number_format($item['preco'], 2, ',', '.') . '</p>';
                                    echo '<div class="item-actions">';
                                    echo '<a href="javascript:void(0)" class="btn-action move-to-cart"><i class="fas fa-shopping-cart"></i></a>';
                                    echo '<a href="javascript:void(0)" class="btn-action saved-remove"><i class="fas fa-heart-broken"></i></a>';
                                    echo '</div>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<div class="empty-message">';
                                echo '<p>Você não tem itens favoritos.</p>';
                                echo '<a href="compras.php" class="btn-shop">Ver Produtos</a>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="compras-coluna">
                        <h4>Meus Pedidos</h4>
                        <div class="item-lista">
                            <?php
                            if ($resultCompras->num_rows > 0) {
                                foreach ($compras as $compra) {
                                    // Formatar valores monetários
                                    $total = 'R$ ' . number_format($compra['total'], 2, ',', '.');

                                    // Determinar o status em português
                                    $statusTexto = '';
                                    switch ($compra['status']) {
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
                                        default:
                                            $statusTexto = ucfirst($compra['status']);
                                            $statusClass = 'status-padrao';
                                    }

                                    // Formatar método de pagamento
                                    $metodoPagamento = '';
                                    switch ($compra['payment_method']) {
                                        case 'credit_card':
                                            $metodoPagamento = 'Cartão de Crédito';
                                            if (!empty($compra['card_last4'])) {
                                                $metodoPagamento .= ' (Final ' . $compra['card_last4'] . ')';
                                            }
                                            break;
                                        case 'pix':
                                            $metodoPagamento = 'PIX';
                                            break;
                                        default:
                                            $metodoPagamento = ucfirst($compra['payment_method']);
                                    }

                                    // Formatar data
                                    $data = date('d/m/Y H:i', strtotime($compra['order_date']));

                                    echo '<div class="item pedido">';

                                    // Se tiver imagem, exibe
                                    if (!empty($compra['imagem'])) {
                                        echo '<img src="' . htmlspecialchars($produto['imagem_path']) . '" alt="Imagem do pedido">';
                                    } else {
                                        echo '<div class="sem-imagem">📦</div>';
                                    }

                                    echo '<div class="pedido-info">';
                                    echo '<h3>Pedido #' . htmlspecialchars($compra['id']) . '</h3>';
                                    echo '<p class="pedido-data">Data: ' . $data . '</p>';
                                    echo '<p class="pedido-total">Total: ' . $total . '</p>';
                                    echo '<p class="pedido-qtd">Itens: ' . htmlspecialchars($compra['qtd_itens']) . '</p>';
                                    echo '<p class="pedido-pagamento">Pagamento: ' . $metodoPagamento . '</p>';

                                    // Código de rastreio, se existir
                                    if (!empty($compra['tracking_code'])) {
                                        echo '<p class="pedido-rastreio">Código de Rastreio: ' . htmlspecialchars($compra['tracking_code']) . '</p>';
                                    }

                                    // Status do pedido
                                    echo '<p class="pedido-status"><span class="' . $statusClass . '">' . $statusTexto . '</span></p>';

                                    // Botão para detalhes
                                    echo '<a href="detalhes-pedido.php?id=' . $compra['id'] . '" class="btn-detalhes">Ver Detalhes</a>';

                                    echo '</div></div>';
                                }
                            } else {
                                echo '<p class="sem-pedidos">Você ainda não realizou nenhum pedido.</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="profile-section" id="notificacoes">
                <h3>Notificações</h3>
                <?php if ($notificacoes->num_rows > 0) : ?>
                    <ul>
                        <?php while ($notificacao = $notificacoes->fetch_assoc()) : ?>
                            <li>
                                <strong><?= htmlspecialchars($notificacao['titulo']) ?></strong><br>
                                <?= htmlspecialchars($notificacao['mensagem']) ?><br>
                                <small>
                                    <?= date('d/m/Y H:i', strtotime($notificacao['data_criacao'])) ?>
                                    <?php if ($notificacao['lida']) : ?>
                                        - Lida
                                    <?php endif; ?>
                                </small>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else : ?>
                    <p>Você não tem notificações.</p>
                <?php endif; ?>
            </div>

            <div class="profile-section" id="senha">
                <h3>Senha</h3>
                <?php if (!isset($_SESSION['google_user'])) : ?>
                    <form action="includes/profile/atualizar_senha.php" method="post">
                        <label for="senha_atual">Senha Atual:</label>
                        <div class="password-field">
                            <input type="password" name="senha_atual" id="senha_atual" required>
                            <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('senha_atual')"></i>
                        </div>

                        <label for="nova_senha">Nova Senha:</label>
                        <div class="password-field">
                            <input type="password" name="nova_senha" id="nova_senha" required>
                            <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('nova_senha')"></i>
                        </div>

                        <label for="confirmar_senha">Confirmar Nova Senha:</label>
                        <div class="password-field">
                            <input type="password" name="confirmar_senha" id="confirmar_senha" required>
                            <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('confirmar_senha')"></i>
                        </div>

                        <button type="submit">Atualizar Senha</button>
                    </form>
                <?php else : ?>
                    <p>Você fez login com o Google. Não é possível alterar a senha aqui.</p>
                <?php endif; ?>
            </div>

            <div class="profile-section" id="meus-dados">
                <h3>Meus Dados</h3>
                <form action="includes/profile/atualizar_dados.php" method="post">
                    <label for="nome">Nome:</label>
                    <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($userNome) ?>" required><br>

                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" value="<?= htmlspecialchars($userEmail) ?>" required><br>

                    <label for="telefone">Telefone:</label>
                    <input type="text" name="telefone" id="telefone" value="<?= htmlspecialchars($userTelefone) ?>"><br>

                    <label for="endereco">Endereço:</label>
                    <input type="text" name="endereco" id="endereco" value="<?= htmlspecialchars($userEndereco) ?>"><br>

                    <label for="cep">CEP:</label>
                    <input type="text" name="cep" id="cep" value="<?= htmlspecialchars($userCep) ?>"><br>

                    <label for="numero_casa">Número da Casa:</label>
                    <input type="text" name="numero_casa" id="numero_casa" value="<?= htmlspecialchars($userNumeroCasa) ?>"><br>

                    <button type="submit">Atualizar Dados</button>
                </form>
            </div>
        </div>


        <footer>
            <div id="content">
                <div id="contacts">
                    <div class="logo2">
                        <img src="img/logo2.png" alt="Logo">
                    </div>
                    <p>Transformando ambientes com beleza natural.</p>
                    <p><?= htmlspecialchars($footerTexto) ?></p>
                    <div id="social_media">
                        <a href="<?= htmlspecialchars($instagram) ?>" class="link" id="instagram">
                            <i class="fa-brands fa-instagram"></i>
                        </a>
                        <a href="<?= htmlspecialchars($facebook) ?>" class="link" id="facebook">
                            <i class="fa-brands fa-facebook-f"></i>
                        </a>
                        <a href="mailto:<?= htmlspecialchars($email) ?>" class="link" id="whatsapp">
                            <i class="fa-regular fa-envelope"></i>
                        </a>
                    </div>
                </div>
                <ul class="list">
                    <li>
                        <h3>Blog</h3>
                    </li>
                    <li><a href="blog.php" class="link">Cuidados com Flores</a></li>
                    <li><a href="blog.php" class="link">Manutenção de Aquários</a></li>
                    <li><a href="blog.php" class="link">Dicas de Decoração</a></li>
                </ul>
                <ul class="list">
                    <li>
                        <h3>Produtos</h3>
                    </li>
                    <li><a href="compras.php" class="link">Arranjos Florais</a></li>
                    <li><a href="compras.php" class="link">Aquários Personalizados</a></li>
                    <li><a href="compras.php" class="link">Acessórios</a></li>
                </ul>
                <div id="subscribe">
                    <h3>Fique de Olho!</h3>
                    <p>Receba nossas novidades e promoções exclusivas diretamente no seu WhatsApp</p>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const currentYear = new Date().getFullYear();
                    const copyrightDiv = document.getElementById('copyright');

                    if (copyrightDiv) {
                        copyrightDiv.innerHTML = `Copyright © ${currentYear} Cristais Gold Lar. Todos os direitos reservados`;
                    }
                });
            </script>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const goldenOverlay = document.createElement('div');
            goldenOverlay.className = 'golden-overlay';
            document.body.appendChild(goldenOverlay);

            const leafCount = 30;
            let windowWidth = window.innerWidth;
            let windowHeight = window.innerHeight;
            const leafTypes = ['leaf-1', 'leaf-2', 'leaf-3'];

            for (let i = 0; i < leafCount; i++) {
                createLeaf();
            }

            setInterval(function() {
                const oldLeaves = document.querySelectorAll('.leaf');
                if (oldLeaves.length > 40) {
                    for (let i = 0; i < 3; i++) {
                        if (oldLeaves[i]) oldLeaves[i].remove();
                    }
                }
                for (let i = 0; i < 3; i++) {
                    createLeaf();
                }
            }, 5000);

            function createLeaf() {
                const leaf = document.createElement('div');
                const leafClass = leafTypes[Math.floor(Math.random() * leafTypes.length)];
                leaf.className = `leaf ${leafClass}`;

                leaf.style.left = `-50px`;
                leaf.style.top = `${Math.random() * windowHeight}px`;
                leaf.dataset.scale = `${0.6 + Math.random() * 0.8}`;
                leaf.dataset.sway = 5 + Math.random() * 15;
                leaf.dataset.speed = 30 + Math.random() * 60;
                leaf.dataset.rotationSpeed = 2 + Math.random() * 4;
                leaf.dataset.swayValue = 0;
                leaf.dataset.swayDirection = Math.random() > 0.5 ? 1 : -1;
                leaf.dataset.rotation = Math.random() * 360;
                leaf.dataset.yBase = leaf.style.top.replace('px', '');

                leaf.style.opacity = 0.3 + Math.random() * 0.4;
                leaf.style.transform = `scale(${leaf.dataset.scale})`;

                document.body.appendChild(leaf);

                animateLeaf(leaf);
            }

            function animateLeaf(leaf) {
                let xPos = -50;
                const scale = leaf.dataset.scale;
                const swayAmount = parseFloat(leaf.dataset.sway);
                const speed = parseFloat(leaf.dataset.speed);
                const rotationSpeed = parseFloat(leaf.dataset.rotationSpeed);
                let swayValue = parseFloat(leaf.dataset.swayValue);
                let swayDirection = parseInt(leaf.dataset.swayDirection);
                let rotation = parseFloat(leaf.dataset.rotation);
                const yBase = parseFloat(leaf.dataset.yBase);

                function updatePosition() {
                    windowWidth = window.innerWidth;

                    xPos += speed / 60;
                    swayValue += 0.05 * swayDirection;
                    if (Math.abs(swayValue) > 1) {
                        swayDirection *= -1;
                    }
                    const newYPos = yBase + (swayValue * swayAmount);
                    rotation += rotationSpeed / 10;

                    leaf.style.left = `${xPos}px`;
                    leaf.style.top = `${newYPos}px`;
                    leaf.style.transform = `rotate(${rotation}deg) scale(${scale})`;

                    if (xPos < windowWidth + 150) {
                        requestAnimationFrame(updatePosition);
                    } else {
                        leaf.remove();
                    }
                }

                requestAnimationFrame(updatePosition);
            }

            window.addEventListener('resize', function() {
                windowWidth = window.innerWidth;
                windowHeight = window.innerHeight;
            });

            // EFEITO 1: Mouse passando perto - Folhas se assustam
            document.addEventListener('mousemove', function(e) {
                const mouseX = e.clientX;
                const mouseY = e.clientY;

                document.querySelectorAll('.leaf').forEach(leaf => {
                    const rect = leaf.getBoundingClientRect();
                    const leafX = rect.left + rect.width / 2;
                    const leafY = rect.top + rect.height / 2;

                    const dist = Math.hypot(mouseX - leafX, mouseY - leafY);

                    if (dist < 100) { // Distância de "susto"
                        const angle = Math.atan2(leafY - mouseY, leafX - mouseX);
                        const jumpX = Math.cos(angle) * 100;
                        const jumpY = Math.sin(angle) * 100;

                        // Faz a folha "pular" para longe
                        leaf.style.left = `${parseFloat(leaf.style.left) + jumpX}px`;
                        leaf.style.top = `${parseFloat(leaf.style.top) + jumpY}px`;
                    }
                });
            });

            // EFEITO 2: Toque na tela - folhas trocam de rota
            document.addEventListener('touchstart', function() {
                document.querySelectorAll('.leaf').forEach(leaf => {
                    // Muda aleatoriamente a direção de sway e rotação
                    leaf.dataset.sway = 10 + Math.random() * 20;
                    leaf.dataset.speed = 40 + Math.random() * 80;
                    leaf.dataset.swayDirection = Math.random() > 0.5 ? 1 : -1;
                    leaf.dataset.rotationSpeed = 1 + Math.random() * 5;
                });
            });

        });
    </script>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;

            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>

    <script src="js/elementos/element.js"></script>
    <script src="js/compras/compras.js"></script>
    <script src="js/profile/profile.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.isotope/3.0.6/isotope.pkgd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.imagesloaded/5.0.0/imagesloaded.pkgd.min.js"></script>
</body>

</html>