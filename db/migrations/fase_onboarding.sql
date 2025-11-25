-- ============================================================================
-- MIGRACIÓN FASE ONBOARDING: CUESTIONARIO INICIAL
-- ============================================================================
-- Fecha: 25 de Noviembre 2025
-- Descripción: Tablas para el sistema de onboarding y cuestionario diagnóstico inicial
-- ============================================================================

-- Asegurar codificación UTF8
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

USE formacion_empresarial;

-- ============================================================================
-- TABLA: cuestionario_inicial
-- Descripción: Registro de cuestionarios realizados por usuarios (registrados o no)
-- ============================================================================
CREATE TABLE IF NOT EXISTS cuestionario_inicial (
    id_cuestionario INT AUTO_INCREMENT PRIMARY KEY,
    token_temporal VARCHAR(64) UNIQUE COMMENT 'Token para identificar cuestionarios de usuarios no registrados',
    
    -- Resultados
    puntaje_total DECIMAL(5,2) DEFAULT 0.00,
    nivel_determinado ENUM('principiante', 'intermedio', 'avanzado') DEFAULT 'principiante',
    
    -- Relación con usuario (se llena al registrarse)
    id_usuario INT NULL,
    
    -- Estado
    completado BOOLEAN DEFAULT FALSE,
    fecha_realizacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_registro DATETIME ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha cuando se asoció al usuario',
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    
    INDEX idx_token (token_temporal),
    INDEX idx_usuario (id_usuario),
    INDEX idx_nivel (nivel_determinado)
) ENGINE=InnoDB COMMENT='Cuestionarios iniciales de onboarding';

-- ============================================================================
-- TABLA: preguntas_cuestionario_inicial
-- Descripción: Preguntas para el cuestionario de onboarding
-- ============================================================================
CREATE TABLE IF NOT EXISTS preguntas_cuestionario_inicial (
    id_pregunta INT AUTO_INCREMENT PRIMARY KEY,
    
    pregunta TEXT NOT NULL,
    categoria ENUM('experiencia', 'conocimientos', 'negocio', 'objetivos') NOT NULL,
    
    -- Configuración
    tipo_pregunta ENUM('escala', 'multiple_choice') DEFAULT 'multiple_choice',
    opciones JSON COMMENT 'Opciones de respuesta con sus valores',
    ponderacion DECIMAL(5,2) DEFAULT 1.00,
    
    orden INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    
    INDEX idx_categoria (categoria),
    INDEX idx_orden (orden)
) ENGINE=InnoDB COMMENT='Preguntas del cuestionario inicial';

-- ============================================================================
-- TABLA: respuestas_cuestionario_inicial
-- Descripción: Respuestas detalladas del cuestionario
-- ============================================================================
CREATE TABLE IF NOT EXISTS respuestas_cuestionario_inicial (
    id_respuesta INT AUTO_INCREMENT PRIMARY KEY,
    id_cuestionario INT NOT NULL,
    id_pregunta INT NOT NULL,
    
    -- Valor de la respuesta
    valor_numerico DECIMAL(5,2) COMMENT 'Puntaje obtenido en esta pregunta',
    valor_texto VARCHAR(255) COMMENT 'Texto de la opción seleccionada',
    
    fecha_respuesta DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_cuestionario) REFERENCES cuestionario_inicial(id_cuestionario) ON DELETE CASCADE,
    FOREIGN KEY (id_pregunta) REFERENCES preguntas_cuestionario_inicial(id_pregunta) ON DELETE CASCADE,
    
    INDEX idx_cuestionario (id_cuestionario)
) ENGINE=InnoDB COMMENT='Respuestas del cuestionario inicial';

-- ============================================================================
-- MODIFICACIÓN: Tabla cursos
-- Descripción: Agregar campos para recomendación automática
-- ============================================================================
-- Verificar si las columnas existen antes de agregarlas (técnica segura)
SET @dbname = DATABASE();
SET @tablename = "cursos";
SET @columnname = "nivel_curso";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cursos ADD COLUMN nivel_curso ENUM('principiante', 'intermedio', 'avanzado') DEFAULT 'principiante' AFTER descripcion"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "recomendado_onboarding";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cursos ADD COLUMN recomendado_onboarding BOOLEAN DEFAULT FALSE AFTER nivel_curso"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================================================
-- DATOS INICIALES: Preguntas del Cuestionario
-- ============================================================================
DELETE FROM preguntas_cuestionario_inicial;
ALTER TABLE preguntas_cuestionario_inicial AUTO_INCREMENT = 1;

