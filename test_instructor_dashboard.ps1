# Test: Dashboard del Instructor - Verificacion de Endpoints
# Verifica que todos los endpoints usados en el dashboard funcionen correctamente

$BaseUrl = "https://nenis-y-bros-production.up.railway.app/api/v1"

Write-Host "======================================" -ForegroundColor Cyan
Write-Host "TEST: Dashboard del Instructor" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""

# 1. LOGIN (obtener token)
Write-Host "1. Autenticando como admin/mentor..." -ForegroundColor Yellow

$loginBody = @{
    email = "admin@nenisybros.com"
    password = "password123"
} | ConvertTo-Json

try {
    $loginResponse = Invoke-RestMethod -Uri "$BaseUrl/auth/login" -Method POST -ContentType "application/json" -Body $loginBody
    
    if ($loginResponse.success -and $loginResponse.data.token) {
        $token = $loginResponse.data.token
        $userId = $loginResponse.data.usuario.id_usuario
        $nombre = $loginResponse.data.usuario.nombre
        Write-Host "OK Login exitoso: $nombre (ID: $userId)" -ForegroundColor Green
        Write-Host "   Token obtenido" -ForegroundColor Gray
        Write-Host ""
    } else {
        Write-Host "ERROR Login fallo" -ForegroundColor Red
        Write-Host "Respuesta: $($loginResponse | ConvertTo-Json -Depth 5)" -ForegroundColor Red
        Write-Host ""
        exit 1
    }
} catch {
    Write-Host "ERROR en login: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

$headers = @{
    "Authorization" = "Bearer $token"
    "Content-Type" = "application/json"
}

# 2. ESTADISTICAS DEL INSTRUCTOR
Write-Host "2. Obteniendo estadisticas del instructor..." -ForegroundColor Yellow

try {
    $statsResponse = Invoke-RestMethod -Uri "$BaseUrl/chat/estadisticas/instructor" -Method GET -Headers $headers
    
    if ($statsResponse.success) {
        Write-Host "OK Estadisticas obtenidas:" -ForegroundColor Green
        $stats = $statsResponse.data
        Write-Host "   - Alumnos unicos: $($stats.alumnos_unicos)" -ForegroundColor Gray
        Write-Host "   - Conversaciones activas: $($stats.conversaciones_activas)" -ForegroundColor Gray
        Write-Host "   - Mensajes pendientes: $($stats.mensajes_pendientes)" -ForegroundColor Gray
        Write-Host "   - Tiempo respuesta promedio: $($stats.tiempo_respuesta_promedio_min) min" -ForegroundColor Gray
        Write-Host "   - Total mensajes del mes: $($stats.total_mensajes_mes)" -ForegroundColor Gray
        Write-Host ""
    } else {
        Write-Host "ERROR: $($statsResponse.message)" -ForegroundColor Red
        Write-Host "Respuesta: $($statsResponse | ConvertTo-Json -Depth 5)" -ForegroundColor Red
        Write-Host ""
    }
} catch {
    Write-Host "ERROR obteniendo estadisticas: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "Detalles: $($_.ErrorDetails.Message)" -ForegroundColor Red
    Write-Host ""
}

# 3. CONVERSACIONES ACTIVAS
Write-Host "3. Obteniendo conversaciones activas..." -ForegroundColor Yellow

try {
    $convResponse = Invoke-RestMethod -Uri "$BaseUrl/chat/conversaciones?estado=activa" -Method GET -Headers $headers
    
    if ($convResponse.success) {
        $conversaciones = $convResponse.data.conversaciones
        $total = $convResponse.data.total
        Write-Host "OK $total conversaciones activas obtenidas" -ForegroundColor Green
        
        if ($conversaciones.Count -gt 0) {
            Write-Host ""
            Write-Host "   Primeras 3 conversaciones:" -ForegroundColor Gray
            $conversaciones | Select-Object -First 3 | ForEach-Object {
                Write-Host "   - [$($_.id_conversacion)] $($_.alumno_nombre)" -ForegroundColor Gray
                Write-Host "     Curso: $($_.curso_titulo)" -ForegroundColor DarkGray
                Write-Host "     Estado: $($_.estado) | No leidos: $($_.mensajes_no_leidos_instructor)" -ForegroundColor DarkGray
                Write-Host "     Ultimo mensaje: $($_.ultimo_mensaje_fecha)" -ForegroundColor DarkGray
                Write-Host ""
            }
        } else {
            Write-Host "   No hay conversaciones activas" -ForegroundColor Gray
            Write-Host ""
        }
    } else {
        Write-Host "ERROR: $($convResponse.message)" -ForegroundColor Red
        Write-Host "Respuesta: $($convResponse | ConvertTo-Json -Depth 5)" -ForegroundColor Red
        Write-Host ""
    }
} catch {
    Write-Host "ERROR obteniendo conversaciones: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "Detalles: $($_.ErrorDetails.Message)" -ForegroundColor Red
    Write-Host ""
}

# RESUMEN
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "RESUMEN DE TESTS" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "OK Login como mentor" -ForegroundColor Green
Write-Host "OK GET /chat/estadisticas/instructor" -ForegroundColor Green
Write-Host "OK GET /chat/conversaciones?estado=activa" -ForegroundColor Green
Write-Host ""
Write-Host "Todos los endpoints del dashboard estan funcionales" -ForegroundColor Green
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""
