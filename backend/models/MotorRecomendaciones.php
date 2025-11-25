<?php
/**
 * ============================================================================
 * MODELO: MOTOR DE RECOMENDACIONES
 * ============================================================================
 * Analiza resultados de diagnósticos y recomienda cursos personalizados
 * Fase 3 - Perfiles Empresariales y Diagnósticos
 * ============================================================================
 */

class MotorRecomendaciones {
    
    private $db;
    
    // Umbrales para determinar necesidad de mejora por área
    const UMBRAL_CRITICO = 40;      // < 40% = Área crítica (alta prioridad)
    const UMBRAL_MEJORABLE = 60;    // 40-60% = Área mejorable (prioridad media)
    const UMBRAL_BUENO = 80;        // 60-80% = Área buena (prioridad baja)
    // > 80% = Área excelente (no requiere acción inmediata)
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Generar recomendaciones completas para un diagnóstico
     * 
     * @param int $diagnosticoId ID del diagnóstico realizado
     * @return array Recomendaciones organizadas por prioridad con cursos sugeridos
     */
    public function generarRecomendaciones($diagnosticoId) {
        // Obtener diagnóstico con resultados
        $query = "SELECT 
            dr.*,
            td.nombre as tipo_diagnostico,
            pe.nombre_empresa,
            pe.sector,
            pe.etapa_negocio
        FROM diagnosticos_realizados dr
        INNER JOIN tipos_diagnostico td ON dr.id_tipo_diagnostico = td.id_tipo_diagnostico
        LEFT JOIN perfiles_empresariales pe ON dr.id_perfil_empresarial = pe.id_perfil
        WHERE dr.id_diagnostico_realizado = ?";
        
        $diagnostico = $this->db->fetchOne($query, [$diagnosticoId]);
        
        if (!$diagnostico || $diagnostico['estado'] !== 'completado') {
            return [
                'error' => 'Diagnóstico no encontrado o no completado',
                'recomendaciones' => []
            ];
        }
        
        // Decodificar resultados por área
        $resultadosAreas = json_decode($diagnostico['resultados_areas'], true);
        
        if (!$resultadosAreas) {
            return [
                'error' => 'No se pudieron analizar los resultados',
                'recomendaciones' => []
            ];
        }
        
        // Clasificar áreas por prioridad
        $areasAnalisis = $this->clasificarAreasPorPrioridad($resultadosAreas);
        
        // Generar recomendaciones por área
        $recomendaciones = [
            'resumen_general' => $this->generarResumenGeneral($diagnostico, $areasAnalisis),
            'areas_criticas' => [],
            'areas_mejorables' => [],
            'areas_fuertes' => [],
            'plan_accion' => []
        ];
        
        // Procesar cada grupo de áreas
        foreach ($areasAnalisis['criticas'] as $area) {
            $recomendaciones['areas_criticas'][] = $this->generarRecomendacionArea(
                $area, 
                'critica',
                $diagnostico['sector'],
                $diagnostico['etapa_negocio']
            );
        }
        
        foreach ($areasAnalisis['mejorables'] as $area) {
            $recomendaciones['areas_mejorables'][] = $this->generarRecomendacionArea(
                $area,
                'mejorable',
                $diagnostico['sector'],
                $diagnostico['etapa_negocio']
            );
        }
        
        foreach ($areasAnalisis['fuertes'] as $area) {
            $recomendaciones['areas_fuertes'][] = [
                'id_area' => $area['id_area'],
                'nombre' => $area['nombre'],
                'porcentaje' => $area['porcentaje'],
                'mensaje' => "¡Excelente! Esta área es una fortaleza de tu negocio."
            ];
        }
        
        // Generar plan de acción priorizado
        $recomendaciones['plan_accion'] = $this->generarPlanAccion($recomendaciones);
        
        // Guardar recomendaciones en la BD
        $this->guardarRecomendaciones($diagnosticoId, $recomendaciones);
        
        return $recomendaciones;
    }
    
