import pymysql

conn = pymysql.connect(
    host='metro.proxy.rlwy.net',
    port=52451,
    user='root',
    password='hVRfZwfOYSrdWHloqDrsPCAuuAkPKNem',
    database='formacion_empresarial'
)

cur = conn.cursor()

# Agregar estados pendiente y rechazado al enum
sql = """
ALTER TABLE productos_vitrina 
MODIFY COLUMN estado ENUM('borrador','publicado','pausado','agotado','pendiente','rechazado') 
NOT NULL DEFAULT 'borrador'
"""

try:
    cur.execute(sql)
    conn.commit()
    print("✅ Campo estado actualizado correctamente")
except Exception as e:
    print(f"❌ Error: {e}")
finally:
    conn.close()
