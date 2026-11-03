<?php
// Run from the project directory after importing both SQL files:
// php scripts/seed_demo.php
require_once __DIR__ . '/../includes/functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse desde la terminal.\n");
}

$pdo = db();
if ((int) $pdo->query('SELECT COUNT(*) FROM pacientes')->fetchColumn() > 0) {
    exit("Ya existen pacientes. No se insertaron datos de demostración para evitar duplicados.\n");
}

$pdo->beginTransaction();
try {
    $plantInsert = $pdo->prepare('INSERT INTO plantas (numero_planta, nombre, capacidad_camas) VALUES (?, ?, ?)');
    $bedInsert = $pdo->prepare('INSERT INTO camas (numero_planta, numero_cama) VALUES (?, ?)');
    foreach ([[3, 'Medicina interna', 12], [7, 'Pediatría', 12]] as [$number, $name, $capacity]) {
        $plantInsert->execute([$number, $name, $capacity]);
        for ($bed = 1; $bed <= $capacity; $bed++) {
            $bedInsert->execute([$number, $bed]);
        }
    }

    $doctorInsert = $pdo->prepare('INSERT INTO medicos (codigo_medico, nombre, apellidos, especialidad) VALUES (?, ?, ?, ?)');
    $doctorInsert->execute(['MED-001', 'Laura', 'Gómez', 'Medicina interna']);
    $doctorInsert->execute(['MED-002', 'Andrés', 'Ríos', 'Pediatría']);

    $diagnosisInsert = $pdo->prepare('INSERT INTO diagnosticos (codigo_diagnostico, descripcion_cifrada) VALUES (?, ?)');
    $diagnosisInsert->execute(['DIA-001', encrypt_sensitive('Hipertensión arterial')]);
    $diagnosisInsert->execute(['DIA-002', encrypt_sensitive('Infección respiratoria')]);
    $diagnosisInsert->execute(['DIA-003', encrypt_sensitive('Migraña')]);

    $linkInsert = $pdo->prepare('INSERT INTO medicos_diagnosticos (codigo_medico, codigo_diagnostico) VALUES (?, ?)');
    $linkInsert->execute(['MED-001', 'DIA-001']);
    $linkInsert->execute(['MED-001', 'DIA-003']);
    $linkInsert->execute(['MED-002', 'DIA-002']);

    $patientInsert = $pdo->prepare(
        'INSERT INTO pacientes (nss_cifrado, nss_hash, dni_cifrado, dni_hash, nombre, apellidos, fecha_nacimiento)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $samplePatients = [
        ['90012345678', '12345678', 'Juan', 'Pérez', '1985-04-12'],
        ['90023456789', '23456789', 'Ana', 'Gómez', '1998-09-23'],
        ['90034567890', '34567890', 'Carlos', 'Torres', '1972-01-08'],
    ];
    $patientIds = [];
    foreach ($samplePatients as [$nss, $dni, $name, $surname, $birth]) {
        $patientInsert->execute([
            encrypt_sensitive($nss), sensitive_index('NSS', $nss),
            encrypt_sensitive($dni), sensitive_index('DNI', $dni),
            $name, $surname, $birth,
        ]);
        $patientIds[] = (int) $pdo->lastInsertId();
    }

    $admissionInsert = $pdo->prepare('INSERT INTO ingresos (id_paciente, fecha_ingreso) VALUES (?, ?)');
    $assignmentInsert = $pdo->prepare('INSERT INTO asignaciones_cama (id_ingreso, numero_planta, numero_cama, fecha_inicio) VALUES (?, ?, ?, ?)');
    $occupancyInsert = $pdo->prepare('INSERT INTO ocupaciones_cama (numero_planta, numero_cama, id_ingreso, id_asignacion) VALUES (?, ?, ?, ?)');
    $visitInsert = $pdo->prepare('INSERT INTO visitas_medicas (id_ingreso, codigo_medico, fecha_hora) VALUES (?, ?, ?)');
    $diagnosisPatientInsert = $pdo->prepare('INSERT INTO diagnosticos_paciente (id_ingreso, codigo_diagnostico, fecha_diagnostico, id_visita) VALUES (?, ?, ?, ?)');
    $cardInsert = $pdo->prepare('INSERT INTO tarjetas_visita (id_ingreso, numero_tarjeta, hora_inicio, hora_fin) VALUES (?, ?, ?, ?)');

    $admissions = [
        [$patientIds[0], 3, 12, 'MED-001', 'DIA-001'],
        [$patientIds[1], 7, 12, 'MED-002', 'DIA-002'],
    ];
    foreach ($admissions as [$patientId, $plant, $bed, $doctor, $diagnosis]) {
        $admittedAt = date('Y-m-d H:i:s', strtotime('-2 days'));
        $admissionInsert->execute([$patientId, $admittedAt]);
        $admissionId = (int) $pdo->lastInsertId();
        $assignmentInsert->execute([$admissionId, $plant, $bed, $admittedAt]);
        $assignmentId = (int) $pdo->lastInsertId();
        $occupancyInsert->execute([$plant, $bed, $admissionId, $assignmentId]);
        $visitInsert->execute([$admissionId, $doctor, date('Y-m-d H:i:s', strtotime('-1 day'))]);
        $visitId = (int) $pdo->lastInsertId();
        $diagnosisPatientInsert->execute([$admissionId, $diagnosis, date('Y-m-d'), $visitId]);
        $cardInsert->execute([$admissionId, 1, '08:00:00', '18:00:00']);
    }

    $pdo->commit();
    echo "Datos de demostración insertados.\n";
    echo "Se crearon 2 plantas, 24 camas, 3 pacientes, 2 ingresos, médicos, diagnósticos, visitas y tarjetas.\n";
    echo "Observa que la cama 12 puede estar ocupada en la planta 3 y en la planta 7 al mismo tiempo.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Error al insertar la demostración: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
