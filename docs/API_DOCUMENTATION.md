# DOCUMENTACIÓN DE LA API - FASE 1
## Sistema de Formación Empresarial - Nenis y Bros

---

## 📋 INFORMACIÓN GENERAL

**Base URL:** `http://localhost/nenis_y_bros/backend`  
**API Version:** v1  
**Formato de respuesta:** JSON  
**Charset:** UTF-8

---

## 🔐 AUTENTICACIÓN

La API utiliza **JWT (JSON Web Tokens)** para autenticación.

### Incluir token en las peticiones

```
Authorization: Bearer {tu_token_jwt}
```

---

## 📡 ENDPOINTS DISPONIBLES

### 1. RUTAS PÚBLICAS

#### Health Check
```http
GET /health
```

**Respuesta:**
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

---

### 2. AUTENTICACIÓN

#### Registro de Usuario
```http
POST /auth/register
Content-Type: application/json
```

**Body:**
```json
{
    "nombre": "Juan",
    "apellido": "Pérez",
    "email": "juan@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "telefono": "5551234567",
    "tipo_usuario": "emprendedor"
}
```

**Respuesta exitosa (201):**
```json
{
    "success": true,
    "message": "Usuario registrado exitosamente",
    "data": {
        "user": {
            "id_usuario": 1,
            "nombre": "Juan",
            "apellido": "Pérez",
            "email": "juan@example.com",
            "tipo_usuario": "emprendedor",
            "estado": "activo",
            "fecha_registro": "2025-11-15 10:30:00"
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
    }
}
```

#### Login
```http
POST /auth/login
Content-Type: application/json
```

**Body:**
```json
{
    "email": "juan@example.com",
    "password": "password123"
}
```

**Respuesta exitosa (200):**
```json
{
    "success": true,
    "message": "Login exitoso",
    "data": {
        "user": {
            "id_usuario": 1,
            "nombre": "Juan",
            "apellido": "Pérez",
            "email": "juan@example.com",
            "tipo_usuario": "emprendedor"
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
    }
}
```

#### Obtener Usuario Autenticado
```http
GET /auth/me
Authorization: Bearer {token}
```

**Respuesta exitosa (200):**
```json
{
    "success": true,
    "message": "Success",
    "data": {
        "user": {
            "id_usuario": 1,
            "nombre": "Juan",
            "apellido": "Pérez",
            "email": "juan@example.com",
            "tipo_usuario": "emprendedor",
            "foto_perfil_url": null
        }
    }
}
```

#### Logout
```http
POST /auth/logout
Authorization: Bearer {token}
```

#### Recuperar Contraseña
```http
POST /auth/forgot-password
Content-Type: application/json
```

**Body:**
```json
{
    "email": "juan@example.com"
}
```

#### Cambiar Contraseña
```http
POST /auth/change-password
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
    "current_password": "password123",
    "new_password": "newpassword456",
    "new_password_confirmation": "newpassword456"
}
```

---

### 3. PERFIL DE USUARIO

#### Obtener Perfil
```http
GET /users/profile
Authorization: Bearer {token}
```

#### Actualizar Perfil
```http
PUT /users/profile
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
    "nombre": "Juan Carlos",
    "apellido": "Pérez López",
    "telefono": "5559876543",
    "biografia": "Emprendedor apasionado por la tecnología",
    "ciudad": "Ciudad de México",
    "pais": "México"
}
```

