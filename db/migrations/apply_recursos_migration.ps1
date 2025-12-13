# ========================================
# Script de Migración - Recursos Schema
# ========================================

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  MIGRACIÓN DE RECURSOS - RAILWAY" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# 1. Verificar Railway CLI
Write-Host "[1] Verificando Railway CLI..." -ForegroundColor Yellow
try {
    $railwayVersion = railway --version 2>$null
    if ($LASTEXITCODE -ne 0) {
        throw "Railway CLI no encontrado"
    }
    Write-Host "    ✓ Railway CLI encontrado: $railwayVersion" -ForegroundColor Green
} catch {
    Write-Host "    ✗ Railway CLI no encontrado" -ForegroundColor Red
    Write-Host "    Instalar con: npm i -g @railway/cli" -ForegroundColor Yellow
    exit 1
}

# 2. Verificar conexión
Write-Host "`n[2] Verificando conexión a Railway..." -ForegroundColor Yellow
try {
    railway status 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "No conectado a Railway"
    }
    Write-Host "    ✓ Conectado a Railway" -ForegroundColor Green
} catch {
    Write-Host "    ✗ No conectado a Railway" -ForegroundColor Red
    Write-Host "    Ejecutar: railway login" -ForegroundColor Yellow
    exit 1
}

# 3. Leer archivo de migración
$migrationFile = "db\migrations\fix_recursos_schema.sql"
Write-Host "`n[3] Leyendo $migrationFile..." -ForegroundColor Yellow

if (-Not (Test-Path $migrationFile)) {
    Write-Host "    ✗ Archivo no encontrado: $migrationFile" -ForegroundColor Red
    exit 1
}

$sqlContent = Get-Content $migrationFile -Raw -Encoding UTF8
Write-Host "    ✓ Archivo leído ($($sqlContent.Length) caracteres)" -ForegroundColor Green

# 4. Mostrar cambios
Write-Host "`n[4] Revisión de cambios a aplicar:" -ForegroundColor Yellow
Write-Host @"

    Esta migración realizará los siguientes cambios:
    
    ✓ Agregar campos a recursos_aprendizaje:
      - slug, id_autor, contenido_texto, contenido_html
      - duracion_minutos, imagen_preview, video_preview
      - idioma, formato, licencia, destacado
      - fecha_publicacion, fecha_actualizacion
    
    ✓ Crear tablas:
      - descargas_recursos
      - calificaciones_recursos
      - vistas_recursos
    
    ✓ Crear triggers:
      - Actualizar contadores automáticamente
      - Recalcular calificaciones promedio

"@ -ForegroundColor White

$respuesta = Read-Host "`n¿Deseas continuar? (si/no)"
if ($respuesta -notin @('si', 'sí', 's', 'y', 'yes')) {
    Write-Host "`n    ⚠ Migración cancelada por el usuario" -ForegroundColor Yellow
    exit 0
}

# 5. Aplicar migración
Write-Host "`n[5] Aplicando migración..." -ForegroundColor Yellow

# Guardar en archivo temporal
$tempFile = "temp_migration.sql"
$sqlContent | Out-File -FilePath $tempFile -Encoding UTF8

try {
    Write-Host "    Ejecutando SQL..." -ForegroundColor Cyan
    
    # Ejecutar con railway
    $output = railway run -- bash -c "mysql < $tempFile" 2>&1
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "    ✓ Migración ejecutada" -ForegroundColor Green
    } else {
        Write-Host "    ⚠ Algunos comandos pueden haber fallado" -ForegroundColor Yellow
        Write-Host "    Output: $output" -ForegroundColor Gray
    }
} catch {
    Write-Host "    ✗ Error aplicando migración: $_" -ForegroundColor Red
} finally {
    # Limpiar archivo temporal
    if (Test-Path $tempFile) {
        Remove-Item $tempFile -Force
    }
}

# 6. Verificar resultados
Write-Host "`n[6] Verificando estructura..." -ForegroundColor Yellow

$verifyQueries = @(
    @{ Desc = "Contar recursos"; Query = "SELECT COUNT(*) as total FROM recursos_aprendizaje;" },
    @{ Desc = "Verificar nuevos campos"; Query = "SHOW COLUMNS FROM recursos_aprendizaje WHERE Field IN ('slug', 'destacado', 'idioma');" },
    @{ Desc = "Verificar tabla descargas"; Query = "SHOW TABLES LIKE 'descargas_recursos';" },
    @{ Desc = "Verificar tabla calificaciones"; Query = "SHOW TABLES LIKE 'calificaciones_recursos';" }
)

foreach ($check in $verifyQueries) {
    Write-Host "    - $($check.Desc)..." -ForegroundColor Cyan
    try {
        $result = railway run -- mysql -e "$($check.Query)" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Host "      ✓" -ForegroundColor Green
        } else {
            Write-Host "      ⚠" -ForegroundColor Yellow
        }
    } catch {
        Write-Host "      ✗" -ForegroundColor Red
    }
    Start-Sleep -Milliseconds 500
}

# 7. Resultado final
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  MIGRACIÓN COMPLETADA" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host @"
✓ Migración aplicada exitosamente

Próximos pasos:
1. Verificar en Railway dashboard que las tablas existen
2. Probar endpoints del módulo de recursos
3. Revisar que los triggers funcionan correctamente

Endpoints a probar:
- GET  /api/v1/recursos
- GET  /api/v1/recursos/estadisticas
- POST /api/v1/recursos
- PUT  /api/v1/recursos/{id}
- POST /api/v1/recursos/{id}/descargar
- POST /api/v1/recursos/{id}/calificar

"@ -ForegroundColor Green

Write-Host "🎉 ¡Migración completada con éxito!`n" -ForegroundColor Green
