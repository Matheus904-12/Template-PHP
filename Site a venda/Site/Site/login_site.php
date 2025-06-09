<?php
// login_site.php
session_start();
require_once '../adminView/config/dbconnect.php';
require_once '../vendor/autoload.php';

function verificarUsuario($conn, $email)
{
    $query = "SELECT id, name, password FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Adicione esta função para verificar o email
function verificarEmail($conn, $email)
{
    $query = "SELECT id FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Processar recuperação de senha
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'verificar_email') {
        // Verificar email
        $email = trim($_POST['email']);
        if (verificarEmail($conn, $email)) {
            $_SESSION['email_recuperacao'] = $email;
            $_SESSION['etapa_recuperacao'] = 'nova_senha';
        } else {
            $_SESSION['erro_recuperacao'] = "Email não encontrado!";
        }
        header("Location: login_site.php");
        exit();
    } elseif ($_POST['acao'] == 'redefinir_senha') {
        // Redefinir senha
        if (isset($_SESSION['email_recuperacao'])) {
            $email = $_SESSION['email_recuperacao'];
            $nova_senha = $_POST['nova_senha'];
            $confirmar_senha = $_POST['confirmar_senha'];

            if ($nova_senha !== $confirmar_senha) {
                $_SESSION['erro_redefinir'] = "As senhas não coincidem!";
            } else {
                // Atualizar senha no banco
                $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $query = "UPDATE usuarios SET password = ? WHERE email = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $senha_hash, $email);

                if ($stmt->execute()) {
                    $_SESSION['sucesso_redefinir'] = "Senha alterada com sucesso!";
                    $_SESSION['etapa_recuperacao'] = 'concluido';
                    unset($_SESSION['email_recuperacao']);
                } else {
                    $_SESSION['erro_redefinir'] = "Erro ao atualizar senha!";
                }
            }
        }
        header("Location: login_site.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['acao']) && $_POST['acao'] == 'cadastrar') {
        // Cadastro de usuário
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $senha = password_hash(trim($_POST['senha']), PASSWORD_BCRYPT);
        $endereco = $_POST['endereco'] ?? '';
        $cep = $_POST['cep'] ?? '';
        $numero_casa = $_POST['numero_casa'] ?? '';
        $telefone = $_POST['telefone'] ?? '';

        $query = "SELECT id FROM usuarios WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $registroErro = 'Email já cadastrado!';
        } else {
            $query = "INSERT INTO usuarios (name, email, password, endereco, cep, numero_casa, telefone) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssssss", $nome, $email, $senha, $endereco, $cep, $numero_casa, $telefone);

            if ($stmt->execute()) {
                $registroSucesso = 'Conta criada com sucesso! Faça login para continuar.';
            } else {
                $registroErro = 'Erro ao cadastrar usuário: ' . $conn->error;
            }
        }
    } elseif (isset($_POST['acao']) && $_POST['acao'] == 'logar') {
        // Login de usuário
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if (!empty($email) && !empty($password)) {
            $usuario = verificarUsuario($conn, $email);
            if (!$usuario) {
                $erro = "Usuário não encontrado!";
            } elseif (password_verify($password, $usuario['password'])) {
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['username'] = $usuario['name'];
                $_SESSION['logged_in'] = true;
                session_regenerate_id(true); // Regenera o session_id
                header("Location: index.php");
                exit();
            } else {
                $erro = "Senha incorreta!";
            }
        } else {
            $erro = "Preencha todos os campos!";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../adminView/assets/images/logo.png" type="image/x-icon">
    <title>Login - Cristais Gold Lar</title>
    <link rel="stylesheet" href="css/login/login.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Estilos adicionais */
        .password-container {
            position: relative;
            width: 100%;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #888;
            z-index: 10;
        }

        .input-wrap .toggle-password {
            right: 10px;
            top: 15px;
            transform: none;
        }

        .step {
            display: none;
        }

        .step.active {
            display: block;
        }

        .success-step {
            text-align: center;
            padding: 20px;
        }

        .success-icon {
            font-size: 60px;
            color: #4CAF50;
            margin-bottom: 15px;
        }

        .error-icon {
            font-size: 60px;
            color: #f44336;
            margin-bottom: 15px;
        }

        .success-message {
            color: #4CAF50;
            margin-bottom: 20px;
        }

        .error-message {
            color: #f44336;
            margin-bottom: 15px;
        }

        /* Preservar estilização original dos inputs */
        #signup .step input[type="text"],
        #signup .step input[type="email"],
        #signup .step input[type="password"] {
            width: 100%;
            margin-bottom: 15px;
            border: none;
            border-bottom: 1px solid #999;
            padding: 6px 0;
            font-family: inherit;
            font-size: inherit;
            outline: none;
        }
    </style>
</head>

<body>
    <main>
        <div class="box">
            <div class="inner-box">
                <div class="forms-wrap">
                    <form action="includes/login/processa_login.php" method="POST" class="sign-in-form" id="sigin">
                        <div class="logo">
                            <img src="img/logo.png" alt="Logo">
                            <h4>Cristais Gold Lar</h4>
                        </div>

                        <div class="heading">
                            <h2>Bem-vindo de volta</h2>
                            <h6>Não tem uma conta?</h6>
                            <a href="#" class="toggle">Cadastre-se</a>
                        </div>

                        <?php if (!empty($erro)): ?>
                            <p class="error-message"><?php echo $erro; ?></p>
                        <?php endif; ?>

                        <div class="actual-form">
                            <div class="input-wrap">
                                <input type="email" name="email" class="input-field" required>
                                <label>Email</label>
                            </div>

                            <div class="input-wrap password-container">
                                <input type="password" name="password" class="input-field" id="login-password" required>
                                <label>Senha</label>
                                <i class="toggle-password fas fa-eye-slash" id="toggle-login-password"></i>
                            </div>

                            <input type="hidden" name="acao" value="logar">
                            <input type="submit" value="Entrar" class="sign-btn">

                            <p class="text">
                                Esqueceu sua senha?
                                <a href="#" class="toggle-recovery">Recuperar acesso</a>
                            </p>
                            <br>
                            <button class="sign-btn" type="button" onclick="window.location.href = 'google_login.php';">
                                Continuar com Google
                            </button>
                        </div>
                    </form>

                    <!-- Updated sign-up form with three steps -->
                    <form method="POST" autocomplete="off" class="sign-up-form" id="signup">
                        <div class="heading2">
                            <h2>Crie sua Conta</h2>
                            <h6>Já possui uma conta?</h6>
                            <a href="#" class="toggle">Entre</a>
                        </div>

                        <?php if (!empty($registroErro)): ?>
                            <p class="error-message"><?php echo $registroErro; ?></p>
                        <?php endif; ?>

                        <?php if (!empty($registroSucesso)): ?>
                            <p class="success-message"><?php echo $registroSucesso; ?></p>
                        <?php endif; ?>

                        <!-- Passo 1: Informações básicas -->
                        <div class="step active" id="step1">
                            <div class="input-wrap">
                                <input type="text" name="nome" class="input-field" value="<?php echo $_POST['nome'] ?? ''; ?>" required>
                                <label>Nome</label>
                            </div>
                            <div class="input-wrap">
                                <input type="email" name="email" class="input-field" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                                <label>E-mail</label>
                            </div>
                            <div class="input-wrap">
                                <input type="password" name="senha" id="signup-password" class="input-field" required>
                                <label>Senha</label>
                                <i class="toggle-password fas fa-eye-slash" id="toggle-signup-password"></i>
                            </div>
                            <button type="button" class="next sign-btn" onclick="nextStep(1, 2)">Próximo</button>
                        </div>

                        <!-- Passo 2: Endereço e telefone -->
                        <div class="step" id="step2">
                            <div class="input-wrap">
                                <input type="text" name="endereco" class="input-field" value="<?php echo $_POST['endereco'] ?? ''; ?>">
                                <label>Endereço</label>
                            </div>
                            <div class="input-wrap">
                                <input type="text" name="cep" class="input-field" value="<?php echo $_POST['cep'] ?? ''; ?>">
                                <label>CEP</label>
                            </div>
                            <div class="input-wrap">
                                <input type="text" name="numero_casa" class="input-field" value="<?php echo $_POST['numero_casa'] ?? ''; ?>">
                                <label>Número</label>
                            </div>
                            <div class="input-wrap">
                                <input type="text" name="telefone" class="input-field" value="<?php echo $_POST['telefone'] ?? ''; ?>" required>
                                <label>Telefone (com DDD)</label>
                            </div>
                            <button type="button" class="prev sign-btn" onclick="prevStep(2, 1)">Voltar</button>
                            <button type="button" class="sign-btn" onclick="showSuccessStep()">Cadastrar</button>
                        </div>

                        <!-- Passo 3: Sucesso/Erro -->
                        <div class="step" id="step3">
                            <div class="success-step">
                                <i id="success-icon" class="fas fa-check-circle success-icon"></i>
                                <h3 id="success-message" class="success-message">Cadastro Concluído com Sucesso!</h3>
                                <p id="success-description">Sua conta foi criada e está pronta para uso.</p>
                                <button type="button" id="success-button" class="sign-btn" onclick="finalizarCadastro()">Finalizar</button>
                            </div>
                        </div>
                    </form>

                    <!-- Formulário de recuperação de senha -->
                    <form method="POST" class="sign-in-form recovery-form" id="recovery-form">
                        <div class="logo">
                            <img src="img/logo.png" alt="Logo">
                            <h4>Cristais Gold Lar</h4>
                        </div>

                        <?php if (isset($_SESSION['etapa_recuperacao']) && $_SESSION['etapa_recuperacao'] == 'nova_senha'): ?>
                            <!-- Etapa 2: Nova senha -->
                            <div class="heading">
                                <h2>Criar Nova Senha</h2>
                                <h6>Digite e confirme sua nova senha</h6>
                            </div>

                            <?php if (isset($_SESSION['erro_redefinir'])): ?>
                                <p class="error-message"><?= $_SESSION['erro_redefinir'];
                                                            unset($_SESSION['erro_redefinir']) ?></p>
                            <?php endif; ?>

                            <div class="actual-form">
                                <input type="hidden" name="acao" value="redefinir_senha">

                                <div class="input-wrap password-container">
                                    <input type="password" name="nova_senha" class="input-field" required minlength="6">
                                    <label>Nova Senha</label>
                                    <i class="toggle-password fas fa-eye-slash"></i>
                                </div>

                                <div class="input-wrap password-container">
                                    <input type="password" name="confirmar_senha" class="input-field" required minlength="6">
                                    <label>Confirmar Senha</label>
                                    <i class="toggle-password fas fa-eye-slash"></i>
                                </div>

                                <input type="submit" value="Redefinir Senha" class="sign-btn">

                                <div class="recovery-options">
                                    <a href="#" class="toggle-recovery">Voltar ao login</a>
                                </div>
                            </div>
                        <?php elseif (isset($_SESSION['etapa_recuperacao']) && $_SESSION['etapa_recuperacao'] == 'concluido'): ?>
                            <!-- Etapa 3: Concluído -->
                            <div class="heading">
                                <h2>Senha Alterada!</h2>
                            </div>

                            <div class="success-step">
                                <i class="fas fa-check-circle success-icon"></i>
                                <p class="success-message">Sua senha foi alterada com sucesso!</p>

                                <div class="recovery-options">
                                    <a href="#" class="toggle-recovery">Voltar ao login</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Etapa 1: Verificar email -->
                            <div class="heading">
                                <h2>Recuperar Senha</h2>
                                <h6>Digite seu email para continuar</h6>
                            </div>

                            <?php if (isset($_SESSION['erro_recuperacao'])): ?>
                                <p class="error-message"><?= $_SESSION['erro_recuperacao'];
                                                            unset($_SESSION['erro_recuperacao']) ?></p>
                            <?php endif; ?>

                            <div class="actual-form">
                                <input type="hidden" name="acao" value="verificar_email">

                                <div class="input-wrap">
                                    <input type="email" name="email" class="input-field" required>
                                    <label>Email</label>
                                </div>

                                <input type="submit" value="Continuar" class="sign-btn">

                                <div class="recovery-options">
                                    <a href="#" class="toggle-recovery">Voltar ao login</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>

                </div>

                <div class="carousel">
                    <div class="images-wrapper">
                        <img src="img/login/image1.png" class="image img-1 show" alt="">
                        <img src="img/login/image2.png" class="image img-2" alt="">
                        <img src="img/login/image3.png" class="image img-3" alt="">
                    </div>

                    <div class="text-slider">
                        <div class="text-wrap">
                            <div class="text-group">
                                <h2>Compre seu arranjo perfeito</h2>
                                <h2>Personalize como desejar</h2>
                                <h2>De mais vida ao seu ambiente</h2>
                            </div>
                        </div>

                        <div class="bullets">
                            <span class="active" data-value="1"></span>
                            <span data-value="2"></span>
                            <span data-value="3"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="js/login/login.js"></script>

    <script>
        // Função para alternar visibilidade da senha
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle para senha de login
            const toggleLoginPassword = document.getElementById('toggle-login-password');
            const loginPassword = document.getElementById('login-password');

            if (toggleLoginPassword) {
                toggleLoginPassword.addEventListener('click', function() {
                    const type = loginPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                    loginPassword.setAttribute('type', type);

                    // Alternar ícone
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }

            // Toggle para senha de cadastro
            const toggleSignupPassword = document.getElementById('toggle-signup-password');
            const signupPassword = document.getElementById('signup-password');

            if (toggleSignupPassword) {
                toggleSignupPassword.addEventListener('click', function() {
                    const type = signupPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                    signupPassword.setAttribute('type', type);

                    // Alternar ícone
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }
        });

        // Funções para navegação entre steps do cadastro
        function nextStep(current, next) {
            document.getElementById('step' + current).classList.remove('active');
            document.getElementById('step' + next).classList.add('active');
        }

        function prevStep(current, prev) {
            document.getElementById('step' + current).classList.remove('active');
            document.getElementById('step' + prev).classList.add('active');
        }

        function showSuccessStep() {
            // Enviar dados do formulário via AJAX antes de mostrar o passo 3
            const formData = new FormData(document.getElementById('signup'));

            fetch('includes/login/processa_cadastro.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    // Verificar se a resposta é válida
                    if (!response.ok) {
                        throw new Error('Erro na requisição: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("Resposta do servidor:", data);

                    // Mostrar o passo 3
                    document.getElementById('step2').classList.remove('active');
                    document.getElementById('step3').classList.add('active');

                    if (data.status === "success") {
                        // Cadastro bem-sucedido
                        document.getElementById('success-icon').className = 'fas fa-check-circle success-icon';
                        document.getElementById('success-message').innerHTML = 'Cadastro Concluído com Sucesso!';
                        document.getElementById('success-description').innerHTML = data.message || 'Sua conta foi criada e está pronta para uso.';
                        document.getElementById('success-button').innerHTML = 'Finalizar';
                        document.getElementById('success-button').style.display = 'block';
                        document.getElementById('success-button').onclick = finalizarCadastro;
                    } else {
                        // Cadastro falhou
                        document.getElementById('success-icon').className = 'fas fa-exclamation-circle error-icon';
                        document.getElementById('success-message').innerHTML = 'Erro no Cadastro';
                        document.getElementById('success-description').innerHTML = data.message || 'Erro no processamento do cadastro.';
                        document.getElementById('success-button').innerHTML = 'Voltar';
                        document.getElementById('success-button').onclick = function() {
                            document.getElementById('step3').classList.remove('active');
                            document.getElementById('step1').classList.add('active');
                        };
                    }
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
                    // Mesmo em caso de erro, mostrar o passo 3 com mensagem de erro
                    document.getElementById('step2').classList.remove('active');
                    document.getElementById('step3').classList.add('active');
                    document.getElementById('success-icon').className = 'fas fa-exclamation-circle error-icon';
                    document.getElementById('success-message').innerHTML = 'Erro na Comunicação';
                    document.getElementById('success-description').innerHTML = 'Não foi possível se comunicar com o servidor. Tente novamente mais tarde.';
                    document.getElementById('success-button').innerHTML = 'Voltar';
                    document.getElementById('success-button').onclick = function() {
                        document.getElementById('step3').classList.remove('active');
                        document.getElementById('step1').classList.add('active');
                    };
                });
        }

        function finalizarCadastro() {
            // Obter os dados do formulário
            const email = document.querySelector('#signup input[name="email"]').value;
            const senha = document.querySelector('#signup input[name="senha"]').value;

            // Criar formulário para login automático
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', senha);

            // Enviar requisição para login
            fetch('includes/login/processa_login.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    // Redirecionar para página principal independente da resposta
                    window.location.href = 'index.php';
                })
                .catch(error => {
                    console.error('Erro ao fazer login automático:', error);
                    // Mesmo em caso de erro, redirecionar para a página de login
                    window.location.href = 'index.php';
                });
        }

        window.onload = function() {
            try {
                google.accounts.id.initialize({
                    client_id: "818588658305-7hfcrmuocusbi88bpq0insq09srdv8jd.apps.googleusercontent.com",
                    callback: handleCredentialResponse,
                    auto_select: false
                });
                console.log("Google client inicializado com sucesso");
            } catch (e) {
                console.error("Erro ao inicializar cliente Google:", e);
            }
        }

        function handleCredentialResponse(response) {
            console.log("handleCredentialResponse chamada");

            if (!response || !response.credential) {
                console.error("Resposta inválida do Google");
                alert("Erro no login com Google. Tente novamente mais tarde.");
                return;
            }

            fetch('google_login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json'
                    },
                    body: 'credential=' + encodeURIComponent(response.credential)
                })
                .then(response => {
                    console.log("Status da resposta:", response.status);

                    if (!response.ok) {
                        if (response.status === 500) {
                            return response.text().then(text => {
                                console.error("Resposta de erro 500:", text);
                                throw new Error('Erro interno do servidor (500)');
                            });
                        }
                        throw new Error('Erro na resposta do servidor: ' + response.status);
                    }

                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            console.error('Resposta não é JSON:', text);
                            throw new Error('Formato de resposta inesperado');
                        });
                    }

                    return response.json();
                })
                .then(data => {
                    console.log("Dados do servidor:", data);

                    if (data.success) {
                        console.log("Login bem-sucedido:", data.message);
                        window.location.href = "index.php";
                    } else {
                        console.error("Erro retornado pelo servidor:", data.message);
                        alert("Erro no login: " + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro durante o processamento:', error);
                    alert("Ocorreu um erro ao processar o login. Por favor, tente novamente mais tarde.");
                })
                .finally(() => {});
        }

        function handleGoogleSignIn() {
            console.log("handleGoogleSignIn chamada");
            try {
                google.accounts.id.cancel();
                google.accounts.id.prompt((notification) => {
                    if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
                        console.log('O prompt não foi exibido:', notification.getNotDisplayedReason() || notification.getSkippedReason());
                    }
                });
            } catch (e) {
                console.error("Erro ao mostrar prompt do Google:", e);
            }
        }

        // Toggle para recuperação de senha
        document.querySelectorAll('.toggle-recovery').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector('main').classList.toggle('recovery-mode');
                // Resetar etapas ao voltar
                fetch('login_site.php?reset_recuperacao=1', {
                    method: 'GET'
                });
            });
        });

        // Resetar recuperação ao carregar a página
        if (window.location.search.includes('reset_recuperacao=1')) {
            <?php
            if (isset($_GET['reset_recuperacao'])) {
                unset($_SESSION['etapa_recuperacao']);
                unset($_SESSION['email_recuperacao']);
            }
            ?>
        }

        // Toggle para senha nos formulários de recuperação
        document.querySelectorAll('#recovery-form .toggle-password').forEach(btn => {
            btn.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });

        // Ativar modo recuperação se estiver em alguma etapa
        <?php if (isset($_SESSION['etapa_recuperacao'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelector('main').classList.add('recovery-mode');
            });
        <?php endif; ?>
    </script>

    <div id="g_id_onload"
        data-client_id="818588658305-7hfcrmuocusbi88bpq0insq09srdv8jd.apps.googleusercontent.com"
        data-context="signin"
        data-ux_mode="popup"
        data-callback="handleCredentialResponse"
        data-auto_prompt="false">
    </div>
</body>

</html>