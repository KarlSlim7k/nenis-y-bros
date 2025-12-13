-- ============================================================================
-- MIGRACIÓN: Crear tablas del módulo de Diagnósticos
-- ============================================================================
-- Fecha: 2025-12-13
-- Descripción: Crea las tablas necesarias para el módulo de diagnósticos
-- Orden de ejecución: 
--   1. tipos_diagnostico
--   2. areas_evaluacion  
--   3. preguntas_diagnostico
--   4. diagnosticos_realizados
--   5. respuestas_diagnostico
-- ============================================================================

-- ============================================================================
-- 1. TABLA: tipos_diagnostico
-- ============================================================================
CREATE TABLE IF NOT EXISTS `tipos_diagnostico` (
  `id_tipo_diagnostico` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `slug` varchar(220) NOT NULL,
  `duracion_estimada` int(11) DEFAULT 30 COMMENT 'Duración estimada en minutos',
  `nivel_detalle` enum('basico','intermedio','avanzado') DEFAULT 'basico',
  `icono` varchar(100) DEFAULT NULL COMMENT 'Emoji o URL del icono',
  `formula_calculo` longtext DEFAULT NULL COMMENT 'Configuración JSON de cómo calcular el puntaje',
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_tipo_diagnostico`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tipos de diagnósticos disponibles';

-- Datos iniciales
INSERT IGNORE INTO `tipos_diagnostico` (`id_tipo_diagnostico`, `nombre`, `descripcion`, `slug`, `duracion_estimada`, `nivel_detalle`, `icono`, `formula_calculo`, `activo`) VALUES
(1, 'Diagnóstico de Madurez Empresarial', 'Evalúa el nivel de desarrollo y madurez de tu negocio en áreas clave como gestión, finanzas, marketing, operaciones y recursos humanos.', 'madurez-empresarial', 25, 'intermedio', '📊', NULL, 1);

-- ============================================================================
-- 2. TABLA: areas_evaluacion
-- ============================================================================
CREATE TABLE IF NOT EXISTS `areas_evaluacion` (
  `id_area` int(11) NOT NULL AUTO_INCREMENT,
  `id_tipo_diagnostico` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `icono` varchar(100) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#667eea',
  `ponderacion` decimal(5,2) DEFAULT 100.00 COMMENT 'Peso del área en el resultado final',
  `orden` int(11) DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_area`),
  KEY `id_tipo_diagnostico` (`id_tipo_diagnostico`),
  CONSTRAINT `areas_evaluacion_ibfk_1` FOREIGN KEY (`id_tipo_diagnostico`) REFERENCES `tipos_diagnostico` (`id_tipo_diagnostico`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Áreas de evaluación de diagnósticos';

-- Datos iniciales para el diagnóstico de madurez empresarial
INSERT IGNORE INTO `areas_evaluacion` (`id_area`, `id_tipo_diagnostico`, `nombre`, `descripcion`, `icono`, `color`, `ponderacion`, `orden`) VALUES
(1, 1, 'Gestión y Planificación', 'Evalúa la capacidad de planificación estratégica, organización y liderazgo del negocio.', '📋', '#3b82f6', 20.00, 1),
(2, 1, 'Gestión Financiera', 'Mide el control financiero, contabilidad, presupuestos y análisis de costos.', '💰', '#10b981', 20.00, 2),
(3, 1, 'Marketing y Ventas', 'Evalúa estrategias de marketing, posicionamiento de marca y procesos de venta.', '📣', '#f59e0b', 20.00, 3),
(4, 1, 'Operaciones y Procesos', 'Analiza la eficiencia operativa, cadena de suministro y control de calidad.', '⚙️', '#8b5cf6', 20.00, 4),
(5, 1, 'Recursos Humanos', 'Evalúa gestión de talento, capacitación, cultura organizacional y estructura.', '👥', '#ec4899', 20.00, 5);

-- ============================================================================
-- 3. TABLA: preguntas_diagnostico
-- ============================================================================
CREATE TABLE IF NOT EXISTS `preguntas_diagnostico` (
  `id_pregunta` int(11) NOT NULL AUTO_INCREMENT,
  `id_area` int(11) NOT NULL,
  `pregunta` text NOT NULL,
  `descripcion_ayuda` text DEFAULT NULL COMMENT 'Texto de ayuda para entender la pregunta',
  `tipo_pregunta` enum('escala_numerica','multiple_choice','si_no','texto_corto','texto_largo') DEFAULT 'escala_numerica',
  `opciones` longtext DEFAULT NULL COMMENT 'Array JSON de opciones con sus valores',
  `escala_minima` int(11) DEFAULT 1,
  `escala_maxima` int(11) DEFAULT 5,
  `etiqueta_minima` varchar(100) DEFAULT NULL COMMENT 'Ej: Muy malo',
  `etiqueta_maxima` varchar(100) DEFAULT NULL COMMENT 'Ej: Excelente',
  `ponderacion` decimal(5,2) DEFAULT 1.00 COMMENT 'Peso de la pregunta en su área',
  `es_obligatoria` tinyint(1) DEFAULT 1,
  `orden` int(11) DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_pregunta`),
  KEY `id_area` (`id_area`),
  CONSTRAINT `preguntas_diagnostico_ibfk_1` FOREIGN KEY (`id_area`) REFERENCES `areas_evaluacion` (`id_area`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Preguntas de diagnósticos';

-- Preguntas iniciales para cada área
INSERT IGNORE INTO `preguntas_diagnostico` (`id_pregunta`, `id_area`, `pregunta`, `descripcion_ayuda`, `tipo_pregunta`, `escala_minima`, `escala_maxima`, `etiqueta_minima`, `etiqueta_maxima`, `ponderacion`, `es_obligatoria`, `orden`) VALUES
-- Área 1: Gestión y Planificación
(1, 1, '¿Tu empresa cuenta con un plan estratégico definido (misión, visión, objetivos)?', NULL, 'escala_numerica', 1, 5, 'No existe', 'Muy completo', 1.00, 1, 1),
(2, 1, '¿Qué tan claros están definidos los roles y responsabilidades en tu organización?', NULL, 'escala_numerica', 1, 5, 'Nada claros', 'Muy claros', 1.00, 1, 2),
(3, 1, '¿Con qué frecuencia se realizan reuniones de seguimiento y evaluación de objetivos?', NULL, 'escala_numerica', 1, 5, 'Nunca', 'Semanalmente', 1.00, 1, 3),
(4, 1, '¿Tu negocio cuenta con indicadores clave de desempeño (KPIs) establecidos?', NULL, 'escala_numerica', 1, 5, 'No tenemos', 'Sí, muy bien definidos', 1.00, 1, 4),
-- Área 2: Gestión Financiera
(5, 2, '¿Llevas un registro sistemático de ingresos y gastos?', NULL, 'escala_numerica', 1, 5, 'No llevo registro', 'Registro detallado', 1.00, 1, 1),
(6, 2, '¿Tu empresa cuenta con un presupuesto anual?', NULL, 'escala_numerica', 1, 5, 'No tenemos', 'Sí y lo seguimos', 1.00, 1, 2),
(7, 2, '¿Realizas análisis de rentabilidad de productos/servicios?', NULL, 'escala_numerica', 1, 5, 'Nunca', 'Regularmente', 1.00, 1, 3),
(8, 2, '¿Tienes control sobre el flujo de caja del negocio?', NULL, 'escala_numerica', 1, 5, 'Sin control', 'Control total', 1.00, 1, 4),
-- Área 3: Marketing y Ventas
(9, 3, '¿Tu negocio tiene una estrategia de marketing definida?', NULL, 'escala_numerica', 1, 5, 'No tenemos', 'Muy bien definida', 1.00, 1, 1),
(10, 3, '¿Conoces claramente quién es tu cliente ideal (buyer persona)?', NULL, 'escala_numerica', 1, 5, 'No lo sé', 'Muy bien definido', 1.00, 1, 2),
(11, 3, '¿Qué tan activa es tu presencia en redes sociales?', NULL, 'escala_numerica', 1, 5, 'No tenemos', 'Muy activa', 1.00, 1, 3),
(12, 3, '¿Tienes un proceso de ventas estandarizado?', NULL, 'escala_numerica', 1, 5, 'No existe', 'Muy estandarizado', 1.00, 1, 4),
-- Área 4: Operaciones y Procesos
(13, 4, '¿Tus procesos operativos están documentados?', NULL, 'escala_numerica', 1, 5, 'No documentados', 'Muy bien documentados', 1.00, 1, 1),
(14, 4, '¿Utilizas tecnología para mejorar la eficiencia operativa?', NULL, 'escala_numerica', 1, 5, 'No usamos', 'Uso avanzado', 1.00, 1, 2),
(15, 4, '¿Cómo evalúas el control de calidad de tus productos/servicios?', NULL, 'escala_numerica', 1, 5, 'Sin control', 'Control riguroso', 1.00, 1, 3),
(16, 4, '¿Qué tan eficiente es tu cadena de suministro?', NULL, 'escala_numerica', 1, 5, 'Ineficiente', 'Muy eficiente', 1.00, 1, 4),
-- Área 5: Recursos Humanos
(17, 5, '¿Tu empresa cuenta con descripciones de puestos de trabajo?', NULL, 'escala_numerica', 1, 5, 'No tenemos', 'Muy detalladas', 1.00, 1, 1),
(18, 5, '¿Ofreces capacitación y desarrollo a tu equipo?', NULL, 'escala_numerica', 1, 5, 'Nunca', 'Regularmente', 1.00, 1, 2),
(19, 5, '¿Cómo evalúas el clima laboral en tu organización?', NULL, 'escala_numerica', 1, 5, 'Muy malo', 'Excelente', 1.00, 1, 3),
(20, 5, '¿Tienes un proceso de evaluación de desempeño establecido?', NULL, 'escala_numerica', 1, 5, 'No existe', 'Muy estructurado', 1.00, 1, 4);

-- ============================================================================
-- 4. TABLA: diagnosticos_realizados
-- ============================================================================
CREATE TABLE IF NOT EXISTS `diagnosticos_realizados` (
  `id_diagnostico_realizado` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_perfil_empresarial` int(11) DEFAULT NULL,
  `id_tipo_diagnostico` int(11) NOT NULL,
  `estado` enum('en_progreso','completado','abandonado') DEFAULT 'en_progreso',
  `puntaje_total` decimal(5,2) DEFAULT NULL,
  `nivel_madurez` enum('inicial','basico','intermedio','avanzado','experto') DEFAULT NULL,
  `resultados_areas` longtext DEFAULT NULL COMMENT 'Puntajes y análisis por área (JSON)',
  `areas_fuertes` longtext DEFAULT NULL COMMENT 'Top 3 áreas fuertes (JSON)',
  `areas_mejora` longtext DEFAULT NULL COMMENT 'Top 3 áreas a mejorar (JSON)',
  `recomendaciones_generadas` longtext DEFAULT NULL COMMENT 'Recomendaciones automáticas (JSON)',
  `tiempo_dedicado` int(11) DEFAULT 0 COMMENT 'Tiempo en minutos',
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_completado` datetime DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_diagnostico_realizado`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_tipo_diagnostico` (`id_tipo_diagnostico`),
  KEY `id_perfil_empresarial` (`id_perfil_empresarial`),
  CONSTRAINT `diagnosticos_realizados_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `diagnosticos_realizados_ibfk_3` FOREIGN KEY (`id_tipo_diagnostico`) REFERENCES `tipos_diagnostico` (`id_tipo_diagnostico`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Diagnósticos realizados por usuarios';

-- ============================================================================
-- 5. TABLA: respuestas_diagnostico
-- ============================================================================
CREATE TABLE IF NOT EXISTS `respuestas_diagnostico` (
  `id_respuesta` int(11) NOT NULL AUTO_INCREMENT,
  `id_diagnostico_realizado` int(11) NOT NULL,
  `id_pregunta` int(11) NOT NULL,
  `valor_respuesta` text DEFAULT NULL COMMENT 'Respuesta dada por el usuario',
  `puntaje_obtenido` decimal(5,2) DEFAULT NULL,
  `fecha_respuesta` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_respuesta`),
  UNIQUE KEY `unique_respuesta` (`id_diagnostico_realizado`, `id_pregunta`),
  KEY `id_pregunta` (`id_pregunta`),
  CONSTRAINT `respuestas_diagnostico_ibfk_1` FOREIGN KEY (`id_diagnostico_realizado`) REFERENCES `diagnosticos_realizados` (`id_diagnostico_realizado`) ON DELETE CASCADE,
  CONSTRAINT `respuestas_diagnostico_ibfk_2` FOREIGN KEY (`id_pregunta`) REFERENCES `preguntas_diagnostico` (`id_pregunta`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Respuestas de usuarios a diagnósticos';

-- ============================================================================
-- VERIFICACIÓN
-- ============================================================================
SELECT 'Tablas creadas exitosamente' AS mensaje;

SELECT 
    'tipos_diagnostico' as tabla, COUNT(*) as registros FROM tipos_diagnostico
UNION ALL
SELECT 
    'areas_evaluacion' as tabla, COUNT(*) as registros FROM areas_evaluacion
UNION ALL
SELECT 
    'preguntas_diagnostico' as tabla, COUNT(*) as registros FROM preguntas_diagnostico
UNION ALL
SELECT 
    'diagnosticos_realizados' as tabla, COUNT(*) as registros FROM diagnosticos_realizados
UNION ALL
SELECT 
    'respuestas_diagnostico' as tabla, COUNT(*) as registros FROM respuestas_diagnostico;
