const mysql = require('mysql2/promise');

async function describeAuditoria() {
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
        
        const [cols] = await connection.query(`DESCRIBE auditoria_logs`);
        console.log('📋 Estructura de auditoria_logs:\n');
        cols.forEach(c => console.log(`  ${c.Field}: ${c.Type} ${c.Null === 'NO' ? 'NOT NULL' : ''} ${c.Default ? `DEFAULT ${c.Default}` : ''}`));
        
        const [count] = await connection.query(`SELECT COUNT(*) as total FROM auditoria_logs`);
        console.log(`\n📊 Total registros: ${count[0].total}`);
        
        if (count[0].total > 0) {
            const [sample] = await connection.query(`SELECT * FROM auditoria_logs ORDER BY fecha_hora DESC LIMIT 3`);
            console.log('\n📝 Últimos 3 registros:');
            sample.forEach(r => {
                console.log(`\n  ID: ${r.id_log}`);
                console.log(`  Usuario: ${r.id_usuario}`);
                console.log(`  Acción: ${r.accion}`);
                console.log(`  Tabla: ${r.tabla_afectada || 'N/A'}`);
                console.log(`  IP: ${r.ip_address || 'N/A'}`);
                console.log(`  Fecha: ${r.fecha_hora}`);
            });
        }

    } catch (error) {
        console.error('❌ Error:', error.message);
    } finally {
        if (connection) await connection.end();
    }
}

describeAuditoria();
