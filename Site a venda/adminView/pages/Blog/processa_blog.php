<?php
require_once('../../controller/Blog/BlogController.php');

$response = [
    'status' => 'error',
    'message' => 'Ocorreu um erro ao processar a requisição.'
];

try {
    $controller = new BlogController();

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_post':
                if (empty($_POST['titulo']) || empty($_POST['resumo']) || empty($_POST['conteudo']) || empty($_POST['autor'])) {
                    throw new Exception("Todos os campos são obrigatórios.");
                }

                if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] > 0) {
                    throw new Exception("É necessário enviar uma imagem para o post.");
                }

                $result = $controller->addPost(
                    $_POST['titulo'],
                    $_POST['resumo'],
                    $_POST['conteudo'],
                    $_POST['autor'],
                    $_FILES['imagem']
                );

                if ($result) {
                    $response = [
                        'status' => 'success',
                        'message' => 'Post adicionado com sucesso!',
                        'post_id' => $result
                    ];
                } else {
                    error_log("Erro ao adicionar post: " . json_encode($_POST)); // Log de erro
                    throw new Exception("Não foi possível adicionar o post.");
                }
                break;

            case 'get_post':
                if (empty($_POST['post_id'])) {
                    throw new Exception("ID do post não fornecido.");
                }

                $post = $controller->getPostById($_POST['post_id']);

                if ($post) {
                    $response = [
                        'status' => 'success',
                        'post' => $post
                    ];
                } else {
                    throw new Exception("Post não encontrado.");
                }
                break;

            case 'update_post':
                if (empty($_POST['post_id']) || empty($_POST['titulo']) || empty($_POST['resumo']) || empty($_POST['conteudo']) || empty($_POST['autor'])) {
                    throw new Exception("Todos os campos são obrigatórios.");
                }

                $post_id = $_POST['post_id'];
                $current_imagem = isset($_POST['current_imagem']) ? $_POST['current_imagem'] : '';

                $result = $controller->updatePost(
                    $post_id,
                    $_POST['titulo'],
                    $_POST['resumo'],
                    $_POST['conteudo'],
                    $_POST['autor'],
                    isset($_FILES['imagem']) && $_FILES['imagem']['error'] === 0 ? $_FILES['imagem'] : null,
                    $current_imagem
                );

                if ($result) {
                    $response = [
                        'status' => 'success',
                        'message' => 'Post atualizado com sucesso!'
                    ];
                } else {
                    error_log("Erro ao atualizar post: " . json_encode($_POST)); // Log de erro
                    throw new Exception("Não foi possível atualizar o post.");
                }
                break;

            case 'delete_post':
                if (empty($_POST['post_id'])) {
                    throw new Exception("ID do post não fornecido.");
                }

                $result = $controller->deletePost($_POST['post_id']);

                if ($result) {
                    $response = [
                        'status' => 'success',
                        'message' => 'Post excluído com sucesso!'
                    ];
                } else {
                    error_log("Erro ao excluir post: " . json_encode($_POST)); // Log de erro
                    throw new Exception("Não foi possível excluir o post.");
                }
                break;

            default:
                throw new Exception("Ação desconhecida.");
        }
    } else {
        throw new Exception("Nenhuma ação especificada.");
    }
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    error_log("Erro no processa_blog.php: " . $e->getMessage() . " - Dados: " . json_encode($_POST)); // Log de erro
}

header('Content-Type: application/json');
echo json_encode($response);
?>