<?php
// Incluir o arquivo de conexão
include('../config/dbconnect.php');

// Buscar as imagens no banco de dados
$stmt = $pdo->query("SELECT image_url, description, category FROM gallery");
$galeria = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Adicionar nova imagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['imagem'])) {
    $uploadDir = '../uploads/';
    $filename = basename($_FILES['imagem']['name']);
    $targetFile = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['imagem']['tmp_name'], $targetFile)) {
        // Inserir a nova imagem no banco de dados
        $descricao = $_POST['description'] ?? '';
        $categoria = $_POST['category'] ?? 'Outros';
        $sql = "INSERT INTO gallery (image_url, description, category) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$filename, $descricao, $categoria]);
    }
}

// Processar a remoção de imagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover'])) {
    $srcRemover = $_POST['remover'];
    $caminhoImagem = "../uploads/" . $srcRemover;

    // Remover do banco de dados
    $stmt = $pdo->prepare("DELETE FROM gallery WHERE image_url = ?");
    $stmt->execute([$srcRemover]);

    // Remover o arquivo físico
    if (file_exists($caminhoImagem)) {
        unlink($caminhoImagem);
    }

    // Redirecionar para a mesma página para atualizar a exibição
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Buscar as imagens no banco de dados (atualizadas)
$stmt = $pdo->query("SELECT image_url, description, category FROM gallery");
$galeria = $stmt->fetchAll(PDO::FETCH_ASSOC);

// No início do arquivo, adicione:
function findBackPath()
{
    $possible_paths = [
        'pages/index.php',
        '../pages/index.php',
        '../../pages/index.php',
        'index.php',
        '../index.php'
    ];

    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }

    // Fallback para o primeiro caminho se nenhum existir
    return $possible_paths[0];
}
?>

