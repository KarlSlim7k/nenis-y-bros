# ============================================================================
# SCRIPT DE TESTING - FASE 5B.1 - Sistema de Chat
# ============================================================================
# Valida los 11 endpoints funcionales del sistema de chat
# (4 endpoints de MentorIA son placeholders para Fase 5B.2)
# ============================================================================

Write-Host "`n╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║         TESTING FASE 5B.1 - SISTEMA DE CHAT                   ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════════╝`n" -ForegroundColor Cyan

$baseUrl = "http://localhost/nenis_y_bros/backend/api/v1"
$testsPassed = 0
$testsFailed = 0

# ============================================================================
# SETUP: Login usuarios
# ============================================================================

Write-Host "📋 SETUP: Autenticación de usuarios..." -ForegroundColor Yellow

# Login Alumno
$json = '{"email":"alumno.test@nyd.com","password":"Test123!"}' | Out-File -FilePath "C:\xampp\htdocs\nenis_y_bros\backend\login.json" -Encoding ascii -NoNewline
$response = curl.exe -X POST "$baseUrl/auth/login" -H "Content-Type: application/json" --data-binary "@C:\xampp\htdocs\nenis_y_bros\backend\login.json" -s | ConvertFrom-Json
$alumnoToken = $response.data.token
$alumnoId = $response.data.user.id_usuario

if ($alumnoToken) {
    Write-Host "   ✅ Alumno autenticado (ID: $alumnoId)" -ForegroundColor Green
} else {
    Write-Host "   ❌ Error al autenticar alumno" -ForegroundColor Red
    exit 1
}

# Login Instructor
$json = '{"email":"instructor.test@nyd.com","password":"Test123!"}' | Out-File -FilePath "C:\xampp\htdocs\nenis_y_bros\backend\login.json" -Encoding ascii -NoNewline
$response = curl.exe -X POST "$baseUrl/auth/login" -H "Content-Type: application/json" --data-binary "@C:\xampp\htdocs\nenis_y_bros\backend\login.json" -s | ConvertFrom-Json
$instructorToken = $response.data.token
$instructorId = $response.data.user.id_usuario

if ($instructorToken) {
    Write-Host "   ✅ Instructor autenticado (ID: $instructorId)`n" -ForegroundColor Green
} else {
    Write-Host "   ❌ Error al autenticar instructor`n" -ForegroundColor Red
    exit 1
}

# ============================================================================
# TEST 1: POST /chat/conversaciones - Crear conversación
# ============================================================================

Write-Host "TEST 1: POST /chat/conversaciones (Crear conversación)" -ForegroundColor Cyan
$json = '{"id_curso":16}' | Out-File -FilePath "C:\xampp\htdocs\nenis_y_bros\backend\test.json" -Encoding ascii -NoNewline
$response = curl.exe -X POST "$baseUrl/chat/conversaciones" -H "Content-Type: application/json" -H "Authorization: Bearer $alumnoToken" --data-binary "@C:\xampp\htdocs\nenis_y_bros\backend\test.json" -s | ConvertFrom-Json

if ($response.success -and $response.data.id_conversacion) {
    $conversacionId = $response.data.id_conversacion
    Write-Host "   ✅ Conversación creada (ID: $conversacionId)" -ForegroundColor Green
    $testsPassed++
} else {
    Write-Host "   ❌ FAILED: $($response.message)" -ForegroundColor Red
    $testsFailed++
}

# ============================================================================
# TEST 2: POST /chat/mensajes - Enviar mensaje (Alumno)
# ============================================================================

Write-Host "TEST 2: POST /chat/mensajes (Alumno envía mensaje)" -ForegroundColor Cyan
$json = "{`"id_conversacion`":$conversacionId,`"contenido`":`"Hola instructor, tengo dudas sobre el módulo 1`"}" | Out-File -FilePath "C:\xampp\htdocs\nenis_y_bros\backend\test.json" -Encoding ascii -NoNewline
$response = curl.exe -X POST "$baseUrl/chat/mensajes" -H "Content-Type: application/json" -H "Authorization: Bearer $alumnoToken" --data-binary "@C:\xampp\htdocs\nenis_y_bros\backend\test.json" -s | ConvertFrom-Json

if ($response.success -and $response.data.id_mensaje) {
    Write-Host "   ✅ Mensaje enviado (ID: $($response.data.id_mensaje))" -ForegroundColor Green
    $testsPassed++
} else {
    Write-Host "   ❌ FAILED: $($response.message)" -ForegroundColor Red
    $testsFailed++
}

