-- ============================================================================
-- MIGRACIÓN FASE 3: PERFILES EMPRESARIALES Y DIAGNÓSTICOS
-- ============================================================================
-- Fecha: 15 de Noviembre 2025
-- Descripción: Tablas para gestión de perfiles empresariales, diagnósticos,
--              análisis de resultados y sistema de recomendaciones
-- ============================================================================

USE formacion_empresarial;

-- ============================================================================
-- TABLA: perfiles_empresariales
-- Descripción: Información de los negocios de los usuarios
-- ============================================================================
CREATE TABLE IF NOT EXISTS perfiles_empresariales (
    id_perfil INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    
    -- Información básica del negocio
    nombre_empresa VARCHAR(200) NOT NULL,
    logo_empresa VARCHAR(255),
    eslogan VARCHAR(300),
    descripcion TEXT,
    
    -- Clasificación del negocio
    sector VARCHAR(100) COMMENT 'Ej: Tecnología, Comercio, Servicios, Manufactura, etc.',
    tipo_negocio ENUM('emprendimiento', 'microempresa', 'pequeña_empresa', 'mediana_empresa', 'grande') DEFAULT 'emprendimiento',
    etapa_negocio ENUM('idea', 'inicio', 'crecimiento', 'consolidacion', 'expansion') DEFAULT 'idea',
    
    -- Datos operativos
    anio_fundacion INT,
    numero_empleados INT DEFAULT 0,
    facturacion_anual DECIMAL(15, 2),
    
    -- Contacto empresarial
    email_empresa VARCHAR(150),
    telefono_empresa VARCHAR(20),
    sitio_web VARCHAR(255),
    
    -- Ubicación
    direccion TEXT,
    ciudad VARCHAR(100),
    estado VARCHAR(100),
    pais VARCHAR(100),
    codigo_postal VARCHAR(20),
    
    -- Redes sociales
    redes_sociales JSON COMMENT 'URLs de redes sociales',
    
    -- Visibilidad
    perfil_publico BOOLEAN DEFAULT TRUE,
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    
    INDEX idx_usuario (id_usuario),
    INDEX idx_sector (sector),
    INDEX idx_tipo (tipo_negocio),
    INDEX idx_etapa (etapa_negocio),
    INDEX idx_publico (perfil_publico)
) ENGINE=InnoDB COMMENT='Perfiles empresariales de usuarios';

-- ============================================================================
-- TABLA: tipos_diagnostico
-- Descripción: Catálogo de diagnósticos disponibles
-- ============================================================================
CREATE TABLE IF NOT EXISTS tipos_diagnostico (
    id_tipo_diagnostico INT AUTO_INCREMENT PRIMARY KEY,
    
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    slug VARCHAR(220) UNIQUE NOT NULL,
    
    -- Configuración
    duracion_estimada INT DEFAULT 30 COMMENT 'Duración estimada en minutos',
    nivel_detalle ENUM('basico', 'intermedio', 'avanzado') DEFAULT 'basico',
    
    -- Fórmula de cálculo (JSON con ponderaciones)
    formula_calculo JSON COMMENT 'Configuración de cómo calcular el puntaje',
    
    -- Estado
    activo BOOLEAN DEFAULT TRUE,
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_slug (slug),
    INDEX idx_activo (activo)
) ENGINE=InnoDB COMMENT='Tipos de diagnósticos disponibles';

-- ============================================================================
-- TABLA: areas_evaluacion
-- Descripción: Áreas que se evalúan en cada diagnóstico
-- ============================================================================
CREATE TABLE IF NOT EXISTS areas_evaluacion (
    id_area INT AUTO_INCREMENT PRIMARY KEY,
    id_tipo_diagnostico INT NOT NULL,
    
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    icono VARCHAR(100),
    color VARCHAR(7) DEFAULT '#667eea',
    
    -- Ponderación del área en el diagnóstico total
    ponderacion DECIMAL(5, 2) DEFAULT 100.00 COMMENT 'Peso del área en el resultado final',
    
    orden INT DEFAULT 0,
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_tipo_diagnostico) REFERENCES tipos_diagnostico(id_tipo_diagnostico) ON DELETE CASCADE,
    
    INDEX idx_diagnostico (id_tipo_diagnostico),
    INDEX idx_orden (orden)
) ENGINE=InnoDB COMMENT='Áreas de evaluación de diagnósticos';

