<?php
session_start();
session_destroy(); // Destroi todas as sessões
header("Location: ../../../login.php"); // Redireciona para a página de login
exit();
?>
