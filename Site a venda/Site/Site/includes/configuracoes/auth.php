<?php

declare(strict_types=1); // Habilita checagem estrita de tipos

/**
 * Funções de autenticação e segurança para o sistema de pagamentos
 *
 * Este arquivo contém funções relacionadas à autenticação de usuários,
 * gerenciamento de sessões, permissões e verificações de segurança.
 */

// Assumindo que a sessão já foi iniciada em outro lugar (ex: session_start();)

/**
 * Obtém o ID do usuário atual logado.
 * Prioriza a sessão, depois cookies/headers.
 *
 * @return int|null ID do usuário ou null se não estiver autenticado.
 */
function getCurrentUserId(): ?int
{
    // 1. Verificar se o usuário está logado na sessão PHP
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        // Garante que o ID seja um inteiro
        if (filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT)) {
            return (int)$_SESSION['user_id'];
        }
    }

    // 2. Verificar token de autenticação nos cookies
    $auth_token = $_COOKIE['auth_token'] ?? null;

    // 3. Se não houver cookie, verificar token no cabeçalho HTTP
    if (!$auth_token) {
        $auth_token = getAuthTokenFromHeader();
    }

    // 4. Validar o token (se encontrado) e obter o ID do usuário
    if ($auth_token) {
        $userId = getUserIdFromToken($auth_token);
        if ($userId !== null) {
            // Opcional: Atualizar a sessão PHP se autenticado via token
            // $_SESSION['user_id'] = $userId;
            return $userId;
        }
    }

    // Não autenticado
    return null;
}

/**
 * Obtém o token de autenticação do cabeçalho HTTP Authorization (Bearer).
 *
 * @return string|null Token de autenticação ou null se não encontrado/formato inválido.
 */
function getAuthTokenFromHeader(): ?string
{
    // Verifica se a função getallheaders está disponível
    if (!function_exists('getallheaders')) {
        // Polyfill/Alternativa para ambientes onde getallheaders não funciona (ex: Nginx com FastCGI)
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            }
        }
    } else {
        $headers = getallheaders();
    }

    // Procura pelo cabeçalho Authorization
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? null; // Verifica ambas as capitalizações

    if ($auth_header !== null) {
        // Verifica se o formato é "Bearer [token]"
        if (preg_match('/^Bearer\s+(.+)$/i', $auth_header, $matches)) {
            // Retorna apenas o token
            return trim($matches[1]);
        }
    }

    return null;
}

/**
 * Obtém o ID do usuário a partir de um token de autenticação, validando-o no banco.
 * Atualiza o tempo de expiração da sessão se o token for válido.
 *
 * @param string $token O token de autenticação a ser validado.
 * @return int|null ID do usuário ou null se o token for inválido ou expirado.
 */
function getUserIdFromToken(string $token): ?int
{
    // Validação básica do token (não pode ser vazio)
    if (empty($token)) {
        return null;
    }

    try {
        // Assume que getDbConnection() retorna uma instância PDO válida
        $db = getDbConnection();

        // Prepara a consulta para buscar a sessão ativa pelo token
        $stmt = $db->prepare("
            SELECT user_id
            FROM user_sessions
            WHERE token = :token AND expires_at > NOW()
        ");
        $stmt->bindParam(':token', $token, PDO::PARAM_STR);
        $stmt->execute();

        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session && isset($session['user_id'])) {
            // Token válido e não expirado, estender a sessão (atualizar expires_at)
            $update_stmt = $db->prepare("
                UPDATE user_sessions
                SET expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) -- Pode ser configurável
                WHERE token = :token
            ");
            $update_stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $update_stmt->execute();

            // Retorna o ID do usuário como inteiro
            return (int)$session['user_id'];
        }
    } catch (PDOException $e) {
        // Em um ambiente real, logar o erro em vez de exibi-lo
        error_log("Erro ao validar token: " . $e->getMessage());
        // Considerar lançar uma exceção ou retornar null dependendo da política de erros
        return null;
    }

    // Token inválido, expirado ou erro no DB
    return null;
}

/**
 * Verifica se um usuário específico tem permissão para acessar um determinado pedido.
 *
 * @param int $user_id ID do usuário.
 * @param int $order_id ID do pedido.
 * @return bool True se o usuário tiver permissão, False caso contrário.
 */
