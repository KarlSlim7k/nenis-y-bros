# Sistema de Versionado de Recursos - Documentación

## Resumen

Sistema completo de control de versiones para recursos de la biblioteca, con historial de cambios, comparación entre versiones, restauración y auditoría completa de modificaciones.

## Arquitectura

### Tablas de Base de Datos

#### 1. `recursos_versiones`

Almacena snapshots completos de cada versión del recurso.

**Campos principales:**
- `id_version` (PK): ID único de la versión
- `id_recurso` (FK): Recurso al que pertenece
- `numero_version`: Número secuencial (1, 2, 3...)
- `id_usuario_cambio` (FK): Quién hizo el cambio
- `fecha_cambio`: Cuándo se realizó
- `tipo_cambio`: ENUM ('creacion', 'actualizacion', 'restauracion', 'publicacion', 'despublicacion')
- `descripcion_cambio`: Descripción textual del cambio
- `campos_modificados`: JSON array de campos que cambiaron
- `datos_anteriores`: JSON con valores anteriores
- **Snapshot completo**: Todos los campos del recurso (título, descripción, archivos, etc.)

**Características:**
- Constraint único: `(id_recurso, numero_version)`
- Índices optimizados para consultas por recurso, fecha, usuario
- Cascada en delete de recurso
- Restrict en delete de usuario (preservar historial)

#### 2. `recursos_etiquetas_versiones`

Etiquetas asociadas a cada versión (para comparación y restauración).

**Campos:**
- `id_version_etiqueta` (PK)
- `id_version` (FK)
- `id_etiqueta` (FK)

### Triggers Automáticos

#### `trg_recursos_version_insert`

Se dispara AFTER INSERT en `recursos`:
- Crea automáticamente la versión 1
- Tipo de cambio: 'creacion'
- Usuario: el autor del recurso

**NO requiere intervención manual** - todos los nuevos recursos tienen versión 1 automáticamente.

### Stored Procedures

#### `sp_crear_version_recurso`

Crea una nueva versión del recurso con snapshot completo.

**Parámetros:**
```sql
CALL sp_crear_version_recurso(
    p_id_recurso INT,
    p_id_usuario_cambio INT,
    p_tipo_cambio ENUM(...),
    p_descripcion_cambio TEXT,
    p_campos_modificados JSON,
    p_datos_anteriores JSON
)
```

**Funcionalidad:**
1. Calcula siguiente número de versión
2. Lee estado actual del recurso
3. Crea snapshot completo en `recursos_versiones`
4. Copia etiquetas actuales a `recursos_etiquetas_versiones`

#### `sp_restaurar_version`

Restaura el recurso a una versión anterior.

**Parámetros:**
```sql
CALL sp_restaurar_version(
    p_id_recurso INT,
    p_numero_version INT,
    p_id_usuario_restauracion INT
)
```

**Flujo:**
1. Valida que la versión exista
2. Crea backup de estado actual (tipo 'restauracion')
3. Actualiza recurso con datos de la versión antigua
4. Restaura etiquetas de esa versión
5. Actualiza `fecha_actualizacion`

### Vistas

#### `vista_versiones_recursos`

Historial con información de usuario y métricas.

**Campos útiles:**
- Datos de versión (número, fecha, tipo)
- Usuario que hizo el cambio (nombre, email)
- Estados (versión vs actual)
- Cantidad de campos modificados
- Tiempo desde versión anterior

#### `vista_versiones_actuales`

Solo la última versión de cada recurso (útil para resúmenes).

### Funciones

#### `fn_comparar_versiones`

Compara dos versiones y retorna JSON con diferencias.

```sql
SELECT fn_comparar_versiones(id_recurso, version1, version2);
```

**Retorna:**
```json
{
  "version_1": {...},
  "version_2": {...},
  "diferencias": {
    "titulo_cambio": true,
    "descripcion_cambio": false,
    ...
  }
}
```

## Modelo PHP: RecursoVersion

**Ubicación**: `backend/models/RecursoVersion.php`

### Métodos Principales

#### 1. Consulta de Historial

```php
// Obtener historial paginado
$versionModel->getHistorial($idRecurso, $page, $limit);

// Obtener versión específica
$versionModel->getVersion($idRecurso, $numeroVersion);

// Obtener última versión
$versionModel->getUltimaVersion($idRecurso);
```

#### 2. Creación de Versiones

```php
$versionModel->crearVersion(
    $idRecurso,
    $idUsuarioCambio,
    'actualizacion',
    'Descripción del cambio',
    ['titulo', 'descripcion'], // Campos modificados
    ['titulo' => 'Valor anterior'] // Datos anteriores
);
```

