-- Agregar columna de configuracion de privacidad
USE formacion_empresarial;

ALTER TABLE usuarios 
ADD COLUMN configuracion_privacidad JSON 
DEFAULT NULL
COMMENT 'Configuracion de privacidad del usuario';

-- Actualizar usuarios existentes con configuracion por defecto
UPDATE usuarios 
SET configuracion_privacidad = '{"perfil_publico": true, "mostrar_email": false, "mostrar_telefono": false, "mostrar_biografia": true, "mostrar_ubicacion": true, "permitir_mensajes": true}'
WHERE configuracion_privacidad IS NULL;
