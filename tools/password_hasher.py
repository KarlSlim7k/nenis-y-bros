#!/usr/bin/env python3
"""
============================================================================
PASSWORD HASHER - Herramienta para gestionar hashes bcrypt
============================================================================
Genera y verifica hashes de contraseñas compatibles con PHP password_hash()
============================================================================
"""

import bcrypt
import sys

def generate_hash(password):
    """Genera un hash bcrypt de la contraseña"""
    password_bytes = password.encode('utf-8')
    salt = bcrypt.gensalt(rounds=10)
    hashed = bcrypt.hashpw(password_bytes, salt)
    return hashed.decode('utf-8')

def verify_hash(password, hash_string):
    """Verifica si una contraseña coincide con un hash"""
    password_bytes = password.encode('utf-8')
    hash_bytes = hash_string.encode('utf-8')
    return bcrypt.checkpw(password_bytes, hash_bytes)

def print_menu():
    """Muestra el menú principal"""
    print("\n" + "="*60)
    print("🔐 PASSWORD HASHER - Gestión de Hashes Bcrypt")
    print("="*60)
    print("\nOpciones:")
    print("  1. Generar hash de una contraseña")
    print("  2. Verificar contraseña contra un hash")
    print("  3. Generar múltiples hashes")
    print("  4. Salir")
    print("-"*60)

def generate_mode():
    """Modo: Generar hash"""
    print("\n📝 GENERAR HASH")
    print("-"*60)
    password = input("Ingresa la contraseña: ")
    
    if not password:
        print("❌ Error: La contraseña no puede estar vacía")
        return
    
    print("\n⏳ Generando hash...")
    hash_result = generate_hash(password)
    
    print("\n✅ Hash generado exitosamente:")
    print("-"*60)
    print(f"Contraseña: {password}")
    print(f"Hash:       {hash_result}")
    print("-"*60)
    
    # SQL para actualizar
    print("\n📋 SQL para actualizar en la base de datos:")
    print(f"UPDATE usuarios SET password_hash = '{hash_result}' WHERE email = 'usuario@email.com';")
    print()

def verify_mode():
    """Modo: Verificar hash"""
    print("\n🔍 VERIFICAR CONTRASEÑA")
    print("-"*60)
    password = input("Ingresa la contraseña a verificar: ")
    hash_string = input("Ingresa el hash: ")
    
    if not password or not hash_string:
        print("❌ Error: Ambos campos son requeridos")
        return
    
    print("\n⏳ Verificando...")
    
    try:
        is_valid = verify_hash(password, hash_string)
        
        if is_valid:
            print("\n✅ ¡COINCIDE! La contraseña es correcta")
            print(f"   Contraseña: '{password}' ✓")
        else:
            print("\n❌ NO COINCIDE. La contraseña es incorrecta")
            print(f"   Contraseña probada: '{password}' ✗")
    except Exception as e:
        print(f"\n❌ Error al verificar: {e}")
    
    print()

def batch_mode():
    """Modo: Generar múltiples hashes"""
    print("\n📝 GENERAR MÚLTIPLES HASHES")
    print("-"*60)
    print("Ingresa las contraseñas (una por línea, línea vacía para terminar):\n")
    
    passwords = []
    while True:
        pwd = input(f"Contraseña {len(passwords) + 1}: ")
        if not pwd:
            break
        passwords.append(pwd)
    
    if not passwords:
        print("❌ No se ingresaron contraseñas")
        return
    
    print("\n⏳ Generando hashes...\n")
    print("="*60)
    
    results = []
    for pwd in passwords:
        hash_result = generate_hash(pwd)
        results.append((pwd, hash_result))
        print(f"✓ Hash generado para: {pwd}")
    
    print("\n" + "="*60)
    print("📋 RESULTADOS")
    print("="*60 + "\n")
    
    for pwd, hash_result in results:
        print(f"Contraseña: {pwd}")
        print(f"Hash:       {hash_result}")
        print("-"*60)
    
    print("\n📋 SQL para insertar usuarios de prueba:")
    print("-"*60)
    for i, (pwd, hash_result) in enumerate(results, 1):
        email = f"usuario{i}@test.com"
        print(f"-- Contraseña: {pwd}")
        print(f"INSERT INTO usuarios (nombre, apellido, email, password_hash, tipo_usuario, estado)")
        print(f"VALUES ('Usuario', 'Prueba {i}', '{email}', '{hash_result}', 'emprendedor', 'activo');")
        print()

def main():
    """Función principal"""
    print("\n🚀 Iniciando Password Hasher...")
    
    # Si se pasan argumentos por línea de comandos
    if len(sys.argv) > 1:
        if sys.argv[1] == "generate" and len(sys.argv) > 2:
            password = sys.argv[2]
            hash_result = generate_hash(password)
            print(hash_result)
            return
        elif sys.argv[1] == "verify" and len(sys.argv) > 3:
            password = sys.argv[2]
            hash_string = sys.argv[3]
            is_valid = verify_hash(password, hash_string)
            print("VÁLIDO" if is_valid else "INVÁLIDO")
            return
    
    # Modo interactivo
    while True:
        print_menu()
        choice = input("\nSelecciona una opción (1-4): ").strip()
        
        if choice == "1":
            generate_mode()
        elif choice == "2":
            verify_mode()
        elif choice == "3":
            batch_mode()
        elif choice == "4":
            print("\n👋 ¡Hasta luego!")
            break
        else:
            print("\n❌ Opción inválida. Por favor selecciona 1-4.")
        
        input("\n🔄 Presiona ENTER para continuar...")

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n\n👋 Programa interrumpido. ¡Hasta luego!")
        sys.exit(0)
    except Exception as e:
        print(f"\n❌ Error fatal: {e}")
        sys.exit(1)
