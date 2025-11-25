# Script de despliegue para Nenis y Bros
# Ejecutar en PowerShell como administrador

Write-Host "🚀 Iniciando despliegue de Nenis y Bros..." -ForegroundColor Green

# Verificar si Git está instalado
if (!(Get-Command git -ErrorAction SilentlyContinue)) {
    Write-Host "❌ Git no está instalado. Descárgalo desde https://git-scm.com/downloads" -ForegroundColor Red
    exit 1
}

# Verificar si el directorio es un repositorio Git
if (!(Test-Path ".git")) {
    Write-Host "📝 Inicializando repositorio Git..." -ForegroundColor Yellow
    git init
    git add .
    git commit -m "Initial commit - Sistema de formación empresarial"
    Write-Host "✅ Repositorio inicializado" -ForegroundColor Green
} else {
    Write-Host "ℹ️ Repositorio Git ya existe" -ForegroundColor Blue
}

Write-Host ""
Write-Host "📋 Próximos pasos:" -ForegroundColor Cyan
Write-Host "1. Crea un repositorio en GitHub" -ForegroundColor White
Write-Host "2. Ejecuta: git remote add origin https://github.com/KarlSlim7k/nenis-y-bros.git" -ForegroundColor White
Write-Host "3. Ejecuta: git push -u origin main" -ForegroundColor White
Write-Host "4. Ve a https://vercel.com y conecta tu repositorio" -ForegroundColor White
Write-Host "5. Ve a https://railway.app y despliega el backend" -ForegroundColor White
Write-Host ""
Write-Host "📖 Lee DEPLOYMENT_README.md para instrucciones detalladas" -ForegroundColor Green