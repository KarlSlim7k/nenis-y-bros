# ============================================================================
# TEST SCRIPT: FASE 6 - BIBLIOTECA DE RECURSOS
# ============================================================================
# Tests completos para recursos, categorías, descargas, calificaciones y búsqueda
# ============================================================================

$API_BASE = "http://localhost/nenis_y_bros/backend/api/v1"
$ContentType = "application/json; charset=utf-8"

# Variables globales
$script:totalTests = 0
$script:passedTests = 0
$script:failedTests = 0
$script:adminToken = $null
$script:estudianteToken = $null
$script:idRecurso = $null
$script:idCategoria = $null

# Función para mostrar resultados
function Show-TestResult {
    param(
        [string]$TestName,
        [bool]$Passed,
        [string]$Details = ""
    )
    
    $script:totalTests++
    
    if ($Passed) {
        $script:passedTests++
        Write-Host "✅ PASS:" -ForegroundColor Green -NoNewline
        Write-Host " $TestName" -ForegroundColor White
    } else {
        $script:failedTests++
        Write-Host "❌ FAIL:" -ForegroundColor Red -NoNewline
        Write-Host " $TestName" -ForegroundColor White
        if ($Details) {
            Write-Host "   $Details" -ForegroundColor Yellow
        }
    }
}

# Función para hacer request
function Invoke-ApiRequest {
    param(
        [string]$Method = "GET",
        [string]$Endpoint,
        [hashtable]$Body = @{},
        [string]$Token = $null
    )
    
    $headers = @{
        "Content-Type" = $ContentType
    }
    
    if ($Token) {
        $headers["Authorization"] = "Bearer $Token"
    }
    
    try {
        $params = @{
            Uri = "$API_BASE$Endpoint"
            Method = $Method
            Headers = $headers
        }
        
        if ($Body.Count -gt 0 -and $Method -ne "GET") {
            $params["Body"] = ($Body | ConvertTo-Json -Depth 10)
        }
        
        $response = Invoke-RestMethod @params
        return $response
    } catch {
        return @{
            success = $false
            message = $_.Exception.Message
            statusCode = $_.Exception.Response.StatusCode.value__
        }
    }
}

Write-Host "`n============================================================================" -ForegroundColor Cyan
Write-Host "  FASE 6: BIBLIOTECA DE RECURSOS - TEST SUITE" -ForegroundColor Cyan
Write-Host "============================================================================`n" -ForegroundColor Cyan

# ============================================================================
# 1. AUTENTICACIÓN
# ============================================================================
Write-Host "📝 1. AUTENTICACIÓN" -ForegroundColor Yellow

# Login Admin
$loginAdmin = Invoke-ApiRequest -Method POST -Endpoint "/auth/login" -Body @{
    email = "admin@test.com"
    password = "password"
}

if ($loginAdmin.success) {
    $script:adminToken = $loginAdmin.data.token
    Show-TestResult "Login admin exitoso" $true
} else {
    Show-TestResult "Login admin exitoso" $false "No se pudo autenticar"
    exit 1
}

# Login Estudiante
$loginEstudiante = Invoke-ApiRequest -Method POST -Endpoint "/auth/login" -Body @{
    email = "emprendedor@test.com"
    password = "password"
}

if ($loginEstudiante.success) {
    $script:estudianteToken = $loginEstudiante.data.token
    Show-TestResult "Login estudiante exitoso" $true
} else {
    Show-TestResult "Login estudiante exitoso" $false "No se pudo autenticar"
}

# ============================================================================
# 2. CATEGORÍAS
# ============================================================================
Write-Host "`n📚 2. CATEGORÍAS" -ForegroundColor Yellow

# Listar categorías (público)
$categorias = Invoke-ApiRequest -Endpoint "/recursos/categorias"
Show-TestResult "Listar categorías (público)" ($categorias.success -and $categorias.data.Count -ge 8)

# Listar categorías con estadísticas
$categoriasStats = Invoke-ApiRequest -Endpoint "/recursos/categorias?con_estadisticas=true"
Show-TestResult "Listar categorías con estadísticas" ($categoriasStats.success -and $categoriasStats.data[0].recursos_publicados -ne $null)

# Crear categoría (admin)
$newCategoria = Invoke-ApiRequest -Method POST -Endpoint "/recursos/categorias" -Token $adminToken -Body @{
    nombre = "Test Category"
    slug = "test-category-$(Get-Random -Maximum 9999)"
    descripcion = "Categoría de prueba"
    icono = "🧪"
    color = "#FF5733"
}

