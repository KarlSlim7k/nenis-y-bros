# ============================================================================
# SCRIPT DE PRUEBA: Endpoints de Administrador
# ============================================================================
# Descripción: Prueba todos los endpoints del panel administrativo
# Uso: .\test_admin_endpoints.ps1
# ============================================================================

$baseUrl = "http://localhost/nenis_y_bros/backend/index.php/api/v1"
$adminEmail = "admin@test.com"
$adminPassword = "Password123!"

Write-Host "================================================" -ForegroundColor Cyan
Write-Host "  PRUEBA DE ENDPOINTS ADMINISTRATIVOS" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# ============================================================================
# 1. LOGIN COMO ADMINISTRADOR
# ============================================================================

Write-Host "1. Intentando login como administrador..." -ForegroundColor Yellow

$loginBody = @{
    email = $adminEmail
    password = $adminPassword
} | ConvertTo-Json

try {
    $loginResponse = Invoke-RestMethod -Uri "$baseUrl/auth/login" `
        -Method POST `
        -Body $loginBody `
        -ContentType "application/json"
    
    if ($loginResponse.success -and $loginResponse.data.token) {
        $token = $loginResponse.data.token
        $user = $loginResponse.data.user
        
        Write-Host "   ✓ Login exitoso" -ForegroundColor Green
        Write-Host "   - Usuario: $($user.nombre) $($user.apellido)" -ForegroundColor Gray
        Write-Host "   - Email: $($user.email)" -ForegroundColor Gray
        Write-Host "   - Tipo: $($user.tipo_usuario)" -ForegroundColor Gray
        Write-Host "   - Token: $($token.Substring(0, 20))..." -ForegroundColor Gray
        Write-Host ""
        
        if ($user.tipo_usuario -ne "administrador") {
            Write-Host "   ✗ ERROR: El usuario no es administrador" -ForegroundColor Red
            exit 1
        }
    } else {
        Write-Host "   ✗ Login fallido" -ForegroundColor Red
        Write-Host "   Respuesta: $($loginResponse | ConvertTo-Json)" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "   ✗ Error en login: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

# Headers con token de autenticación
$headers = @{
    "Authorization" = "Bearer $token"
    "Content-Type" = "application/json"
}

# ============================================================================
# 2. OBTENER DASHBOARD (ESTADÍSTICAS)
# ============================================================================

Write-Host "2. Obteniendo dashboard y estadísticas..." -ForegroundColor Yellow

try {
    $dashboardResponse = Invoke-RestMethod -Uri "$baseUrl/admin/dashboard" `
        -Method GET `
        -Headers $headers
    
    if ($dashboardResponse.success) {
        Write-Host "   ✓ Dashboard obtenido exitosamente" -ForegroundColor Green
        $stats = $dashboardResponse.data.statistics
        Write-Host "   - Total Usuarios: $($stats.total_usuarios)" -ForegroundColor Gray
        Write-Host "   - Total Cursos: $($stats.total_cursos)" -ForegroundColor Gray
        Write-Host "   - Total Diagnósticos: $($stats.total_diagnosticos)" -ForegroundColor Gray
        Write-Host "   - Total Productos: $($stats.total_productos)" -ForegroundColor Gray
        Write-Host ""
    } else {
        Write-Host "   ✗ Error al obtener dashboard" -ForegroundColor Red
    }
} catch {
    Write-Host "   ✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

# ============================================================================
# 3. LISTAR USUARIOS (CON PAGINACIÓN)
# ============================================================================

Write-Host "3. Listando usuarios (página 1, límite 5)..." -ForegroundColor Yellow

try {
    $usersResponse = Invoke-RestMethod -Uri "$baseUrl/admin/users?page=1&limit=5" `
        -Method GET `
        -Headers $headers
    
    if ($usersResponse.success) {
        Write-Host "   ✓ Usuarios obtenidos exitosamente" -ForegroundColor Green
        Write-Host "   - Total: $($usersResponse.data.total)" -ForegroundColor Gray
        Write-Host "   - Página: $($usersResponse.data.page) de $($usersResponse.data.total_pages)" -ForegroundColor Gray
        Write-Host "   - Usuarios en esta página: $($usersResponse.data.usuarios.Count)" -ForegroundColor Gray
        Write-Host ""
        Write-Host "   Primeros usuarios:" -ForegroundColor Gray
        
        foreach ($u in $usersResponse.data.usuarios) {
            Write-Host "   - [$($u.id_usuario)] $($u.nombre) $($u.apellido) ($($u.email)) - $($u.tipo_usuario) - $($u.estado)" -ForegroundColor DarkGray
        }
        Write-Host ""
    } else {
        Write-Host "   ✗ Error al obtener usuarios" -ForegroundColor Red
    }
} catch {
    Write-Host "   ✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

# ============================================================================
# 4. FILTRAR USUARIOS POR TIPO
# ============================================================================

Write-Host "4. Filtrando usuarios por tipo 'emprendedor'..." -ForegroundColor Yellow

try {
    $filteredResponse = Invoke-RestMethod -Uri "$baseUrl/admin/users?tipo_usuario=emprendedor&limit=3" `
        -Method GET `
        -Headers $headers
    
    if ($filteredResponse.success) {
        Write-Host "   ✓ Filtro aplicado exitosamente" -ForegroundColor Green
        Write-Host "   - Emprendedores encontrados: $($filteredResponse.data.total)" -ForegroundColor Gray
        Write-Host ""
    } else {
        Write-Host "   ✗ Error al filtrar usuarios" -ForegroundColor Red
    }
} catch {
    Write-Host "   ✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

# ============================================================================
# 5. BUSCAR USUARIOS
# ============================================================================

Write-Host "5. Buscando usuarios con término 'admin'..." -ForegroundColor Yellow

try {
    $searchResponse = Invoke-RestMethod -Uri "$baseUrl/admin/users?search=admin&limit=5" `
        -Method GET `
        -Headers $headers
    
    if ($searchResponse.success) {
        Write-Host "   ✓ Búsqueda realizada exitosamente" -ForegroundColor Green
        Write-Host "   - Resultados: $($searchResponse.data.total)" -ForegroundColor Gray
        Write-Host ""
    } else {
        Write-Host "   ✗ Error en búsqueda" -ForegroundColor Red
    }
} catch {
    Write-Host "   ✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

# ============================================================================
# 6. OBTENER DETALLE DE UN USUARIO
# ============================================================================

Write-Host "6. Obteniendo detalle del usuario actual..." -ForegroundColor Yellow

try {
    $userDetailResponse = Invoke-RestMethod -Uri "$baseUrl/admin/users/$($user.id_usuario)" `
        -Method GET `
        -Headers $headers
    
    if ($userDetailResponse.success) {
        Write-Host "   ✓ Detalle obtenido exitosamente" -ForegroundColor Green
        $userDetail = $userDetailResponse.data.user
        Write-Host "   - ID: $($userDetail.id_usuario)" -ForegroundColor Gray
        Write-Host "   - Nombre: $($userDetail.nombre) $($userDetail.apellido)" -ForegroundColor Gray
        Write-Host "   - Email: $($userDetail.email)" -ForegroundColor Gray
        Write-Host "   - Tipo: $($userDetail.tipo_usuario)" -ForegroundColor Gray
        Write-Host "   - Estado: $($userDetail.estado)" -ForegroundColor Gray
        Write-Host ""
    } else {
        Write-Host "   ✗ Error al obtener detalle" -ForegroundColor Red
    }
} catch {
    Write-Host "   ✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

# ============================================================================
# 7. PROBAR CAMBIO DE ESTADO (SIN EJECUTAR)
# ============================================================================

Write-Host "7. Información sobre cambio de estado..." -ForegroundColor Yellow
Write-Host "   Para cambiar el estado de un usuario, usa:" -ForegroundColor Gray
Write-Host "   PUT $baseUrl/admin/users/{id}/status" -ForegroundColor DarkGray
Write-Host "   Body: { `"estado`": `"activo|inactivo|suspendido`" }" -ForegroundColor DarkGray
Write-Host ""
Write-Host "   Ejemplo con PowerShell:" -ForegroundColor Gray
Write-Host "   `$body = @{ estado = 'suspendido' } | ConvertTo-Json" -ForegroundColor DarkGray
Write-Host "   Invoke-RestMethod -Uri '$baseUrl/admin/users/2/status' \" -ForegroundColor DarkGray
Write-Host "       -Method PUT -Headers `$headers -Body `$body" -ForegroundColor DarkGray
Write-Host ""

# ============================================================================
# RESUMEN
# ============================================================================

Write-Host "================================================" -ForegroundColor Cyan
Write-Host "  RESUMEN DE PRUEBAS" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host "✓ Todas las pruebas completadas" -ForegroundColor Green
Write-Host ""
Write-Host "Endpoints probados:" -ForegroundColor White
Write-Host "  1. POST   /auth/login" -ForegroundColor Gray
Write-Host "  2. GET    /admin/dashboard" -ForegroundColor Gray
Write-Host "  3. GET    /admin/users (con paginación)" -ForegroundColor Gray
Write-Host "  4. GET    /admin/users (con filtro por tipo)" -ForegroundColor Gray
Write-Host "  5. GET    /admin/users (con búsqueda)" -ForegroundColor Gray
Write-Host "  6. GET    /admin/users/{id}" -ForegroundColor Gray
Write-Host "  7. PUT    /admin/users/{id}/status (info)" -ForegroundColor Gray
Write-Host ""
Write-Host "Token generado (válido por 2 horas):" -ForegroundColor White
Write-Host $token -ForegroundColor DarkGray
Write-Host ""
Write-Host "================================================" -ForegroundColor Cyan
