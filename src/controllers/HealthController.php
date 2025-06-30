<?php
require_once '../src/config/database.php';

class HealthController {
    
    public function index() {
        $this->jsonResponse([
            'message' => '🚀 Mail Service API está funcionando!',
            'version' => '1.0.0',
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoints' => [
                'GET /health' => 'Estado del servicio',
                'GET /templates' => 'Listar plantillas',
                'POST /mail/send' => 'Enviar mail',
                'POST /mail/verification' => 'Mail de verificación',
                'GET /mail/logs' => 'Logs de envío'
            ]
        ]);
    }
    
    public function check() {
        $checks = [
            'api' => true,
            'database' => $this->checkDatabase(),
            'mail_config' => $this->checkMailConfig(),
            'templates_dir' => $this->checkTemplatesDirectory()
        ];
        
        $allHealthy = !in_array(false, $checks);
        
        http_response_code($allHealthy ? 200 : 503);
        
        $this->jsonResponse([
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function checkDatabase() {
        try {
            $db = Database::getInstance();
            return $db->testConnection();
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function checkMailConfig() {
        $required = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS'];
        foreach ($required as $env) {
            if (empty($_ENV[$env])) {
                return false;
            }
        }
        return true;
    }
    
    private function checkTemplatesDirectory() {
        return is_dir('../templates/email') && is_readable('../templates/email');
    }
    
    private function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
}
?>
