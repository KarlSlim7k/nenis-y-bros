<?php

/**
 * Modelo: Recurso
 * 
 * Gestión completa de recursos descargables: artículos, ebooks, plantillas,
 * herramientas, videos, infografías y podcasts
 */
class Recurso {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Generar slug único a partir del título
     */
    private function generateSlug($titulo, $idRecurso = null) {
        // Convertir a minúsculas y reemplazar caracteres especiales
        $slug = strtolower($titulo);
        $slug = preg_replace('/[áàäâ]/u', 'a', $slug);
        $slug = preg_replace('/[éèëê]/u', 'e', $slug);
        $slug = preg_replace('/[íìïî]/u', 'i', $slug);
        $slug = preg_replace('/[óòöô]/u', 'o', $slug);
        $slug = preg_replace('/[úùüû]/u', 'u', $slug);
        $slug = preg_replace('/ñ/u', 'n', $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Verificar unicidad
        $baseSlug = $slug;
        $counter = 1;
        
        while (true) {
            $query = "SELECT COUNT(*) as count FROM recursos_aprendizaje WHERE slug = ?";
            if ($idRecurso) {
                $query .= " AND id_recurso != ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$slug, $idRecurso]);
            } else {
                $stmt = $this->db->prepare($query);
                $stmt->execute([$slug]);
            }
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result['count'] == 0) {
                break;
            }
            
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Obtener todos los recursos con filtros
     */
    public function getAll($filters = [], $page = 1, $limit = 20) {
        // Generar clave de caché basada en filtros
        $cacheKey = 'recursos:list:' . md5(json_encode($filters) . ':' . $page . ':' . $limit);
        
        // Intentar obtener del caché
        $cached = Cache::getInstance()->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $offset = ($page - 1) * $limit;
        $where = ['r.activo = 1'];
        $params = [];
        
        // Filtros
        if (!empty($filters['categoria'])) {
            $where[] = 'JSON_CONTAINS(r.categorias, ?)';
            $params[] = '"' . $filters['categoria'] . '"';
        }
        
        if (!empty($filters['tipo_recurso'])) {
            $where[] = 'r.tipo_recurso = ?';
            $params[] = $filters['tipo_recurso'];
        }
        
        if (!empty($filters['estado'])) {
            // Para recursos_aprendizaje solo usamos activo/inactivo
            if ($filters['estado'] === 'publicado') {
                $where[] = 'r.activo = 1';
            } else {
                $where[] = 'r.activo = 0';
            }
        }
        
        if (!empty($filters['nivel'])) {
            $where[] = 'r.nivel = ?';
            $params[] = $filters['nivel'];
        }
        
        if (!empty($filters['destacado'])) {
            $where[] = 'r.destacado = 1';
        }
        
        if (!empty($filters['etiqueta'])) {
            $where[] = 'JSON_CONTAINS(r.etiquetas, ?)';
            $params[] = '"' . $filters['etiqueta'] . '"';
        }
        
        if (!empty($filters['idioma'])) {
            $where[] = 'r.idioma = ?';
            $params[] = $filters['idioma'];
        }
        
        // Búsqueda por texto (incluir contenido_texto)
        if (!empty($filters['buscar'])) {
            $where[] = '(r.titulo LIKE ? OR r.descripcion LIKE ? OR r.contenido_texto LIKE ?)';
            $searchTerm = '%' . $filters['buscar'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $where);
        
        // Ordenamiento
        $orderBy = 'ORDER BY r.fecha_publicacion DESC';
        if (!empty($filters['orden'])) {
            switch ($filters['orden']) {
                case 'mas_descargados':
                    $orderBy = 'ORDER BY r.descargas DESC';
                    break;
                case 'mejor_calificados':
                    $orderBy = 'ORDER BY r.calificacion_promedio DESC';
                    break;
                case 'recientes':
                    $orderBy = 'ORDER BY r.fecha_publicacion DESC';
                    break;
                case 'alfabetico':
                    $orderBy = 'ORDER BY r.titulo ASC';
                    break;
            }
        }
        
        // Query principal
        $query = "
            SELECT 
                r.id_recurso,
                r.titulo,
                r.slug,
                r.id_autor,
                r.descripcion,
                r.tipo_recurso,
                r.url_recurso,
                r.archivo_recurso,
                r.duracion_minutos,
                r.imagen_portada,
                r.imagen_preview,
                r.categorias,
                r.etiquetas,
                r.es_gratuito,
                r.nivel,
                r.idioma,
                r.formato,
                r.destacado,
                r.descargas as total_descargas,
                r.vistas as total_vistas,
                r.calificacion_promedio,
                r.activo,
                r.fecha_creacion,
                r.fecha_publicacion,
                CASE WHEN r.activo = 1 THEN 'publicado' ELSE 'borrador' END as estado,
                CASE WHEN r.es_gratuito = 1 THEN 'publico' ELSE 'premium' END as tipo_acceso
            FROM recursos_aprendizaje r
            {$whereClause}
            {$orderBy}
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $recursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Contar total para paginación
        $countQuery = "SELECT COUNT(*) as total FROM recursos_aprendizaje r {$whereClause}";
        $countParams = array_slice($params, 0, -2); // Quitar limit y offset
        $stmt = $this->db->prepare($countQuery);
        $stmt->execute($countParams);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $result = [
            'recursos' => $recursos,
            'total' => $total,
            'pagina_actual' => $page,
            'total_paginas' => ceil($total / $limit),
            'por_pagina' => $limit
        ];
        
        // Guardar en caché por 10 minutos
        Cache::getInstance()->set($cacheKey, $result, 600);
        
        return $result;
    }
    
    /**
     * Obtener recurso por ID (con detalles completos)
     */
    public function getById($id, $incrementarVistas = false, $idUsuario = null) {
        // Caché individual del recurso (no cachear si incrementa vistas)
        if (!$incrementarVistas) {
            $cached = Cache::getInstance()->get("recurso:$id");
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $query = "
            SELECT 
                id_recurso,
                titulo,
                slug,
                id_autor,
                descripcion,
                contenido_texto,
                contenido_html,
                tipo_recurso,
                url_recurso,
                archivo_recurso,
                duracion_minutos,
                imagen_portada,
                imagen_preview,
                video_preview,
                categorias,
                etiquetas,
                es_gratuito,
                nivel,
                idioma,
                formato,
                licencia,
                destacado,
                descargas as total_descargas,
                vistas as total_vistas,
                calificacion_promedio,
                activo,
                fecha_creacion,
                fecha_publicacion,
                fecha_actualizacion,
                CASE WHEN activo = 1 THEN 'publicado' ELSE 'borrador' END as estado,
                CASE WHEN es_gratuito = 1 THEN 'publico' ELSE 'premium' END as tipo_acceso
            FROM recursos_aprendizaje 
            WHERE id_recurso = ?
        ";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        $recurso = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$recurso) {
            return null;
        }
        
        // Extraer primera categoría del JSON
        if ($recurso['categorias']) {
            $cats = json_decode($recurso['categorias'], true);
            $recurso['id_categoria'] = $cats[0] ?? null;
        }
        
        // Incrementar contador de vistas si se solicita
        if ($incrementarVistas) {
            try {
                $updateQuery = "UPDATE recursos_aprendizaje SET vistas = vistas + 1 WHERE id_recurso = ?";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->execute([$id]);
            } catch (Exception $e) {
                // No fallar si no se puede incrementar
                Logger::error("Error incrementando vistas: " . $e->getMessage());
            }
        } else {
            // Cachear por 5 minutos
            Cache::getInstance()->set("recurso:$id", $recurso, 300);
        }
        
        return $recurso;
    }
    
    /**
     * Obtener recurso por slug
     */
    public function getBySlug($slug, $incrementarVistas = false, $idUsuario = null) {
        $query = "SELECT * FROM vista_recursos_completos WHERE slug = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$slug]);
        $recurso = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$recurso) {
            return null;
        }
        
        // Incrementar contador de vistas si se solicita
        if ($incrementarVistas) {
            $this->registrarVista($recurso['id_recurso'], $idUsuario);
        }
        
        return $recurso;
    }
    
    /**
     * Crear nuevo recurso
     */
    public function create($data) {
        // Preparar categorias como JSON
        $categorias = null;
        if (!empty($data['id_categoria'])) {
            $categorias = json_encode([$data['id_categoria']]);
        }
        
        // Generar slug si no se proporciona
        $slug = $data['slug'] ?? $this->generateSlug($data['titulo']);
        
        // Mapear nivel: principiante -> basico
        $nivel = $data['nivel'] ?? 'principiante';
        if ($nivel === 'principiante') {
            $nivel = 'basico';
        }
        
        // Determinar si es gratuito basado en tipo_acceso
        $esGratuito = ($data['tipo_acceso'] ?? 'publico') === 'publico' ? 1 : 0;
        
        // Determinar si está activo basado en estado
        $activo = ($data['estado'] ?? 'borrador') === 'publicado' ? 1 : 0;
        
        // Si se publica, establecer fecha de publicación
        $fechaPublicacion = $activo ? date('Y-m-d H:i:s') : null;
        
        $query = "
            INSERT INTO recursos_aprendizaje (
                titulo, slug, id_autor, descripcion, contenido_texto, contenido_html,
                tipo_recurso, url_recurso, archivo_recurso, duracion_minutos,
                imagen_portada, imagen_preview, video_preview,
                categorias, etiquetas, es_gratuito, nivel, idioma, formato, licencia,
                destacado, activo, fecha_publicacion
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ";
        
        $stmt = $this->db->prepare($query);
        
        $success = $stmt->execute([
            $data['titulo'],
            $slug,
            $data['id_autor'] ?? null,
            $data['descripcion'] ?? '',
            $data['contenido_texto'] ?? null,
            $data['contenido_html'] ?? null,
            $data['tipo_recurso'],
            $data['url_recurso'] ?? null,
            $data['archivo_recurso'] ?? null,
            $data['duracion_minutos'] ?? null,
            $data['imagen_portada'] ?? null,
            $data['imagen_preview'] ?? null,
            $data['video_preview'] ?? null,
            $categorias,
            json_encode($data['etiquetas'] ?? []),
            $esGratuito,
            $nivel,
            $data['idioma'] ?? 'es',
            $data['formato'] ?? null,
            $data['licencia'] ?? 'Uso educativo',
            $data['destacado'] ?? 0,
            $activo,
            $fechaPublicacion
        ]);
        
        if (!$success) {
            return false;
        }
        
        $idRecurso = $this->db->lastInsertId();
        
        // Asignar etiquetas si se proporcionaron
        if (!empty($data['etiquetas']) && is_array($data['etiquetas'])) {
            $this->asignarEtiquetas($idRecurso, $data['etiquetas']);
        }
        
        // Invalidar caché de recursos
        Cache::getInstance()->invalidateResources();
        
        return $idRecurso;
    }
    
    /**
     * Actualizar recurso
     */
    public function update($id, $data, $idUsuario = null, $descripcionCambio = null) {
        // Obtener datos actuales
        $recursoActual = $this->getById($id);
        if (!$recursoActual) {
            return false;
        }
        
        $fields = [];
        $values = [];
        
        // Mapeo completo de todos los campos soportados
        $allowedFields = [
            'titulo', 'slug', 'id_autor', 'descripcion', 'contenido_texto', 'contenido_html',
            'tipo_recurso', 'url_recurso', 'archivo_recurso', 'duracion_minutos',
            'imagen_portada', 'imagen_preview', 'video_preview',
            'nivel', 'idioma', 'formato', 'licencia', 'destacado'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'nivel') {
                    // Convertir principiante -> basico
                    $nivel = $data[$field];
                    if ($nivel === 'principiante') {
                        $nivel = 'basico';
                    }
                    $fields[] = "nivel = ?";
                    $values[] = $nivel;
                } elseif ($field === 'slug' && empty($data[$field])) {
                    // Generar slug si está vacío
                    $fields[] = "slug = ?";
                    $values[] = $this->generateSlug($data['titulo'] ?? $recursoActual['titulo'], $id);
                } else {
                    $fields[] = "{$field} = ?";
                    $values[] = $data[$field];
                }
            }
        }
        
        // Actualizar categoría si se proporciona
        if (isset($data['id_categoria'])) {
            $fields[] = "categorias = ?";
            $values[] = json_encode([$data['id_categoria']]);
        }
        
        // Actualizar etiquetas si se proporcionan
        if (isset($data['etiquetas']) && is_array($data['etiquetas'])) {
            $fields[] = "etiquetas = ?";
            $values[] = json_encode($data['etiquetas']);
        }
        
        // Actualizar tipo_acceso -> es_gratuito
        if (isset($data['tipo_acceso'])) {
            $fields[] = "es_gratuito = ?";
            $values[] = ($data['tipo_acceso'] === 'publico') ? 1 : 0;
        }
        
        // Actualizar estado -> activo y fecha_publicacion
        if (isset($data['estado'])) {
            $activo = ($data['estado'] === 'publicado') ? 1 : 0;
            $fields[] = "activo = ?";
            $values[] = $activo;
            
            // Si se está publicando y no tenía fecha de publicación, establecerla
            if ($activo && empty($recursoActual['fecha_publicacion'])) {
                $fields[] = "fecha_publicacion = NOW()";
            }
        }
        
        if (empty($fields)) {
            return true; // No hay nada que actualizar
        }
        
        $values[] = $id;
        $query = "UPDATE recursos_aprendizaje SET " . implode(', ', $fields) . " WHERE id_recurso = ?";
        
        $stmt = $this->db->prepare($query);
        $success = $stmt->execute($values);
        
        // Actualizar etiquetas si se proporcionaron
        if ($success && isset($data['etiquetas']) && is_array($data['etiquetas'])) {
            $this->asignarEtiquetas($id, $data['etiquetas']);
            if (!in_array('etiquetas', $camposModificados)) {
                $camposModificados[] = 'etiquetas';
            }
        }
        
        // Crear versión si hubo cambios y se proporcionó usuario
        if ($success && !empty($camposModificados) && $idUsuario) {
            require_once __DIR__ . '/RecursoVersion.php';
            $versionModel = new RecursoVersion();
            
            $tipoCambio = 'actualizacion';
            if (isset($data['estado'])) {
                if ($data['estado'] === 'publicado' && $recursoActual['estado'] !== 'publicado') {
                    $tipoCambio = 'publicacion';
                } else if ($data['estado'] !== 'publicado' && $recursoActual['estado'] === 'publicado') {
                    $tipoCambio = 'despublicacion';
                }
            }
            
            $descripcion = $descripcionCambio ?? 
                          'Actualización de: ' . implode(', ', $camposModificados);
            
            $versionModel->crearVersion(
                $id,
                $idUsuario,
                $tipoCambio,
                $descripcion,
                $camposModificados,
                $datosAnteriores
            );
        }
        
        // Invalidar caché de este recurso y listas
        if ($success) {
            Cache::getInstance()->delete("recurso:$id");
            Cache::getInstance()->invalidateResources();
        }
        
        return $success;
    }
    
    /**
     * Eliminar recurso
     */
    public function delete($id) {
        $query = "DELETE FROM recursos_aprendizaje WHERE id_recurso = ?";
        $stmt = $this->db->prepare($query);
        $success = $stmt->execute([$id]);
        
        // Invalidar caché
        if ($success) {
            Cache::getInstance()->delete("recurso:$id");
            Cache::getInstance()->invalidateResources();
        }
        
        return $success;
    }
    
    /**
     * Registrar descarga de recurso
     */
    public function registrarDescarga($idRecurso, $idUsuario) {
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // Registrar descarga
            $query = "
                INSERT INTO descargas_recursos (id_recurso, id_usuario, ip_address, user_agent)
                VALUES (?, ?, ?, ?)
            ";
            $stmt = $this->db->prepare($query);
            $success = $stmt->execute([$idRecurso, $idUsuario, $ipAddress, $userAgent]);
            
            if ($success) {
                // El trigger se encarga de incrementar el contador
                // Otorgar puntos al usuario por descargar (solo primera vez)
                $checkQuery = "
                    SELECT COUNT(*) as total 
                    FROM puntos_usuario 
                    WHERE id_usuario = ? 
                    AND tipo_actividad = 'descargar_recurso' 
                    AND referencia_id = ?
                ";
                $stmt = $this->db->prepare($checkQuery);
                $stmt->execute([$idUsuario, $idRecurso]);
                $yaDescargo = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
                
                if (!$yaDescargo) {
                    try {
                        $puntosQuery = "
                            INSERT INTO puntos_usuario (id_usuario, puntos_obtenidos, tipo_actividad, referencia_id)
                            VALUES (?, 5, 'descargar_recurso', ?)
                        ";
                        $stmt = $this->db->prepare($puntosQuery);
                        $stmt->execute([$idUsuario, $idRecurso]);
                    } catch (Exception $e) {
                        // No fallar si no existe tabla de puntos
                        Logger::error("Error otorgando puntos por descarga: " . $e->getMessage());
                    }
                }
            }
            
            return $success;
        } catch (Exception $e) {
            Logger::error("Error registrando descarga: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar si usuario ya descargó el recurso
     */
    public function yaDescargo($idRecurso, $idUsuario) {
        $query = "
            SELECT COUNT(*) as total 
            FROM descargas_recursos 
            WHERE id_recurso = ? AND id_usuario = ?
        ";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idRecurso, $idUsuario]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total'] > 0;
    }
    
    /**
     * Registrar vista de recurso
     */
    private function registrarVista($idRecurso, $idUsuario = null) {
        // Evitar duplicados de vistas en la misma sesión (últimos 30 minutos)
        $checkQuery = "
            SELECT COUNT(*) as total 
            FROM vistas_recursos 
            WHERE id_recurso = ? 
            AND ip_address = ?
            AND fecha_vista > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ";
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $this->db->prepare($checkQuery);
        $stmt->execute([$idRecurso, $ipAddress]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['total'] > 0) {
            return; // Ya se registró una vista reciente
        }
        
        $query = "
            INSERT INTO vistas_recursos (id_recurso, id_usuario, ip_address)
            VALUES (?, ?, ?)
        ";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idRecurso, $idUsuario, $ipAddress]);
    }
    
    /**
     * Calificar recurso
     */
    public function calificar($idRecurso, $idUsuario, $calificacion, $comentario = null) {
        // Verificar que el usuario haya descargado el recurso
        if (!$this->yaDescargo($idRecurso, $idUsuario)) {
            return ['success' => false, 'message' => 'Debes descargar el recurso antes de calificarlo'];
        }
        
        $query = "
            INSERT INTO calificaciones_recursos (id_recurso, id_usuario, calificacion, comentario)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                calificacion = VALUES(calificacion),
                comentario = VALUES(comentario),
                fecha_actualizacion = CURRENT_TIMESTAMP
        ";
        
        $stmt = $this->db->prepare($query);
        $success = $stmt->execute([$idRecurso, $idUsuario, $calificacion, $comentario]);
        
        if ($success) {
            // Otorgar puntos solo si es primera calificación
            $checkQuery = "
                SELECT COUNT(*) as total 
                FROM puntos_usuario 
                WHERE id_usuario = ? 
                AND tipo_actividad = 'calificar_recurso' 
                AND referencia_id = ?
            ";
            $stmt = $this->db->prepare($checkQuery);
            $stmt->execute([$idUsuario, $idRecurso]);
            $yaCalificado = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
            
            if (!$yaCalificado) {
                $puntosQuery = "
                    INSERT INTO puntos_usuario (id_usuario, puntos_obtenidos, tipo_actividad, referencia_id)
                    VALUES (?, 3, 'calificar_recurso', ?)
                ";
                $stmt = $this->db->prepare($puntosQuery);
                $stmt->execute([$idUsuario, $idRecurso]);
            }
        }
        
        return ['success' => $success];
    }
    
    /**
     * Obtener calificaciones de un recurso
     */
    public function getCalificaciones($idRecurso, $limit = 10) {
        $query = "
            SELECT 
                c.*,
                u.nombre,
                u.apellido,
                u.foto_perfil
            FROM calificaciones_recursos c
            JOIN usuarios u ON c.id_usuario = u.id_usuario
            WHERE c.id_recurso = ?
            ORDER BY c.fecha_calificacion DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idRecurso, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Asignar etiquetas a un recurso
     */
    private function asignarEtiquetas($idRecurso, $etiquetas) {
        // Eliminar etiquetas actuales
        $deleteQuery = "DELETE FROM recursos_etiquetas WHERE id_recurso = ?";
        $stmt = $this->db->prepare($deleteQuery);
        $stmt->execute([$idRecurso]);
        
        // Insertar nuevas etiquetas
        foreach ($etiquetas as $nombreEtiqueta) {
            // Crear etiqueta si no existe
            $slug = $this->generateSlug($nombreEtiqueta);
            $insertEtiquetaQuery = "
                INSERT INTO etiquetas_recursos (nombre, slug)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE slug = slug
            ";
            $stmt = $this->db->prepare($insertEtiquetaQuery);
            $stmt->execute([$nombreEtiqueta, $slug]);
            
            // Obtener ID de la etiqueta
            $getIdQuery = "SELECT id_etiqueta FROM etiquetas_recursos WHERE slug = ?";
            $stmt = $this->db->prepare($getIdQuery);
            $stmt->execute([$slug]);
            $idEtiqueta = $stmt->fetch(PDO::FETCH_ASSOC)['id_etiqueta'];
            
            // Asociar etiqueta al recurso
            $insertRelacionQuery = "
                INSERT INTO recursos_etiquetas (id_recurso, id_etiqueta)
                VALUES (?, ?)
            ";
            $stmt = $this->db->prepare($insertRelacionQuery);
            $stmt->execute([$idRecurso, $idEtiqueta]);
        }
    }
    
    /**
     * Obtener recursos relacionados (misma categoría o etiquetas)
     */
    public function getRelacionados($idRecurso, $limit = 6) {
        $query = "
            SELECT DISTINCT r.*
            FROM vista_recursos_completos r
            WHERE r.id_recurso != ?
            AND r.estado = 'publicado'
            AND (
                r.id_categoria = (SELECT id_categoria FROM recursos WHERE id_recurso = ?)
                OR EXISTS (
                    SELECT 1 FROM recursos_etiquetas re1
                    JOIN recursos_etiquetas re2 ON re1.id_etiqueta = re2.id_etiqueta
                    WHERE re1.id_recurso = ? AND re2.id_recurso = r.id_recurso
                )
            )
            ORDER BY r.calificacion_promedio DESC, r.total_descargas DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idRecurso, $idRecurso, $idRecurso, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener descargas de un usuario
     */
    public function getDescargasUsuario($idUsuario, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $query = "
            SELECT 
                r.*,
                d.fecha_descarga
            FROM vista_recursos_completos r
            JOIN descargas_recursos d ON r.id_recurso = d.id_recurso
            WHERE d.id_usuario = ?
            ORDER BY d.fecha_descarga DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$idUsuario, $limit, $offset]);
        $recursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Contar total
        $countQuery = "SELECT COUNT(*) as total FROM descargas_recursos WHERE id_usuario = ?";
        $stmt = $this->db->prepare($countQuery);
        $stmt->execute([$idUsuario]);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return [
            'recursos' => $recursos,
            'total' => $total,
            'pagina_actual' => $page,
            'total_paginas' => ceil($total / $limit)
        ];
    }
    
    /**
     * Búsqueda avanzada con FULLTEXT
     */
    public function buscarFullText($termino, $filters = [], $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $where = ['r.estado = "publicado"'];
        $params = [];
        
        // Búsqueda FULLTEXT
        if (!empty($termino)) {
            $where[] = 'MATCH(r.titulo, r.descripcion) AGAINST(? IN NATURAL LANGUAGE MODE)';
            $params[] = $termino;
        }
        
        // Aplicar filtros adicionales
        if (!empty($filters['categoria'])) {
            $where[] = 'r.id_categoria = ?';
            $params[] = $filters['categoria'];
        }
        
        if (!empty($filters['tipo_recurso'])) {
            $where[] = 'r.tipo_recurso = ?';
            $params[] = $filters['tipo_recurso'];
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $where);
        
        $query = "
            SELECT * FROM vista_recursos_completos r
            {$whereClause}
            ORDER BY r.calificacion_promedio DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $recursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Contar total
        $countQuery = "SELECT COUNT(*) as total FROM recursos r {$whereClause}";
        $countParams = array_slice($params, 0, -2);
        $stmt = $this->db->prepare($countQuery);
        $stmt->execute($countParams);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return [
            'recursos' => $recursos,
            'total' => $total,
            'termino_busqueda' => $termino
        ];
    }
    
    /**
     * Estadísticas globales de recursos
     */
    public function getEstadisticas() {
        // Estadísticas de recursos
        $query = "
            SELECT 
                COUNT(*) as total_recursos,
                COUNT(CASE WHEN activo = 1 THEN 1 END) as publicados,
                COUNT(CASE WHEN activo = 0 THEN 1 END) as borradores,
                COALESCE(SUM(descargas), 0) as total_descargas,
                COALESCE(SUM(vistas), 0) as total_vistas,
                COALESCE(AVG(calificacion_promedio), 0) as calificacion_promedio_global
            FROM recursos_aprendizaje
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Contar categorías desde tabla categorias_recursos
        $queryCat = "SELECT COUNT(*) as total FROM categorias_recursos WHERE activa = 1";
        $stmtCat = $this->db->prepare($queryCat);
        $stmtCat->execute();
        $catResult = $stmtCat->fetch(PDO::FETCH_ASSOC);
        
        $stats['total_categorias'] = $catResult['total'] ?? 0;
        
        return $stats;
    }
    
    /**
     * Analytics: Descargas por período de tiempo
     */
    public function getDescargasPorTiempo($fechaDesde, $fechaHasta, $agrupacion = 'day') {
        $formatoFecha = match($agrupacion) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d'
        };
        
        $query = "
            SELECT 
                DATE_FORMAT(fecha_descarga, ?) as periodo,
                COUNT(*) as total_descargas,
                COUNT(DISTINCT id_usuario) as usuarios_unicos,
                COUNT(DISTINCT id_recurso) as recursos_descargados
            FROM descargas_recursos
            WHERE fecha_descarga BETWEEN ? AND ?
            GROUP BY periodo
            ORDER BY periodo ASC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$formatoFecha, $fechaDesde, $fechaHasta]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Analytics: Recursos más descargados
     */
    public function getRecursosMasDescargados($limit = 10, $fechaDesde = null, $fechaHasta = null) {
        $whereClause = $fechaDesde && $fechaHasta 
            ? "WHERE d.fecha_descarga BETWEEN ? AND ?"
            : "";
        
        $query = "
            SELECT 
                r.id_recurso,
                r.titulo,
                r.slug,
                r.tipo_recurso,
                c.nombre as categoria,
                COUNT(d.id_descarga) as total_descargas,
                COUNT(DISTINCT d.id_usuario) as usuarios_unicos,
                r.calificacion_promedio,
                r.total_calificaciones
            FROM recursos r
            LEFT JOIN descargas_recursos d ON r.id_recurso = d.id_recurso
            LEFT JOIN categorias_recursos c ON r.id_categoria = c.id_categoria
            {$whereClause}
            GROUP BY r.id_recurso
            ORDER BY total_descargas DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($query);
        
        if ($fechaDesde && $fechaHasta) {
            $stmt->execute([$fechaDesde, $fechaHasta, $limit]);
        } else {
            $stmt->execute([$limit]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Analytics: Recursos más vistos
     */
    public function getRecursosMasVistos($limit = 10, $fechaDesde = null, $fechaHasta = null) {
        $whereClause = $fechaDesde && $fechaHasta 
            ? "WHERE v.fecha_vista BETWEEN ? AND ?"
            : "";
        
        $query = "
            SELECT 
                r.id_recurso,
                r.titulo,
                r.slug,
                r.tipo_recurso,
                c.nombre as categoria,
                COUNT(v.id_vista) as total_vistas,
                COUNT(DISTINCT v.id_usuario) as usuarios_unicos,
                r.total_descargas,
                r.calificacion_promedio
            FROM recursos r
            LEFT JOIN vistas_recursos v ON r.id_recurso = v.id_recurso
            LEFT JOIN categorias_recursos c ON r.id_categoria = c.id_categoria
            {$whereClause}
            GROUP BY r.id_recurso
            ORDER BY total_vistas DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($query);
        
        if ($fechaDesde && $fechaHasta) {
            $stmt->execute([$fechaDesde, $fechaHasta, $limit]);
        } else {
            $stmt->execute([$limit]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Analytics: Recursos mejor calificados
     */
    public function getRecursosMejorCalificados($limit = 10, $minCalificaciones = 5) {
        $query = "
            SELECT 
                r.id_recurso,
                r.titulo,
                r.slug,
                r.tipo_recurso,
                c.nombre as categoria,
                r.calificacion_promedio,
                r.total_calificaciones,
                r.total_descargas,
                r.total_vistas
            FROM recursos r
            LEFT JOIN categorias_recursos c ON r.id_categoria = c.id_categoria
            WHERE r.total_calificaciones >= ?
            AND r.estado = 'publicado'
            ORDER BY r.calificacion_promedio DESC, r.total_calificaciones DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$minCalificaciones, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Analytics: Tasa de conversión (vistas -> descargas)
     */
    public function getTasaConversion($fechaDesde = null, $fechaHasta = null) {
        $whereClause = "";
        $params = [];
        
        if ($fechaDesde && $fechaHasta) {
            $whereClause = "WHERE r.fecha_creacion BETWEEN ? AND ?";
            $params = [$fechaDesde, $fechaHasta];
        }
        
        $query = "
            SELECT 
                r.id_recurso,
                r.titulo,
                r.slug,
                r.total_vistas,
                r.total_descargas,
                CASE 
                    WHEN r.total_vistas > 0 
                    THEN ROUND((r.total_descargas / r.total_vistas) * 100, 2)
                    ELSE 0 
                END as tasa_conversion,
                c.nombre as categoria
            FROM recursos r
            LEFT JOIN categorias_recursos c ON r.id_categoria = c.id_categoria
            {$whereClause}
            HAVING r.total_vistas > 0
            ORDER BY tasa_conversion DESC
            LIMIT 50
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Analytics: Distribución por categoría
     */
    public function getDistribucionPorCategoria() {
        $query = "
            SELECT 
                c.id_categoria,
                c.nombre,
                c.slug,
                c.color,
                COUNT(r.id_recurso) as total_recursos,
                SUM(r.total_descargas) as total_descargas,
                SUM(r.total_vistas) as total_vistas,
                AVG(r.calificacion_promedio) as calificacion_promedio,
                COUNT(CASE WHEN r.estado = 'publicado' THEN 1 END) as publicados
            FROM categorias_recursos c
            LEFT JOIN recursos r ON c.id_categoria = r.id_categoria
            WHERE c.activa = 1
            GROUP BY c.id_categoria
            ORDER BY total_recursos DESC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Analytics: Distribución por tipo de recurso
     */
    public function getDistribucionPorTipo() {
        $query = "
            SELECT 
                tipo_recurso,
                COUNT(*) as total,
                SUM(total_descargas) as descargas,
                SUM(total_vistas) as vistas,
                AVG(calificacion_promedio) as calificacion_promedio
            FROM recursos
            WHERE estado = 'publicado'
            GROUP BY tipo_recurso
            ORDER BY total DESC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Analytics: Tendencias (comparación con período anterior)
     */
    public function getTendencias($fechaDesde, $fechaHasta) {
        $diasDiferencia = (strtotime($fechaHasta) - strtotime($fechaDesde)) / 86400;
        $fechaDesdePrevio = date('Y-m-d', strtotime($fechaDesde . ' -' . $diasDiferencia . ' days'));
        
        $query = "
            SELECT 
                'actual' as periodo,
                COUNT(DISTINCT d.id_descarga) as descargas,
                COUNT(DISTINCT d.id_usuario) as usuarios,
                COUNT(DISTINCT d.id_recurso) as recursos_descargados
            FROM descargas_recursos d
            WHERE d.fecha_descarga BETWEEN ? AND ?
            
            UNION ALL
            
            SELECT 
                'anterior' as periodo,
                COUNT(DISTINCT d.id_descarga) as descargas,
                COUNT(DISTINCT d.id_usuario) as usuarios,
                COUNT(DISTINCT d.id_recurso) as recursos_descargados
            FROM descargas_recursos d
            WHERE d.fecha_descarga BETWEEN ? AND ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$fechaDesde, $fechaHasta, $fechaDesdePrevio, $fechaDesde]);
        
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular diferencias porcentuales
        $actual = $resultados[0] ?? ['descargas' => 0, 'usuarios' => 0, 'recursos_descargados' => 0];
        $anterior = $resultados[1] ?? ['descargas' => 0, 'usuarios' => 0, 'recursos_descargados' => 0];
        
        $tendencias = [
            'actual' => $actual,
            'anterior' => $anterior,
            'cambio_porcentual' => [
                'descargas' => $anterior['descargas'] > 0 
                    ? round((($actual['descargas'] - $anterior['descargas']) / $anterior['descargas']) * 100, 2)
                    : 0,
                'usuarios' => $anterior['usuarios'] > 0 
                    ? round((($actual['usuarios'] - $anterior['usuarios']) / $anterior['usuarios']) * 100, 2)
                    : 0,
                'recursos_descargados' => $anterior['recursos_descargados'] > 0 
                    ? round((($actual['recursos_descargados'] - $anterior['recursos_descargados']) / $anterior['recursos_descargados']) * 100, 2)
                    : 0
            ]
        ];
        
        return $tendencias;
    }
    
    /**
     * Analytics: Usuarios más activos
     */
    public function getUsuariosMasActivos($limit = 10, $fechaDesde = null, $fechaHasta = null) {
        $whereClause = $fechaDesde && $fechaHasta 
            ? "WHERE d.fecha_descarga BETWEEN ? AND ?"
            : "";
        
        $query = "
            SELECT 
                u.id_usuario,
                u.nombre,
                u.email,
                COUNT(DISTINCT d.id_descarga) as total_descargas,
                COUNT(DISTINCT d.id_recurso) as recursos_unicos,
                MAX(d.fecha_descarga) as ultima_descarga
            FROM usuarios u
            INNER JOIN descargas_recursos d ON u.id_usuario = d.id_usuario
            {$whereClause}
            GROUP BY u.id_usuario
            ORDER BY total_descargas DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($query);
        
        if ($fechaDesde && $fechaHasta) {
            $stmt->execute([$fechaDesde, $fechaHasta, $limit]);
        } else {
            $stmt->execute([$limit]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