**Llamado automáticamente** desde `Recurso::update()` cuando se detectan cambios.

#### 3. Restauración

```php
$result = $versionModel->restaurarVersion(
    $idRecurso,
    $numeroVersion,
    $idUsuarioRestauracion
);
```

**Efectos:**
- Crea backup automático (versión N+1)
- Restaura datos de versión especificada
- Invalida caché
- Retorna resultado del SP

#### 4. Comparación

```php
$comparacion = $versionModel->compararVersiones($idRecurso, 1, 3);
```

**Retorna:**
```php
[
    'version_1' => [...],
    'version_2' => [...],
    'diferencias' => [
        'titulo' => [
            'version_1' => 'Título antiguo',
            'version_2' => 'Título nuevo',
            'cambio' => true
        ],
        'etiquetas' => [
            'agregadas' => ['nueva-etiqueta'],
            'eliminadas' => ['etiqueta-vieja'],
            'cambio' => true
        ],
        ...
    ],
    'total_cambios' => 5
]
```

#### 5. Estadísticas

```php
// Por recurso
$versionModel->getEstadisticasRecurso($idRecurso);
// Retorna: total_versiones, primera_version, ultima_version, 
//          usuarios_editores, actualizaciones, restauraciones

// Globales
$versionModel->getEstadisticasGlobales();
// Retorna: total_versiones, recursos_con_versiones,
//          promedio_versiones, por_tipo_cambio, actividad_30_dias

// Recursos más editados
$versionModel->getRecursosMasEditados($limit);

// Timeline de cambios recientes
$versionModel->getCambiosRecientes($limit);

// Buscar en historial
$versionModel->buscarEnHistorial($busqueda, $filtros);
```

## Integración Automática

### En Recurso::update()

Cuando se actualiza un recurso:

```php
public function update($id, $data, $idUsuario = null, $descripcionCambio = null) {
    // 1. Obtener estado actual
    $recursoActual = $this->getById($id);
    
    // 2. Detectar qué cambió
    $camposModificados = [];
    $datosAnteriores = [];
    foreach ($allowedFields as $field) {
        if (isset($data[$field]) && $recursoActual[$field] != $data[$field]) {
            $camposModificados[] = $field;
            $datosAnteriores[$field] = $recursoActual[$field];
        }
    }
    
    // 3. Ejecutar update en BD
    $stmt->execute($values);
    
    // 4. Si hubo cambios Y se pasó usuario, crear versión
    if ($success && !empty($camposModificados) && $idUsuario) {
        $versionModel->crearVersion(
            $id, $idUsuario, $tipoCambio, 
            $descripcion, $camposModificados, $datosAnteriores
        );
    }
    
    // 5. Invalidar caché
    Cache::getInstance()->delete("recurso:$id");
    Cache::getInstance()->invalidateResources();
}
```

**Ventajas:**
- ✅ Versionado transparente - los controllers no necesitan cambios
- ✅ Detección automática de qué cambió
- ✅ Detección inteligente de tipo (publicacion, despublicacion, actualizacion)
- ✅ Opcional - si no se pasa `$idUsuario`, no se crea versión

## API REST Endpoints

### 1. Historial de Versiones

```http
GET /api/v1/recursos/{id}/versiones?page=1&limit=20
```

**Autenticación**: Bearer token requerido

**Respuesta:**
```json
{
  "status": "success",
  "data": {
    "versiones": [
      {
        "id_version": 45,
        "numero_version": 3,
        "titulo_version": "Guía actualizada",
        "tipo_cambio": "actualizacion",
        "descripcion_cambio": "Actualización de: titulo, descripcion",
        "fecha_cambio": "2025-11-19 14:30:00",
        "nombre_usuario": "Admin User",
        "cantidad_campos_modificados": 2
      },
      ...
    ],
    "total": 3,
    "pagina_actual": 1,
    "total_paginas": 1
  }
}
```

### 2. Obtener Versión Específica

```http
GET /api/v1/recursos/{id}/versiones/{numero}
```

**Respuesta:**
Snapshot completo de esa versión con todas sus propiedades y etiquetas.

### 3. Restaurar Versión

```http
POST /api/v1/recursos/{id}/versiones/{numero}/restaurar
```

**Autenticación**: Solo admin o autor del recurso

**Respuesta:**
```json
{
  "status": "success",
  "message": "Versión restaurada exitosamente",
  "data": {
    "status": "success",
    "message": "Versión restaurada exitosamente"
  }
}
```

**Efectos secundarios:**
- Crea nueva versión (backup) antes de restaurar
- Actualiza recurso actual
- Invalida caché
- Log de actividad registrado