-- ============================================================================
-- TABLA: preguntas_diagnostico
-- Descripción: Preguntas de cada área de diagnóstico
-- ============================================================================
CREATE TABLE IF NOT EXISTS preguntas_diagnostico (
    id_pregunta INT AUTO_INCREMENT PRIMARY KEY,
    id_area INT NOT NULL,
    
    pregunta TEXT NOT NULL,
    descripcion_ayuda TEXT COMMENT 'Texto de ayuda para entender la pregunta',
    
    -- Tipo de pregunta
    tipo_pregunta ENUM('multiple_choice', 'escala', 'si_no', 'texto', 'numerica') DEFAULT 'multiple_choice',
    
    -- Opciones para preguntas de opción múltiple (JSON)
    opciones JSON COMMENT 'Array de opciones con sus valores',
    
    -- Configuración de escala (si aplica)
    escala_minima INT DEFAULT 1,
    escala_maxima INT DEFAULT 5,
    etiqueta_minima VARCHAR(100) COMMENT 'Ej: Muy malo',
    etiqueta_maxima VARCHAR(100) COMMENT 'Ej: Excelente',
    
    -- Ponderación
    ponderacion DECIMAL(5, 2) DEFAULT 1.00 COMMENT 'Peso de la pregunta en su área',
    
    -- Validación
    es_obligatoria BOOLEAN DEFAULT TRUE,
    
    orden INT DEFAULT 0,
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_area) REFERENCES areas_evaluacion(id_area) ON DELETE CASCADE,
    
    INDEX idx_area (id_area),
    INDEX idx_tipo (tipo_pregunta),
    INDEX idx_orden (orden)
) ENGINE=InnoDB COMMENT='Preguntas de diagnósticos';

-- ============================================================================
-- TABLA: diagnosticos_realizados
-- Descripción: Registro de diagnósticos completados por usuarios
-- ============================================================================
CREATE TABLE IF NOT EXISTS diagnosticos_realizados (
    id_diagnostico_realizado INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_perfil_empresarial INT,
    id_tipo_diagnostico INT NOT NULL,
    
    -- Estado del diagnóstico
    estado ENUM('en_progreso', 'completado', 'abandonado') DEFAULT 'en_progreso',
    
    -- Resultados globales
    puntaje_total DECIMAL(5, 2),
    nivel_madurez ENUM('inicial', 'basico', 'intermedio', 'avanzado', 'experto'),
    
    -- Resultados por área (JSON)
    resultados_areas JSON COMMENT 'Puntajes y análisis por área',
    
    -- Análisis y recomendaciones
    areas_fuertes JSON COMMENT 'Top 3 áreas fuertes',
    areas_mejora JSON COMMENT 'Top 3 áreas a mejorar',
    recomendaciones_generadas JSON COMMENT 'Recomendaciones automáticas',
    
    -- Control de tiempo
    tiempo_dedicado INT DEFAULT 0 COMMENT 'Tiempo en minutos',
    
    fecha_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_completado DATETIME,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_perfil_empresarial) REFERENCES perfiles_empresariales(id_perfil) ON DELETE SET NULL,
    FOREIGN KEY (id_tipo_diagnostico) REFERENCES tipos_diagnostico(id_tipo_diagnostico) ON DELETE CASCADE,
    
    INDEX idx_usuario (id_usuario),
    INDEX idx_perfil (id_perfil_empresarial),
    INDEX idx_tipo (id_tipo_diagnostico),
    INDEX idx_estado (estado),
    INDEX idx_fecha_completado (fecha_completado)
) ENGINE=InnoDB COMMENT='Diagnósticos realizados por usuarios';

-- ============================================================================
-- TABLA: respuestas_diagnostico
-- Descripción: Respuestas individuales a preguntas de diagnóstico
-- ============================================================================
CREATE TABLE IF NOT EXISTS respuestas_diagnostico (
    id_respuesta INT AUTO_INCREMENT PRIMARY KEY,
    id_diagnostico_realizado INT NOT NULL,
    id_pregunta INT NOT NULL,
    
    -- Respuesta (dependiendo del tipo de pregunta)
    respuesta_valor DECIMAL(10, 2) COMMENT 'Para preguntas numéricas o de escala',
    respuesta_texto TEXT COMMENT 'Para preguntas de texto libre',
    respuesta_opcion VARCHAR(255) COMMENT 'Para preguntas de opción múltiple',
    respuesta_si_no BOOLEAN COMMENT 'Para preguntas de sí/no',
    
    -- Puntaje calculado para esta respuesta
    puntaje_obtenido DECIMAL(5, 2),
    
    fecha_respuesta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_diagnostico_realizado) REFERENCES diagnosticos_realizados(id_diagnostico_realizado) ON DELETE CASCADE,
    FOREIGN KEY (id_pregunta) REFERENCES preguntas_diagnostico(id_pregunta) ON DELETE CASCADE,
    
    UNIQUE KEY unique_respuesta (id_diagnostico_realizado, id_pregunta),
    INDEX idx_diagnostico (id_diagnostico_realizado),
    INDEX idx_pregunta (id_pregunta)
) ENGINE=InnoDB COMMENT='Respuestas a preguntas de diagnósticos';

