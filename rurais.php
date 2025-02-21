<?php
file_put_contents('debug.log', print_r($_POST, true) . print_r($_FILES, true));
// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'rural_error.log');

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir arquivo de conexão
require_once 'conectar.php';

// Configurações FTP (mantenha essas em outro arquivo se possível)
$ftp_server = "localhost"r";
//... (recomendo mover para arquivo de configuração separado)

// Processar dados
try {
     // Verifica se há dados enviados
     if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Coleta todos os dados do formulário
    $dados = $_POST;

    // Processa arquivos de fotos
    $fotos = [];
    if (!empty($_FILES['fotos'])) {
        foreach ($_FILES['fotos']['name'] as $key => $name) {
            if ($_FILES['fotos']['error'][$key] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['fotos']['tmp_name'][$key];
                $fotos[] = [
                    'nome' => $name,
                    'tamanho' => $_FILES['fotos']['size'][$key],
                    'tipo' => $_FILES['fotos']['type'][$key],
                    'conteudo' => base64_encode(file_get_contents($tmp_name))
                ];
            }
        }
    }
    $json_str = file_get_contents('php://input');
    if (empty($json_str)) {
        throw new Exception("Nenhum dado recebido");
    }

    $formData = json_decode($json_str, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON inválido: " . json_last_error_msg());
    }

    // Mapeamento de campos
    $campos = [
        'tipo' => 'string',
        'nome_propriedade' => 'string',
        'municipio' => 'string',
        'localizacao_imagem' => 'string',
        'coordenadas_geograficas' => 'string',
        'logistica' => 'string',
        'km_estrada_pavimentada' => 'float',
        'cidade_proxima' => 'string',
        'industriais_ao_redor' => 'array',
        'altitude' => 'string',
        'segmentacao' => 'array',
        'hidrografia' => 'array',
        'topografia' => 'array',
        'topografia_graus' => 'string',
        'tipo_solo' => 'string',
        'como_chegar' => 'string',
        'benfeitorias' => 'array',
        'energia' => 'string',
        'registros_fotograficos_videos' => 'string',
        'documentos' => 'array',
        'preco' => 'float',
        'forma_pagamento' => 'string'
    ];

    $valores = [];
    foreach ($campos as $campo => $tipo) {
        $valor = $formData[$campo] ?? null;
        
        if ($tipo === 'array') {
            $valores[$campo] = is_array($valor) ? implode(', ', array_map([$conn, 'real_escape_string'], $valor)) : '';
        } else {
            $valores[$campo] = $conn->real_escape_string($valor);
            
            if ($tipo === 'float' && $valor !== null) {
                $valores[$campo] = (float)$valores[$campo];
            }
        }
    }

    // Query com prepared statement
    $sql = "INSERT INTO imoveis_rurais (
        tipo, nome_propriedade, municipio, localizacao_imagem, coordenadas_geograficas,
        logistica, km_estrada_pavimentada, cidade_proxima, industriais_ao_redor,
        altitude, segmentacao, hidrografia, topografia, topografia_graus, tipo_solo,
        como_chegar, benfeitorias, energia, registros_fotograficos_videos, documentos,
        preco, forma_pagamento
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar query: " . $conn->error);
    }

    // Tipos de parâmetros
    $tipos = 'ssssssssssssssssssssss';
    $params = [
        $valores['tipo'],
        $valores['nome_propriedade'],
        $valores['municipio'],
        $valores['localizacao_imagem'],
        $valores['coordenadas_geograficas'],
        $valores['logistica'],
        $valores['km_estrada_pavimentada'],
        $valores['cidade_proxima'],
        $valores['industriais_ao_redor'],
        $valores['altitude'],
        $valores['segmentacao'],
        $valores['hidrografia'],
        $valores['topografia'],
        $valores['topografia_graus'],
        $valores['tipo_solo'],
        $valores['como_chegar'],
        $valores['benfeitorias'],
        $valores['energia'],
        $valores['registros_fotograficos_videos'],
        $valores['documentos'],
        $valores['preco'],
        $valores['forma_pagamento']
    ];

    $stmt->bind_param($tipos, ...$params);

    if (!$stmt->execute()) {
        throw new Exception("Erro ao executar query: " . $stmt->error);
    }

    echo json_encode([
        'success' => true,
        'id' => $stmt->insert_id,
        'message' => 'Cadastro realizado com sucesso'
    ]);

} catch (Exception $e) {
    error_log("ERRO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>
