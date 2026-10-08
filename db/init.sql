CREATE DATABASE IF NOT EXISTS agenda_actividades
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE agenda_actividades;

CREATE TABLE actividades (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(150) NOT NULL,
    descripcion VARCHAR(2000) NOT NULL DEFAULT '',
    estado ENUM('pendiente', 'en_proceso', 'completada') NOT NULL DEFAULT 'pendiente',
    prioridad ENUM('baja', 'media', 'alta') NOT NULL DEFAULT 'media',
    fecha_limite DATE NULL,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT uq_actividades_titulo UNIQUE (titulo),
    CONSTRAINT chk_actividades_titulo CHECK (
        CHAR_LENGTH(TRIM(titulo)) BETWEEN 3 AND 150
        AND CHAR_LENGTH(titulo) = CHAR_LENGTH(TRIM(titulo))
    ),
    INDEX idx_actividades_estado_fecha (estado, fecha_limite),
    INDEX idx_actividades_creacion (fecha_creacion, id)
);

INSERT INTO actividades (titulo, descripcion, estado, prioridad, fecha_limite)
VALUES
    ('Definir el modelo de datos',
     'Disenar la tabla de actividades y sus restricciones.',
     'completada', 'alta', CURDATE()),
    ('Desarrollar la API de actividades',
     'Implementar listado, creacion, actualizacion y eliminacion.',
     'en_proceso', 'alta', DATE_ADD(CURDATE(), INTERVAL 3 DAY)),
    ('Preparar las evidencias de la practica',
     'Documentar las pruebas y capturas de la aplicacion.',
     'pendiente', 'media', DATE_ADD(CURDATE(), INTERVAL 7 DAY));