#### Subir Foto de Perfil
```http
POST /users/profile/photo
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**FormData:**
- photo: (archivo de imagen)

#### Obtener Configuración de Privacidad
```http
GET /users/privacy-settings
Authorization: Bearer {token}
```

**Respuesta exitosa (200):**
```json
{
    "success": true,
    "message": "Success",
    "data": {
        "privacy_settings": {
            "perfil_publico": true,
            "mostrar_email": false,
            "mostrar_telefono": false,
            "mostrar_biografia": true,
            "mostrar_ubicacion": true,
            "permitir_mensajes": true
        }
    }
}
```

#### Actualizar Configuración de Privacidad
```http
PUT /users/privacy-settings
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
    "perfil_publico": false,
    "mostrar_email": false,
    "mostrar_telefono": false
}
```

**Nota:** Puedes enviar solo los campos que deseas actualizar.

**Ver documentación completa:** [API_PRIVACY_SETTINGS.md](./API_PRIVACY_SETTINGS.md)

#### Obtener Usuario Público
```http
GET /users/{id}
```

**Nota:** Este endpoint ahora aplica filtros de privacidad según la configuración del usuario.

---

### 4. ADMINISTRACIÓN

#### Dashboard de Estadísticas
```http
GET /admin/dashboard
Authorization: Bearer {token}
Requiere: rol administrador
```

**Respuesta exitosa (200):**
```json
{
    "success": true,
    "message": "Success",
    "data": {
        "statistics": {
            "total": 150,
            "por_estado": {
                "activo": 140,
                "inactivo": 8,
                "suspendido": 2
            },
            "por_tipo": {
                "emprendedor": 80,
                "empresario": 50,
                "mentor": 15,
                "administrador": 5
            },
            "registros_recientes": 25
        }
    }
}
```

#### Listar Usuarios
```http
GET /admin/users?page=1&limit=10&tipo_usuario=emprendedor&estado=activo&search=juan
Authorization: Bearer {token}
Requiere: rol administrador
```

**Parámetros de Query:**
- `page` (opcional): Número de página (default: 1)
- `limit` (opcional): Registros por página (default: 10)
- `tipo_usuario` (opcional): Filtrar por tipo
- `estado` (opcional): Filtrar por estado
- `search` (opcional): Buscar por nombre, apellido o email

#### Obtener Detalles de Usuario
```http
GET /admin/users/{id}
Authorization: Bearer {token}
Requiere: rol administrador
```

#### Actualizar Estado de Usuario
```http
PUT /admin/users/{id}/status
Authorization: Bearer {token}
Content-Type: application/json
Requiere: rol administrador
```

**Body:**
```json
{
    "estado": "suspendido"
}
```

#### Eliminar Usuario
```http
DELETE /admin/users/{id}
Authorization: Bearer {token}
Requiere: rol administrador
```

---

## 📊 CÓDIGOS DE RESPUESTA

| Código | Descripción |
|--------|-------------|
| 200 | OK - Solicitud exitosa |
| 201 | Created - Recurso creado exitosamente |
| 204 | No Content - Operación exitosa sin contenido |
| 400 | Bad Request - Error en la solicitud |
| 401 | Unauthorized - No autenticado |
| 403 | Forbidden - Sin permisos |
| 404 | Not Found - Recurso no encontrado |
| 422 | Unprocessable Entity - Errores de validación |
| 500 | Internal Server Error - Error del servidor |

---

## 🔒 ESTRUCTURA DE RESPUESTAS

### Respuesta Exitosa
```json
{
    "success": true,
    "message": "Mensaje descriptivo",
    "data": { ... },
    "timestamp": "2025-11-15 10:30:00"
}
```

### Respuesta de Error
```json
{
    "success": false,
    "message": "Mensaje de error",
    "timestamp": "2025-11-15 10:30:00"
}
```

### Respuesta de Error de Validación
```json
{
    "success": false,
    "message": "Errores de validación",
    "errors": {
        "email": "Email es requerido",
        "password": "Password debe tener al menos 8 caracteres"
    },
    "timestamp": "2025-11-15 10:30:00"
}
```

---

## 🛠️ EJEMPLOS DE USO

### JavaScript (Fetch API)

```javascript
// Login
async function login(email, password) {
    const response = await fetch('http://localhost/nenis_y_bros/backend/auth/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ email, password })
    });
    
    const data = await response.json();
    
    if (data.success) {
        localStorage.setItem('token', data.data.token);
        return data.data.user;
    }
    
    throw new Error(data.message);
}

// Obtener perfil
async function getProfile() {
    const token = localStorage.getItem('token');
    
    const response = await fetch('http://localhost/nenis_y_bros/backend/users/profile', {
        headers: {
            'Authorization': `Bearer ${token}`
        }
    });
    
    const data = await response.json();
    return data.data.user;
}
```

### cURL

```bash
# Login
curl -X POST http://localhost/nenis_y_bros/backend/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"juan@example.com","password":"password123"}'

# Obtener perfil (con token)
curl -X GET http://localhost/nenis_y_bros/backend/users/profile \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## 📝 NOTAS IMPORTANTES

1. **Tokens JWT**: Los tokens tienen una expiración de 2 horas por defecto
2. **Validación**: Todos los endpoints validan los datos de entrada
3. **Seguridad**: Las contraseñas se almacenan hasheadas con bcrypt
4. **CORS**: Habilitado en modo desarrollo
5. **Rate Limiting**: (Pendiente de implementación)

---

**Última actualización:** Noviembre 2025  
**Versión:** 1.0 - Fase 1