    /**
     * Clasificar áreas según su puntaje
     */
    private function clasificarAreasPorPrioridad($resultadosAreas) {
        $clasificacion = [
            'criticas' => [],      // < 40%
            'mejorables' => [],    // 40-60%
            'buenas' => [],        // 60-80%
            'fuertes' => []        // > 80%
        ];
        
        foreach ($resultadosAreas as $area) {
            $porcentaje = $area['porcentaje'];
            
            if ($porcentaje < self::UMBRAL_CRITICO) {
                $clasificacion['criticas'][] = $area;
            } elseif ($porcentaje < self::UMBRAL_MEJORABLE) {
                $clasificacion['mejorables'][] = $area;
            } elseif ($porcentaje < self::UMBRAL_BUENO) {
                $clasificacion['buenas'][] = $area;
            } else {
                $clasificacion['fuertes'][] = $area;
            }
        }
        
        // Ordenar por porcentaje ascendente (peor primero)
        usort($clasificacion['criticas'], fn($a, $b) => $a['porcentaje'] <=> $b['porcentaje']);
        usort($clasificacion['mejorables'], fn($a, $b) => $a['porcentaje'] <=> $b['porcentaje']);
        
        return $clasificacion;
    }
    
    /**
     * Generar resumen general del diagnóstico
     */
    private function generarResumenGeneral($diagnostico, $areasAnalisis) {
        $nivel = $diagnostico['nivel_madurez'];
        $puntaje = $diagnostico['puntaje_total'];
        
        $mensajes = [
            'inicial' => "Tu negocio está en etapa inicial. Necesitas fortalecer las bases en varias áreas clave.",
            'basico' => "Tu negocio tiene fundamentos básicos, pero hay oportunidades significativas de mejora.",
            'intermedio' => "Tu negocio muestra un desarrollo sólido. Enfócate en optimizar áreas específicas.",
            'avanzado' => "¡Excelente! Tu negocio está bien desarrollado. Mantén las fortalezas y perfecciona detalles.",
            'experto' => "¡Felicitaciones! Tu negocio demuestra madurez excepcional en todas las áreas."
        ];
        
        $accionesGenerales = [
            'inicial' => "Prioriza formación en las áreas críticas identificadas.",
            'basico' => "Invierte en capacitación para superar las brechas detectadas.",
            'intermedio' => "Continúa profesionalizando tu gestión con formación especializada.",
            'avanzado' => "Busca formación avanzada para mantener tu ventaja competitiva.",
            'experto' => "Considera especializaciones o compartir tu conocimiento con otros."
        ];
        
        return [
            'nivel_madurez' => $nivel,
            'puntaje_total' => $puntaje,
            'mensaje' => $mensajes[$nivel] ?? $mensajes['basico'],
            'accion_general' => $accionesGenerales[$nivel] ?? $accionesGenerales['basico'],
            'total_areas_criticas' => count($areasAnalisis['criticas']),
            'total_areas_mejorables' => count($areasAnalisis['mejorables']),
            'total_areas_fuertes' => count($areasAnalisis['fuertes'])
        ];
    }
    
    /**
     * Generar recomendación detallada para un área específica
     */
    private function generarRecomendacionArea($area, $prioridad, $sector = null, $etapa = null) {
        $areaNombre = $area['nombre'];
        $porcentaje = $area['porcentaje'];
        
        // Buscar cursos relevantes para esta área
        $cursosRecomendados = $this->buscarCursosParaArea($areaNombre, $sector, $etapa, $prioridad);
        
        // Generar mensaje personalizado
        $mensaje = $this->generarMensajeArea($areaNombre, $porcentaje, $prioridad);
        
        // Acciones específicas sugeridas
        $acciones = $this->generarAccionesArea($areaNombre, $prioridad);
        
        return [
            'id_area' => $area['id_area'],
            'nombre' => $areaNombre,
            'porcentaje' => $porcentaje,
            'nivel' => $area['nivel'],
            'prioridad' => $prioridad,
            'mensaje' => $mensaje,
            'acciones_sugeridas' => $acciones,
            'cursos_recomendados' => $cursosRecomendados,
            'total_cursos' => count($cursosRecomendados)
        ];
    }
    
