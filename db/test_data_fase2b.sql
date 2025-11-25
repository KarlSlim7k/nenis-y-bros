-- ============================================================================
-- DATOS DE PRUEBA PARA FASE 2B - EVALUACIONES
-- ============================================================================
-- Script para insertar datos de prueba del sistema de evaluaciones
-- ============================================================================

USE formacion_empresarial;

-- Insertar evaluación de ejemplo para el curso de Introducción
INSERT INTO evaluaciones (
    id_leccion, 
    titulo, 
    descripcion, 
    tipo_evaluacion, 
    duracion_minutos, 
    intentos_permitidos, 
    puntaje_minimo_aprobacion,
    mostrar_resultados_inmediatos,
    permitir_revision,
    estado
) VALUES (
    16, -- Lección "Bienvenida al curso"
    'Quiz: Fundamentos de Negocios',
    'Evaluación básica sobre conceptos fundamentales de negocios',
    'quiz',
    15,
    3,
    70.00,
    1,
    1,
    'publicado'
);

SET @id_eval = LAST_INSERT_ID();

-- Preguntas de opción múltiple
INSERT INTO preguntas_evaluacion (
    id_evaluacion,
    pregunta_texto,
    tipo_pregunta,
    puntos,
    orden
) VALUES
(@id_eval, '¿Qué es un modelo de negocio?', 'multiple_choice', 2, 1),
(@id_eval, '¿Cuál es el objetivo principal de un plan de negocios?', 'multiple_choice', 2, 2),
(@id_eval, 'El análisis FODA evalúa:', 'multiple_choice', 2, 3);

-- Opciones para pregunta 1
INSERT INTO opciones_pregunta (id_pregunta_evaluacion, texto_opcion, es_correcta, orden) VALUES
((SELECT id_pregunta_evaluacion FROM preguntas_evaluacion WHERE pregunta_texto LIKE '%modelo de negocio%' LIMIT 1), 
 'Una estrategia para describir cómo una empresa crea, entrega y captura valor', 1, 1),
((SELECT id_pregunta_evaluacion FROM preguntas_evaluacion WHERE pregunta_texto LIKE '%modelo de negocio%' LIMIT 1), 
 'Un documento legal para registrar una empresa', 0, 2),
((SELECT id_pregunta_evaluacion FROM preguntas_evaluacion WHERE pregunta_texto LIKE '%modelo de negocio%' LIMIT 1), 
 'Un tipo de software empresarial', 0, 3),
((SELECT id_pregunta_evaluacion FROM preguntas_evaluacion WHERE pregunta_texto LIKE '%modelo de negocio%' LIMIT 1), 
 'Una forma de calcular impuestos', 0, 4);

-- Opciones para pregunta 2
INSERT INTO opciones_pregunta (id_pregunta_evaluacion, texto_opcion, es_correcta, orden) VALUES
((SELECT id_pregunta_evaluacion FROM preguntas_evaluacion WHERE pregunta_texto LIKE '%plan de negocios%' LIMIT 1), 
 'Describir únicamente los productos', 0, 1),
((SELECT id_pregunta_evaluacion FROM preguntas_evaluacion WHERE pregunta_texto LIKE '%plan de negocios%' LIMIT 1), 
 'Planificar la estrategia, operaciones y viabilidad financiera de la empresa', 1, 2),
((SELECT id_pregunta_evaluacion FROM preguntas_evaluacion WHERE pregunta_texto LIKE '%plan de negocios%' LIMIT 1), 
 'Calcular el salario de los empleados', 0, 3),
((SELECT id_pregunta_evaluacion FROM preguntas_evaluacion WHERE pregunta_texto LIKE '%plan de negocios%' LIMIT 1), 
 'Diseñar el logo de la empresa', 0, 4);

-- Opciones para pregunta 3
INSERT INTO opciones_pregunta (id_pregunta_evaluacion, texto_opcion, es_correcta, orden) VALUES
((SELECT id_pregunta_evaluacion FROM preguntas_evaluacion WHERE pregunta_texto LIKE '%FODA%' LIMIT 1), 
 'Solo las amenazas del mercado', 0, 1),
((SELECT id_pregunta_evaluacion FROM preguntas_evaluacion WHERE pregunta_texto LIKE '%FODA%' LIMIT 1), 
 'Fortalezas, Oportunidades, Debilidades y Amenazas', 1, 2),
((SELECT id_pregunta_evaluacion FROM preguntas_evaluacion WHERE pregunta_texto LIKE '%FODA%' LIMIT 1), 
 'Los ingresos y gastos', 0, 3),
((SELECT id_pregunta_evaluacion FROM preguntas_evaluacion WHERE pregunta_texto LIKE '%FODA%' LIMIT 1), 
 'El organigrama de la empresa', 0, 4);

-- Pregunta verdadero/falso
INSERT INTO preguntas_evaluacion (
    id_evaluacion,
    pregunta_texto,
    tipo_pregunta,
    puntos,
    orden,
    explicacion
) VALUES
(@id_eval, 'Un emprendedor debe tener habilidades financieras básicas', 'verdadero_falso', 2, 4, 
 'Es fundamental que los emprendedores comprendan conceptos financieros básicos para gestionar su negocio efectivamente.');

INSERT INTO opciones_pregunta (id_pregunta_evaluacion, texto_opcion, es_correcta, orden) VALUES
((SELECT id_pregunta_evaluacion FROM preguntas_evaluacion WHERE pregunta_texto LIKE '%emprendedor debe tener%' LIMIT 1), 
 'Verdadero', 1, 1),
((SELECT id_pregunta_evaluacion FROM preguntas_evaluacion WHERE pregunta_texto LIKE '%emprendedor debe tener%' LIMIT 1), 
 'Falso', 0, 2);

-- Pregunta de respuesta corta
INSERT INTO preguntas_evaluacion (
    id_evaluacion,
    pregunta_texto,
    tipo_pregunta,
    puntos,
    orden,
    explicacion
) VALUES
(@id_eval, '¿Qué significa la sigla MVP en el contexto de startups?', 'respuesta_corta', 2, 5,
 'MVP significa Minimum Viable Product (Producto Mínimo Viable)');

SELECT 'Datos de prueba insertados exitosamente' as mensaje;
SELECT CONCAT('ID de evaluación creada: ', @id_eval) as info;
