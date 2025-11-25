# 🔒 API DE CONFIGURACIÓN DE PRIVACIDAD
## Sistema de Formación Empresarial - Nenis y Bros

---

## 📋 DESCRIPCIÓN

Este documento describe los endpoints de la API para gestionar la configuración de privacidad de los usuarios. Permite controlar qué información del perfil es visible para otros usuarios.

---

## 🔐 CONFIGURACIÓN DE PRIVACIDAD

### Campos de Configuración

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `perfil_publico` | boolean | Si es `false`, solo se muestra nombre e inicial del apellido |
| `mostrar_email` | boolean | Si es `true`, otros usuarios pueden ver el email |
| `mostrar_telefono` | boolean | Si es `true`, otros usuarios pueden ver el teléfono |
| `mostrar_biografia` | boolean | Si es `true`, se muestra la biografía en el perfil público |
| `mostrar_ubicacion` | boolean | Si es `true`, se muestra ciudad y país |
| `permitir_mensajes` | boolean | Si es `true`, otros usuarios pueden enviar mensajes (futuro) |

### Configuración por Defecto

```json
{
  "perfil_publico": true,
  "mostrar_email": false,
  "mostrar_telefono": false,
  "mostrar_biografia": true,
  "mostrar_ubicacion": true,
  "permitir_mensajes": true
}
```

---

## 🔗 ENDPOINTS

### 1. Obtener Configuración de Privacidad

**GET** `/api/users/privacy-settings`

Obtiene la configuración de privacidad del usuario autenticado.

#### Headers
```
Authorization: Bearer {token}
```

#### Respuesta Exitosa (200)
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
  },
  "timestamp": "2025-11-15 10:30:00"
}
```

#### Errores Posibles
- **401 Unauthorized**: Token no proporcionado o inválido
- **500 Internal Server Error**: Error al obtener configuración

---

### 2. Actualizar Configuración de Privacidad

**PUT** `/api/users/privacy-settings`

Actualiza uno o más campos de la configuración de privacidad del usuario autenticado.

#### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

#### Body (Ejemplo)
```json
{
  "perfil_publico": false,
  "mostrar_email": false,
  "mostrar_telefono": false
}
```

**Nota:** Puedes enviar solo los campos que deseas actualizar. Los demás se mantienen con su valor actual.

#### Respuesta Exitosa (200)
```json
{
  "success": true,
  "message": "Configuración de privacidad actualizada exitosamente",
  "data": {
    "privacy_settings": {
      "perfil_publico": false,
      "mostrar_email": false,
      "mostrar_telefono": false,
      "mostrar_biografia": true,
      "mostrar_ubicacion": true,
      "permitir_mensajes": true
    }
  },
  "timestamp": "2025-11-15 10:35:00"
}
```

#### Errores Posibles
- **400 Bad Request**: No se proporcionó ningún campo válido
- **401 Unauthorized**: Token no proporcionado o inválido
- **500 Internal Server Error**: Error al actualizar configuración

---

## 🔍 CÓMO FUNCIONA LA PRIVACIDAD

### Cuando un usuario ve un perfil:

#### 1. Usuario ve su propio perfil
- ✅ Se muestran **todos** los datos
- ✅ Configuración de privacidad **no aplica**

#### 2. Usuario administrador ve cualquier perfil
- ✅ Se muestran **todos** los datos
- ✅ Configuración de privacidad **no aplica**

#### 3. Usuario autenticado ve perfil de otro usuario

##### Si `perfil_publico: false`
```json
{
  "id_usuario": 5,
  "nombre": "Juan",
  "apellido": "P.",
  "tipo_usuario": "empresario",
  "perfil_privado": true
}
```

##### Si `perfil_publico: true`
Se aplican los demás filtros:
- `mostrar_email: false` → email no se incluye en respuesta
- `mostrar_telefono: false` → teléfono no se incluye
- `mostrar_biografia: false` → biografía no se incluye
- `mostrar_ubicacion: false` → ciudad y país no se incluyen

#### 4. Usuario NO autenticado (público)

##### Si `perfil_publico: false`
```json
{
  "id_usuario": 5,
  "nombre": "Juan",
  "apellido": "P.",
  "tipo_usuario": "empresario",
  "perfil_privado": true
}
```

##### Si `perfil_publico: true`
Se muestra información según configuración, pero **nunca** se muestra:
- Email
- Teléfono (salvo que `mostrar_telefono: true`)
- Datos sensibles

---

## 📝 EJEMPLOS DE USO

### Ejemplo 1: Hacer el perfil completamente privado

```bash
curl -X PUT http://localhost/api/users/privacy-settings \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "perfil_publico": false
  }'
