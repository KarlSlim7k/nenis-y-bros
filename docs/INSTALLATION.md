# GUÍA DE INSTALACIÓN Y CONFIGURACIÓN
## Sistema de Formación Empresarial - Nenis y Bros

---

## 📋 REQUISITOS PREVIOS

- **XAMPP** (Apache + MySQL + PHP 7.4+)
- **Navegador Web** moderno
- **Editor de código** (VS Code recomendado)

---

## 🚀 INSTALACIÓN

### 1. Clonar/Descargar el Proyecto

Coloca el proyecto en la carpeta de XAMPP:
```
C:\xampp\htdocs\nenis_y_bros\
```

### 2. Crear Base de Datos

1. Inicia XAMPP y activa **Apache** y **MySQL**
2. Abre phpMyAdmin: `http://localhost/phpmyadmin`
3. Crea una nueva base de datos llamada `formacion_empresarial`
4. Importa el archivo: `db/nyd_db.sql`

### 3. Configurar Variables de Entorno

1. Copia el archivo `.env.example` y renómbralo a `.env`:
   ```bash
   copy .env.example .env
   ```

2. Edita el archivo `.env` y configura tus valores:
   ```env
   DB_HOST=localhost
   DB_DATABASE=formacion_empresarial
   DB_USERNAME=root
   DB_PASSWORD=
   
   JWT_SECRET=genera_una_clave_secreta_aqui_32_chars
   ENCRYPTION_KEY=otra_clave_de_encriptacion_segura
   ```

### 4. Configurar Permisos de Carpetas

Asegúrate de que estas carpetas tengan permisos de escritura:
- `backend/logs/`
- `backend/sessions/`
- `uploads/profiles/`
- `uploads/temp/`

En Windows con XAMPP generalmente no es necesario cambiar permisos.

### 5. Configurar Apache (opcional)

Si deseas usar URLs amigables sin `/backend/`, edita:
`C:\xampp\apache\conf\extra\httpd-vhosts.conf`

Agrega:
```apache
<VirtualHost *:80>
    ServerName nenis.local
    DocumentRoot "C:/xampp/htdocs/nenis_y_bros/backend"
    
    <Directory "C:/xampp/htdocs/nenis_y_bros/backend">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Y edita: `C:\Windows\System32\drivers\etc\hosts`

Agrega:
```
127.0.0.1 nenis.local
```

Reinicia Apache.

---

## ✅ VERIFICAR INSTALACIÓN

### 1. Verificar API

Abre en tu navegador:
```
http://localhost/nenis_y_bros/backend/health
```

Deberías ver:
```json
{
    "success": true,
    "message": "Success",
    "data": {
        "status": "ok",
        "timestamp": "2025-11-15 10:30:00"
    }
}
```

### 2. Crear Usuario Administrador

Puedes usar el endpoint de registro o insertar directamente en la BD:

```sql
INSERT INTO usuarios (nombre, apellido, email, password_hash, tipo_usuario, estado) 
VALUES (
    'Admin',
    'Sistema',
    'admin@nenisybros.com',
    '$2y$12$ejemplo_de_hash_aqui', -- Usa password_hash('tu_password', PASSWORD_BCRYPT)
    'administrador',
    'activo'
);
```

O usa el endpoint:
```bash
curl -X POST http://localhost/nenis_y_bros/backend/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Admin",
    "apellido": "Sistema",
    "email": "admin@nenisybros.com",
    "password": "Admin123!",
    "password_confirmation": "Admin123!",
    "tipo_usuario": "administrador"
  }'
```

---

## 🧪 PROBAR LA API

### Usando Postman o Thunder Client

1. Importa la colección de endpoints (ver documentación API)
2. Registra un usuario de prueba
3. Haz login y guarda el token
4. Prueba los endpoints protegidos con el token

### Usando cURL

```bash
# Registro
curl -X POST http://localhost/nenis_y_bros/backend/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Test",
    "apellido": "User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "tipo_usuario": "emprendedor"
  }'

# Login
curl -X POST http://localhost/nenis_y_bros/backend/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }'
```

---

## 🔧 SOLUCIÓN DE PROBLEMAS

### Error: "No se puede conectar a la base de datos"
- Verifica que MySQL esté corriendo en XAMPP
- Verifica las credenciales en `.env`
- Verifica que la base de datos exista

### Error: "Token inválido"
- El token puede haber expirado (2 horas)
- Verifica que el `JWT_SECRET` en `.env` sea consistente
- Haz login nuevamente para obtener un nuevo token

### Error 404 en las rutas
- Verifica que Apache tenga `mod_rewrite` habilitado
- Verifica que el archivo `.htaccess` exista en `/backend/`
- Verifica la configuración de `AllowOverride` en Apache

### Error: "Cannot write to log file"
- Verifica permisos de la carpeta `backend/logs/`
- En Windows: click derecho > Propiedades > Seguridad

### Error: "Failed to upload file"
- Verifica permisos de `uploads/profiles/`
- Verifica `upload_max_filesize` en `php.ini`
- Verifica `post_max_size` en `php.ini`

---

## 📱 PRÓXIMOS PASOS

1. ✅ **Fase 1 completada** - Sistema de autenticación funcional
2. 📝 **Crear páginas frontend** - Login, registro, dashboard
3. 🎨 **Aplicar diseño UI/UX** - Interfaz de usuario
4. 🚀 **Continuar con Fase 2** - Sistema de cursos

---

## 📞 SOPORTE

Para problemas o dudas:
- Revisar `backend/logs/app_*.log`
- Revisar `backend/logs/database_*.log`
- Consultar la documentación de la API

---

**Última actualización:** Noviembre 2025  
**Versión:** 1.0 - Fase 1