# ============================================================================
# TEST 3: GET /chat/conversaciones - Listar conversaciones
# ============================================================================

Write-Host "TEST 3: GET /chat/conversaciones (Listar conversaciones)" -ForegroundColor Cyan
$response = curl.exe -X GET "$baseUrl/chat/conversaciones" -H "Authorization: Bearer $alumnoToken" -s | ConvertFrom-Json

if ($response.success -and $response.data.total -ge 1) {
    Write-Host "   ✅ Conversaciones listadas (Total: $($response.data.total))" -ForegroundColor Green
    $testsPassed++
} else {
    Write-Host "   ❌ FAILED: $($response.message)" -ForegroundColor Red
    $testsFailed++
}

# ============================================================================
# TEST 4: GET /chat/conversaciones/{id} - Obtener conversación
# ============================================================================

Write-Host "TEST 4: GET /chat/conversaciones/$conversacionId (Ver mensajes)" -ForegroundColor Cyan
$response = curl.exe -X GET "$baseUrl/chat/conversaciones/$conversacionId" -H "Authorization: Bearer $alumnoToken" -s | ConvertFrom-Json

if ($response.success -and $response.data.mensajes) {
    $totalMensajes = $response.data.mensajes.Count
    Write-Host "   ✅ Conversación obtenida ($totalMensajes mensajes)" -ForegroundColor Green
    $testsPassed++
} else {
    Write-Host "   ❌ FAILED: $($response.message)" -ForegroundColor Red
    $testsFailed++
}

# ============================================================================
# TEST 5: POST /chat/mensajes - Enviar mensaje (Instructor)
# ============================================================================

Write-Host "TEST 5: POST /chat/mensajes (Instructor responde)" -ForegroundColor Cyan
$json = "{`"id_conversacion`":$conversacionId,`"contenido`":`"Claro! Te ayudo con el módulo 1. ¿Qué duda específica tienes?`"}" | Out-File -FilePath "C:\xampp\htdocs\nenis_y_bros\backend\test.json" -Encoding ascii -NoNewline
$response = curl.exe -X POST "$baseUrl/chat/mensajes" -H "Content-Type: application/json" -H "Authorization: Bearer $instructorToken" --data-binary "@C:\xampp\htdocs\nenis_y_bros\backend\test.json" -s | ConvertFrom-Json

if ($response.success -and $response.data.remitente_tipo -eq "instructor") {
    Write-Host "   ✅ Instructor respondió (ID: $($response.data.id_mensaje))" -ForegroundColor Green
    $testsPassed++
} else {
    Write-Host "   ❌ FAILED: $($response.message)" -ForegroundColor Red
    $testsFailed++
}

# ============================================================================
# TEST 6: PUT /chat/estado - Cambiar estado de presencia
# ============================================================================

Write-Host "TEST 6: PUT /chat/estado (Cambiar estado presencia)" -ForegroundColor Cyan
$json = '{"estado":"en_linea","mensaje":"Disponible para consultas"}' | Out-File -FilePath "C:\xampp\htdocs\nenis_y_bros\backend\test.json" -Encoding ascii -NoNewline
$response = curl.exe -X PUT "$baseUrl/chat/estado" -H "Content-Type: application/json" -H "Authorization: Bearer $instructorToken" --data-binary "@C:\xampp\htdocs\nenis_y_bros\backend\test.json" -s | ConvertFrom-Json

if ($response.success) {
    Write-Host "   ✅ Estado actualizado a 'en_linea'" -ForegroundColor Green
    $testsPassed++
} else {
    Write-Host "   ❌ FAILED: $($response.message)" -ForegroundColor Red
    $testsFailed++
}

# ============================================================================
# TEST 7: POST /chat/disponibilidad - Configurar disponibilidad
# ============================================================================

Write-Host "TEST 7: POST /chat/disponibilidad (Configurar horarios)" -ForegroundColor Cyan
$json = '{"bloques":[{"dia_semana":1,"hora_inicio":"09:00:00","hora_fin":"12:00:00"},{"dia_semana":3,"hora_inicio":"14:00:00","hora_fin":"18:00:00"},{"dia_semana":5,"hora_inicio":"10:00:00","hora_fin":"13:00:00"}]}' | Out-File -FilePath "C:\xampp\htdocs\nenis_y_bros\backend\test.json" -Encoding ascii -NoNewline
$response = curl.exe -X POST "$baseUrl/chat/disponibilidad" -H "Content-Type: application/json" -H "Authorization: Bearer $instructorToken" --data-binary "@C:\xampp\htdocs\nenis_y_bros\backend\test.json" -s | ConvertFrom-Json

