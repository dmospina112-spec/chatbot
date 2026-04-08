# App Educativa (XAMPP + MySQL)

Proyecto web para registrar observaciones disciplinarias y estímulos por estudiante.

## Estado actual
- CRUD de estudiantes funcional vía `api.php`.
- Login conectado a base de datos (`docentes`).
- Registro disciplinario guardado en MySQL (`registros_disciplinarios`).
- Base de datos nueva: `app_educativa_xampp`.
- `database.sql` migra automáticamente datos desde una BD antigua `app_educativa` si existe.

## Requisitos
- XAMPP (Apache + MySQL)
- phpMyAdmin
- Navegador moderno

## Instalación rápida en XAMPP
1. Copia esta carpeta en `C:\xampp\htdocs\proyecto-educativo`.
2. Inicia Apache y MySQL desde XAMPP.
3. Abre phpMyAdmin: `http://localhost/phpmyadmin`.
4. Ejecuta el archivo `database.sql` completo.
5. Confirma que exista la base `app_educativa_xampp`.
6. Abre la app: `http://localhost/proyecto-educativo/`.

## Credenciales iniciales
- Usuario: `admin`
- Contraseña: `1234`

## Configuración de conexión
La conexión vive en `config.php`.

Valores por defecto:
- `DB_HOST=localhost`
- `DB_PORT=3306`
- `DB_USER=root`
- `DB_PASS=`
- `DB_NAME=app_educativa_xampp`

Si no defines variables de entorno, se usan esos valores automáticamente.
Puedes crear un `.env` usando `.env.example`.

## Envío de correos
El botón de envío al acudiente ahora usa SMTP real configurado desde `.env`.

Variables disponibles:
- `MAIL_FROM`
- `MAIL_FROM_NAME`
- `SMTP_HOST`
- `SMTP_PORT`
- `SMTP_USERNAME`
- `SMTP_PASSWORD`
- `SMTP_ENCRYPTION`
- `SMTP_TIMEOUT`

Ejemplo con Gmail:
- `MAIL_FROM=tu_correo@gmail.com`
- `MAIL_FROM_NAME=App Educativa`
- `SMTP_HOST=smtp.gmail.com`
- `SMTP_PORT=587`
- `SMTP_USERNAME=tu_correo@gmail.com`
- `SMTP_PASSWORD=tu_app_password`
- `SMTP_ENCRYPTION=tls`

Nota:
- Para Gmail debes usar una contraseña de aplicación, no tu contraseña normal.

## Estructura principal
- `index.html`: interfaz principal.
- `api.php`: API backend (login, CRUD estudiantes, guardar registros).
- `config.php`: configuración MySQL.
- `database.sql`: creación de base nueva + migración desde `app_educativa`.
- `js/script.js`: login, reportes y sesión.
- `js/estudiantes.js`: gestión de estudiantes y flujo de registro.
- `verificar.php`: validación técnica de instalación.
- `test.html`: pruebas rápidas de endpoints.

## Endpoints
- `GET api.php?action=test`
- `POST api.php?action=login`
- `GET api.php?action=obtenerEstudiantes`
- `GET api.php?action=obtenerEstudiante&id=1`
- `POST api.php?action=agregarEstudiante`
- `POST api.php?action=actualizarEstudiante`
- `POST api.php?action=eliminarEstudiante`
- `POST api.php?action=guardarRegistro`

## Verificación rápida
- `http://localhost/proyecto-educativo/verificar.php`
- `http://localhost/proyecto-educativo/test.html`

## Notas
- La eliminación de estudiantes es lógica (`activo = 0`).
- El script SQL crea una base nueva y no borra la antigua `app_educativa`.
- Si existen datos en `app_educativa`, se migran a la nueva base al ejecutar `database.sql`.
