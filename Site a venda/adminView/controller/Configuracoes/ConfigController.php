<?php
// ConfigController.php
require_once __DIR__ . '/../../models/Configuracoes/Config.php';

class ConfigController
{
    private $config;

    public function __construct($jsonPath)
    {
        $this->config = new Config($jsonPath);
    }

    public function salvarJson($jsonData)
    {
        try {
            file_put_contents($this->config->getJsonPath(), $jsonData);
            return "JSON salvo com sucesso!";
        } catch (Exception $e) {
            return "Erro: " . $e->getMessage();
        }
    }

    public function getConfig()
    {
        return $this->config->getJsonData();
    }
}
?>