<?php
require_once('../controller/Blog/BlogController.php');
$controller = new BlogController();

$posts = $controller->getPosts();

if (empty($posts)) {
    error_log("A variável \$posts está vazia.");
}
?>


<!DOCTYPE html>
<html lang="pt-br" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração do Blog - Cristais Gold Lar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="assets/css/styles.css" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdfa',
                            100: '#ccfbf1',
                            200: '#99f6e4',
                            300: '#5eead4',
                            400: '#2dd4bf',
                            500: '#14b8a6',
                            600: '#0d9488',
                            700: '#0f766e',
                            800: '#115e59',
                            900: '#134e4a',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-900 text-gray-200 min-h-screen">
    <!-- Campo oculto para armazenar o ID do post a ser excluído -->
    <input type="hidden" id="deletePostId" value="">

    <div class="container mx-auto py-8 px-4">

        <div id="message-container" class="mb-6 p-4 rounded-lg hidden"></div>

        <div class="grid grid-cols-1 gap-6">
            <div class="mb-6">
                <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                    <div class="bg-gray-700 px-6 py-4 border-b border-gray-600">
                        <h5 class="text-xl font-semibold">Adicionar Novo Post</h5>
                    </div>
                    <div class="p-6">
                        <form id="post-form" method="POST" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label for="titulo" class="block text-gray-300 mb-2">Título do Post</label>
                                <input type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    id="titulo" name="titulo" required>
                            </div>

                            <div class="mb-4">
                                <label for="resumo" class="block text-gray-300 mb-2">Resumo</label>
                                <textarea class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    id="resumo" name="resumo" rows="2" required></textarea>
                                <p class="text-gray-400 text-sm mt-1">Resumo breve que aparecerá na listagem de posts</p>
                            </div>

                            <div class="mb-4">
                                <label for="conteudo" class="block text-gray-300 mb-2">Conteúdo</label>
                                <textarea class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    id="conteudo" name="conteudo" rows="10" required></textarea>
                            </div>

                            <div class="mb-4">
                                <label for="autor" class="block text-gray-300 mb-2">Autor</label>
                                <input type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    id="autor" name="autor" required>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label for="imagem" class="block text-gray-300 mb-2 font-medium">Imagem:</label>
                                    <div class="border-2 border-dashed border-gray-600 rounded-lg p-4 text-center hover:border-blue-500 transition-colors cursor-pointer">
                                        <input type="file" name="imagem" id="imagem" class="hidden" accept="image/*" onchange="previewImagem(this)">
                                        <label for="imagem" class="cursor-pointer w-full block">
                                            <div class="mx-auto text-gray-400 mb-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                </svg>
                                            </div>
                                            <span class="text-sm text-gray-400">Clique para fazer upload ou arraste um arquivo</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <p class="text-gray-400 text-sm mb-2">Pré-visualização:</p>
                                    <div id="imagem-preview" class="bg-gray-900 rounded-lg p-4 flex items-center justify-center min-h-[200px]">
                                        <div class="text-gray-500 text-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <p>Nenhuma imagem selecionada</p>
                                        </div>
                                    </div>
                                    <div id="format-warning" class="text-red-500 text-sm mt-2"></div>
                                </div>
                            </div>

                            <div class="text-right">
                                <button type="button" onclick="submitForm('add_post')" class="inline-block px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-500 transition-colors">Adicionar Post</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div>
                <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                    <div class="bg-gray-700 px-6 py-4 border-b border-gray-600">
                        <h5 class="text-xl font-semibold">Posts Publicados</h5>
                    </div>
                    <div class="p-2 md:p-6">
                        <?php if (isset($posts) && is_array($posts) && count($posts) > 0): ?>
                            <div class="space-y-4">
                                <?php foreach ($posts as $post): ?>
                                    <div class="border-b border-gray-700 pb-4">
                                        <div class="flex flex-col md:flex-row items-start gap-4">
                                            <div class="w-full md:w-24 lg:w-32 flex-shrink-0">
                                                <?php if (!empty($post['imagem'])): ?>
                                                    <img src="../uploads/img-blog/<?php echo $post['imagem']; ?>" alt="<?php echo htmlspecialchars($post['titulo']); ?>"
                                                        class="w-full h-24 object-cover rounded-lg shadow-md">
                                                <?php else: ?>
                                                    <div class="bg-gray-700 text-center p-3 h-24 rounded-lg flex items-center justify-center">
                                                        <span class="text-gray-400">Sem imagem</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow">
                                                <h5 class="text-lg font-medium"><?php echo htmlspecialchars($post['titulo']); ?></h5>
                                                <p class="text-gray-400 text-sm mb-1">
                                                    Publicado em: <?php echo date('d/m/Y H:i', strtotime($post['data_publicacao'])); ?> |
                                                    Autor: <?php echo htmlspecialchars($post['autor']); ?>
                                                </p>
                                                <p class="text-gray-300">
                                                    <?php echo htmlspecialchars(substr($post['resumo'], 0, 150)) . (strlen($post['resumo']) > 150 ? '...' : ''); ?>
                                                </p>
                                            </div>
                                            <div class="flex flex-wrap gap-2 mt-3 md:mt-0">
                                                <a href="../../Site/blog.php?id=<?php echo $post['id']; ?>"
                                                    class="px-3 py-1 bg-blue-800 text-blue-100 rounded hover:bg-blue-700 transition-colors"
                                                    target="_blank">
                                                    Visualizar
                                                </a>
                                                <button type="button"
                                                    class="px-3 py-1 bg-primary-700 text-primary-100 rounded hover:bg-primary-600 transition-colors"
                                                    onclick="openEditModal(<?php echo $post['id']; ?>)">
                                                    Editar
                                                </button>
                                                <button type="button"
                                                    class="px-3 py-1 bg-red-800 text-red-100 rounded hover:bg-red-700 transition-colors"
                                                    onclick="confirmDelete(<?php echo $post['id']; ?>, '<?php echo addslashes($post['titulo']); ?>')">
                                                    Excluir
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center p-6 text-gray-400">Nenhum post encontrado. Comece adicionando um novo post!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Exclusão -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="border-b border-gray-700 px-6 py-4 flex items-center justify-between">
                <h5 class="text-xl font-semibold">Confirmar Exclusão</h5>
                <button type="button" class="text-gray-400 hover:text-white" onclick="closeModal()">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4" id="deleteModalBody">
                Tem certeza que deseja excluir este post?
            </div>
            <div class="px-6 py-4 bg-gray-700 rounded-b-lg flex justify-end space-x-2">
                <button type="button" class="px-4 py-2 bg-gray-600 text-gray-100 rounded hover:bg-gray-500 transition-colors" onclick="closeModal()">
                    Cancelar
                </button>
                <button type="button" onclick="deletePost()" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-500 transition-colors">
                    Excluir
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-3xl w-full mx-4">
            <div class="border-b border-gray-700 px-6 py-3 flex items-center justify-between">
                <h5 class="text-xl font-semibold">Editar Post</h5>
                <button type="button" class="text-gray-400 hover:text-white" onclick="closeEditModal()">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="px-6 py-3">
                <form id="edit-form" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="post_id" id="edit_post_id">
                    <input type="hidden" name="current_imagem" id="edit_current_imagem">

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Título e Autor lado a lado -->
                        <div class="mb-3">
                            <label for="edit_titulo" class="block text-gray-300">Título do Post</label>
                            <input type="text" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                id="edit_titulo" name="titulo" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_autor" class="block text-gray-300">Autor</label>
                            <input type="text" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                id="edit_autor" name="autor" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_resumo" class="block text-gray-300">Resumo</label>
                        <textarea class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            id="edit_resumo" name="resumo" rows="2" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="edit_conteudo" class="block text-gray-300">Conteúdo</label>
                        <textarea class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            id="edit_conteudo" name="conteudo" rows="4" required></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="mb-3">
                            <label for="edit_imagem" class="block text-gray-300">Imagem</label>
                            <input type="file" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                id="edit_imagem" name="imagem" accept="image/*">
                        </div>

                        <div class="mb-3">
                            <div id="current_image_preview">
                                <label class="block text-gray-300">Imagem Atual</label>
                                <img id="edit_image_preview" src="" alt="Imagem atual" class="max-h-20 rounded-lg shadow-lg">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="px-6 py-3 bg-gray-700 rounded-b-lg flex justify-end space-x-2">
                <button type="button" class="px-4 py-2 bg-gray-600 text-gray-100 rounded hover:bg-gray-500 transition-colors" onclick="closeEditModal()">
                    Cancelar
                </button>
                <button type="button" onclick="updatePost()" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-500 transition-colors">
                    Atualizar
                </button>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="border-b border-gray-700 px-6 py-4 flex items-center justify-between">
                <h5 class="text-xl font-semibold">Confirmar Exclusão</h5>
                <button type="button" class="text-gray-400 hover:text-white" onclick="closeModal()">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4" id="deleteModalBody">
                Tem certeza que deseja excluir este post?
            </div>
            <div class="px-6 py-4 bg-gray-700 rounded-b-lg flex justify-end space-x-2">
                <button type="button" class="px-4 py-2 bg-gray-600 text-gray-100 rounded hover:bg-gray-500 transition-colors" onclick="closeModal()">
                    Cancelar
                </button>
                <button type="button" onclick="deletePost()" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-500 transition-colors">
                    Excluir
                </button>
            </div>
        </div>
    </div>

    <!-- Notificação Toast -->
    <div id="toast-notification" class="hidden fixed top-4 right-4 z-50 max-w-xs">
        <div class="flex items-center p-4 mb-4 text-sm text-indigo-100 rounded-lg bg-indigo-900 shadow-lg border border-indigo-700">
            <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 text-indigo-200 bg-indigo-800 rounded-lg">
                <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                </svg>
                <span class="sr-only">Sucesso</span>
            </div>
            <div class="ml-3 text-sm font-medium">Post publicado com sucesso!</div>
            <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-indigo-800 text-indigo-300 hover:text-indigo-100 rounded-lg p-1.5 inline-flex h-8 w-8" onclick="closeNotification()">
                <span class="sr-only">Fechar</span>
                <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Script para controlar a notificação -->
    <script>
        function showNotification(message) {
            const toast = document.getElementById('toast-notification');
            if (message) {
                // Atualiza a mensagem se fornecida
                toast.querySelector('.text-sm.font-medium').textContent = message;
            }

            // Exibe a notificação
            toast.classList.remove('hidden');

            // Adiciona animação de entrada
            toast.classList.add('animate-fade-in-right');

            // Configura o fechamento automático após 5 segundos
            setTimeout(() => {
                closeNotification();
            }, 5000);
        }

        function closeNotification() {
            const toast = document.getElementById('toast-notification');

            // Adiciona animação de saída
            toast.classList.add('animate-fade-out');

            // Remove o elemento após a animação
            setTimeout(() => {
                toast.classList.add('hidden');
                toast.classList.remove('animate-fade-out', 'animate-fade-in-right');
            }, 300);
        }

        // Adiciona as animações necessárias ao CSS
        const style = document.createElement('style');
        style.textContent = `
    @keyframes fadeInRight {
        from {
            opacity: 0;
            transform: translateX(20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes fadeOut {
        from {
            opacity: 1;
        }
        to {
            opacity: 0;
        }
    }
    
    .animate-fade-in-right {
        animation: fadeInRight 0.3s ease-out;
    }
    
    .animate-fade-out {
        animation: fadeOut 0.3s ease-in;
    }
`;
        document.head.appendChild(style);

        // Função para ser chamada após o sucesso do envio do formulário
        function notifyPostSuccess(postTitle) {
            const message = postTitle ?
                `Post "${postTitle}" publicado com sucesso!` :
                "Post publicado com sucesso!";
            showNotification(message);
        }
    </script>

    <script>
        function submitForm(action, redirectUrls = []) {
            const form = document.getElementById('post-form');
            const formData = new FormData(form);
            formData.append('action', action);

            fetch('../pages/Blog/processa_blog.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Mostrar notificação de sucesso
                        notifyPostSuccess(formData.get('titulo'));

                        // Redirecionar para o primeiro URL após 2 segundos
                        setTimeout(() => {
                            window.location.href = redirectUrls[0] || '../pages/index.php';
                        }, 2000);

                        // Opção: Adicionar um botão na notificação para o segundo redirecionamento
                        showNotificationWithOptions(data.message, redirectUrls);
                    } else {
                        showNotification(data.message || "Erro ao processar o post");
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showNotification('Erro ao processar o post. Tente novamente.');
                });
        }

        // Função adicional para notificação com opções de redirecionamento
        function showNotificationWithOptions(message, urls) {
            const toast = document.getElementById('toast-notification');
            toast.innerHTML = `
        <div class="bg-green-800 text-white rounded-lg shadow-lg p-4">
            <div class="flex items-center justify-between">
                <p>${message}</p>
                <div class="flex space-x-2 ml-4">
                    ${urls.map((url, i) => `
                        <a href="${url}" class="px-3 py-1 bg-green-700 hover:bg-green-600 rounded text-sm">
                            ${i === 0 ? 'Painel' : 'Blog'}
                        </a>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
            toast.classList.remove('hidden');
        }

        // Variáveis para armazenar informações temporárias
        let currentPostId = null;
        let currentImageUrl = null;

        // Função para abrir o modal de edição
        function openEditModal(postId) {
            // Obter os dados do post
            fetch('../pages/Blog/processa_blog.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=get_post&post_id=${postId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const post = data.post;

                        // Preencher o formulário
                        document.getElementById('edit_post_id').value = post.id;
                        document.getElementById('edit_titulo').value = post.titulo;
                        document.getElementById('edit_resumo').value = post.resumo;
                        document.getElementById('edit_conteudo').value = post.conteudo;
                        document.getElementById('edit_autor').value = post.autor;
                        document.getElementById('edit_current_imagem').value = post.imagem;

                        // Mostrar a imagem atual se existir
                        if (post.imagem) {
                            document.getElementById('current_image_preview').classList.remove('hidden');
                            document.getElementById('edit_image_preview').src = '../uploads/img-blog/' + post.imagem;
                        } else {
                            document.getElementById('current_image_preview').classList.add('hidden');
                        }

                        // Exibir o modal
                        document.getElementById('editModal').classList.remove('hidden');
                    } else {
                        const messageContainer = document.getElementById('message-container');
                        messageContainer.textContent = data.message;
                        messageContainer.classList.remove('hidden');
                        messageContainer.classList.add('bg-red-800', 'text-red-100');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                });
        }

        // Função para fechar o modal de edição
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('edit-form').reset();
        }

        // Função para atualizar o post
        function updatePost() {
            const form = document.getElementById('edit-form');
            const formData = new FormData(form);
            formData.append('action', 'update_post');

            fetch('../pages/Blog/processa_blog.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    const messageContainer = document.getElementById('message-container');
                    messageContainer.textContent = data.message;
                    messageContainer.classList.remove('hidden');

                    if (data.status === 'success') {
                        messageContainer.classList.add('bg-green-800', 'text-green-100');
                        messageContainer.classList.remove('bg-red-800', 'text-red-100');
                        closeEditModal();
                        // Recarregar a página para mostrar as mudanças
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        messageContainer.classList.add('bg-red-800', 'text-red-100');
                        messageContainer.classList.remove('bg-green-800', 'text-green-100');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                });
        }

        // Função para fechar qualquer modal (você pode adaptá-la se tiver mais modais)
        function closeModal() {
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.classList.add('hidden');
            }
            // Se você tiver outros modais, adicione lógicas semelhantes aqui
            const editModal = document.getElementById('editModal');
            if (editModal) {
                editModal.classList.add('hidden');
            }
        }

        // Função para confirmar a exclusão, exibindo o modal de confirmação
        function confirmDelete(postId, postTitle) {
            currentPostId = postId;
            const deleteModal = document.getElementById('deleteModal');
            const deleteModalBody = document.getElementById('deleteModalBody');

            if (deleteModalBody) {
                deleteModalBody.textContent = `Tem certeza que deseja excluir o post "${postTitle}"?`;
            }
            if (deleteModal) {
                deleteModal.classList.remove('hidden');
            }
        }

        // Função para excluir o post após a confirmação
        function deletePost() {
            if (currentPostId !== null) {
                fetch('../pages/Blog/processa_blog.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_post&post_id=${currentPostId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        const messageContainer = document.getElementById('message-container');
                        if (messageContainer) {
                            messageContainer.textContent = data.message;
                            messageContainer.classList.remove('hidden');
                            messageContainer.classList.remove('bg-green-800', 'text-green-100', 'bg-red-800', 'text-red-100');
                            if (data.status === 'success') {
                                messageContainer.classList.add('bg-green-800', 'text-green-100');
                                // Remover o post da interface do usuário sem recarregar
                                const postElement = document.querySelector(`.border-b[data-post-id="${currentPostId}"]`);
                                if (postElement && postElement.parentNode) {
                                    postElement.parentNode.removeChild(postElement);
                                }
                            } else {
                                messageContainer.classList.add('bg-red-800', 'text-red-100');
                            }
                        } else {
                            showNotification(data.message || "Erro ao excluir o post");
                        }
                        closeModal(); // Fechar o modal após a tentativa de exclusão
                        currentPostId = null; // Resetar o ID
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        showNotification('Erro ao comunicar com o servidor para excluir o post.');
                        closeModal(); // Fechar o modal em caso de erro
                        currentPostId = null; // Resetar o ID
                    });
            }
        }

        function previewImagem(input) {
            const preview = document.getElementById('imagem-preview');
            const warning = document.getElementById('format-warning');

            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();

                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="max-w-full max-h-[200px] object-contain">`;
                    warning.textContent = ''; // Limpa o aviso
                };

                // Verifica o tipo de arquivo
                if (file.type.startsWith('image/')) {
                    reader.readAsDataURL(file);
                } else {
                    preview.innerHTML = `
                    <div class="text-gray-500 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p>Nenhuma imagem selecionada</p>
                    </div>
                `;
                    warning.textContent = 'Por favor, selecione um arquivo de imagem.';
                }
            } else {
                preview.innerHTML = `
                <div class="text-gray-500 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p>Nenhuma imagem selecionada</p>
                </div>
            `;
                warning.textContent = ''; // Limpa o aviso
            }
        }
    </script>
</body>

</html>