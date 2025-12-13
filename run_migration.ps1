# Script temporal para ejecutar la migración SQL
# Se eliminará después de la ejecución

$ErrorActionPreference = "Stop"

Write-Host "🔄 Ejecutando migración de tablas de diagnósticos..." -ForegroundColor Cyan

# Leer el archivo SQL
$sqlScript = Get-Content -Path "db\migrations\create_diagnosticos_tables.sql" -Raw

# Usar railway shell para ejecutar mysql con el script
$env:MYSQL_PWD = "hVRfZwfOYSrdWHloqDrsPCAuuAkPKNem"
$tempSqlFile = "temp_migration.sql"
Set-Content -Path $tempSqlFile -Value $sqlScript -Encoding UTF8

Write-Host "📤 Conectando a la base de datos de Railway..." -ForegroundColor Yellow

try {
    # Intentar ejecutar usando railway run
    railway run --command "type $tempSqlFile | mysql -h metro.proxy.rlwy.net -P 52451 -u root --password=hVRfZwfOYSrdWHloqDrsPCAuuAkPKNem formacion_empresarial"
    
    Write-Host "✅ Migración completada exitosamente!" -ForegroundColor Green
} catch {
    Write-Host "❌ Error al ejecutar la migración: $_" -ForegroundColor Red
    Write-Host "💡 Intenta ejecutar manualmente con:" -ForegroundColor Yellow
    Write-Host "   railway connect mysql" -ForegroundColor White
} finally {
    # Limpiar archivo temporal
    if (Test-Path $tempSqlFile) {
        Remove-Item $tempSqlFile -Force
    }
}
