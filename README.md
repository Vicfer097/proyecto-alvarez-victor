# Agenda de actividades con Docker Compose

Proyecto individual de Infraestructura de Servicios para un número de control terminado en **1**. Permite registrar, consultar, editar y eliminar actividades. Integra búsqueda, paginación, prioridad, fecha límite, validación, tema visual, exportación JSON y correos al crear o completar una actividad.

Esta guía permite preparar y comprobar el proyecto desde una copia nueva. Todos los comandos se ejecutan manualmente en **PowerShell**, desde la carpeta del repositorio, salvo el paso de descarga. Los resultados indicados son los esperados; deben comprobarse en el equipo donde se ejecute.

## 1. Arquitectura y archivos

| Servicio | Responsabilidad                                               | Acceso                                   |
| -------- | ------------------------------------------------------------- | ---------------------------------------- |
| frontend | Nginx, HTML, CSS y JavaScript; interfaz y proxy `/api/`       | http://localhost:8081                    |
| backend  | PHP 8.3 con Apache; API REST, validación, SQL, caché y correo | Interno, puerto 80                       |
| db       | MariaDB 11.4; almacenamiento de actividades                   | Interno, puerto 3306                     |
| redis    | Redis 7; caché, contador y persistencia AOF                   | Interno, puerto 6379                     |
| mailpit  | Captura de correos de desarrollo                              | http://localhost:8025; SMTP interno 1025 |

Los cinco servicios comparten `app_network`. Docker resuelve los nombres `backend`, `db`, `redis` y `mailpit` mediante su DNS interno. El navegador solicita `/api/` al frontend; Nginx reenvía la petición al backend. Dentro del backend, `localhost` identifica su propio contenedor: por eso usa `db`, `redis` y `mailpit` para comunicarse con los otros servicios.

Solo los puertos del frontend y la interfaz de Mailpit se publican, vinculados a `127.0.0.1`. MariaDB y Redis tienen health checks; el backend espera a que ambos estén saludables. Todos los servicios usan `restart: unless-stopped`.

```text
proyecto-alvarez-victor/
|-- README.md
|-- compose.yaml
|-- .env.example
|-- .gitignore
|-- practicaDocker2.pdf
|-- database/
|   |-- init.sql
|   `-- README.md
|-- backend/
|   |-- Dockerfile
|   |-- composer.json
|   |-- apache.conf
|   |-- src/
|   `-- tests/validation.php
`-- frontend/
    |-- Dockerfile
    |-- nginx.conf
    `-- src/
```

`.env` se crea localmente y no se incluye en Git. MariaDB conserva sus datos en `mariadb_data`; Redis utiliza `redis_data` y AOF. Mailpit captura mensajes de prueba y no entrega correo real; sus mensajes no tienen un volumen persistente en esta configuración.

## 2. Instalar los requisitos

En Windows, instalar **Git** y **Docker Desktop** con contenedores Linux y el motor WSL 2 habilitado. Si Docker Desktop solicita habilitar virtualización, instalar WSL o reiniciar Windows, completar esos pasos. Abrir Docker Desktop y esperar a que su motor esté iniciado.

Se requiere conexión a Internet para descargar el repositorio, las imágenes y las dependencias de construcción. Los puertos locales 8081 y 8025 deben estar disponibles. No es necesario instalar PHP, Composer, MariaDB, Redis ni Nginx en Windows: se ejecutan en contenedores.

Comprobar en PowerShell:

```powershell
git --version
docker --version
docker compose version
docker info
```

Los comandos deben mostrar sus versiones y `docker info` debe poder comunicarse con el motor. Se utiliza **Docker Compose v2**, con el comando `docker compose`.

## 3. Descargar el proyecto

Desde una carpeta donde tengas permiso de escritura:

```powershell
Set-Location $env:USERPROFILE
git clone https://github.com/Vicfer097/proyecto-alvarez-victor.git
Set-Location .\proyecto-alvarez-victor
Get-ChildItem
```

