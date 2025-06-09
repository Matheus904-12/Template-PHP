<?php
// GaleriaController.php

// Verifica se o arquivo do modelo existe
$modelPath = __DIR__ . '/../../models/Galeria/GaleriaModel.php';
if (!file_exists($modelPath)) {
    throw new RuntimeException("Arquivo do modelo GaleriaModel.php não encontrado em: " . $modelPath);
}

require_once($modelPath);

class GaleriaController {
    private $model;

    public function __construct() {
        try {
            $this->model = new GaleriaModel();
        } catch (Exception $e) {
            error_log("Erro ao instanciar GaleriaModel: " . $e->getMessage());
            throw new RuntimeException("Erro ao inicializar o controlador da galeria");
        }
    }

    public function exibirGaleria() {
        try {
            return $this->model->getGaleria();
        } catch (Exception $e) {
            error_log("Erro ao obter galeria: " . $e->getMessage());
            return []; // Retorna array vazio em caso de erro
        }
    }

    public function gerarGaleriaJson() {
        try {
            $imagens = $this->exibirGaleria();

            if (empty($imagens)) {
                return ["status" => "error", "message" => "Nenhuma imagem encontrada."];
            }

            $jsonData = json_encode($imagens, JSON_PRETTY_PRINT);
            $jsonFilePath = __DIR__ . '/../../../galeria.json';

            if (file_put_contents($jsonFilePath, $jsonData) === false) {
                return ["status" => "error", "message" => "Erro ao salvar arquivo JSON."];
            }

            return ["status" => "success", "message" => "Arquivo JSON gerado com sucesso!"];
        } catch (Exception $e) {
            error_log("Erro ao gerar JSON da galeria: " . $e->getMessage());
            return ["status" => "error", "message" => "Erro ao processar galeria."];
        }
    }
}
