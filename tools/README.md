# 🔐 Password Hasher

Herramienta para generar y verificar hashes bcrypt compatibles con PHP `password_hash()`.

## 📦 Instalación

```bash
# Instalar dependencias
pip install -r requirements.txt
```

## 🚀 Uso

### Modo Interactivo (Recomendado)

```bash
python password_hasher.py
```

El programa mostrará un menú con las siguientes opciones:

1. **Generar hash** - Crea un hash bcrypt de una contraseña
2. **Verificar contraseña** - Comprueba si una contraseña coincide con un hash
3. **Generar múltiples hashes** - Genera varios hashes en lote
4. **Salir** - Cierra el programa

### Modo Línea de Comandos

```bash
# Generar hash
python password_hasher.py generate "micontraseña"

# Verificar contraseña
python password_hasher.py verify "micontraseña" "$2y$10$hash..."
```

## 📋 Ejemplos

### Ejemplo 1: Generar un hash

```bash
$ python password_hasher.py

🔐 PASSWORD HASHER - Gestión de Hashes Bcrypt
==============================================================

Opciones:
  1. Generar hash de una contraseña
  2. Verificar contraseña contra un hash
  3. Generar múltiples hashes
  4. Salir
--------------------------------------------------------------

Selecciona una opción (1-4): 1

📝 GENERAR HASH
--------------------------------------------------------------
Ingresa la contraseña: password

⏳ Generando hash...

✅ Hash generado exitosamente:
--------------------------------------------------------------
Contraseña: password
Hash:       $2y$10$abcd1234...
--------------------------------------------------------------

📋 SQL para actualizar en la base de datos:
UPDATE usuarios SET password_hash = '$2y$10$abcd1234...' WHERE email = 'usuario@email.com';
```

### Ejemplo 2: Verificar un hash

```bash
Selecciona una opción (1-4): 2

🔍 VERIFICAR CONTRASEÑA
--------------------------------------------------------------
Ingresa la contraseña a verificar: password
Ingresa el hash: $2y$10$abcd1234...

⏳ Verificando...

✅ ¡COINCIDE! La contraseña es correcta
   Contraseña: 'password' ✓
```

### Ejemplo 3: Generar múltiples hashes

```bash
Selecciona una opción (1-4): 3

📝 GENERAR MÚLTIPLES HASHES
--------------------------------------------------------------
Ingresa las contraseñas (una por línea, línea vacía para terminar):

Contraseña 1: password
Contraseña 2: admin123
Contraseña 3: test2024
Contraseña 4: 

⏳ Generando hashes...

==============================================================
✓ Hash generado para: password
✓ Hash generado para: admin123
✓ Hash generado para: test2024

==============================================================
📋 RESULTADOS
==============================================================

Contraseña: password
Hash:       $2y$10$...
--------------------------------------------------------------
Contraseña: admin123
Hash:       $2y$10$...
--------------------------------------------------------------
Contraseña: test2024
Hash:       $2y$10$...
--------------------------------------------------------------

📋 SQL para insertar usuarios de prueba:
--------------------------------------------------------------
-- Contraseña: password
INSERT INTO usuarios (nombre, apellido, email, password_hash, tipo_usuario, estado)
VALUES ('Usuario', 'Prueba 1', 'usuario1@test.com', '$2y$10$...', 'emprendedor', 'activo');

-- Contraseña: admin123
INSERT INTO usuarios (nombre, apellido, email, password_hash, tipo_usuario, estado)
VALUES ('Usuario', 'Prueba 2', 'usuario2@test.com', '$2y$10$...', 'emprendedor', 'activo');
```

## 🔧 Uso en el Proyecto

Esta herramienta es útil para:

- ✅ Crear usuarios de prueba en la base de datos
- ✅ Actualizar contraseñas de usuarios existentes
- ✅ Verificar que los hashes almacenados sean correctos
- ✅ Depurar problemas de autenticación

## 📝 Notas

- Los hashes generados son **compatibles 100% con PHP** `password_hash()` y `password_verify()`
- Usa bcrypt con **10 rounds** (igual que PHP por defecto)
- Los hashes comienzan con `$2y$10$`
- Cada vez que generas un hash de la misma contraseña, obtendrás un hash diferente (esto es normal y seguro)

## ⚠️ Seguridad

- **NO** guardes contraseñas en texto plano
- **SIEMPRE** usa los hashes generados en la base de datos
- Esta herramienta es solo para desarrollo/testing
