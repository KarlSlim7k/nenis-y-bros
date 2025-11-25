# 🔧 SOLUCIÓN: Comandos SQL en PowerShell

## Problema Identificado

Al intentar ejecutar comandos SQL en PowerShell, se encontraron los siguientes problemas:

### 1. Operador `&&` no válido en PowerShell

**❌ Error:**
```powershell
cd c:\xampp\htdocs\nenis_y_bros\nenis_y_bros && mysql -u root -e "..."
```

**Mensaje de error:**
```
El token '&&' no es un separador de instrucciones válido en esta versión.
```

**✅ Solución:**
En PowerShell, ejecuta los comandos en líneas separadas o usa `;`:
```powershell
cd c:\xampp\htdocs\nenis_y_bros\nenis_y_bros
C:\xampp\mysql\bin\mysql.exe -u root formacion_empresarial -e "..."
```

---

### 2. Operador `<` (redirección) no soportado

**❌ Error:**
```powershell
mysql -u root database < archivo.sql
```

**Mensaje de error:**
```
El operador '<' está reservado para uso futuro.
```

**✅ Solución:**
Usa `Get-Content` con pipe:
```powershell
Get-Content archivo.sql | C:\xampp\mysql\bin\mysql.exe -u root database
```

---

### 3. MySQL no está en el PATH

**❌ Error:**
```powershell
mysql -u root
```

**Mensaje de error:**
```
El término 'mysql' no se reconoce como nombre de un cmdlet...
```

**✅ Solución:**
Usa la ruta completa de XAMPP:
```powershell
C:\xampp\mysql\bin\mysql.exe -u root
```

---

### 4. Problemas con comillas anidadas

**❌ Error:**
```powershell
mysql.exe -u root -e "UPDATE tabla SET json = '{\"key\": \"value\"}'"
```

**Problema:** PowerShell escapa las comillas de forma diferente.

**✅ Solución:**
Crea un archivo SQL temporal y ejecútalo:

**Archivo: `temp_query.sql`**
```sql
UPDATE tabla SET json = '{"key": "value"}';
```

**Ejecutar:**
```powershell
Get-Content temp_query.sql | C:\xampp\mysql\bin\mysql.exe -u root database
```

---

## 📝 Comandos Correctos Aplicados

### 1. Crear base de datos
```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS formacion_empresarial CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 2. Importar schema completo
```powershell
Get-Content db\nyd_db.sql | C:\xampp\mysql\bin\mysql.exe -u root formacion_empresarial
```

### 3. Ejecutar migración de privacidad
```powershell
Get-Content db\migrations\update_privacy_defaults.sql | C:\xampp\mysql\bin\mysql.exe -u root
```

### 4. Verificar resultados
```powershell
C:\xampp\mysql\bin\mysql.exe -u root formacion_empresarial -e "SELECT id_usuario, email, configuracion_privacidad FROM usuarios LIMIT 3;"
```

---

## 🎯 Migración de Privacidad Aplicada

### Archivo creado: `update_privacy_defaults.sql`

```sql
USE formacion_empresarial;
UPDATE usuarios 
SET configuracion_privacidad = '{"perfil_publico": true, "mostrar_email": false, "mostrar_telefono": false, "mostrar_biografia": true, "mostrar_ubicacion": true, "permitir_mensajes": true}' 
WHERE configuracion_privacidad IS NULL;
SELECT 'Migracion completada' AS resultado;
```

### Resultado
```
✅ Migracion completada
```

---

## 💡 Tips para PowerShell + MySQL

### 1. Alias útil
Agrega a tu perfil de PowerShell (`$PROFILE`):
```powershell
function mysql { C:\xampp\mysql\bin\mysql.exe @args }
function mysqldump { C:\xampp\mysql\bin\mysqldump.exe @args }
```

Después puedes usar simplemente:
```powershell
mysql -u root database
```

### 2. Comandos múltiples
En lugar de `&&`, usa `;`:
```powershell
cd proyecto ; npm install ; npm start
```

### 3. Redirección de salida
```powershell
# Guardar resultado de query
C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT * FROM usuarios" > usuarios.txt

# Ejecutar archivo SQL
Get-Content script.sql | C:\xampp\mysql\bin\mysql.exe -u root database
```

### 4. Variables de entorno
```powershell
$env:PATH += ";C:\xampp\mysql\bin"
```

---

## 🐛 Debugging

### Ver bases de datos disponibles
```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "SHOW DATABASES;"
```

### Ver estructura de tabla
```powershell
C:\xampp\mysql\bin\mysql.exe -u root database -e "DESCRIBE tabla;"
```

### Ver columnas específicas
```powershell
C:\xampp\mysql\bin\mysql.exe -u root database -e "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'tabla';"
```

---

## ✅ Estado Final

- ✅ Base de datos `formacion_empresarial` verificada
- ✅ Columna `configuracion_privacidad` existente
- ✅ Usuarios actualizados con configuración por defecto
- ✅ Migración completada exitosamente

---

**Fecha:** 15 de Noviembre 2025  
**Sistema:** Windows + PowerShell + XAMPP  
**Problema resuelto:** Comandos SQL en PowerShell
