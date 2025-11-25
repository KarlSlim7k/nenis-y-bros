-- ============================================================================
-- DATA DE PRUEBA FASE 3: PERFILES EMPRESARIALES Y DIAGNÓSTICOS
-- ============================================================================
-- Crear datos de prueba para perfiles y diagnósticos completados
-- ============================================================================

USE formacion_empresarial;

-- ============================================================================
-- PERFILES EMPRESARIALES DE PRUEBA
-- ============================================================================
-- Nota: Solo hay 2 usuarios existentes (IDs: 1 y 2), creamos perfiles para ellos

-- Perfil para Juan (id_usuario = 1)
INSERT INTO perfiles_empresariales (
    id_usuario, nombre_empresa, sector, tipo_negocio, etapa_negocio,
    numero_empleados, facturacion_anual, descripcion, sitio_web,
    telefono_empresa, email_empresa, redes_sociales,
    direccion, ciudad, pais
) VALUES
(1, 'Cafetería El Aroma', 'Gastronomía', 'microempresa', 'inicio', 
 5, 50000.00,
 'Cafetería especializada en café de origen con productos de repostería artesanal',
 'https://cafeelaroma.com',
 '+34 91 555 1234',
 'contacto@cafeelaroma.com',
 '{"facebook": "cafeelaroma", "instagram": "@cafeelaroma"}',
 'Calle Mayor 45',
 'Madrid',
 'España');

-- Perfil para María (id_usuario = 2)
INSERT INTO perfiles_empresariales (
    id_usuario, nombre_empresa, sector, tipo_negocio, etapa_negocio,
    numero_empleados, facturacion_anual, descripcion, sitio_web,
    telefono_empresa, email_empresa, redes_sociales,
    ciudad, pais
) VALUES
(2, 'Consultora Digital MG', 'Consultoría', 'pequeña_empresa', 'crecimiento',
 15, 250000.00,
 'Consultora especializada en transformación digital para PYMEs',
 'https://consultoramg.com',
 '+34 93 444 5678',
 'info@consultoramg.com',
 '{"linkedin": "consultora-mg", "twitter": "@consultoramg"}',
 'Barcelona',
 'España');

-- ============================================================================
-- DIAGNÓSTICOS REALIZADOS DE PRUEBA
-- ============================================================================

-- Diagnóstico completado para Juan (Cafetería) - Reciente
INSERT INTO diagnosticos_realizados (
    id_usuario, id_perfil_empresarial, id_tipo_diagnostico,
    fecha_inicio, fecha_finalizacion, estado, 
    puntaje_total, nivel_madurez, resultados_areas
) VALUES
(1, 1, 1, 
 DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), 'completado',
 58.50, 'intermedio',
 '[{"id_area":1,"nombre":"Gestión Empresarial","puntaje":11.5,"puntaje_maximo":20,"porcentaje":57.5,"nivel":"intermedio"},{"id_area":2,"nombre":"Finanzas","puntaje":10,"puntaje_maximo":20,"porcentaje":50,"nivel":"basico"},{"id_area":3,"nombre":"Marketing y Ventas","puntaje":13,"puntaje_maximo":20,"porcentaje":65,"nivel":"intermedio"},{"id_area":4,"nombre":"Operaciones","puntaje":12.5,"puntaje_maximo":20,"porcentaje":62.5,"nivel":"intermedio"},{"id_area":5,"nombre":"Recursos Humanos","puntaje":11,"puntaje_maximo":20,"porcentaje":55,"nivel":"intermedio"}]');

-- Diagnóstico completado para María (Consultora)
INSERT INTO diagnosticos_realizados (
    id_usuario, id_perfil_empresarial, id_tipo_diagnostico,
    fecha_inicio, fecha_finalizacion, estado,
    puntaje_total, nivel_madurez, resultados_areas
) VALUES
(2, 2, 1,
 DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), 'completado',
 75.25, 'intermedio',
 '[{"id_area":1,"nombre":"Gestión Empresarial","puntaje":16,"puntaje_maximo":20,"porcentaje":80,"nivel":"avanzado"},{"id_area":2,"nombre":"Finanzas","puntaje":14.5,"puntaje_maximo":20,"porcentaje":72.5,"nivel":"intermedio"},{"id_area":3,"nombre":"Marketing y Ventas","puntaje":15.5,"puntaje_maximo":20,"porcentaje":77.5,"nivel":"intermedio"},{"id_area":4,"nombre":"Operaciones","puntaje":14,"puntaje_maximo":20,"porcentaje":70,"nivel":"intermedio"},{"id_area":5,"nombre":"Recursos Humanos","puntaje":15.5,"puntaje_maximo":20,"porcentaje":77.5,"nivel":"intermedio"}]');

