<?php
// Iniciar sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/dbconnect.php';
require_once '../../controller/Produtos/ProductController.php';

$productController = new ProductController($conn);

$isEditing = false;
$produto = null;
$productId = null; // Inicializar $productId aqui

// Verificar se é uma edição
if (!empty($_GET['edit']) && is_numeric($_GET['edit'])) {
    $productId = (int)$_GET['edit'];
    $produto = $productController->getProductById($productId);
    $isEditing = !empty($produto);
}

// Definir categorias disponíveis
$categorias = ["Arranjos", "Buquês", "Flores Individuais", "Presentes", "Ocasiões Especiais", "Geral"];

// Processar envio do formulário
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($isEditing) {
        $result = $productController->updateProduct($productId, $_POST, $_FILES['imagem'] ?? null);
    } else {
        $result = $productController->createProduct($_POST, $_FILES['imagem'] ?? null); // Use createProduct for new products
    }

    $_SESSION['message'] = $result['message'] ?? "Erro desconhecido.";
    $_SESSION['message_type'] = ($result['success'] ?? false) ? "success" : "danger";

    if (!empty($result['success']) && $result['success']) {
        echo '<script>window.location.href = "../../visualizar_produtos.php";</script>'; // Redirecionar via JavaScript
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEditing ? 'Editar' : 'Adicionar' ?> Produto</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 text-gray-100">
    <div class="container mx-auto max-w-4xl py-8 px-4">
        <div class="bg-gray-800 rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-6 text-blue-400"><?= $isEditing ? 'Editar' : 'Adicionar' ?> Produto</h2>

            <?php if (!empty($_SESSION['message'])): ?>
                <div class="mb-6 rounded-md p-4 <?= $_SESSION['message_type'] === 'success' ? 'bg-green-800 text-green-200' : 'bg-red-800 text-red-200' ?>">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <?php if ($_SESSION['message_type'] === 'success'): ?>
                                <svg class="h-5 w-5 text-green-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            <?php else: ?>
                                <svg class="h-5 w-5 text-red-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm"><?= $_SESSION['message'] ?></p>
                        </div>
                    </div>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="nome" class="block text-sm font-medium text-gray-300 mb-2">Nome do Produto*</label>
                        <input type="text" class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            id="nome" name="nome" value="<?= $isEditing ? htmlspecialchars($produto['nome']) : '' ?>" required>
                    </div>
                    <div>
                        <label for="categoria" class="block text-sm font-medium text-gray-300 mb-2">Categoria</label>
                        <select class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            id="categoria" name="categoria">
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= htmlspecialchars($categoria) ?>" <?= $isEditing && isset($produto['categoria']) && $produto['categoria'] == $categoria ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($categoria) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="preco" class="block text-sm font-medium text-gray-300 mb-2">Preço (R$)*</label>
                        <input type="number" class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            id="preco" name="preco" step="0.01" min="0" value="<?= $isEditing ? htmlspecialchars($produto['preco']) : '' ?>" required>
                    </div>
                </div>

                <div class="mb-6">
                    <label for="descricao" class="block text-sm font-medium text-gray-300 mb-2">Descrição</label>
                    <textarea class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                        id="descricao" name="descricao" rows="4"><?= $isEditing ? htmlspecialchars($produto['descricao']) : '' ?></textarea>
                </div>

                <div class="mb-6">
                    <label for="imagem" class="block text-sm font-medium text-gray-300 mb-2">Imagem do Produto</label>
                    <?php if ($isEditing && !empty($produto['imagem'])): ?>
                        <div class="mb-4">
                            <p class="text-sm text-gray-400 mb-2">Imagem atual:</p>
                            <img src="../../uploads/produtos/<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" class="max-h-40 rounded-md border border-gray-600">
                        </div>
                    <?php endif; ?>

                    <input type="file" class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                        id="imagem" name="imagem" accept="image/*">
                    <p class="mt-2 text-sm text-gray-400">Formatos aceitos: JPG, PNG, GIF, WEBP. Tamanho máximo: 5MB.</p>

                    <div id="img-preview-container" class="hidden mt-4">
                        <p class="text-sm text-gray-400 mb-2">Nova imagem:</p>
                        <img id="img-preview" class="max-h-40 rounded-md border border-gray-600">
                    </div>
                </div>

                <div class="flex justify-end space-x-4">
                    <a href="../../visualizar_produtos.php" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-md transition duration-200">Voltar</a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                        <?= $isEditing ? 'Atualizar' : 'Adicionar' ?> Produto
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Exibir preview da imagem escolhida
        document.getElementById('imagem').addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                const previewContainer = document.getElementById('img-preview-container');
                const preview = document.getElementById('img-preview');

                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.classList.remove('hidden');
                }

                reader.readAsDataURL(file);
            }
        });
    </script>
</body>

</html>