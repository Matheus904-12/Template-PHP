<?php
include 'controller/Produtos/rastreioController.php';
$rastreioPedidos = getRastreioPedidos();
?>

<h2 class="text-2xl font-bold mb-4">Rastreamento de Pedidos</h2>

<table class="table table-dark table-striped">
    <thead>
        <tr>
            <th>Pedido #</th>
            <th>Data</th>
            <th>Código de Rastreio</th>
            <th>Status</th>
            <th>Endereço de Entrega</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rastreioPedidos)): ?>
            <tr>
                <td colspan="6" class="text-center py-4">Nenhum pedido com rastreamento encontrado</td>
            </tr>
        <?php else: ?>
            <?php foreach ($rastreioPedidos as $rastreio): ?>
                <tr id="rastreio-<?= $rastreio['order_id'] ?>">
                    <td><?= $rastreio['order_id'] ?></td>
                    <td><?= $rastreio['order_date'] ?></td>
                    <td>
                        <span class="font-mono"><?= $rastreio['tracking_code'] ?></span>
                    </td>
                    <td class="<?= $rastreio['status'] == 'Enviado' || $rastreio['status'] == 'Entregue' ? 'text-green-500' : 'text-yellow-500' ?>">
                        <?= $rastreio['status'] ?>
                    </td>
                    <td class="truncate max-w-xs"><?= $rastreio['shipping_address'] ?></td>
                    <td>
                        <div class="flex space-x-2">
                            <button class="px-3 py-1 bg-blue-500 text-white rounded-md shadow hover:bg-blue-600 transition"
                                onclick="trackPackage('<?= $rastreio['tracking_code'] ?>')">
                                Rastrear
                            </button>

                            <a href="../pages/Compras/detalhes-pedido.php?id=<?= $rastreio['order_id'] ?>"
                                class="px-3 py-1 bg-gray-500 text-white rounded-md shadow hover:bg-gray-600 transition">
                                Detalhes
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<script>
    function trackPackage(trackingCode) {
        if (!trackingCode) {
            alert('Código de rastreamento inválido!');
            return;
        }

        // Abre a página de rastreamento dos Correios em uma nova aba
        window.open('https://rastreamento.correios.com.br/app/index.php?codigo=' + trackingCode, '_blank');
    }
</script>