-- ============================================================================
-- TABLA: recomendaciones_cursos
-- Descripción: Relación entre áreas de diagnóstico y cursos recomendados
-- ============================================================================
CREATE TABLE IF NOT EXISTS recomendaciones_cursos (
    id_recomendacion INT AUTO_INCREMENT PRIMARY KEY,
    id_area INT NOT NULL,
    id_curso INT NOT NULL,
    
    -- Rango de puntaje para el cual aplica esta recomendación
    puntaje_minimo DECIMAL(5, 2) DEFAULT 0.00,
    puntaje_maximo DECIMAL(5, 2) DEFAULT 100.00,
    
    -- Nivel de prioridad
    prioridad ENUM('baja', 'media', 'alta', 'critica') DEFAULT 'media',
    
    -- Justificación de la recomendación
    justificacion TEXT,
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_area) REFERENCES areas_evaluacion(id_area) ON DELETE CASCADE,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE,
    
    INDEX idx_area (id_area),
    INDEX idx_curso (id_curso),
    INDEX idx_prioridad (prioridad)
) ENGINE=InnoDB COMMENT='Recomendaciones de cursos por área y puntaje';

-- ============================================================================
-- DATOS INICIALES: Diagnóstico de Madurez Empresarial Básico
-- ============================================================================

-- Tipo de diagnóstico
INSERT INTO tipos_diagnostico (nombre, descripcion, slug, duracion_estimada, nivel_detalle, activo) VALUES
('Diagnóstico de Madurez Empresarial', 
 'Evalúa el nivel de desarrollo y madurez de tu negocio en áreas clave como gestión, finanzas, marketing, operaciones y recursos humanos.',
 'madurez-empresarial',
 25,
 'intermedio',
 TRUE);

SET @diagnostico_id = LAST_INSERT_ID();

-- Áreas de evaluación
INSERT INTO areas_evaluacion (id_tipo_diagnostico, nombre, descripcion, icono, color, ponderacion, orden) VALUES
(@diagnostico_id, 'Gestión y Planificación', 'Evalúa la capacidad de planificación estratégica, organización y liderazgo del negocio.', 'clipboard-list', '#3b82f6', 20.00, 1),
(@diagnostico_id, 'Gestión Financiera', 'Mide el control financiero, contabilidad, presupuestos y análisis de costos.', 'dollar-sign', '#10b981', 20.00, 2),
(@diagnostico_id, 'Marketing y Ventas', 'Evalúa estrategias de marketing, posicionamiento de marca y procesos de venta.', 'bullhorn', '#f59e0b', 20.00, 3),
(@diagnostico_id, 'Operaciones y Procesos', 'Analiza la eficiencia operativa, cadena de suministro y control de calidad.', 'cog', '#8b5cf6', 20.00, 4),
(@diagnostico_id, 'Recursos Humanos', 'Evalúa gestión de talento, capacitación, cultura organizacional y estructura.', 'users', '#ec4899', 20.00, 5);

-- Preguntas para Gestión y Planificación
SET @area_gestion = (SELECT id_area FROM areas_evaluacion WHERE nombre = 'Gestión y Planificación' AND id_tipo_diagnostico = @diagnostico_id);

INSERT INTO preguntas_diagnostico (id_area, pregunta, tipo_pregunta, escala_minima, escala_maxima, etiqueta_minima, etiqueta_maxima, ponderacion, orden) VALUES
(@area_gestion, '¿Tu empresa cuenta con un plan estratégico definido (misión, visión, objetivos)?', 'escala', 1, 5, 'No existe', 'Muy completo', 1.00, 1),
(@area_gestion, '¿Qué tan claros están definidos los roles y responsabilidades en tu organización?', 'escala', 1, 5, 'Nada claros', 'Muy claros', 1.00, 2),
(@area_gestion, '¿Con qué frecuencia se realizan reuniones de seguimiento y evaluación de objetivos?', 'escala', 1, 5, 'Nunca', 'Semanalmente', 1.00, 3),
(@area_gestion, '¿Tu negocio cuenta con indicadores clave de desempeño (KPIs) establecidos?', 'escala', 1, 5, 'No tenemos', 'Sí, muy bien definidos', 1.00, 4);

-- Preguntas para Gestión Financiera
SET @area_finanzas = (SELECT id_area FROM areas_evaluacion WHERE nombre = 'Gestión Financiera' AND id_tipo_diagnostico = @diagnostico_id);

