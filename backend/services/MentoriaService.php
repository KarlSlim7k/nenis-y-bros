<?php
/**
 * MentoriaService
 * 
 * Servicio para integración con Groq API (compatible con OpenAI)
 * Maneja la lógica de conversación con el modelo de IA para mentoría empresarial
 * 
 * @author Nenis y Bros
 * @version 1.0.0
 */

class MentoriaService {
    private $apiKey;
    private $apiUrl;
    private $model;
    private $maxTokens;
    private $temperature;
    
    /**
     * Constructor - Carga configuración desde .env
     */
    public function __construct() {
        $this->apiKey = $_ENV['GROQ_API_KEY'] ?? '';
        $this->apiUrl = $_ENV['GROQ_API_URL'] ?? 'https://api.groq.com/openai/v1/chat/completions';
        $this->model = $_ENV['GROQ_MODEL'] ?? 'llama3-8b-8192';
        $this->maxTokens = (int)($_ENV['GROQ_MAX_TOKENS'] ?? 1024);
        $this->temperature = (float)($_ENV['GROQ_TEMPERATURE'] ?? 0.7);
        
        if (empty($this->apiKey)) {
            Logger::error('GROQ_API_KEY no está configurada en .env');
        }
    }
    
    /**
     * Obtener respuesta del modelo de IA basado en historial de conversación
     * 
     * @param array $mensajes Array de mensajes con formato: [['role' => 'user|assistant', 'content' => 'texto'], ...]
     * @param string|null $contextoEmpresarial Información adicional del perfil empresarial del usuario
     * @return array ['success' => bool, 'response' => string, 'tokens_used' => int, 'error' => string]
     */
    public function obtenerRespuesta($mensajes, $contextoEmpresarial = null) {
        try {
            // Validar API key
            if (empty($this->apiKey)) {
                return [
                    'success' => false,
                    'error' => 'API key no configurada'
                ];
            }
            
            // Construir prompt del sistema con contexto de mentoría empresarial
            $systemPrompt = $this->construirSystemPrompt($contextoEmpresarial);
            
            // Preparar mensajes con el system prompt al inicio
            $chatMessages = [
                ['role' => 'system', 'content' => $systemPrompt]
            ];
            
            // Agregar historial de mensajes (limitado a últimos 10 para no exceder tokens)
            $mensajesRecientes = array_slice($mensajes, -10);
            foreach ($mensajesRecientes as $msg) {
                $chatMessages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content']
                ];
            }
            
            // Preparar payload para la API
            $payload = [
                'model' => $this->model,
                'messages' => $chatMessages,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'top_p' => 1,
                'stream' => false
            ];
            
            // Realizar llamada a la API
            $response = $this->callGroqAPI($payload);
            
            if (!$response['success']) {
                return $response;
            }
            
            // Extraer respuesta del modelo
            $data = $response['data'];
            
            if (isset($data['choices'][0]['message']['content'])) {
                return [
                    'success' => true,
                    'response' => $data['choices'][0]['message']['content'],
                    'tokens_used' => $data['usage']['total_tokens'] ?? 0,
                    'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'unknown'
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Formato de respuesta inválido de la API'
            ];
            
        } catch (Exception $e) {
            Logger::error('Error en MentoriaService::obtenerRespuesta: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error interno al procesar la solicitud: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Construir el prompt del sistema con contexto empresarial
     * 
     * @param string|null $contextoEmpresarial
     * @return string
     */
    private function construirSystemPrompt($contextoEmpresarial = null) {
        $basePrompt = "Eres MentorIA, un asistente virtual experto en formación empresarial y desarrollo de negocios. " .
                      "Tu objetivo es ayudar a emprendedores y empresarios a mejorar sus habilidades, resolver problemas " .
                      "y tomar decisiones informadas sobre sus negocios.\n\n" .
                      "INSTRUCCIONES:\n" .
                      "- Responde de manera profesional, clara y concisa\n" .
                      "- Proporciona ejemplos prácticos cuando sea relevante\n" .
                      "- Si no conoces la respuesta, admítelo y ofrece alternativas\n" .
                      "- Haz preguntas de seguimiento para entender mejor el contexto\n" .
                      "- Enfócate en soluciones prácticas y accionables\n" .
                      "- Usa un tono motivador pero realista\n" .
                      "- Mantén las respuestas en español\n";
        
        if ($contextoEmpresarial) {
            $basePrompt .= "\nCONTEXTO DEL USUARIO:\n" . $contextoEmpresarial . "\n";
        }
        
        return $basePrompt;
    }
    
    /**
     * Realizar llamada HTTP a Groq API
     * 
     * @param array $payload
     * @return array ['success' => bool, 'data' => array, 'error' => string]
     */
    private function callGroqAPI($payload) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Manejar errores de cURL
        if ($curlError) {
            Logger::error('Error cURL en MentoriaService: ' . $curlError);
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $curlError
            ];
        }
        
        // Decodificar respuesta JSON
        $data = json_decode($response, true);
        
        if ($httpCode !== 200) {
            $errorMsg = isset($data['error']['message']) ? $data['error']['message'] : 'Error desconocido';
            Logger::error("Error de API Groq (HTTP $httpCode): " . $errorMsg);
            return [
                'success' => false,
                'error' => "Error de la API ($httpCode): $errorMsg"
            ];
        }
        
        return [
            'success' => true,
            'data' => $data
        ];
    }
    
    /**
     * Generar sugerencia de tema basado en el perfil del usuario
     * 
     * @param string $contextoEmpresarial
     * @return array ['success' => bool, 'sugerencias' => array, 'error' => string]
     */
    public function generarSugerencias($contextoEmpresarial) {
        $prompt = "Basado en el siguiente perfil empresarial, sugiere 3 temas específicos sobre los que el usuario " .
                  "podría necesitar mentoría o asesoramiento. Responde SOLO con una lista numerada, sin introducción:\n\n" .
                  $contextoEmpresarial;
        
        $mensajes = [
            ['role' => 'user', 'content' => $prompt]
        ];
        
        $response = $this->obtenerRespuesta($mensajes);
        
        if ($response['success']) {
            // Parsear la respuesta para extraer las sugerencias
            $texto = $response['response'];
            $lineas = explode("\n", $texto);
            $sugerencias = [];
            
            foreach ($lineas as $linea) {
                $linea = trim($linea);
                // Buscar líneas que empiecen con número seguido de punto o paréntesis
                if (preg_match('/^\d+[\.\)]\s*(.+)$/', $linea, $matches)) {
                    $sugerencias[] = trim($matches[1]);
                }
            }
            
            return [
                'success' => true,
                'sugerencias' => !empty($sugerencias) ? $sugerencias : [$texto]
            ];
        }
        
        return $response;
    }
    
    /**
     * Validar salud del servicio (verificar que la API key funciona)
     * 
     * @return array ['success' => bool, 'message' => string]
     */
    public function healthCheck() {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'API key no configurada'
            ];
        }
        
        // Hacer una llamada simple para verificar conectividad
        $testPayload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => 'Hola']
            ],
            'max_tokens' => 10
        ];
        
        $response = $this->callGroqAPI($testPayload);
        
        if ($response['success']) {
            return [
                'success' => true,
                'message' => 'Servicio de MentorIA operativo',
                'model' => $this->model
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Error al conectar con la API: ' . $response['error']
        ];
    }
}
