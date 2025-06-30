class TemplateController {
    private $templateService;
    
    public function __construct() {
        $this->templateService = new TemplateService();
    }
    
    /**
     * GET /api/templates - Listar todas las plantillas
     */
    public function index() {
        try {
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 10;
            $type = $_GET['type'] ?? null; // 'email', 'sms', etc.
            
            $templates = $this->templateService->getTemplates($page, $limit, $type);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $templates,
                'pagination' => [
                    'page' => (int)$page,
                    'limit' => (int)$limit
                ]
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'error' => true,
                'message' => 'Error al obtener plantillas: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * GET /api/templates/{id} - Obtener plantilla específica
     */
    public function show($id) {
        try {
            $template = $this->templateService->getTemplate($id);
            
            if (!$template) {
                $this->jsonResponse([
                    'error' => true,
                    'message' => 'Plantilla no encontrada'
                ], 404);
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $template
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'error' => true,
                'message' => 'Error al obtener plantilla: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /api/templates - Crear nueva plantilla
     */
    public function store() {
        try {
            $input = $this->getJsonInput();
            
            // Validar datos requeridos
            $required = ['name', 'type', 'subject', 'content'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    $this->jsonResponse([
                        'error' => true,
                        'message' => "Campo requerido: {$field}"
                    ], 400);
                    return;
                }
            }
            
            // Validar tipo de plantilla
            $validTypes = ['verification', 'password_reset', 'login_alert', 'welcome', 'custom'];
            if (!in_array($input['type'], $validTypes)) {
                $this->jsonResponse([
                    'error' => true,
                    'message' => 'Tipo de plantilla inválido. Tipos válidos: ' . implode(', ', $validTypes)
                ], 400);
                return;
            }
            
            // Validar variables de plantilla
            $variables = $this->templateService->extractVariables($input['content']);
            
            $templateData = [
                'name' => $input['name'],
                'type' => $input['type'],
                'subject' => $input['subject'],
                'content' => $input['content'],
                'variables' => json_encode($variables),
                'description' => $input['description'] ?? '',
                'is_active' => $input['is_active'] ?? 1
            ];
            
            $templateId = $this->templateService->createTemplate($templateData);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Plantilla creada correctamente',
                'data' => [
                    'id' => $templateId,
                    'variables' => $variables
                ]
            ], 201);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'error' => true,
                'message' => 'Error al crear plantilla: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * PUT /api/templates/{id} - Actualizar plantilla
     */
    public function update($id) {
        try {
            $input = $this->getJsonInput();
            
            // Verificar que la plantilla existe
            $existingTemplate = $this->templateService->getTemplate($id);
            if (!$existingTemplate) {
                $this->jsonResponse([
                    'error' => true,
                    'message' => 'Plantilla no encontrada'
                ], 404);
                return;
            }
            
            // Validar variables si se actualiza el contenido
            if (isset($input['content'])) {
                $variables = $this->templateService->extractVariables($input['content']);
                $input['variables'] = json_encode($variables);
            }
            
            $success = $this->templateService->updateTemplate($id, $input);
            
            if ($success) {
                $updatedTemplate = $this->templateService->getTemplate($id);
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Plantilla actualizada correctamente',
                    'data' => $updatedTemplate
                ]);
            } else {
                $this->jsonResponse([
                    'error' => true,
                    'message' => 'No se pudo actualizar la plantilla'
                ], 400);
            }
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'error' => true,
                'message' => 'Error al actualizar plantilla: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * DELETE /api/templates/{id} - Eliminar plantilla
     */
    public function destroy($id) {
        try {
            // Verificar que la plantilla existe
            $template = $this->templateService->getTemplate($id);
            if (!$template) {
                $this->jsonResponse([
                    'error' => true,
                    'message' => 'Plantilla no encontrada'
                ], 404);
                return;
            }
            
            // Verificar que la plantilla no esté en uso
            $inUse = $this->templateService->isTemplateInUse($id);
            if ($inUse) {
                $this->jsonResponse([
                    'error' => true,
                    'message' => 'No se puede eliminar la plantilla porque está en uso'
                ], 400);
                return;
            }
            
            $success = $this->templateService->deleteTemplate($id);
            
            if ($success) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Plantilla eliminada correctamente'
                ]);
            } else {
                $this->jsonResponse([
                    'error' => true,
                    'message' => 'No se pudo eliminar la plantilla'
                ], 400);
            }
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'error' => true,
                'message' => 'Error al eliminar plantilla: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /api/templates/{id}/preview - Previsualizar plantilla con datos
     */
    public function preview($id) {
        try {
            $input = $this->getJsonInput();
            
            $template = $this->templateService->getTemplate($id);
            if (!$template) {
                $this->jsonResponse([
                    'error' => true,
                    'message' => 'Plantilla no encontrada'
                ], 404);
                return;
            }
            
            $variables = $input['variables'] ?? [];
            $preview = $this->templateService->renderTemplate($template, $variables);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'subject' => $preview['subject'],
                    'content' => $preview['content'],
                    'variables_used' => $preview['variables_used'],
                    'missing_variables' => $preview['missing_variables']
                ]
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'error' => true,
                'message' => 'Error al generar previsualización: ' . $e->getMessage()
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
