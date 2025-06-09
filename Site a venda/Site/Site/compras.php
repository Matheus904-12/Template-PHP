<?php
session_start();
require_once '../adminView/config/dbconnect.php';
require_once '../adminView/controller/Produtos/ProductController.php';
require_once '../adminView/controller/Produtos/UserCartController.php';
require_once '../adminView/controller/Produtos/UserFavoritesController.php';

// Inicializar os controladores
$productController = new ProductController($conn);
$userCartController = new UserCartController($conn);
$UserFavoritesController = new UserFavoritesController($conn);

// Verificar se o usuário está logado
$isLoggedIn = isset($_SESSION['user_id']) && $_SESSION['logged_in'] === true;

if ($isLoggedIn) {
    $userName = $_SESSION['username'];
    if (strlen($userName) > 16) {
        $userName = substr($userName, 0, 16) . "...";
    }
    // Verifica se a foto de perfil está no banco de dados ou na sessão
    if (isset($_SESSION['user_picture']) && !empty($_SESSION['user_picture'])) {
        $userPicture = $_SESSION['user_picture'];
    } else {
        $userId = $_SESSION['user_id'];
        $query = "SELECT profile_picture FROM usuarios WHERE id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $userPicture = $row['profile_picture'];
            } else {
                $userPicture = 'img/icons/perfil.png'; // Imagem padrão se não encontrada
            }
        } else {
            $userPicture = 'img/icons/perfil.png'; // Imagem padrão se erro na consulta
        }
    }
}

// Função para buscar dados do carrinho e favoritos
function fetchUserCartAndFavorites($conn, $userId)
{
    $cart = [];
    $favorites = [];
    $uploadPath = '../adminView/uploads/produtos/'; // Defina o caminho base das imagens

    // Buscar itens do carrinho
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

    // Buscar favoritos
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

// Buscar dados do carrinho e favoritos
$cartData = $isLoggedIn
    ? fetchUserCartAndFavorites($conn, $_SESSION['user_id'])
    : ['cart' => [], 'favorites' => []];

$cart = $cartData['cart'];
$favorites = $cartData['favorites'];

// Contar itens
$cartCount = array_sum(array_column($cart, 'quantity')); // Total de itens, considerando quantidade
$favoritesCount = count($favorites);

// Recuperar informações do usuário
function getUserInfo($conn, $isLoggedIn)
{
    if (!$isLoggedIn) {
        return [
            'picture' => 'img/icons/perfil.png',
            'username' => 'Entrar/Cadastrar'
        ];
    }

    $userId = $_SESSION['user_id'];

    // Primeiro, tenta usar dados da sessão
    $userPicture = $_SESSION['user_picture'] ?? '';
    $userName = $_SESSION['username'] ?? '';

    // Se não houver na sessão, busca no banco de dados
    if (empty($userPicture) || empty($userName)) {
        $query = "SELECT username, profile_picture FROM usuarios WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $userPicture = $row['profile_picture'] ?: 'img/icons/perfil.png';
            $userName = $row['username'];

            // Atualiza sessão
            $_SESSION['user_picture'] = $userPicture;
            $_SESSION['username'] = $userName;
        }
    }

    // Trunca o nome de usuário se for muito longo
    if (strlen($userName) > 16) {
        $userName = substr($userName, 0, 16) . "...";
    }

    return [
        'picture' => $userPicture ?: 'img/icons/perfil.png',
        'username' => $userName
    ];
}

$userInfo = getUserInfo($conn, $isLoggedIn);

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
    if (!empty($produto['imagem'])) {
        if (strpos($produto['imagem'], '/') === false && strpos($produto['imagem'], '\\') === false) {
            $produto['imagem_path'] = '../adminView/uploads/produtos/' . $produto['imagem'];
        } else {
            $produto['imagem_path'] = $produto['imagem'];
        }
    } else {
        $produto['imagem_path'] = '../adminView/uploads/produtos/placeholder.jpeg';
    }

    $produtos[] = $produto;
}

// Carregamento de configurações do site
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

