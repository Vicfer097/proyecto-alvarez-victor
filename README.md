# Agenda de actividades

Practica individual de Infraestructura de Servicios basada en practicaDocker2.pdf.
El numero de control termina en 1: corresponde una agenda cuya entidad es Actividad.

## Estado de la preparacion

- Estructura de carpetas preparada.
- .gitignore excluye .env y conserva .env.example.
- .env.example documenta la configuracion sin contrasenas reales.
- .env local creado con contrasenas aleatorias independientes para root y la aplicacion.
- Modelo SQL con llave primaria, restricciones y tres registros iniciales.
- Etapa 2 completada manualmente por el estudiante.
- Backend, frontend y Mailpit implementados; integracion pendiente de pruebas manuales.

compose.yaml incluye los cinco servicios. La API y la interfaz estan implementadas.
El estudiante ejecuta manualmente todos los comandos Docker. No se han ejecutado
comandos Docker durante el desarrollo del backend y frontend.
Consultar GUIA_INTEGRACION.md para construir, levantar y comprobar el proyecto.

## Estructura

```text
proyecto-alvarez-victor/
|-- compose.yaml
|-- .env.example
|-- .env                 # Local, excluido de Git
|-- .gitignore
|-- README.md
|-- practicaDocker2.pdf
|-- database/
|   |-- init.sql
|   `-- README.md
|-- backend/
|   |-- Dockerfile
|   `-- src/
`-- frontend/
    |-- Dockerfile
    |-- nginx.conf
    `-- src/
```

Los archivos .gitkeep permiten conservar las carpetas src vacias en Git.

## Arquitectura prevista segun el PDF

| Servicio | Tecnologia y responsabilidad | Acceso |
| --- | --- | --- |
| frontend | Nginx, HTML, CSS y JavaScript; interfaz y proxy /api/ | localhost:8081 |
| backend | PHP con Apache; API REST, SQL, Redis y SMTP | Red interna |
| db | MariaDB; persistencia de actividades | Red interna, puerto 3306 |
| redis | Redis 7; cache y contador | Red interna, puerto 6379 |
| mailpit | Captura de correos de prueba | Web localhost:8025; SMTP interno 1025 |

Los cinco servicios perteneceran a app_network. El backend usara los nombres db,
redis y mailpit. El navegador consumira rutas relativas /api/ mediante Nginx.

La configuracion posterior incluira volumen nombrado para MariaDB, persistencia AOF
para Redis, health checks para ambos, dependencias saludables del backend y
restart: unless-stopped. Solo se publicaran 8081 y 8025. No se usara privileged ni
se montara el socket de Docker. Las imagenes tendran versiones explicitas verificadas.

## Modelo y alcance funcional

La base agenda_actividades contiene actividades con id, titulo, descripcion, estado,
prioridad, fecha_limite, fecha_creacion y fecha_actualizacion. El titulo es unico.
Los estados son pendiente, en_proceso y completada; las prioridades baja, media y alta.
Las reglas y consultas se describen en database/README.md.

Se implementara el CRUD con confirmacion al eliminar, cache general con TTL minimo
30 segundos, invalidacion en cada escritura, contador de consultas, correo de creacion
y /health con comprobacion de MariaDB y Redis. La API utilizara GET, POST, PUT y DELETE.

El PDF exige elegir una mejora individual. Se mantiene el alcance previamente
solicitado: busqueda, paginacion, prioridad, fecha limite, validacion avanzada, tema
visual, exportacion JSON y segundo correo al completar una actividad.
Estas funciones estan implementadas y pendientes de verificacion en Docker.

## Requisitos y configuracion local

Docker Desktop o Docker Engine con Docker Compose v2, editor, navegador, curl y Git.
El PDF solicita al menos 4 GB de RAM disponibles.

Para una copia nueva del repositorio, copiar .env.example a .env y sustituir las dos
contrasenas de ejemplo por valores locales distintos. No publicar .env.
MARIADB_PASSWORD sera la credencial que recibira el backend como DB_PASSWORD en Compose;
MARIADB_ROOT_PASSWORD solo sera utilizada para administrar MariaDB.
DB_NAME y MARIADB_DATABASE deben coincidir, al igual que DB_USER y MARIADB_USER.
El SQL actual crea agenda_actividades; cambiar el nombre requiere ajustar tambien el SQL.

Mailpit solo captura mensajes de desarrollo; no entrega correo real.

## Precisiones tecnicas del documento

El PDF menciona DB_PORT=5432 y /var/lib/MariaDB/data. Para la imagen oficial de
MariaDB se utilizaran 3306 y /var/lib/mysql. Tambien menciona MariaDB:16-alpine;
se utiliza la imagen oficial mariadb:11.4 para la etapa 2.
Estas correcciones conservan la arquitectura y los requisitos de la practica.

## Ejecucion y pruebas manuales

```powershell
Set-Location C:\proyecto-alvarez-victor
docker compose config --quiet
docker compose up -d --build
docker compose ps
curl.exe http://localhost:8081/api/health
```

Agenda: http://localhost:8081. Mailpit: http://localhost:8025.
Los puertos publicos estan vinculados a 127.0.0.1; db, redis y backend no publican puertos.

GUIA_INTEGRACION.md describe los endpoints y las pruebas de CRUD, cache, contador,
correos, mejoras individuales, aislamiento y recuperacion. database/ETAPA_2.md conserva
las pruebas de inicializacion y persistencia. No eliminar volumenes para reconstruir.

La cache usa versiones: cada escritura incrementa actividades:version, y las claves
anteriores dejan de utilizarse y expiran con su TTL. Los correos se envian por SMTP
a Mailpit al crear y al cambiar el estado a completada. Los titulos se muestran como
texto, sin interpretar HTML. El tema se conserva en localStorage.

Las evidencias y el PDF final de entrega se elaboraran con los resultados de las
pruebas manuales del estudiante. La construccion y funcionamiento integrado aun
requieren verificacion en Docker.
