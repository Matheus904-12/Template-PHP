<?php
include '../config/dbconnect.php';

function getAdmins() {
    global $conn;
    $sql = "SELECT * FROM admins"; // Corrigido de 'admin' para 'admins'
    $result = mysqli_query($conn, $sql);

    $admins = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $admins[] = $row;
    }
    return $admins;
}
?>