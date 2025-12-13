#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Verificar esquema de preguntas_diagnostico"""
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
        cursor.execute("DESCRIBE preguntas_diagnostico")
        cols = cursor.fetchall()
        
        print("\nColumnas en preguntas_diagnostico:")
        for col in cols:
            print(f"  - {col[0]}")
        
        # Verificar si id_area existe
        has_id_area = any(col[0] == 'id_area' for col in cols)
        print(f"\nTiene columna id_area: {'SI' if has_id_area else 'NO'}")
        
    conn.close()
    print("\nOK: Esquema verificado")
except Exception as e:
    print(f"ERROR: {e}")
