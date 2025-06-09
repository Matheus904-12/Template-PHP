<?php
session_start();
include '../adminView/config/dbconnect.php';
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch blog posts
$sql = "SELECT id, titulo, resumo, imagem, data_publicacao FROM blog_posts ORDER BY data_publicacao DESC";
$result = $conn->query($sql);

// Check if a specific post is requested
$post_id = isset($_GET['post_id']) ? $_GET['post_id'] : null;
$single_post = null;

if ($post_id) {
    $stmt = $conn->prepare("SELECT id, titulo, conteudo, imagem, data_publicacao, autor FROM blog_posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $single_result = $stmt->get_result();
    if ($single_result->num_rows > 0) {
        $single_post = $single_result->fetch_assoc();
    }
    $stmt->close();
}

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
        // Se não estiver na sessão, você pode buscar no banco de dados aqui
        // Exemplo (adapte com sua lógica de banco de dados):
        require_once '../adminView/config/dbconnect.php'; // Inclua seu arquivo de conexão
        $userId = $_SESSION['user_id'];
        $query = "SELECT profile_picture FROM usuarios WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            var_dump($row);
            $userPicture = $row['profile_picture'];
        } else {
            $userPicture = 'img/icons/perfil.png'; // Imagem padrão se não encontrada
        }
    }
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
    <title>Cristais Gold Lar - <?php echo $post_id ? 'Post: ' . $single_post['titulo'] : 'Blog'; ?></title>
    <link rel="stylesheet" href="css/blog/blog.css">
    <link rel="stylesheet" href="css/elements.css">
    <link rel="stylesheet" href="css/index/index-responsivo.css">
    <link rel="stylesheet" href="css/blog/blog-responsivo.css">
    <!-- UNICONS -->
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
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
                <li><a href="index.php">Início</a></li>
                <li><a href="index.php">Sobre</a></li>
                <li><a href="index.php">Arranjos</a></li>
                <li><a href="galeria.php">Galeria</a></li>
                <li><a href="blog.php">Blog</a></li>
                <li><a href="compras.php">Loja</a></li>
            </ul>
        </div>
    </header>


    <section class="blog-header">
        <div class="hero-background">
            <div class="hero-circle one"></div>
            <div class="hero-circle two"></div>
        </div>
        <h1>Blog Cristais Gold Lar</h1>
        <p>Confira nossas dicas e novidades sobre decoração e arranjos de cristais</p>

        <a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>" class="whatsapp-btn">
            <img src="img/icons/whats.png" alt="WhatsApp">
        </a>
    </section>
    <div class="background-container">
        <div class="blog-container">
            <?php if ($post_id && $single_post): ?>
                <!-- Single Post View -->
                <div class="single-post">
                    <div class="post-header">
                        <h1><?php echo htmlspecialchars($single_post['titulo']); ?></h1>
                        <div class="post-meta">
                            <span class="post-date"><i class="fa-regular fa-calendar"></i> <?php echo date('d/m/Y', strtotime($single_post['data_publicacao'])); ?></span>
                            <span class="post-time"><i class="fa-regular fa-clock"></i> <?php echo date('H:i', strtotime($single_post['data_publicacao'])); ?></span>
                            <span class="post-author"><i class="fa-regular fa-user"></i> <?php echo htmlspecialchars($single_post['autor']); ?></span>
                        </div>
                    </div>

                    <div class="post-image">
                        <img src="../adminView/uploads/img-blog/<?php echo htmlspecialchars($single_post['imagem']); ?>" alt="<?php echo htmlspecialchars($single_post['titulo']); ?>">
                    </div>

                    <div class="post-content">
                        <?php echo $single_post['conteudo']; ?>
                    </div>

                    <div class="post-rating" data-post-id="<?php echo $post_id; ?>">
                        <h3>Avalie este post:</h3>
                        <div class="rating">
                            <i class="fa-solid fa-star" data-rating="1"></i>
                            <i class="fa-solid fa-star" data-rating="2"></i>
                            <i class="fa-solid fa-star" data-rating="3"></i>
                            <i class="fa-solid fa-star" data-rating="4"></i>
                            <i class="fa-solid fa-star" data-rating="5"></i>
                        </div>
                    </div>

                    <div class="post-comments">
                        <h3>Comentários</h3>

                        <div class="comments-list">
                            <?php
                            // Fetch comments for this post
                            $stmt = $conn->prepare("SELECT nome, comentario, data_comentario FROM blog_comentarios WHERE post_id = ? ORDER BY data_comentario DESC");
                            $stmt->bind_param("i", $post_id);
                            $stmt->execute();
                            $comments_result = $stmt->get_result();

                            if ($comments_result->num_rows > 0) {
                                while ($comment = $comments_result->fetch_assoc()) {
                            ?>
                                    <div class="comment">
                                        <div class="comment-header">
                                            <span class="comment-author"><?php echo htmlspecialchars($comment['nome']); ?></span>
                                            <span class="comment-date"><?php echo date('d/m/Y H:i', strtotime($comment['data_comentario'])); ?></span>
                                        </div>
                                        <div class="comment-content">
                                            <?php echo htmlspecialchars($comment['comentario']); ?>
                                        </div>
                                    </div>
                            <?php
                                }
                            } else {
                                echo '<p>Nenhum comentário ainda. Seja o primeiro a comentar!</p>';
                            }
                            $stmt->close();
                            ?>
                        </div>

                        <div class="comment-form">
                            <h4>Deixe seu comentário</h4>
                            <form id="comment-form" action="../adminView/pages/Blog/processa_comentario.php" method="post">
                                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                                <div class="form-group">
                                    <label for="nome">Nome:</label>
                                    <input type="text" id="nome" name="nome" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">E-mail:</label>
                                    <input type="email" id="email" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label for="comentario">Comentário:</label>
                                    <textarea id="comentario" name="comentario" rows="5" required></textarea>
                                </div>
                                <button type="submit" class="comment-btn">Enviar Comentário</button>
                            </form>
                        </div>
                    </div>

                    <div class="post-navigation">
                        <a href="blog.php" class="back-to-blog">← Voltar para o Blog</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Blog Posts List View -->


                <div class="blog-posts">
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                    ?>
                            <div class="blog-post">
                                <div class="post-img">
                                    <img src="../adminView/uploads/img-blog/<?php echo htmlspecialchars($row['imagem']); ?>" alt="<?php echo htmlspecialchars($row['titulo']); ?>">
                                </div>
                                <div class="post-info">
                                    <h2><?php echo htmlspecialchars($row['titulo']); ?></h2>
                                    <p class="post-date"><i class="fa-regular fa-calendar"></i> <?php echo date('d/m/Y', strtotime($row['data_publicacao'])); ?></p>
                                    <p class="post-excerpt"><?php echo htmlspecialchars($row['resumo']); ?></p>
                                    <a href="blog.php?post_id=<?php echo $row['id']; ?>" class="read-more-btn">Leia mais</a>
                                </div>
                            </div>
                    <?php
                        }
                    } else {
                        echo "<p class='no-posts'>Nenhum post disponível no momento.</p>";
                    }
                    ?>
                </div>
            <?php endif; ?>
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

    <script src="js/elementos/element.js"></script>
    <script>
        // Rating functionality
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.rating .fa-star');
            const postRatingDiv = document.querySelector('.post-rating'); // Seleciona o elemento pai

            if (stars && postRatingDiv) {
                const postId = postRatingDiv.dataset.postId; // Obtém o ID do post

                stars.forEach(star => {
                    star.addEventListener('click', function() {
                        const rating = this.getAttribute('data-rating');

                        // Reset all stars
                        stars.forEach(s => {
                            s.classList.remove('active');
                        });

                        // Highlight stars up to the selected one
                        stars.forEach(s => {
                            if (s.getAttribute('data-rating') <= rating) {
                                s.classList.add('active');
                            }
                        });

                        // Send rating to server via AJAX
                        fetch('../adminView/pages/Blog/processa_avaliacao.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({
                                    post_id: postId,
                                    rating: rating
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert(data.message);
                                    // Atualize a exibição da avaliação média, se necessário
                                    if (data.avg_rating) {
                                        console.log("Avaliação média: " + data.avg_rating);
                                    }
                                } else {
                                    alert(data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Erro:', error);
                                alert('Ocorreu um erro ao processar a avaliação.');
                            });
                    });
                });
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
<?php $conn->close(); ?>