    /**
     * Buscar cursos relevantes para un área específica
     */
    private function buscarCursosParaArea($areaNombre, $sector, $etapa, $prioridad) {
        // Mapeo de áreas a palabras clave de búsqueda en cursos
        $palabrasClave = [
            'Gestión Empresarial' => ['gestión', 'administración', 'liderazgo', 'planificación', 'estrategia'],
            'Finanzas' => ['finanzas', 'contabilidad', 'presupuesto', 'costos', 'financiero'],
            'Marketing y Ventas' => ['marketing', 'ventas', 'digital', 'redes sociales', 'publicidad'],
            'Operaciones' => ['operaciones', 'procesos', 'productividad', 'calidad', 'logística'],
            'Recursos Humanos' => ['recursos humanos', 'talento', 'equipo', 'capacitación', 'personal']
        ];
        
        $keywords = $palabrasClave[$areaNombre] ?? ['empresa', 'negocio'];
        
        // Construir query de búsqueda
        $whereClauses = [];
        $params = [];
        
        // Buscar en título y descripción
        $keywordConditions = [];
        foreach ($keywords as $keyword) {
            $keywordConditions[] = "(c.titulo LIKE ? OR c.descripcion_corta LIKE ? OR c.descripcion LIKE ?)";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }
        $whereClauses[] = "(" . implode(" OR ", $keywordConditions) . ")";
        
        // Solo cursos publicados
        $whereClauses[] = "c.estado = 'publicado'";
        
        $whereClause = implode(" AND ", $whereClauses);
        
        // Ordenar: Prioridad alta muestra cursos básicos primero
        $orderBy = ($prioridad === 'critica') 
            ? "c.nivel_dificultad ASC, c.fecha_creacion DESC" 
            : "c.fecha_creacion DESC";
        
        $query = "SELECT 
            c.id_curso,
            c.titulo,
            c.descripcion_corta,
            c.nivel_dificultad,
            c.duracion_horas,
            c.precio,
            c.imagen_portada,
            c.calificacion_promedio,
            (SELECT COUNT(*) FROM modulos WHERE id_curso = c.id_curso) as total_modulos
        FROM cursos c
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT 5";
        
        $cursos = $this->db->fetchAll($query, $params);
        
        // Si no hay cursos con keywords, buscar cursos generales de empresa
        if (empty($cursos)) {
            $query = "SELECT 
                c.id_curso,
                c.titulo,
                c.descripcion_corta,
                c.nivel_dificultad,
                c.duracion_horas,
                c.precio,
                c.imagen_portada,
                c.calificacion_promedio,
                (SELECT COUNT(*) FROM modulos WHERE id_curso = c.id_curso) as total_modulos
            FROM cursos c
            WHERE c.estado = 'publicado'
            AND (c.titulo LIKE '%empresa%' OR c.titulo LIKE '%negocio%')
            ORDER BY c.fecha_creacion DESC
            LIMIT 3";
            
            $cursos = $this->db->fetchAll($query);
        }
        
        return $cursos;
    }
    
