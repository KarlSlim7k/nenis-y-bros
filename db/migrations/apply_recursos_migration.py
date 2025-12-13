#!/usr/bin/env python3
"""
Script para ejecutar migración de recursos en Railway MySQL
Ejecutar: python apply_recursos_migration.py
"""

import os
import sys
import subprocess
import time

def print_header(text):
    """Imprimir encabezado"""
    print("\n" + "="*60)
    print(f"  {text}")
    print("="*60 + "\n")

def print_step(step, text):
    """Imprimir paso"""
    print(f"[{step}] {text}")

def run_command(command, description):
    """Ejecutar comando y capturar salida"""
    print_step("▶", description)
    try:
        result = subprocess.run(
            command,
            shell=True,
            capture_output=True,
            text=True,
            timeout=120
        )
        
        if result.returncode == 0:
            print(f"    ✓ {description} - Éxito")
            if result.stdout:
                print(f"    Output: {result.stdout[:200]}")
            return True
        else:
            print(f"    ✗ {description} - Error")
            if result.stderr:
                print(f"    Error: {result.stderr[:500]}")
            return False
    except subprocess.TimeoutExpired:
        print(f"    ✗ Timeout ejecutando: {description}")
        return False
    except Exception as e:
        print(f"    ✗ Excepción: {str(e)}")
        return False

def main():
    print_header("MIGRACIÓN DE RECURSOS - RAILWAY")
    
    # 1. Verificar que railway CLI está instalado
    print_step("1", "Verificando Railway CLI...")
    result = subprocess.run(["railway", "--version"], capture_output=True)
    if result.returncode != 0:
        print("    ✗ Railway CLI no encontrado. Instalar con: npm i -g @railway/cli")
        sys.exit(1)
    print("    ✓ Railway CLI encontrado")
    
    # 2. Verificar conexión
    print_step("2", "Verificando conexión a Railway...")
    if not run_command("railway status", "Verificar status"):
        print("    ⚠ No conectado. Ejecutar: railway login")
        sys.exit(1)
    
    # 3. Leer archivo de migración
    migration_file = "db/migrations/fix_recursos_schema.sql"
    print_step("3", f"Leyendo {migration_file}...")
    
    if not os.path.exists(migration_file):
        print(f"    ✗ Archivo no encontrado: {migration_file}")
        sys.exit(1)
    
    with open(migration_file, 'r', encoding='utf-8') as f:
        sql_content = f.read()
    
    print(f"    ✓ Archivo leído ({len(sql_content)} caracteres)")
    
    # 4. Confirmar ejecución
    print_step("4", "Revisión de cambios a aplicar:")
    print("""
    Esta migración realizará los siguientes cambios:
    
    ✓ Agregar campos a recursos_aprendizaje:
      - slug, id_autor, contenido_texto, contenido_html
      - duracion_minutos, imagen_preview, video_preview
      - idioma, formato, licencia, destacado
      - fecha_publicacion, fecha_actualizacion
    
    ✓ Crear tablas:
      - descargas_recursos
      - calificaciones_recursos
      - vistas_recursos
    
    ✓ Crear triggers:
      - Actualizar contadores automáticamente
      - Recalcular calificaciones promedio
    """)
    
    respuesta = input("\n¿Deseas continuar? (si/no): ").lower()
    if respuesta not in ['si', 'sí', 's', 'y', 'yes']:
        print("    ⚠ Migración cancelada por el usuario")
        sys.exit(0)
    
    # 5. Aplicar migración
    print_step("5", "Aplicando migración...")
    
    # Guardar SQL temporalmente
    temp_file = "temp_migration.sql"
    with open(temp_file, 'w', encoding='utf-8') as f:
        f.write(sql_content)
    
    # Ejecutar con railway
    command = f'railway run mysql -e "source {temp_file}"'
    success = run_command(command, "Ejecutar migración SQL")
    
    # Limpiar archivo temporal
    if os.path.exists(temp_file):
        os.remove(temp_file)
    
    if not success:
        print("\n⚠ La migración puede haber fallado parcialmente.")
        print("Revisa los errores arriba y verifica la base de datos.")
        sys.exit(1)
    
    # 6. Verificar resultados
    print_step("6", "Verificando estructura...")
    
    verify_queries = [
        ("Verificar recursos_aprendizaje", "DESCRIBE recursos_aprendizaje;"),
        ("Contar recursos", "SELECT COUNT(*) FROM recursos_aprendizaje;"),
        ("Verificar descargas_recursos", "SHOW TABLES LIKE 'descargas_recursos';"),
        ("Verificar calificaciones_recursos", "SHOW TABLES LIKE 'calificaciones_recursos';"),
        ("Verificar vistas_recursos", "SHOW TABLES LIKE 'vistas_recursos';"),
    ]
    
    for desc, query in verify_queries:
        cmd = f'railway run mysql -e "{query}"'
        run_command(cmd, desc)
        time.sleep(1)
    
    # 7. Resultado final
    print_header("MIGRACIÓN COMPLETADA")
    print("""
    ✓ Migración aplicada exitosamente
    
    Próximos pasos:
    1. Verificar en Railway dashboard que las tablas existen
    2. Probar endpoints del módulo de recursos
    3. Revisar que los triggers funcionan correctamente
    
    Endpoints a probar:
    - GET  /api/v1/recursos
    - GET  /api/v1/recursos/estadisticas
    - POST /api/v1/recursos
    - PUT  /api/v1/recursos/{id}
    - POST /api/v1/recursos/{id}/descargar
    - POST /api/v1/recursos/{id}/calificar
    """)
    
    print("\n🎉 ¡Migración completada con éxito!")

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n\n⚠ Migración interrumpida por el usuario")
        sys.exit(1)
    except Exception as e:
        print(f"\n\n✗ Error inesperado: {str(e)}")
        import traceback
        traceback.print_exc()
        sys.exit(1)
