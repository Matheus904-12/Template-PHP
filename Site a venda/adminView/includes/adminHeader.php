<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Evitar cache para garantir que a página seja recarregada com dados atualizados
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

include_once "../config/dbconnect.php";

// Incluir o controller de pedidos
if (file_exists('../controller/Produtos/pedidosController.php')) {
    include_once '../controller/Produtos/pedidosController.php';
} elseif (file_exists('./controller/Produtos/pedidosController.php')) {
    include_once './controller/Produtos/pedidosController.php';
}

// Função para traduzir status (caso não esteja definida no pedidosController.php)
if (!function_exists('traduzirStatus')) {
    function traduzirStatus($status) {
        $statusMap = [
            'aguardando_pagamento' => 'Aguardando Pagamento',
            'processando' => 'Processando',
            'pago' => 'Pago'
        ];
        return $statusMap[$status] ?? ucfirst($status);
    }
}

// Buscar pedidos recentes para notificações
$notificacoes = [];
$notificacoesNaoLidas = 0;

if (isset($_SESSION['admin_logged_in'])) {
    // Obter pedidos recentes com status que precisam de atenção e não visualizados
    $conn = $GLOBALS['conn'];

    // Query ajustada para MySQL
    $sql = "SELECT 
            id AS order_id, 
            user_id AS customer_id, 
            order_date AS created_at,
            total,
            status,
            visualizado
        FROM orders 
        WHERE (status = 'aguardando_pagamento' OR status = 'processando' OR status = 'pago') 
        AND order_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
        AND (visualizado = 0 OR visualizado IS NULL)
        ORDER BY order_date DESC 
        LIMIT 5";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Formatar data
            $row['created_at'] = date('d/m/Y H:i', strtotime($row['created_at']));
            $row['status_display'] = traduzirStatus($row['status']);
            $notificacoes[] = $row;
            $notificacoesNaoLidas++; // Incrementa para cada pedido não visualizado
        }
        error_log("Pedidos não visualizados encontrados: " . $notificacoesNaoLidas . " | Query: " . $sql);
    } else {
        error_log("Nenhum pedido encontrado ou erro na query SELECT: " . $conn->error . " | Query: " . $sql);
    }
}

