const mysql = require('mysql2/promise');

async function checkColumns() {
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
        
        const [cols] = await connection.query(`DESCRIBE recursos_aprendizaje`);
        
        console.log('📋 Columnas de recursos_aprendizaje:\n');
        cols.forEach(c => console.log(`  ${c.Field}: ${c.Type} ${c.Null === 'NO' ? 'NOT NULL' : ''} ${c.Default ? `DEFAULT ${c.Default}` : ''}`));
        
    } catch (error) {
        console.error('❌ Error:', error.message);
    } finally {
        if (connection) await connection.end();
    }
}

checkColumns();
