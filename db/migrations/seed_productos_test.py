import pymysql

conn = pymysql.connect(
    host='metro.proxy.rlwy.net',
    port=52451,
    user='root',
    password='hVRfZwfOYSrdWHloqDrsPCAuuAkPKNem',
    database='formacion_empresarial'
)

cur = conn.cursor()

# Primero obtener un usuario existente
cur.execute("SELECT id_usuario FROM usuarios LIMIT 1")
user = cur.fetchone()

if not user:
    print("❌ No hay usuarios en la base de datos")
    conn.close()
    exit()

id_usuario = user[0]
print(f"Usando usuario ID: {id_usuario}")

# Verificar si hay categorías de productos
cur.execute("SELECT id_categoria_producto, nombre FROM categorias_productos LIMIT 5")
categorias = cur.fetchall()

if not categorias:
    # Crear algunas categorías
    categorias_data = [
        ('Alimentos y Bebidas', 'Productos alimenticios artesanales'),
        ('Artesanías', 'Productos hechos a mano'),
        ('Ropa y Accesorios', 'Moda y complementos'),
        ('Belleza y Cuidado Personal', 'Productos de belleza'),
        ('Hogar y Decoración', 'Artículos para el hogar')
    ]
    for nombre, desc in categorias_data:
        cur.execute("INSERT INTO categorias_productos (nombre, descripcion) VALUES (%s, %s)", (nombre, desc))
    conn.commit()
    
    cur.execute("SELECT id_categoria_producto, nombre FROM categorias_productos LIMIT 5")
    categorias = cur.fetchall()
    print("✅ Categorías creadas")

print(f"Categorías disponibles: {categorias}")

# Insertar productos de prueba
productos = [
    {
        'nombre': 'Mermelada Artesanal de Fresa',
        'descripcion': 'Deliciosa mermelada hecha con fresas orgánicas de la región.',
        'descripcion_corta': 'Mermelada orgánica de fresa 350g',
        'precio': 85.00,
        'estado': 'publicado',
        'id_categoria': categorias[0][0] if len(categorias) > 0 else None
    },
    {
        'nombre': 'Bolsa Tejida a Mano',
        'descripcion': 'Bolsa tradicional tejida a mano por artesanas locales. Diseño único.',
        'descripcion_corta': 'Bolsa artesanal de fibras naturales',
        'precio': 450.00,
        'estado': 'pendiente',
        'id_categoria': categorias[1][0] if len(categorias) > 1 else None
    },
    {
        'nombre': 'Jabón de Lavanda Natural',
        'descripcion': 'Jabón artesanal elaborado con aceite esencial de lavanda y aceite de coco.',
        'descripcion_corta': 'Jabón natural de lavanda 100g',
        'precio': 65.00,
        'estado': 'publicado',
        'id_categoria': categorias[3][0] if len(categorias) > 3 else None
    },
    {
        'nombre': 'Velas Aromáticas Set x3',
        'descripcion': 'Set de 3 velas aromáticas de soya con fragancias naturales: vainilla, canela y lavanda.',
        'descripcion_corta': 'Set de velas aromáticas naturales',
        'precio': 180.00,
        'estado': 'pendiente',
        'id_categoria': categorias[4][0] if len(categorias) > 4 else None
    },
    {
        'nombre': 'Miel de Abeja Pura',
        'descripcion': 'Miel 100% pura de abeja, cosechada de apiarios locales sin procesar.',
        'descripcion_corta': 'Miel pura de abeja 500ml',
        'precio': 120.00,
        'estado': 'publicado',
        'id_categoria': categorias[0][0] if len(categorias) > 0 else None
    },
    {
        'nombre': 'Aretes de Plata Artesanales',
        'descripcion': 'Aretes elaborados a mano en plata .925 con diseños inspirados en la naturaleza.',
        'descripcion_corta': 'Aretes de plata hechos a mano',
        'precio': 350.00,
        'estado': 'rechazado',
        'id_categoria': categorias[2][0] if len(categorias) > 2 else None
    }
]

for p in productos:
    try:
        sql = """
        INSERT INTO productos_vitrina 
        (id_usuario, id_categoria_producto, nombre, descripcion, descripcion_corta, precio, estado, destacado, vistas)
        VALUES (%s, %s, %s, %s, %s, %s, %s, 0, 0)
        """
        cur.execute(sql, (
            id_usuario,
            p['id_categoria'],
            p['nombre'],
            p['descripcion'],
            p['descripcion_corta'],
            p['precio'],
            p['estado']
        ))
        print(f"✅ Producto creado: {p['nombre']} ({p['estado']})")
    except Exception as e:
        print(f"❌ Error al crear {p['nombre']}: {e}")

conn.commit()
print("\n✅ Productos de prueba creados exitosamente")
conn.close()
