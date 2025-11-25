<?php
/**
 * Modelo: Prerrequisito
 * Gestiona prerrequisitos entre cursos
 */

class Prerrequisito {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Agregar prerrequisito
     */
    public function agregar($idCurso, $idCursoRequerido) {
        // Verificar que no exista
        if ($this->existe($idCurso, $idCursoRequerido)) {
            return false;
        }
        
        // Verificar que no cree ciclos
        if ($this->creariaciclo($idCurso, $idCursoRequerido)) {
            throw new Exception("No se puede agregar: crearía una dependencia circular");
        }
        
        $query = "INSERT INTO prerrequisitos_curso (id_curso, id_curso_requerido) VALUES (?, ?)";
        
        try {
            return $this->db->insert($query, [$idCurso, $idCursoRequerido]);
        } catch (Exception $e) {
            Logger::error("Error al agregar prerrequisito: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verificar si existe prerrequisito
     */
    private function existe($idCurso, $idCursoRequerido) {
        $query = "SELECT COUNT(*) as count FROM prerrequisitos_curso 
                 WHERE id_curso = ? AND id_curso_requerido = ?";
        $result = $this->db->fetchOne($query, [$idCurso, $idCursoRequerido]);
        return $result['count'] > 0;
    }
    
    /**
     * Verificar si crearía ciclo
     */
    private function creariaciclo($idCurso, $idCursoRequerido) {
        // Si el curso requerido ya depende del curso actual, sería un ciclo
        return $this->dependeDe($idCursoRequerido, $idCurso);
    }
    
    /**
     * Verificar si un curso depende de otro (recursivo)
     */
    private function dependeDe($idCurso, $idCursoBuscado, $visitados = []) {
        if ($idCurso == $idCursoBuscado) return true;
        if (in_array($idCurso, $visitados)) return false;
        
        $visitados[] = $idCurso;
        
        $query = "SELECT id_curso_requerido FROM prerrequisitos_curso WHERE id_curso = ?";
        $prerrequisitos = $this->db->fetchAll($query, [$idCurso]);
        
        foreach ($prerrequisitos as $prereq) {
            if ($this->dependeDe($prereq['id_curso_requerido'], $idCursoBuscado, $visitados)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Obtener prerrequisitos de un curso
     */
    public function getPrerrequisitos($idCurso) {
        $query = "SELECT 
            pc.*,
            c.titulo,
            c.nivel,
            c.imagen_url
        FROM prerrequisitos_curso pc
        INNER JOIN cursos c ON pc.id_curso_requerido = c.id_curso
        WHERE pc.id_curso = ?
        ORDER BY c.orden ASC";
        
        return $this->db->fetchAll($query, [$idCurso]);
    }
    
    /**
     * Verificar si usuario cumple prerrequisitos
     */
    public function cumplePrerrequisitos($idUsuario, $idCurso) {
        $prerrequisitos = $this->getPrerrequisitos($idCurso);
        
        if (empty($prerrequisitos)) {
            return ['cumple' => true, 'faltantes' => []];
        }
        
        $faltantes = [];
        
        foreach ($prerrequisitos as $prereq) {
            // Verificar si tiene certificado del curso requerido
            $query = "SELECT COUNT(*) as count FROM certificados 
                     WHERE id_usuario = ? AND id_curso = ? AND estado = 'valido'";
            $result = $this->db->fetchOne($query, [$idUsuario, $prereq['id_curso_requerido']]);
            
            if ($result['count'] == 0) {
                $faltantes[] = [
                    'id_curso' => $prereq['id_curso_requerido'],
                    'titulo' => $prereq['titulo'],
                    'nivel' => $prereq['nivel']
                ];
            }
        }
        
        return [
            'cumple' => empty($faltantes),
            'faltantes' => $faltantes
        ];
    }
    
    /**
     * Eliminar prerrequisito
     */
    public function eliminar($idCurso, $idCursoRequerido) {
        $query = "DELETE FROM prerrequisitos_curso 
                 WHERE id_curso = ? AND id_curso_requerido = ?";
        return $this->db->query($query, [$idCurso, $idCursoRequerido]);
    }
    
    /**
     * Obtener cursos que requieren este curso como prerrequisito
     */
    public function getCursosQueRequieren($idCurso) {
        $query = "SELECT 
            pc.*,
            c.titulo,
            c.nivel
        FROM prerrequisitos_curso pc
        INNER JOIN cursos c ON pc.id_curso = c.id_curso
        WHERE pc.id_curso_requerido = ?";
        
        return $this->db->fetchAll($query, [$idCurso]);
    }
}
