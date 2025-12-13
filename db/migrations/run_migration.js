const fs = require('fs');
const mysql = require('mysql2/promise');

async function runMigration() {
    console.log('🚀 Iniciando migración de recursos...\n');
    
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
        connection = await mysql.createConnection(config);
        console.log('✓ Conectado a MySQL\n');
        
        // Leer archivo SQL
        const sql = fs.readFileSync('db/migrations/fix_recursos_schema.sql', 'utf8');
        
        // Filtrar comentarios y dividir en comandos individuales
        const commands = sql
            .split('\n')
            .filter(line => !line.trim().startsWith('--') && line.trim().length > 0)
            .join('\n')
            .split(';')
            .filter(cmd => cmd.trim().length > 0);
        
        console.log(`📝 Ejecutando ${commands.length} comandos SQL...\n`);
        
        for (let i = 0; i < commands.length; i++) {
            const cmd = commands[i].trim();
            if (cmd) {
                try {
                    await connection.query(cmd);
                    if (cmd.includes('ALTER TABLE')) {
                        console.log(`  ✓ ALTER TABLE ejecutado (${i + 1}/${commands.length})`);
                    } else if (cmd.includes('CREATE TABLE')) {
                        console.log(`  ✓ CREATE TABLE ejecutado (${i + 1}/${commands.length})`);
                    } else if (cmd.includes('CREATE TRIGGER')) {
                        console.log(`  ✓ CREATE TRIGGER ejecutado (${i + 1}/${commands.length})`);
                    } else {
                        console.log(`  ✓ Comando ejecutado (${i + 1}/${commands.length})`);
                    }
                } catch (error) {
                    if (error.code === 'ER_DUP_FIELDNAME') {
                        console.log(`  ⚠ Campo ya existe, continuando... (${i + 1}/${commands.length})`);
                    } else if (error.code === 'ER_TABLE_EXISTS_ERROR') {
                        console.log(`  ⚠ Tabla ya existe, continuando... (${i + 1}/${commands.length})`);
                    } else if (error.code === 'ER_TRG_ALREADY_EXISTS') {
                        console.log(`  ⚠ Trigger ya existe, continuando... (${i + 1}/${commands.length})`);
                    } else {
                        console.error(`  ✗ Error: ${error.message}`);
                        // Continuar con el siguiente comando
                    }
                }
            }
        }
        
        console.log('\n🎉 Migración completada!\n');
        
        // Verificar estructura
        console.log('📊 Verificando estructura...\n');
        
        const [fields] = await connection.query('SHOW COLUMNS FROM recursos_aprendizaje');
        console.log(`  ✓ recursos_aprendizaje tiene ${fields.length} columnas`);
        
        const newFields = ['slug', 'id_autor', 'contenido_texto', 'destacado', 'idioma', 'fecha_publicacion'];
        const fieldNames = fields.map(f => f.Field);
        newFields.forEach(field => {
            if (fieldNames.includes(field)) {
                console.log(`  ✓ Campo '${field}' presente`);
            } else {
                console.log(`  ✗ Campo '${field}' NO encontrado`);
            }
        });
        
        const [tables] = await connection.query("SHOW TABLES LIKE '%recursos%'");
        console.log(`\n  ✓ Encontradas ${tables.length} tablas de recursos`);
        
        const [triggers] = await connection.query("SHOW TRIGGERS WHERE `Table` LIKE '%recursos%'");
        console.log(`  ✓ Encontrados ${triggers.length} triggers`);
        
        console.log('\n✅ Todo listo!\n');
        
    } catch (error) {
        console.error('\n❌ Error fatal:', error.message);
        process.exit(1);
    } finally {
        if (connection) {
            await connection.end();
        }
    }
}

runMigration();
