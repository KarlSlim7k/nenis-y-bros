const mysql = require('mysql2/promise');

async function testAuditoriaQuery() {
    const config = {
        host: 'metro.proxy.rlwy.net',
        port: 52451,
        user: 'root',
        password: 'hVRfZwfOYSrdWHloqDrsPCAuuAkPKNem',
        database: 'formacion_empresarial'
    };

    let connection;
    try {
        connection = await mysql.createConnection(config);
        
        console.log('🔍 Ejecutando query de auditoría...\n');
        
        const query = `
            SELECT 
                a.*,
                u.nombre,
                u.apellido,
                u.email
            FROM auditoria_logs a
            LEFT JOIN usuarios u ON a.id_usuario = u.id_usuario
            WHERE 1=1
            ORDER BY a.fecha_creacion DESC
            LIMIT 5
        `;
        
        const [logs] = await connection.query(query);
        
        console.log(`✅ Query exitoso: ${logs.length} logs encontrados\n`);
        
        if (logs.length > 0) {
            console.log('📋 Primer log:');
            console.log(JSON.stringify(logs[0], null, 2));
        }

    } catch (error) {
        console.error('❌ Error en query:', error.message);
        console.error('Stack:', error.stack);
    } finally {
        if (connection) await connection.end();
    }
}

testAuditoriaQuery();
