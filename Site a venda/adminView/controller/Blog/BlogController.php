<?php
require_once __DIR__ . '/../../models/Blog/BlogModel.php';

class BlogController {
    private $model;

    public function __construct() {
        $this->model = new BlogModel();
    }

    public function getPosts() {
        return $this->model->getAllPosts();
    }

    public function getPostById($id) {
        return $this->model->getPostById($id);
    }

    public function addPost($titulo, $resumo, $conteudo, $autor, $imagem = null) {
        $imagem_nome = $this->processarImagem($imagem);

        if ($imagem_nome === '') {
            return false;
        }

        return $this->model->addPost($titulo, $resumo, $conteudo, $autor, $imagem_nome);
    }

    public function updatePost($id, $titulo, $resumo, $conteudo, $autor, $imagem = null, $current_imagem = '') {
        $imagem_nome = $current_imagem;

        if ($imagem && $imagem['size'] > 0) {
            if (!empty($current_imagem)) {
                $imagem_path = '../../uploads/img-blog/' . $current_imagem;
                if (file_exists($imagem_path)) {
                    unlink($imagem_path);
                }
            }

            $imagem_nome = $this->processarImagem($imagem);
        }

        if ($imagem_nome === '') {
            return false;
        }

        return $this->model->updatePost($id, $titulo, $resumo, $conteudo, $autor, $imagem_nome);
    }

    public function deletePost($id) {
        return $this->model->deletePost($id);
    }

    private function processarImagem($imagem) {
        if (!$imagem || $imagem['error'] != 0) {
            error_log("Erro: Imagem não enviada ou erro no upload.");
            return '';
        }
    
        $upload_dir = '../../uploads/img-blog/';
    
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                error_log("Erro: Não foi possível criar o diretório de upload.");
                return '';
            }
        }
    
        $nome_arquivo = uniqid() . '_' . basename($imagem['name']);
        $caminho_destino = $upload_dir . $nome_arquivo;
    
        $tipo_arquivo = strtolower(pathinfo($caminho_destino, PATHINFO_EXTENSION));
        if (!in_array($tipo_arquivo, ['jpg', 'jpeg', 'png', 'gif'])) {
            error_log("Erro: Tipo de arquivo não permitido.");
            return '';
        }
    
        if (!move_uploaded_file($imagem['tmp_name'], $caminho_destino)) {
            error_log("Erro: Não foi possível mover o arquivo para o diretório de upload.");
            return '';
        }
    
        return $nome_arquivo;
    }
}
?>