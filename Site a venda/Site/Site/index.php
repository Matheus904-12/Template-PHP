<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../adminView/config/dbconnect.php'; // Inclua a conexão primeiro
require_once '../adminView/controller/Produtos/ProductController.php';

// Inicializar os controladores
$productController = new ProductController($conn);

// Verifica se o usuário está logado
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
                $userPicture = '/img/icons/perfil.png'; // Imagem padrão se não encontrada
            }
        } else {
            $userPicture = '/img/icons/perfil.png'; // Imagem padrão se erro na consulta
        }
    }
}

// Verificar se foi solicitada uma operação de filtragem ou pesquisa
$produtos = [];
if (isset($_GET['categoria']) && !empty($_GET['categoria'])) {
    $produtos = $productController->getProductsByCategory($_GET['categoria']);
} elseif (isset($_GET['orderBy']) && !empty($_GET['orderBy'])) {
    $produtos = $productController->getProductsOrderedBy($_GET['orderBy']);
} elseif (isset($_GET['search']) && !empty($_GET['search'])) {
    $produtos = $productController->searchProducts($_GET['search']);
} else {
    // Padrão: obter todos os produtos
    $produtos = $productController->getAllProducts();
}

$siteConfigPath = __DIR__ . '/../adminView/config_site.json';
if (!file_exists($siteConfigPath)) {
    echo "Erro ao carregar as configurações do site.";
    return;
}

$jsonContent = file_get_contents($siteConfigPath);
if ($jsonContent === false) {
    echo "Erro ao carregar as configurações do site.";
    return;
}

$configData = json_decode($jsonContent, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Erro ao carregar as configurações do site.";
    return;
}

