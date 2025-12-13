const mysql = require('mysql2/promise');

async function checkAuditoria() {
    const config = {
        host: 'metro.proxy.rlwy.net',
        port: 52451,
        user: 'root',
        password: 'hVRfZwfOYSrdWHloqDrsPCAuuAkPKNem',
        database: 'formacion_empresarial'
    };

    let connection;
    try {
        console.log('🔍 Verificando sistema de auditoría...\n');
        connection = await mysql.createConnection(config);
        
        // 1. Verificar tablas de auditoría/logs
        const [tables] = await connection.query(`
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = 'formacion_empresarial' 
            AND (TABLE_NAME LIKE '%audit%' OR TABLE_NAME LIKE '%log%' OR TABLE_NAME = 'actividad_usuarios')
        `);
        
        console.log('📋 Tablas de auditoría/logs:');
        if (tables.length === 0) {
            console.log('  ❌ No se encontraron tablas de auditoría');
        } else {
            tables.forEach(t => console.log(`  - ${t.TABLE_NAME}`));
        }
        
        // 2. Verificar si existe actividad_usuarios
        const [actTable] = await connection.query(`
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = 'formacion_empresarial' 
            AND TABLE_NAME = 'actividad_usuarios'
        `);
        
        if (actTable.length > 0) {
            console.log('\n✓ Tabla actividad_usuarios existe');
            
            // Ver estructura
            const [cols] = await connection.query(`DESCRIBE actividad_usuarios`);
            console.log('\nEstructura:');
            cols.forEach(c => console.log(`  - ${c.Field}: ${c.Type}`));
            
            // Contar registros
            const [count] = await connection.query(`SELECT COUNT(*) as total FROM actividad_usuarios`);
            console.log(`\nTotal registros: ${count[0].total}`);
            
            // Ver últimos 5 registros
            if (count[0].total > 0) {
                const [recent] = await connection.query(`
                    SELECT tipo_actividad, detalles, fecha_actividad 
                    FROM actividad_usuarios 
                    ORDER BY fecha_actividad DESC 
                    LIMIT 5
                `);
                console.log('\nÚltimos 5 registros:');
                recent.forEach(r => console.log(`  - ${r.tipo_actividad}: ${r.detalles} (${r.fecha_actividad})`));
            }
        }
        
        console.log('\n✅ Verificación completada');

    } catch (error) {
        console.error('❌ Error:', error.message);
    } finally {
        if (connection) await connection.end();
    }
}

checkAuditoria();
