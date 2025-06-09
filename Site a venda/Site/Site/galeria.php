<?php
// Ativar exibição de erros no início do arquivo
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/galeria_errors.log');

// Verificar se a sessão já foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Caminho para o GaleriaController
$controllerPath = __DIR__ . '/../adminView/controller/Galeria/GaleriaController.php';

if (!file_exists($controllerPath)) {
    die("Erro crítico: Arquivo GaleriaController.php não encontrado em: " . $controllerPath);
}

require_once($controllerPath);

try {
    $galeriaController = new GaleriaController();
    $imagens = $galeriaController->exibirGaleria();
} catch (Exception $e) {
    error_log("Erro ao carregar galeria: " . $e->getMessage());
    $imagens = []; // Array vazio para continuar a execução
}

// Verifica se o usuário está logado
$isLoggedIn = isset($_SESSION['user_id']) && $_SESSION['logged_in'] === true;
$userName = '';
$userPicture = 'img/icons/perfil.png'; // Valor padrão

if ($isLoggedIn) {
    $userName = $_SESSION['username'] ?? '';
    if (strlen($userName) > 16) {
        $userName = substr($userName, 0, 16) . "...";
    }
    
    // Verifica a foto de perfil
    if (!empty($_SESSION['user_picture'])) {
        $userPicture = $_SESSION['user_picture'];
    } else {
        try {
            require_once __DIR__ . '/../adminView/config/dbconnect.php';
            $userId = $_SESSION['user_id'];
            $query = "SELECT profile_picture FROM usuarios WHERE id = ?";
            $stmt = $conn->prepare($query);
            
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    $userPicture = $row['profile_picture'] ?? $userPicture;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar foto de perfil: " . $e->getMessage());
        }
    }
}

// Carregar configurações do site
$siteConfigPath = __DIR__ . '/../adminView/config_site.json';
$configData = [];

if (file_exists($siteConfigPath)) {
    $jsonContent = file_get_contents($siteConfigPath);
    if ($jsonContent !== false) {
        $configData = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Erro ao decodificar config_site.json: " . json_last_error_msg());
        }
    }
}

// Função auxiliar para obter valores de configuração
function getConfigValue($config, $keys, $default = '') {
    $value = $config;
    foreach ($keys as $key) {
        if (!isset($value[$key])) {
            return $default;
        }
        $value = $value[$key];
    }
    return is_string($value) ? htmlspecialchars($value) : $value;
}

