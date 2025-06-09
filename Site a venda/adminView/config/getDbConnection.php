<?php
/**
 * Função para obter conexão com banco de dados para o sistema de pagamentos
 * 
 * Este arquivo implementa a função getDbConnection() utilizada pelos módulos de 
 * autenticação e processamento de pedidos
 */

/**
 * Obtém uma conexão PDO com o banco de dados
 * 
 * Esta função retorna uma conexão já existente ou cria uma nova
 * mantendo compatibilidade com a configuração atual do sistema
 * 
 * @return PDO Objeto de conexão PDO
 */
function getDbConnection() {
    global $pdo;
    
    // Se já existe uma conexão PDO global, retorna ela
    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo;
    }
    
    // Configurações do banco
    $usuario = 'goldlar_2025';
    $senha = 'FNvVuWRZ#1';
    $banco = 'goldlar_2025';
    $servidor = 'goldlar_2025.mysql.dbaas.com.br';
    
    try {
        // Criar nova conexão com as mesmas configurações do sistema
        $connection = new PDO("mysql:dbname=$banco;host=$servidor;charset=utf8", $usuario, $senha);
        
        // Configurar opções do PDO
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        return $connection;
    } catch (PDOException $e) {
        // Registrar erro no log do sistema
        error_log("Erro de conexão com o banco de dados: " . $e->getMessage());
        
        // Relançar exceção para tratamento adequado pelo sistema
        throw new PDOException("Erro de conexão com o banco de dados: " . $e->getMessage());
    }
}

/**
 * Executa uma transação segura no banco de dados
 * 
 * @param callable $callback Função a ser executada dentro da transação
 * @return mixed Resultado da função callback ou false em caso de erro
 */
function executeDbTransaction($callback) {
    $db = getDbConnection();
    
    try {
        // Iniciar transação
        $db->beginTransaction();
        
        // Executar o callback
        $result = $callback($db);
        
        // Commit da transação
        $db->commit();
        
        return $result;
    } catch (Exception $e) {
        // Rollback em caso de erro
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log("Erro na transação: " . $e->getMessage());
        return false;
    }
}