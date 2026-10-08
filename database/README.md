# Base de datos de la agenda de actividades

Base de datos: `agenda_actividades`. Tabla principal: `actividades`.
`init.sql` crea el esquema y tres actividades de ejemplo. Las fechas de ejemplo
se calculan a partir del dia en que se ejecuta la inicializacion.

| Campo               | Tipo            | Regla                                                                   |
| ------------------- | --------------- | ----------------------------------------------------------------------- |
| id                  | BIGINT UNSIGNED | Llave primaria autoincremental                                          |
| titulo              | VARCHAR(150)    | Obligatorio, unico, de 3 a 150 caracteres, sin espacios en los extremos |
| descripcion         | VARCHAR(2000)   | Hasta 2000 caracteres; puede estar vacia                                |
| estado              | ENUM            | pendiente, en_proceso o completada; inicialmente pendiente              |
| prioridad           | ENUM            | baja, media o alta; inicialmente media                                  |
| fecha_limite        | DATE            | Opcional; una actividad puede no tener vencimiento                      |
| fecha_creacion      | TIMESTAMP       | Asignada automaticamente al insertar                                    |
| fecha_actualizacion | TIMESTAMP       | Actualizada automaticamente cuando cambian los datos                    |

## Reglas de negocio

- Se considera duplicada una actividad con el mismo titulo. La comparacion usa
  `utf8mb4_unicode_ci`, por lo que no distingue mayusculas ni acentos.
  Si posteriormente se necesitan actividades recurrentes con el mismo titulo,
  esta regla debera modificarse.
- Una actividad esta vencida si no esta completada y su fecha limite es anterior
  al dia actual. Una actividad que vence hoy aun no esta vencida.
- Como criterio inicial, una actividad esta proxima a vencer si no esta completada
  y vence entre hoy y los siguientes tres dias, incluidos ambos extremos.
- Se permiten fechas pasadas para registrar pendientes vencidos.
- El estado de vencimiento se calcula al consultar; no se almacena, porque cambia
  con el paso del tiempo.
- El backend debe recortar espacios del titulo, validar longitudes y valores,
  usar consultas preparadas y convertir errores de duplicados en mensajes claros.
  Debe utilizar modo SQL estricto para rechazar valores invalidos o demasiado largos.
- Se admite texto Unicode y puntuacion habitual. No se restringiran titulos a
  letras ASCII; al mostrarlos en el frontend se debe evitar interpretarlos como HTML.

## Funciones de la imagen y responsabilidad

| Funcion             | Como se implementara                                                                         |
| ------------------- | -------------------------------------------------------------------------------------------- |
| Busqueda            | El backend filtrara titulo o descripcion con un parametro buscar                             |
| Paginacion          | El backend aplicara LIMIT y OFFSET con un orden estable                                      |
| Prioridad           | Se guarda en prioridad; el frontend asignara los colores                                     |
| Fecha limite        | Se guarda en fecha_limite; el backend calculara vencimientos                                 |
| Validacion avanzada | Restricciones SQL mas validacion y mensajes en frontend y backend                            |
| Tema visual         | Preferencia del navegador; no requiere una tabla adicional                                   |
| Exportacion JSON    | El backend consultara las actividades y generara la descarga                                 |
| Segundo correo      | El backend enviara a Mailpit al pasar a completada; no requiere guardar el correo en MariaDB |

Redis almacenara la cache y el contador de consultas fuera de MariaDB.
Los correos de creacion y finalizacion se implementaran posteriormente en el backend.

## Consultas de referencia

Los signos `?` son parametros para consultas preparadas del backend.

```sql
-- Busqueda y paginacion: enviar el patron %texto% dos veces,
-- seguido del limite y desplazamiento, ambos enteros validados.
SELECT * FROM actividades
WHERE titulo LIKE ? OR descripcion LIKE ?
ORDER BY fecha_creacion DESC, id DESC
LIMIT ? OFFSET ?;

-- Actividades vencidas.
SELECT * FROM actividades
WHERE estado <> 'completada' AND fecha_limite < CURDATE()
ORDER BY fecha_limite, id;

-- Actividades proximas a vencer.
SELECT * FROM actividades
WHERE estado <> 'completada'
  AND fecha_limite BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
ORDER BY fecha_limite, id;
```

## Integracion posterior con Docker

Montar `database/init.sql` en `/docker-entrypoint-initdb.d/01-init.sql` y un volumen
nombrado en `/var/lib/mysql`. El backend usara `DB_HOST=db`, `DB_PORT=3306`
y `DB_NAME=agenda_actividades`.

La inicializacion se ejecuta solo cuando el directorio de datos esta vacio.
Editar este archivo no modifica una base de datos ya inicializada: los cambios
posteriores se aplicaran mediante migraciones, conservando los datos.
