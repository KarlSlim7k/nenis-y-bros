# Migración de Recursos - Guía de Aplicación

## 📋 Resumen

Esta migración completa el esquema de `recursos_aprendizaje` agregando todos los campos necesarios para funcionalidad completa del módulo de recursos.

## ✅ Qué Hace Esta Migración

### 1. Agrega 13 Campos Nuevos
- `slug` - URLs amigables
- `id_autor` - Tracking de quién crea el recurso
- `contenido_texto`, `contenido_html` - Contenido completo
- `duracion_minutos` - Para videos/podcasts
- `imagen_preview`, `video_preview` - Previsualizaciones
- `idioma`, `formato`, `licencia` - Metadata
- `destacado` - Marcar recursos importantes
- `fecha_publicacion`, `fecha_actualizacion` - Timestamps

### 2. Crea 3 Tablas Nuevas
- `descargas_recursos` - Registro de cada descarga
- `calificaciones_recursos` - Sistema de ratings
- `vistas_recursos` - Analytics de visualizaciones

### 3. Implementa 4 Triggers
- Incremento automático de contador de descargas
- Recálculo automático de calificación promedio

## 🚀 Cómo Aplicar la Migración

### Opción 1: Script de PowerShell (Recomendado)

```powershell
# Desde la raíz del proyecto
cd db\migrations
.\apply_recursos_migration.ps1
```

**Ventajas:**
- Verificaciones automáticas
- Confirmación antes de aplicar
- Verificación post-migración
- Feedback visual

### Opción 2: Script de Python

```bash
# Desde la raíz del proyecto
cd db/migrations
python apply_recursos_migration.py
```

**Requisitos:**
- Python 3.6+
- Railway CLI instalado y configurado

### Opción 3: Manual con Railway CLI

```bash
# 1. Conectar a Railway
railway link

# 2. Aplicar migración
railway run mysql < db/migrations/fix_recursos_schema.sql
```

### Opción 4: Desde Railway Dashboard

1. Ir a Railway Dashboard
2. Seleccionar el servicio MySQL
3. Abrir "Query"
4. Copiar y pegar el contenido de `fix_recursos_schema.sql`
5. Ejecutar

## ✅ Verificación Post-Migración

### 1. Verificar Estructura de Tabla

```sql
DESCRIBE recursos_aprendizaje;
```

Deberías ver los nuevos campos: `slug`, `destacado`, `idioma`, etc.

### 2. Verificar Tablas Creadas

```sql
SHOW TABLES LIKE '%recursos%';
```

Deberías ver:
- recursos_aprendizaje
- descargas_recursos
- calificaciones_recursos
- vistas_recursos
- recursos_etiquetas
- categorias_recursos

### 3. Verificar Triggers

```sql
SHOW TRIGGERS WHERE `Table` LIKE '%recursos%';
```

Deberías ver 4 triggers relacionados con descargas y calificaciones.

### 4. Probar Funcionalidad

```sql
-- Insertar recurso de prueba
INSERT INTO recursos_aprendizaje (titulo, descripcion, tipo_recurso, activo)
VALUES ('Recurso de Prueba', 'Descripción', 'articulo', 1);

-- Verificar que el slug se generó
SELECT titulo, slug FROM recursos_aprendizaje ORDER BY id_recurso DESC LIMIT 1;
```

## 🔧 Solución de Problemas

### Error: "Railway CLI not found"
```bash
npm install -g @railway/cli
railway login
railway link
```

### Error: "Column 'slug' already exists"
Esta migración es idempotente. Si el campo ya existe, el ALTER TABLE fallará pero continuará con el resto. Es seguro.

### Error: "Trigger already exists"
Los triggers usan `IF NOT EXISTS`. Si ya existen, serán omitidos.

### Error: "Access denied"
Verifica que tienes permisos de escritura en la base de datos:
```bash
railway variables
# Busca DATABASE_URL y verifica las credenciales
```

