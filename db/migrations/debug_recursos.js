const mysql = require('mysql2/promise');

async function debugRecursos() {
    const config = {
        host: 'metro.proxy.rlwy.net',
        port: 52451,
        user: 'root',
        password: 'hVRfZwfOYSrdWHloqDrsPCAuuAkPKNem',
        database: 'formacion_empresarial'
    };

    let connection;
    try {
        console.log('🔍 Verificando tablas de recursos...\n');
        connection = await mysql.createConnection(config);
        
        // 1. Verificar tablas existentes
        const [tables] = await connection.query(`
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = 'formacion_empresarial' 
            AND (TABLE_NAME LIKE '%recurso%' OR TABLE_NAME LIKE '%categoria%')
        `);
        
        console.log('📋 Tablas relacionadas:');
        tables.forEach(t => console.log(`  - ${t.TABLE_NAME}`));
        
        // 2. Verificar si existe categorias_recursos
        const [catTable] = await connection.query(`
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = 'formacion_empresarial' 
            AND TABLE_NAME = 'categorias_recursos'
        `);
        
        if (catTable.length === 0) {
            console.log('\n❌ La tabla categorias_recursos NO existe');
        } else {
            console.log('\n✓ La tabla categorias_recursos existe');
            
            // Ver estructura
            const [cols] = await connection.query(`DESCRIBE categorias_recursos`);
            console.log('\nEstructura:');
            cols.forEach(c => console.log(`  - ${c.Field}: ${c.Type}`));
            
            // Ver contenido
            const [data] = await connection.query(`SELECT * FROM categorias_recursos LIMIT 5`);
            console.log(`\nDatos: ${data.length} registros encontrados`);
        }
        
        // 3. Verificar recursos_aprendizaje
        const [recTable] = await connection.query(`
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = 'formacion_empresarial' 
            AND TABLE_NAME = 'recursos_aprendizaje'
        `);
        
        if (recTable.length === 0) {
            console.log('\n❌ La tabla recursos_aprendizaje NO existe');
        } else {
            console.log('\n✓ La tabla recursos_aprendizaje existe');
            
            // Ver columnas relevantes
            const [cols] = await connection.query(`DESCRIBE recursos_aprendizaje`);
            console.log(`\nTotal columnas: ${cols.length}`);
            
            // Ver contenido
            const [data] = await connection.query(`SELECT COUNT(*) as total FROM recursos_aprendizaje`);
            console.log(`Total registros: ${data[0].total}`);
        }
        
        console.log('\n✅ Debug completado');

    } catch (error) {
        console.error('❌ Error:', error.message);
    } finally {
        if (connection) {
            await connection.end();
        }
    }
}

debugRecursos();
