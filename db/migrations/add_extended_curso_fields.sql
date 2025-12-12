-- ============================================================================
-- Migración: Agregar campos extendidos a la tabla cursos
-- Descripción: Agrega campos para mejor gestión administrativa de cursos
-- Fecha: 2024
-- ============================================================================

-- Agregar campos adicionales a la tabla cursos
ALTER TABLE cursos
ADD COLUMN descripcion_larga TEXT COMMENT 'Descripción completa y detallada del curso' AFTER descripcion,
ADD COLUMN requisitos TEXT COMMENT 'Requisitos previos para tomar el curso' AFTER objetivo_aprendizaje,
ADD COLUMN objetivos TEXT COMMENT 'Objetivos de aprendizaje en formato JSON' AFTER requisitos,
ADD COLUMN icono VARCHAR(10) DEFAULT '📚' COMMENT 'Emoji o icono del curso' AFTER imagen_portada,
ADD COLUMN max_estudiantes INT DEFAULT 0 COMMENT 'Número máximo de estudiantes (0 = ilimitado)' AFTER requiere_prerequisitos,
ADD COLUMN certificado BOOLEAN DEFAULT FALSE COMMENT 'Si otorga certificado al completar' AFTER max_estudiantes;

-- Nota: Estos campos son opcionales y no afectan la funcionalidad existente
