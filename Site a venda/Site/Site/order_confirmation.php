<?php
// Iniciar sessão para acessar dados do usuário logado
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    // Redirecionar para a página de login se não estiver logado
    header('Location: login_site.php?redirect=order-confirmation.php');
    exit;
}

// Obter ID do pedido da URL
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_NUMBER_INT);
$confirmed = filter_input(INPUT_GET, 'confirmed', FILTER_SANITIZE_STRING) === 'true';

// Verificar se o ID do pedido foi fornecido
if (!$order_id) {
    header('Location: profile.php?tab=orders&error=invalid_order');
    exit;
}

// Incluir arquivo de conexão com o banco de dados
require_once '../adminView/config/dbconnect.php';

// Função para obter detalhes do pedido
function getOrderDetails($order_id, $user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT o.*, 
               DATE_FORMAT(o.order_date, '%d/%m/%Y às %H:%i') as formatted_date,
               DATE_FORMAT(o.paid_at, '%d/%m/%Y às %H:%i') as formatted_paid_date
        FROM orders o 
        WHERE o.id = ? AND o.user_id = ?
    ");
    
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    return $result->fetch_assoc();
}

// Função para obter itens do pedido
function getOrderItems($order_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT oi.*, p.name as product_name, p.image as product_image 
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    return $items;
}

// Obter dados do pedido
$order = getOrderDetails($order_id, $_SESSION['user_id']);

// Verificar se o pedido existe e pertence ao usuário logado
if (!$order) {
    header('Location: profile.php?tab=orders&error=not_found');
    exit;
}

// Obter itens do pedido
$order_items = getOrderItems($order_id);

// Formatar valores monetários
function formatMoney($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

// Definir título da página
$page_title = "Confirmação do Pedido #" . $order_id;

?>

<div class="container my-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <?php if ($confirmed): ?>
            <div class="alert alert-success mb-4" role="alert">
                <h4 class="alert-heading"><i class="fas fa-check-circle"></i> Pagamento Confirmado!</h4>
                <p>Seu pagamento foi processado com sucesso. Obrigado pela sua compra!</p>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Pedido #<?php echo htmlspecialchars($order_id); ?></h3>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Informações do Pedido</h5>
                            <p><strong>Data do Pedido:</strong> <?php echo htmlspecialchars($order['formatted_date']); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge <?php echo $order['status'] === 'processing' ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php 
                                    $status_labels = [
                                        'processing' => 'Em Processamento',
                                        'shipped' => 'Enviado',
                                        'delivered' => 'Entregue',
                                        'canceled' => 'Cancelado',
                                        'pending' => 'Pendente',
                                        'awaiting_payment' => 'Aguardando Pagamento',
                                        'approved' => 'Aprovado'
                                    ];
                                    echo htmlspecialchars($status_labels[$order['status']] ?? $order['status']); 
                                    ?>
                                </span>
                            </p>
                            <p><strong>Método de Pagamento:</strong> 
                                <?php 
                                $payment_methods = [
                                    'credit_card' => 'Cartão de Crédito',
                                    'pix' => 'PIX',
                                    'bank_transfer' => 'Transferência Bancária'
                                ];
                                echo htmlspecialchars($payment_methods[$order['payment_method']] ?? $order['payment_method']); 
                                ?>
                            </p>
                            <?php if ($order['payment_method'] === 'credit_card' && !empty($order['card_last4'])): ?>
                            <p><strong>Cartão:</strong> **** **** **** <?php echo htmlspecialchars($order['card_last4']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($order['paid_at'])): ?>
                            <p><strong>Data do Pagamento:</strong> <?php echo htmlspecialchars($order['formatted_paid_date']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h5>Endereço de Entrega</h5>
                            <address>
                                <?php echo htmlspecialchars($order['shipping_address']); ?>, 
                                <?php echo htmlspecialchars($order['shipping_number']); ?><br>
                                <?php if (!empty($order['shipping_complement'])): ?>
                                    <?php echo htmlspecialchars($order['shipping_complement']); ?><br>
                                <?php endif; ?>
                                CEP: <?php echo htmlspecialchars($order['shipping_cep']); ?>
                            </address>
                            
                            <?php if (!empty($order['tracking_code'])): ?>
                            <p class="mt-3"><strong>Código de Rastreio:</strong> <?php echo htmlspecialchars($order['tracking_code']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h5>Itens do Pedido</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th class="text-center">Quantidade</th>
                                    <th class="text-end">Preço</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($item['product_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($item['product_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                                 class="img-thumbnail me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                            <?php endif; ?>
                                            <div>
                                                <?php echo htmlspecialchars($item['product_name']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td class="text-end"><?php echo formatMoney($item['price']); ?></td>
                                    <td class="text-end"><?php echo formatMoney($item['price'] * $item['quantity']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                    <td class="text-end"><?php echo formatMoney($order['subtotal']); ?></td>
                                </tr>
                                <?php if ($order['discount'] > 0): ?>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Desconto:</strong></td>
                                    <td class="text-end">-<?php echo formatMoney($order['discount']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Frete:</strong></td>
                                    <td class="text-end"><?php echo formatMoney($order['shipping']); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                    <td class="text-end h5"><?php echo formatMoney($order['total']); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <a href="profile.php?tab=orders" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Voltar para Meus Pedidos
                        </a>
                        
                        <a href="contact.php?subject=Pedido%20#<?php echo $order_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-envelope me-2"></i>Suporte
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>