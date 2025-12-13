#!/usr/bin/env python3
"""
Script para verificar el esquema de las tablas en Railway
"""

import pymysql

# Configuración de Railway
DB_CONFIG = {
    'host': 'metro.proxy.rlwy.net',
    'port': 52451,
    'user': 'root',
    'password': 'hVRfZwfOYSrdWHloqDrsPCAuuAkPKNem',
    'database': 'formacion_empresarial',
    'charset': 'utf8mb4'
}

def check_schema():
    """Verifica el esquema de las tablas"""
    try:
        connection = pymysql.connect(**DB_CONFIG)
        
        with connection.cursor() as cursor:
            tables = ['tipos_diagnostico', 'areas_evaluacion', 'preguntas_diagnostico', 
                     'diagnosticos_realizados', 'respuestas_diagnostico']
            
            for table in tables:
                print(f"\n{'='*60}")
                print(f"📋 Tabla: {table}")
                print('='*60)
                
                # Verificar si existe
                cursor.execute(f"SHOW TABLES LIKE '{table}'")
                if not cursor.fetchone():
                    print(f"  ❌ La tabla no existe")
                    continue
                
                # Mostrar estructura
                cursor.execute(f"DESCRIBE {table}")
                columns = cursor.fetchall()
                
                print("\n  Columnas:")
                for col in columns:
                    print(f"    - {col[0]:30} {col[1]:20} {col[2]:10}")
        
        connection.close()
        
    except Exception as e:
        print(f"❌ Error: {e}")

if __name__ == '__main__':
    print("🔍 Verificando esquema de tablas en Railway...")
    check_schema()