-- Categoría: Experiencia
INSERT INTO preguntas_cuestionario_inicial (pregunta, categoria, tipo_pregunta, opciones, ponderacion, orden) VALUES
(
    '¿Cuál es tu nivel de experiencia en emprendimiento?', 
    'experiencia', 
    'multiple_choice', 
    '[
        {"texto": "Ninguna, estoy empezando", "valor": 1},
        {"texto": "He intentado emprender antes", "valor": 3},
        {"texto": "Tengo un negocio activo", "valor": 5}
    ]', 
    1.00, 
    1
),
(
    '¿Has gestionado un negocio anteriormente?', 
    'experiencia', 
    'multiple_choice', 
    '[
        {"texto": "No, nunca", "valor": 1},
        {"texto": "Sí, informalmente", "valor": 3},
        {"texto": "Sí, formalmente", "valor": 5}
    ]', 
    1.00, 
    2
),
(
    '¿Cuánto tiempo llevas en el mundo empresarial?', 
    'experiencia', 
    'multiple_choice', 
    '[
        {"texto": "Menos de 6 meses", "valor": 1},
        {"texto": "Entre 6 meses y 2 años", "valor": 3},
        {"texto": "Más de 2 años", "valor": 5}
    ]', 
    1.00, 
    3
);

-- Categoría: Conocimientos
INSERT INTO preguntas_cuestionario_inicial (pregunta, categoria, tipo_pregunta, opciones, ponderacion, orden) VALUES
(
    '¿Qué tan familiarizado estás con conceptos de gestión empresarial?', 
    'conocimientos', 
    'multiple_choice', 
    '[
        {"texto": "Nada familiarizado", "valor": 1},
        {"texto": "Conozco lo básico", "valor": 3},
        {"texto": "Muy familiarizado", "valor": 5}
    ]', 
    1.00, 
    4
),
(
    '¿Tienes conocimientos de marketing digital?', 
    'conocimientos', 
    'multiple_choice', 
    '[
        {"texto": "No sé nada del tema", "valor": 1},
        {"texto": "Uso redes sociales básicas", "valor": 3},
        {"texto": "Gestiono campañas y estrategias", "valor": 5}
    ]', 
    1.00, 
    5
),
(
    '¿Conoces sobre finanzas y contabilidad básica?', 
    'conocimientos', 
    'multiple_choice', 
    '[
        {"texto": "Me cuesta entender los números", "valor": 1},
        {"texto": "Llevo mis cuentas básicas", "valor": 3},
        {"texto": "Manejo estados financieros", "valor": 5}
    ]', 
    1.00, 
    6
);

-- Categoría: Negocio
INSERT INTO preguntas_cuestionario_inicial (pregunta, categoria, tipo_pregunta, opciones, ponderacion, orden) VALUES
(
    '¿En qué etapa se encuentra tu negocio/idea?', 
    'negocio', 
    'multiple_choice', 
    '[
        {"texto": "Solo es una idea", "valor": 1},
        {"texto": "Primeras ventas / Prototipo", "valor": 3},
        {"texto": "Negocio establecido y operando", "valor": 5}
    ]', 
    1.00, 
    7
),
(
    '¿Cuentas con un plan de negocios?', 
    'negocio', 
    'multiple_choice', 
    '[
        {"texto": "No tengo uno", "valor": 1},
        {"texto": "Tengo un borrador mental o simple", "valor": 3},
        {"texto": "Sí, documentado y estructurado", "valor": 5}
    ]', 
    1.00, 
    8
),
(
    '¿Tienes clientes o ventas actualmente?', 
    'negocio', 
    'multiple_choice', 
    '[
        {"texto": "Aún no", "valor": 1},
        {"texto": "Algunos clientes esporádicos", "valor": 3},
        {"texto": "Cartera de clientes recurrente", "valor": 5}
    ]', 
    1.00, 
    9
);

-- Categoría: Objetivos
INSERT INTO preguntas_cuestionario_inicial (pregunta, categoria, tipo_pregunta, opciones, ponderacion, orden) VALUES
(
    '¿Cuál es tu principal objetivo al usar esta plataforma?', 
    'objetivos', 
    'multiple_choice', 
    '[
        {"texto": "Aprender desde cero", "valor": 1},
        {"texto": "Mejorar áreas específicas", "valor": 3},
        {"texto": "Escalar mi negocio", "valor": 5}
    ]', 
    1.00, 
    10
),
(
    '¿Cuánto tiempo puedes dedicar a tu formación semanalmente?', 
    'objetivos', 
    'multiple_choice', 
    '[
        {"texto": "1-2 horas", "valor": 1},
        {"texto": "3-5 horas", "valor": 3},
        {"texto": "Más de 5 horas", "valor": 5}
    ]', 
    1.00, 
    11
),
(
    '¿Qué área te interesa más desarrollar?', 
    'objetivos', 
    'multiple_choice', 
    '[
        {"texto": "Fundamentos básicos", "valor": 1},
        {"texto": "Ventas y Marketing", "valor": 3},
        {"texto": "Gestión y Estrategia", "valor": 5}
    ]', 
    1.00, 
    12
);

-- ============================================================================
-- VERIFICACIÓN
-- ============================================================================
SELECT 'Tablas de onboarding creadas exitosamente' AS resultado;
SELECT COUNT(*) AS preguntas_creadas FROM preguntas_cuestionario_inicial;