<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Galeria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            100: '#f3f4f6',
                            200: '#e5e7eb',
                            300: '#d1d5db',
                            400: '#9ca3af',
                            500: '#6b7280',
                            600: '#4b5563',
                            700: '#374151',
                            800: '#1f2937',
                            900: '#111827',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-950 text-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Gerenciar Galeria</h1>
            <p class="text-gray-400">Adicione, visualize ou remova imagens da galeria</p>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 relative z-0">
            <!-- Formulário de Upload -->
            <div class="lg:col-span-1">
                <div class="bg-gray-800 rounded-lg shadow-lg p-6 sticky top-4">
                    <h2 class="text-xl font-semibold mb-4 text-white">Adicionar Nova Imagem</h2>
                    <!-- Formulário com manipulação de eventos integrada -->
                    <form id="uploadForm" action="../pages/Configuracoes/processa_upload.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label for="imagem" class="block text-gray-300 mb-2 font-medium">Selecione uma imagem:</label>
                            <div class="border-2 border-dashed border-gray-600 rounded-lg p-4 text-center hover:border-blue-500 transition-colors cursor-pointer">
                                <input type="file" name="imagem" id="imagem" required class="hidden" onchange="mostrarPrevia(this)">
                                <label for="imagem" class="cursor-pointer w-full block">
                                    <div id="upload-icon" class="mx-auto text-gray-400 mb-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <span id="upload-text" class="text-sm text-gray-400">Clique para escolher ou arraste a imagem</span>
                                </label>
                            </div>
                        </div>

                        <!-- Exibir prévia da imagem -->
                        <div id="preview-container" class="mb-4 hidden">
                            <img id="preview" src="#" alt="Prévia da Imagem" class="w-full h-40 object-cover rounded mb-2">
                            <button type="button" onclick="removerPrevia()" class="text-sm text-red-400 hover:text-red-300">Remover imagem</button>
                        </div>

                        <div class="mb-6">
                            <label for="category" class="block text-gray-300 mb-2 font-medium">Categoria:</label>
                            <select name="category" id="category" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-white appearance-none">
                                <option value="buques">Buquês</option>
                                <option value="decoracao">Decoração</option>
                                <option value="presentes">Presentes</option>
                            </select>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-500/50 text-white font-medium py-3 px-4 rounded-lg transition-all">
                            Enviar Imagem
                        </button>

                        <!-- Botão Voltar -->
                        <div class="mt-6">
                            <a href="<?= file_exists('pages/index.php') ? 'index.php' : '../pages/index.php' ?>"
                                class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-medium py-3 px-4 rounded-lg flex items-center justify-center transition-all duration-300 shadow-lg hover:shadow-indigo-500/30">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Voltar ao Painel
                            </a>
                        </div>
                    </form>

                    <!-- Elemento para notificações -->
                    <div id="notification-container"></div>
                </div>
            </div>

            <!-- Galeria de Imagens -->
            <div class="lg:col-span-2">
                <h2 class="text-xl font-semibold mb-4 text-white">Imagens da Galeria</h2>

                <?php if (empty($galeria)): ?>
                    <div class="bg-gray-800 p-8 rounded-lg text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-gray-400">Nenhuma imagem encontrada na galeria</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                        <?php foreach ($galeria as $imagem): ?>
                            <div class="bg-gray-800 rounded-lg overflow-hidden shadow-lg transition-transform hover:scale-105">
                                <div class="relative h-48">
                                    <img src="../uploads/galeria/<?php echo htmlspecialchars($imagem['image_url']); ?>"
                                        class="w-full h-full object-cover"
                                        alt="<?php echo htmlspecialchars($imagem['description'] ?? 'Imagem da galeria'); ?>">
                                </div>
                                <div class="p-4">
                                    <?php if (!empty($imagem['description'])): ?>
                                        <p class="text-white font-medium mb-1"><?php echo htmlspecialchars($imagem['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="flex justify-between items-center">
                                        <span class="inline-block bg-gray-700 text-gray-300 text-xs px-2 py-1 rounded">
                                            <?php
                                            $categoryDisplay = [
                                                'buques' => 'Buquês',
                                                'decoracao' => 'Decoração',
                                                'presentes' => 'Presentes',
                                                'Outros' => 'Outros'
                                            ];
                                            echo htmlspecialchars($categoryDisplay[$imagem['category']] ?? $imagem['category']);
                                            ?>
                                        </span>
                                        <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" onsubmit="return confirm('Tem certeza que deseja remover esta imagem?');">
                                            <input type="hidden" name="remover" value="<?php echo htmlspecialchars($imagem['image_url']); ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-400 focus:outline-none">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script>
        function mostrarPrevia(input) {
            const preview = document.getElementById('preview');
            const previewContainer = document.getElementById('preview-container');
            const uploadIcon = document.getElementById('upload-icon');
            const uploadText = document.getElementById('upload-text');
            const file = input.files[0];
            const reader = new FileReader();

            reader.onloadend = function() {
                preview.src = reader.result;
                previewContainer.classList.remove('hidden');
                uploadIcon.classList.add('hidden');
                uploadText.textContent = 'Alterar imagem';
            }

            if (file) {
                reader.readAsDataURL(file);
            } else {
                removerPrevia();
            }
        }

        function removerPrevia() {
            const preview = document.getElementById('preview');
            const previewContainer = document.getElementById('preview-container');
            const uploadIcon = document.getElementById('upload-icon');
            const uploadText = document.getElementById('upload-text');
            const inputFile = document.getElementById('imagem');

            preview.src = '#';
            previewContainer.classList.add('hidden');
            uploadIcon.classList.remove('hidden');
            uploadText.textContent = 'Clique para escolher ou arraste a imagem';
            inputFile.value = '';
        }

        // Função para exibir notificações
        function showNotification(message, type = 'success') {
            // Criar o elemento da notificação
            const notification = document.createElement('div');

            // Aplicar estilos Tailwind para tema dark indigo
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg transform transition-all duration-500 ease-in-out z-50 ${
            type === 'success' 
                ? 'bg-indigo-900 text-white border-l-4 border-indigo-500' 
                : 'bg-red-900 text-white border-l-4 border-red-500'
        }`;

            // Adicionar conteúdo da notificação
            notification.innerHTML = `
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    ${type === 'success' 
                        ? '<svg class="h-5 w-5 text-indigo-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>'
                        : '<svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>'
                    }
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium">${message}</p>
                </div>
            </div>
        `;

            // Adicionar ao DOM
            document.body.appendChild(notification);

            // Remover após 3 segundos
            setTimeout(() => {
                notification.classList.add('opacity-0');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 500);
            }, 3000);
        }

        // Manipular o envio do formulário
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Impedir o comportamento padrão de redirecionamento

            const formData = new FormData(this);

            // Enviar os dados usando AJAX
            fetch(this.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    // Mostrar notificação de sucesso
                    showNotification('Imagem lançada com sucesso!', 'success');

                    // Opcional: limpar o formulário
                    // this.reset();
                    // removerPrevia();
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showNotification('Erro ao enviar a imagem.', 'error');
                });
        });
    </script>
</body>

</html>