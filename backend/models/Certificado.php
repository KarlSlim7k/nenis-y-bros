<?php
/**
 * Modelo: Certificado
 * Gestiona los certificados de cursos
 */

class Certificado {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Generar certificado para usuario
     */
    public function generar($idUsuario, $idCurso, $fechaFinalizacion = null) {
        // Verificar si ya existe certificado
        $existente = $this->findByUsuarioCurso($idUsuario, $idCurso);
        if ($existente) {
            return $existente['id_certificado'];
        }
        
        // Generar código único
        $codigoVerificacion = $this->generarCodigoUnico();
        
        // Obtener info del curso y usuario
        $queryCurso = "SELECT titulo FROM cursos WHERE id_curso = ?";
        $curso = $this->db->fetchOne($queryCurso, [$idCurso]);
        
        $queryUsuario = "SELECT nombre, apellido FROM usuarios WHERE id_usuario = ?";
        $usuario = $this->db->fetchOne($queryUsuario, [$idUsuario]);
        
        // Obtener nota final (promedio de evaluaciones aprobadas)
        $queryNota = "SELECT AVG(ie.porcentaje) as nota_final
                     FROM intentos_evaluacion ie
                     INNER JOIN evaluaciones e ON ie.id_evaluacion = e.id_evaluacion
                     INNER JOIN lecciones l ON e.id_leccion = l.id_leccion
                     WHERE l.id_curso = ? AND ie.id_usuario = ? AND ie.aprobado = 1
                     AND ie.id_intento IN (
                         SELECT MAX(id_intento) FROM intentos_evaluacion 
                         WHERE id_usuario = ? GROUP BY id_evaluacion
                     )";
        
        $nota = $this->db->fetchOne($queryNota, [$idCurso, $idUsuario, $idUsuario]);
        $notaFinal = $nota ? round($nota['nota_final'], 2) : null;
        
        // Insertar certificado
        $query = "INSERT INTO certificados (
            id_usuario, id_curso, codigo_verificacion, 
            fecha_finalizacion, nota_final
        ) VALUES (?, ?, ?, ?, ?)";
        
