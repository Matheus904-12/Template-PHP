<?php
// detalhes-pedido.php
include '../../config/dbconnect.php';
include '../../controller/Produtos/pedidosController.php';

// Verifica se o ID do pedido foi fornecido (aceita tanto 'id' quanto 'order_id')
$orderId = null;
if (isset($_GET['order_id']) && !empty($_GET['order_id'])) {
    $orderId = intval($_GET['order_id']);
} elseif (isset($_GET['id']) && !empty($_GET['id'])) {
    $orderId = intval($_GET['id']);
}

if (!$orderId) {
    echo '<div class="bg-indigo-100 border border-indigo-400 text-indigo-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Erro!</strong>
            <span class="block sm:inline">ID do pedido não fornecido. <a href="../index.php" class="underline">Retorne para a lista de pedidos</a>.</span>
          </div>';
    exit;
}

// Obter detalhes do pedido
$pedido = getPedidoDetalhes($orderId);

// Verifica se o pedido existe
if (!$pedido) {
    echo '<div class="bg-indigo-100 border border-indigo-400 text-indigo-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Erro!</strong>
            <span class="block sm:inline">Pedido não encontrado. <a href="../index.php" class="underline">Retorne para a lista de pedidos</a>.</span>
          </div>';
    exit;
}

// Função para obter URL de rastreio baseado no código de rastreio
function getURLRastreio($trackingCode)
{
    // Verificar se o código de rastreio parece ser dos Correios (BR)
    if (preg_match('/^[A-Z]{2}[0-9]{9}[A-Z]{2}$/', $trackingCode)) {
        return "https://www.linkcorreios.com.br/?id=" . $trackingCode;
    }

    // URL padrão para outros casos (pode ser adaptado para outras transportadoras)
    return "https://www.linkcorreios.com.br/?id=" . $trackingCode;
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Pedido #<?= $orderId ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-indigo-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center mb-6">
            <a href="../index.php" class="mr-4 text-indigo-500 hover:text-indigo-700">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <h1 class="text-2xl font-bold text-indigo-800">Detalhes do Pedido #<?= $orderId ?></h1>
        </div>

        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-4 pb-4 border-b border-indigo-200">
                <div>
                    <h2 class="text-xl font-semibold text-indigo-700">Informações do Pedido</h2>
                    <p class="text-indigo-600 text-sm">Pedido realizado em <?= $pedido['order_date'] ?></p>
                </div>
                <div class="flex flex-col items-end">
                    <span class="px-4 py-2 rounded-full text-sm font-semibold 
                        <?php if ($pedido['status'] == 'aceito' || $pedido['status'] == 'pago' || $pedido['status'] == 'enviado' || $pedido['status'] == 'entregue'): ?>
                            bg-green-100 text-green-800
                        <?php elseif ($pedido['status'] == 'em_alerta' || $pedido['status'] == 'cancelado'): ?>
                            bg-red-100 text-red-800
                        <?php else: ?>
                            bg-yellow-100 text-yellow-800
                        <?php endif; ?>">
                        <?= $pedido['status_display'] ?>
                    </span>
                    <?php if (isset($pedido['updated_at'])): ?>
                        <span class="text-xs text-indigo-500 mt-1">Atualizado em: <?= date('d/m/Y H:i', strtotime($pedido['updated_at'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($pedido['tracking_code']) && !empty($pedido['tracking_code'])): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-indigo-700 mb-3">Código de Rastreio</h3>
                        <div class="flex items-center mb-4">
                            <span class="font-mono text-lg bg-indigo-100 px-3 py-2 rounded text-indigo-800"><?= $pedido['tracking_code'] ?></span>
                            <button onclick="copyTrackingCode('<?= $pedido['tracking_code'] ?>')" class="ml-2 text-indigo-500 hover:text-indigo-700">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <a href="<?= getURLRastreio($pedido['tracking_code']) ?>" target="_blank" class="inline-block px-4 py-2 bg-indigo-500 text-white rounded-md shadow hover:bg-indigo-600 transition text-sm font-medium">
                            <i class="fas fa-truck mr-2"></i> Rastrear nos Correios
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($pedido['shipping_address']) || isset($pedido['shipping_cep'])): ?>
                <div class="bg-indigo-50 p-4 rounded-lg mb-6">
                    <h3 class="text-lg font-semibold text-indigo-700 mb-3">Endereço de Entrega</h3>

                    <?php if (isset($pedido['shipping_address']) && !empty($pedido['shipping_address'])): ?>
                        <p class="text-indigo-600 mb-1"><span class="font-semibold text-indigo-700">Endereço:</span> <?= $pedido['shipping_address'] ?></p>
                    <?php endif; ?>

                    <?php if (isset($pedido['shipping_number']) && !empty($pedido['shipping_number'])): ?>
                        <p class="text-indigo-600 mb-1"><span class="font-semibold text-indigo-700">Número:</span> <?= $pedido['shipping_number'] ?></p>
                    <?php endif; ?>

                    <?php if (isset($pedido['shipping_complement']) && !empty($pedido['shipping_complement'])): ?>
                        <p class="text-indigo-600 mb-1"><span class="font-semibold text-indigo-700">Complemento:</span> <?= $pedido['shipping_complement'] ?></p>
                    <?php endif; ?>

                    <?php if (isset($pedido['shipping_cep']) && !empty($pedido['shipping_cep'])): ?>
                        <p class="text-indigo-600"><span class="font-semibold text-indigo-700">CEP:</span> <?= $pedido['shipping_cep'] ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-indigo-700 mb-4">Informações de Pagamento</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 bg-indigo-50 rounded-lg">
                    <p class="text-sm text-indigo-500 uppercase">Subtotal</p>
                    <p class="font-semibold text-indigo-800">R$ <?= isset($pedido['subtotal']) ? number_format($pedido['subtotal'], 2, ',', '.') : number_format($pedido['total'], 2, ',', '.') ?></p>
                </div>
                <div class="p-4 bg-indigo-50 rounded-lg">
                    <p class="text-sm text-indigo-500 uppercase">Frete</p>
                    <p class="font-semibold text-indigo-800">R$ <?= isset($pedido['shipping']) ? number_format($pedido['shipping'], 2, ',', '.') : '0,00' ?></p>
                </div>
                <div class="p-4 bg-indigo-50 rounded-lg">
                    <p class="text-sm text-indigo-500 uppercase">Desconto</p>
                    <p class="font-semibold text-indigo-800">R$ <?= isset($pedido['discount']) ? number_format($pedido['discount'], 2, ',', '.') : '0,00' ?></p>
                </div>
            </div>

            <div class="mt-4 p-4 bg-indigo-500 rounded-lg flex justify-between items-center">
                <div>
                    <p class="text-indigo-100 uppercase text-sm">Total do Pedido</p>
                    <p class="text-xl font-bold text-indigo-100">R$ <?= number_format($pedido['total'], 2, ',', '.') ?></p>
                </div>
                <div>
                    <p class="text-indigo-100 uppercase text-sm">Forma de Pagamento</p>
                    <p class="font-semibold text-indigo-100 flex items-center">
                        <?php if (isset($pedido['payment_method'])): ?>
                            <?php if ($pedido['payment_method'] == 'credit_card' || $pedido['payment_method'] == 'Cartão de Crédito'): ?>
                                <i class="fas fa-credit-card mr-2"></i> Cartão de Crédito
                                <?php if (isset($pedido['card_last4']) && !empty($pedido['card_last4'])): ?>
                                    <span class="ml-1 text-xs text-indigo-200">(Final <?= $pedido['card_last4'] ?>)</span>
                                <?php endif; ?>
                            <?php elseif ($pedido['payment_method'] == 'boleto' || $pedido['payment_method'] == 'Boleto'): ?>
                                <i class="fas fa-barcode mr-2"></i> Boleto Bancário
                            <?php elseif ($pedido['payment_method'] == 'pix' || $pedido['payment_method'] == 'PIX'): ?>
                                <i class="fas fa-qrcode mr-2"></i> PIX
                            <?php elseif ($pedido['payment_method'] == 'bank_transfer' || $pedido['payment_method'] == 'Transferência Bancária'): ?>
                                <i class="fas fa-university mr-2"></i> Transferência Bancária
                            <?php else: ?>
                                <?= ucfirst(str_replace('_', ' ', $pedido['payment_method'])) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Lista de Produtos -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-indigo-700 mb-4">Produtos do Pedido</h2>

            <?php if (isset($pedido['itens']) && !empty($pedido['itens'])): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-indigo-100">
                            <tr>
                                <th class="py-3 px-4 text-left text-xs font-medium text-indigo-700 uppercase tracking-wider">Produto</th>
                                <th class="py-3 px-4 text-left text-xs font-medium text-indigo-700 uppercase tracking-wider">Quantidade</th>
                                <th class="py-3 px-4 text-left text-xs font-medium text-indigo-700 uppercase tracking-wider">Preço Unitário</th>
                                <th class="py-3 px-4 text-left text-xs font-medium text-indigo-700 uppercase tracking-wider">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-indigo-100">
                            <?php foreach ($pedido['itens'] as $item): ?>
                                <tr class="hover:bg-indigo-50">
                                    <td class="py-4 px-4">
                                        <div class="flex items-center space-x-3">
                                            <?php if (isset($item['produto_imagem']) && !empty($item['produto_imagem'])): ?>
                                                <div class="flex-shrink-0 w-12 h-12">
                                                    <img class="w-12 h-12 object-cover rounded" src="../../uploads/produtos/<?= $item['produto_imagem'] ?>" alt="<?= $item['produto_nome'] ?>">
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <p class="text-indigo-800 font-medium"><?= $item['produto_nome'] ?></p>
                                                <?php if (isset($item['variacao']) && !empty($item['variacao'])): ?>
                                                    <span class="text-xs text-indigo-500">Variação: <?= $item['variacao'] ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4 text-indigo-700">
                                        <?= isset($item['quantity']) ? $item['quantity'] : 1 ?>
                                    </td>
                                    <td class="py-4 px-4 text-indigo-700">
                                        R$ <?= number_format($item['produto_preco'], 2, ',', '.') ?>
                                    </td>
                                    <td class="py-4 px-4 font-medium text-indigo-800">
                                        R$ <?= number_format($item['produto_preco'] * (isset($item['quantidade']) ? $item['quantidade'] : 1), 2, ',', '.') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <p class="text-indigo-700">Não há produtos disponíveis para este pedido.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold text-indigo-700 mb-4">Histórico de Atualizações</h2>
            <div class="relative">
                <div class="absolute left-4 top-0 h-full w-0.5 bg-indigo-200"></div>

                <div class="relative pl-12 pb-8">
                    <div class="absolute left-2 -top-1 h-8 w-8 flex items-center justify-center rounded-full bg-indigo-500 text-white">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="bg-indigo-50 p-4 rounded shadow-sm">
                        <p class="font-semibold text-indigo-800"><?= $pedido['status_display'] ?></p>
                        <p class="text-sm text-indigo-500">Status atual do pedido</p>
                        <?php if (isset($pedido['updated_at'])): ?>
                            <p class="text-xs text-indigo-500"><?= date('d/m/Y H:i', strtotime($pedido['updated_at'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="relative pl-12">
                    <div class="absolute left-2 -top-1 h-8 w-8 flex items-center justify-center rounded-full bg-indigo-300 text-white">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="bg-indigo-50 p-4 rounded shadow-sm">
                        <p class="font-semibold text-indigo-800">Pedido Recebido</p>
                        <p class="text-sm text-indigo-500">Seu pedido foi recebido e está sendo processado</p>
                        <p class="text-xs text-indigo-500"><?= $pedido['order_date'] ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botões de ação -->
        <div class="mt-6 flex justify-end space-x-3">
            <?php if ($pedido['status'] != 'entregue' && $pedido['status'] != 'cancelado'): ?>
                <button onclick="updatePedidoStatus(<?= $orderId ?>, 'Enviado')" class="px-4 py-2 bg-blue-500 text-white rounded-md shadow hover:bg-blue-600 transition">
                    <i class="fas fa-truck mr-2"></i> Marcar como Enviado
                </button>

                <button onclick="updatePedidoStatus(<?= $orderId ?>, 'Entregue')" class="px-4 py-2 bg-green-500 text-white rounded-md shadow hover:bg-green-600 transition">
                    <i class="fas fa-check-circle mr-2"></i> Marcar como Entregue
                </button>

                <button onclick="updatePedidoStatus(<?= $orderId ?>, 'Cancelado')" class="px-4 py-2 bg-red-500 text-white rounded-md shadow hover:bg-red-600 transition">
                    <i class="fas fa-times-circle mr-2"></i> Cancelar Pedido
                </button>
            <?php endif; ?>

            <a href="../index.php" class="px-4 py-2 bg-gray-500 text-white rounded-md shadow hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i> Voltar
            </a>
        </div>
    </div>

    <script>
        function copyTrackingCode(code) {
            navigator.clipboard.writeText(code)
                .then(() => {
                    alert('Código de rastreio copiado para a área de transferência!');
                })
                .catch(err => {
                    console.error('Erro ao copiar o código: ', err);
                });
        }

        function updatePedidoStatus(orderId, status) {
            if (confirm(`Confirma a alteração do status para "${status}"?`)) {
                fetch('../../controller/Produtos/updatePedidoStatus.php', {
                        method: 'POST',
                        body: new URLSearchParams({
                            order_id: orderId,
                            status: status
                        }),
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        }
                    })
                    .then(response => {
                        // Verificar se a resposta está ok antes de tentar interpretar como JSON
                        if (!response.ok) {
                            throw new Error(`Erro HTTP: ${response.status}`);
                        }

                        // Tentar interpretar a resposta como JSON
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('Erro ao analisar JSON:', text);
                                throw new Error('Resposta inválida do servidor');
                            }
                        });
                    })
                    .then(data => {
                        if (data.success) {
                            alert(`Status do pedido #${orderId} atualizado para ${status}!`);
                            // Recarregar a página para mostrar as atualizações
                            location.reload();
                        } else {
                            alert("Erro ao atualizar status: " + (data.message || "Erro desconhecido"));
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao atualizar pedido:', error);
                        alert("Erro ao processar a solicitação. Verifique o console para mais detalhes.");
                    });
            }
        }
    </script>
</body>

</html>