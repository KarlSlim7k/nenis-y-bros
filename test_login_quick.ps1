# Script de prueba rápida de login
$baseUrl = "http://localhost/nenis_y_bros/backend/index.php/api/v1"

$loginBody = @{
    email = "admin@test.com"
    password = "Password123!"
} | ConvertTo-Json

Write-Host "Probando login..." -ForegroundColor Yellow

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/auth/login" `
        -Method POST `
        -Body $loginBody `
        -ContentType "application/json"
    
    Write-Host "Respuesta completa:" -ForegroundColor Green
    $response | ConvertTo-Json -Depth 5
    
    Write-Host "`nDatos del usuario:" -ForegroundColor Cyan
    Write-Host "Nombre: $($response.data.user.nombre)"
    Write-Host "Email: $($response.data.user.email)"
    Write-Host "Tipo Usuario: $($response.data.user.tipo_usuario)" -ForegroundColor Yellow
    Write-Host "Estado: $($response.data.user.estado)"
    
} catch {
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}
