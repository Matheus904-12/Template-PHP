<?php
session_start();
include '../config/dbconnect.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Inicializa um array para armazenar os dados
$data = [
    'total_usuarios' => 0,
    'total_logins' => 0,
    'rastreio_entregas' => 0,
    'total_estoque' => 0,
    'total_duvidas' => 0,
    'total_pedidos' => 0
];

// Define todas as consultas SQL
$queries = [
    "total_usuarios" => "SELECT COUNT(*) AS total FROM usuarios",
    "total_logins" => "SELECT COUNT(*) AS total FROM logins",
    "rastreio_entregas" => "SELECT COUNT(*) AS total FROM orders",
    "total_estoque" => "SELECT COUNT(*) AS total FROM produtos",
    "total_duvidas" => "SELECT COUNT(*) AS total FROM blog_comentarios",
    "total_pedidos" => "SELECT COUNT(*) AS total FROM order_items"
];

// Executa cada consulta e armazena os resultados
try {
    foreach ($queries as $key => $sql) {
        $stmt = $pdo->query($sql);
        if ($stmt) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $data[$key] = $row['total'];
        }
    }
} catch (PDOException $e) {
    error_log("Erro na consulta SQL: " . $e->getMessage());
    // Opcional: Mostrar mensagem de erro amigável para o usuário
    // echo "Ocorreu um erro ao carregar os dados do dashboard.";
}

include '../includes/adminHeader.php';
include '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <title>Painel Administrativo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-gray-900 text-white">
    <main class="container mx-auto p-6">

        <!-- Conteúdo Padrão do Dashboard -->
        <div id="defaultContent">
            <h2 class="text-2xl font-bold mb-4">Bem-vindo ao Painel Administrativo - Cristais Gold Lar</h2>
            <p class="text-gray-400 mb-6">Use o menu lateral para gerenciar o conteúdo do site.</p>
            <a href="../visualizar_produtos.php" class="stockBtn btn-sm mt-4 bg-yellow-500 hover:bg-yellow-600 text-white rounded-md px-4 py-2 shadow-lg transition-all duration-300 ease-in-out hover:shadow-xl hover:translate-y-[-2px] focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:ring-opacity-50" style="text-decoration: none;">Ver Estoque</a>            <br><br>
            <!-- Cards Informativos -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php
                $cards = [
                    ["icon" => "fa-sign-in-alt", "title" => "Administradores", "value" => $data['total_logins']],
                    ["icon" => "fa-truck", "title" => "Rastreamento", "value" => $data['rastreio_entregas']],
                    ["icon" => "fa-user-plus", "title" => "Cadastros", "value" => $data['total_usuarios']],
                    ["icon" => "fa-boxes", "title" => "Estoque", "value" => $data['total_estoque']],
                    ["icon" => "fa-comments", "title" => "Comentários", "value" => $data['total_duvidas']],
                    ["icon" => "fa-clipboard-list", "title" => "Pedidos", "value" => $data['total_pedidos']],
                ];

                foreach ($cards as $card) {
                    echo '<div class="bg-gray-800 p-6 rounded-lg shadow-lg flex flex-col items-center">';
                    echo '<i class="fas ' . $card['icon'] . ' text-4xl text-yellow-400"></i>';
                    echo '<h4 class="text-lg font-semibold mt-3">' . $card['title'] . '</h4>';
                    echo '<h5 class="text-2xl font-bold mt-2">' . $card['value'] . '</h5>';

                    // Verifica se existe botão e exibe
                    if (isset($card['button'])) {
                        echo '<a href="' . $card['button']['href'] . '" class="' . $card['button']['class'] . '">' . $card['button']['label'] . '</a>';
                    }

                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <!-- Aqui será carregado o conteúdo da sidebar via AJAX -->
        <div class="allContent-section mt-6"></div>
    </main>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/ajaxWork.js"></script>
    <script src="../assets/js/script.js"></script>

    <script>
        function loadPage(page) {
            $.ajax({
                url: page,
                type: "GET",
                success: function(response) {
                    $("#defaultContent").hide(); // Esconde o conteúdo inicial
                    $(".allContent-section").html(response).show(); // Exibe a nova página carregada
                },
                error: function(xhr, status, error) {
                    console.error("Erro ao carregar a página:", error);
                    $(".allContent-section").html("<p class='text-danger'>Erro ao carregar a página.</p>");
                }
            });
        }
    </script>

</body>
</html>