<?php
header('Content-Type: application/json');

try {
    // Verifica se é uma requisição POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método não permitido", 405);
    }

    // Recebe os dados do corpo da requisição
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON inválido", 400);
    }

    $destination = $data['destination'] ?? '';
    $subtotal = floatval($data['subtotal'] ?? 0);

    // Valores padrão
    $response = [
        'shippingCost' => 100.00,
        'error' => null
    ];

    // Extrai CEP do endereço
    preg_match('/\d{5}-?\d{3}/', $destination, $matches);
    $cep = $matches[0] ?? '';

    if (!empty($cep)) {
        $cep = preg_replace('/\D/', '', $cep);
        
        // Consulta ViaCEP
        $viaCepUrl = "https://viacep.com.br/ws/{$cep}/json/";
        $viaCepResponse = file_get_contents($viaCepUrl);
        
        if ($viaCepResponse) {
            $cepData = json_decode($viaCepResponse, true);
            
            // Frete grátis para SP ou compras acima de R$350
            if (($cepData['uf'] ?? '') === 'SP' || $subtotal >= 350) {
                $response['shippingCost'] = 0.00;
            } else {
                // Lógica de faixas de CEP
                $cepBase = substr($cep, 0, 5);
                
                if ($cepBase >= '08000' && $cepBase <= '08499') {
                    $response['shippingCost'] = 30.00;
                } elseif ($cepBase >= '08500' && $cepBase <= '08999') {
                    $response['shippingCost'] = 45.00;
                } elseif ($cepBase >= '09000' && $cepBase <= '09999') {
                    $response['shippingCost'] = 60.00;
                }
            }
        }
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'error' => $e->getMessage(),
        'shippingCost' => 0.00 // Valor padrão em caso de erro
    ]);
}
?>