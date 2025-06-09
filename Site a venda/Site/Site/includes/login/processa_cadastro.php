<?php
require_once '../../../adminView/config/dbconnect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = password_hash(trim($_POST['senha']), PASSWORD_BCRYPT);
    $endereco = $_POST['endereco'] ?? '';
    $cep = $_POST['cep'] ?? '';
    $numero_casa = $_POST['numero_casa'] ?? '';
    $telefone = $_POST['telefone'] ?? '';

    $query = "SELECT id FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Email já cadastrado!"]);
    } else {
        $query = "INSERT INTO usuarios (name, email, password, endereco, cep, numero_casa, telefone) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssss", $nome, $email, $senha, $endereco, $cep, $numero_casa, $telefone);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Conta criada com sucesso!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Erro ao cadastrar usuário: " . $conn->error]);
        }
    }
} else {
    echo json_encode(["status" => "error", "message" => "Método de requisição inválido."]);
}
?>