-- Diagnóstico en progreso para María (segunda vez)
INSERT INTO diagnosticos_realizados (
    id_usuario, id_perfil_empresarial, id_tipo_diagnostico,
    fecha_inicio, estado
) VALUES
(2, 2, 1, NOW(), 'iniciado');

-- Diagnóstico antiguo para Juan (para comparación - hace 3 meses)
INSERT INTO diagnosticos_realizados (
    id_usuario, id_perfil_empresarial, id_tipo_diagnostico,
    fecha_inicio, fecha_finalizacion, estado,
    puntaje_total, nivel_madurez, resultados_areas
) VALUES
(1, 1, 1,
 DATE_SUB(NOW(), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 89 DAY), 'completado',
 45.75, 'basico',
 '[{"id_area":1,"nombre":"Gestión Empresarial","puntaje":9,"puntaje_maximo":20,"porcentaje":45,"nivel":"basico"},{"id_area":2,"nombre":"Finanzas","puntaje":8.5,"puntaje_maximo":20,"porcentaje":42.5,"nivel":"basico"},{"id_area":3,"nombre":"Marketing y Ventas","puntaje":10,"puntaje_maximo":20,"porcentaje":50,"nivel":"basico"},{"id_area":4,"nombre":"Operaciones","puntaje":9.5,"puntaje_maximo":20,"porcentaje":47.5,"nivel":"basico"},{"id_area":5,"nombre":"Recursos Humanos","puntaje":8.75,"puntaje_maximo":20,"porcentaje":43.75,"nivel":"basico"}]');

-- ============================================================================
-- RESPUESTAS PARA DIAGNÓSTICO COMPLETADO (Juan - Reciente)
-- ============================================================================

-- Área 1: Gestión Empresarial (Preguntas 1-4)
INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico, valor_texto) VALUES
(1, 1, 3, 'Sí, tenemos plan anual pero no siempre lo seguimos'),
(1, 2, 3, 'Tenemos algunos procesos documentados pero no todos'),
(1, 3, 3, 'Revisamos mensualmente pero no siempre tomamos acción'),
(1, 4, 2, 'Decisiones basadas en experiencia más que en datos');

-- Área 2: Finanzas (Preguntas 5-8)
INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico, valor_texto) VALUES
(1, 5, 3, 'Control básico de ingresos y gastos mensual'),
(1, 6, 2, 'No tenemos flujo de caja proyectado'),
(1, 7, 2, 'No calculamos márgenes por producto'),
(1, 8, 3, 'Tenemos algunos ahorros pero sin plan específico');

-- Área 3: Marketing y Ventas (Preguntas 9-12)
INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico, valor_texto) VALUES
(1, 9, 4, 'Sí, tenemos presencia en redes y hacemos promociones'),
(1, 10, 3, 'Conocemos a nuestros clientes habituales'),
(1, 11, 3, 'Medimos ventas totales pero no conversión detallada'),
(1, 12, 3, 'Ocasionalmente pedimos opiniones');

-- Área 4: Operaciones (Preguntas 13-16)
INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico, valor_texto) VALUES
(1, 13, 3, 'Tenemos proceso definido pero mejorable'),
(1, 14, 3, 'Control de inventario básico semanal'),
(1, 15, 3, 'Tenemos proveedores de confianza'),
(1, 16, 4, 'Sí, medimos satisfacción regularmente');

-- Área 5: Recursos Humanos (Preguntas 17-20)
INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico, valor_texto) VALUES
(1, 17, 3, 'Roles claros pero sin descripciones formales'),
(1, 18, 2, 'Capacitación informal, no estructurada'),
(1, 19, 3, 'Ambiente positivo pero sin evaluaciones formales'),
(1, 20, 3, 'Contratación por referencias, sin proceso formal');

