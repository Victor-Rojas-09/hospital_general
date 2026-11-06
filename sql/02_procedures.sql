USE hospital_general;

DROP PROCEDURE IF EXISTS sp_archivar_ingreso;
DELIMITER $$
CREATE PROCEDURE sp_archivar_ingreso(IN p_id_ingreso BIGINT UNSIGNED)
BEGIN
    DECLARE v_id_paciente BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_fecha_alta DATETIME DEFAULT CURRENT_TIMESTAMP;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT id_paciente
      INTO v_id_paciente
      FROM ingresos
     WHERE id_ingreso = p_id_ingreso
     FOR UPDATE;

    IF v_id_paciente IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El ingreso no existe o ya fue archivado.';
    END IF;

    SET v_fecha_alta = CURRENT_TIMESTAMP;

    -- Cerrar la asignación vigente y liberar la cama antes del archivo
    UPDATE asignaciones_cama
       SET fecha_fin = v_fecha_alta
     WHERE id_ingreso = p_id_ingreso
       AND fecha_fin IS NULL;

    DELETE FROM ocupaciones_cama WHERE id_ingreso = p_id_ingreso;

    -- Copiar la información de las tablas originales
    INSERT INTO historico_ingresos (
        id_ingreso, id_paciente, fecha_ingreso, fecha_alta, archivado_en,
        paciente_nombre, paciente_apellidos, nss_cifrado, dni_cifrado, fecha_nacimiento
    )
    SELECT i.id_ingreso, i.id_paciente, i.fecha_ingreso, v_fecha_alta, v_fecha_alta,
           p.nombre, p.apellidos, p.nss_cifrado, p.dni_cifrado, p.fecha_nacimiento
      FROM ingresos i
      JOIN pacientes p ON p.id_paciente = i.id_paciente
     WHERE i.id_ingreso = p_id_ingreso;

    INSERT INTO historico_asignaciones_cama (
        id_asignacion, id_ingreso, numero_planta, nombre_planta,
        numero_cama, fecha_inicio, fecha_fin
    )
    SELECT a.id_asignacion, a.id_ingreso, a.numero_planta, pl.nombre,
           a.numero_cama, a.fecha_inicio, COALESCE(a.fecha_fin, v_fecha_alta)
      FROM asignaciones_cama a
      JOIN plantas pl ON pl.numero_planta = a.numero_planta
     WHERE a.id_ingreso = p_id_ingreso;

    INSERT INTO historico_tarjetas_visita (
        id_tarjeta, id_ingreso, numero_tarjeta, hora_inicio, hora_fin, activa
    )
    SELECT id_tarjeta, id_ingreso, numero_tarjeta, hora_inicio, hora_fin, activa
      FROM tarjetas_visita
     WHERE id_ingreso = p_id_ingreso;

    INSERT INTO historico_visitas_medicas (
        id_visita, id_ingreso, codigo_medico, medico_nombre, especialidad, fecha_hora
    )
    SELECT v.id_visita, v.id_ingreso, v.codigo_medico,
           CONCAT(m.nombre, ' ', m.apellidos), m.especialidad, v.fecha_hora
      FROM visitas_medicas v
      JOIN medicos m ON m.codigo_medico = v.codigo_medico
     WHERE v.id_ingreso = p_id_ingreso;

    INSERT INTO historico_diagnosticos_paciente (
        id_registro, id_ingreso, codigo_diagnostico,
        descripcion_cifrada, fecha_diagnostico, id_visita
    )
    SELECT dp.id_registro, dp.id_ingreso, dp.codigo_diagnostico,
           d.descripcion_cifrada, dp.fecha_diagnostico, dp.id_visita
      FROM diagnosticos_paciente dp
      JOIN diagnosticos d ON d.codigo_diagnostico = dp.codigo_diagnostico
     WHERE dp.id_ingreso = p_id_ingreso;

    -- Retirar las filas operativas
    DELETE FROM diagnosticos_paciente WHERE id_ingreso = p_id_ingreso;
    DELETE FROM tarjetas_visita WHERE id_ingreso = p_id_ingreso;
    DELETE FROM visitas_medicas WHERE id_ingreso = p_id_ingreso;
    DELETE FROM asignaciones_cama WHERE id_ingreso = p_id_ingreso;
    DELETE FROM ingresos WHERE id_ingreso = p_id_ingreso;

    COMMIT;
END$$
DELIMITER ;
