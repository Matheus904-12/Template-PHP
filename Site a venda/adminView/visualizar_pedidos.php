<?php

// visualizar_pedidos.php
include 'controller/Produtos/pedidosController.php';
$pedidos = getPedidos();
?>

<h2 class="text-2xl font-bold mb-4">Lista de Pedidos</h2>
<table class="table table-dark table-striped">
    <thead>
        <tr>
            <th>Pedido #</th>
            <th>Cliente</th>
            <th>Data</th>
            <th>Total</th>
            <th>Método de Pagamento</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($pedidos)): ?>
            <tr>
                <td colspan="7" class="text-center py-4">Nenhum pedido encontrado</td>
            </tr>
        <?php else: ?>
            <?php foreach ($pedidos as $pedido): ?>
                <tr id="pedido-<?= $pedido['order_id'] ?>">
                    <td><?= $pedido['order_id'] ?></td>
                    <td><?= $pedido['customer_id'] ?></td>
                    <td><?= $pedido['created_at'] ?></td>
                    <td>R$ <?= number_format($pedido['total'], 2, ',', '.') ?></td>
                    <td><?= $pedido['payment_method'] ?></td>
                    <td class="status-text font-semibold 
                        <?php
                        if ($pedido['status'] == 'Aceito' || $pedido['status'] == 'Pago' || $pedido['status'] == 'Enviado' || $pedido['status'] == 'Entregue') {
                            echo 'text-green-500';
                        } elseif ($pedido['status'] == 'Em Alerta' || $pedido['status'] == 'Cancelado') {
                            echo 'text-red-500';
                        } else {
                            echo 'text-yellow-500';
                        }
                        ?>">
                        <?= $pedido['status'] ?>
                    </td>
                    <td>
                        <div class="flex space-x-2">
                            <button class="px-3 py-1 bg-green-500 text-white rounded-md shadow hover:bg-green-600 transition"
                                onclick="updatePedidoStatus(<?= $pedido['order_id'] ?>, 'Aceito')">
                                Aceitar
                            </button>

                            <button class="px-3 py-1 bg-red-500 text-white rounded-md shadow hover:bg-red-600 transition"
                                onclick="updatePedidoStatus(<?= $pedido['order_id'] ?>, 'Em Alerta')">
                                Alerta
                            </button>

                            <a href="./Compras/detalhes-pedido.php?order_id=<?php echo $pedido['order_id']; ?>"
                                class="px-3 py-1 bg-blue-500 text-white rounded-md shadow hover:bg-blue-600 transition">
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
    function updatePedidoStatus(orderId, status) {
        // Mostrar indicador de carregamento
        const statusCell = document.querySelector(`#pedido-${orderId} .status-text`);
        const originalStatus = statusCell.innerText;
        statusCell.innerHTML = `<span class="animate-pulse">Atualizando...</span>`;

        // Determinar o caminho correto do arquivo
        // Use o caminho absoluto começando com / para evitar problemas de caminho relativo
        const url = '../controller/Produtos/updatePedidoStatus.php';

        fetch(url, {
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
                        throw new Error('Resposta inválida do servidor: ' + text);
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    // Atualizar status visualmente
                    statusCell.innerText = status;

                    // Muda a cor do status dinamicamente
                    statusCell.classList.remove("text-yellow-500", "text-green-500", "text-red-500");
                    if (status === "Aceito" || status === "Pago" || status === "Enviado" || status === "Entregue") {
                        statusCell.classList.add("text-green-500");
                    } else if (status === "Em Alerta" || status === "Cancelado") {
                        statusCell.classList.add("text-red-500");
                    } else {
                        statusCell.classList.add("text-yellow-500");
                    }

                    // Exibe mensagem de sucesso
                    alert(`Status do pedido #${orderId} atualizado para ${status}!`);
                } else {
                    // Restaurar status original em caso de erro
                    statusCell.innerText = originalStatus;
                    alert("Erro ao atualizar status: " + (data.message || "Erro desconhecido"));
                }
            })
            .catch(error => {
                // Restaurar status original em caso de erro
                statusCell.innerText = originalStatus;
                console.error('Erro ao atualizar pedido:', error);
                alert("Erro ao processar a solicitação: " + error.message);
            });
    }
</script>