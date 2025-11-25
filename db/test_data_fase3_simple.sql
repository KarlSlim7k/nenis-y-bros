-- ============================================================================
-- DATA DE PRUEBA SIMPLIFICADA FASE 3
-- ============================================================================

USE formacion_empresarial;

-- Perfiles empresariales (2 perfiles para los 2 usuarios existentes)
INSERT INTO perfiles_empresariales (
    id_usuario, nombre_empresa, sector, tipo_negocio, etapa_negocio,
    numero_empleados, facturacion_anual, descripcion, sitio_web,
    telefono_empresa, email_empresa, ciudad, pais
) VALUES
(1, 'Cafetería El Aroma', 'Gastronomía', 'microempresa', 'inicio', 
 5, 50000.00,
 'Cafetería especializada en café de origen', 'https://cafeelaroma.com',
 '+34 91 555 1234', 'contacto@cafeelaroma.com', 'Madrid', 'España'),
 
(2, 'Consultora Digital MG', 'Consultoría', 'pequeña_empresa', 'crecimiento',
 15, 250000.00,
 'Consultora de transformación digital', 'https://consultoramg.com',
 '+34 93 444 5678', 'info@consultoramg.com', 'Barcelona', 'España');

-- Diagnósticos realizados (usando IDs correctos de perfiles: 5 y 6)
INSERT INTO diagnosticos_realizados (
    id_usuario, id_perfil_empresarial, id_tipo_diagnostico,
    fecha_inicio, fecha_completado, estado, 
    puntaje_total, nivel_madurez, resultados_areas
) VALUES
-- Juan (usuario 1, perfil 5) - Diagnóstico completado reciente
(1, 5, 1, 
 DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), 'completado',
 58.50, 'intermedio',
 '[{"id_area":1,"nombre":"Gestión","porcentaje":57.5,"nivel":"intermedio"},{"id_area":2,"nombre":"Finanzas","porcentaje":50,"nivel":"basico"}]'),

-- María (usuario 2, perfil 6) - Diagnóstico completado
(2, 6, 1,
 DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), 'completado',
 75.25, 'avanzado',
 '[{"id_area":1,"nombre":"Gestión","porcentaje":80,"nivel":"avanzado"},{"id_area":2,"nombre":"Finanzas","porcentaje":72.5,"nivel":"intermedio"}]'),

-- María - Diagnóstico en progreso
(2, 6, 1, NOW(), NULL, 'en_progreso', NULL, NULL, NULL),

-- Juan - Diagnóstico antiguo (comparación)
(1, 5, 1,
 DATE_SUB(NOW(), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 89 DAY), 'completado',
 45.75, 'basico',
 '[{"id_area":1,"nombre":"Gestión","porcentaje":45,"nivel":"basico"},{"id_area":2,"nombre":"Finanzas","porcentaje":42.5,"nivel":"basico"}]');

-- Respuestas para diagnóstico completado 1 (Juan - reciente)
INSERT INTO respuestas_diagnostico (id_diagnostico_realizado, id_pregunta, valor_numerico, valor_texto) VALUES
(1, 1, 3, 'Tenemos plan anual'), (1, 2, 3, 'Procesos documentados parcialmente'),
(1, 3, 3, 'Revisión mensual'), (1, 4, 2, 'Decisiones por experiencia'),
(1, 5, 3, 'Control básico'), (1, 6, 2, 'Sin flujo proyectado'),
(1, 7, 2, 'No calculamos márgenes'), (1, 8, 3, 'Algunos ahorros'),
(1, 9, 4, 'Presencia en redes'), (1, 10, 3, 'Conocemos clientes'),
(1, 11, 3, 'Medimos ventas'), (1, 12, 3, 'Pedimos opiniones'),
(1, 13, 3, 'Proceso mejorable'), (1, 14, 3, 'Control semanal'),
(1, 15, 3, 'Proveedores confiables'), (1, 16, 4, 'Medimos satisfacción'),
(1, 17, 3, 'Roles claros'), (1, 18, 2, 'Capacitación informal'),
(1, 19, 3, 'Ambiente positivo'), (1, 20, 3, 'Contratación por referencias');

-- Respuestas para diagnóstico completado 2 (María)
INSERT INTO respuestas_diagnostico (id_diagnostico_realizado, id_pregunta, valor_numerico) VALUES
(2, 1, 4), (2, 2, 4), (2, 3, 4), (2, 4, 4),
(2, 5, 4), (2, 6, 3), (2, 7, 4), (2, 8, 4),
(2, 9, 4), (2, 10, 4), (2, 11, 4), (2, 12, 3),
(2, 13, 4), (2, 14, 3), (2, 15, 3), (2, 16, 4),
(2, 17, 4), (2, 18, 4), (2, 19, 4), (2, 20, 4);

-- Respuestas parciales para diagnóstico en progreso 3 (María)
INSERT INTO respuestas_diagnostico (id_diagnostico_realizado, id_pregunta, valor_numerico) VALUES
(3, 1, 4), (3, 2, 4), (3, 3, 4), (3, 4, 4),
(3, 5, 4), (3, 6, 3), (3, 7, 4);

-- Respuestas para diagnóstico antiguo 4 (Juan)
INSERT INTO respuestas_diagnostico (id_diagnostico_realizado, id_pregunta, valor_numerico) VALUES
(4, 1, 2), (4, 2, 2), (4, 3, 3), (4, 4, 2),
(4, 5, 2), (4, 6, 2), (4, 7, 2), (4, 8, 3),
(4, 9, 3), (4, 10, 2), (4, 11, 2), (4, 12, 3),
(4, 13, 2), (4, 14, 3), (4, 15, 2), (4, 16, 3),
(4, 17, 2), (4, 18, 2), (4, 19, 2), (4, 20, 3);

-- Verificación
SELECT 'Perfiles' as item, COUNT(*) as cantidad FROM perfiles_empresariales
UNION ALL SELECT 'Diagnósticos', COUNT(*) FROM diagnosticos_realizados
UNION ALL SELECT 'Completados', COUNT(*) FROM diagnosticos_realizados WHERE estado = 'completado'
UNION ALL SELECT 'Respuestas', COUNT(*) FROM respuestas_diagnostico;