```

**Resultado:** Solo se mostrará nombre e inicial del apellido a otros usuarios.

---

### Ejemplo 2: Perfil público pero sin contacto

```bash
curl -X PUT http://localhost/api/users/privacy-settings \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "perfil_publico": true,
    "mostrar_email": false,
    "mostrar_telefono": false,
    "permitir_mensajes": false
  }'
```

**Resultado:** Se muestra el perfil completo pero sin medios de contacto.

---

### Ejemplo 3: Perfil completamente público

```bash
curl -X PUT http://localhost/api/users/privacy-settings \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "perfil_publico": true,
    "mostrar_email": true,
    "mostrar_telefono": true,
    "mostrar_biografia": true,
    "mostrar_ubicacion": true,
    "permitir_mensajes": true
  }'
```

**Resultado:** Toda la información es visible para todos.

---

### Ejemplo 4: Obtener configuración actual

```bash
curl -X GET http://localhost/api/users/privacy-settings \
  -H "Authorization: Bearer {token}"
```

---

## 🧪 TESTING

### Test 1: Verificar configuración por defecto
```bash
# 1. Registrar nuevo usuario
# 2. Login
# 3. Obtener configuración de privacidad
# Resultado esperado: Configuración por defecto
```

### Test 2: Actualizar configuración
```bash
# 1. Actualizar perfil_publico a false
# 2. Obtener configuración
# Resultado esperado: perfil_publico = false, demás igual
```

### Test 3: Ver perfil propio vs ajeno
```bash
# 1. Usuario A actualiza privacidad (perfil_publico = false)
# 2. Usuario A ve su propio perfil → Ve todo
# 3. Usuario B ve perfil de Usuario A → Ve solo nombre e inicial
```

### Test 4: Validación de campos
```bash
# 1. Intentar actualizar con campo inválido
# 2. Intentar actualizar sin campos
# Resultado esperado: Error 400
```

---

## 🔐 SEGURIDAD

### Datos que NUNCA se muestran (independiente de configuración):
- ✅ `password_hash` - Siempre oculto
- ✅ `configuracion_privacidad` - Solo visible para el propio usuario
- ✅ Tokens de recuperación - Nunca en respuestas públicas

### Validaciones:
- ✅ Usuario solo puede modificar su propia configuración
- ✅ Campos booleanos se convierten automáticamente
- ✅ Campos no reconocidos se ignoran
- ✅ Administradores ven todo (para moderación)

---

## 📊 IMPACTO EN OTROS ENDPOINTS

### GET `/api/users/{id}`
- ✅ **ACTUALIZADO**: Ahora aplica filtros de privacidad
- Si usuario no autenticado → filtros más estrictos
- Si es el mismo usuario → sin filtros
- Si es administrador → sin filtros

### GET `/api/users/profile`
- ✅ Sin cambios (siempre muestra todo al propio usuario)

### GET `/api/admin/users/{id}`
- ✅ Sin cambios (administradores ven todo)

---

## 🎯 CASOS DE USO

### 1. Usuario quiere privacidad total
→ `perfil_publico: false`

### 2. Mentor quiere ser visible pero no contactable
→ `perfil_publico: true`, `mostrar_email: false`, `mostrar_telefono: false`

### 3. Emprendedor quiere networking
→ Todo en `true` (configuración por defecto)

### 4. Usuario temporal/prueba
→ `perfil_publico: false` hasta estar listo

---

## 📖 NOTAS ADICIONALES

1. **Migración automática**: Usuarios existentes obtienen configuración por defecto
2. **Reversible**: Cambios pueden deshacerse en cualquier momento
3. **Granular**: Cada campo se controla independientemente
4. **Futuro**: `permitir_mensajes` se usará cuando se implemente sistema de mensajería

---

## 🐛 DEBUGGING

### Ver configuración en base de datos:
```sql
SELECT 
    id_usuario, 
    email, 
    configuracion_privacidad 
FROM usuarios 
WHERE id_usuario = ?;
```

### Actualizar manualmente:
```sql
UPDATE usuarios 
SET configuracion_privacidad = '{"perfil_publico": true, "mostrar_email": false}'
WHERE id_usuario = ?;
```

---

**Documentación creada:** 15 de Noviembre 2025  
**Versión:** 1.0  
**Fase:** 1 - Fundamentos y Autenticación  
**Estado:** ✅ Implementado y testeado