if ($newCategoria.success) {
    $script:idCategoria = $newCategoria.data.id_categoria
    Show-TestResult "Crear categoría (admin)" $true
} else {
    Show-TestResult "Crear categoría (admin)" $false $newCategoria.message
}

# Obtener categoría por ID
$categoriaDetail = Invoke-ApiRequest -Endpoint "/recursos/categorias/$script:idCategoria"
Show-TestResult "Obtener categoría por ID" ($categoriaDetail.success -and $categoriaDetail.data.nombre -eq "Test Category")

# Actualizar categoría
$updateCategoria = Invoke-ApiRequest -Method PUT -Endpoint "/recursos/categorias/$script:idCategoria" -Token $adminToken -Body @{
    nombre = "Test Category Updated"
}
Show-TestResult "Actualizar categoría" $updateCategoria.success

# Intentar crear categoría sin auth
$noAuthCategoria = Invoke-ApiRequest -Method POST -Endpoint "/recursos/categorias" -Body @{
    nombre = "No Auth"
    slug = "no-auth"
}
Show-TestResult "Bloquear creación sin auth" (-not $noAuthCategoria.success)

# ============================================================================
# 3. RECURSOS - CRUD
# ============================================================================
Write-Host "`n📄 3. RECURSOS - CRUD" -ForegroundColor Yellow

# Crear recurso (admin)
$newRecurso = Invoke-ApiRequest -Method POST -Endpoint "/recursos" -Token $adminToken -Body @{
    id_categoria = $script:idCategoria
    titulo = "Test Resource - PowerShell Script"
    slug = "test-resource-ps-$(Get-Random -Maximum 9999)"
    descripcion = "Este es un recurso de prueba creado desde PowerShell para validar la API"
    tipo_recurso = "articulo"
    tipo_acceso = "gratuito"
    nivel = "intermedio"
    archivo_url = "https://example.com/test.pdf"
    archivo_nombre = "test.pdf"
    contenido_texto = "Contenido de prueba extenso para el recurso"
    imagen_portada = "https://via.placeholder.com/400x300"
    estado = "publicado"
    etiquetas = @("PowerShell", "Testing", "API")
}

if ($newRecurso.success) {
    $script:idRecurso = $newRecurso.data.id_recurso
    Show-TestResult "Crear recurso (admin)" $true
} else {
    Show-TestResult "Crear recurso (admin)" $false $newRecurso.message
}

# Listar recursos (público)
$recursos = Invoke-ApiRequest -Endpoint "/recursos?limite=5"
Show-TestResult "Listar recursos (público)" ($recursos.success -and $recursos.data.recursos.Count -gt 0)
Show-TestResult "Paginación en listar recursos" ($recursos.data.total -ne $null -and $recursos.data.total_paginas -ne $null)

# Obtener recurso por ID
$recursoDetail = Invoke-ApiRequest -Endpoint "/recursos/$script:idRecurso"
Show-TestResult "Obtener recurso por ID" ($recursoDetail.success -and $recursoDetail.data.titulo -like "*PowerShell*")

# Obtener recurso por slug
$recursoBySlug = Invoke-ApiRequest -Endpoint "/recursos/slug/$($recursoDetail.data.slug)"
Show-TestResult "Obtener recurso por slug" ($recursoBySlug.success -and $recursoBySlug.data.id_recurso -eq $script:idRecurso)

# Actualizar recurso
$updateRecurso = Invoke-ApiRequest -Method PUT -Endpoint "/recursos/$script:idRecurso" -Token $adminToken -Body @{
    descripcion = "Descripción actualizada desde test"
    destacado = $true
}
Show-TestResult "Actualizar recurso" $updateRecurso.success

# Verificar actualización
$recursoUpdated = Invoke-ApiRequest -Endpoint "/recursos/$script:idRecurso"
Show-TestResult "Verificar recurso destacado" ($recursoUpdated.data.destacado -eq 1)

# Listar recursos destacados
$destacados = Invoke-ApiRequest -Endpoint "/recursos/destacados?limite=5"
Show-TestResult "Listar recursos destacados" ($destacados.success -and $destacados.data.Count -gt 0)

# ============================================================================
# 4. FILTROS Y BÚSQUEDA
# ============================================================================
Write-Host "`n🔍 4. FILTROS Y BÚSQUEDA" -ForegroundColor Yellow

# Filtrar por categoría
$filtroCategoria = Invoke-ApiRequest -Endpoint "/recursos?categoria=$script:idCategoria"
Show-TestResult "Filtrar por categoría" ($filtroCategoria.success -and $filtroCategoria.data.recursos.Count -gt 0)

