<?php header('Content-Type: text/html; charset=UTF-8'); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>App Educativa Docente</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles/styles.css">
</head>
<body>
  <div class="container py-5">
    <header class="text-center mb-5">
      <img src="img/logo.jpg" alt="Logo Institución Educativa" class="img-fluid mb-3" style="max-height: 100px;">
      <h1 class="text-primary">Bienvenido Docente</h1>
      <h2 class="h4 text-primary">Institución Educativa Gilberto Alzate Avendaño</h2>
    </header>
  </div>

  <section id="loginSection" class="login-section py-4">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
          <section class="card p-4 shadow login-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h3 class="text-primary mb-0">Ingreso al Sistema</h3>
            </div>

            <form id="loginForm" novalidate>
              <div class="mb-3">
                <label for="usuario" class="form-label">Usuario</label>
                <input type="text" class="form-control" id="usuario" required>
              </div>
              <div class="mb-3">
                <label for="contrasena" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="contrasena" required>
              </div>

              <div id="mensajeError" class="text-danger mb-3 d-none">
                Usuario o contraseña incorrectos.
              </div>

              <button type="submit" class="btn btn-primary w-100">Ingresar</button>
              <button type="button" class="btn btn-link w-100 mt-2" id="switchToRegister">
                Registrar cuenta
              </button>
            </form>

            <form id="registerForm" class="d-none mt-3" novalidate>
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
                <input type="text" class="form-control" id="registroUsuario" placeholder="Ej. docente1" required>
              </div>
              <div class="mb-3">
                <label for="registroCorreo" class="form-label">Correo electrónico</label>
                <input type="email" class="form-control" id="registroCorreo" placeholder="correo@institucion.edu.co" required>
              </div>
              <div class="mb-3">
                <label for="registroContrasena" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="registroContrasena" placeholder="********" required>
              </div>
              <div class="mb-3">
                <label for="registroRole" class="form-label">Rol</label>
                <select class="form-select" id="registroRole" required>
                  <option value="docente" selected>Docente</option>
                  <option value="administrador">Administrador</option>
                </select>
              </div>

              <button type="submit" class="btn btn-success w-100">Crear cuenta</button>
              <button type="button" class="btn btn-success w-100 mt-2" id="switchToLogin">
                Volver al inicio
              </button>

              <div id="mensajeRegistro" class="alert d-none mt-3" role="alert"></div>
            </form>

            <div class="mt-3 text-center">
              <button type="button" class="btn btn-link p-0" id="recordarBtn">¿Olvidaste tu contraseña?</button>
            </div>

            <div id="mensajeRecuperacion" class="alert alert-success mt-3 d-none">
              Se ha enviado un enlace de recuperación al correo electrónico registrado.
            </div>
          </section>
        </div>
      </div>
    </div>
  </section>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/script.js?v=20260307-5"></script>

</body>
</html>

