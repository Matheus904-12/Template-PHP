<?php
// Iniciar sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado como administrador
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../config/dbconnect.php';
require_once '../../controller/Produtos/ProductController.php';

$productController = new ProductController($conn);

// Verificar se o ID do produto foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "ID do produto não fornecido!";
    $_SESSION['message_type'] = "danger";
    header('Location: ../../visualizar_produtos.php');
    exit;
}

$productId = (int)$_GET['id'];
$produto = $productController->getProductById($productId);

// Verificar se o produto existe
if (!$produto) {
    $_SESSION['message'] = "Produto não encontrado!";
    $_SESSION['message_type'] = "danger";
    header('Location: ../../visualizar_produtos.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Produto</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-gray-900 text-gray-100">
    <div class="container mx-auto max-w-4xl py-8 px-4">
        <nav class="flex mb-6" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="../../pages/index.php" class="text-sm text-gray-400 hover:text-white">Painel</a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <a href="../../visualizar_produtos.php" class="ml-1 text-sm text-gray-400 hover:text-white md:ml-2">Produtos</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-sm font-medium text-gray-300 md:ml-2">Detalhes</span>
                    </div>
                </li>
            </ol>
        </nav>

        <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="p-6 border-b border-gray-700">
                <div class="flex justify-between items-center">
                    <h2 class="text-2xl font-bold text-blue-400">Detalhes do Produto</h2>
                    <div class="space-x-2">
                        <a href="../../pages/Compras/productForm.php?edit=<?= $produto['id'] ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-200 inline-flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Editar
                        </a>
                        <a href="../../visualizar_produtos.php" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-md transition duration-200 inline-flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
                            </svg>
                            Voltar
                        </a>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-1">
                        <div class="flex justify-center mb-4">
                            <?php if (!empty($produto['imagem'])): ?>
                                <img src="../../uploads/produtos/<?= $produto['imagem'] ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" class="rounded-md max-h-64 border border-gray-700">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <h2 class="text-2xl font-bold mb-4"><?= htmlspecialchars($produto['nome']) ?></h2>
                        <hr class="border-gray-700 mb-4">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <p class="mb-2"><span class="font-medium text-gray-300">ID:</span> #<?= $produto['id'] ?></p>
                                <p class="mb-2"><span class="font-medium text-gray-300">Categoria:</span> <?= htmlspecialchars($produto['categoria']) ?></p>
                                <p class="mb-2"><span class="font-medium text-gray-300">Preço:</span> R$ <?= number_format($produto['preco'], 2, ',', '.') ?></p>
                                <p class="mb-2">
                                    <span class="font-medium text-gray-300">Status:</span>
                                    <?php if (isset($produto['ativo']) && $produto['ativo'] == 1): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Ativo</span>
                                    <?php elseif (isset($produto['ativo'])): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Inativo</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">N/A</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div>
                                <p class="mb-2"><span class="font-medium text-gray-300">Data de Cadastro:</span> <?= isset($produto['data_cadastro']) ? date('d/m/Y', strtotime($produto['data_cadastro'])) : 'N/A' ?></p>
                            </div>

                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-gray-300 mb-2">Descrição:</h3>
                                <div class="bg-gray-700 p-4 rounded-md">
                                    <?= nl2br(htmlspecialchars($produto['descricao'])) ?>
                                </div>
                            </div>

                            <?php if (!empty($produto['especificacoes'])): ?>
                                <div class="mb-6">
                                    <h3 class="text-lg font-medium text-gray-300 mb-2">Especificações técnicas:</h3>
                                    <div class="bg-gray-700 p-4 rounded-md">
                                        <?= nl2br(htmlspecialchars($produto['especificacoes'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-8">
                            <h3 class="text-xl font-bold mb-4">Histórico de Vendas</h3>
                            <?php
                            $vendas = $productController->getProductSalesHistory($productId); // Correção aqui
                            if (!empty($vendas)):
                            ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-gray-700 rounded-md overflow-hidden">
                                        <thead class="bg-gray-800 text-gray-300">
                                            <tr>
                                                <th class="py-3 px-4 text-left">ID Venda</th>
                                                <th class="py-3 px-4 text-left">Data</th>
                                                <th class="py-3 px-4 text-left">Quantidade</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-600">
                                            <?php foreach ($vendas as $venda): ?>
                                                <tr class="hover:bg-gray-600">
                                                    <td class="py-3 px-4"><a href="../vendas/viewSale.php?id=<?= $venda['id'] ?>" class="text-blue-400 hover:underline">#<?= $venda['id'] ?></a></td>
                                                    <td class="py-3 px-4"><?= date('d/m/Y', strtotime($venda['data_venda'])) ?></td>
                                                    <td class="py-3 px-4"><?= $venda['quantidade'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="bg-blue-900 text-blue-200 p-4 rounded-md flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                    Nenhuma venda registrada para este produto.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="deleteProductModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3 text-center">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Confirmar Exclusão</h3>
                    <div class="mt-2 px-7 py-3">
                        <p class="text-sm text-gray-500">Você tem certeza que deseja excluir o produto <strong><?= htmlspecialchars($produto['nome']) ?></strong>?</p>
                        <p class="text-red-600"><i class="fas fa-exclamation-triangle"></i> Esta ação não pode ser desfeita.</p>
                    </div>
                    <div class="items-center px-4 py-3">
                        <button id="cancelBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400" onclick="document.getElementById('deleteProductModal').classList.add('hidden')">Cancelar</button>
                        <a href="deleteProduct.php?id=<?= $produto['id'] ?>&confirm=1" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-400 ml-2">Confirmar Exclusão</a>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="../assets/js/admin.js"></script>
</body>

</html>