# Filtrar por tipo de recurso
$filtroTipo = Invoke-ApiRequest -Endpoint "/recursos?tipo_recurso=articulo"
Show-TestResult "Filtrar por tipo de recurso" ($filtroTipo.success)

# Filtrar por nivel
$filtroNivel = Invoke-ApiRequest -Endpoint "/recursos?nivel=intermedio"
Show-TestResult "Filtrar por nivel" ($filtroNivel.success)

# Ordenar por más descargados
$ordenDescargados = Invoke-ApiRequest -Endpoint "/recursos?orden=mas_descargados&limite=5"
Show-TestResult "Ordenar por más descargados" ($ordenDescargados.success)

# Ordenar por mejor calificados
$ordenCalificados = Invoke-ApiRequest -Endpoint "/recursos?orden=mejor_calificados&limite=5"
Show-TestResult "Ordenar por mejor calificados" ($ordenCalificados.success)

# Búsqueda por texto
$busqueda = Invoke-ApiRequest -Endpoint "/recursos?buscar=PowerShell"
Show-TestResult "Búsqueda por texto" ($busqueda.success -and $busqueda.data.recursos.Count -gt 0)

# Búsqueda fulltext
$busquedaFull = Invoke-ApiRequest -Endpoint "/recursos/buscar?q=test"
Show-TestResult "Búsqueda fulltext" $busquedaFull.success

# ============================================================================
# 5. DESCARGAS
# ============================================================================
Write-Host "`n⬇️ 5. DESCARGAS" -ForegroundColor Yellow

# Intentar descargar sin auth
$descargaNoAuth = Invoke-ApiRequest -Method POST -Endpoint "/recursos/$script:idRecurso/descargar"
Show-TestResult "Bloquear descarga sin auth" (-not $descargaNoAuth.success)

# Descargar recurso (estudiante)
$descarga = Invoke-ApiRequest -Method POST -Endpoint "/recursos/$script:idRecurso/descargar" -Token $estudianteToken
if ($descarga.success) {
    Show-TestResult "Descargar recurso (estudiante)" $true
    Show-TestResult "Otorgar puntos por descarga" ($descarga.data.puntos_obtenidos -eq 5)
} else {
    Show-TestResult "Descargar recurso (estudiante)" $false $descarga.message
}

# Verificar contador de descargas
Start-Sleep -Seconds 1
$recursoDescargado = Invoke-ApiRequest -Endpoint "/recursos/$script:idRecurso"
Show-TestResult "Incrementar contador de descargas" ($recursoDescargado.data.total_descargas -ge 1)

# Listar mis descargas
$misDescargas = Invoke-ApiRequest -Endpoint "/recursos/mis-descargas" -Token $estudianteToken
Show-TestResult "Listar mis descargas" ($misDescargas.success -and $misDescargas.data.recursos.Count -gt 0)
Show-TestResult "Paginación en mis descargas" ($misDescargas.data.total -ne $null)

# Descargar nuevamente (sin puntos adicionales por trigger)
$descargaRepeat = Invoke-ApiRequest -Method POST -Endpoint "/recursos/$script:idRecurso/descargar" -Token $estudianteToken
Show-TestResult "Permitir descarga repetida" $descargaRepeat.success

# ============================================================================
# 6. CALIFICACIONES
# ============================================================================
Write-Host "`n⭐ 6. CALIFICACIONES" -ForegroundColor Yellow

# Intentar calificar sin descargar
$calificacionSinDescarga = Invoke-ApiRequest -Method POST -Endpoint "/recursos/999/calificar" -Token $adminToken -Body @{
    calificacion = 5
    comentario = "Intento inválido"
}
Show-TestResult "Bloquear calificación sin descarga" (-not $calificacionSinDescarga.success)

# Calificar recurso (estudiante que ya descargó)
$calificacion = Invoke-ApiRequest -Method POST -Endpoint "/recursos/$script:idRecurso/calificar" -Token $estudianteToken -Body @{
    calificacion = 5
    comentario = "Excelente recurso de prueba. Los tests funcionan perfectamente!"
}

if ($calificacion.success) {
    Show-TestResult "Calificar recurso" $true
} else {
    Show-TestResult "Calificar recurso" $false $calificacion.message
}

