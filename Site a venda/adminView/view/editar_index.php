<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

require_once '../controller/Configuracoes/ConfigController.php';

$jsonPath = '../config_site.json';
$controller = new ConfigController($jsonPath);
$config = $controller->getConfig();
$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Processar os dados do formulário e atualizar o JSON
    $data = [
        'pagina_inicial' => [
            'hero' => [
                'texto' => $_POST['hero_texto']
            ],
            'sobre' => [
                'texto' => $_POST['sobre_texto'],
                'midia' => isset($_FILES['midia_upload']) && $_FILES['midia_upload']['error'] === UPLOAD_ERR_OK ? basename($_FILES['midia_upload']['name']) : $_POST['midia_url']
            ],
            'produtos' => [
                'titulo' => $_POST['produtos_titulo'],
                'texto' => $_POST['produtos_texto']
            ]
        ],
        'contato' => [
            'whatsapp' => $_POST['whatsapp'],
            'instagram' => $_POST['instagram'],
            'facebook' => $_POST['facebook'],
            'email' => $_POST['email']
        ],
        'rodape' => [
            'texto' => $_POST['rodape_texto']
        ]
    ];

    $mensagem = $controller->salvarJson(json_encode($data));
}

$data = $controller->getConfig();
$sobreMidia = isset($data->pagina_inicial->sobre->midia) ? $data->pagina_inicial->sobre->midia : '';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Configurações</title>
    <link href="assets/css/styles.css" rel="stylesheet">
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
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Configurações do Site</h1>
            <p class="text-gray-400">Gerencie as configurações da sua loja</p>
        </header>

        <?php if (!empty($mensagem)): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo strpos($mensagem, 'sucesso') !== false ? 'bg-green-600/20 text-green-400 border border-green-700' : 'bg-red-600/20 text-red-400 border border-red-700'; ?>">
                <div class="flex items-center">
                    <?php if (strpos($mensagem, 'sucesso') !== false): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    <?php endif; ?>
                    <?php echo $mensagem; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-gray-800 rounded-lg shadow-lg p-6 mb-8">
            <form method="POST" action="Configuracoes/processa_configuracao.php" enctype="multipart/form-data" class="space-y-6">
                <!-- Seção Mídia -->
                <div class="border-b border-gray-700 pb-6">
                    <h2 class="text-xl font-semibold mb-4 text-white">Mídia</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="midia_upload" class="block text-gray-300 mb-2 font-medium">Upload de Mídia:</label>
                            <div class="border-2 border-dashed border-gray-600 rounded-lg p-4 text-center hover:border-blue-500 transition-colors cursor-pointer">
                                <input type="file" name="midia_upload" id="midia_upload" class="hidden" onchange="previewMedia(this)">
                                <label for="midia_upload" class="cursor-pointer w-full block">
                                    <div class="mx-auto text-gray-400 mb-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                        </svg>
                                    </div>
                                    <span class="text-sm text-gray-400">Clique para fazer upload ou arraste um arquivo</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label for="midia_url" class="block text-gray-300 mb-2 font-medium">URL da Mídia:</label>
                            <input type="text" name="midia_url" id="midia_url" value="<?= htmlspecialchars($sobreMidia) ?>"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-white"
                                oninput="previewMedia(this)"
                                placeholder="https://exemplo.com/imagem.jpg ou URL do YouTube/Vimeo">
                        </div>

                        <div class="mt-4">
                            <p class="text-gray-400 text-sm mb-2">Pré-visualização:</p>
                            <div id="media-preview" class="bg-gray-900 rounded-lg p-4 flex items-center justify-center min-h-[200px]">
                                <?php if (!empty($sobreMidia)) : ?>
                                    <?php if (strpos($sobreMidia, '.mp4') !== false || strpos($sobreMidia, '.webm') !== false || strpos($sobreMidia, '.ogg') !== false) : ?>
                                        <video controls class="max-w-full max-h-[200px]">
                                            <source src="<?= htmlspecialchars($sobreMidia) ?>" type="video/mp4">
                                            Seu navegador não suporta vídeos HTML5.
                                        </video>
                                    <?php else : ?>
                                        <img src="<?= htmlspecialchars($sobreMidia) ?>" alt="Preview" class="max-w-full max-h-[200px] object-contain">
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-gray-500 text-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <p>Nenhuma mídia selecionada</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div id="format-warning" class="text-red-500 text-sm mt-2"></div>
                        </div>
                    </div>
                </div>



                <!-- Seção Contato -->
                <div class="border-b border-gray-700 pb-6">
                    <h2 class="text-xl font-semibold mb-4 text-white">Informações de Contato</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="whatsapp" class="block text-gray-300 mb-2 font-medium">WhatsApp:</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                    </svg>
                                </div>
                                <input type="text" name="whatsapp" id="whatsapp"
                                    value="<?= htmlspecialchars($data->contato->whatsapp ?? '') ?>"
                                    class="w-full pl-10 px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-white"
                                    placeholder="Ex: +5511987654321">
                            </div>
                        </div>

                        <div>
                            <label for="instagram" class="block text-gray-300 mb-2 font-medium">Instagram:</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
                                    </svg>
                                </div>
                                <input type="text" name="instagram" id="instagram"
                                    value="<?= htmlspecialchars($data->contato->instagram ?? '') ?>"
                                    class="w-full pl-10 px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-white"
                                    placeholder="Ex: @sua_loja">
                            </div>
                        </div>

                        <div>
                            <label for="facebook" class="block text-gray-300 mb-2 font-medium">Facebook:</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                    </svg>
                                </div>
                                <input type="text" name="facebook" id="facebook"
                                    value="<?= htmlspecialchars($data->contato->facebook ?? '') ?>"
                                    class="w-full pl-10 px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-white"
                                    placeholder="Ex: https://facebook.com/sua_loja">
                            </div>
                        </div>

                        <div>
                            <label for="email" class="block text-gray-300 mb-2 font-medium">Email:</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                        <polyline points="22,6 12,13 2,6"></polyline>
                                    </svg>
                                </div>
                                <input type="email" name="email" id="email"
                                    value="<?= htmlspecialchars($data->contato->email ?? '') ?>"
                                    class="w-full pl-10 px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-white"
                                    placeholder="Ex: contato@sualoja.com">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex justify-between items-center">
                    <a href="../pages/index.php" class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-medium py-3 px-4 rounded-lg flex items-center justify-center transition-all duration-300 shadow-lg hover:shadow-indigo-500/30 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Voltar ao Painel
                    </a>
                    <button type="submit" id="submitButton" class="w-full bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 focus:ring-4 focus:ring-blue-500/50 text-white font-medium py-3 px-6 rounded-lg transition-all flex items-center justify-center">
                        <span id="buttonText">Salvar Alterações</span>
                        <span id="loadingSpinner" class="hidden ml-2">
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast-notification" class="hidden fixed top-4 right-4 z-50 max-w-xs transform transition-all duration-300 translate-x-full"></div>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();

            const submitButton = document.getElementById('submitButton');
            const buttonText = document.getElementById('buttonText');
            const loadingSpinner = document.getElementById('loadingSpinner');

            // Mostrar loading
            submitButton.disabled = true;
            buttonText.textContent = 'Salvando...';
            loadingSpinner.classList.remove('hidden');

            // Criar FormData
            const formData = new FormData(this);

            // Enviar via AJAX
            axios.post(this.action, formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                })
                .then(function(response) {
                    showToast('success', response.data);
                })
                .catch(function(error) {
                    showToast('error', error.response?.data || 'Erro ao salvar as configurações');
                })
                .finally(function() {
                    // Restaurar botão
                    submitButton.disabled = false;
                    buttonText.textContent = 'Salvar Alterações';
                    loadingSpinner.classList.add('hidden');
                });
        });

        function showToast(type, message) {
            const toast = document.getElementById('toast-notification');
            const icon = type === 'success' ?
                `<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>` :
                `<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>`;

            const bgColor = type === 'success' ? 'bg-green-800' : 'bg-red-800';

            toast.innerHTML = `
        <div class="${bgColor} text-white rounded-lg shadow-lg p-4 flex items-start">
            <div class="flex-shrink-0 mr-3">
                ${icon}
            </div>
            <div class="flex-1">
                <p class="text-sm font-medium">${message}</p>
            </div>
            <button onclick="this.parentElement.parentElement.classList.add('hidden')" class="ml-2 text-gray-300 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    `;

            toast.classList.remove('hidden', 'translate-x-full');
            toast.classList.add('translate-x-0');

            // Esconder após 5 segundos
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.classList.add('hidden'), 300);
            }, 5000);
        }
    </script>

    <style>
        #toast-notification {
            transition: transform 0.3s ease-in-out;
        }
    </style>

    <script>
        function previewMedia(input) {
            const preview = document.getElementById('media-preview');
            const warning = document.getElementById('format-warning');
            warning.textContent = '';

            // Clear current preview
            preview.innerHTML = '';

            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileType = file.type;

                if (fileType.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 90%; height: 150px; object-fit: contain;">`;
                    }
                    reader.readAsDataURL(file);
                } else if (fileType.startsWith('video/')) {
                    const url = URL.createObjectURL(file);
                    preview.innerHTML = `<video controls style="width: 90%; height: 150px;"><source src="${url}" type="${fileType}">Seu navegador não suporta vídeos HTML5.</video>`;
                } else {
                    preview.innerHTML = `<div style="text-align: center; color: gray;">
                <p>Formato não suportado</p>
            </div>`;
                    warning.textContent = "Formato de arquivo não suportado. Por favor, use JPG, PNG, GIF ou MP4.";
                }
            } else if (input.id === 'midia_url' && input.value) {
                const url = input.value;

                // Check if it's a YouTube URL
                if (url.includes('youtube.com') || url.includes('youtu.be')) {
                    let videoId = '';
                    if (url.includes('youtube.com/watch?v=')) {
                        videoId = url.split('v=')[1].split('&')[0];
                    } else if (url.includes('youtu.be/')) {
                        videoId = url.split('youtu.be/')[1].split('?')[0];
                    }

                    if (videoId) {
                        preview.innerHTML = `<iframe width="90%" height="150" src="https://www.youtube.com/embed/${videoId}" frameborder="0" allowfullscreen></iframe>`;
                        return;
                    }
                }

                // Check if it's a Vimeo URL
                if (url.includes('vimeo.com')) {
                    const videoId = url.split('vimeo.com/')[1].split('?')[0];
                    preview.innerHTML = `<iframe src="https://player.vimeo.com/video/${videoId}" width="90%" height="150" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>`;
                    return;
                }

                // Check file extension for images and videos
                const extension = url.split('.').pop().toLowerCase();
                const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                const videoExtensions = ['mp4', 'webm', 'ogg'];

                if (imageExtensions.includes(extension)) {
                    preview.innerHTML = `<img src="${url}" alt="Preview" style="width: 90%; height: 150px; object-fit: contain;" onerror="this.onerror=null;document.getElementById('format-warning').textContent='Erro ao carregar a imagem. Verifique se a URL é válida.';">`;
                } else if (videoExtensions.includes(extension)) {
                    preview.innerHTML = `<video controls style="width: 90%; height: 150px;"><source src="${url}" type="video/${extension}">Seu navegador não suporta vídeos HTML5.</video>`;
                } else {
                    warning.textContent = "Formato de URL não suportado.";
                }
            }
        }

        function validarJSON() {
            try {
                JSON.parse(document.getElementById('json_data').value);
                return true;
            } catch (e) {
                alert("JSON inválido: " + e.message);
                return false;
            }
        }

        function formatarJSON() {
            try {
                const json = JSON.parse(document.getElementById('json_data').value);
                document.getElementById('json_data').value = JSON.stringify(json, null, 4);
            } catch (e) {
                alert("JSON inválido: " + e.message);
            }
        }
    </script>
</body>

</html>