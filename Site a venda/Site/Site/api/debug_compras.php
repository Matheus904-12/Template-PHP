<?php
debugLog("Arquivo cart_operations.php (ou favorites_operations.php) acessado.");
printPostData();
printGetData();
printSessionData();
function debugLog($message) {
    $logFile = 'debug_compras.log'; // Nome do arquivo de log
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function printPostData() {
    debugLog("Dados POST recebidos:");
    if (!empty($_POST)) {
        foreach ($_POST as $key => $value) {
            debugLog("  {$key}: {$value}");
        }
    } else {
        debugLog("  Nenhum dado POST recebido.");
    }
}

function printGetData() {
    debugLog("Dados GET recebidos:");
    if (!empty($_GET)) {
        foreach ($_GET as $key => $value) {
            debugLog("  {$key}: {$value}");
        }
    } else {
        debugLog("  Nenhum dado GET recebido.");
    }
}

function printSessionData() {
    debugLog("Dados da sessão:");
    if (!empty($_SESSION)) {
        foreach ($_SESSION as $key => $value) {
            debugLog("  {$key}: {$value}");
        }
    } else {
        debugLog("  Nenhum dado de sessão.");
    }
}

function printSqlError($stmt) {
    if ($stmt && $stmt->error) {
        debugLog("Erro SQL: " . $stmt->error);
    }
}

function printAffectedRows($stmt) {
    if ($stmt) {
        debugLog("Linhas afetadas: " . $stmt->affected_rows);
    }
}

?>