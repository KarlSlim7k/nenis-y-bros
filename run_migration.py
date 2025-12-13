#!/usr/bin/env python3
"""
Script para ejecutar la migración SQL en Railway usando pymysql
Ejecutar: python run_migration.py
"""

import pymysql
import sys
from pathlib import Path

# Configuración de Railway (desde variables)
DB_CONFIG = {
    'host': 'metro.proxy.rlwy.net',
    'port': 52451,
    'user': 'root',
    'password': 'hVRfZwfOYSrdWHloqDrsPCAuuAkPKNem',
    'database': 'formacion_empresarial',
    'charset': 'utf8mb4'
}

def run_migration():
    """Ejecuta el script de migración SQL"""
    print("🔄 Iniciando migración de tablas de diagnósticos...")
    
    # Leer archivo SQL
    sql_file = Path(__file__).parent / 'db' / 'migrations' / 'fix_diagnosticos_schema.sql'
    
    if not sql_file.exists():
        print(f"❌ Error: No se encontró el archivo {sql_file}")
        return False
    
    try:
        print(f"📖 Leyendo script SQL desde {sql_file}")
        with open(sql_file, 'r', encoding='utf-8') as f:
            sql_content = f.read()
        
        # Conectar a la base de datos
        print(f"🔌 Conectando a {DB_CONFIG['host']}:{DB_CONFIG['port']}...")
        connection = pymysql.connect(**DB_CONFIG)
        
        try:
            with connection.cursor() as cursor:
                # Dividir en statements individuales y ejecutar
                statements = [s.strip() for s in sql_content.split(';') if s.strip()]
                
                total = len(statements)
                print(f"📊 Ejecutando {total} statements SQL...")
                
                errors = []
                success_count = 0
                
                for i, statement in enumerate(statements, 1):
                    try:
                        if statement.upper().startswith('SELECT'):
                            print(f"\n[{i}/{total}] Ejecutando verificación...")
                            cursor.execute(statement)
                            results = cursor.fetchall()
                            for row in results:
                                print(f"  ✓ {row}")
                        else:
                            cursor.execute(statement)
                            success_count += 1
                            print(f"  [{i}/{total}] ✓", end='\r')
                    except pymysql.Error as e:
                        # Ignorar errores de "tabla ya existe" o "columna ya existe"
                        if e.args[0] in (1050, 1060, 1061, 1062):  # Table/column/index exists
                            print(f"  [{i}/{total}] ⚠️  Ya existe, omitiendo...")
                            success_count += 1
                        else:
                            error_msg = f"Statement {i}: {str(e)}"
                            errors.append(error_msg)
                            print(f"  [{i}/{total}] ❌ {str(e)}")
                
                connection.commit()
                
                print(f"\n📊 Resumen:")
                print(f"  ✅ Exitosos: {success_count}/{total}")
                if errors:
                    print(f"  ❌ Errores: {len(errors)}")
                    for error in errors:
                        print(f"     - {error}")
                
                if success_count > 0:
                    print(f"\n✅ Migración completada!")
                    return True
                else:
                    print(f"\n❌ No se pudo completar la migración")
                    return False
                
        finally:
            connection.close()
            
    except pymysql.Error as e:
        print(f"\n❌ Error de MySQL: {e}")
        return False
    except Exception as e:
        print(f"\n❌ Error: {e}")
        return False

if __name__ == '__main__':
    print("=" * 60)
    print("  MIGRACIÓN DE TABLAS DE DIAGNÓSTICOS - RAILWAY")
    print("=" * 60)
    
    # Verificar que pymysql está instalado
    try:
        import pymysql
    except ImportError:
        print("❌ pymysql no está instalado.")
        print("💡 Instalar con: pip install pymysql")
        sys.exit(1)
    
    success = run_migration()
    sys.exit(0 if success else 1)
