<?php
include '../config/dbconnect.php';

function loginAdmin($email, $senha) {
    global $conn;
    $sql = "SELECT * FROM admin WHERE email = '$email' AND senha = '$senha'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $_SESSION['admin_logged_in'] = true;
        return true;
    } else {
        return false;
    }
}
?>
