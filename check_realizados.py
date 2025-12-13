#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Verificar esquema de diagnosticos_realizados"""
import pymysql

DB_CONFIG = {
    'host': 'metro.proxy.rlwy.net',
    'port': 52451,
    'user': 'root',
    'password': 'hVRfZwfOYSrdWHloqDrsPCAuuAkPKNem',
    'database': 'formacion_empresarial',
    'charset': 'utf8mb4'
}

try:
    conn = pymysql.connect(**DB_CONFIG)
    with conn.cursor() as cursor:
        # Verificar diagnosticos_realizados
        print("\n=== diagnosticos_realizados ===")
        cursor.execute("DESCRIBE diagnosticos_realizados")
        for col in cursor.fetchall():
            print(f"  - {col[0]:30} {col[1]}")
        
        # Verificar perfiles_empresariales
        print("\n=== perfiles_empresariales ===")
        cursor.execute("DESCRIBE perfiles_empresariales")
        for col in cursor.fetchall():
            print(f"  - {col[0]:30} {col[1]}")
        
        # Verificar la consulta que falla
        print("\n=== Probando consulta ===")
        query = """SELECT 
            dr.id_diagnostico_realizado,
            dr.id_usuario,
            dr.estado,
            td.nombre as tipo_diagnostico,
            u.nombre as usuario_nombre
        FROM diagnosticos_realizados dr
        INNER JOIN tipos_diagnostico td ON dr.id_tipo_diagnostico = td.id_tipo_diagnostico
        INNER JOIN usuarios u ON dr.id_usuario = u.id_usuario
        LIMIT 1"""
        
        try:
            cursor.execute(query)
            print("Consulta OK")
        except Exception as e:
            print(f"Error en consulta: {e}")
            
            # Intentar con id_diagnostico en lugar de id_tipo_diagnostico
            print("\n=== Probando con id_diagnostico ===")
            query2 = """SELECT 
                dr.id_diagnostico_realizado,
                dr.id_usuario,
                dr.estado
            FROM diagnosticos_realizados dr
            INNER JOIN usuarios u ON dr.id_usuario = u.id_usuario
            LIMIT 1"""
            try:
                cursor.execute(query2)
                print("Consulta alternativa OK")
            except Exception as e2:
                print(f"Error: {e2}")
        
    conn.close()
    print("\nVerificacion completada")
except Exception as e:
    print(f"ERROR: {e}")