    /**
     * Generar mensaje personalizado por área
     */
    private function generarMensajeArea($areaNombre, $porcentaje, $prioridad) {
        $mensajes = [
            'critica' => [
                'Gestión Empresarial' => "URGENTE: Tu gestión empresarial requiere atención inmediata. Sin bases sólidas en planificación y organización, el crecimiento será difícil.",
                'Finanzas' => "ALERTA: El control financiero es crítico. Necesitas dominar presupuestos, costos y flujo de caja para la sostenibilidad del negocio.",
                'Marketing y Ventas' => "PRIORIDAD ALTA: Sin estrategias efectivas de marketing y ventas, captar y retener clientes será un desafío constante.",
                'Operaciones' => "CRÍTICO: La eficiencia operativa es fundamental. Procesos deficientes impactan directamente en costos y calidad.",
                'Recursos Humanos' => "IMPORTANTE: Tu equipo es tu activo más valioso. La gestión de talento requiere mejora urgente."
            ],
            'mejorable' => [
                'Gestión Empresarial' => "Tu gestión tiene bases, pero puedes profesionalizarla significativamente con formación adicional.",
                'Finanzas' => "Mantienes control financiero básico, pero necesitas herramientas más avanzadas para optimizar recursos.",
                'Marketing y Ventas' => "Tienes presencia en el mercado, pero puedes multiplicar resultados con estrategias más efectivas.",
                'Operaciones' => "Tus operaciones funcionan, pero hay oportunidades claras de mejora en eficiencia y calidad.",
                'Recursos Humanos' => "La gestión de tu equipo es funcional, pero puedes crear un ambiente más productivo y motivador."
            ]
        ];
        
        return $mensajes[$prioridad][$areaNombre] 
            ?? "Esta área muestra un puntaje de {$porcentaje}% y requiere atención para mejorar el desempeño general del negocio.";
    }
    
    /**
     * Generar acciones específicas por área
     */
    private function generarAccionesArea($areaNombre, $prioridad) {
        $accionesPorArea = [
            'Gestión Empresarial' => [
                'critica' => [
                    'Establecer un plan de negocio básico con objetivos claros',
                    'Implementar herramientas de organización (Trello, Asana)',
                    'Definir procesos clave del negocio por escrito',
                    'Tomar curso de fundamentos de gestión empresarial'
                ],
                'mejorable' => [
                    'Profesionalizar la planificación estratégica',
                    'Implementar indicadores KPI de gestión',
                    'Mejorar procesos de toma de decisiones',
                    'Capacitarse en liderazgo empresarial'
                ]
            ],
            'Finanzas' => [
                'critica' => [
                    'Implementar un sistema de control de ingresos/gastos diario',
                    'Separar finanzas personales de las del negocio',
                    'Crear un presupuesto mensual simple',
                    'Tomar curso de finanzas básicas para emprendedores'
                ],
                'mejorable' => [
                    'Elaborar proyecciones financieras trimestrales',
                    'Calcular punto de equilibrio y márgenes por producto',
                    'Implementar análisis de rentabilidad',
                    'Formarse en gestión financiera avanzada'
                ]
            ],
            'Marketing y Ventas' => [
                'critica' => [
                    'Definir claramente tu propuesta de valor',
                    'Identificar y perfilar tu cliente ideal',
                    'Establecer presencia en al menos 2 redes sociales',
                    'Tomar curso de marketing digital básico'
                ],
                'mejorable' => [
                    'Desarrollar estrategia de contenidos',
                    'Implementar embudo de ventas (funnel)',
                    'Medir conversiones y ROI de marketing',
                    'Capacitarse en marketing digital avanzado'
                ]
            ],
            'Operaciones' => [
                'critica' => [
                    'Documentar los procesos operativos principales',
                    'Establecer estándares de calidad básicos',
                    'Implementar control de inventarios simple',
                    'Tomar curso de gestión de operaciones'
                ],
                'mejorable' => [
                    'Optimizar tiempos y movimientos en procesos',
                    'Implementar metodología de mejora continua',
                    'Automatizar procesos repetitivos',
                    'Formarse en eficiencia operativa'
                ]
            ],
            'Recursos Humanos' => [
                'critica' => [
                    'Definir roles y responsabilidades claramente',
                    'Establecer un proceso de inducción para nuevos',
                    'Implementar comunicación regular con el equipo',
                    'Tomar curso de gestión de equipos'
                ],
                'mejorable' => [
                    'Crear plan de desarrollo para cada colaborador',
                    'Implementar sistema de evaluación de desempeño',
                    'Diseñar programa de capacitación continua',
                    'Formarse en liderazgo y motivación de equipos'
                ]
            ]
        ];
        
        return $accionesPorArea[$areaNombre][$prioridad] 
            ?? ['Revisar esta área en detalle', 'Buscar formación especializada', 'Implementar mejoras graduales'];
    }
    
