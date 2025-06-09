<?php
include '../../config/dbconnect.php';

$new_password = password_hash("admin123", PASSWORD_DEFAULT);
$sql = "UPDATE admins SET password = ? WHERE username = 'admin'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $new_password);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Senha alterada com sucesso!";
} else {
    echo "Erro ao alterar a senha.";
}

$stmt->close();
$conn->close();
?>
