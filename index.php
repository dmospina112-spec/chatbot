<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authUser = $_SESSION['auth_user'] ?? null;
if (is_array($authUser)) {
    $role = strtolower((string) ($authUser['rol'] ?? 'docente'));
    $target = $role === 'administrador' ? 'panel_admin.php' : 'panel_docente.php';
    header('Location: ' . $target);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$stylesVersion = (string) filemtime(__DIR__ . '/styles/styles.css');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>App Educativa Docente</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles/styles.css?v=<?php echo htmlspecialchars($stylesVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="site-body login-page">
  <main class="login-shell container-xxl">
    <section class="login-hero">
      <div class="hero-brand">
        <img src="img/Logo.png" alt="Escudo institucional" class="hero-logo">
        <div class="hero-brand-copy">
          <span class="hero-kicker">Plataforma docente</span>
          <span class="hero-motto">Ciencia, amor y virtud</span>
        </div>
      </div>

      <div class="hero-copy">
        <span class="hero-badge">Acceso institucional seguro</span>
        <h1 class="hero-title">Bienvenido Docente</h1>
        <p class="hero-subtitle">
          Gestiona observaciones, reportes y seguimiento estudiantil desde un entorno más claro,
          ordenado y alineado con la identidad de la institución.
        </p>
      </div>
    </section>

    <section id="loginSection" class="login-section">
      <section class="card login-card auth-card">
        <div class="auth-card-top">
          <span class="auth-chip">Ingreso al sistema</span>
          <h2 class="auth-title">Accede con tu cuenta</h2>
          <p class="auth-copy">Usa tu usuario institucional para continuar con el panel docente.</p>
        </div>

        <form id="loginForm" class="auth-form" novalidate>
          <div class="mb-3">
            <label for="usuario" class="form-label">Usuario</label>
            <input type="text" class="form-control" id="usuario" autocomplete="username" placeholder="Ingresa tu usuario" required>
          </div>
          <div class="mb-3">
            <label for="contrasena" class="form-label">Contraseña</label>
            <input type="password" class="form-control" id="contrasena" autocomplete="current-password" placeholder="Ingresa tu contraseña" required>
          </div>

          <div id="mensajeError" class="auth-feedback auth-feedback-error d-none">
            Usuario o contraseña incorrectos.
          </div>

          <button type="submit" class="btn btn-primary w-100 auth-submit">Ingresar</button>

          <div class="auth-actions">
            <button type="button" class="btn btn-link auth-switch" id="switchToRegister">
              Registrar cuenta
            </button>
            <button type="button" class="btn btn-link auth-help" id="recordarBtn">¿Olvidaste tu contraseña?</button>
          </div>
        </form>

        <form id="registerForm" class="auth-form d-none" novalidate>
          <div class="auth-subheader">
            <span class="auth-chip auth-chip-muted">Registro docente</span>
            <h3>Crea tu cuenta</h3>
            <p>Completa tus datos para solicitar acceso como docente.</p>
          </div>

          <div class="mb-3">
            <label for="registroNombre" class="form-label">Nombres</label>
            <input type="text" class="form-control" id="registroNombre" placeholder="Nombre(s)" required>
          </div>
          <div class="mb-3">
            <label for="registroApellido" class="form-label">Apellido</label>
            <input type="text" class="form-control" id="registroApellido" placeholder="Apellido" required>
          </div>
          <div class="mb-3">
            <label for="registroUsuario" class="form-label">Usuario</label>
            <input type="text" class="form-control" id="registroUsuario" placeholder="Ej. docente1" autocomplete="username" minlength="4" maxlength="30" required>
          </div>
          <div class="mb-3">
            <label for="registroCorreo" class="form-label">Correo electrónico</label>
            <input type="email" class="form-control" id="registroCorreo" placeholder="correo@institucion.edu.co" autocomplete="email" required>
          </div>
          <div class="mb-3">
            <label for="registroPreguntaSeguridad" class="form-label">Pregunta de seguridad</label>
            <select class="form-select" id="registroPreguntaSeguridad" required>
              <option value="">Selecciona una pregunta</option>
              <option value="mascota">¿Cuál es el nombre de tu primera mascota?</option>
              <option value="escuela">¿Cómo se llamaba tu escuela primaria?</option>
              <option value="madre">¿Cuál es el segundo nombre de tu madre?</option>
              <option value="ciudad">¿En qué ciudad naciste?</option>
              <option value="profesor">¿Cuál fue tu profesor favorito?</option>
              <option value="amigo">¿Cómo se llamaba tu mejor amigo de la infancia?</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="registroRespuestaSeguridad" class="form-label">Respuesta de seguridad</label>
            <input type="text" class="form-control" id="registroRespuestaSeguridad" placeholder="Escribe tu respuesta" autocomplete="off" required>
            <div class="form-text">La usarás para recuperar tu contraseña si la olvidas.</div>
          </div>
          <div class="mb-3">
            <label for="registroContrasena" class="form-label">Contraseña</label>
            <input type="password" class="form-control" id="registroContrasena" placeholder="********" autocomplete="new-password" minlength="8" required>
          </div>
          <div class="mb-3">
            <label for="registroContrasenaConfirmacion" class="form-label">Confirmar contraseña</label>
            <input type="password" class="form-control" id="registroContrasenaConfirmacion" placeholder="********" autocomplete="new-password" minlength="8" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="registroRoleLabel">Rol</label>
            <input type="text" class="form-control" id="registroRoleLabel" value="Docente" readonly>
            <input type="hidden" id="registroRole" value="docente">
          </div>

          <button type="submit" class="btn btn-success w-100 auth-submit">Crear cuenta</button>
          <button type="button" class="btn btn-link auth-switch auth-switch-back" id="switchToLogin">
            Volver al inicio
          </button>

          <div id="mensajeRegistro" class="alert d-none mt-3 auth-feedback" role="alert"></div>
        </form>

        <form id="recoverForm" class="auth-form d-none" novalidate>
          <div class="auth-subheader">
            <span class="auth-chip auth-chip-muted">Recuperación segura</span>
            <h3>Restablece tu contraseña</h3>
            <p>Confirma tu usuario y correo, consulta tu pregunta de seguridad y define una nueva clave.</p>
          </div>

          <div class="mb-3">
            <label for="recuperarUsuario" class="form-label">Usuario</label>
            <input type="text" class="form-control" id="recuperarUsuario" placeholder="Ingresa tu usuario" autocomplete="username" required>
          </div>
          <div class="mb-3">
            <label for="recuperarCorreo" class="form-label">Correo electrónico</label>
            <input type="email" class="form-control" id="recuperarCorreo" placeholder="correo@institucion.edu.co" autocomplete="email" required>
          </div>

          <div class="auth-inline-actions">
            <button type="button" class="btn btn-outline-primary" id="consultarPreguntaBtn">Consultar pregunta</button>
          </div>

          <div id="recoverSecurityQuestionWrap" class="security-question-box d-none mt-3">
            <span class="security-question-label">Pregunta configurada</span>
            <strong id="recoverSecurityQuestionText"></strong>
          </div>

          <div id="recoverSecurityFields" class="d-none">
            <div class="mb-3 mt-3">
              <label for="recuperarRespuestaSeguridad" class="form-label">Respuesta de seguridad</label>
              <input type="text" class="form-control" id="recuperarRespuestaSeguridad" placeholder="Escribe tu respuesta exacta" autocomplete="off">
            </div>
            <div class="mb-3">
              <label for="recuperarContrasenaNueva" class="form-label">Nueva contraseña</label>
              <input type="password" class="form-control" id="recuperarContrasenaNueva" placeholder="********" autocomplete="new-password" minlength="8">
            </div>
            <div class="mb-3">
              <label for="recuperarContrasenaConfirmacion" class="form-label">Confirmar nueva contraseña</label>
              <input type="password" class="form-control" id="recuperarContrasenaConfirmacion" placeholder="********" autocomplete="new-password" minlength="8">
            </div>
          </div>

          <button type="submit" class="btn btn-success w-100 auth-submit d-none" id="btnCambiarContrasena">Cambiar contraseña</button>
          <button type="button" class="btn btn-link auth-switch auth-switch-back" id="switchRecoverToLogin">
            Volver al inicio
          </button>

          <div id="mensajeRecuperacion" class="alert d-none mt-3 auth-feedback" role="alert"></div>
        </form>
      </section>
    </section>
  </main>

  <footer class="site-footer">
    <div class="site-footer-shell">
      <div class="site-footer-brand">
        <img src="img/Logo.png" alt="Logo institucional" class="site-footer-logo">
        <div>
          <strong>Institución Educativa Gilberto Alzate Avendaño</strong>
          <p>Plataforma de seguimiento disciplinario.</p>
        </div>
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/script.js?v=20260408-8"></script>

</body>
</html>
