<?php
include '../config/dbconnect.php';

function getEstoque() {
    global $conn;
    $sql = "SELECT * FROM estoque";
    $result = mysqli_query($conn, $sql);

    $estoque = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $estoque[] = $row;
    }
    return $estoque;
}
?>
