class Template {
    private $db;
    private $table = 'mail_templates';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Obtener todas las plantillas
     */
    public function getAll($page = 1, $limit = 10, $type = null) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT id, name, type, subject, description, is_active, created_at, updated_at 
                FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if ($type) {
            $sql .= " AND type = :type";
            $params['type'] = $type;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        $templates = $stmt->fetchAll();
        
        // Obtener total para paginación
        $countSql = "SELECT COUNT(*) FROM {$this->table} WHERE 1=1";
        if ($type) {
            $countSql .= " AND type = :type";
        }
        
        $countStmt = $this->db->prepare($countSql);
        if ($type) {
            $countStmt->bindValue(':type', $type);
        }
        $countStmt->execute();
        $total = $countStmt->fetchColumn();
        
        return [
            'templates' => $templates,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Obtener plantilla por ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    /**
     * Obtener plantilla por tipo
     */
    public function getByType($type) {
        $sql = "SELECT * FROM {$this->table} WHERE type = :type AND is_active = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':type', $type);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    /**
     * Crear nueva plantilla
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table} 
                (name, type, subject, content, variables, description, is_active, created_at) 
                VALUES (:name, :type, :subject, :content, :variables, :description, :is_active, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':type', $data['type']);
        $stmt->bindValue(':subject', $data['subject']);
        $stmt->bindValue(':content', $data['content']);
        $stmt->bindValue(':variables', $data['variables']);
        $stmt->bindValue(':description', $data['description'] ?? '');
        $stmt->bindValue(':is_active', $data['is_active'] ?? 1, PDO::PARAM_INT);
        
        $stmt->execute();
        return $this->db->lastInsertId();
    }
    
    /**
     * Actualizar plantilla
     */
    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];
        
        $allowedFields = ['name', 'type', 'subject', 'content', 'variables', 'description', 'is_active'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = NOW()";
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        return $stmt->execute();
    }
    
    /**
     * Eliminar plantilla
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    /**
     * Verificar si una plantilla está en uso
     */
    public function isInUse($id) {
        $sql = "SELECT COUNT(*) FROM mail_logs WHERE template_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Activar/Desactivar plantilla
     */
    public function toggleStatus($id) {
        $sql = "UPDATE {$this->table} SET is_active = NOT is_active, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    /**
     * Buscar plantillas
     */
    public function search($query, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT id, name, type, subject, description, is_active, created_at 
                FROM {$this->table} 
                WHERE (name LIKE :query OR subject LIKE :query OR description LIKE :query)
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':query', "%{$query}%");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener estadísticas de plantillas
     */
    public function getStats() {
        $stats = [];
        
        // Total de plantillas
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $stmt = $this->db->query($sql);
        $stats['total'] = $stmt->fetchColumn();
        
        // Plantillas activas
        $sql = "SELECT COUNT(*) as active FROM {$this->table} WHERE is_active = 1";
        $stmt = $this->db->query($sql);
        $stats['active'] = $stmt->fetchColumn();
        
        // Por tipo
        $sql = "SELECT type, COUNT(*) as count FROM {$this->table} GROUP BY type";
        $stmt = $this->db->query($sql);
        $stats['by_type'] = $stmt->fetchAll();
        
        return $stats;
    }
}
