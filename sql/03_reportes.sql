USE hospital_general;

-- 1. Ocupación por planta: camas registradas, ocupadas y disponibles.
SELECT p.numero_planta,
       p.nombre,
       COUNT(c.numero_cama) AS total_camas,
       COUNT(o.numero_cama) AS camas_ocupadas,
       COUNT(c.numero_cama) - COUNT(o.numero_cama) AS camas_disponibles,
       ROUND(100 * COUNT(o.numero_cama) / NULLIF(COUNT(c.numero_cama), 0), 2) AS porcentaje_ocupacion
FROM plantas p
LEFT JOIN camas c ON c.numero_planta = p.numero_planta
LEFT JOIN ocupaciones_cama o
       ON o.numero_planta = c.numero_planta
      AND o.numero_cama = c.numero_cama
GROUP BY p.numero_planta, p.nombre
ORDER BY p.numero_planta;

-- 2. Prevalencia por período. La aplicación descifra la descripción en PHP.
SELECT x.codigo_diagnostico,
       COUNT(*) AS total_casos
FROM (
    SELECT codigo_diagnostico, fecha_diagnostico
    FROM diagnosticos_paciente
    UNION ALL
    SELECT codigo_diagnostico, fecha_diagnostico
    FROM historico_diagnosticos_paciente
) AS x
WHERE x.fecha_diagnostico BETWEEN '2026-01-01' AND '2026-12-31'
GROUP BY x.codigo_diagnostico
ORDER BY total_casos DESC;

-- 3. Recorrido de un paciente (reemplazar 1 por el id_paciente deseado).
SELECT x.numero_planta,
       x.nombre_planta,
       x.numero_cama,
       x.fecha_inicio,
       x.fecha_fin
FROM (
    SELECT a.numero_planta, pl.nombre AS nombre_planta, a.numero_cama,
           a.fecha_inicio, a.fecha_fin, i.id_paciente
    FROM asignaciones_cama a
    JOIN ingresos i ON i.id_ingreso = a.id_ingreso
    JOIN plantas pl ON pl.numero_planta = a.numero_planta
    UNION ALL
    SELECT ha.numero_planta, ha.nombre_planta, ha.numero_cama,
           ha.fecha_inicio, ha.fecha_fin, hi.id_paciente
    FROM historico_asignaciones_cama ha
    JOIN historico_ingresos hi ON hi.id_ingreso = ha.id_ingreso
) AS x
WHERE x.id_paciente = 1
ORDER BY x.fecha_inicio;

-- 4. Prevalencia por especialidad (incluye ingresos actuales e históricos).
-- Cambiar 'Medicina interna' por la especialidad que se desea consultar.
SELECT x.codigo_diagnostico,
       x.especialidad,
       COUNT(*) AS total_casos
FROM (
    SELECT dp.codigo_diagnostico, m.especialidad, dp.fecha_diagnostico
    FROM diagnosticos_paciente dp
    LEFT JOIN visitas_medicas v ON v.id_visita = dp.id_visita
    LEFT JOIN medicos m ON m.codigo_medico = v.codigo_medico
    UNION ALL
    SELECT hdp.codigo_diagnostico, hv.especialidad, hdp.fecha_diagnostico
    FROM historico_diagnosticos_paciente hdp
    LEFT JOIN historico_visitas_medicas hv ON hv.id_visita = hdp.id_visita
) AS x
WHERE x.fecha_diagnostico BETWEEN '2026-01-01' AND '2026-12-31'
  AND x.especialidad = 'Medicina interna'
GROUP BY x.codigo_diagnostico, x.especialidad
ORDER BY total_casos DESC;
