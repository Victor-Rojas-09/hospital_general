CREATE DATABASE IF NOT EXISTS hospital_general
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE hospital_general;

CREATE TABLE IF NOT EXISTS roles (
    id_rol TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_rol TINYINT UNSIGNED NOT NULL,
    usuario VARCHAR(40) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pacientes (
    id_paciente BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nss_cifrado TEXT NOT NULL,
    nss_hash CHAR(64) NOT NULL UNIQUE,
    dni_cifrado TEXT NOT NULL,
    dni_hash CHAR(64) NOT NULL UNIQUE,
    nombre VARCHAR(80) NOT NULL,
    apellidos VARCHAR(120) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pacientes_nombre (apellidos, nombre)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plantas (
    numero_planta INT UNSIGNED PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    capacidad_camas SMALLINT UNSIGNED NOT NULL,
    CONSTRAINT chk_capacidad_positiva CHECK (capacidad_camas > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS camas (
    numero_planta INT UNSIGNED NOT NULL,
    numero_cama SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (numero_planta, numero_cama),
    CONSTRAINT fk_camas_planta FOREIGN KEY (numero_planta) REFERENCES plantas(numero_planta)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_numero_cama CHECK (numero_cama > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS medicos (
    codigo_medico VARCHAR(20) PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    apellidos VARCHAR(120) NOT NULL,
    especialidad VARCHAR(100) NULL,
    INDEX idx_medicos_apellidos (apellidos, nombre)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS diagnosticos (
    codigo_diagnostico VARCHAR(20) PRIMARY KEY,
    descripcion_cifrada TEXT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS medicos_diagnosticos (
    codigo_medico VARCHAR(20) NOT NULL,
    codigo_diagnostico VARCHAR(20) NOT NULL,
    PRIMARY KEY (codigo_medico, codigo_diagnostico),
    CONSTRAINT fk_md_medico FOREIGN KEY (codigo_medico) REFERENCES medicos(codigo_medico)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_md_diagnostico FOREIGN KEY (codigo_diagnostico) REFERENCES diagnosticos(codigo_diagnostico)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ingresos (
    id_ingreso BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_paciente BIGINT UNSIGNED NOT NULL,
    fecha_ingreso DATETIME NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ingreso_abierto_paciente (id_paciente),
    CONSTRAINT fk_ingreso_paciente FOREIGN KEY (id_paciente) REFERENCES pacientes(id_paciente)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_ingresos_fecha (fecha_ingreso)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS asignaciones_cama (
    id_asignacion BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_ingreso BIGINT UNSIGNED NOT NULL,
    numero_planta INT UNSIGNED NOT NULL,
    numero_cama SMALLINT UNSIGNED NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NULL,
    UNIQUE KEY uq_asignacion_compuesta (id_asignacion, id_ingreso, numero_planta, numero_cama),
    INDEX idx_asignaciones_ingreso (id_ingreso, fecha_inicio),
    CONSTRAINT fk_asignacion_ingreso FOREIGN KEY (id_ingreso) REFERENCES ingresos(id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_asignacion_cama FOREIGN KEY (numero_planta, numero_cama)
        REFERENCES camas(numero_planta, numero_cama)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_fechas_asignacion CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ocupaciones_cama (
    numero_planta INT UNSIGNED NOT NULL,
    numero_cama SMALLINT UNSIGNED NOT NULL,
    id_ingreso BIGINT UNSIGNED NOT NULL,
    id_asignacion BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (numero_planta, numero_cama),
    UNIQUE KEY uq_ocupacion_ingreso (id_ingreso),
    UNIQUE KEY uq_ocupacion_asignacion (id_asignacion),
    CONSTRAINT fk_ocupacion_cama FOREIGN KEY (numero_planta, numero_cama)
        REFERENCES camas(numero_planta, numero_cama)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ocupacion_asignacion FOREIGN KEY (id_asignacion, id_ingreso, numero_planta, numero_cama)
        REFERENCES asignaciones_cama(id_asignacion, id_ingreso, numero_planta, numero_cama)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tarjetas_visita (
    id_tarjeta BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_ingreso BIGINT UNSIGNED NOT NULL,
    numero_tarjeta TINYINT UNSIGNED NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    slot_activo TINYINT UNSIGNED GENERATED ALWAYS AS
        (CASE WHEN activa = 1 THEN numero_tarjeta ELSE NULL END) STORED,
    creada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tarjeta_slot_activo (id_ingreso, slot_activo),
    CONSTRAINT fk_tarjeta_ingreso FOREIGN KEY (id_ingreso) REFERENCES ingresos(id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_numero_tarjeta CHECK (numero_tarjeta BETWEEN 1 AND 4),
    CONSTRAINT chk_horario_tarjeta CHECK (hora_inicio < hora_fin)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS visitas_medicas (
    id_visita BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_ingreso BIGINT UNSIGNED NOT NULL,
    codigo_medico VARCHAR(20) NOT NULL,
    fecha_hora DATETIME NOT NULL,
    UNIQUE KEY uq_visita_ingreso (id_visita, id_ingreso),
    CONSTRAINT fk_visita_ingreso FOREIGN KEY (id_ingreso) REFERENCES ingresos(id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_visita_medico FOREIGN KEY (codigo_medico) REFERENCES medicos(codigo_medico)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_visitas_fecha (fecha_hora)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS diagnosticos_paciente (
    id_registro BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_ingreso BIGINT UNSIGNED NOT NULL,
    codigo_diagnostico VARCHAR(20) NOT NULL,
    fecha_diagnostico DATE NOT NULL,
    id_visita BIGINT UNSIGNED NULL,
    CONSTRAINT fk_dp_ingreso FOREIGN KEY (id_ingreso) REFERENCES ingresos(id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_dp_diagnostico FOREIGN KEY (codigo_diagnostico) REFERENCES diagnosticos(codigo_diagnostico)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_dp_visita_ingreso FOREIGN KEY (id_visita, id_ingreso)
        REFERENCES visitas_medicas(id_visita, id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_dp_fecha (fecha_diagnostico),
    INDEX idx_dp_ingreso (id_ingreso)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historico_ingresos (
    id_ingreso BIGINT UNSIGNED PRIMARY KEY,
    id_paciente BIGINT UNSIGNED NOT NULL,
    fecha_ingreso DATETIME NOT NULL,
    fecha_alta DATETIME NOT NULL,
    archivado_en DATETIME NOT NULL,
    paciente_nombre VARCHAR(80) NOT NULL,
    paciente_apellidos VARCHAR(120) NOT NULL,
    nss_cifrado TEXT NOT NULL,
    dni_cifrado TEXT NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    CONSTRAINT fk_hist_ingreso_paciente FOREIGN KEY (id_paciente) REFERENCES pacientes(id_paciente)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_hist_paciente (id_paciente),
    INDEX idx_hist_fecha_alta (fecha_alta)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historico_asignaciones_cama (
    id_asignacion BIGINT UNSIGNED PRIMARY KEY,
    id_ingreso BIGINT UNSIGNED NOT NULL,
    numero_planta INT UNSIGNED NOT NULL,
    nombre_planta VARCHAR(80) NOT NULL,
    numero_cama SMALLINT UNSIGNED NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    CONSTRAINT fk_hist_asig_ingreso FOREIGN KEY (id_ingreso) REFERENCES historico_ingresos(id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_hist_asig_ingreso (id_ingreso)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historico_tarjetas_visita (
    id_tarjeta BIGINT UNSIGNED PRIMARY KEY,
    id_ingreso BIGINT UNSIGNED NOT NULL,
    numero_tarjeta TINYINT UNSIGNED NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    activa TINYINT(1) NOT NULL,
    CONSTRAINT fk_hist_tarjeta_ingreso FOREIGN KEY (id_ingreso) REFERENCES historico_ingresos(id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historico_visitas_medicas (
    id_visita BIGINT UNSIGNED PRIMARY KEY,
    id_ingreso BIGINT UNSIGNED NOT NULL,
    codigo_medico VARCHAR(20) NOT NULL,
    medico_nombre VARCHAR(201) NOT NULL,
    especialidad VARCHAR(100) NULL,
    fecha_hora DATETIME NOT NULL,
    CONSTRAINT fk_hist_visita_ingreso FOREIGN KEY (id_ingreso) REFERENCES historico_ingresos(id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_hist_visita_fecha (fecha_hora)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historico_diagnosticos_paciente (
    id_registro BIGINT UNSIGNED PRIMARY KEY,
    id_ingreso BIGINT UNSIGNED NOT NULL,
    codigo_diagnostico VARCHAR(20) NOT NULL,
    descripcion_cifrada TEXT NOT NULL,
    fecha_diagnostico DATE NOT NULL,
    id_visita BIGINT UNSIGNED NULL,
    CONSTRAINT fk_hist_dp_ingreso FOREIGN KEY (id_ingreso) REFERENCES historico_ingresos(id_ingreso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_hist_dp_fecha (fecha_diagnostico)
) ENGINE=InnoDB;