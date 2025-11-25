-- ============================================================================
-- MIGRACIÓN: FASE 2B - SISTEMA DE EVALUACIONES
-- ============================================================================
-- Fecha: 18 de noviembre de 2025
-- Descripción: Agrega tablas para quizzes, preguntas, respuestas y certificados
-- ============================================================================

USE formacion_empresarial;

-- ============================================================================
-- TABLA: evaluaciones
-- Descripción: Evaluaciones/quizzes asociados a lecciones o cursos
-- ============================================================================
CREATE TABLE IF NOT EXISTS evaluaciones (
    id_evaluacion INT AUTO_INCREMENT PRIMARY KEY,
    id_leccion INT,
    id_curso INT,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    
    -- Configuración
    tipo_evaluacion ENUM('quiz', 'examen', 'practica') NOT NULL DEFAULT 'quiz',
    duracion_minutos INT COMMENT 'Duración en minutos (0 = sin límite)',
    intentos_permitidos INT DEFAULT 3 COMMENT 'Número de intentos (0 = ilimitados)',
    puntaje_minimo_aprobacion DECIMAL(5,2) DEFAULT 70.00 COMMENT 'Porcentaje mínimo para aprobar',
    
    -- Opciones
    mostrar_resultados_inmediatos BOOLEAN DEFAULT TRUE,
    permitir_revision BOOLEAN DEFAULT TRUE,
    barajar_preguntas BOOLEAN DEFAULT FALSE,
    barajar_opciones BOOLEAN DEFAULT FALSE,
    
    -- Estado
    estado ENUM('borrador', 'publicado', 'archivado') NOT NULL DEFAULT 'borrador',
    
    -- Auditoría
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_leccion) REFERENCES lecciones(id_leccion) ON DELETE CASCADE,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE,
    INDEX idx_leccion (id_leccion),
    INDEX idx_curso (id_curso),
    INDEX idx_estado (estado)
) ENGINE=InnoDB COMMENT='Evaluaciones y quizzes del sistema';

-- ============================================================================
-- TABLA: preguntas_evaluacion
-- Descripción: Preguntas de las evaluaciones
-- ============================================================================
CREATE TABLE IF NOT EXISTS preguntas_evaluacion (
    id_pregunta_evaluacion INT AUTO_INCREMENT PRIMARY KEY,
    id_evaluacion INT NOT NULL,
    
    -- Contenido
    pregunta_texto TEXT NOT NULL,
    tipo_pregunta ENUM('multiple_choice', 'verdadero_falso', 'respuesta_corta', 'texto_libre') NOT NULL DEFAULT 'multiple_choice',
    
    -- Configuración
    puntos DECIMAL(5,2) DEFAULT 1.00,
    orden INT DEFAULT 0,
    
    -- Explicación/retroalimentación
    explicacion TEXT COMMENT 'Explicación de la respuesta correcta',
    
    -- Auditoría
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_evaluacion) REFERENCES evaluaciones(id_evaluacion) ON DELETE CASCADE,
    INDEX idx_evaluacion (id_evaluacion),
    INDEX idx_orden (orden)
) ENGINE=InnoDB COMMENT='Preguntas de las evaluaciones';

-- ============================================================================
-- TABLA: opciones_pregunta
-- Descripción: Opciones de respuesta para preguntas de opción múltiple
-- ============================================================================
CREATE TABLE IF NOT EXISTS opciones_pregunta (
    id_opcion INT AUTO_INCREMENT PRIMARY KEY,
    id_pregunta_evaluacion INT NOT NULL,
    
    -- Contenido
    texto_opcion TEXT NOT NULL,
    es_correcta BOOLEAN DEFAULT FALSE,
    orden INT DEFAULT 0,
    
    -- Retroalimentación específica
    feedback TEXT COMMENT 'Feedback al seleccionar esta opción',
    
    FOREIGN KEY (id_pregunta_evaluacion) REFERENCES preguntas_evaluacion(id_pregunta_evaluacion) ON DELETE CASCADE,
    INDEX idx_pregunta (id_pregunta_evaluacion)
) ENGINE=InnoDB COMMENT='Opciones de respuesta para preguntas';

-- ============================================================================
-- TABLA: intentos_evaluacion
-- Descripción: Registros de intentos de evaluación por usuario
-- ============================================================================
CREATE TABLE IF NOT EXISTS intentos_evaluacion (
    id_intento INT AUTO_INCREMENT PRIMARY KEY,
    id_evaluacion INT NOT NULL,
    id_usuario INT NOT NULL,
    
    -- Resultado
    puntaje_obtenido DECIMAL(5,2) DEFAULT 0.00,
    puntaje_maximo DECIMAL(5,2) DEFAULT 0.00,
    porcentaje DECIMAL(5,2) DEFAULT 0.00,
    aprobado BOOLEAN DEFAULT FALSE,
    
    -- Estado
    estado ENUM('en_progreso', 'completado', 'abandonado') NOT NULL DEFAULT 'en_progreso',
    
    -- Tiempo
    fecha_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_finalizacion DATETIME,
    tiempo_transcurrido INT COMMENT 'Tiempo en segundos',
    
    -- Número de intento
    numero_intento INT DEFAULT 1,
    
    FOREIGN KEY (id_evaluacion) REFERENCES evaluaciones(id_evaluacion) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    INDEX idx_evaluacion_usuario (id_evaluacion, id_usuario),
    INDEX idx_usuario (id_usuario),
    INDEX idx_fecha (fecha_inicio)
) ENGINE=InnoDB COMMENT='Intentos de evaluación de usuarios';