## 📊 Impacto en la Aplicación

### Backend (`backend/models/Recurso.php`)
✅ **Ya actualizado** - El modelo soporta todos los nuevos campos

### Frontend (`frontend/pages/admin/recursos.html`)
✅ **Ya actualizado** - Incluye campos de duración, idioma y destacado

### Endpoints Afectados
- ✅ GET `/api/v1/recursos` - Retorna campos adicionales
- ✅ POST `/api/v1/recursos` - Acepta nuevos campos
- ✅ PUT `/api/v1/recursos/{id}` - Actualiza todos los campos
- ✅ POST `/api/v1/recursos/{id}/descargar` - Funcional con nueva tabla
- ✅ POST `/api/v1/recursos/{id}/calificar` - Funcional con nueva tabla

## 🎯 Testing Después de Migración

### 1. Probar CRUD Básico
```bash
# Crear recurso destacado
curl -X POST https://tu-api.railway.app/api/v1/recursos \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "titulo": "Recurso Destacado",
    "descripcion": "Test",
    "tipo_recurso": "articulo",
    "destacado": 1,
    "idioma": "es"
  }'

# Listar recursos destacados
curl https://tu-api.railway.app/api/v1/recursos?destacado=1
```

### 2. Probar Descargas
```bash
# Registrar descarga
curl -X POST https://tu-api.railway.app/api/v1/recursos/1/descargar \
  -H "Authorization: Bearer TOKEN"

# Verificar contador incrementado
curl https://tu-api.railway.app/api/v1/recursos/1
```

### 3. Probar Calificaciones
```bash
# Calificar recurso
curl -X POST https://tu-api.railway.app/api/v1/recursos/1/calificar \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "calificacion": 5,
    "comentario": "Excelente recurso"
  }'
```

## 📝 Rollback (Si es Necesario)

Si necesitas revertir la migración:

```sql
-- Eliminar campos agregados
ALTER TABLE recursos_aprendizaje
DROP COLUMN slug,
DROP COLUMN id_autor,
DROP COLUMN contenido_texto,
DROP COLUMN contenido_html,
DROP COLUMN duracion_minutos,
DROP COLUMN imagen_preview,
DROP COLUMN video_preview,
DROP COLUMN idioma,
DROP COLUMN formato,
DROP COLUMN licencia,
DROP COLUMN destacado,
DROP COLUMN fecha_publicacion,
DROP COLUMN fecha_actualizacion;

-- Eliminar tablas
DROP TABLE IF EXISTS vistas_recursos;
DROP TABLE IF EXISTS calificaciones_recursos;
DROP TABLE IF EXISTS descargas_recursos;

-- Eliminar triggers
DROP TRIGGER IF EXISTS trg_recurso_descarga_insert;
DROP TRIGGER IF EXISTS trg_recurso_calificacion_insert;
DROP TRIGGER IF EXISTS trg_recurso_calificacion_update;
DROP TRIGGER IF EXISTS trg_recurso_calificacion_delete;
```

## 🆘 Soporte

Si encuentras problemas:

1. **Revisa los logs de Railway**
   ```bash
   railway logs
   ```

2. **Verifica el estado de MySQL**
   ```bash
   railway run mysql -e "SHOW PROCESSLIST;"
   ```

3. **Consulta la documentación completa**
   - `docs/FASE_6C_RECURSOS_ADAPTACION.md`

4. **Reporta el issue** con:
   - Comando ejecutado
   - Error completo
   - Output de `railway status`

---

## ✅ Checklist de Migración

- [ ] Railway CLI instalado y configurado
- [ ] Conectado al proyecto correcto (`railway status`)
- [ ] Backup de base de datos realizado (opcional pero recomendado)
- [ ] Migración ejecutada sin errores
- [ ] Verificación de estructura completada
- [ ] Triggers confirmados
- [ ] Endpoints probados
- [ ] Frontend probado en admin panel

**Última actualización:** 13 de diciembre de 2025
