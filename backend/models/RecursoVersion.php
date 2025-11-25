<?php

/**
 * Modelo: RecursoVersion
 * 
 * Gestión de versiones de recursos: historial, comparación y restauración
 */
class RecursoVersion {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Obtener historial de versiones de un recurso
     */
    public function getHistorial($idRecurso, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $query = "
            SELECT * FROM vista_versiones_recursos
            WHERE id_recurso = ?
            ORDER BY numero_version DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idRecurso, $limit, $offset]);
        $versiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Contar total
        $countQuery = "SELECT COUNT(*) as total FROM recursos_versiones WHERE id_recurso = ?";
        $stmt = $this->db->prepare($countQuery);
        $stmt->execute([$idRecurso]);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return [
            'versiones' => $versiones,
            'total' => $total,
            'pagina_actual' => $page,
            'total_paginas' => ceil($total / $limit)
        ];
    }
    
    /**
     * Obtener versión específica de un recurso
     */
    public function getVersion($idRecurso, $numeroVersion) {
        $query = "
            SELECT rv.*, u.nombre AS nombre_usuario, u.email AS email_usuario
            FROM recursos_versiones rv
            INNER JOIN usuarios u ON rv.id_usuario_cambio = u.id_usuario
            WHERE rv.id_recurso = ? AND rv.numero_version = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idRecurso, $numeroVersion]);
        $version = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$version) {
            return null;
        }
        
        // Obtener etiquetas de esta versión
        $etiquetasQuery = "
            SELECT e.*
            FROM etiquetas_recursos e
            INNER JOIN recursos_etiquetas_versiones rev ON e.id_etiqueta = rev.id_etiqueta
            WHERE rev.id_version = ?
        ";
        
        $stmt = $this->db->prepare($etiquetasQuery);
        $stmt->execute([$version['id_version']]);
        $version['etiquetas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $version;
    }
    
    /**
     * Obtener la última versión de un recurso
     */
    public function getUltimaVersion($idRecurso) {
        $query = "
            SELECT * FROM vista_versiones_actuales
            WHERE id_recurso = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idRecurso]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crear nueva versión del recurso (llamar después de update)
     */
    public function crearVersion($idRecurso, $idUsuarioCambio, $tipocambio, $descripcionCambio, $camposModificados, $datosAnteriores = null) {
        $query = "CALL sp_crear_version_recurso(?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        
        $camposJson = json_encode($camposModificados);
        $datosJson = $datosAnteriores ? json_encode($datosAnteriores) : null;
        
        $stmt->execute([
            $idRecurso,
            $idUsuarioCambio,
            $tipocambio,
            $descripcionCambio,
            $camposJson,
            $datosJson
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    
    /**
     * Restaurar recurso a una versión anterior
     */
    public function restaurarVersion($idRecurso, $numeroVersion, $idUsuarioRestauracion) {
        try {
            $query = "CALL sp_restaurar_version(?, ?, ?)";
            $stmt = $this->db->prepare($query);
            
            $stmt->execute([
                $idRecurso,
                $numeroVersion,
                $idUsuarioRestauracion
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Invalidar caché del recurso
            Cache::getInstance()->delete("recurso:$idRecurso");
            Cache::getInstance()->invalidateResources();
            
            return $result;
        } catch (PDOException $e) {
            Logger::error("Error al restaurar versión: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Comparar dos versiones de un recurso
     */
    public function compararVersiones($idRecurso, $version1, $version2) {
        // Obtener ambas versiones
        $v1 = $this->getVersion($idRecurso, $version1);
        $v2 = $this->getVersion($idRecurso, $version2);
        
        if (!$v1 || !$v2) {
            return null;
        }
        
        // Campos a comparar
        $camposComparables = [
            'titulo', 'descripcion', 'tipo_recurso', 'tipo_acceso',
            'archivo_url', 'archivo_nombre', 'contenido_texto', 'contenido_html',
            'url_externo', 'nivel', 'idioma', 'estado', 'destacado',
            'imagen_portada', 'duracion_minutos'
        ];
        
        $diferencias = [];
        
        foreach ($camposComparables as $campo) {
            if (isset($v1[$campo]) && isset($v2[$campo])) {
                if ($v1[$campo] !== $v2[$campo]) {
                    $diferencias[$campo] = [
                        'version_' . $version1 => $v1[$campo],
                        'version_' . $version2 => $v2[$campo],
                        'cambio' => true
                    ];
                } else {
                    $diferencias[$campo] = [
                        'valor' => $v1[$campo],
                        'cambio' => false
                    ];
                }
            }
        }
        
        // Comparar etiquetas
        $etiquetas1 = array_column($v1['etiquetas'], 'nombre');
        $etiquetas2 = array_column($v2['etiquetas'], 'nombre');
        
        $diferencias['etiquetas'] = [
            'version_' . $version1 => $etiquetas1,
            'version_' . $version2 => $etiquetas2,
            'agregadas' => array_diff($etiquetas2, $etiquetas1),
            'eliminadas' => array_diff($etiquetas1, $etiquetas2),
            'cambio' => $etiquetas1 !== $etiquetas2
        ];
        
        return [
            'version_1' => [
                'numero' => $v1['numero_version'],
                'fecha' => $v1['fecha_cambio'],
                'usuario' => $v1['nombre_usuario'],
                'tipo_cambio' => $v1['tipo_cambio']
            ],
            'version_2' => [
                'numero' => $v2['numero_version'],
                'fecha' => $v2['fecha_cambio'],
                'usuario' => $v2['nombre_usuario'],
                'tipo_cambio' => $v2['tipo_cambio']
            ],
            'diferencias' => $diferencias,
            'total_cambios' => count(array_filter($diferencias, function($diff) {
                return isset($diff['cambio']) && $diff['cambio'] === true;
            }))
        ];
    }
    
    /**
     * Obtener estadísticas de versionado por recurso
     */
    public function getEstadisticasRecurso($idRecurso) {
        $query = "
            SELECT 
                COUNT(*) as total_versiones,
                MIN(fecha_cambio) as primera_version,
                MAX(fecha_cambio) as ultima_version,
                COUNT(DISTINCT id_usuario_cambio) as usuarios_editores,
                SUM(CASE WHEN tipo_cambio = 'actualizacion' THEN 1 ELSE 0 END) as actualizaciones,
                SUM(CASE WHEN tipo_cambio = 'restauracion' THEN 1 ELSE 0 END) as restauraciones,
                SUM(CASE WHEN tipo_cambio = 'publicacion' THEN 1 ELSE 0 END) as publicaciones
            FROM recursos_versiones
            WHERE id_recurso = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idRecurso]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener estadísticas globales de versionado
     */
    public function getEstadisticasGlobales() {
        $query = "
            SELECT 
                COUNT(*) as total_versiones,
                COUNT(DISTINCT id_recurso) as recursos_con_versiones,
                COUNT(DISTINCT id_usuario_cambio) as usuarios_editores,
                AVG(versiones_por_recurso) as promedio_versiones_por_recurso,
                MAX(versiones_por_recurso) as max_versiones_recurso
            FROM (
                SELECT id_recurso, COUNT(*) as versiones_por_recurso
                FROM recursos_versiones
                GROUP BY id_recurso
            ) subquery
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $globales = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Distribución por tipo de cambio
        $tiposQuery = "
            SELECT tipo_cambio, COUNT(*) as total
            FROM recursos_versiones
            GROUP BY tipo_cambio
            ORDER BY total DESC
        ";
        
        $stmt = $this->db->prepare($tiposQuery);
        $stmt->execute();
        $globales['por_tipo_cambio'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Actividad reciente (últimos 30 días)
        $actividadQuery = "
            SELECT 
                DATE(fecha_cambio) as fecha,
                COUNT(*) as versiones_creadas
            FROM recursos_versiones
            WHERE fecha_cambio >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(fecha_cambio)
            ORDER BY fecha DESC
        ";
        
        $stmt = $this->db->prepare($actividadQuery);
        $stmt->execute();
        $globales['actividad_30_dias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $globales;
    }
    
    /**
     * Obtener cambios recientes (timeline)
     */
    public function getCambiosRecientes($limit = 50) {
        $query = "
            SELECT * FROM vista_versiones_recursos
            ORDER BY fecha_cambio DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener recursos más editados
     */
    public function getRecursosMasEditados($limit = 10) {
        $query = "
            SELECT 
                rv.id_recurso,
                r.titulo,
                r.slug,
                COUNT(*) as total_versiones,
                MAX(rv.fecha_cambio) as ultima_modificacion,
                COUNT(DISTINCT rv.id_usuario_cambio) as editores_unicos
            FROM recursos_versiones rv
            INNER JOIN recursos r ON rv.id_recurso = r.id_recurso
            GROUP BY rv.id_recurso, r.titulo, r.slug
            ORDER BY total_versiones DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Buscar en historial de versiones
     */
    public function buscarEnHistorial($busqueda, $filtros = []) {
        $where = ['1=1'];
        $params = [];
        
        // Búsqueda por texto
        if (!empty($busqueda)) {
            $where[] = '(rv.titulo LIKE ? OR rv.descripcion_cambio LIKE ? OR u.nombre LIKE ?)';
            $searchTerm = '%' . $busqueda . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Filtro por tipo de cambio
        if (!empty($filtros['tipo_cambio'])) {
            $where[] = 'rv.tipo_cambio = ?';
            $params[] = $filtros['tipo_cambio'];
        }
        
        // Filtro por usuario
        if (!empty($filtros['id_usuario'])) {
            $where[] = 'rv.id_usuario_cambio = ?';
            $params[] = $filtros['id_usuario'];
        }
        
        // Filtro por recurso
        if (!empty($filtros['id_recurso'])) {
            $where[] = 'rv.id_recurso = ?';
            $params[] = $filtros['id_recurso'];
        }
        
        // Filtro por rango de fechas
        if (!empty($filtros['fecha_desde'])) {
            $where[] = 'rv.fecha_cambio >= ?';
            $params[] = $filtros['fecha_desde'];
        }
        
        if (!empty($filtros['fecha_hasta'])) {
            $where[] = 'rv.fecha_cambio <= ?';
            $params[] = $filtros['fecha_hasta'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $query = "
            SELECT rv.*, u.nombre AS nombre_usuario, r.titulo AS titulo_actual
            FROM recursos_versiones rv
            INNER JOIN usuarios u ON rv.id_usuario_cambio = u.id_usuario
            INNER JOIN recursos r ON rv.id_recurso = r.id_recurso
            WHERE {$whereClause}
            ORDER BY rv.fecha_cambio DESC
            LIMIT 100
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
