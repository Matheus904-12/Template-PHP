<?php
include './config/dbconnect.php';

function getEstatisticas() {
    global $conn;
    $estatisticas = [];

    $queries = [
        "total_usuarios" => "SELECT COUNT(*) AS total FROM usuarios",
        "total_pedidos" => "SELECT COUNT(*) AS total FROM pedidos",
        "ganhos_mes" => "SELECT IFNULL(SUM(valor_total), 0) AS total FROM pedidos WHERE MONTH(data_pedido) = MONTH(NOW())",
    ];

    foreach ($queries as $key => $sql) {
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($result);
        $estatisticas[$key] = $row['total'];
    }

    return $estatisticas;
}
?>