# Verificar actualización de promedio
Start-Sleep -Seconds 1
$recursoCalificado = Invoke-ApiRequest -Endpoint "/recursos/$script:idRecurso"
Show-TestResult "Actualizar promedio de calificación" ($recursoCalificado.data.calificacion_promedio -gt 0)
Show-TestResult "Incrementar contador de calificaciones" ($recursoCalificado.data.total_calificaciones -ge 1)

# Listar calificaciones del recurso
$calificaciones = Invoke-ApiRequest -Endpoint "/recursos/$script:idRecurso/calificaciones?limite=5"
Show-TestResult "Listar calificaciones del recurso" ($calificaciones.success -and $calificaciones.data.Count -gt 0)

# Actualizar calificación
$updateCalificacion = Invoke-ApiRequest -Method POST -Endpoint "/recursos/$script:idRecurso/calificar" -Token $estudianteToken -Body @{
    calificacion = 4
    comentario = "Actualización: Muy bueno, pero puede mejorar"
}
Show-TestResult "Actualizar calificación existente" $updateCalificacion.success

# ============================================================================
# 7. RECURSOS RELACIONADOS
# ============================================================================
Write-Host "`n🔗 7. RECURSOS RELACIONADOS" -ForegroundColor Yellow

# Obtener recursos relacionados
$relacionados = Invoke-ApiRequest -Endpoint "/recursos/$script:idRecurso/relacionados?limite=4"
Show-TestResult "Obtener recursos relacionados" $relacionados.success

# ============================================================================
# 8. ESTADÍSTICAS (ADMIN)
# ============================================================================
Write-Host "`n📊 8. ESTADÍSTICAS" -ForegroundColor Yellow

# Obtener estadísticas globales (admin)
$stats = Invoke-ApiRequest -Endpoint "/recursos/estadisticas" -Token $adminToken
if ($stats.success) {
    Show-TestResult "Obtener estadísticas globales (admin)" $true
    Show-TestResult "Estadísticas incluyen total_recursos" ($stats.data.total_recursos -ne $null)
    Show-TestResult "Estadísticas incluyen total_descargas" ($stats.data.total_descargas_global -ne $null)
} else {
    Show-TestResult "Obtener estadísticas globales (admin)" $false $stats.message
}

# Bloquear estadísticas para no-admin
$statsNoAuth = Invoke-ApiRequest -Endpoint "/recursos/estadisticas" -Token $estudianteToken
Show-TestResult "Bloquear estadísticas para estudiante" (-not $statsNoAuth.success)

# ============================================================================
# 9. LIMPIEZA (OPCIONAL)
# ============================================================================
Write-Host "`n🧹 9. LIMPIEZA" -ForegroundColor Yellow

# Eliminar recurso de prueba
$deleteRecurso = Invoke-ApiRequest -Method DELETE -Endpoint "/recursos/$script:idRecurso" -Token $adminToken
Show-TestResult "Eliminar recurso" $deleteRecurso.success

# Verificar que no existe
$recursoDeleted = Invoke-ApiRequest -Endpoint "/recursos/$script:idRecurso"
Show-TestResult "Confirmar eliminación de recurso" (-not $recursoDeleted.success)

# Eliminar categoría de prueba
$deleteCategoria = Invoke-ApiRequest -Method DELETE -Endpoint "/recursos/categorias/$script:idCategoria" -Token $adminToken
Show-TestResult "Eliminar categoría" $deleteCategoria.success

# ============================================================================
# RESUMEN
# ============================================================================
Write-Host "`n============================================================================" -ForegroundColor Cyan
Write-Host "  RESUMEN DE TESTS" -ForegroundColor Cyan
Write-Host "============================================================================" -ForegroundColor Cyan

Write-Host "`nTotal de tests ejecutados:" -NoNewline
Write-Host " $script:totalTests" -ForegroundColor White

Write-Host "Tests exitosos:" -NoNewline
Write-Host " $script:passedTests" -ForegroundColor Green

Write-Host "Tests fallidos:" -NoNewline
Write-Host " $script:failedTests" -ForegroundColor Red

$successRate = [math]::Round(($script:passedTests / $script:totalTests) * 100, 2)
Write-Host "`nTasa de éxito:" -NoNewline
if ($successRate -ge 95) {
    Write-Host " $successRate%" -ForegroundColor Green
} elseif ($successRate -ge 80) {
    Write-Host " $successRate%" -ForegroundColor Yellow
} else {
    Write-Host " $successRate%" -ForegroundColor Red
}

Write-Host "`n============================================================================`n" -ForegroundColor Cyan

# Exit code
if ($script:failedTests -eq 0) {
    exit 0
} else {
    exit 1
}
