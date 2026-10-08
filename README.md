# Agenda de actividades

Practica individual de Infraestructura de Servicios basada en practicaDocker2.pdf.
El numero de control termina en 1: corresponde una agenda cuya entidad es Actividad.

## Estado de la preparacion

- Estructura de carpetas preparada.
- .gitignore excluye .env y conserva .env.example.
- .env.example documenta la configuracion sin contrasenas reales.
- .env local creado con contrasenas aleatorias independientes para root y la aplicacion.
- Modelo SQL con llave primaria, restricciones y tres registros iniciales.
- Etapa 2 configurada en compose.yaml; pruebas pendientes de ejecucion manual.

Los Dockerfiles y nginx.conf estan reservados para las siguientes etapas.
compose.yaml permite levantar db y redis. La aplicacion completa aun no esta implementada.
El estudiante ejecutara manualmente todos los comandos Docker.
La guia de arranque y persistencia se encuentra en database/ETAPA_2.md.

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
Estas funciones estan previstas; aun no estan implementadas.

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

## Siguientes etapas y verificacion

La configuracion de db y redis esta lista. El estudiante debe validar Compose, levantar
ambos y demostrar salud, inicializacion y persistencia siguiendo database/ETAPA_2.md.
Despues implementar backend, Mailpit, frontend y la integracion completa.

Cuando exista una configuracion ejecutable, los comandos previstos son:

```sh
docker compose config
docker compose up -d db redis
docker compose ps
docker compose logs db redis
docker compose exec redis redis-cli ping
```

Para la integracion final:

```sh
docker compose up -d --build
curl http://localhost:8081/api/health
```

Las pruebas del punto 7 cubriran CRUD, persistencia, cache, invalidacion, contador,
correo, aislamiento y recuperacion del backend. Se recopilaran capturas para el PDF
final y se completaran las instrucciones reproducibles del repositorio.