### 4. Comparar Versiones

```http
GET /api/v1/recursos/{id}/versiones/comparar?v1=1&v2=3
```

**Parámetros query**:
- `v1`: Número de primera versión
- `v2`: Número de segunda versión

**Respuesta:**
```json
{
  "status": "success",
  "data": {
    "version_1": {
      "numero": 1,
      "fecha": "2025-11-01 10:00:00",
      "usuario": "Admin",
      "tipo_cambio": "creacion"
    },
    "version_2": {
      "numero": 3,
      "fecha": "2025-11-19 14:30:00",
      "usuario": "Editor",
      "tipo_cambio": "actualizacion"
    },
    "diferencias": {
      "titulo": {
        "version_1": "Guía Inicial",
        "version_2": "Guía Actualizada 2025",
        "cambio": true
      },
      "descripcion": {
        "valor": "Misma descripción",
        "cambio": false
      },
      "etiquetas": {
        "version_1": ["emprendimiento", "finanzas"],
        "version_2": ["emprendimiento", "finanzas", "marketing"],
        "agregadas": ["marketing"],
        "eliminadas": [],
        "cambio": true
      }
    },
    "total_cambios": 2
  }
}
```

### 5. Estadísticas de Versionado

```http
GET /api/v1/recursos/versiones/estadisticas
```

**Solo admin**

**Respuesta:**
```json
{
  "total_versiones": 150,
  "recursos_con_versiones": 45,
  "usuarios_editores": 8,
  "promedio_versiones_por_recurso": 3.33,
  "max_versiones_recurso": 12,
  "por_tipo_cambio": [
    {"tipo_cambio": "actualizacion", "total": 95},
    {"tipo_cambio": "creacion", "total": 45},
    {"tipo_cambio": "publicacion", "total": 8},
    {"tipo_cambio": "restauracion", "total": 2}
  ],
  "actividad_30_dias": [
    {"fecha": "2025-11-19", "versiones_creadas": 5},
    {"fecha": "2025-11-18", "versiones_creadas": 3},
    ...
  ]
}
```

### 6. Timeline de Cambios Recientes

```http
GET /api/v1/recursos/versiones/recientes?limit=50
```

**Solo admin**

**Respuesta:**
Lista de las últimas N versiones creadas en todo el sistema (actividad reciente).

## Casos de Uso

### 1. Auditoría Completa

**Pregunta**: "¿Quién cambió este recurso y cuándo?"

```php
$historial = $versionModel->getHistorial($idRecurso);
foreach ($historial['versiones'] as $v) {
    echo "{$v['nombre_usuario']} - {$v['fecha_cambio']}: {$v['descripcion_cambio']}\n";
}
```

### 2. Rollback por Error

**Escenario**: Admin publica recurso con error, necesita volver atrás.

```php
// Ver versiones disponibles
$historial = $versionModel->getHistorial($idRecurso);

// Restaurar a versión anterior (la 2)
$versionModel->restaurarVersion($idRecurso, 2, $idAdmin);
```

### 3. Comparar qué Cambió

**Escenario**: Verificar qué se modificó entre dos fechas.

```php
// Comparar versión 1 (inicial) vs versión 5 (actual)
$diff = $versionModel->compararVersiones($idRecurso, 1, 5);

echo "Cambios detectados: {$diff['total_cambios']}\n";
foreach ($diff['diferencias'] as $campo => $info) {
    if ($info['cambio']) {
        echo "$campo cambió\n";
    }
}
```

### 4. Reporte de Actividad

**Escenario**: Admin quiere ver qué se editó esta semana.

```php
$cambios = $versionModel->buscarEnHistorial('', [
    'fecha_desde' => '2025-11-13',
    'fecha_hasta' => '2025-11-19',
    'tipo_cambio' => 'actualizacion'
]);
```

### 5. Recursos Más Activos

**Escenario**: Identificar recursos que requieren mucha edición (posible problema de calidad).

```php
$masEditados = $versionModel->getRecursosMasEditados(10);
// Retorna top 10 recursos con más versiones
```

## Consideraciones de Performance

### 1. Tamaño de Tabla

- Cada versión duplica ~2KB de datos (snapshot completo)
- 100 recursos con promedio 5 versiones = ~1MB
- Escalable hasta millones de versiones

### 2. Índices Optimizados

```sql
INDEX idx_recurso (id_recurso)
INDEX idx_recurso_version (id_recurso, numero_version)
INDEX idx_fecha (fecha_cambio)
INDEX idx_usuario (id_usuario_cambio)
INDEX idx_tipo_cambio (tipo_cambio)
INDEX idx_versiones_recurso_fecha (id_recurso, fecha_cambio DESC)
```