try {
    $siteConfigPath = __DIR__ . '/../adminView/config_site.json';
    $configData = loadSiteConfig($siteConfigPath);

    // Função auxiliar para buscar valores de configuração
    function getConfigValue($config, $keys, $default = '')
    {
        $value = $config;
        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return $default;
            }
            $value = $value[$key];
        }
        return is_string($value) ? htmlspecialchars($value) : $value;
    }

    // Extrair configurações específicas
    $sobreMidia = getConfigValue($configData, ['pagina_inicial', 'sobre', 'midia']);
    $whatsapp = getConfigValue($configData, ['contato', 'whatsapp']);
    $instagram = getConfigValue($configData, ['contato', 'instagram'], '#');
    $facebook = getConfigValue($configData, ['contato', 'facebook'], '#');
    $email = getConfigValue($configData, ['contato', 'email'], '#');
    $footerTexto = getConfigValue($configData, ['rodape', 'texto']);
} catch (Exception $e) {
    // Configurações padrão em caso de erro
    $sobreMidia = '';
    $whatsapp = '#';
    $instagram = '#';
    $facebook = '#';
    $email = '#';
    $footerTexto = 'Transformando momentos com arte e natureza.';
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" href="../adminView/assets/images/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cristais Gold Lar - Loja</title>
    <link rel="stylesheet" href="css/compras/compras.css">
    <link rel="stylesheet" href="css/elements.css">
    <link rel="stylesheet" href="css/compras/compras-responsivo.css">
    <link rel="stylesheet" href="css/compras/modal.css">
    <!-- UNICONS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
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
            <!-- Botão do menu hambúrguer (Somente no Mobile) -->
            <div class="menu-hamburguer" id="menu-btn">
                <label class="hamburger">
                    <input type="checkbox" id="toggle-menu" />
                    <svg viewBox="0 0 32 32">
                        <path class="line line-top-bottom"
                            d="M27 10 13 10C10.8 10 9 8.2 9 6 9 3.5 10.8 2 13 2 15.2 2 17 3.8 17 6L17 26C17 28.2 18.8 30 21 30 23.2 30 25 28.2 25 26 25 23.8 23.2 22 21 22L7 22">
                        </path>
                        <path class="line" d="M7 16 27 16"></path>
                    </svg>
                </label>
            </div>

            <!-- Links e Ícones da Navbar (Desktop) -->
            <ul class="nav-links">
                <li><a href="index.php">Voltar ao Início</a></li>
                <li><a href="index.php">Arranjos</a></li>
                <li><a href="galeria.php">Galeria</a></li>
                <li><a href="blog.php">Blog</a></li>
            </ul>
            <div class="logo">
                <img src="img/logo.png" alt="Logo">
            </div>
            <div class="nav-icons">
                <div class="cart-container">
                    <img src="img/icons/compras.png" alt="Carrinho" id="cart-icon">
                    <span class="cart-item-count"><?= $cartCount ?></span>
                </div>
                <ul class="nav-links">
                    <li class="favorites-btn">
                        <a href="#" alt="Favoritos" id="salvar">Favoritos</a>
                        <span class="favorites-counter"><?= $favoritesCount ?></span>
                    </li>
                </ul>
                <div class="dropdown">
                    <a href="#" id="profile-btn">
                        <img src="<?= htmlspecialchars($userInfo['picture']) ?>" alt="Foto de Perfil" id="profile-pic">
                        <span class="profile-toggle">
                            <span id="username-display"><?= htmlspecialchars($userInfo['username']) ?></span>
                            <img src="img/icons/seta.png" alt="Seta" class="arrow">
                        </span>
                    </a>
                    <div class="dropdown-menu">
                        <?php if ($isLoggedIn) : ?>
                            <a href="includes/configuracoes/logout.php" class="logout-btn">Sair</a>
                            <a href="profile.php" class="config-btn">Configurações</a>
                        <?php else : ?>
                            <button class="google-login" onclick="location.href='google_login.php'">Entrar com Google</button>
                            <button class="google-login" onclick="location.href='login_site.php'">Cadastrar</button>
                            <button class="google-login" onclick="location.href='profile.php'">Configurações</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Menu Mobile (Escondido por padrão) -->
        <div class="mobile-menu" id="mobile-menu">
            <button class="close-btn" id="close-btn">×</button>
            <div class="dropdown">
                <a href="<?= $isLoggedIn ? 'profile.php' : 'login_site.php' ?>" id="mobile-profile-btn">
                    <img src="<?= htmlspecialchars($userInfo['picture']) ?>" alt="Foto de Perfil" id="profile-pic">
                    <span class="profile-toggle">
                        <?= htmlspecialchars($userInfo['username']) ?>
                        <img src="img/icons/seta.png" alt="Seta" class="arrow">
                    </span>
                    </a>
                    <div class="dropdown-menu">
                        <?php if ($isLoggedIn) : ?>
                            <a href="includes/configuracoes/logout.php" class="logout-btn">Sair</a>
                            <a href="profile.php" class="config-btn">Configurações</a>
                        <?php else : ?>
                            <button class="google-login" onclick="location.href='google_login.php'">Entrar com Google</button>
                            <a href="profile.php" class="facebook-login">Configurações</a>
                        <?php endif; ?>
                    </div>
                </div>
                <ul class="mobile-nav-links">
                    <li><a href="index.php">Voltar ao Início</a></li>
                    <li><a href="index.php">Arranjos</a></li>
                    <li><a href="galeria.php">Galeria</a></li>
                    <li><a href="blog.php">Blog</a></li>
                </ul>
            </div>
        </header>

        <div class="cart">
            <h2 class="cart-title">Seu Carrinho</h2>
            <div class="cart-content">
                <?php if (empty($cart)) : ?>
                    <div class="empty-cart">Seu carrinho está vazio</div>
                <?php else : ?>
                    <?php foreach ($cart as $item) : ?>
                        <div class="cart-box" data-id="<?= $item['id'] ?>">
                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="cart-img">
                            <div class="detail-box">
                                <div class="cart-product-title"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="cart-price">R$ <?= number_format($item['price'], 2, ',', '.') ?></div>
                                <div class="cart-quantity">
                                    <button class="decrement">-</button>
                                    <span class="number"><?= $item['quantity'] ?></span>
                                    <button class="increment">+</button>
                                </div>
                            </div>
                            <i class="ri-delete-bin-line cart-remove"></i>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="total">
                <div class="total-title">Total</div>
                <div class="total-price">R$ 0,00</div>
            </div>
            <button class="btn-buy">Finalizar Compra</button>
            <i class="ri-close-line" id="cart-close"></i>
        </div>

        <div class="saved-items">
            <h2 class="saved-items-title">Itens Salvos</h2>
            <div class="saved-items-content">
                <?php if (empty($favorites)) : ?>
                    <div class="empty-saved">Você não tem produtos salvos</div>
                <?php else : ?>
                    <?php foreach ($favorites as $item) : ?>
                        <div class="saved-box" data-id="<?= $item['id'] ?>">
                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="saved-item-img">
                            <div class="detail-box">
                                <div class="saved-item-title"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="saved-item-price">R$ <?= number_format($item['price'], 2, ',', '.') ?></div>
                            </div>
                            <i class="ri-shopping-cart-line move-to-cart"></i>
                            <i class="ri-delete-bin-line saved-remove"></i>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="saved-items-summary">
                <div class="total-items">
                    <div class="total-items-title">Total de Itens</div>
                    <div class="total-items-count"><?= $favoritesCount ?></div>
                </div>
            </div>
            <button class="btn-move-to-cart">Mover Tudo para o Carrinho</button>
            <i class="ri-close-line" id="saved-items-close"></i>
        </div>

        <br><br><br><br><br><br>

        <section class="hero" id="inicio">
            <div class="hero-background">
                <div class="hero-circle one"></div>
                <div class="hero-circle two"></div>
            </div>
            <div class="hero-content">
                <h1>Encante-se com a beleza das flores</h1>
                <p>Transforme momentos especiais com arranjos florais únicos e sofisticados. Cada flor conta uma história,
                    cada pétala carrega emoção. Escolha o presente perfeito para encantar quem você ama.</p>
            </div>
            <div class="floating-buttons">
                <div class="floating-button" id="floating-cart">
                    <img src="img/icons/cesta.png" alt="Carrinho">
                    <span class="counter cart-item-count"><?= $cartCount ?></span>
                </div>
                <div class="floating-button" id="floating-favorites">
                    <img src="img/icons/salvar.png" alt="Favoritos">
                    <span class="counter"><?= $favoritesCount ?></span>
                </div>
                <div class="floating-button" id="floating-profile">
                    <img src="img/icons/perfil2.png" alt="Perfil">
                </div>
                <div class="floating-button" id="floating-search">
                    <img src="img/icons/buscar.png" alt="Buscar">
                </div>
            </div>
            <a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>" class="whatsapp-btn">
                <img src="img/icons/whats.png" alt="WhatsApp">
            </a>
        </section>

        <div class="bottom-navbar">
            <a href="#" class="nav-item"><img src="img/icons/salvar.png" alt="Favoritos" class="salvar"></a>
            <a href="#buscar" class="nav-item"><img src="img/icons/buscar.png" alt="Buscar"></a>
            <a href="profile.php" class="nav-item"><img src="img/icons/perfil2.png" alt="Perfil"></a>
            <a href="#" class="nav-item">
                <img src="img/icons/cesta.png" alt="Carrinho" class="cesta" id="cart-icon">
                <span class="cart-item-count"><?= $cartCount ?></span>
            </a>
        </div>

        <div class="background-container" id="back">
            <section class="shop">
                <div class="search-filter-container">
                    <div class="search-container">
                        <form method="GET" action="">
                            <input type="text" name="search" class="search-input" placeholder="Buscar produtos..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                            <button type="submit" class="search-button">
                                <i class="ri-search-line search-icon"></i>
                            </button>
                        </form>
                    </div>
                    <div class="filter-menu">
                        <label class="main">
                            Classificar por:
                            <span class="selected-filter">
                                <?php
                                $filterLabels = [
                                    'destaque' => 'Em Destaque',
                                    'vendidos' => 'Mais Vendidos',
                                    'promocao' => 'Em Promoção',
                                    'baratos' => 'Mais Baratos',
                                    'caros' => 'Mais Caros'
                                ];
                                echo isset($_GET['orderBy']) && isset($filterLabels[$_GET['orderBy']])
                                    ? $filterLabels[$_GET['orderBy']]
                                    : 'Em Destaque';
                                ?>
                            </span>
                            <input class="inp" checked="" type="checkbox" />
                            <section class="menu-container">
                                <div class="menu-list" onclick="location.href='?orderBy=destaque'">Em Destaque</div>
                                <div class="menu-list" onclick="location.href='?orderBy=vendidos'">Mais Vendidos</div>
                                <div class="menu-list" onclick="location.href='?orderBy=promocao'">Em Promoção</div>
                                <div class="menu-list" onclick="location.href='?orderBy=baratos'">Mais Baratos</div>
                                <div class="menu-list" onclick="location.href='?orderBy=caros'">Mais Caros</div>
                            </section>
                        </label>
                    </div>
                </div>
                <div class="product-content">
                    <?php if (empty($produtos)) : ?>
                        <div class="no-products">
                            <p>Nenhum produto encontrado!</p>
                        </div>
                    <?php else : ?>
                        <?php foreach ($produtos as $produto) : ?>
                            <div class="product-box"
                                data-id="<?= $produto['id'] ?>"
                                data-name="<?= htmlspecialchars($produto['nome']) ?>"
                                data-price="<?= $produto['preco'] ?>"
                                data-image="<?= htmlspecialchars($produto['imagem_path']) ?>"
                                data-description="<?= htmlspecialchars($produto['descricao']) ?>">
                                <div class="img-box">
                                    <img src="<?= htmlspecialchars($produto['imagem_path']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>">
                                </div>
                                <h2 class="product-title"><?= htmlspecialchars($produto['nome']) ?></h2>
                                <span class="price">R$<?= number_format($produto['preco'], 2, ',', '.') ?></span>
                                <p class="price-info">
                                    R$<?= number_format($produto['preco'] * 0.95, 2, ',', '.') ?> à vista ou
                                    <?php
                                    $parcelas = min(5, ceil($produto['preco'] / 50));
                                    $valor_parcela = $produto['preco'] / $parcelas;
                                    ?>
                                    <?= $parcelas ?>x de R$<?= number_format($valor_parcela, 2, ',', '.') ?> sem juros
                                </p>
                                <div class="price-and-cart">
                                    <i class="ri-heart-line save-item"></i>
                                    <i class="ri-shopping-bag-line add-cart"></i>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

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
                        <li><h3>Blog</h3></li>
                        <li><a href="blog.php" class="link">Cuidados com Flores</a></li>
                        <li><a href="blog.php" class="link">Manutenção de Aquários</a></li>
                        <li><a href="blog.php" class="link">Dicas de Decoração</a></li>
                    </ul>
                    <ul class="list">
                        <li><h3>Produtos</h3></li>
                        <li><a href="compras.php" class="link">Arranjos Florais</a></li>
                        <li><a href="compras.php" class="link">Aquários Personalizados</a></li>
                        <li><a href="compras.php" class="link">Acessórios</a></li>
                    </ul>
                    <ul class="list">
                    <li>
                        <h3>Termos de Segurança</h3>
                    </li>
                    <li><a href="../politica-de-privacidade.php" class="link">Política de Privacidade</a></li>
                    <li><a href="../termos-de-servico.php" class="link">Termos de Serviço</a></li>
                </ul>
                </div>
                <div id="copyright"></div>
            </footer>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const currentYear = new Date().getFullYear();
                const copyrightDiv = document.getElementById('copyright');
                if (copyrightDiv) {
                    copyrightDiv.innerHTML = `Copyright © ${currentYear} Cristais Gold Lar. Todos os direitos reservados`;
                }

                // Efeito de folhas flutuantes
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

                document.addEventListener('mousemove', function(e) {
                    const mouseX = e.clientX;
                    const mouseY = e.clientY;

                    document.querySelectorAll('.leaf').forEach(leaf => {
                        const rect = leaf.getBoundingClientRect();
                        const leafX = rect.left + rect.width / 2;
                        const leafY = rect.top + rect.height / 2;

                        const dist = Math.hypot(mouseX - leafX, mouseY - leafY);

                        if (dist < 100) {
                            const angle = Math.atan2(leafY - mouseY, leafX - mouseX);
                            const jumpX = Math.cos(angle) * 100;
                            const jumpY = Math.sin(angle) * 100;

                            leaf.style.left = `${parseFloat(leaf.style.left) + jumpX}px`;
                            leaf.style.top = `${parseFloat(leaf.style.top) + jumpY}px`;
                        }
                    });
                });

                document.addEventListener('touchstart', function() {
                    document.querySelectorAll('.leaf').forEach(leaf => {
                        leaf.dataset.sway = 10 + Math.random() * 20;
                        leaf.dataset.speed = 40 + Math.random() * 80;
                        leaf.dataset.swayDirection = Math.random() > 0.5 ? 1 : -1;
                        leaf.dataset.rotationSpeed = 1 + Math.random() * 5;
                    });
                });
            });
        </script>

        <script src="js/elementos/element.js"></script>
        <script src="js/compras/compras.js"></script>
        <script src="js/compras/modal.js"></script>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
        <div id="g_id_onload"
            data-client_id="818588658305-7hfcrmuocusbi88bpq0insq09srdv8jd.apps.googleusercontent.com"
            data-context="signin"
            data-ux_mode="popup"
            data-callback="handleCredentialResponse"
            data-auto_prompt="false">
        </div>
    </body>
</html>