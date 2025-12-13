-- =============================================================================
-- MIGRACIÓN: Configuraciones del Sistema por Defecto
-- Fecha: 2024-12-13
-- =============================================================================

-- Limpiar configuraciones existentes (opcional)
-- DELETE FROM configuracion_sistema;

-- Insertar configuraciones por defecto
INSERT INTO configuracion_sistema (clave, valor, tipo_dato, descripcion, categoria) VALUES
-- General
('modo_mantenimiento', '0', 'boolean', 'Deshabilitar el acceso público al sistema', 'general'),
('registro_publico', '1', 'boolean', 'Permitir que nuevos usuarios se registren', 'general'),
('verificacion_email', '1', 'boolean', 'Requerir verificación de correo para acceder', 'general'),
('nombre_sitio', 'Nenis y Bros', 'string', 'Nombre del sitio web', 'general'),
('descripcion_sitio', 'Plataforma de formación empresarial', 'string', 'Descripción del sitio', 'general'),

-- Notificaciones
('email_bienvenida', '1', 'boolean', 'Enviar correo automático al registrarse', 'notificaciones'),
('alertas_seguridad', '1', 'boolean', 'Notificar intentos de acceso sospechosos', 'notificaciones'),
('reportes_semanales', '0', 'boolean', 'Recibir resumen de actividad por correo', 'notificaciones'),
('notificaciones_push', '1', 'boolean', 'Habilitar notificaciones push en el navegador', 'notificaciones'),

-- Seguridad
('intentos_login_max', '5', 'number', 'Máximo de intentos de login antes de bloquear', 'seguridad'),
('tiempo_bloqueo_minutos', '30', 'number', 'Minutos de bloqueo tras exceder intentos', 'seguridad'),
('sesion_duracion_horas', '24', 'number', 'Duración de la sesión en horas', 'seguridad'),
('forzar_https', '1', 'boolean', 'Forzar conexiones seguras HTTPS', 'seguridad'),

-- Límites
('max_upload_mb', '10', 'number', 'Tamaño máximo de archivos en MB', 'limites'),
('usuarios_por_pagina', '20', 'number', 'Usuarios a mostrar por página', 'limites'),
('logs_retencion_dias', '90', 'number', 'Días para retener logs de auditoría', 'limites')

ON DUPLICATE KEY UPDATE 
    valor = VALUES(valor),
    descripcion = VALUES(descripcion),
    categoria = VALUES(categoria);

-- Verificar
SELECT * FROM configuracion_sistema ORDER BY categoria, clave;