### 3. Sin Caché (Por Diseño)

El historial de versiones **NO se cachea** porque:
- Consultas poco frecuentes (solo admin/autores)
- Datos de auditoría (deben ser precisos)
- Cambios poco frecuentes (no justifica caché)

### 4. Paginación

Siempre usar paginación en historial:
```php
getHistorial($id, $page = 1, $limit = 20); // Default 20 versiones/página
```

## Limitaciones y Consideraciones

### 1. Archivos NO Versionados

**Importante**: El sistema versiona URLs y metadatos, **NO los archivos físicos**.

- Si un archivo se sobrescribe, versiones anteriores pierden acceso
- **Solución recomendada**: Usar nombres de archivo con timestamp o hash
  - ❌ `recurso.pdf` (sobrescrito)
  - ✅ `recurso_v1_20251119.pdf`
  - ✅ `recurso_abc123.pdf` (hash)

### 2. Restauración No Restaura Archivos

Al restaurar versión anterior:
- ✅ Se restaura `archivo_url`
- ❌ NO se restaura archivo físico (si fue borrado)

### 3. Usuario Obligatorio para Versionado

```php
// SIN versionado (usuario = null)
$recurso->update($id, $data);

// CON versionado (usuario explícito)
$recurso->update($id, $data, $idUsuario);
```

Si no se pasa usuario, el update funciona pero **no se crea versión**.

### 4. Trigger Solo en INSERT

- ✅ INSERT → Crea versión 1 automáticamente
- ❌ UPDATE → NO crea versión (se hace desde PHP)

**Razón**: PHP necesita trackear qué cambió y proporcionar usuario/descripción.

## Mantenimiento

### Limpieza de Versiones Antiguas (Opcional)

Si el historial crece mucho, puedes eliminar versiones muy antiguas:

```sql
-- Eliminar versiones de más de 2 años (mantener versión 1)
DELETE FROM recursos_versiones
WHERE fecha_cambio < DATE_SUB(NOW(), INTERVAL 2 YEAR)
AND numero_version > 1;
```

**Precaución**: Solo si realmente necesitas espacio. El historial es valioso para auditoría.

### Verificar Integridad

```sql
-- Recursos sin versiones (no debería haber)
SELECT r.id_recurso, r.titulo
FROM recursos r
LEFT JOIN recursos_versiones rv ON r.id_recurso = rv.id_recurso
WHERE rv.id_version IS NULL;

-- Versiones huérfanas (no debería haber por CASCADE)
SELECT rv.id_recurso, COUNT(*)
FROM recursos_versiones rv
LEFT JOIN recursos r ON rv.id_recurso = r.id_recurso
WHERE r.id_recurso IS NULL
GROUP BY rv.id_recurso;
```

## Testing

### Test Manual con cURL

```bash
# 1. Crear recurso (versión 1 automática)
curl -X POST http://localhost/nenis_y_bros/backend/api/v1/recursos \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"titulo": "Test", "id_categoria": 1, ...}'

# 2. Actualizar (crea versión 2)
curl -X PUT http://localhost/nenis_y_bros/backend/api/v1/recursos/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"titulo": "Test Actualizado", "descripcion_cambio": "Cambio de prueba"}'

# 3. Ver historial
curl http://localhost/nenis_y_bros/backend/api/v1/recursos/1/versiones \
  -H "Authorization: Bearer $TOKEN"

# 4. Comparar versiones
curl "http://localhost/nenis_y_bros/backend/api/v1/recursos/1/versiones/comparar?v1=1&v2=2" \
  -H "Authorization: Bearer $TOKEN"

# 5. Restaurar a versión 1
curl -X POST http://localhost/nenis_y_bros/backend/api/v1/recursos/1/versiones/1/restaurar \
  -H "Authorization: Bearer $TOKEN"
```

## Próximas Mejoras (Futuro)

- [ ] Diff visual en frontend (highlight de cambios)
- [ ] Versionado de archivos físicos (S3 versioning)
- [ ] Comentarios en versiones
- [ ] Aprobación de cambios (workflow)
- [ ] Notificaciones de cambios a suscriptores
- [ ] Exportar historial a PDF
- [ ] Comparación lado a lado (split view)

## Referencias

- Migración SQL: `db/migrations/fase_6b_versionado_recursos.sql`
- Modelo PHP: `backend/models/RecursoVersion.php`
- Integración: `backend/models/Recurso.php` (método `update`)
- Controller: `backend/controllers/RecursoController.php` (endpoints de versionado)
- Rutas: `backend/routes/api.php`
