@echo off
echo ============================================================
echo   Day Spa Hochheim - Iniciando ambiente de desenvolvimento
echo ============================================================
echo.

REM Verifica se Docker esta instalado
where docker >nul 2>&1
if errorlevel 1 (
    echo [ERRO] Docker nao encontrado.
    echo.
    echo Instale o Docker Desktop em: https://www.docker.com/products/docker-desktop/
    echo Apos instalar, reinicie o computador e execute este script novamente.
    pause
    exit /b 1
)

echo [1/3] Iniciando banco de dados, API e servicos Docker...
docker compose up -d --build
if errorlevel 1 (
    echo [ERRO] Falha ao iniciar Docker Compose.
    pause
    exit /b 1
)

echo.
echo [2/3] Aguardando API ficar pronta...
timeout /t 10 /nobreak >nul

echo.
echo [3/3] Iniciando servidor de desenvolvimento frontend...
start cmd /k "cd /d %~dp0 && npm run dev"

echo.
echo ============================================================
echo   Servicos disponiveis:
echo   - Frontend: http://localhost:5173
echo   - API:      http://localhost:8000
echo   - Admin:    http://localhost:5173/admin
echo   - phpMyAdmin: http://localhost:8080
echo   - Mailhog:  http://localhost:8025
echo ============================================================
echo.
echo Credenciais admin: usuario=admin / senha=password
echo.
pause
