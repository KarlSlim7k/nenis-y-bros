const mysql = require('mysql2/promise');

async function createTriggers() {
    const config = {
        host: 'metro.proxy.rlwy.net',
        port: 52451,
        user: 'root',
        password: 'hVRfZwfOYSrdWHloqDrsPCAuuAkPKNem',
        database: 'formacion_empresarial',
        multipleStatements: true
    };

    let connection;
    try {
        console.log('🚀 Creando triggers...\n');
        connection = await mysql.createConnection(config);
        console.log('✓ Conectado a MySQL\n');

        // Trigger 1: Después de insertar descarga
        const trigger1 = `
        CREATE TRIGGER IF NOT EXISTS trg_recurso_descarga_insert
        AFTER INSERT ON descargas_recursos
        FOR EACH ROW
        BEGIN
            UPDATE recursos_aprendizaje 
            SET contador_descargas = contador_descargas + 1
            WHERE id = NEW.id_recurso;
        END`;

        // Trigger 2: Después de insertar calificación
        const trigger2 = `
        CREATE TRIGGER IF NOT EXISTS trg_recurso_calificacion_insert
        AFTER INSERT ON calificaciones_recursos
        FOR EACH ROW
        BEGIN
            UPDATE recursos_aprendizaje 
            SET calificacion = (SELECT AVG(calificacion) FROM calificaciones_recursos WHERE id_recurso = NEW.id_recurso),
                contador_calificaciones = contador_calificaciones + 1
            WHERE id = NEW.id_recurso;
        END`;

        // Trigger 3: Después de actualizar calificación
        const trigger3 = `
        CREATE TRIGGER IF NOT EXISTS trg_recurso_calificacion_update
        AFTER UPDATE ON calificaciones_recursos
        FOR EACH ROW
        BEGIN
            UPDATE recursos_aprendizaje 
            SET calificacion = (SELECT AVG(calificacion) FROM calificaciones_recursos WHERE id_recurso = NEW.id_recurso)
            WHERE id = NEW.id_recurso;
        END`;

        // Trigger 4: Después de eliminar calificación
        const trigger4 = `
        CREATE TRIGGER IF NOT EXISTS trg_recurso_calificacion_delete
        AFTER DELETE ON calificaciones_recursos
        FOR EACH ROW
        BEGIN
            UPDATE recursos_aprendizaje 
            SET calificacion = COALESCE((SELECT AVG(calificacion) FROM calificaciones_recursos WHERE id_recurso = OLD.id_recurso), 0),
                contador_calificaciones = GREATEST(contador_calificaciones - 1, 0)
            WHERE id = OLD.id_recurso;
        END`;

        const triggers = [trigger1, trigger2, trigger3, trigger4];
        const names = [
            'trg_recurso_descarga_insert',
            'trg_recurso_calificacion_insert',
            'trg_recurso_calificacion_update',
            'trg_recurso_calificacion_delete'
        ];

        for (let i = 0; i < triggers.length; i++) {
            try {
                // Primero intentar eliminar el trigger si existe
                await connection.query(`DROP TRIGGER IF EXISTS ${names[i]}`);
                // Crear el trigger
                await connection.query(triggers[i]);
                console.log(`  ✓ Trigger ${names[i]} creado`);
            } catch (error) {
                if (error.code === 'ER_TRG_ALREADY_EXISTS') {
                    console.log(`  ⚠ Trigger ${names[i]} ya existe`);
                } else {
                    console.log(`  ✗ Error en ${names[i]}: ${error.message}`);
                }
            }
        }

        // Verificar triggers creados
        const [triggers_result] = await connection.query(`
            SELECT TRIGGER_NAME 
            FROM INFORMATION_SCHEMA.TRIGGERS 
            WHERE TRIGGER_SCHEMA = 'formacion_empresarial' 
            AND TRIGGER_NAME LIKE 'trg_recurso%'
        `);

        console.log(`\n📊 Total de triggers: ${triggers_result.length}`);
        triggers_result.forEach(t => console.log(`  - ${t.TRIGGER_NAME}`));

        console.log('\n✅ ¡Triggers creados exitosamente!');

    } catch (error) {
        console.error('❌ Error:', error.message);
        process.exit(1);
    } finally {
        if (connection) {
            await connection.end();
        }
    }
}

createTriggers();
