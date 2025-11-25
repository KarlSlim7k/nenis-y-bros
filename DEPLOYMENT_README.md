# 🚀 Guía de Despliegue - Nenis y Bros

## 📋 Despliegue en Vercel + Railway

Esta guía te ayudará a desplegar el sistema completo en producción.

---

## 🛠️ Prerrequisitos

1. **Cuenta en GitHub** (para conectar con Vercel)
2. **Cuenta en Vercel** (https://vercel.com)
3. **Cuenta en Railway** (https://railway.app)

---

## 📦 Paso 1: Preparar el repositorio

### 1.1 Instalar Git (si no lo tienes)

Descarga Git desde: https://git-scm.com/downloads

### 1.2 Inicializar repositorio y subir a GitHub

```bash
# Inicializar Git
git init

# Agregar archivos
git add .

# Commit inicial
git commit -m "Initial commit - Sistema de formación empresarial"

# Crear repositorio en GitHub y conectar
git remote add origin https://github.com/TU_USUARIO/nenis-y-bros.git

# Subir código
git push -u origin main
```

---

## 🌐 Paso 2: Desplegar Backend en Railway

### 2.1 Crear proyecto en Railway

1. Ve a [railway.app](https://railway.app) y haz login
2. Haz clic en "New Project"
3. Selecciona "Deploy from GitHub"
4. Conecta tu repositorio `nenis-y-bros`
5. Railway detectará automáticamente el `Dockerfile`

### 2.2 Configurar variables de entorno

En el dashboard de Railway, ve a "Variables" y agrega:

```
APP_NAME=Nenis y Bros
APP_ENV=production
APP_DEBUG=false
JWT_SECRET=tu_clave_jwt_muy_segura_de_al_menos_32_caracteres
ENCRYPTION_KEY=tu_clave_encriptacion_segura_32_caracteres
TIMEZONE=America/Mexico_City
API_PREFIX=api
API_VERSION=v1
CACHE_ENABLED=false
```

### 2.3 Configurar base de datos

1. En Railway, agrega un servicio de "MySQL" o "PostgreSQL"
2. Railway creará automáticamente las variables de entorno para la DB
3. Importa el esquema de la base de datos:

```bash
# Desde Railway CLI o conectándote a la DB
mysql -h [DB_HOST] -u [DB_USER] -p[DB_PASSWORD] [DB_NAME] < db/nyd_db.sql
mysql -h [DB_HOST] -u [DB_USER] -p[DB_PASSWORD] [DB_NAME] < db/test_data.sql
```

### 2.4 Obtener la URL del backend

Después del despliegue, Railway te dará una URL como:
`https://nenis-y-bros-backend-production.up.railway.app`

---

## 🎨 Paso 3: Desplegar Frontend en Vercel

### 3.1 Conectar repositorio

1. Ve a [vercel.com](https://vercel.com) y haz login con GitHub
2. Haz clic en "New Project"
3. Importa tu repositorio `nenis-y-bros`
4. Vercel detectará automáticamente el `vercel.json`

### 3.2 Configurar variables de entorno

En Vercel, ve a "Settings" → "Environment Variables" y agrega:

```
API_BASE_URL=https://TU_URL_DE_RAILWAY.up.railway.app/api/v1
```

### 3.3 Desplegar

Haz clic en "Deploy" y Vercel desplegará automáticamente tu frontend.

---

## 🔧 Paso 4: Actualizar configuración del frontend

### 4.1 Actualizar URL del API

Edita `frontend/assets/js/config.js` y reemplaza la URL del backend:

```javascript
// Cambia esta línea con tu URL real de Railway
return 'https://nenis-y-bros-backend-production.up.railway.app/api/v1';
```

### 4.2 Probar la conexión

1. Ve a tu sitio desplegado en Vercel
2. Intenta hacer login
3. Verifica que las llamadas al API funcionen

---

## 📊 Paso 5: Verificar funcionamiento

### URLs importantes:
- **Frontend**: `https://nenis-y-bros.vercel.app`
- **Backend API**: `https://nenis-y-bros-backend-production.up.railway.app/api/v1`
- **Base de datos**: Gestionada por Railway

### Endpoints a probar:
- `GET /health` - Verificar que el backend responde
- `POST /auth/login` - Probar autenticación
- `GET /users` - Verificar conexión a DB

---

## 🐛 Solución de problemas

### Error de CORS
Si hay errores de CORS, verifica que el backend tenga los headers correctos en `config.php`.

### Error de base de datos
Revisa las variables de entorno en Railway y asegúrate de que la DB esté corriendo.

### Frontend no carga
Verifica que las rutas en `vercel.json` estén correctas.

---

## 💰 Costos aproximados

- **Vercel**: Gratuito (hobby plan)
- **Railway**: ~$5/mes (por el backend PHP + DB)
- **Total**: ~$5/mes

---

## 📞 Soporte

Si tienes problemas, revisa:
1. Los logs en Railway (Deployments → Logs)
2. Los logs en Vercel (Functions → Logs)
3. Las variables de entorno configuradas