-- ============================================================================
-- TABLA: respuestas_evaluacion
-- Descripción: Respuestas dadas por usuarios en sus intentos
-- ============================================================================
CREATE TABLE IF NOT EXISTS respuestas_evaluacion (
    id_respuesta_evaluacion INT AUTO_INCREMENT PRIMARY KEY,
    id_intento INT NOT NULL,
    id_pregunta_evaluacion INT NOT NULL,
    
    -- Respuesta
    id_opcion_seleccionada INT COMMENT 'Para preguntas de opción múltiple',
    respuesta_texto TEXT COMMENT 'Para preguntas de texto',
    
    -- Evaluación
    es_correcta BOOLEAN,
    puntos_obtenidos DECIMAL(5,2) DEFAULT 0.00,
    
    -- Auditoría
    fecha_respuesta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_intento) REFERENCES intentos_evaluacion(id_intento) ON DELETE CASCADE,
    FOREIGN KEY (id_pregunta_evaluacion) REFERENCES preguntas_evaluacion(id_pregunta_evaluacion) ON DELETE CASCADE,
    FOREIGN KEY (id_opcion_seleccionada) REFERENCES opciones_pregunta(id_opcion) ON DELETE SET NULL,
    INDEX idx_intento (id_intento),
    INDEX idx_pregunta (id_pregunta_evaluacion)
) ENGINE=InnoDB COMMENT='Respuestas de usuarios en evaluaciones';

-- ============================================================================
-- TABLA: certificados
-- Descripción: Certificados generados al completar cursos
-- ============================================================================
CREATE TABLE IF NOT EXISTS certificados (
    id_certificado INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_curso INT NOT NULL,
    
    -- Información del certificado
    codigo_certificado VARCHAR(50) UNIQUE NOT NULL COMMENT 'Código único de verificación',
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    
    -- Datos de finalización
    fecha_emision DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    puntaje_final DECIMAL(5,2) COMMENT 'Puntuación final del curso',
    horas_completadas INT COMMENT 'Horas invertidas',
    
    -- Instructor/emisor
    id_instructor INT COMMENT 'Instructor que emite el certificado',
    nombre_instructor VARCHAR(200),
    
    -- Archivo
    archivo_pdf VARCHAR(255) COMMENT 'Ruta al PDF generado',
    
    -- Validez
    valido BOOLEAN DEFAULT TRUE,
    fecha_revocacion DATETIME,
    motivo_revocacion TEXT,
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE,
    FOREIGN KEY (id_instructor) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    UNIQUE KEY unique_usuario_curso (id_usuario, id_curso),
    INDEX idx_codigo (codigo_certificado),
    INDEX idx_usuario (id_usuario),
    INDEX idx_curso (id_curso),
    INDEX idx_fecha (fecha_emision)
) ENGINE=InnoDB COMMENT='Certificados de finalización de cursos';

-- ============================================================================
-- TABLA: prerrequisitos_curso
-- Descripción: Define cursos prerequisitos para otros cursos
-- ============================================================================
CREATE TABLE IF NOT EXISTS prerrequisitos_curso (
    id_prerrequisito INT AUTO_INCREMENT PRIMARY KEY,
    id_curso INT NOT NULL COMMENT 'Curso que tiene el prerrequisito',
    id_curso_requerido INT NOT NULL COMMENT 'Curso que se debe completar primero',
    obligatorio BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE,
    FOREIGN KEY (id_curso_requerido) REFERENCES cursos(id_curso) ON DELETE CASCADE,
    UNIQUE KEY unique_prerrequisito (id_curso, id_curso_requerido),
    INDEX idx_curso (id_curso)
) ENGINE=InnoDB COMMENT='Prerrequisitos entre cursos';

-- ============================================================================
-- VISTA: resumen_evaluaciones_usuario
-- Descripción: Resumen de evaluaciones por usuario
-- ============================================================================
CREATE OR REPLACE VIEW resumen_evaluaciones_usuario AS
SELECT 
    u.id_usuario,
    u.nombre,
    u.apellido,
    e.id_evaluacion,
    e.titulo as evaluacion_titulo,
    COUNT(ie.id_intento) as total_intentos,
    MAX(ie.porcentaje) as mejor_porcentaje,
    AVG(ie.porcentaje) as promedio_porcentaje,
    MAX(CASE WHEN ie.aprobado = TRUE THEN 1 ELSE 0 END) as ha_aprobado,
    MAX(ie.fecha_finalizacion) as ultima_fecha
FROM usuarios u
INNER JOIN intentos_evaluacion ie ON u.id_usuario = ie.id_usuario
INNER JOIN evaluaciones e ON ie.id_evaluacion = e.id_evaluacion
WHERE ie.estado = 'completado'
GROUP BY u.id_usuario, u.nombre, u.apellido, e.id_evaluacion, e.titulo;

-- ============================================================================
-- Datos de prueba
-- ============================================================================

-- Insertar evaluación de ejemplo (requiere que existan cursos/lecciones)
-- Esto se hará después en un script separado de test_data

-- ============================================================================
-- FIN DE MIGRACIÓN
-- ============================================================================
