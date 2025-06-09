<?php
// processa_upload.php
require_once(__DIR__ . '/../../models/Galeria/GaleriaModel.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['imagem'])) {
    $categoria = $_POST['category'];
    $imagem = $_FILES['imagem'];

    $uploadDir = '../../uploads/galeria/';
    $fileName = basename($imagem['name']);
    $uploadFile = $uploadDir . $fileName;

    // Verifica se a pasta "uploads" existe, se não, cria
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Move o arquivo para a pasta de uploads
    if (move_uploaded_file($imagem['tmp_name'], $uploadFile)) {
        $galeriaModel = new GaleriaModel();
        $galeriaModel->adicionarImagem($fileName, $categoria);
        echo "Imagem enviada com sucesso!";
    } else {
        echo "Erro ao enviar a imagem.";
    }
}
?>