if ($response.success) {
    Write-Host "   ✅ Disponibilidad configurada (3 bloques)" -ForegroundColor Green
    $testsPassed++
} else {
    Write-Host "   ❌ FAILED: $($response.message)" -ForegroundColor Red
    $testsFailed++
}

# ============================================================================
# TEST 8: GET /chat/disponibilidad/{id} - Obtener disponibilidad
# ============================================================================

Write-Host "TEST 8: GET /chat/disponibilidad/$instructorId (Ver horarios)" -ForegroundColor Cyan
$response = curl.exe -X GET "$baseUrl/chat/disponibilidad/$instructorId" -H "Authorization: Bearer $alumnoToken" -s | ConvertFrom-Json

if ($response.success -and $response.data.disponibilidad) {
    $bloques = $response.data.disponibilidad.Count
    Write-Host "   ✅ Disponibilidad obtenida ($bloques bloques)" -ForegroundColor Green
    $testsPassed++
} else {
    Write-Host "   ❌ FAILED: $($response.message)" -ForegroundColor Red
    $testsFailed++
}

# ============================================================================
# TEST 9: GET /chat/estadisticas/instructor - Estadísticas
# ============================================================================

Write-Host "TEST 9: GET /chat/estadisticas/instructor" -ForegroundColor Cyan
$response = curl.exe -X GET "$baseUrl/chat/estadisticas/instructor" -H "Authorization: Bearer $instructorToken" -s | ConvertFrom-Json

if ($response.success -and $response.data) {
    Write-Host "   ✅ Estadísticas obtenidas (Conversaciones: $($response.data.conversaciones_activas))" -ForegroundColor Green
    $testsPassed++
} else {
    Write-Host "   ❌ FAILED: $($response.message)" -ForegroundColor Red
    $testsFailed++
}

# ============================================================================
# TEST 10: POST /chat/conversaciones/{id}/archivar
# ============================================================================

Write-Host "TEST 10: POST /chat/conversaciones/$conversacionId/archivar" -ForegroundColor Cyan
$response = curl.exe -X POST "$baseUrl/chat/conversaciones/$conversacionId/archivar" -H "Authorization: Bearer $alumnoToken" -s | ConvertFrom-Json

if ($response.success) {
    Write-Host "   ✅ Conversación archivada" -ForegroundColor Green
    $testsPassed++
} else {
    Write-Host "   ❌ FAILED: $($response.message)" -ForegroundColor Red
    $testsFailed++
}

# ============================================================================
# TEST 11: GET /chat/conversaciones?estado=archivada
# ============================================================================

Write-Host "TEST 11: GET /chat/conversaciones?estado=archivada" -ForegroundColor Cyan
$response = curl.exe -X GET "$baseUrl/chat/conversaciones?estado=archivada" -H "Authorization: Bearer $alumnoToken" -s | ConvertFrom-Json

if ($response.success -and $response.data.total -ge 1) {
    Write-Host "   ✅ Conversaciones archivadas listadas (Total: $($response.data.total))" -ForegroundColor Green
    $testsPassed++
} else {
    Write-Host "   ❌ FAILED: $($response.message)" -ForegroundColor Red
    $testsFailed++
}

# ============================================================================
# RESULTADOS
# ============================================================================

Write-Host "`n╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║                    RESULTADOS DE TESTING                       ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan

$totalTests = $testsPassed + $testsFailed
$porcentaje = [math]::Round(($testsPassed / $totalTests) * 100, 2)

Write-Host "`n   Tests ejecutados: $totalTests" -ForegroundColor White
Write-Host "   ✅ Pasados: $testsPassed" -ForegroundColor Green
Write-Host "   ❌ Fallados: $testsFailed" -ForegroundColor Red
Write-Host "   📊 Porcentaje: $porcentaje%" -ForegroundColor Yellow

if ($testsFailed -eq 0) {
    Write-Host "`n   🎉 TODOS LOS TESTS PASARON! Sistema funcional.`n" -ForegroundColor Green
} else {
    Write-Host "`n   ⚠️  Algunos tests fallaron. Revisar errores.`n" -ForegroundColor Yellow
}

# Cleanup
Remove-Item "C:\xampp\htdocs\nenis_y_bros\backend\test.json" -ErrorAction SilentlyContinue
Remove-Item "C:\xampp\htdocs\nenis_y_bros\backend\login.json" -ErrorAction SilentlyContinue
