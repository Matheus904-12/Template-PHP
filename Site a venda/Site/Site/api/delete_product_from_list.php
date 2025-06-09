<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
require_once('../../adminView/config/dbconnect.php');

// Função para excluir produto do carrinho ou favoritos
function deleteProductFromUserList($user_id, $product_id, $list_type = 'cart')
{
    // Validações iniciais
    if (!is_numeric($user_id) || $user_id <= 0) {
        return [
            'success' => false,
            'message' => 'ID de usuário inválido'
        ];
    }

    if (!is_numeric($product_id) || $product_id <= 0) {
        return [
            'success' => false,
            'message' => 'ID de produto inválido'
        ];
    }

    $servername = "localhost";
    $username = "root";
    $password = "";
    $database = "admin_panel";

    try {
        // Criar conexão
        $conn = new mysqli($servername, $username, $password, $database);

        // Verificar conexão
        if ($conn->connect_error) {
            throw new Exception('Falha na conexão: ' . $conn->connect_error);
        }

        // Determinar a tabela baseada no tipo de lista
        $table = ($list_type === 'cart') ? 'carrinho' : 'favoritos';

        // Preparar statement para verificar se o produto existe na lista do usuário
        $check_stmt = $conn->prepare("SELECT * FROM $table WHERE user_id = ? AND product_id = ?");
        $check_stmt->bind_param("ii", $user_id, $product_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        // Verificar se o produto existe na lista
        if ($result->num_rows === 0) {
            $check_stmt->close();
            $conn->close();
            return [
                'success' => false,
                'message' => 'Produto não encontrado na lista'
            ];
        }
        $check_stmt->close();

        // Preparar statement para excluir o produto
        $delete_stmt = $conn->prepare("DELETE FROM $table WHERE user_id = ? AND product_id = ?");
        $delete_stmt->bind_param("ii", $user_id, $product_id);

        // Executar exclusão
        if ($delete_stmt->execute()) {
            $delete_stmt->close();
            $conn->close();
            return [
                'success' => true,
                'message' => 'Produto removido com sucesso'
            ];
        } else {
            throw new Exception('Erro ao excluir produto: ' . $delete_stmt->error);
        }
    } catch (Exception $e) {
        // Retornar erro em caso de falha
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Receber dados do corpo da requisição
$json_input = file_get_contents('php://input');
$input_data = json_decode($json_input, true);

// Verificar se os dados necessários foram recebidos
if (!isset($input_data['user_id']) || !isset($input_data['product_id']) || !isset($input_data['list_type'])) {
    $response = [
        'success' => false,
        'message' => 'Dados incompletos'
    ];
} else {
    // Chamar a função com os dados recebidos
    $response = deleteProductFromUserList(
        $input_data['user_id'], 
        $input_data['product_id'], 
        $input_data['list_type']
    );
}

// Enviar resposta como JSON
echo json_encode($response);
exit;