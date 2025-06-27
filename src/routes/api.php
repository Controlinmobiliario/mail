<?php
require_once '../src/controllers/MailController.php';
require_once '../src/controllers/TemplateController.php';
require_once '../src/controllers/HealthController.php';

class ApiRouter {
    private $routes = [];
    
    public function __construct() {
        $this->defineRoutes();
    }
    
    private function defineRoutes() {
        // Health check
        $this->routes['GET']['/health'] = ['HealthController', 'check'];
        $this->routes['GET']['/'] = ['HealthController', 'index'];
        
        // Gestión de plantillas
        $this->routes['GET']['/templates'] = ['TemplateController', 'index'];
        $this->routes['GET']['/templates/{id}'] = ['TemplateController', 'show'];
        $this->routes['POST']['/templates'] = ['TemplateController', 'store'];
        $this->routes['PUT']['/templates/{id}'] = ['TemplateController', 'update'];
        $this->routes['DELETE']['/templates/{id}'] = ['TemplateController', 'destroy'];
        
        // Envío de mails
        $this->routes['POST']['/mail/send'] = ['MailController', 'send'];
        $this->routes['POST']['/mail/verification'] = ['MailController', 'sendVerification'];
        $this->routes['POST']['/mail/password-reset'] = ['MailController', 'sendPasswordReset'];
        $this->routes['POST']['/mail/login-alert'] = ['MailController', 'sendLoginAlert'];
        
        // Logs de mail
        $this->routes['GET']['/mail/logs'] = ['MailController', 'logs'];
        $this->routes['GET']['/mail/logs/{id}'] = ['MailController', 'logDetail'];
    }
    
    public function handleRequest($uri, $method) {
        $uri = rtrim($uri, '/');
        if (empty($uri)) $uri = '/';
        
        // Buscar ruta exacta
        if (isset($this->routes[$method][$uri])) {
            $this->executeController($this->routes[$method][$uri], []);
            return;
        }
        
        // Buscar ruta con parámetros
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $pattern = preg_replace('/{([^}]+)}/', '([^/]+)', $route);
            $pattern = '#^' . $pattern . '$#';
            
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // Remover el match completo
                $this->executeController($handler, $matches);
                return;
            }
        }
        
        // Ruta no encontrada
        http_response_code(404);
        echo json_encode([
            'error' => true,
            'message' => 'Ruta no encontrada',
            'requested_uri' => $uri,
            'method' => $method
        ]);
    }
    
    private function executeController($handler, $params) {
        [$controllerName, $method] = $handler;
        
        if (!class_exists($controllerName)) {
            throw new Exception("Controlador {$controllerName} no encontrado");
        }
        
        $controller = new $controllerName();
        
        if (!method_exists($controller, $method)) {
            throw new Exception("Método {$method} no encontrado en {$controllerName}");
        }
        
        call_user_func_array([$controller, $method], $params);
    }
}
?>
