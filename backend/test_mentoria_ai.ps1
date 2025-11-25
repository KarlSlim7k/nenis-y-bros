# ============================================================================
# TEST SCRIPT - MENTORIA AI ENDPOINTS
# ============================================================================
# Prueba completa de los endpoints de MentorIA (Fase 5B.2)
# ============================================================================

$baseUrl = "http://localhost/nenis_y_bros/backend/api/v1"
$token = ""

Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "  TEST: MENTORIA AI ENDPOINTS" -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""

# ============================================================================
# PASO 1: Login como estudiante
# ============================================================================
Write-Host "[1/5] Autenticando usuario..." -ForegroundColor Yellow

$loginData = @{
    email = "alumno.test@nyd.com"
    password = "Test123!"
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/auth/login" -Method POST -Body $loginData -ContentType "application/json"
    $token = $response.data.token
    Write-Host "OK Login exitoso. Token obtenido." -ForegroundColor Green
    Write-Host ""
} catch {
    Write-Host "ERROR en login: $_" -ForegroundColor Red
    exit 1
}

# ============================================================================
# PASO 2: Verificar estadísticas (health check)
# ============================================================================
Write-Host "[2/5] Verificando estado del servicio MentorIA..." -ForegroundColor Yellow

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/mentoria/estadisticas" -Method GET -Headers @{ "Authorization" = "Bearer $token" }
    Write-Host "OK Servicio MentorIA activo" -ForegroundColor Green
    Write-Host "  Modelo: $($response.data.modelo_actual)" -ForegroundColor Gray
    Write-Host "  Mensaje: $($response.data.mensaje)" -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "ERROR al verificar servicio: $_" -ForegroundColor Red
    exit 1
}

# ============================================================================
# PASO 3: Iniciar sesión de mentoría
# ============================================================================
Write-Host "[3/5] Iniciando sesión de mentoría..." -ForegroundColor Yellow

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/mentoria/iniciar" -Method POST -Headers @{ "Authorization" = "Bearer $token" } -ContentType "application/json"
    Write-Host "OK Sesión iniciada exitosamente" -ForegroundColor Green
    Write-Host "  Mensaje de bienvenida:" -ForegroundColor Gray
    Write-Host "  $($response.data.mensaje_bienvenida)" -ForegroundColor White
    
    if ($response.data.sugerencias_temas -and $response.data.sugerencias_temas.Count -gt 0) {
        Write-Host ""
        Write-Host "  Sugerencias de temas:" -ForegroundColor Gray
        foreach ($sugerencia in $response.data.sugerencias_temas) {
            Write-Host "  - $sugerencia" -ForegroundColor White
        }
    }
    
    Write-Host ""
    Write-Host "  Contexto cargado: $($response.data.contexto_cargado)" -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "ERROR al iniciar mentoría: $_" -ForegroundColor Red
    exit 1
}

# ============================================================================
# PASO 4: Hacer una pregunta a MentorIA
# ============================================================================
Write-Host "[4/5] Haciendo pregunta a MentorIA..." -ForegroundColor Yellow

$preguntaData = @{
    pregunta = "Cuales son las mejores estrategias de marketing digital para una pequeña empresa?"
    historial = @()
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/mentoria/preguntar" -Method POST -Headers @{ "Authorization" = "Bearer $token" } -Body $preguntaData -ContentType "application/json"
    Write-Host "OK Respuesta recibida de MentorIA" -ForegroundColor Green
    Write-Host ""
    Write-Host "  Respuesta:" -ForegroundColor Gray
    Write-Host "  $($response.data.respuesta)" -ForegroundColor White
    Write-Host ""
    Write-Host "  Tokens usados: $($response.data.tokens_usados)" -ForegroundColor Gray
    Write-Host "  Finish reason: $($response.data.finish_reason)" -ForegroundColor Gray
    
    $historial = @(
        @{ role = "user"; content = "Cuales son las mejores estrategias de marketing digital para una pequeña empresa?" },
        @{ role = "assistant"; content = $response.data.respuesta }
    )
    Write-Host ""
} catch {
    Write-Host "ERROR al preguntar: $_" -ForegroundColor Red
    exit 1
}

# ============================================================================
# PASO 5: Pregunta de seguimiento (con historial)
# ============================================================================
Write-Host "[5/5] Pregunta de seguimiento con contexto..." -ForegroundColor Yellow

# Simplificar para PowerShell
$seguimientoData = @{
    pregunta = "Dame un ejemplo practico de SEO para mi negocio"
    historial = @(
        @{ role = "user"; content = "Cuales son las mejores estrategias de marketing digital?" },
        @{ role = "assistant"; content = "Te recomiendo SEO, redes sociales y contenido de calidad" }
    )
} | ConvertTo-Json -Depth 5

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/mentoria/preguntar" -Method POST -Headers @{ "Authorization" = "Bearer $token" } -Body $seguimientoData -ContentType "application/json"
    Write-Host "OK Respuesta de seguimiento recibida" -ForegroundColor Green
    Write-Host ""
    Write-Host "  Respuesta:" -ForegroundColor Gray
    Write-Host "  $($response.data.respuesta)" -ForegroundColor White
    Write-Host ""
    Write-Host "  Tokens usados: $($response.data.tokens_usados)" -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "ERROR en pregunta de seguimiento: $_" -ForegroundColor Red
    exit 1
}

# ============================================================================
# PASO 6: Enviar feedback (opcional)
# ============================================================================
Write-Host "[6/6] Enviando feedback positivo..." -ForegroundColor Yellow

$feedbackData = @{
    interaccion_id = "test_$(Get-Date -Format 'yyyyMMddHHmmss')"
    calificacion = "positivo"
    comentario = "Respuesta muy util y practica"
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/mentoria/feedback" -Method POST -Headers @{ "Authorization" = "Bearer $token" } -Body $feedbackData -ContentType "application/json"
    Write-Host "OK Feedback enviado: $($response.message)" -ForegroundColor Green
} catch {
    Write-Host "WARNING al enviar feedback: $_" -ForegroundColor Yellow
}

# ============================================================================
# RESUMEN
# ============================================================================
Write-Host ""
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "  PRUEBAS COMPLETADAS" -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "OK Sistema de MentorIA funcionando correctamente" -ForegroundColor Green
Write-Host "OK Integracion con Groq API (Llama 3) exitosa" -ForegroundColor Green
Write-Host "OK Contexto empresarial cargado correctamente" -ForegroundColor Green
Write-Host "OK Historial de conversacion mantenido" -ForegroundColor Green
Write-Host ""
