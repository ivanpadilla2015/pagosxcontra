@echo off
echo ========================================
echo  Instalador de Backup Automatico
echo  Pagos por Contrato
echo ========================================
echo.
echo  [1] Windows - Tarea Programada
echo  [2] Linux/Hosting - Instrucciones Cron Job
echo.
set /p opcion="Seleccione una opcion (1 o 2): "

if "%opcion%"=="1" goto windows
if "%opcion%"=="2" goto linux
echo Opcion no valida.
goto fin

:windows
echo.
echo --- WINDOWS - Tarea Programada ---
echo.
echo Este script registrara una tarea en el
echo Programador de Tareas de Windows que
echo ejecutara el schedule de Laravel cada minuto.
echo.
echo IMPORTANTE: Ejecute este archivo como Administrador.
echo.
pause

schtasks /create /tn "PagosXContrato_Backup" /tr "D:\wamp64\bin\php\php8.3.28\php.exe artisan schedule:run" /sc minute /mo 1 /f

if %errorlevel% equ 0 (
    echo.
    echo [OK] Tarea programada creada exitosamente.
    echo La tarea ejecutara "php artisan schedule:run" cada minuto.
    echo Los backups se ejecutaran segun la hora configurada en la interfaz.
) else (
    echo.
    echo [ERROR] No se pudo crear la tarea programada.
    echo Asegurese de ejecutar este archivo como Administrador.
)
goto fin

:linux
echo.
echo ========================================
echo  LINUX / HOSTING - Cron Job
echo ========================================
echo.
echo  Si el proyecto esta montado en un hosting Linux,
echo  debe configurar un Cron Job desde el panel
echo  de su proveedor (cPanel, Plesk, etc.).
echo.
echo  Agregue la siguiente linea:
echo.
echo  * * * * * cd /ruta/al/proyecto ^&^& php artisan schedule:run ^>^> /dev/null 2^>^&1
echo.
echo  Reemplaze "/ruta/al/proyecto" con la ruta real
echo  de su proyecto en el servidor.
echo.
echo  Ejemplo cPanel (Ruta típica):
echo  -----------------------------------------------
echo  * * * * * cd /home/usuario/public_html/pagosxcontra && php artisan schedule:run >> /dev/null 2>&1
echo  -----------------------------------------------
echo.
echo  Nota: El path de php puede variar segun el hosting.
echo  Si "php" no funciona, pruebe con:
echo    /usr/bin/php artisan schedule:run
echo    /usr/local/bin/php artisan schedule:run
echo.
echo  Puede verificar la ruta de php ejecutando:
echo    which php
echo.
goto fin

:fin
echo.
pause