Si ya descargaste el proyecto, entra en su carpeta real en lugar de clonarlo de nuevo. No es obligatorio usar una ruta en `C:\`. Comprueba que aparecen `compose.yaml`, `.env.example`, `backend`, `frontend` y `database`.

## 4. Crear la configuración local

En una copia nueva, ejecutar:

```powershell
Copy-Item .env.example .env
notepad .env
```

Si `.env` ya existe, edítalo sin sobrescribirlo. Sustituye los valores de ejemplo de **MARIADB_PASSWORD** y **MARIADB_ROOT_PASSWORD** por dos contraseñas distintas. Utiliza valores largos con letras y números para evitar problemas de interpretación del archivo. No compartas el archivo ni sus contraseñas en capturas.

Mantén inicialmente estos valores:

```dotenv
COMPOSE_PROJECT_NAME=proyecto-alvarez-victor
MARIADB_DATABASE=agenda_actividades
MARIADB_USER=agenda_app
DB_HOST=db
DB_PORT=3306
DB_NAME=agenda_actividades
DB_USER=agenda_app
REDIS_HOST=redis
REDIS_PORT=6379
CACHE_TTL=30
SMTP_HOST=mailpit
SMTP_PORT=1025
SMTP_FROM=agenda@example.test
SMTP_TO=estudiante@example.test
FRONTEND_PORT=8081
MAILPIT_UI_PORT=8025
```

Este bloque explica los valores y no reemplaza la plantilla completa: `.env` también debe conservar ambas variables de contraseña.

`DB_NAME` debe coincidir con `MARIADB_DATABASE`, y `DB_USER` con `MARIADB_USER`. Compose pasa `MARIADB_PASSWORD` al backend como `DB_PASSWORD`. El SQL crea explícitamente `agenda_actividades`: cambiar el nombre de la base requiere ajustar también `database/init.sql`. El TTL efectivo del backend nunca es menor de 30 segundos.

Comprobar la exclusión de secretos:

```powershell
git check-ignore .env
git ls-files -- .env
```

El primer comando debe mostrar `.env`; el segundo no debe mostrar nada. `.env.example` sí debe formar parte del repositorio.

## 5. Validar y levantar los cinco servicios

```powershell
docker compose config --quiet
docker compose up -d --build
docker compose ps
```

La validación debe terminar sin errores. La primera construcción puede tardar porque descarga imágenes e instala las dependencias del backend. No cierres Docker Desktop.

En `ps` deben aparecer `frontend`, `backend`, `db`, `redis` y `mailpit` en ejecución; `db` y `redis` deben indicar `healthy`. Si aún están iniciando, espera y repite `docker compose ps`.

En el primer arranque con un volumen vacío, MariaDB ejecuta `database/init.sql`, crea la tabla `actividades` y carga **tres registros iniciales**. El script no vuelve a ejecutarse sobre un volumen ya inicializado.

Si algún servicio falla:

```powershell
docker compose logs --tail=100 db redis backend frontend mailpit
```

## 6. Comprobar salud y abrir la aplicación

```powershell
Invoke-RestMethod http://localhost:8081/api/health | ConvertTo-Json -Depth 5
Start-Process http://localhost:8081
Start-Process http://localhost:8025
```

La comprobación de salud debe devolver `status: ok`, con `db: true` y `redis: true` dentro de `services`. La agenda debe mostrar las tres actividades iniciales en una instalación nueva. Si modificaste los puertos en `.env`, sustituye 8081 y 8025 en las direcciones y comandos de esta guía.

## 7. Probar la interfaz y las mejoras

1. Crear una actividad con título único, descripción, prioridad y fecha límite. Comprobar que aparece en el listado y que Mailpit recibe un correo de creación.
2. Editar sus campos y guardar. Cambiar el estado a `completada` y comprobar el segundo correo en Mailpit.
3. Intentar crear otra actividad con el mismo título: debe aparecer un error de duplicado. Probar también un título demasiado corto.
4. Buscar por título o descripción. Crear suficientes registros para comprobar la paginación de cinco elementos y cambiar de página.
5. Comprobar las prioridades y el aviso visual de fechas vencidas o próximas a vencer.
6. Cambiar el tema y recargar: la preferencia se conserva en el navegador mediante `localStorage`.
7. Exportar JSON y abrir el archivo descargado para revisar sus actividades.
8. Eliminar una actividad: primero cancelar la confirmación y comprobar que permanece; después confirmar y verificar que desaparece.

## 8. Probar API, caché e invalidación

Ejecutar los bloques en orden y en la misma ventana de PowerShell para conservar las variables. Estas pruebas crean una actividad temporal y finalmente la eliminan.

### 8.1 Caché, TTL y contador

```powershell
$base = 'http://localhost:8081/api'
$url = "$base/actividades?pagina=1&limite=5"
# Generar una versión nueva para que la primera consulta no reutilice caché.
docker compose exec -T redis redis-cli INCR actividades:version
$primera = Invoke-RestMethod $url
$segunda = Invoke-RestMethod $url
$primera | Select-Object origen, contador_consultas | Format-Table -AutoSize
$segunda | Select-Object origen, contador_consultas | Format-Table -AutoSize
$version = (docker compose exec -T redis redis-cli --raw GET actividades:version).Trim()
$claves = @(docker compose exec -T redis redis-cli --scan --pattern "actividades:listado:${version}:*")
foreach ($clave in $claves) {
    Write-Output "Clave: $clave"
    docker compose exec -T redis redis-cli TTL $clave
}
docker compose exec -T redis redis-cli GET contador_consultas
```

Las dos consultas consecutivas deben mostrar `mariadb` y `redis`, respectivamente, y el contador debe aumentar. Consulta el TTL antes de que expire: debe ser positivo y disminuir con el tiempo. Un TTL de 29 segundos es compatible con una configuración de 30 segundos. `-2` significa que la clave ya no existe; repite este bloque para generar una nueva.

### 8.2 Crear y comprobar invalidación

```powershell
$datos = @{
    titulo = "Prueba README $(Get-Date -Format 'yyyyMMdd-HHmmss')"
    descripcion = 'Actividad temporal para comprobar integracion.'
    estado = 'pendiente'
    prioridad = 'alta'
    fecha_limite = $null
}
$cuerpo = $datos | ConvertTo-Json
$versionAntes = (docker compose exec -T redis redis-cli --raw GET actividades:version).Trim()
$creada = Invoke-RestMethod "$base/actividades" -Method Post -ContentType 'application/json; charset=utf-8' -Body ([System.Text.Encoding]::UTF8.GetBytes($cuerpo))
$actividadId = $creada.actividad.id
$versionDespues = (docker compose exec -T redis redis-cli --raw GET actividades:version).Trim()
Write-Output "Version: $versionAntes -> $versionDespues"
$creada | ConvertTo-Json -Depth 5
$listado = Invoke-RestMethod $url
$listado | Select-Object origen, total | Format-Table -AutoSize
$listado.actividades | Select-Object id, titulo, estado | Format-Table -AutoSize
```

Debe aumentar la versión, aparecer `correo_enviado: true` y obtenerse un listado desde `mariadb` con la actividad nueva. Verifica el correo y su contenido en Mailpit.

### 8.3 Actualizar y recibir el correo de finalización

```powershell
$datos.estado = 'completada'
$cuerpo = $datos | ConvertTo-Json
$versionAntes = (docker compose exec -T redis redis-cli --raw GET actividades:version).Trim()
$actualizada = Invoke-RestMethod "$base/actividades/$actividadId" -Method Put -ContentType 'application/json; charset=utf-8' -Body ([System.Text.Encoding]::UTF8.GetBytes($cuerpo))
$versionDespues = (docker compose exec -T redis redis-cli --raw GET actividades:version).Trim()
Write-Output "Version: $versionAntes -> $versionDespues"
$actualizada | ConvertTo-Json -Depth 5
$listado = Invoke-RestMethod $url
$listado | Select-Object origen, total | Format-Table -AutoSize
$listado.actividades | Select-Object id, titulo, estado | Format-Table -AutoSize
```

Debe aumentar la versión, mostrarse el estado `completada`, salir `origen: mariadb` y recibirse el correo de finalización. El segundo correo se dispara al pasar de otro estado a `completada`.

### 8.4 Eliminar y comprobar el listado

```powershell
$versionAntes = (docker compose exec -T redis redis-cli --raw GET actividades:version).Trim()
Invoke-RestMethod "$base/actividades/$actividadId" -Method Delete | ConvertTo-Json
$versionDespues = (docker compose exec -T redis redis-cli --raw GET actividades:version).Trim()
Write-Output "Version: $versionAntes -> $versionDespues"
$listado = Invoke-RestMethod $url
$listado | Select-Object origen, total | Format-Table -AutoSize
$listado.actividades | Select-Object id, titulo, estado | Format-Table -AutoSize
```

Debe aumentar la versión y la actividad eliminada ya no debe aparecer. La siguiente consulta utiliza MariaDB. Las claves de versiones anteriores pueden seguir visibles hasta que expire su TTL; las consultas nuevas ya no las usan.

### 8.5 Rutas y códigos HTTP

| Método y ruta pública                                 | Resultado                                             |
| ----------------------------------------------------- | ----------------------------------------------------- |
| GET `/api/health`                                     | 200 si MariaDB y Redis responden; 503 si falla alguna |
| GET `/api/actividades?buscar=texto&pagina=1&limite=5` | 200; listado con origen y contador                    |
| GET `/api/actividades/{id}`                           | 200 o 404 si no existe                                |
| POST `/api/actividades`                               | 201 al crear                                          |
| PUT `/api/actividades/{id}`                           | 200 al actualizar                                     |
| DELETE `/api/actividades/{id}`                        | 200 al eliminar                                       |
| GET `/api/actividades/exportar`                       | 200; descarga JSON                                    |

Los títulos duplicados devuelven 409, los campos inválidos 422, el JSON inválido 400 y los métodos no admitidos 405. Puedes comprobar encabezados sin instalar herramientas adicionales:

```powershell
curl.exe -i http://localhost:8081/api/health
curl.exe -i -X POST http://localhost:8081/api/health
```

Deben responder 200 y 405 respectivamente. Para guardar la exportación:

```powershell
Invoke-WebRequest "$base/actividades/exportar" -OutFile .\actividades.json
```

## 9. Consultar MariaDB y demostrar persistencia

Crear desde la interfaz una actividad con un título reconocible y conservarla durante esta prueba. Abrir el cliente:

```powershell
docker compose exec db mariadb -u agenda_app -p agenda_actividades
```

Cuando aparezca `Enter password:`, escribir el valor de **MARIADB_PASSWORD** configurado en `.env` y pulsar Enter. La contraseña no se muestra mientras se escribe. `-p` sin valor solicita la contraseña; no pulses Enter dejando el campo vacío.

En el cliente MariaDB ejecutar:

```sql
SHOW TABLES;
SHOW CREATE TABLE actividades\G
SELECT id, titulo, estado FROM actividades ORDER BY id;
SELECT COUNT(*) AS total FROM actividades;
EXIT;
```

Anotar el identificador y título del registro de prueba. En PowerShell recrear únicamente el contenedor, conservando el volumen:

```powershell
docker compose up -d --force-recreate db
docker compose ps
docker compose exec db mariadb -u agenda_app -p agenda_actividades
```

Cuando `db` esté saludable, repetir el `SELECT` y comprobar que el registro sigue presente. Salir con `EXIT;`. Una nueva conexión utiliza otra vez `MARIADB_PASSWORD`.

El modelo incluye llave primaria autoincremental, título único y longitud restringida, estados y prioridades definidos, fecha límite opcional y marcas de creación y actualización. La persistencia depende del volumen, no de conservar el contenedor.

## 10. Comprobar AOF, recuperación y validación

```powershell
docker compose exec -T redis redis-cli CONFIG GET appendonly
docker compose exec -T redis redis-cli SET prueba:readme:persistencia confirmado
docker compose up -d --force-recreate redis
docker compose ps
docker compose exec -T redis redis-cli GET prueba:readme:persistencia
docker compose exec -T redis redis-cli DEL prueba:readme:persistencia
```

`appendonly` debe mostrar `yes`. Espera a que Redis esté saludable antes del `GET`; debe devolver `confirmado`. La clave de prueba no tiene TTL para que su expiración no interfiera con esta comprobación.

Para revisar la sintaxis de los archivos PHP:

```powershell
docker compose exec -T backend php -l /var/www/html/index.php
docker compose exec -T backend php -l /var/www/html/functions.php
```

El archivo `backend/tests/validation.php` no se copia a la imagen. Para ejecutar sus pruebas sin modificar los Dockerfiles, copiarlo temporalmente al contenedor conservando su ruta relativa a `src/functions.php`:

```powershell
docker compose exec -T backend mkdir -p /tmp/agenda-tests/tests /tmp/agenda-tests/src
docker compose cp ./backend/tests/validation.php backend:/tmp/agenda-tests/tests/validation.php
docker compose cp ./backend/src/functions.php backend:/tmp/agenda-tests/src/functions.php
docker compose exec -T backend php /tmp/agenda-tests/tests/validation.php
```

Estas pruebas de validación complementan las pruebas de integración y no sustituyen las comprobaciones del navegador, MariaDB, Redis y Mailpit.

## 11. Detener, volver a iniciar y reconstruir

Detener y retirar contenedores y red, conservando los volúmenes:

```powershell
docker compose down
```

Volver a iniciar:

```powershell
docker compose up -d
docker compose ps
```

Tras cambios en el código o en los Dockerfiles:

```powershell
docker compose up -d --build
```

No uses `docker compose down -v` como paso habitual: elimina los volúmenes de MariaDB y Redis y sus datos. Tampoco cambies `COMPOSE_PROJECT_NAME` durante una prueba de persistencia: otro nombre utiliza otros volúmenes.

## 12. Resolver problemas frecuentes

| Problema                                         | Qué revisar                                                                                                                                                                                                                                       |
| ------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Docker no conecta con el motor                   | Abrir Docker Desktop, esperar su inicio y comprobar `docker info`.                                                                                                                                                                                |
| Compose indica una variable obligatoria faltante | Crear `.env` desde la plantilla, guardar las dos contraseñas y ejecutar desde la carpeta que contiene `compose.yaml`.                                                                                                                             |
| Puerto ocupado                                   | Cambiar `FRONTEND_PORT` o `MAILPIT_UI_PORT` en `.env`, ejecutar `docker compose up -d` y utilizar los nuevos puertos.                                                                                                                             |
| MariaDB devuelve `using password: NO`            | Entrar con `-p` y escribir la contraseña de la aplicación cuando se solicite. No dejarla vacía.                                                                                                                                                   |
| MariaDB rechaza una contraseña nueva             | Con un volumen existente, cambiar `.env` no cambia las credenciales guardadas en MariaDB. Recuperar las credenciales originales o modificar el usuario mediante una sesión administrativa válida; no borrar el volumen para solucionar el acceso. |
| No aparecen los tres registros iniciales         | El SQL solo se ejecuta al inicializar un volumen vacío. Un volumen utilizado anteriormente conserva su estado.                                                                                                                                    |
| `/api/health` devuelve 503                       | Revisar `docker compose ps` y los registros de db, redis y backend; comprobar nombres de host y credenciales.                                                                                                                                     |
| Redis muestra TTL -2                             | La clave expiró. Repetir las consultas y comprobar el TTL inmediatamente.                                                                                                                                                                         |
| No llega correo a Mailpit                        | Revisar `correo_enviado`, los registros del backend y que SMTP use `mailpit:1025`. Completar una actividad ya completada no dispara otro correo de finalización.                                                                                  |
| Cambios de código no aparecen                    | Reconstruir con `docker compose up -d --build`; los archivos se copian a las imágenes.                                                                                                                                                            |

## 13. Evidencias y preparación de la entrega

Guardar capturas legibles y completas de:

- Los cinco servicios en ejecución y los estados saludables de MariaDB y Redis.
- CRUD, manejo de errores y al menos una mejora individual funcionando.
- Correo de creación y correo de finalización con los datos de la actividad.
- Primera consulta desde MariaDB, segunda desde Redis, contador y TTL positivo.
- Cambio de versión y listado actualizado después de crear, editar y eliminar.
- Registro de MariaDB antes y después de recrear su contenedor.
- Respuesta de `/api/health` y comprobaciones de la API.

El repositorio debe conservar código fuente, Dockerfiles, `compose.yaml`, `database/init.sql`, `.env.example`, `.gitignore` y este README. El PDF de entrega debe describir el proceso, incluir las capturas reales y el enlace al repositorio. Evita mostrar contraseñas.

Para la revisión individual, practica explicar los servicios, la red, `ports`, los volúmenes, las variables de entorno, los health checks y la invalidación por versiones. Debes poder localizar un registro en MariaDB, mostrar una clave de Redis, provocar un correo y explicar cómo aplicar un cambio de configuración.

## 14. Precisiones respecto al enunciado

El documento de la práctica menciona el puerto 5432, la ruta `/var/lib/MariaDB/data` y la imagen `MariaDB:16-alpine`. Esta implementación usa MariaDB con puerto interno **3306**, almacenamiento **`/var/lib/mysql`** e imagen **`mariadb:11.4`**, tal como está definido en `compose.yaml`. Las correcciones mantienen la arquitectura de cinco servicios solicitada.
