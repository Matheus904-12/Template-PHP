<?php
// Iniciar sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado como administrador
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once 'config/dbconnect.php';
require_once 'controller/Produtos/ProductController.php';

$productController = new ProductController($conn);

// Processar ação de exclusão
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $productId = (int)$_GET['delete'];

    $result = $productController->deleteProduct($productId);

    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? "success" : "danger";

    // Redirecionar para evitar reenvio do formulário
    header('Location: visualizar_produtos.php');
    exit;
}



// Obter lista de produtos
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';
$orderBy = isset($_GET['orderBy']) ? $_GET['orderBy'] : '';

if (!empty($searchTerm)) {
    $produtos = $productController->searchProducts($searchTerm);
} elseif (!empty($categoria)) {
    $produtos = $productController->getProductsByCategory($categoria);
} elseif (!empty($orderBy)) {
    $produtos = $productController->getProductsOrderedBy($orderBy);
} else {
    $produtos = $productController->getAllProducts();
}

// Obter categorias disponíveis
$categorias = ["Arranjos", "Buquês", "Flores Individuais", "Presentes", "Ocasiões Especiais", "Geral"];
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Produtos - Painel Administrativo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'black-indigo': '#1e293b',
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-black-indigo text-white">
    <div class="main-content p-4">
        <div class="container mx-auto mt-4">
            <nav class="flex mb-6" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="pages/index.php" class="text-sm text-gray-400 hover:text-white">Painel</a>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-1 text-sm font-medium text-gray-300 md:ml-2">Catálogo</span>
                        </div>
                    </li>
                </ol>
            </nav>

            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-semibold">Gerenciar Produtos</h2>
                <div class="flex space-x-2">
                    <a href="pages/Compras/productForm.php" class="bg-blue-500 hover:bg-blue-600 transition duration-300 text-white font-semibold py-2 px-4 rounded flex items-center">
                        <i class="fas fa-plus mr-2"></i> Adicionar Produto
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="bg-<?= $_SESSION['message_type'] == 'success' ? 'green' : ($_SESSION['message_type'] == 'warning' ? 'yellow' : 'red') ?>-200 text-<?= $_SESSION['message_type'] == 'success' ? 'green' : ($_SESSION['message_type'] == 'warning' ? 'yellow' : 'red') ?>-800 border border-<?= $_SESSION['message_type'] == 'success' ? 'green' : ($_SESSION['message_type'] == 'warning' ? 'yellow' : 'red') ?>-400 rounded p-3 mb-4" role="alert">
                    <?= $_SESSION['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
                ?>
            <?php endif; ?>

            <div class="bg-gray-800 rounded shadow p-4">
                <div class="mb-4">
                    <div class="flex flex-wrap">
                        <div class="w-full md:w-1/2 mb-2 md:mb-0">
                            <form method="GET" action="">
                                <div class="flex">
                                    <input type="text" class="bg-gray-700 text-white border border-gray-600 rounded-l p-2 w-full" placeholder="Buscar produtos..." name="search" value="<?= htmlspecialchars($searchTerm) ?>">
                                    <button class="bg-gray-700 hover:bg-gray-600 text-white p-2 rounded-r" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="w-full md:w-1/4 mb-2 md:mb-0 md:pl-2">
                            <select class="bg-gray-700 text-white border border-gray-600 rounded p-2 w-full" id="filtroCategoria" onchange="filtrarPorCategoria(this.value)">
                                <option value="">Todas as categorias</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat ?>" <?= $categoria == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-full md:w-1/4 md:pl-2">
                            <select class="bg-gray-700 text-white border border-gray-600 rounded p-2 w-full" id="ordenacao" onchange="ordenarPor(this.value)">
                                <option value="">Ordenar por</option>
                                <option value="destaque" <?= $orderBy == 'destaque' ? 'selected' : '' ?>>Em Destaque</option>
                                <option value="vendidos" <?= $orderBy == 'vendidos' ? 'selected' : '' ?>>Mais Vendidos</option>
                                <option value="promocao" <?= $orderBy == 'promocao' ? 'selected' : '' ?>>Em Promoção</option>
                                <option value="baratos" <?= $orderBy == 'baratos' ? 'selected' : '' ?>>Menor Preço</option>
                                <option value="caros" <?= $orderBy == 'caros' ? 'selected' : '' ?>>Maior Preço</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Imagem</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Nome</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Categoria</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Preço</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Cadastro</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php if (empty($produtos)): ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-4 whitespace-nowrap text-center">Nenhum produto encontrado</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($produtos as $produto): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= $produto['id'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if (!empty($produto['imagem'])): ?>
                                                <?php
                                                // Define o caminho base para a pasta de uploads de produtos
                                                $caminho_imagem = 'uploads/produtos/' . $produto['imagem'];
                                                ?>
                                                <img src="<?= $caminho_imagem ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" class="img-thumbnail" style="max-height: 80px;">
                                            <?php else: ?>
                                                <span class="text-gray-500">Sem imagem</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($produto['nome']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($produto['categoria']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">R$ <?= number_format($produto['preco'], 2, ',', '.') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= date('d/m/Y', strtotime($produto['data_cadastro'])) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="pages/Compras/productForm.php?edit=<?= $produto['id'] ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mr-2">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded mr-2" onclick="confirmarExclusao(<?= $produto['id'] ?>, '<?= addslashes($produto['nome']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <a href="pages/Compras/productDetails.php?id=<?= $produto['id'] ?>" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmação de exclusão -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-gray-800 text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Tem certeza que deseja excluir o produto <span id="productName"></span>?
                </div>
                <div class="modal-footer">
                    <button type="button" class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" id="confirmDelete" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">Excluir</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de upload de CSV -->
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-gray-800 text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadModalLabel">Importar Produtos via CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="file" name="csv_upload" accept=".csv" class="form-control bg-gray-700 text-white" required>
                        <small class="text-gray-400 mt-2 block">
                            O arquivo CSV deve ter o formato: Nome, Categoria, Preço, Quantidade, Descrição, Imagem
                        </small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">Importar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Adicione este código no final do arquivo, substituindo as funções anteriores

        document.addEventListener('DOMContentLoaded', function() {
            // Configurar evento para o formulário de pesquisa
            const searchForm = document.querySelector('form[action=""]');
            if (searchForm) {
                searchForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const searchTerm = this.querySelector('input[name="search"]').value;
                    carregarProdutos({
                        search: searchTerm
                    });
                });
            }

            // Inicializar seletores
            const filtroCategoria = document.getElementById('filtroCategoria');
            const ordenacao = document.getElementById('ordenacao');

            // Adicionar listeners
            if (filtroCategoria) {
                filtroCategoria.addEventListener('change', function() {
                    carregarProdutos({
                        categoria: this.value
                    });
                    // Resetar outros filtros
                    if (ordenacao) ordenacao.value = '';
                });
            }

            if (ordenacao) {
                ordenacao.addEventListener('change', function() {
                    carregarProdutos({
                        orderBy: this.value
                    });
                    // Resetar outros filtros
                    if (filtroCategoria) filtroCategoria.value = '';
                });
            }
        });

        // Função para carregar produtos via AJAX
        function carregarProdutos(params) {
            // Mostrar indicador de carregamento
            const tabela = document.querySelector('table tbody');
            tabela.innerHTML = '<tr><td colspan="8" class="px-6 py-4 whitespace-nowrap text-center">Carregando...</td></tr>';

            // Construir URL
            const url = new URL(window.location.href.split('?')[0]);

            // Adicionar parâmetros
            Object.keys(params).forEach(key => {
                if (params[key]) {
                    url.searchParams.append(key, params[key]);
                }
            });

            // Fazer requisição AJAX
            fetch(url + '&ajax=true')
                .then(response => response.text())
                .then(html => {
                    // Criar um elemento temporário para analisar o HTML
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    // Extrair tabela de produtos
                    const newTable = doc.querySelector('table tbody');
                    if (newTable) {
                        tabela.innerHTML = newTable.innerHTML;
                    } else {
                        tabela.innerHTML = '<tr><td colspan="8" class="px-6 py-4 whitespace-nowrap text-center">Nenhum produto encontrado</td></tr>';
                    }

                    // Atualizar URL sem recarregar a página
                    window.history.pushState({}, '', url);

                    // Reconfigurar eventos de confirmação de exclusão
                    configurarEventosExclusao();
                })
                .catch(error => {
                    console.error('Erro ao carregar produtos:', error);
                    tabela.innerHTML = '<tr><td colspan="8" class="px-6 py-4 whitespace-nowrap text-center">Erro ao carregar produtos</td></tr>';
                });
        }

        // Reconfigurar botões de exclusão após AJAX
        function configurarEventosExclusao() {
            const botoesExcluir = document.querySelectorAll('button[onclick^="confirmarExclusao"]');
            botoesExcluir.forEach(botao => {
                const onclick = botao.getAttribute('onclick');
                const matches = onclick.match(/confirmarExclusao\((\d+),\s*['"]([^'"]+)['"]\)/);
                if (matches && matches.length >= 3) {
                    const id = matches[1];
                    const nome = matches[2];
                    botao.onclick = function() {
                        confirmarExclusao(id, nome);
                    };
                }
            });
        }

        // Função confirmarExclusao modificada
        function confirmarExclusao(id, nome) {
            document.getElementById('productName').textContent = nome;
            document.getElementById('confirmDelete').href = 'visualizar_produtos.php?delete=' + id;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>