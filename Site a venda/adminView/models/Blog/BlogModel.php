<?php
class BlogModel
{
    private $conn;

    public function __construct()
    {
        // Tentativa de múltiplos caminhos para encontrar os arquivos de configuração
        $configPaths = [
            __DIR__ . '/../config/dbconnect.php',
            __DIR__ . '/../../config/dbconnect.php',
            '../config/dbconnect.php',
            '../../config/dbconnect.php',
            dirname(__FILE__) . '/../config/dbconnect.php'
        ];
        
        $configFound = false;
        foreach ($configPaths as $path) {
            if (file_exists($path)) {
                require_once($path);
                $configFound = true;
                break;
            }
        }
        
        if (!$configFound) {
            die("Erro crítico: Arquivo de configuração do banco de dados não encontrado.");
        }
        
        // Tentativa de incluir o arquivo de constantes
        $constantsPaths = [
            __DIR__ . '/../config/dbconnect.php',
            __DIR__ . '/../../config/dbconnect.php',
            '../config/dbconnect.php',
            '../../config/dbconnect.php',
            dirname(__FILE__) . '/../config/dbconnect.php'
        ];
        
        foreach ($constantsPaths as $path) {
            if (file_exists($path)) {
                require_once($path);
                break;
            }
        }

        // Conexão com o banco de dados
        try {
            $this->conn = new mysqli($servername, $username, $password, $database);

            if ($this->conn->connect_error) {
                throw new Exception("Conexão falhou: " . $this->conn->connect_error);
            }

            $this->conn->set_charset("utf8");
        } catch (Exception $e) {
            error_log("Erro de conexão: " . $e->getMessage());
            die("Não foi possível conectar ao banco de dados. Por favor, contate o administrador.");
        }
    }

    public function getAllPosts()
    {
        $sql = "SELECT * FROM blog_posts ORDER BY data_publicacao DESC";
        $result = $this->conn->query($sql);

        if (!$result) {
            error_log("Erro na consulta getAllPosts: " . $this->conn->error);
            return [];
        }

        $posts = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $posts[] = $row;
            }
        }

        return $posts;
    }

    public function getPostById($id)
    {
        $sql = "SELECT * FROM blog_posts WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result) {
            error_log("Erro na consulta getPostById: " . $this->conn->error);
            return null;
        }

        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }

        return null;
    }

    public function addPost($titulo, $resumo, $conteudo, $autor, $imagem)
    {
        $sql = "INSERT INTO blog_posts (titulo, resumo, conteudo, autor, imagem, data_publicacao) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sssss", $titulo, $resumo, $conteudo, $autor, $imagem);

        if (!$stmt->execute()) {
            error_log("Erro na consulta addPost: " . $stmt->error);
            return false;
        }

        return $this->conn->insert_id;
    }

    public function updatePost($id, $titulo, $resumo, $conteudo, $autor, $imagem)
    {
        if (empty($imagem)) {
            $sql = "UPDATE blog_posts SET titulo = ?, resumo = ?, conteudo = ?, autor = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ssssi", $titulo, $resumo, $conteudo, $autor, $id);
        } else {
            $sql = "UPDATE blog_posts SET titulo = ?, resumo = ?, conteudo = ?, autor = ?, imagem = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("sssssi", $titulo, $resumo, $conteudo, $autor, $imagem, $id);
        }

        if (!$stmt->execute()) {
            error_log("Erro na consulta updatePost: " . $stmt->error);
            return false;
        }

        return true;
    }

    public function deletePost($id)
    {
        $post = $this->getPostById($id);
        if (!$post) {
            error_log("Tentativa de excluir post inexistente ID: " . $id);
            return false;
        }

        $sql = "DELETE FROM blog_posts WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);

        $result = $stmt->execute();

        if (!$result) {
            error_log("Erro na consulta deletePost: " . $stmt->error);
            return false;
        }

        // Tenta remover a imagem física se existir
        if (!empty($post['imagem'])) {
            $imagePaths = [
                '../../uploads/img-blog/' . $post['imagem'],
                '../uploads/img-blog/' . $post['imagem'],
                __DIR__ . '/../../uploads/img-blog/' . $post['imagem'],
                __DIR__ . '/../uploads/img-blog/' . $post['imagem']
            ];
            
            foreach ($imagePaths as $path) {
                if (file_exists($path)) {
                    unlink($path);
                    break;
                }
            }
        }

        return true;
    }
    
    // Método para fechar a conexão quando o objeto for destruído
    public function __destruct() 
    {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}