function getConfigValue($config, $keys, $default = '')
{
    $value = $config;
    foreach ($keys as $key) {
        if (!isset($value[$key])) {
            return $default;
        }
        $value = $value[$key];
    }
    if (is_string($value)) {
        return htmlspecialchars($value);
    }
    return $value;
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

$sobreMidia = getConfigValue($configData, ['pagina_inicial', 'sobre', 'midia']);
$whatsapp = getConfigValue($configData, ['contato', 'whatsapp']);
$instagram = getConfigValue($configData, ['contato', 'instagram'], '#');
$facebook = getConfigValue($configData, ['contato', 'facebook'], '#');
$email = getConfigValue($configData, ['contato', 'email'], '#');
$footerTexto = getConfigValue($configData, ['rodape', 'texto']);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../adminView/assets/images/logo.png" type="image/x-icon">
    <title>Cristais Gold Lar - Inicio</title>
    <link rel="stylesheet" href="css/index/index.css">
    <link rel="stylesheet" href="css/elements.css">
    <link rel="stylesheet" href="css/index/index-responsivo.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="css/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
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
                <li><a href="#inicio">Início</a></li>
                <li><a href="#sobre">Sobre</a></li>
                <li><a href="#produtos">Arranjos</a></li>
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
                        <img src="<?= $isLoggedIn ? htmlspecialchars($userPicture) : 'img/icons/perfil.png' ?>" alt="Entrar/Cadastrar-se" alt="Foto de Perfil" id="profile-pic">
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
                            <button class="google-login" onclick="location.href='login_site.php'">Cadastrar</button>
                            <button class="google-login" onclick="location.href='profile.php'">Configurações</button>
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
                <li><a href="#inicio">Início</a></li>
                <li><a href="#sobre">Sobre</a></li>
                <li><a href="#produtos">Arranjos</a></li>
                <li><a href="galeria.php">Galeria</a></li>
                <li><a href="blog.php">Blog</a></li>
                <li><a href="compras.php">Loja</a></li>
            </ul>
        </div>
    </header>

    <style>
        #profile-pic {
            border-radius: 50%;
            /* Faz a imagem ficar redonda */
            width: 40px;
            /* Ajuste o tamanho conforme necessário */
            height: 40px;
            /* Ajuste o tamanho conforme necessário */
            object-fit: cover;
            /* Garante que a imagem cubra todo o espaço */
        }
    </style>

    <section class="hero" id="inicio">
        <div class="hero-background">
            <div class="hero-circle one"></div>
            <div class="hero-circle two"></div>
        </div>
        <div class="hero-content">
            <h1>Cristais Gold Lar</h1>
            <p>Arranjos de plantas e aquários que transformam ambientes com vida, equilíbrio e sofisticação.</p>
            <div class="scroll-icon-box">
                <a href="#sobre" class="scroll-btn">
                    <i class="uil uil-mouse-alt"></i>
                    <p>Role para baixo</p>
                </a>
            </div>
        </div>
        <div class="social-icons">
            <a href="<?= htmlspecialchars($instagram) ?>"><img src="img/icons/insta.png" alt="Instagram"></a>
            <a href="<?= htmlspecialchars($facebook) ?>"><img src="img/icons/face.png" alt="Facebook"></a>
            <a href="mailto:<?= htmlspecialchars($email) ?>"><img src="img/icons/email.png" alt="E-mail"></a>
        </div>
        <a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>" class="whatsapp-btn">
            <img src="img/icons/whats.png" alt="WhatsApp">
        </a>
    </section>
    <div class="background-container">
        <section class="sobre-nos" id="sobre">
            <div class="texto">
                <h2>Sobre Nós</h2>
                <p>Na Cristais Gold Lar, acreditamos que a natureza é o toque essencial para um lar mais harmonioso e elegante. Criamos arranjos de plantas e aquários que unem beleza, equilíbrio e bem-estar, trazendo o encanto da vida natural para dentro de casa. Cada peça é cuidadosamente elaborada para refletir serenidade e sofisticação, tornando seu ambiente único e acolhedor.</p>
            </div>
            <div class="imagem">
                <?php
                if (!empty($sobreMidia)) {
                    // Se for um link externo (YouTube ou Vimeo)
                    if (strpos($sobreMidia, 'youtube.com') !== false) {
                        preg_match('/v=([^&]+)/', $sobreMidia, $matches);
                        $videoId = $matches[1] ?? '';
                        echo "<iframe width='100%' height='117%' border-radius='12px' src='https://www.youtube.com/embed/$videoId' frameborder='0' allowfullscreen></iframe>";
                    } elseif (strpos($sobreMidia, 'vimeo.com') !== false) {
                        preg_match('/vimeo.com\/(\d+)/', $sobreMidia, $matches);
                        $videoId = $matches[1] ?? '';
                        echo "<iframe src='https://player.vimeo.com/video/$videoId' width='100%' height='100%' frameborder='0' allowfullscreen></iframe>";
                    } else {
                        // Arquivo de mídia local
                        $caminhoMidia = '../adminView/uploads/inicio/' . basename($sobreMidia);
                        if (preg_match('/\.(mp4|webm|ogg)$/i', $sobreMidia)) {
                            echo "<video controls style='width: 100%; height: 100%; object-fit: cover; border-radius: 12px;'>
                    <source src='" . htmlspecialchars($caminhoMidia) . "' type='video/mp4'>
                    Seu navegador não suporta vídeos HTML5.
                </video>";
                        } else {
                            echo "<img src='" . htmlspecialchars($caminhoMidia) . "' alt='Sobre Nós' style='width: 100%; height: 100%; object-fit: cover; border-radius: 12px;'>";
                        }
                    }
                }
                ?>
            </div>
        </section>
        <section class="produtos" id="produtos">
            <h2>Nossos Produtos</h2>
            <p>Transforme seu ambiente com a essência da natureza</p>
            <br>
            <div class="container swiper">
                <div class="slide-container">
                    <div class="card-wrapper swiper-wrapper">
                        <?php
                        if (is_array($produtos) && !empty($produtos)) { // Verifique se $produtos é um array e não está vazio
                            foreach ($produtos as $produto) {
                                $avaliacao = rand(3, 5);
                                // Modificado para até 5 parcelas
                                $parcelas = min(5, ceil($produto['preco'] / 50));
                                $valor_parcela = $produto['preco'] / $parcelas;

                                echo "<div class='card swiper-slide'>";
                                echo "<div class='card-content'>";
                                echo "<div class='imagem'><img src='" . htmlspecialchars($produto['imagem_path'] ?? '') . "' alt='" . htmlspecialchars($produto['nome'] ?? '') . "'></div>";
                                echo "<p>" . htmlspecialchars($produto['nome'] ?? '') . "</p>";
                                echo "<p class='preco'>R$" . number_format($produto['preco'] ?? 0, 2, ',', '.') . "</p>";
                                echo "<p class='parcelamento'>R$" . number_format($valor_parcela, 2, ',', '.') . " em até $parcelas sem juros</p>";
                                echo "<button class='detalhes-btn' data-product-id='" . ($produto['id'] ?? 0) . "'>Ver Detalhes</button>";
                                echo "</div></div>";
                            }
                        } else {
                            echo "<p class='text-center text-red-500'>Nenhum produto encontrado.</p>";
                        }
                        ?>
                    </div>
                </div>
                <div class="swiper-button-next swiper-navBtn"></div>
                <div class="swiper-button-prev swiper-navBtn"></div>
            </div>
            <div class="btn-container">
                <button class="btn" id="redirect-btn"><a href="compras.php" style="text-decoration: none; color: inherit;">Conheça Mais Produtos</button>
            </div>

            <!-- Modal de Detalhes do Produto -->
            <div id="product-modal" class="modal">
                <div class="modal-content">
                    <span class="close-modal">&times;</span>
                    <div class="modal-body">
                        <div class="modal-image">
                            <img src="" alt="Imagem do Produto" id="modal-product-image">
                        </div>
                        <div class="modal-info">
                            <h3 id="modal-product-name"></h3>
                            <p class="modal-price" id="modal-product-price"></p>
                            <p class="modal-parcelas" id="modal-product-parcelas"></p>
                            <a href="compras.php" class="modal-shop-btn">Ir para a loja</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Seção Métodos de Pagamento -->
        <section class="pagamento" id="pagamento">
            <div class="container">
                <!-- Título e Texto -->
                <div class="texto">
                    <h2>Métodos de Pagamento</h2>
                    <p>
                        Aceitamos diversos métodos de pagamento para facilitar sua compra. Pague com cartões de crédito Visa,
                        MasterCard, Elo, American Express, e mais. Também oferecemos opções de pagamento via Pix,
                        garantindo mais flexibilidade e segurança para você.
                    </p>
                </div>
                <br>
                <!-- Bandeiras de Pagamento -->
                <div class="bandeiras-pagamento">
                    <img src="img/pagamento/visa.png" alt="Visa">
                    <img src="img/pagamento/master.png" alt="MasterCard">
                    <img src="img/pagamento/elo.png" alt="Elo">
                    <img src="img/pagamento/amex.png" alt="AmericaExpress">
                    <img src="img/pagamento/bradesco.png" alt="Bradesco">
                    <img src="img/pagamento/hyper.png" alt="HyperCard">
                    <img src="img/pagamento/pix.png" alt="Pix">
                </div>

                <!-- Ícones e Descrições -->
                <div class="info-section">
                    <div class="info-box">
                        <img src="img/transporte.png" alt="Entrega" class="icon">
                        <div class="info-text">
                            <p>Entregamos seus arranjos com todo o cuidado e rapidez. Trabalhamos com transportadoras confiáveis para
                                garantir que seu pedido chegue no prazo e em perfeitas condições.</p>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="info-box">
                        <img src="img/segurança.png" alt="Segurança" class="icon">
                        <div class="info-text">
                            <p>Suas compras são 100% seguras! Utilizamos protocolos avançados de segurança para proteger seus
                                dados e garantir uma experiência de compra tranquila e confiável.</p>
                        </div>
                    </div>
                </div>
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
                <ul class="list">
                    <li>
                        <h3>Termos de Segurança</h3>
                    </li>
                    <li><a href="../politica-de-privacidade.php" class="link">Política de Privacidade</a></li>
                    <li><a href="../termos-de-servico.php" class="link">Termos de Serviço</a></li>
                </ul>
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
    <script src="js/elementos/script.js"></script>
    <script src="js/index/index.js"></script>
    <script src="js/elementos/swiper-bundle.min.js"></script>
    <script src="js/elementos/element.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.isotope/3.0.6/isotope.pkgd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.imagesloaded/5.0.0/imagesloaded.pkgd.min.js"></script>
    <script type="text/javascript">
        const swiper = new Swiper('.swiper', {
            slidesPerView: 1,
            spaceBetween: 10,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            breakpoints: {
                320: {
                    slidesPerView: 1,
                    spaceBetween: 10,
                },
                375: {
                    slidesPerView: 1,
                    spaceBetween: 10,
                },
                425: {
                    slidesPerView: 1,
                    spaceBetween: 10,
                },
                520: {
                    slidesPerView: 2,
                },
                640: {
                    slidesPerView: 2,
                    spaceBetween: 20,
                },
                768: {
                    slidesPerView: 3,
                    spaceBetween: 30,
                },
                1024: {
                    slidesPerView: 3,
                    spaceBetween: 30,
                },
            },
        })

        document.getElementById('google-login-btn').addEventListener('click', function() {
            google.accounts.id.prompt();
        });

        function handleCredentialResponse(response) {
            console.log("Encoded JWT ID token: " + response.credential);

            fetch('google_login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'credential=' + encodeURIComponent(response.credential),
                })
                .then(response => {
                    if (response.ok) {
                        response.json().then(data => {
                            // Atualiza o nome do usuário e a foto de perfil
                            document.getElementById('username-display').textContent = data.name;
                            document.getElementById('profile-pic').src = data.picture;

                            // Remove os botões de login
                            const dropdownMenu = document.querySelector('.dropdown-menu');
                            dropdownMenu.innerHTML = '';

                            // Redireciona para a página inicial
                            window.location.href = "index.php";
                        });
                    } else {
                        console.error('Erro ao processar login do Google.');
                    }
                })
                .catch(error => {
                    console.error('Erro de rede:', error);
                });
        }
    </script>

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