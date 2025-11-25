# Guía de Optimización de Archivos - Fase 6B

## Índice
1. [Descripción General](#descripción-general)
2. [Componentes del Sistema](#componentes-del-sistema)
3. [Características Principales](#características-principales)
4. [API Endpoints](#api-endpoints)
5. [Casos de Uso](#casos-de-uso)
6. [Configuración](#configuración)
7. [Ejemplos de Código](#ejemplos-de-código)
8. [Mantenimiento](#mantenimiento)

---

## Descripción General

El sistema de optimización de archivos proporciona capacidades avanzadas para:
- **Optimización automática de imágenes** con compresión inteligente
- **Generación de thumbnails** para carga rápida
- **Conversión a WebP** para navegadores modernos
- **Imágenes responsivas** con srcset automático
- **Descargas seguras** con URLs firmadas temporales
- **Compresión de PDFs** mediante Ghostscript

### Beneficios
- ⚡ Reducción de ancho de banda hasta 70%
- 🚀 Carga de páginas más rápida
- 🔒 Control de acceso con URLs temporales
- 📱 Soporte automático para dispositivos móviles
- 💾 Ahorro de espacio en servidor

---

## Componentes del Sistema

### 1. FileOptimizer.php
Clase singleton que proporciona todas las funcionalidades de optimización.

**Ubicación**: `backend/utils/FileOptimizer.php`

**Métodos principales**:
```php
// Obtener instancia
$optimizer = FileOptimizer::getInstance();

// Optimizar imagen
$optimizedPath = $optimizer->optimizarImagen($originalPath);

// Generar thumbnail
$thumbnailPath = $optimizer->generarThumbnail($imagePath);

// Convertir a WebP
$webpPath = $optimizer->generarWebP($imagePath);

// Generar URL firmada
$token = $optimizer->generarUrlFirmada($filePath, $expirySeconds, $extraData);

// Verificar URL firmada
$params = $optimizer->verificarUrlFirmada($token);

// Generar srcset responsivo
$srcset = $optimizer->generarSrcSet($imagePath);

// Comprimir PDF
$compressedPath = $optimizer->comprimirPDF($pdfPath, 'screen');

// Limpiar caché
$deleted = $optimizer->limpiarCache($uploadsDir, $diasAntiguedad);

// Obtener información del archivo
$info = $optimizer->getFileInfo($filePath);
```

### 2. API Endpoints
**Ubicación**: `backend/controllers/RecursoController.php`

- `POST /api/v1/recursos/optimizar-imagen` - Optimizar imagen subida
- `POST /api/v1/recursos/{id}/generar-url-descarga` - Generar URL temporal
- `GET /api/v1/recursos/download/{token}` - Descargar con URL firmada

### 3. Rutas
**Ubicación**: `backend/routes/api.php`

---

## Características Principales

### 1. Optimización de Imágenes

**Configuración**:
```php
const MAX_IMAGE_WIDTH = 1920;
const MAX_IMAGE_HEIGHT = 1080;
const JPEG_QUALITY = 85;
const PNG_COMPRESSION = 8;
const WEBP_QUALITY = 80;
```

**Proceso**:
1. Redimensionar a máximo 1920x1080 manteniendo aspect ratio
2. Comprimir JPEG al 85% de calidad
3. Comprimir PNG con nivel 8 (máxima compresión)
4. Preservar transparencia en PNG
5. Generar versión WebP al 80%

**Reducción típica**:
- JPEG: 30-50% sin pérdida visible
- PNG: 40-60% con compresión sin pérdida
- WebP: 60-70% vs JPEG original

### 2. Generación de Thumbnails

**Configuración**:
```php
const THUMBNAIL_WIDTH = 400;
const THUMBNAIL_HEIGHT = 300;
```

**Características**:
- Redimensionamiento proporcional
- Preserva transparencia
- Sufijo `_thumb` en nombre
- Compresión optimizada

### 3. URLs Firmadas (Signed URLs)

**Seguridad**:
- HMAC-SHA256 para firma
- Parámetros cifrados en Base64
- Validación de expiración
- Prevención de manipulación

**Estructura del token**:
```
base64_encode(json_encode($params)) . '.' . hmac_sha256($params)
```

**Parámetros incluidos**:
```php
[
    'file' => '/ruta/absoluta/archivo.jpg',
    'expiry' => 1234567890,  // timestamp Unix
    'id_recurso' => 123,      // opcional
    'id_usuario' => 456       // opcional
]
```

### 4. Srcset Responsivo

Genera automáticamente 4 variantes:
- 480px - Móviles pequeños
- 768px - Tablets
- 1200px - Desktop estándar
- 1920px - Desktop HD

**Salida**:
```php
[
    'uploads/recursos/imagen_480.jpg',
    'uploads/recursos/imagen_768.jpg',
    'uploads/recursos/imagen_1200.jpg',
    'uploads/recursos/imagen_1920.jpg'
]
```

**Uso en HTML**:
```html
<img src="imagen_1920.jpg"
     srcset="imagen_480.jpg 480w,
             imagen_768.jpg 768w,
             imagen_1200.jpg 1200w,
             imagen_1920.jpg 1920w"
     sizes="(max-width: 480px) 480px,
            (max-width: 768px) 768px,
            (max-width: 1200px) 1200px,
            1920px">
```

### 5. Compresión de PDFs

**Niveles de calidad**:
- `screen` (72 dpi) - Para visualización web
- `ebook` (150 dpi) - Balance calidad/tamaño
- `printer` (300 dpi) - Para impresión estándar
- `prepress` (300 dpi) - Para impresión profesional

**Requiere**: Ghostscript instalado
```bash
# Windows (Chocolatey)
choco install ghostscript

# Linux
sudo apt-get install ghostscript
```

---

## API Endpoints

### 1. Optimizar Imagen

**Endpoint**: `POST /api/v1/recursos/optimizar-imagen`

**Autenticación**: Bearer Token (admin o instructor)

**Request**:
```http
POST /api/v1/recursos/optimizar-imagen
Content-Type: multipart/form-data
Authorization: Bearer {token}

imagen: [archivo binario]
```

**Response** (201 Created):
```json
{
    "success": true,
    "message": "Imagen optimizada exitosamente",
    "data": {
        "url_original": "/uploads/recursos/recurso_abc123.jpg",
        "url_thumbnail": "/uploads/recursos/recurso_abc123_thumb.jpg",
        "url_webp": "/uploads/recursos/recurso_abc123.webp",
        "srcset": [
            "/uploads/recursos/recurso_abc123_480.jpg",
            "/uploads/recursos/recurso_abc123_768.jpg",
            "/uploads/recursos/recurso_abc123_1200.jpg",
            "/uploads/recursos/recurso_abc123_1920.jpg"
        ],
        "tamanio_bytes": 245600,
        "tamanio_formateado": "240 KB",
        "dimensiones": "1920x1080",
        "mime_type": "image/jpeg"
    }
}
```

**Validaciones**:
- Solo admin e instructor
- Tipos permitidos: JPEG, PNG, GIF, WebP
- Tamaño máximo: 10 MB
- MIME type verificado por finfo

**Ejemplo cURL**:
```bash
curl -X POST "http://localhost/nenis_y_bros/api/v1/recursos/optimizar-imagen" \
  -H "Authorization: Bearer $TOKEN" \
  -F "imagen=@/path/to/image.jpg"
```

### 2. Generar URL de Descarga

**Endpoint**: `POST /api/v1/recursos/{id}/generar-url-descarga`

**Autenticación**: Bearer Token

**Request**:
```http
POST /api/v1/recursos/123/generar-url-descarga
Authorization: Bearer {token}
```

**Response** (200 OK):
```json
{
    "success": true,
    "message": "URL de descarga generada exitosamente",
    "data": {
        "url_descarga": "http://localhost/nenis_y_bros/api/v1/recursos/download/eyJmaWxlIjoiL3Vwb...",
        "expira_en_segundos": 3600,
        "expira_en": "2024-01-15 15:30:00"
    }
}
```

**Características**:
- URL válida por 1 hora (3600 segundos)
- Incluye id_recurso y id_usuario en token
- Verifica que el recurso esté publicado
- Verifica existencia del archivo

**Ejemplo JavaScript**:
```javascript
async function generarUrlDescarga(idRecurso) {
    const response = await fetch(
        `${API_URL}/recursos/${idRecurso}/generar-url-descarga`,
        {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`
            }
        }
    );
    
    const data = await response.json();
    return data.data.url_descarga;
}
```

### 3. Descargar con URL Firmada

**Endpoint**: `GET /api/v1/recursos/download/{token}`

**Autenticación**: No requiere (seguridad en el token)

**Request**:
```http
GET /api/v1/recursos/download/eyJmaWxlIjoiL3Vwb...
```

**Response**: Archivo binario con headers

**Headers de respuesta**:
```http
Content-Type: [mime_type del archivo]
Content-Length: [tamaño en bytes]
Content-Disposition: attachment; filename="archivo.pdf"
Cache-Control: private, max-age=0, no-cache, no-store, must-revalidate
Pragma: no-cache
Expires: 0
X-Content-Type-Options: nosniff
```

**Errores**:
- 403: URL inválida o expirada
- 404: Archivo no encontrado

**Ejemplo HTML**:
```html
<a href="${urlDescarga}" download>
    Descargar recurso (válido por 1 hora)
</a>
```

---

## Casos de Uso

### Caso 1: Upload de imagen desde frontend

```javascript
// 1. Usuario selecciona imagen
const fileInput = document.getElementById('imagen');
const formData = new FormData();
formData.append('imagen', fileInput.files[0]);

// 2. Enviar a optimizar
const response = await fetch(`${API_URL}/recursos/optimizar-imagen`, {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`
    },
    body: formData
});

const result = await response.json();

// 3. Usar URLs optimizadas al crear recurso
const recursoData = {
    titulo: 'Mi Recurso',
    url_archivo: result.data.url_original,
    url_preview: result.data.url_thumbnail,
    // ... otros campos
};

await fetch(`${API_URL}/recursos`, {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify(recursoData)
});
```

### Caso 2: Descarga controlada

```javascript
// 1. Generar URL temporal al hacer clic
async function descargarRecurso(idRecurso) {
    try {
        // Generar URL firmada
        const response = await fetch(
            `${API_URL}/recursos/${idRecurso}/generar-url-descarga`,
            {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            }
        );
        
        const data = await response.json();
        
        // Mostrar advertencia de expiración
        alert(`URL válida por ${data.data.expira_en_segundos / 60} minutos`);
        
        // Abrir URL de descarga
        window.open(data.data.url_descarga, '_blank');
        
    } catch (error) {
        alert('Error al generar URL de descarga');
    }
}
```

### Caso 3: Imágenes responsivas

```javascript
// Resultado de optimización
const srcset = result.data.srcset.map((url, index) => {
    const widths = [480, 768, 1200, 1920];
    return `${url} ${widths[index]}w`;
}).join(', ');

// HTML generado
const imgHtml = `
    <img src="${result.data.url_original}"
         srcset="${srcset}"
         sizes="(max-width: 768px) 100vw, 
                (max-width: 1200px) 80vw,
                1200px"
         alt="Imagen optimizada">
`;
```

### Caso 4: Limpieza de caché (cron job)

```php
// Script para ejecutar periódicamente (ej: cada noche)
// Ubicación: backend/cron/limpiar_cache.php

<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/FileOptimizer.php';

$optimizer = FileOptimizer::getInstance();
$uploadsDir = __DIR__ . '/../../uploads/recursos/';

// Eliminar archivos con más de 30 días sin uso
$deleted = $optimizer->limpiarCache($uploadsDir, 30);

echo "Limpieza completada: $deleted archivos eliminados\n";
```

**Configurar en cron (Linux)**:
```bash
# Ejecutar cada día a las 3 AM
0 3 * * * php /var/www/backend/cron/limpiar_cache.php >> /var/log/cache_cleanup.log 2>&1
```

**Configurar en Task Scheduler (Windows)**:
```powershell
$action = New-ScheduledTaskAction -Execute 'php.exe' -Argument 'C:\xampp\htdocs\nenis_y_bros\backend\cron\limpiar_cache.php'
$trigger = New-ScheduledTaskTrigger -Daily -At 3am
Register-ScheduledTask -Action $action -Trigger $trigger -TaskName "CleanupResourceCache" -Description "Limpia caché de recursos antiguos"
```

---

## Configuración

### Variables de entorno (.env)

```env
# Secret para firmar URLs (¡NUNCA compartir!)
ENCRYPTION_KEY=tu_clave_secreta_muy_larga_aqui

# URL base de la aplicación
APP_URL=http://localhost/nenis_y_bros
```

### Configuración de PHP (php.ini)

```ini
# Para permitir uploads de 10 MB
upload_max_filesize = 10M
post_max_size = 12M
memory_limit = 128M

# Extensiones requeridas
extension=gd           ; Para procesamiento de imágenes
extension=fileinfo     ; Para detección de MIME types
```

### Permisos de directorios

```bash
# Linux
chmod 755 uploads/recursos/
chown www-data:www-data uploads/recursos/

# Windows con XAMPP
# Asegurar que el usuario de Apache tenga permisos de escritura
```

### Verificar instalación

```php
<?php
// test_optimization_setup.php

// 1. Verificar extensión GD
if (!extension_loaded('gd')) {
    die("ERROR: Extensión GD no instalada\n");
}
echo "✓ GD instalado\n";

// 2. Verificar funciones WebP
if (function_exists('imagewebp')) {
    echo "✓ Soporte WebP disponible\n";
} else {
    echo "⚠ WebP no disponible (GD compilado sin soporte WebP)\n";
}

// 3. Verificar Ghostscript (para PDFs)
exec('gs -version', $output, $return);
if ($return === 0) {
    echo "✓ Ghostscript instalado: " . $output[0] . "\n";
} else {
    echo "⚠ Ghostscript no encontrado (compresión de PDF no disponible)\n";
}

// 4. Verificar permisos de escritura
$uploadsDir = __DIR__ . '/../uploads/recursos/';
if (is_writable($uploadsDir)) {
    echo "✓ Directorio uploads escribible\n";
} else {
    echo "ERROR: Directorio uploads no tiene permisos de escritura\n";
}

echo "\nConfiguración lista para optimización de archivos\n";
```

---

## Ejemplos de Código

### Frontend: Formulario de Upload con Preview

```html
<!DOCTYPE html>
<html>
<head>
    <title>Upload con Optimización</title>
    <style>
        .preview { max-width: 400px; margin: 20px 0; }
        .info { background: #f0f0f0; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Subir Recurso</h1>
    
    <input type="file" id="fileInput" accept="image/*">
    <button onclick="uploadAndOptimize()">Optimizar y Subir</button>
    
    <div id="preview"></div>
    <div id="info"></div>
    
    <script>
        const API_URL = 'http://localhost/nenis_y_bros/api/v1';
        const token = localStorage.getItem('token');
        
        async function uploadAndOptimize() {
            const fileInput = document.getElementById('fileInput');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Selecciona una imagen');
                return;
            }
            
            // Mostrar preview original
            const reader = new FileReader();
            reader.onload = (e) => {
                document.getElementById('preview').innerHTML = `
                    <h3>Original</h3>
                    <img src="${e.target.result}" class="preview">
                    <p>Tamaño: ${(file.size / 1024).toFixed(2)} KB</p>
                `;
            };
            reader.readAsDataURL(file);
            
            // Enviar a optimizar
            const formData = new FormData();
            formData.append('imagen', file);
            
            try {
                const response = await fetch(`${API_URL}/recursos/optimizar-imagen`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`
                    },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Mostrar resultados
                    const data = result.data;
                    document.getElementById('info').innerHTML = `
                        <h3>Optimizada</h3>
                        <p><strong>Tamaño:</strong> ${data.tamanio_formateado}</p>
                        <p><strong>Dimensiones:</strong> ${data.dimensiones}</p>
                        <p><strong>URLs generadas:</strong></p>
                        <ul>
                            <li>Original: <a href="${data.url_original}" target="_blank">Ver</a></li>
                            <li>Thumbnail: <a href="${data.url_thumbnail}" target="_blank">Ver</a></li>
                            <li>WebP: <a href="${data.url_webp}" target="_blank">Ver</a></li>
                        </ul>
                        <p><strong>Srcset:</strong> ${data.srcset.length} variantes</p>
                    `;
                    
                    // Ahora puedes crear el recurso con las URLs optimizadas
                    console.log('URLs optimizadas:', data);
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error al optimizar: ' + error.message);
            }
        }
    </script>
</body>
</html>
```

### Backend: Integración en controlador personalizado

```php
<?php
// En tu controlador personalizado

public function subirImagen() {
    try {
        $usuario = AuthMiddleware::authenticate();
        
        if (!isset($_FILES['imagen'])) {
            Response::error('No se recibió imagen', 400);
        }
        
        $file = $_FILES['imagen'];
        $uploadsDir = __DIR__ . '/../../uploads/recursos/';
        
        // Generar nombre único
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('img_') . '.' . $ext;
        $originalPath = $uploadsDir . $filename;
        
        // Mover archivo
        move_uploaded_file($file['tmp_name'], $originalPath);
        
        // Optimizar
        $optimizer = FileOptimizer::getInstance();
        
        // Optimización principal
        $optimizedPath = $optimizer->optimizarImagen($originalPath);
        
        // Thumbnail
        $thumbPath = $optimizer->generarThumbnail($optimizedPath);
        
        // WebP
        $webpPath = $optimizer->generarWebP($optimizedPath);
        
        // Srcset
        $srcset = $optimizer->generarSrcSet($optimizedPath);
        
        // Info
        $info = $optimizer->getFileInfo($optimizedPath);
        
        // Guardar en base de datos
        $data = [
            'url_original' => '/uploads/recursos/' . basename($optimizedPath),
            'url_thumbnail' => $thumbPath ? '/uploads/recursos/' . basename($thumbPath) : null,
            'url_webp' => $webpPath ? '/uploads/recursos/' . basename($webpPath) : null,
            'tamanio_bytes' => $info['size'],
            'dimensiones' => $info['width'] . 'x' . $info['height']
        ];
        
        Response::success($data, 'Imagen procesada exitosamente');
        
    } catch (Exception $e) {
        Logger::error('Error en subirImagen: ' . $e->getMessage());
        Response::error('Error al procesar imagen', 500);
    }
}
```

---

## Mantenimiento

### Monitoreo de espacio en disco

```php
// backend/scripts/monitor_storage.php

<?php
require_once __DIR__ . '/../config/config.php';

$uploadsDir = __DIR__ . '/../../uploads/recursos/';

function getDirSize($dir) {
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

$totalSize = getDirSize($uploadsDir);
$formatted = FileOptimizer::getInstance()->formatBytes($totalSize);

echo "Espacio usado en uploads/recursos: $formatted\n";

// Enviar alerta si supera 1 GB
if ($totalSize > 1024 * 1024 * 1024) {
    // Enviar email o notificación
    echo "⚠ ALERTA: Espacio usado supera 1 GB\n";
}
```

### Auditoría de optimizaciones

```sql
-- Ver actividad de optimización reciente
SELECT 
    u.nombre_completo,
    l.tipo_accion,
    l.detalles,
    l.fecha_accion
FROM logs_actividad l
JOIN usuarios u ON l.id_usuario = u.id_usuario
WHERE l.tipo_accion = 'optimizar_imagen'
AND l.fecha_accion >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY l.fecha_accion DESC;
```

### Backup de archivos originales

```bash
#!/bin/bash
# backup_uploads.sh

UPLOADS_DIR="/var/www/html/nenis_y_bros/uploads/recursos"
BACKUP_DIR="/var/backups/recursos"
DATE=$(date +%Y%m%d)

# Crear backup comprimido
tar -czf "${BACKUP_DIR}/recursos_${DATE}.tar.gz" "$UPLOADS_DIR"

# Eliminar backups antiguos (más de 30 días)
find "$BACKUP_DIR" -name "recursos_*.tar.gz" -mtime +30 -delete

echo "Backup completado: recursos_${DATE}.tar.gz"
```

---

## Troubleshooting

### Problema: "URL de descarga inválida o expirada"

**Causa**: Token manipulado o vencido

**Solución**:
1. Verificar que `ENCRYPTION_KEY` en `.env` no haya cambiado
2. Generar nueva URL de descarga
3. Verificar fecha/hora del servidor sincronizada

### Problema: Imágenes WebP no se generan

**Causa**: GD sin soporte WebP

**Solución**:
```bash
# Verificar
php -r "echo function_exists('imagewebp') ? 'WebP OK' : 'WebP NO';"

# Linux: Reinstalar PHP con WebP
sudo apt-get install php-gd libwebp-dev
sudo service apache2 restart

# Windows: Usar PHP 7.4+ que incluye WebP por defecto
```

### Problema: Compresión PDF no funciona

**Causa**: Ghostscript no instalado

**Solución**:
```bash
# Windows
choco install ghostscript

# Linux
sudo apt-get install ghostscript

# Verificar
gs -version
```

### Problema: Límite de tamaño de archivo

**Causa**: Configuración PHP restrictiva

**Solución** (php.ini):
```ini
upload_max_filesize = 10M
post_max_size = 12M
memory_limit = 128M
```

Reiniciar servidor web después de cambios.

---

## Conclusión

El sistema de optimización de archivos proporciona:
✅ Reducción automática de tamaño de archivos
✅ Múltiples formatos y resoluciones
✅ Seguridad con URLs temporales
✅ Soporte para imágenes responsivas
✅ Fácil integración en flujo de trabajo

Para más información, consultar:
- `backend/utils/FileOptimizer.php` - Implementación completa
- `backend/controllers/RecursoController.php` - Integración en API
- `backend/test_file_optimizer.ps1` - Suite de pruebas