    /**
     * Generar plan de acción priorizado
     */
    private function generarPlanAccion($recomendaciones) {
        $plan = [];
        $prioridad = 1;
        
        // Paso 1: Áreas críticas (máximo 2 en paralelo)
        $areasCriticas = array_slice($recomendaciones['areas_criticas'], 0, 2);
        foreach ($areasCriticas as $area) {
            $plan[] = [
                'paso' => $prioridad++,
                'plazo' => 'Inmediato (0-30 días)',
                'area' => $area['nombre'],
                'prioridad' => 'ALTA',
                'accion' => "Enfócate en {$area['nombre']}: " . ($area['acciones_sugeridas'][0] ?? 'Mejorar esta área'),
                'cursos_sugeridos' => array_slice($area['cursos_recomendados'], 0, 2)
            ];
        }
        
        // Paso 2: Áreas mejorables (siguientes 30-90 días)
        $areasMejorables = array_slice($recomendaciones['areas_mejorables'], 0, 2);
        foreach ($areasMejorables as $area) {
            $plan[] = [
                'paso' => $prioridad++,
                'plazo' => 'Corto plazo (30-90 días)',
                'area' => $area['nombre'],
                'prioridad' => 'MEDIA',
                'accion' => "Optimiza {$area['nombre']}: " . ($area['acciones_sugeridas'][0] ?? 'Mejorar esta área'),
                'cursos_sugeridos' => array_slice($area['cursos_recomendados'], 0, 2)
            ];
        }
        
        // Paso 3: Mantenimiento de áreas fuertes
        if (!empty($recomendaciones['areas_fuertes'])) {
            $plan[] = [
                'paso' => $prioridad++,
                'plazo' => 'Mediano plazo (90+ días)',
                'area' => 'Áreas Fuertes',
                'prioridad' => 'BAJA',
                'accion' => 'Mantén tus fortalezas actualizadas con formación continua',
                'cursos_sugeridos' => []
            ];
        }
        
        return $plan;
    }
    
    /**
     * Guardar recomendaciones en la base de datos
     */
    private function guardarRecomendaciones($diagnosticoId, $recomendaciones) {
        // Guardar solo las áreas críticas y mejorables en el campo recomendaciones_generadas
        $recomendacionesResumidas = [
            'areas_criticas' => count($recomendaciones['areas_criticas']),
            'areas_mejorables' => count($recomendaciones['areas_mejorables']),
            'plan_accion' => $recomendaciones['plan_accion']
        ];
        
        $query = "UPDATE diagnosticos_realizados 
                  SET recomendaciones_generadas = ?,
                      areas_fuertes = ?,
                      areas_mejora = ?
                  WHERE id_diagnostico_realizado = ?";
        
        $areasFuertes = json_encode(array_column($recomendaciones['areas_fuertes'], 'nombre'));
        $areasMejora = json_encode(array_merge(
            array_column($recomendaciones['areas_criticas'], 'nombre'),
            array_column($recomendaciones['areas_mejorables'], 'nombre')
        ));
        
        $params = [
            json_encode($recomendacionesResumidas),
            $areasFuertes,
            $areasMejora,
            $diagnosticoId
        ];
        
        return $this->db->query($query, $params);
    }
    
    /**
     * Obtener recomendaciones guardadas
     */
    public function obtenerRecomendaciones($diagnosticoId) {
        $query = "SELECT 
            recomendaciones_generadas,
            areas_fuertes,
            areas_mejora
        FROM diagnosticos_realizados
        WHERE id_diagnostico_realizado = ?";
        
        $result = $this->db->fetchOne($query, [$diagnosticoId]);
        
        if ($result && $result['recomendaciones_generadas']) {
            return [
                'recomendaciones' => json_decode($result['recomendaciones_generadas'], true),
                'areas_fuertes' => json_decode($result['areas_fuertes'], true),
                'areas_mejora' => json_decode($result['areas_mejora'], true)
            ];
        }
        
        return null;
    }
}
