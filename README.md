# Sistema de Gestión Hospitalaria

Aplicación académica desarrollada con **PHP + HTML + CSS + JavaScript básico + MySQL/MariaDB**, pensada para ejecutarse localmente con XAMPP. La lógica de negocio y la persistencia están en PHP y SQL; la interfaz se mantiene deliberadamente sencilla.

> Utiliza únicamente datos ficticios. Esta aplicación es una base funcional para un trabajo académico, no un sistema clínico listo para producción.

## Requisitos

- XAMPP con Apache, PHP 8.1 o superior y MySQL/MariaDB.
- Extensiones PHP `pdo_mysql` y `openssl` habilitadas.
- Navegador web.
- MySQL 8.x o MariaDB 10.4+ recomendado (se utilizan claves foráneas, restricciones `CHECK` y procedimientos almacenados).
## Instalación en XAMPP



### 1. Clonar el repositorio

Puedes obtener el proyecto clonando el repositorio desde GitHub utilizando tu terminal:

```bash
git clone https://github.com/Victor-Rojas-09/hospital_general.git

```

### 2. Configuración e inicio del sistema

1. Copia o mueve la carpeta `hospital_general` dentro de la ruta `C:\xampp\htdocs\`.
2. Abre el panel de control de XAMPP e inicia los servicios **Apache** y **MySQL**.
3. Ingresa a `http://localhost/phpmyadmin/` en tu navegador.
4. Selecciona la pestaña **Importar** y ejecuta el archivo `sql/01_schema.sql` para crear las tablas y la base de datos.
5. Vuelve a la pestaña **Importar** y ejecuta `sql/02_procedures.sql` para registrar el procedimiento del archivo histórico.
6. El archivo `sql/03_reportes.sql` incluye consultas de referencia; no es indispensable para el funcionamiento inicial de la aplicación.
7. Revisa el archivo `config/local.php` y verifica la configuración de host, nombre de la base de datos, usuario y contraseña de MySQL.
8. Abre `http://localhost/hospital_general/` en el navegador para acceder al sistema.

## Seguridad básica

- Consultas preparadas con PDO.
- Contraseñas guardadas con `password_hash()` y verificadas con `password_verify()`.
- Sesión regenerada después del inicio de sesión.
- Tokens CSRF en formularios que modifican información.
- Salida HTML escapada con `htmlspecialchars()`.
- Permisos básicos por rol.
- NSS, DNI y descripciones de diagnósticos cifrados en PHP con AES-256-GCM. Los hashes HMAC permiten comprobar unicidad sin almacenar el valor en texto plano.

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

## Observaciones de alcance

- Para mantener el proyecto manejable, cada planta se registra con su capacidad y el sistema crea sus camas correlativas automáticamente. No se incluye una pantalla para borrar camas; evita dejar referencias huérfanas en el historial.
- El límite se interpreta como cuatro tarjetas activas por paciente, como indica el requisito funcional del PDF. Al desactivar una tarjeta, su espacio numerado puede reutilizarse; el registro anterior no se elimina.
- La aplicación conserva los datos maestros de pacientes, médicos, diagnósticos y plantas; al dar de alta traslada la estancia y sus detalles clínicos/administrativos.
- El reporte de prevalencia puede filtrarse por fechas y especialidad cuando el diagnóstico se relacionó con una visita médica.