// Obter valores de configuração
$sobreMidia = getConfigValue($configData, ['pagina_inicial', 'sobre', 'midia'], '');
$whatsapp = getConfigValue($configData, ['contato', 'whatsapp'], '');
$instagram = getConfigValue($configData, ['contato', 'instagram'], '#');
$facebook = getConfigValue($configData, ['contato', 'facebook'], '#');
$email = getConfigValue($configData, ['contato', 'email'], '#');
$footerTexto = getConfigValue($configData, ['rodape', 'texto'], '');
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" href="../adminView/assets/images/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cristais Gold Lar - Galeria</title>
    <link rel="stylesheet" href="css/galeria/galeria.css">
    <link rel="stylesheet" href="css/elements.css">
    <link rel="stylesheet" href="css/index/index-responsivo.css">
    <link rel="stylesheet" href="css/galeria/galeria-responsivo.css">

    <!-- UNICONS -->
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>
    <div id="imageModal" class="modal">
        <span class="close">&times;</span>
        <img class="modal-content" id="modalImage">
        <button id="prev" class="nav-button">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M15 18l-6-6 6-6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
        <button id="next" class="nav-button">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 18l6-6-6-6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
    </div>

    <div id="imageModal" class="modal">
        <span class="close">&times;</span>
        <img class="modal-content" id="modalImage">
        <button id="prev" class="nav-button">&#10094;</button>
        <button id="next" class="nav-button">&#10095;</button>
    </div>

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

    <section class="hero" id="index">
        <div class="hero-background">
            <div class="hero-circle one"></div>
            <div class="hero-circle two"></div>
        </div>
        <div class="hero-content">
            <h1>Galeria de Encantos</h1>
            <p>Explore a delicadeza e o charme de nossos arranjos florais feitos com amor e dedicação.</p>
            <div class="scroll-icon-box">
                <a href="#back" class="scroll-btn">
                    <i class="uil uil-mouse-alt"></i>
                    <p>Clique Aqui</p>
                </a>
            </div>
        </div>



        <!-- Ícones Sociais -->
        <div class="social-icons">
            <a href="#"><img src="img/icons/insta.png" alt="Instagram"></a>
            <a href="#"><img src="img/icons/face.png" alt="Facebook"></a>
            <a href="#"><img src="img/icons/email.png" alt="E-mail"></a>
        </div>

        <!-- Botão WhatsApp -->
        <a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>" class="whatsapp-btn">
            <img src="img/icons/whats.png" alt="WhatsApp">
        </a>
    </section>

    <div class="background-container" id="back">
        <section class="portfolio">
            <main class="mainContainer">
                <div class="button-group" id="filter">
                    <button class="button active" data-filter="*">Todos</button>
                    <button class="button" data-filter=".buques">Buquês</button>
                    <button class="button" data-filter=".decoracao">Decoração</button>
                    <button class="button" data-filter=".presentes">Presentes</button>
                </div>

                <div class="gallery">
                    <?php foreach ($imagens as $imagem): ?>
                        <div class="item <?= htmlspecialchars($imagem['category']); ?>">
                            <?php $imagePath = "../adminView/uploads/galeria/" . htmlspecialchars($imagem['image_url']); ?>
                            <img src="<?= $imagePath ?>" alt="Imagem da Galeria" class="gallery-image">
                            <div class="overlay">
                                <a href="" class="open-modal" data-image="<?= $imagePath ?>">Clique para ver</a>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>

                <!--  *****  Gallery Section Ends  *****  -->

            </main>

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
    </div>

    <script src="js/elementos/element.js"></script>
    <!--   *****   JQuery Link   *****   -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>

    <!--   *****   Isotope Filter Link   *****  -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.isotope/3.0.6/isotope.pkgd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.imagesloaded/5.0.0/imagesloaded.pkgd.min.js"></script>
    <script type="text/javascript">
        var $galleryContainer = $('.gallery');

        // Aguarda todas as imagens serem carregadas antes de inicializar o Isotope
        $galleryContainer.imagesLoaded(function() {
            $galleryContainer.isotope({
                itemSelector: '.item',
                layoutMode: 'fitRows'
            });
        });

        $('.button-group .button').on('click', function() {
            $('.button-group .button').removeClass('active');
            $(this).addClass('active');

            var value = $(this).attr('data-filter');
            $galleryContainer.isotope({
                filter: value
            });
        });

        document.addEventListener("DOMContentLoaded", function() {
            // Elementos do modal
            var modal = document.getElementById("imageModal");
            var modalImg = document.getElementById("modalImage");
            var closeBtn = document.getElementsByClassName("close")[0];
            var prevBtn = document.getElementById("prev");
            var nextBtn = document.getElementById("next");

            // Array para armazenar as imagens
            var images = [];
            var currentIndex = 0;

            // Coleta todos os links da galeria
            var links = document.querySelectorAll(".open-modal");

            // Armazena os caminhos das imagens no array
            links.forEach(function(link, index) {
                var imgPath = link.getAttribute("data-image");
                images.push(imgPath);

                // Adiciona evento de clique para abrir o modal
                link.onclick = function(e) {
                    e.preventDefault();
                    currentIndex = index;
                    openModal(imgPath);
                }
            });

            // Função para abrir o modal
            function openModal(imgPath) {
                modal.style.display = "block";
                modalImg.src = imgPath;
            }

            // Evento para fechar o modal
            closeBtn.onclick = function() {
                modal.style.display = "none";
            }

            // Navegação para a próxima imagem
            nextBtn.onclick = function() {
                currentIndex = (currentIndex + 1) % images.length;
                modalImg.src = images[currentIndex];
            }

            // Navegação para a imagem anterior
            prevBtn.onclick = function() {
                currentIndex = (currentIndex - 1 + images.length) % images.length;
                modalImg.src = images[currentIndex];
            }

            // Fechar o modal ao clicar fora da imagem
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }

            // Navegação por teclado
            document.onkeydown = function(event) {
                if (modal.style.display === "block") {
                    if (event.key === "ArrowRight") {
                        nextBtn.click();
                    } else if (event.key === "ArrowLeft") {
                        prevBtn.click();
                    } else if (event.key === "Escape") {
                        modal.style.display = "none";
                    }
                }
            }
        });

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