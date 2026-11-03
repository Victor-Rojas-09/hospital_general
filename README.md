# Sistema de Gestión Hospitalaria

Aplicación académica desarrollada con **PHP + HTML + CSS + JavaScript básico + MySQL/MariaDB**, pensada para ejecutarse localmente con XAMPP.

> Nota: En este proyecto utiliza únicamente datos ficticios. Esta aplicación es una base funcional para un trabajo académico, no un sistema clínico listo para producción.

## Requisitos

- XAMPP con Apache, PHP 8.1 o superior y MySQL/MariaDB.
- Extensiones PHP `pdo_mysql` y `openssl` habilitadas.
- Navegador web.
- MySQL 8.x o MariaDB 10.4+ recomendado (se utilizan claves foráneas, restricciones `CHECK` y procedimientos almacenados).

## Modelo relacional

| Tabla | Función |
|---|---|
| `pacientes` | Datos personales. NSS y DNI se almacenan cifrados y con índices HMAC para comprobar unicidad y permitir búsqueda exacta. |
| `plantas` | Número, nombre y capacidad de cada planta. |
| `camas` | Camas identificadas con PK compuesta `(numero_planta, numero_cama)`. |
| `ingresos` | Estancias hospitalarias actualmente abiertas. Un paciente solo puede tener un ingreso activo. |
| `asignaciones_cama` | Historial de movimientos de cada ingreso; conserva fechas de inicio y fin. |
| `ocupaciones_cama` | Ocupación actual. Su PK compuesta impide ocupar una cama dos veces y `UNIQUE(id_ingreso)` impide dos camas simultáneas para un ingreso. |
| `tarjetas_visita` | Hasta cuatro tarjetas activas por ingreso, con horario y estado; conserva las desactivadas. |
| `medicos` | Código, nombre, apellidos y especialidad. |
| `diagnosticos` | Código y descripción cifrada. |
| `medicos_diagnosticos` | Tabla intermedia que implementa la relación N:M médico-diagnóstico. |
| `visitas_medicas` | Visitas realizadas a cada ingreso, con médico y fecha/hora. |
| `diagnosticos_paciente` | Diagnósticos asociados a un ingreso y su fecha; puede enlazarse a una visita del mismo ingreso. |
| `historico_*` | Tablas que reciben los ingresos dados de alta y sus camas, tarjetas, visitas y diagnósticos. |
| `usuarios`, `roles` | Autenticación y control básico de acceso por roles. |

El diagrama y la explicación de las relaciones están en `docs/modelo_relacional.md`.

## Reglas implementadas

- El número de cama se interpreta dentro de una planta; por eso `(planta, cama)` es clave primaria. Al registrar una planta, PHP crea exactamente la cantidad de camas declarada y no se ofrece una operación para añadir camas por encima de esa capacidad.
- Un mismo número de cama puede existir en plantas diferentes.
- Un ingreso tiene como máximo una cama actual. Una cama tiene como máximo un ingreso actual.
- Los cambios de cama se registran cerrando la asignación anterior y creando una nueva; no se sobrescriben los movimientos anteriores.
- El alta se ejecuta mediante `sp_archivar_ingreso`, que copia el ingreso y sus detalles a las tablas históricas y luego elimina las filas operativas, todo dentro de una transacción.
- La tarjeta usa uno de cuatro espacios activos por ingreso. Una columna generada y un índice único garantizan que no existan más de cuatro tarjetas activas; al desactivar una, el espacio se puede reutilizar y el registro anterior permanece guardado.
- El horario de tarjeta se valida en PHP y con una restricción `CHECK` en SQL.
- Las visitas y diagnósticos están relacionados con el ingreso. Si se relaciona un diagnóstico con una visita, una FK compuesta impide asociar una visita de otro ingreso.
- Las operaciones de ingreso, cambio de cama y emisión de tarjetas usan transacciones y bloqueos (`SELECT ... FOR UPDATE`) para evitar carreras entre operaciones concurrentes.

## Seguridad básica

- Consultas preparadas con PDO.
- Contraseñas guardadas con `password_hash()` y verificadas con `password_verify()`.
- Sesión regenerada después del inicio de sesión.
- Tokens CSRF en formularios que modifican información.
- Salida HTML escapada con `htmlspecialchars()`.
- Permisos básicos por rol.
- NSS, DNI y descripciones de diagnósticos cifrados en PHP con AES-256-GCM. Los hashes HMAC permiten comprobar unicidad sin almacenar el valor en texto plano.

### Clave de cifrado

`config/local.php` contiene una clave de desarrollo para que el proyecto funcione tras importarlo. En un entorno propio, genera una clave aleatoria de 32 bytes (64 caracteres hexadecimales) y reemplaza `encryption_key_hex`. Conserva una copia segura de esa clave: si se pierde, los datos cifrados no podrán recuperarse. No cambies la clave mientras existan registros cifrados, salvo que implementes una migración de datos.

No publiques `config/local.php`, no expongas phpMyAdmin y no utilices datos reales de salud en un entorno de desarrollo local. Para producción serían necesarios, entre otros, HTTPS, gestión segura de secretos, copias de seguridad, auditoría, políticas de privacidad y pruebas de seguridad.

## Estructura del proyecto

```text
hospital_general/
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── config/
│   ├── app.php
│   ├── database.php
│   └── local.php
├── docs/
│   └── modelo_relacional.md
├── includes/
│   ├── functions.php
│   ├── header.php
│   └── footer.php
├── pages/
│   ├── inicio.php
│   ├── pacientes.php
│   ├── infraestructura.php
│   ├── ingresos.php
│   ├── tarjetas.php
│   ├── catalogos.php
│   ├── atencion.php
│   ├── reportes.php
│   ├── historico.php
│   └── usuarios.php
├── scripts/seed_demo.php
├── sql/
│   ├── 01_schema.sql
│   ├── 02_procedures.sql
│   └── 03_reportes.sql
├── dashboard.php
├── index.php
└── logout.php
```
- El límite se interpreta como cuatro tarjetas activas por paciente, como indica el requisito funcional del PDF. Al desactivar una tarjeta, su espacio numerado puede reutilizarse; el registro anterior no se elimina.
- La aplicación conserva los datos maestros de pacientes, médicos, diagnósticos y plantas; al dar de alta traslada la estancia y sus detalles clínicos/administrativos.
- El reporte de prevalencia puede filtrarse por fechas y especialidad cuando el diagnóstico se relacionó con una visita médica.
