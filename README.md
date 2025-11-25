# 🚀 Sistema de Formación Empresarial
## Nenis y Bros

Sistema integral para gestión de usuarios, cursos, diagnósticos empresariales, logros y vitrina de productos.

---

## 📋 CARACTERÍSTICAS PRINCIPALES

### ✅ Fase 1 - COMPLETADA
- ✅ Sistema de autenticación (JWT)
- ✅ Registro y login de usuarios
- ✅ Gestión de perfiles
- ✅ Recuperación de contraseña
- ✅ Panel de administración básico
- ✅ CRUD de usuarios
- ✅ Sistema de roles (emprendedor, empresario, mentor, administrador)
- ✅ API RESTful documentada
- ✅ Logging y manejo de errores
- ✅ Validación de datos

### 🔄 En Desarrollo
- 🎓 Sistema de cursos y aprendizaje (Fase 2)
- 🏢 Perfiles empresariales y diagnósticos (Fase 3)
- 🎮 Gamificación y engagement (Fase 4)
- 🛍️ Vitrina de productos y mentorías (Fase 5)

---

## 🛠️ TECNOLOGÍAS

- **Backend:** PHP 8.x (Vanilla PHP)
- **Base de datos:** MySQL 8.0+
- **Autenticación:** JWT (JSON Web Tokens)
- **Servidor:** Apache (XAMPP)
- **Arquitectura:** MVC + API RESTful

---

## 📁 ESTRUCTURA DEL PROYECTO

```
nenis_y_bros/
├── backend/
│   ├── config/          # Configuraciones
│   ├── controllers/     # Controladores
│   ├── models/          # Modelos de datos
│   ├── middleware/      # Middlewares
│   ├── routes/          # Rutas de la API
│   ├── services/        # Servicios
│   ├── validators/      # Validadores
│   ├── utils/           # Utilidades
│   ├── logs/            # Archivos de log
│   └── index.php        # Punto de entrada
├── frontend/
│   ├── assets/          # CSS, JS, imágenes
│   ├── components/      # Componentes reutilizables
│   └── pages/           # Páginas HTML
├── uploads/
│   ├── profiles/        # Fotos de perfil
│   └── temp/            # Archivos temporales
├── db/
│   └── nyd_db.sql       # Script de base de datos
├── docs/                # Documentación
├── .env                 # Variables de entorno
└── README.md
```

---

## 🚀 INSTALACIÓN RÁPIDA

### 1. Requisitos
- XAMPP instalado
- PHP 7.4 o superior
- MySQL 8.0 o superior

### 2. Instalación

```bash
# 1. Coloca el proyecto en htdocs
C:\xampp\htdocs\nenis_y_bros\

# 2. Inicia XAMPP (Apache + MySQL)

# 3. Crea la base de datos
# Abre: http://localhost/phpmyadmin
# Crea DB: formacion_empresarial
# Importa: db/nyd_db.sql

# 4. Configura el archivo .env (ya está creado)
# Verifica las credenciales de BD

# 5. Accede a la API
http://localhost/nenis_y_bros/backend/health
```

### 3. Crear Usuario Administrador

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

## 📚 DOCUMENTACIÓN

- [Guía de Instalación](docs/INSTALLATION.md)
- [Documentación de la API](docs/API_DOCUMENTATION.md)
- [Plan de Desarrollo](PLAN_DESARROLLO.md)

---

## 🔐 ENDPOINTS PRINCIPALES

### Autenticación
```
POST /auth/register      - Registrar usuario
POST /auth/login         - Iniciar sesión
GET  /auth/me            - Obtener usuario autenticado
POST /auth/logout        - Cerrar sesión
```

### Usuarios
```
GET  /users/profile      - Obtener perfil
PUT  /users/profile      - Actualizar perfil
POST /users/profile/photo - Subir foto de perfil
GET  /users/{id}         - Ver perfil público
```

### Administración (requiere rol admin)
```
GET    /admin/dashboard       - Estadísticas
GET    /admin/users           - Listar usuarios
GET    /admin/users/{id}      - Ver detalles
PUT    /admin/users/{id}/status - Cambiar estado
DELETE /admin/users/{id}      - Eliminar usuario
```

---

## 🧪 TESTING

### Probar con cURL

```bash
# Health check
curl http://localhost/nenis_y_bros/backend/health

# Login
curl -X POST http://localhost/nenis_y_bros/backend/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@nenisybros.com","password":"Admin123!"}'

# Obtener perfil (usa el token del login)
curl http://localhost/nenis_y_bros/backend/users/profile \
  -H "Authorization: Bearer TU_TOKEN_AQUI"
```

### Probar con Postman/Thunder Client

1. Importa la colección de la documentación
2. Configura la variable `baseUrl`: `http://localhost/nenis_y_bros/backend`
3. Ejecuta los requests

---

## 📊 PROGRESO DEL PROYECTO

- [x] **Fase 1:** Fundamentos y Autenticación (COMPLETADA ✅)
  - [x] Sistema de autenticación JWT
  - [x] Gestión de usuarios
  - [x] Panel administrativo
  - [x] API RESTful base
  - [x] Logging y validación

- [ ] **Fase 2:** Sistema de Cursos (En desarrollo)
- [ ] **Fase 3:** Diagnósticos Empresariales
- [ ] **Fase 4:** Gamificación
- [ ] **Fase 5:** Vitrina de Productos
- [ ] **Fase 6:** Biblioteca de Recursos
- [ ] **Fase 7:** Testing y Lanzamiento

---

## 🔒 SEGURIDAD

- ✅ Contraseñas hasheadas con bcrypt
- ✅ Autenticación JWT
- ✅ Validación de datos de entrada
- ✅ Protección XSS
- ✅ Headers de seguridad
- ✅ Logging de actividades
- ⏳ Rate limiting (pendiente)
- ⏳ CSRF protection (pendiente)

---

## 🐛 SOLUCIÓN DE PROBLEMAS

### Error de conexión a BD
```bash
# Verifica que MySQL esté corriendo
# Verifica credenciales en .env
# Verifica que la BD exista
```

### Error 404 en rutas
```bash
# Verifica que mod_rewrite esté habilitado en Apache
# Verifica que existe .htaccess en /backend/
```

### Revisar logs
```bash
# Logs de aplicación
backend/logs/app_YYYY-MM-DD.log

# Logs de base de datos
backend/logs/database_YYYY-MM-DD.log

# Logs de actividad
backend/logs/activity_YYYY-MM-DD.log
```

---

## 📝 CONTRIBUIR

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

---

## 📄 LICENCIA

Este proyecto es propiedad de **Nenis y Bros**.

---

## 👥 EQUIPO

- **Desarrollo:** Equipo Nenis y Bros
- **Versión:** 1.0 - Fase 1
- **Fecha:** Noviembre 2025

---

## 📞 CONTACTO

Para soporte o consultas:
- Email: soporte@nenisybros.com
- Documentación: [docs/](docs/)

---

## ✨ CARACTERÍSTICAS DESTACADAS

🔐 **Seguridad robusta** - JWT, bcrypt, validaciones  
📱 **API RESTful** - Diseño moderno y escalable  
📊 **Logging completo** - Trazabilidad de todas las acciones  
🎯 **Arquitectura limpia** - MVC, código organizado  
📚 **Bien documentado** - API y código documentados  
🚀 **Listo para producción** - Siguiendo mejores prácticas  

---

**¡Gracias por usar Nenis y Bros! 🚀**
