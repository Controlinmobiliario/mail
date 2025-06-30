require_once '../src/models/Template.php';
require_once '../src/config/database.php';

class TemplateService {
    private $templateModel;
    
    public function __construct() {
        $this->templateModel = new Template();
    }
    
    /**
     * Obtener todas las plantillas con paginación
     */
    public function getTemplates($page = 1, $limit = 10, $type = null) {
        return $this->templateModel->getAll($page, $limit, $type);
    }
    
    /**
     * Obtener una plantilla específica
     */
    public function getTemplate($id) {
        $template = $this->templateModel->getById($id);
        if ($template && $template['variables']) {
            $template['variables'] = json_decode($template['variables'], true);
        }
        return $template;
    }
    
    /**
     * Crear nueva plantilla
     */
    public function createTemplate($data) {
        return $this->templateModel->create($data);
    }
    
    /**
     * Actualizar plantilla
     */
    public function updateTemplate($id, $data) {
        return $this->templateModel->update($id, $data);
    }
    
    /**
     * Eliminar plantilla
     */
    public function deleteTemplate($id) {
        return $this->templateModel->delete($id);
    }
    
    /**
     * Verificar si una plantilla está en uso
     */
    public function isTemplateInUse($id) {
        return $this->templateModel->isInUse($id);
    }
    
    /**
     * Extraer variables de una plantilla
     * Busca patrones como {{variable}} o {variable}
     */
    public function extractVariables($content) {
        $variables = [];
        
        // Extraer variables del contenido
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $variable) {
                $variable = trim($variable);
                if (!in_array($variable, $variables)) {
                    $variables[] = $variable;
                }
            }
        }
        
        // También buscar variables con una sola llave
        preg_match_all('/\{([^}]+)\}/', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $variable) {
                $variable = trim($variable);
                if (!in_array($variable, $variables)) {
                    $variables[] = $variable;
                }
            }
        }
        
        return $variables;
    }
    
    /**
     * Renderizar plantilla con variables
     */
    public function renderTemplate($template, $variables = []) {
        $subject = $template['subject'];
        $content = $template['content'];
        
        // Obtener variables requeridas
        $requiredVariables = json_decode($template['variables'] ?? '[]', true);
        $missingVariables = [];
        $usedVariables = [];
        
        // Reemplazar variables en el subject
        foreach ($requiredVariables as $variable) {
            $patterns = [
                '{{' . $variable . '}}',
                '{' . $variable . '}'
            ];
            
            foreach ($patterns as $pattern) {
                if (strpos($subject, $pattern) !== false) {
                    if (isset($variables[$variable])) {
                        $subject = str_replace($pattern, $variables[$variable], $subject);
                        $usedVariables[] = $variable;
                    } else {
                        $missingVariables[] = $variable;
                    }
                }
                
                if (strpos($content, $pattern) !== false) {
                    if (isset($variables[$variable])) {
                        $content = str_replace($pattern, $variables[$variable], $content);
                        if (!in_array($variable, $usedVariables)) {
                            $usedVariables[] = $variable;
                        }
                    } else {
                        if (!in_array($variable, $missingVariables)) {
                            $missingVariables[] = $variable;
                        }
                    }
                }
            }
        }
        
        // Aplicar layout base si es HTML
        if ($this->isHtmlContent($content)) {
            $content = $this->applyBaseLayout($content);
        }
        
        return [
            'subject' => $subject,
            'content' => $content,
            'variables_used' => array_unique($usedVariables),
            'missing_variables' => array_unique($missingVariables)
        ];
    }
    
    /**
     * Verificar si el contenido es HTML
     */
    private function isHtmlContent($content) {
        return strpos($content, '<') !== false && strpos($content, '>') !== false;
    }
    
    /**
     * Aplicar layout base a la plantilla
     */
    private function applyBaseLayout($content) {
        $layoutPath = '../templates/layouts/base.html';
        
        if (!file_exists($layoutPath)) {
            return $content;
        }
        
        $layout = file_get_contents($layoutPath);
        
        // Reemplazar el contenido en el layout
        $layout = str_replace('{{content}}', $content, $layout);
        $layout = str_replace('{content}', $content, $layout);
        
        return $layout;
    }
    
    /**
     * Obtener plantilla por tipo
     */
    public function getTemplateByType($type) {
        return $this->templateModel->getByType($type);
    }
    
    /**
     * Validar plantilla antes de guardar
     */
    public function validateTemplate($data) {
        $errors = [];
        
        // Validar nombre
        if (empty($data['name'])) {
            $errors[] = 'El nombre es requerido';
        }
        
        // Validar tipo
        $validTypes = ['verification', 'password_reset', 'login_alert', 'welcome', 'custom'];
        if (empty($data['type']) || !in_array($data['type'], $validTypes)) {
            $errors[] = 'Tipo de plantilla inválido';
        }
        
        // Validar subject
        if (empty($data['subject'])) {
            $errors[] = 'El asunto es requerido';
        }
        
        // Validar contenido
        if (empty($data['content'])) {
            $errors[] = 'El contenido es requerido';
        }
        
        // Validar variables en el contenido
        if (!empty($data['content'])) {
            $variables = $this->extractVariables($data['content']);
            if (empty($variables)) {
                $errors[] = 'La plantilla debe contener al menos una variable';
            }
        }
        
        return $errors;
    }
    
    /**
     * Clonar plantilla
     */
    public function cloneTemplate($id, $newName) {
        $template = $this->getTemplate($id);
        if (!$template) {
            throw new Exception('Plantilla no encontrada');
        }
        
        unset($template['id']);
        $template['name'] = $newName;
        $template['created_at'] = date('Y-m-d H:i:s');
        
        return $this->createTemplate($template);
    }
}
