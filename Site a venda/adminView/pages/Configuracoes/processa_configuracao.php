<?php 
require_once '../../controller/Configuracoes/ConfigController.php';

$jsonPath = '../../config_site.json'; 
$controller = new ConfigController($jsonPath);

$uploadDir = '../../uploads/inicio/'; 
$allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif']; 
$allowedVideoTypes = ['video/mp4', 'video/webm', 'video/ogg'];

if ($_SERVER["REQUEST_METHOD"] == "POST") { 
    $midia = $_POST['midia_url'] ?? '';

    // O arquivo foi enviado corretamente
    if (isset($_FILES['midia_upload']) && $_FILES['midia_upload']['error'] === UPLOAD_ERR_OK) { 
        $fileTmpPath = $_FILES['midia_upload']['tmp_name']; 
        $fileType = mime_content_type($fileTmpPath); 
        $fileName = basename($_FILES['midia_upload']['name']); 
        $fileDestination = $uploadDir . $fileName;

        // Verifica se o formato é válido
        if (in_array($fileType, array_merge($allowedImageTypes, $allowedVideoTypes))) { 
            // Move o arquivo para a pasta de uploads
            if (move_uploaded_file($fileTmpPath, $fileDestination)) { 
                $midia = $fileDestination; // Caminho salvo no JSON
            } else { 
                http_response_code(400);
                echo "Erro ao salvar o arquivo.";
                exit; 
            } 
        } else { 
            http_response_code(400);
            echo "Tipo de arquivo não permitido.";
            exit; 
        } 
    }

    // Atualiza o JSON com a mídia processada
    $data = [ 
        'pagina_inicial' => [ 
            'sobre' => [ 
                'midia' => $midia 
            ], 
        ], 
        'contato' => [ 
            'whatsapp' => $_POST['whatsapp'], 
            'instagram' => $_POST['instagram'], 
            'facebook' => $_POST['facebook'], 
            'email' => $_POST['email'] 
        ] 
    ];

    $mensagem = $controller->salvarJson(json_encode($data)); 
    echo $mensagem; 
} else { 
    http_response_code(405);
    echo "Erro: Método inválido."; 
}
?>