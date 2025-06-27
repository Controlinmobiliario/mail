<?php
require_once '../src/services/MailService.php';
require_once '../src/models/Mail.php';

class MailController {
    private $mailService;
    
    public function __construct() {
        $this->mailService = new MailService();
    }
    
    public function send() {
        try {
            $input = $this->getJsonInput();
            
            // Validar datos requeridos
            $required = ['email', 'template_id', 'variables'];
            foreach ($required as $field) {
                if (!isset($input[$field])) {
                    $this->jsonResponse([
                        'error' => true,
                        'message' => "Campo requerido: {$field}"
                    ], 400);
                    return;
                }
            }
            
            $result = $this->mailService->sendMail(
                $input['email'],
                $input['template_id'],
                $input['variables']
            );
            
            if ($result['success']) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Mail enviado correctamente',
                    'mail_id' => $result['mail_id']
                ], 200);
            } else {
                $this->jsonResponse([
                    'error' => true,
                    'message' => $result['message']
                ], 400);
            }
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'error' => true,
                'message' => 'Error al enviar mail: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function sendVerification() {
        try {
            $input = $this->getJsonInput();
            
            $required = ['email', 'name', 'verification_url'];
            foreach ($required as $field) {
                if (!isset($input[$field])) {
                    $this->jsonResponse([
                        'error' => true,
                        'message' => "Campo requerido: {$field}"
                    ], 400);
                    return;
                }
            }
            
            $result = $this->mailService->sendVerificationMail(
                $input['email'],
                $input['name'],
                $input['verification_url']
            );
            
            $this->jsonResponse($result, $result['success'] ? 200 : 400);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'error' => true,
                'message' => 'Error al enviar verificación: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function sendPasswordReset() {
        try {
            $input = $this->getJsonInput();
            
            $required = ['email', 'name', 'reset_url'];
            foreach ($required as $field) {
                if (!isset($input[$field])) {
                    $this->jsonResponse([
                        'error' => true,
                        'message' => "Campo requerido: {$field}"
                    ], 400);
                    return;
                }
            }
            
            $result = $this->mailService->sendPasswordResetMail(
                $input['email'],
                $input['name'],
                $input['reset_url']
            );
            
            $this->jsonResponse($result, $result['success'] ? 200 : 400);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'error' => true,
                'message' => 'Error al enviar reset: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function logs() {
        try {
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 10;
            
            $mail = new Mail();
            $logs = $mail->getLogs($page, $limit);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $logs,
                'pagination' => [
                    'page' => (int)$page,
                    'limit' => (int)$limit
                ]
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'error' => true,
                'message' => 'Error al obtener logs: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function getJsonInput() {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON inválido');
        }
        return $input;
    }
    
    private function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
}
?>
