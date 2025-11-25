# Módulo de Registro de Usuarios - Documentación

## Ubicación
**URL**: `http://localhost/nenis_y_bros/frontend/pages/auth/registro.html`

## Campos del Formulario

### Campos Obligatorios (*)
1. **Tipo de Usuario** - Menú desplegable con opciones:
   - Emprendedor
   - Empresario

2. **Nombre** - Mínimo 2 caracteres, máximo 100

3. **Apellido** - Mínimo 2 caracteres, máximo 100

4. **Correo Electrónico** - Formato válido de email

5. **Contraseña** - Requisitos:
   - Mínimo 8 caracteres
   - Al menos una letra mayúscula
   - Al menos una letra minúscula
   - Al menos un número
   - Indicador visual de fortaleza (débil/medio/fuerte)

6. **Confirmar Contraseña** - Debe coincidir con la contraseña

### Campos Opcionales
7. **Teléfono** - Formato libre

8. **Ciudad** - Máximo 100 caracteres

9. **País** - Máximo 100 caracteres

## Características Implementadas

### Validación en Tiempo Real
- ✅ Validación de fortaleza de contraseña con indicador visual
- ✅ Verificación de coincidencia de contraseñas
- ✅ Validación de formato de email
- ✅ Mensajes de error específicos por campo
- ✅ Limpieza automática de errores al corregir

### Experiencia de Usuario
- ✅ Diseño responsive (funciona en móviles)
- ✅ Animaciones suaves
- ✅ Botón de carga con spinner durante el registro
- ✅ Mensajes de éxito/error claros
- ✅ Redirección automática después del registro exitoso

### Integración con Backend
- ✅ Conectado al endpoint: `POST /api/v1/auth/register`
- ✅ Manejo de errores de validación del servidor
- ✅ Almacenamiento automático de token JWT
- ✅ Redirección según tipo de usuario:
  - Administrador → `/frontend/pages/admin/dashboard.html`
  - Otros usuarios → `/frontend/pages/dashboard.html`

## Estructura de Datos Enviados

```json
{
  "tipo_usuario": "emprendedor|empresario",
  "nombre": "string",
  "apellido": "string",
  "email": "string",
  "telefono": "string (opcional)",
  "password": "string",
  "password_confirmation": "string",
  "ciudad": "string (opcional)",
  "pais": "string (opcional)"
}
```

## Base de Datos

### Tabla: `usuarios`
Los campos se mapean directamente a la tabla `usuarios` en la base de datos:

```sql
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    telefono VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    tipo_usuario ENUM('emprendedor', 'empresario', 'mentor', 'administrador'),
    ciudad VARCHAR(100),
    pais VARCHAR(100),
    estado ENUM('activo', 'inactivo', 'suspendido') DEFAULT 'activo',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    ...
);
```

## Flujo de Registro

1. Usuario completa el formulario
2. Validación del lado del cliente
3. Envío de datos al backend
4. Backend valida datos (validador del servidor)
5. Backend verifica que el email no esté duplicado
6. Backend hashea la contraseña
7. Backend crea el usuario en la BD
8. Backend genera token JWT
9. Frontend almacena token y datos del usuario
10. Redirección automática al dashboard correspondiente

## Pruebas Recomendadas

### Caso 1: Registro Exitoso
- Tipo: Emprendedor
- Nombre: Juan
- Apellido: Pérez
- Email: juan.perez@example.com
- Teléfono: +52 123 456 7890
- Contraseña: Password123
- Ciudad: Ciudad de México
- País: México

### Caso 2: Validación de Email Duplicado
- Intentar registrar el mismo email dos veces

### Caso 3: Validación de Contraseña Débil
- Intentar con contraseñas como: "12345678" (sin mayúsculas)

### Caso 4: Contraseñas No Coinciden
- Ingresar diferentes valores en contraseña y confirmación

## Archivos Relacionados

- **Frontend**: `/frontend/pages/auth/registro.html`
- **Backend Controller**: `/backend/controllers/AuthController.php`
- **Modelo**: `/backend/models/Usuario.php`
- **Ruta**: `/backend/routes/api.php` (línea 40)
- **Validador**: `/backend/validators/Validator.php`

## Próximos Pasos (Opcional)

1. Agregar verificación de email por correo
2. Implementar reCAPTCHA para evitar bots
3. Agregar más tipos de usuario si es necesario
4. Implementar recuperación de contraseña
5. Agregar foto de perfil durante el registro