-- ============================================================================
-- RESPUESTAS PARA DIAGNÓSTICO COMPLETADO (María - Consultora)
-- ============================================================================

-- Área 1: Gestión (Preguntas 1-4)
INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico) VALUES
(2, 1, 4), (2, 2, 4), (2, 3, 4), (2, 4, 4);

-- Área 2: Finanzas (Preguntas 5-8)
INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico) VALUES
(2, 5, 4), (2, 6, 3), (2, 7, 4), (2, 8, 4);

-- Área 3: Marketing (Preguntas 9-12)
INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico) VALUES
(2, 9, 4), (2, 10, 4), (2, 11, 4), (2, 12, 3);

-- Área 4: Operaciones (Preguntas 13-16)
INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico) VALUES
(2, 13, 4), (2, 14, 3), (2, 15, 3), (2, 16, 4);

-- Área 5: RRHH (Preguntas 17-20)
INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico) VALUES
(2, 17, 4), (2, 18, 4), (2, 19, 4), (2, 20, 4);

-- ============================================================================
-- RESPUESTAS PARCIALES PARA DIAGNÓSTICO EN PROGRESO (María - 2do intento)
-- ============================================================================

INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico) VALUES
(3, 1, 4), (3, 2, 4), (3, 3, 4), (3, 4, 4),
(3, 5, 4), (3, 6, 3), (3, 7, 4);
-- (Solo 7 de 20 preguntas respondidas - diagnóstico en progreso)

-- ============================================================================
-- RESPUESTAS PARA DIAGNÓSTICO ANTIGUO (Juan - 3 meses atrás)
-- ============================================================================

-- Área 1
INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico) VALUES
(4, 1, 2), (4, 2, 2), (4, 3, 3), (4, 4, 2);

-- Área 2
INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico) VALUES
(4, 5, 2), (4, 6, 2), (4, 7, 2), (4, 8, 3);

-- Área 3
INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico) VALUES
(4, 9, 3), (4, 10, 2), (4, 11, 2), (4, 12, 3);

-- Área 4
INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico) VALUES
(4, 13, 2), (4, 14, 3), (4, 15, 2), (4, 16, 3);

-- Área 5
INSERT INTO respuestas_diagnostico (id_diagnostico, id_pregunta, valor_numerico) VALUES
(4, 17, 2), (4, 18, 2), (4, 19, 2), (4, 20, 3);

-- ============================================================================
-- RESUMEN DE DATA CREADA
-- ============================================================================
SELECT 
    'Perfiles creados' as tipo,
    COUNT(*) as cantidad
FROM perfiles_empresariales
UNION ALL
SELECT 
    'Diagnósticos realizados',
    COUNT(*)
FROM diagnosticos_realizados
UNION ALL
SELECT
    'Diagnósticos completados',
    COUNT(*)
FROM diagnosticos_realizados
WHERE estado = 'completado'
UNION ALL
SELECT
    'Respuestas registradas',
    COUNT(*)
FROM respuestas_diagnostico;

-- Ver diagnósticos con progreso
SELECT 
    dr.id_diagnostico,
    u.nombre as usuario,
    pe.nombre_empresa,
    dr.estado,
    dr.puntaje_total,
    dr.nivel_madurez,
    COUNT(rd.id_respuesta) as respuestas_dadas,
    (SELECT COUNT(*) FROM preguntas_diagnostico pd
     INNER JOIN areas_evaluacion ae ON pd.id_area = ae.id_area
     WHERE ae.id_tipo_diagnostico = dr.id_tipo_diagnostico) as total_preguntas
FROM diagnosticos_realizados dr
INNER JOIN usuarios u ON dr.id_usuario = u.id_usuario
LEFT JOIN perfiles_empresariales pe ON dr.id_perfil_empresarial = pe.id_perfil_empresarial
LEFT JOIN respuestas_diagnostico rd ON dr.id_diagnostico = rd.id_diagnostico
GROUP BY dr.id_diagnostico
ORDER BY dr.fecha_inicio DESC;