INSERT INTO preguntas_diagnostico (id_area, pregunta, tipo_pregunta, escala_minima, escala_maxima, etiqueta_minima, etiqueta_maxima, ponderacion, orden) VALUES
(@area_finanzas, '¿Llevas un registro sistemático de ingresos y gastos?', 'escala', 1, 5, 'No llevo registro', 'Registro detallado', 1.00, 1),
(@area_finanzas, '¿Tu empresa cuenta con un presupuesto anual?', 'escala', 1, 5, 'No tenemos', 'Sí y lo seguimos', 1.00, 2),
(@area_finanzas, '¿Realizas análisis de rentabilidad de productos/servicios?', 'escala', 1, 5, 'Nunca', 'Regularmente', 1.00, 3),
(@area_finanzas, '¿Tienes control sobre el flujo de caja del negocio?', 'escala', 1, 5, 'Sin control', 'Control total', 1.00, 4);

-- Preguntas para Marketing y Ventas
SET @area_marketing = (SELECT id_area FROM areas_evaluacion WHERE nombre = 'Marketing y Ventas' AND id_tipo_diagnostico = @diagnostico_id);

INSERT INTO preguntas_diagnostico (id_area, pregunta, tipo_pregunta, escala_minima, escala_maxima, etiqueta_minima, etiqueta_maxima, ponderacion, orden) VALUES
(@area_marketing, '¿Tu negocio tiene una estrategia de marketing definida?', 'escala', 1, 5, 'No tenemos', 'Muy bien definida', 1.00, 1),
(@area_marketing, '¿Conoces claramente quién es tu cliente ideal (buyer persona)?', 'escala', 1, 5, 'No lo sé', 'Muy bien definido', 1.00, 2),
(@area_marketing, '¿Qué tan activa es tu presencia en redes sociales?', 'escala', 1, 5, 'No tenemos', 'Muy activa', 1.00, 3),
(@area_marketing, '¿Tienes un proceso de ventas estandarizado?', 'escala', 1, 5, 'No existe', 'Muy estandarizado', 1.00, 4);

-- Preguntas para Operaciones y Procesos
SET @area_operaciones = (SELECT id_area FROM areas_evaluacion WHERE nombre = 'Operaciones y Procesos' AND id_tipo_diagnostico = @diagnostico_id);

INSERT INTO preguntas_diagnostico (id_area, pregunta, tipo_pregunta, escala_minima, escala_maxima, etiqueta_minima, etiqueta_maxima, ponderacion, orden) VALUES
(@area_operaciones, '¿Tus procesos operativos están documentados?', 'escala', 1, 5, 'No documentados', 'Muy bien documentados', 1.00, 1),
(@area_operaciones, '¿Utilizas tecnología para mejorar la eficiencia operativa?', 'escala', 1, 5, 'No usamos', 'Uso avanzado', 1.00, 2),
(@area_operaciones, '¿Cómo evalúas el control de calidad de tus productos/servicios?', 'escala', 1, 5, 'Sin control', 'Control riguroso', 1.00, 3),
(@area_operaciones, '¿Qué tan eficiente es tu cadena de suministro?', 'escala', 1, 5, 'Ineficiente', 'Muy eficiente', 1.00, 4);

-- Preguntas para Recursos Humanos
SET @area_rrhh = (SELECT id_area FROM areas_evaluacion WHERE nombre = 'Recursos Humanos' AND id_tipo_diagnostico = @diagnostico_id);

INSERT INTO preguntas_diagnostico (id_area, pregunta, tipo_pregunta, escala_minima, escala_maxima, etiqueta_minima, etiqueta_maxima, ponderacion, orden) VALUES
(@area_rrhh, '¿Tu empresa cuenta con descripciones de puestos de trabajo?', 'escala', 1, 5, 'No tenemos', 'Muy detalladas', 1.00, 1),
(@area_rrhh, '¿Ofreces capacitación y desarrollo a tu equipo?', 'escala', 1, 5, 'Nunca', 'Regularmente', 1.00, 2),
(@area_rrhh, '¿Cómo evalúas el clima laboral en tu organización?', 'escala', 1, 5, 'Muy malo', 'Excelente', 1.00, 3),
(@area_rrhh, '¿Tienes un proceso de evaluación de desempeño establecido?', 'escala', 1, 5, 'No existe', 'Muy estructurado', 1.00, 4);

-- ============================================================================
-- VERIFICACIÓN
-- ============================================================================

SELECT 'Migración Fase 3 completada exitosamente' AS resultado;
SELECT COUNT(*) AS tipos_diagnostico FROM tipos_diagnostico;
SELECT COUNT(*) AS areas_evaluacion FROM areas_evaluacion;
SELECT COUNT(*) AS preguntas_diagnostico FROM preguntas_diagnostico;