        try {
            $fechaFin = $fechaFinalizacion ?: date('Y-m-d H:i:s');
            $idCertificado = $this->db->insert($query, [
                $idUsuario, 
                $idCurso, 
                $codigoVerificacion,
                $fechaFin,
                $notaFinal
            ]);
            
            Logger::activity($idUsuario, "Certificado generado", [
                'id_certificado' => $idCertificado,
                'id_curso' => $idCurso,
                'codigo' => $codigoVerificacion
            ]);
            
            return $idCertificado;
        } catch (Exception $e) {
            Logger::error("Error al generar certificado: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generar código único de verificación
     */
    private function generarCodigoUnico() {
        $intentos = 0;
        do {
            // Formato: NYB-XXXX-XXXX-XXXX
            $codigo = 'NYB-' . 
                     strtoupper(substr(md5(uniqid(rand(), true)), 0, 4)) . '-' .
                     strtoupper(substr(md5(uniqid(rand(), true)), 0, 4)) . '-' .
                     strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
            
            $query = "SELECT COUNT(*) as count FROM certificados WHERE codigo_verificacion = ?";
            $result = $this->db->fetchOne($query, [$codigo]);
            $existe = $result['count'] > 0;
            
            $intentos++;
        } while ($existe && $intentos < 10);
        
        if ($existe) {
            throw new Exception("No se pudo generar código único de certificado");
        }
        
        return $codigo;
    }
    
    /**
     * Buscar certificado por ID
     */
    public function findById($id) {
        $query = "SELECT 
            c.*,
            u.nombre,
            u.apellido,
            u.email,
            cu.titulo as curso_titulo,
            cu.descripcion as curso_descripcion,
            cu.duracion_horas
        FROM certificados c
        INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
        INNER JOIN cursos cu ON c.id_curso = cu.id_curso
        WHERE c.id_certificado = ?";
        
        return $this->db->fetchOne($query, [$id]);
    }
    
    /**
     * Buscar certificado por usuario y curso
     */
    public function findByUsuarioCurso($idUsuario, $idCurso) {
        $query = "SELECT * FROM certificados 
                 WHERE id_usuario = ? AND id_curso = ?";
        return $this->db->fetchOne($query, [$idUsuario, $idCurso]);
    }
    
    /**
     * Verificar certificado por código
     */
    public function verificar($codigo) {
        $query = "SELECT 
            c.*,
            u.nombre,
            u.apellido,
            cu.titulo as curso_titulo
        FROM certificados c
        INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
        INNER JOIN cursos cu ON c.id_curso = cu.id_curso
        WHERE c.codigo_verificacion = ?";
        
        $certificado = $this->db->fetchOne($query, [$codigo]);
        
        if ($certificado) {
            Logger::activity(null, "Certificado verificado", ['codigo' => $codigo]);
        }
        
        return $certificado;
    }
    
    /**
     * Obtener certificados de un usuario
     */
    public function getCertificadosByUsuario($idUsuario) {
        $query = "SELECT 
            c.*,
            cu.titulo as curso_titulo,
            cu.nivel,
            cu.imagen_url
        FROM certificados c
        INNER JOIN cursos cu ON c.id_curso = cu.id_curso
        WHERE c.id_usuario = ?
        ORDER BY c.fecha_emision DESC";
        
        return $this->db->fetchAll($query, [$idUsuario]);
    }
    
    /**
     * Verificar si usuario puede obtener certificado
     */
    public function puedeObtenerCertificado($idUsuario, $idCurso) {
        // Ya tiene certificado
        if ($this->findByUsuarioCurso($idUsuario, $idCurso)) {
            return ['puede' => false, 'razon' => 'Ya tiene certificado para este curso'];
        }
        
        // Verificar que todas las lecciones estén completadas
        $queryLecciones = "SELECT COUNT(*) as total FROM lecciones WHERE id_curso = ?";
        $totalLecciones = $this->db->fetchOne($queryLecciones, [$idCurso])['total'];
        
        $queryCompletadas = "SELECT COUNT(DISTINCT id_leccion) as completadas 
                            FROM progreso_lecciones 
                            WHERE id_usuario = ? AND completado = 1 
                            AND id_leccion IN (SELECT id_leccion FROM lecciones WHERE id_curso = ?)";
        $leccionesCompletadas = $this->db->fetchOne($queryCompletadas, [$idUsuario, $idCurso])['completadas'];
        
        if ($leccionesCompletadas < $totalLecciones) {
            return [
                'puede' => false, 
                'razon' => 'Debe completar todas las lecciones',
                'progreso' => "$leccionesCompletadas/$totalLecciones lecciones"
            ];
        }
        
        // Verificar que todas las evaluaciones estén aprobadas
        $queryEvaluaciones = "SELECT COUNT(*) as total 
                             FROM evaluaciones e
                             INNER JOIN lecciones l ON e.id_leccion = l.id_leccion
                             WHERE l.id_curso = ? AND e.es_obligatoria = 1";
        $totalEvaluaciones = $this->db->fetchOne($queryEvaluaciones, [$idCurso])['total'];
        
        if ($totalEvaluaciones > 0) {
            $queryAprobadas = "SELECT COUNT(DISTINCT e.id_evaluacion) as aprobadas
                              FROM evaluaciones e
                              INNER JOIN lecciones l ON e.id_leccion = l.id_leccion
                              INNER JOIN intentos_evaluacion ie ON e.id_evaluacion = ie.id_evaluacion
                              WHERE l.id_curso = ? AND ie.id_usuario = ? 
                              AND e.es_obligatoria = 1 AND ie.aprobado = 1";
            
            $evaluacionesAprobadas = $this->db->fetchOne($queryAprobadas, [$idCurso, $idUsuario])['aprobadas'];
            
            if ($evaluacionesAprobadas < $totalEvaluaciones) {
                return [
                    'puede' => false,
                    'razon' => 'Debe aprobar todas las evaluaciones obligatorias',
                    'progreso' => "$evaluacionesAprobadas/$totalEvaluaciones evaluaciones"
                ];
            }
        }
        
        return ['puede' => true];
    }
    
    /**
     * Generar URL del certificado (PDF)
     */
    public function getUrlCertificado($idCertificado) {
        // Placeholder - implementar cuando se agregue generación de PDF
        return APP_URL . "/certificados/$idCertificado/download";
    }
    
    /**
     * Invalidar certificado
     */
    public function invalidar($idCertificado, $razon = null) {
        $query = "UPDATE certificados SET 
                 estado = 'invalido',
                 fecha_invalidacion = CURRENT_TIMESTAMP
                 WHERE id_certificado = ?";
        
        $result = $this->db->query($query, [$idCertificado]);
        
        if ($result) {
            $certificado = $this->findById($idCertificado);
            Logger::activity($certificado['id_usuario'], "Certificado invalidado", [
                'id_certificado' => $idCertificado,
                'razon' => $razon
            ]);
        }
        
        return $result;
    }
}
