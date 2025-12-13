const mysql = require('mysql2/promise');

async function createCategoriasRecursos() {
    const config = {
        host: 'metro.proxy.rlwy.net',
        port: 52451,
        user: 'root',
        password: 'hVRfZwfOYSrdWHloqDrsPCAuuAkPKNem',
        database: 'formacion_empresarial'
    };

    let connection;
    try {
        console.log('🚀 Creando tabla categorias_recursos...\n');
        connection = await mysql.createConnection(config);
        
        // 1. Crear tabla categorias_recursos
        const createTable = `
            CREATE TABLE IF NOT EXISTS categorias_recursos (
                id_categoria INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(100) NOT NULL,
                slug VARCHAR(120) NOT NULL UNIQUE,
                descripcion TEXT,
                icono VARCHAR(50) DEFAULT 'folder',
                color VARCHAR(20) DEFAULT '#6366f1',
                orden INT DEFAULT 0,
                activa TINYINT(1) DEFAULT 1,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_slug (slug),
                INDEX idx_orden (orden)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        `;
        
        await connection.query(createTable);
        console.log('✓ Tabla categorias_recursos creada');
        
        // 2. Insertar categorías por defecto
        const categorias = [
            { nombre: 'Artículos', slug: 'articulos', icono: '📄', color: '#3b82f6', orden: 1 },
            { nombre: 'E-Books', slug: 'ebooks', icono: '📕', color: '#8b5cf6', orden: 2 },
            { nombre: 'Plantillas', slug: 'plantillas', icono: '📋', color: '#10b981', orden: 3 },
            { nombre: 'Herramientas', slug: 'herramientas', icono: '🛠️', color: '#f59e0b', orden: 4 },
            { nombre: 'Videos', slug: 'videos', icono: '🎥', color: '#ef4444', orden: 5 },
            { nombre: 'Infografías', slug: 'infografias', icono: '📊', color: '#06b6d4', orden: 6 },
            { nombre: 'Podcasts', slug: 'podcasts', icono: '🎙️', color: '#ec4899', orden: 7 },
            { nombre: 'Cursos', slug: 'cursos', icono: '🎓', color: '#6366f1', orden: 8 },
            { nombre: 'Guías', slug: 'guias', icono: '📚', color: '#84cc16', orden: 9 }
        ];
        
        const insertQuery = `
            INSERT INTO categorias_recursos (nombre, slug, icono, color, orden, activa)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)
        `;
        
        for (const cat of categorias) {
            await connection.query(insertQuery, [cat.nombre, cat.slug, cat.icono, cat.color, cat.orden]);
            console.log(`  ✓ Categoría '${cat.nombre}' insertada`);
        }
        
        // 3. Verificar
        const [result] = await connection.query('SELECT COUNT(*) as total FROM categorias_recursos');
        console.log(`\n📊 Total categorías: ${result[0].total}`);
        
        console.log('\n✅ Tabla categorias_recursos lista');

    } catch (error) {
        console.error('❌ Error:', error.message);
    } finally {
        if (connection) {
            await connection.end();
        }
    }
}

createCategoriasRecursos();
