USE formacion_empresarial;
UPDATE usuarios SET configuracion_privacidad = '{"perfil_publico": true, "mostrar_email": false, "mostrar_telefono": false, "mostrar_biografia": true, "mostrar_ubicacion": true, "permitir_mensajes": true}' WHERE configuracion_privacidad IS NULL;
SELECT 'Migracion completada' AS resultado;