function userCanAccessOrder(int $user_id, int $order_id): bool
{
    // IDs devem ser positivos
    if ($user_id <= 0 || $order_id <= 0) {
        return false;
    }

    try {
        $db = getDbConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM orders -- Usa a tabela 'orders' existente no dump
            WHERE id = :order_id AND user_id = :user_id
        ");
        $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        // fetchColumn() retorna o valor da primeira coluna (COUNT(*)) ou false se não houver linhas
        $count = $stmt->fetchColumn();
        return $count !== false && $count > 0;
    } catch (PDOException $e) {
        error_log("Erro ao verificar acesso ao pedido: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica se um cartão salvo pertence a um usuário específico.
 *
 * @param int $user_id ID do usuário.
 * @param int $card_id ID do cartão salvo (na tabela `saved_cards`).
 * @return bool True se o cartão pertencer ao usuário, False caso contrário.
 */
function verifyCardOwnership(int $user_id, int $card_id): bool
{
    if ($user_id <= 0 || $card_id <= 0) {
        return false;
    }

    try {
        $db = getDbConnection();
        // Usa a tabela 'saved_cards' criada a partir do SQL gerado
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM saved_cards
            WHERE id = :card_id AND user_id = :user_id
        ");
        $stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $count = $stmt->fetchColumn();
        return $count !== false && $count > 0;
    } catch (PDOException $e) {
        error_log("Erro ao verificar propriedade do cartão: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém o token de um cartão salvo (geralmente fornecido pelo gateway de pagamento).
 *
 * @param int $card_id ID do cartão salvo (na tabela `saved_cards`).
 * @return string|null Token do cartão ou null se não encontrado ou erro.
 */
function getSavedCardToken(int $card_id): ?string
{
    if ($card_id <= 0) {
        return null;
    }

    try {
        $db = getDbConnection();
        // Usa a tabela 'saved_cards'
        $stmt = $db->prepare("
            SELECT card_token
            FROM saved_cards
            WHERE id = :card_id
        ");
        $stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchColumn();
        // fetchColumn retorna false se não encontrar, convertemos para null
        return ($result !== false) ? (string)$result : null;
    } catch (PDOException $e) {
        error_log("Erro ao obter token do cartão salvo: " . $e->getMessage());
        return null;
    }
}

/**
 * Salva as informações de um cartão para uso futuro do usuário.
 *
 * @param int $user_id ID do usuário.
 * @param string $card_token Token do cartão fornecido pelo gateway de pagamento.
 * @param string $last_4_digits Últimos 4 dígitos do cartão.
 * @param string $cardholder_name Nome do titular do cartão.
 * @param string $card_brand Bandeira do cartão (ex: 'visa', 'mastercard').
 * @return int|false ID do cartão salvo em caso de sucesso, False em caso de erro.
 */
function saveCardForUser(int $user_id, string $card_token, string $last_4_digits, string $cardholder_name, string $card_brand): int|false
{
    if ($user_id <= 0 || empty($card_token) || !preg_match('/^\d{4}$/', $last_4_digits)) {
        // Validação básica dos parâmetros
        return false;
    }

    try {
        $db = getDbConnection();
        // Usa a tabela 'saved_cards'
        $stmt = $db->prepare("
            INSERT INTO saved_cards
                (user_id, card_token, last_4_digits, cardholder_name, card_brand, created_at)
            VALUES
                (:user_id, :card_token, :last_4_digits, :cardholder_name, :card_brand, NOW())
        ");

        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':card_token', $card_token, PDO::PARAM_STR);
        $stmt->bindParam(':last_4_digits', $last_4_digits, PDO::PARAM_STR);
        $stmt->bindParam(':cardholder_name', $cardholder_name, PDO::PARAM_STR);
        $stmt->bindParam(':card_brand', $card_brand, PDO::PARAM_STR);

        $success = $stmt->execute();

        // Retorna o ID inserido ou false
        return $success ? (int)$db->lastInsertId() : false;
    } catch (PDOException $e) {
        error_log("Erro ao salvar cartão para usuário: " . $e->getMessage());
        // Verificar violação de constraint única (ex: mesmo token/cartão já salvo?)
        return false;
    }
}

/**
 * Adiciona uma camada extra de segurança para transações sensíveis,
 * verificando se o IP atual do usuário corresponde a um IP de login recente.
 *
 * @param int $user_id ID do usuário.
 * @param string $transaction_type Descrição do tipo de transação (para logging).
 * @return bool True se a verificação de segurança passar, False caso contrário.
 */
function validateSecureTransaction(int $user_id, string $transaction_type): bool
{
    // Obter IP atual do usuário (considerar proxies)
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? null;
    if (!$current_ip) {
        logSecurityEvent($user_id, 'missing_ip', $transaction_type);
        return false; // Não podemos validar sem IP
    }

    if ($user_id <= 0) {
        return false; // ID de usuário inválido
    }

    try {
        $db = getDbConnection();
        // Usa a tabela 'user_activity'
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM user_activity
            WHERE user_id = :user_id
              AND ip_address = :ip_address
              AND activity_type = 'login' -- Considera apenas IPs de logins bem-sucedidos
              AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) -- Verifica últimas 24 horas
        ");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':ip_address', $current_ip, PDO::PARAM_STR);
        $stmt->execute();

        $count = $stmt->fetchColumn();

        if ($count === false || $count == 0) {
            // IP não coincide com logins recentes ou erro na consulta
            logSecurityEvent($user_id, 'ip_mismatch', "Transação: $transaction_type | IP: $current_ip");
            return false; // Considera a transação insegura
        }

        // IP corresponde a um login recente
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao validar transação segura: " . $e->getMessage());
        // Logar o erro e talvez negar a transação por precaução
        logSecurityEvent($user_id, 'validation_error', "Transação: $transaction_type | Erro: DB Exception");
        return false;
    }
}

/**
 * Registra um evento de segurança no banco de dados para análise posterior.
 *
 * @param int|null $user_id ID do usuário associado ao evento (pode ser null).
 * @param string $event_type Tipo do evento (ex: 'ip_mismatch', 'failed_payment').
 * @param string $context Informações adicionais sobre o evento.
 * @return void
 */
function logSecurityEvent(?int $user_id, string $event_type, string $context): void
{
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

    // Validações básicas
    if (empty($event_type)) {
        error_log("Tentativa de logar evento de segurança sem event_type.");
        return;
    }

    try {
        $db = getDbConnection();
        // Usa a tabela 'security_events'
        $stmt = $db->prepare("
            INSERT INTO security_events
                (user_id, event_type, context, ip_address, created_at)
            VALUES
                (:user_id, :event_type, :context, :ip_address, NOW())
        ");

        // Bind como NULL se user_id for null ou <= 0
        $userIdParam = ($user_id !== null && $user_id > 0) ? $user_id : null;

        $stmt->bindParam(':user_id', $userIdParam, $userIdParam === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':event_type', $event_type, PDO::PARAM_STR);
        $stmt->bindParam(':context', $context, PDO::PARAM_STR);
        $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);

        $stmt->execute();
    } catch (PDOException $e) {
        // Falha ao logar o evento - logar em arquivo como fallback
        error_log("CRÍTICO: Falha ao registrar evento de segurança no DB! User: $user_id, Evento: $event_type, Contexto: $context, IP: $ip_address. Erro DB: " . $e->getMessage());
    }
}

/**
 * Placeholder para a função de conexão com o banco de dados.
 * Substitua pelo seu método real de conexão (ex: usando PDO).
 *
 * @return PDO Retorna uma instância de PDO conectada ao banco.
 * @throws PDOException Se a conexão falhar.
 */
function getDbConnection(): PDO
{
    // Exemplo de conexão PDO - Substitua com suas credenciais reais
    $host = 'localhost';
    $dbname = 'admin_panel'; // <- Coloque o nome do seu banco aqui
    $username = 'root';     // <- Seu usuário do DB
    $password = '';       // <- Sua senha do DB
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lança exceções em erros
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna arrays associativos
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Usa prepared statements nativos
    ];

    static $pdo = null; // Conexão estática para reutilização (Singleton simples)
    if ($pdo === null) {
        try {
            $pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            // Em produção, logar o erro e talvez mostrar uma página de erro genérica
            error_log('Erro de Conexão com Banco de Dados: ' . $e->getMessage());
            throw new PDOException("Não foi possível conectar ao banco de dados.", (int)$e->getCode());
        }
    }
    return $pdo;
}