// Função para marcar notificações como visualizadas
if (isset($_POST['marcar_como_visualizado']) && isset($_SESSION['admin_logged_in'])) {
    $conn = $GLOBALS['conn'];

    // Query ajustada para MySQL
    $sql = "UPDATE orders 
            SET visualizado = 1 
            WHERE (status = 'aguardando_pagamento' OR status = 'processando' OR status = 'pago') 
            AND order_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND (visualizado = 0 OR visualizado IS NULL)";

    if ($conn->query($sql)) {
        $affectedRows = $conn->affected_rows;
        error_log("Notificações marcadas como visualizadas com sucesso. Linhas afetadas: " . $affectedRows . " | Query: " . $sql);
        echo json_encode(['success' => true, 'affected_rows' => $affectedRows]);
    } else {
        error_log("Erro ao marcar notificações como visualizadas: " . $conn->error . " | Query: " . $sql);
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}
?>

<link rel="stylesheet" href="../assets/css/logout.css">
<link href="assets/css/styles.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* Estilos refinados para o botão de notificação e o badge */
    #notificationButton {
        position: relative;
        background: transparent;
        border: none;
        padding: 0.6rem;
        cursor: pointer;
        transition: all 0.3s ease;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #notificationButton:hover {
        background-color: rgba(255, 255, 255, 0.1);
        transform: translateY(-2px);
    }

    #notificationButton .bell {
        fill: #ffc107;
    }

    #notificationButton:active {
        transform: translateY(0);
    }

    .notification-badge {
        position: absolute;
        top: -3px;
        right: -3px;
        background: linear-gradient(135deg, #ffc107, #ff9800);
        color: #111;
        border-radius: 12px;
        padding: 0.15rem 0.45rem;
        font-size: 0.45rem;
        font-weight: 600;
        min-width: 1.2rem;
        text-align: center;
        box-shadow: 0 0 0 2px #1e293b;
        animation: pulseGlow 2s infinite cubic-bezier(0.4, 0, 0.6, 1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: all 0.3s ease;
    }

    @keyframes pulseGlow {
        0%, 100% {
            opacity: 1;
            box-shadow: 0 0 0 2px #1e293b, 0 0 6px 2px rgba(255, 193, 7, 0.6);
        }
        50% {
            opacity: 0.85;
            box-shadow: 0 0 0 2px #1e293b, 0 0 12px 4px rgba(255, 193, 7, 0.8);
        }
    }

    /* Estilos do dropdown de notificações */
    .notification-dropdown {
        display: none;
        position: absolute;
        right: 10px;
        top: 50px;
        width: 520px;
        max-height: 450px;
        overflow-y: auto;
        background-color: #1e293b;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.6);
        border-radius: 0.75rem;
        z-index: 1000;
        color: #e2e8f0;
        border: 1px solid #2d3c54;
        animation: fadeIn 0.2s ease-out;
        scrollbar-width: thin;
        scrollbar-color: #4b5563 #1e293b;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notification-dropdown::-webkit-scrollbar {
        width: 6px;
    }

    .notification-dropdown::-webkit-scrollbar-track {
        background: #1e293b;
    }

    .notification-dropdown::-webkit-scrollbar-thumb {
        background-color: #334366;
        border-radius: 6px;
    }

    .notification-header {
        padding: 1.25rem;
        background-color: #172033;
        border-bottom: 1px solid #2d3c54;
        border-radius: 0.75rem 0.75rem 0 0;
    }

    .notification-title {
        font-weight: 600;
        color: #ffc107;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
    }

    .notification-title i {
        margin-right: 0.5rem;
        color: #ffc107;
    }

    .notification-subtitle {
        font-size: 0.85rem;
        color: #94a3b8;
        margin-top: 0.25rem;
    }

    .notification-item {
        padding: 1.5rem;
        border-bottom: 1px solid #2d3c54;
        transition: all 0.2s ease;
        background-color: #243148;
        margin: 0.75rem;
        border-radius: 0.5rem;
        text-decoration: none;
    }

    .notification-item:hover {
        background-color: #2c3a57;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
        flex-wrap: wrap;
        gap: 7px;
    }

    .notification-order-id {
        font-weight: 600;
        color: #ffc107;
        font-size: 18px !important;
        line-height: 1.2;
    }

    .notification-time {
        font-size: 18px !important;
        color: #e2e8f0;
        background-color: #172033;
        padding: 0.35rem 0.6rem;
        border-radius: 1rem;
        margin-left: 30px;
    }

    .notification-time i {
        color: #ffc107;
        margin-right: 0.25rem;
    }

    .notification-detail {
        font-size: 0.85rem;
        color: #e2e8f0;
        margin: 1rem 0;
        display: flex;
        align-items: center;
        background-color: rgba(255, 255, 255, 0.03);
        padding: 1rem;
        border-radius: 0.5rem;
    }

    .notification-detail i {
        width: 1.5rem;
        text-align: center;
        margin-right: 0.75rem;
        font-size: 0.8rem;
        color: #ffc107;
    }

    .notification-footer {
        padding: 0.75rem;
        background-color: #172033;
        border-top: 1px solid #2d3c54;
        border-radius: 0 0 0.75rem 0.75rem;
        text-align: center;
    }

    .empty-notifications {
        padding: 2.5rem 1rem;
        text-align: center;
        color: #94a3b8;
        font-size: 0.9rem;
    }

    .empty-notifications i {
        display: block;
        font-size: 2.5rem;
        margin-bottom: 1rem;
        color: #334366;
        opacity: 0.6;
    }

    /* Ajustes para dispositivos móveis pequenos (até 480px) */
    @media (max-width: 480px) {
        .notification-dropdown {
            width: calc(100vw - 20px); /* Ocupa quase toda a largura da viewport */
            max-width: 320px; /* Garante tamanho máximo */
            right: 0;
            left: -42.5px; /* Ajustado com unidade para centralizar */
            transform: translateX(-50%);
            top: 60px;
            border-radius: 1rem;
        }
        
        .notification-header {
            padding: 1rem;
        }
        
        .notification-title {
            font-size: 1rem;
        }
        
        .notification-subtitle {
            font-size: 0.8rem;
        }
        
        .notification-item {
            padding: 1rem;
            margin: 0.5rem;
        }
        
        .notification-header-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }
        
        .notification-order-id {
            font-size: 16px !important;
        }
        
        .notification-time {
            font-size: 14px !important;
            margin-left: 0;
            align-self: flex-start;
        }
        
        .notification-detail {
            padding: 0.75rem;
            font-size: 0.8rem;
        }
        
        .notification-detail i {
            width: 1.2rem;
            margin-right: 0.5rem;
        }
    }

    /* Ajustes para telas médias (481px a 767px) */
    @media (min-width: 481px) and (max-width: 767px) {
        .notification-dropdown {
            width: 90%;
            max-width: 400px;
            right: 0; /* Mantém centralização ajustada */
            left: -42.5px;
            transform: translateX(-50%);
        }
        
        .notification-header-row {
            flex-wrap: wrap;
        }
        
        .notification-time {
            margin-left: 10px;
        }
    }

    /* Ajustes para tablets (768px a 1024px) */
    @media (min-width: 768px) and (max-width: 1024px) {
        .notification-dropdown {
            width: 450px;
            right: 10px; /* Volta ao comportamento padrão para telas maiores */
            left: auto;
            transform: none;
        }
    }
</style>

<!-- Botão de abrir sidebar -->
<nav class="navbar navbar-expand-lg navbar-light px-3 md:px-5 flex justify-between items-center bg-gray-900 text-white h-16">
    <button id="openSidebarBtn" onclick="openNav()" class="text-white text-xl md:text-2xl">
        <i class="fa fa-bars"></i>
    </button>

    <div id="date-time" class="text-white font-semibold text-sm md:text-lg text-center flex-grow hidden sm:block"></div>

    <div class="user-cart flex items-center">
        <?php if (isset($_SESSION['admin_logged_in'])) { ?>
            <!-- Botão de notificações com contador -->
            <div class="relative mr-2 md:mr-4">
                <button id="notificationButton" class="button">
                    <svg viewBox="0 0 448 512" class="bell">
                        <path d="M224 0c-17.7 0-32 14.3-32 32V49.9C119.5 61.4 64 124.2 64 200v33.4c0 45.4-15.5 89.5-43.8 124.9L5.3 377c-5.8 7.2-6.9 17.1-2.9 25.4S14.8 416 24 416H424c9.2 0 17.6-5.3 21.6-13.6s2.9-18.2-2.9-25.4l-14.9-18.6C399.5 322.9 384 278.8 384 233.4V200c0-75.8-55.5-138.6-128-150.1V32c0-17.7-14.3-32-32-32zm0 96h8c57.4 0 104 46.6 104 104v33.4c0 47.9 13.9 94.6 39.7 134.6H72.3C98.1 328 112 281.3 112 233.4V200c0-57.4 46.6-104 104-104h8zm64 352H224 160c0 17 6.7 33.3 18.7 45.3s28.3 18.7 45.3 18.7s33.3-6.7 45.3-18.7s18.7-28.3 18.7-45.3z"></path>
                    </svg>

                    <?php if ($notificacoesNaoLidas > 0): ?>
                        <span class="notification-badge" id="notificationBadge"><?= $notificacoesNaoLidas ?></span>
                    <?php endif; ?>
                </button>

                <!-- Dropdown de notificações estilizado -->
                <div id="notificationDropdown" class="notification-dropdown">
                    <div class="notification-header">
                        <div class="notification-title">
                            <i class="fas fa-bell"></i>
                            Notificações
                        </div>
                        <div class="notification-subtitle">
                            Pedidos recentes que precisam de atenção
                        </div>
                    </div>

                    <div>
                        <?php if (count($notificacoes) > 0): ?>
                            <?php foreach ($notificacoes as $notificacao): ?>
                                <?php
                                $statusClass = '';
                                if ($notificacao['status'] == 'aguardando_pagamento') {
                                    $statusClass = 'status-aguardando';
                                } elseif ($notificacao['status'] == 'processando') {
                                    $statusClass = 'status-processando';
                                } elseif ($notificacao['status'] == 'pago') {
                                    $statusClass = 'status-pago';
                                }
                                ?>
                                <div class="notification-item block">
                                    <div class="notification-header-row">
                                        <span class="notification-order-id">Pedido #<?= $notificacao['order_id'] ?></span>
                                        <span class="notification-time">
                                            <i class="far fa-clock"></i>
                                            <?= $notificacao['created_at'] ?>
                                        </span>
                                    </div>

                                    <div class="notification-detail">
                                        <i class="fas fa-money-bill-wave"></i>
                                        Total: R$ <?= number_format($notificacao['total'], 2, ',', '.') ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-notifications">
                                <i class="far fa-bell-slash"></i>
                                Nenhuma notificação nos últimos pedidos.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Botão de logout -->
            <a href="Configuracoes/logout.php">
                <button class="Btn">
                    <div class="sign">
                        <svg viewBox="0 0 512 512">
                            <path d="M377.9 105.9L500.7 228.7c7.2 7.2 11.3 17.1 11.3 27.3s-4.1 20.1-11.3 27.3L377.9 406.1c-6.4 6.4-15 9.9-24 9.9c-18.7 0-33.9-15.2-33.9-33.9l0-62.1-128 0c-17.7 0-32-14.3-32-32l0-64c0-17.7 14.3-32 32-32l128 0 0-62.1c0-18.7 15.2-33.9 33.9-33.9c9 0 17.6 3.6 24 9.9zM160 96L96 96c-17.7 0-32 14.3-32 32l0 256c0 17.7 14.3 32 32 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-53 0-96-43-96-96L0 128C0 75 43 32 96 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32z"></path>
                        </svg>
                    </div>
                </button>
            </a>
        <?php } ?>
    </div>
</nav>

<script>
    function updateDateTime() {
        const now = new Date();
        const options = {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };
        document.getElementById('date-time').innerHTML = now.toLocaleDateString('pt-BR', options);
    }

    setInterval(updateDateTime, 1000);
    updateDateTime();

    document.addEventListener('DOMContentLoaded', function() {
        const notificationButton = document.getElementById('notificationButton');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationBadge = document.getElementById('notificationBadge');

        if (notificationButton && notificationDropdown) {
            if (notificationBadge) {
                notificationButton.classList.add('has-notifications');
                console.log('Inicializando com ' + notificationBadge.textContent + ' notificações não lidas');
            } else {
                console.log('Nenhuma notificação não lida ao carregar a página');
            }

            notificationButton.addEventListener('click', function(event) {
                event.stopPropagation();
                if (notificationDropdown.style.display === 'block') {
                    notificationDropdown.style.display = 'none';
                    console.log('Dropdown fechado');
                } else {
                    notificationDropdown.style.display = 'block';
                    console.log('Dropdown aberto');

                    if (notificationBadge) {
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', window.location.href, true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4) {
                                if (xhr.status === 200) {
                                    try {
                                        const response = JSON.parse(xhr.responseText);
                                        if (response.success) {
                                            console.log('Notificações marcadas como visualizadas. Linhas afetadas: ' + (response.affected_rows || 0));
                                            notificationBadge.style.display = 'none';
                                            notificationButton.classList.remove('has-notifications');
                                        } else {
                                            console.error('Erro ao marcar notificações como visualizadas:', response.error);
                                        }
                                    } catch (e) {
                                        console.error('Erro ao processar resposta JSON:', e, xhr.responseText);
                                    }
                                } else {
                                    console.error('Erro na requisição AJAX:', xhr.status, xhr.statusText);
                                }
                            }
                        };
                        xhr.onerror = function() {
                            console.error('Erro de rede na requisição AJAX');
                        };
                        xhr.send('marcar_como_visualizado=1');
                    } else {
                        console.log('Nenhum badge para marcar como visualizado');
                    }
                }
            });

            document.addEventListener('click', function(event) {
                if (!notificationButton.contains(event.target) && !notificationDropdown.contains(event.target)) {
                    notificationDropdown.style.display = 'none';
                    console.log('Dropdown fechado ao clicar fora');
                }
            });

            notificationDropdown.addEventListener('click', function(event) {
                event.stopPropagation();
            });
        } else {
            console.error('Elementos notificationButton ou notificationDropdown não encontrados');
        }
    });
</script>