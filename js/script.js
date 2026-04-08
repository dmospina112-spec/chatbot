const loginForm = document.getElementById('loginForm');
const registerForm = document.getElementById('registerForm');
const recoverForm = document.getElementById('recoverForm');
const switchToLoginBtn = document.getElementById('switchToLogin');
const switchToRegisterBtn = document.getElementById('switchToRegister');
const switchRecoverToLoginBtn = document.getElementById('switchRecoverToLogin');
const recordarBtn = document.getElementById('recordarBtn');
const consultarPreguntaBtn = document.getElementById('consultarPreguntaBtn');
const mensajeRegistro = document.getElementById('mensajeRegistro');
const mensajeError = document.getElementById('mensajeError');
const mensajeRecuperacion = document.getElementById('mensajeRecuperacion');
const loginSection = document.getElementById('loginSection');
const isLoginPage = Boolean(loginSection);
const recoverSecurityQuestionWrap = document.getElementById('recoverSecurityQuestionWrap');
const recoverSecurityQuestionText = document.getElementById('recoverSecurityQuestionText');
const recoverSecurityFields = document.getElementById('recoverSecurityFields');
const btnCambiarContrasena = document.getElementById('btnCambiarContrasena');

const API_ENDPOINT = 'api.php';
const AUTH_STORAGE_KEY = 'auth_user';
const DOCENTE_STORAGE_KEY = 'docente';
const SESSION_FLAG = 'authed';
const PANEL_PATHS = {
  administrador: 'panel_admin.php',
  docente: 'panel_docente.php',
};
let recoveryQuestionCode = '';

function toggleLoginMode(mode) {
  const showLogin = mode === 'login';
  const showRegister = mode === 'register';
  const showRecover = mode === 'recover';

  loginForm?.classList.toggle('d-none', !showLogin);
  registerForm?.classList.toggle('d-none', !showRegister);
  recoverForm?.classList.toggle('d-none', !showRecover);
  switchToLoginBtn?.classList.toggle('active', showLogin);
  switchToRegisterBtn?.classList.toggle('active', showRegister);
  hideRegisterMessage();
  hideRecoveryMessage();
  mensajeError?.classList.add('d-none');

  if (!showRecover) {
    resetRecoveryForm();
  }
}

function hideRegisterMessage() {
  if (!mensajeRegistro) {
    return;
  }
  mensajeRegistro.classList.add('d-none');
  mensajeRegistro.classList.remove('alert-success', 'alert-warning', 'alert-danger');
}

function showRegisterMessage(text, variant = 'warning') {
  if (!mensajeRegistro) {
    return;
  }
  mensajeRegistro.textContent = text;
  mensajeRegistro.classList.remove('alert-success', 'alert-warning', 'alert-danger', 'd-none');
  mensajeRegistro.classList.add(`alert-${variant}`);
}

function showLoginError(message) {
  if (!mensajeError) {
    return;
  }
  mensajeError.textContent = message || 'Usuario o contraseña incorrectos.';
  mensajeError.classList.remove('d-none');
}

function hideRecoveryMessage() {
  if (!mensajeRecuperacion) {
    return;
  }

  mensajeRecuperacion.textContent = '';
  mensajeRecuperacion.classList.add('d-none');
  mensajeRecuperacion.classList.remove('alert-success', 'alert-warning', 'alert-danger', 'alert-info');
}

function showRecoveryMessage(text, variant = 'info') {
  if (!mensajeRecuperacion) {
    return;
  }

  mensajeRecuperacion.textContent = text;
  mensajeRecuperacion.classList.remove('alert-success', 'alert-warning', 'alert-danger', 'alert-info', 'd-none');
  mensajeRecuperacion.classList.add(`alert-${variant}`);
}

function persistSession(user) {
  if (!user) {
    return;
  }
  const serialized = JSON.stringify(user);
  sessionStorage.setItem(AUTH_STORAGE_KEY, serialized);
  sessionStorage.setItem(DOCENTE_STORAGE_KEY, serialized);
  sessionStorage.setItem(SESSION_FLAG, '1');
}

function clearPersistedSession() {
  sessionStorage.removeItem(SESSION_FLAG);
  sessionStorage.removeItem(AUTH_STORAGE_KEY);
  sessionStorage.removeItem(DOCENTE_STORAGE_KEY);
}

if (!isLoginPage && window.__panelUser) {
  persistSession(window.__panelUser);
}

function getPanelPath(role) {
  const normalized = (role || 'docente').toLowerCase();
  return PANEL_PATHS[normalized] || PANEL_PATHS.docente;
}

async function postAction(action, payload) {
  const response = await fetch(`${API_ENDPOINT}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  });

  const raw = await response.text();
  let data = null;

  if (raw.trim() !== '') {
    try {
      data = JSON.parse(raw);
    } catch (_error) {
      data = null;
    }
  }

  if (!data || typeof data !== 'object') {
    const fallbackMessage = raw.trim() !== ''
      ? raw.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
      : `Error HTTP ${response.status}`;

    throw new Error(fallbackMessage || 'La API devolvió una respuesta inválida.');
  }

  if (!response.ok || data.success === false) {
    const errorMessage = data.error || data.message || `Error HTTP ${response.status}`;
    throw new Error(errorMessage);
  }

  return data;
}

function normalizeRegisterUsername(value) {
  return value.trim().toLowerCase().replace(/\s+/g, '');
}

function getEmailPattern() {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
}

function getUsernamePattern() {
  return /^[a-z0-9._-]{4,30}$/;
}

function clearRecoveryVerificationState() {
  recoveryQuestionCode = '';

  if (recoverSecurityQuestionText) {
    recoverSecurityQuestionText.textContent = '';
  }

  recoverSecurityQuestionWrap?.classList.add('d-none');
  recoverSecurityFields?.classList.add('d-none');
  btnCambiarContrasena?.classList.add('d-none');

  ['recuperarRespuestaSeguridad', 'recuperarContrasenaNueva', 'recuperarContrasenaConfirmacion'].forEach((id) => {
    const input = document.getElementById(id);
    if (input) {
      input.value = '';
    }
  });
}

function resetRecoveryForm() {
  recoverForm?.reset();
  clearRecoveryVerificationState();
  hideRecoveryMessage();
}

if (isLoginPage) {
  clearPersistedSession();
}

if (loginForm) {
  loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const usuario = document.getElementById('usuario').value.trim();
    const contrasena = document.getElementById('contrasena').value.trim();

    mensajeError?.classList.add('d-none');

    if (!usuario || !contrasena) {
      showLoginError('Completa usuario y contraseña.');
      return;
    }

    try {
      const result = await postAction('login', { usuario, contrasena });
      persistSession(result.data);
      const target = getPanelPath(result.data?.rol);
      window.location.href = target;
    } catch (error) {
      showLoginError(error.message);
    }
  });
}

if (switchToLoginBtn) {
  switchToLoginBtn.addEventListener('click', () => toggleLoginMode('login'));
}

if (switchToRegisterBtn) {
  switchToRegisterBtn.addEventListener('click', () => toggleLoginMode('register'));
}

if (registerForm) {
  registerForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!mensajeRegistro) {
      return;
    }

    const nombre = document.getElementById('registroNombre').value.trim();
    const apellido = document.getElementById('registroApellido').value.trim();
    const usuario = document.getElementById('registroUsuario').value.trim();
    const correo = document.getElementById('registroCorreo').value.trim();
    const preguntaSeguridad = document.getElementById('registroPreguntaSeguridad').value;
    const respuestaSeguridad = document.getElementById('registroRespuestaSeguridad').value.trim();
    const contrasena = document.getElementById('registroContrasena').value.trim();
    const confirmacion = document.getElementById('registroContrasenaConfirmacion').value.trim();
    const rol = document.getElementById('registroRole').value;

    hideRegisterMessage();

    if (!nombre || !apellido || !usuario || !correo || !preguntaSeguridad || !respuestaSeguridad || !contrasena) {
      showRegisterMessage('Completa todos los campos para crear la cuenta.', 'danger');
      return;
    }

    const usuarioNormalizado = normalizeRegisterUsername(usuario);
    const usuarioPattern = getUsernamePattern();
    if (!usuarioPattern.test(usuarioNormalizado)) {
      showRegisterMessage('El usuario debe tener entre 4 y 30 caracteres y solo puede usar letras, números, punto, guion y guion bajo.', 'danger');
      return;
    }

    const emailPattern = getEmailPattern();
    if (!emailPattern.test(correo)) {
      showRegisterMessage('Ingresa un correo electrónico válido.', 'danger');
      return;
    }

    if (respuestaSeguridad.length < 3) {
      showRegisterMessage('La respuesta de seguridad debe tener al menos 3 caracteres.', 'danger');
      return;
    }

    if (contrasena.length < 8) {
      showRegisterMessage('La contraseña debe tener al menos 8 caracteres.', 'danger');
      return;
    }

    if (contrasena !== confirmacion) {
      showRegisterMessage('La confirmación de la contraseña no coincide.', 'danger');
      return;
    }

    try {
      const response = await postAction('crearDocente', {
        nombre,
        apellido,
        usuario: usuarioNormalizado,
        correo,
        pregunta_seguridad: preguntaSeguridad,
        respuesta_seguridad: respuestaSeguridad,
        contrasena,
        rol,
      });
      registerForm.reset();
      document.getElementById('usuario').value = response.data?.usuario || usuarioNormalizado;
      document.getElementById('contrasena').value = '';
      showRegisterMessage(response.message || 'Cuenta creada correctamente.', 'success');
      setTimeout(() => {
        toggleLoginMode('login');
        document.getElementById('contrasena').focus();
      }, 1200);
    } catch (error) {
      showRegisterMessage(error.message, 'danger');
    }
  });
}

if (recordarBtn) {
  recordarBtn.addEventListener('click', () => {
    resetRecoveryForm();
    toggleLoginMode('recover');
    document.getElementById('recuperarUsuario')?.focus();
  });
}

if (switchRecoverToLoginBtn) {
  switchRecoverToLoginBtn.addEventListener('click', () => {
    toggleLoginMode('login');
  });
}

['recuperarUsuario', 'recuperarCorreo'].forEach((id) => {
  const input = document.getElementById(id);
  input?.addEventListener('input', () => {
    if (recoveryQuestionCode) {
      clearRecoveryVerificationState();
      hideRecoveryMessage();
    }
  });
});

if (consultarPreguntaBtn) {
  consultarPreguntaBtn.addEventListener('click', async () => {
    const usuario = normalizeRegisterUsername(document.getElementById('recuperarUsuario')?.value || '');
    const correo = (document.getElementById('recuperarCorreo')?.value || '').trim().toLowerCase();

    hideRecoveryMessage();
    clearRecoveryVerificationState();

    if (!usuario || !correo) {
      showRecoveryMessage('Primero escribe tu usuario y correo registrado.', 'warning');
      return;
    }

    if (!getUsernamePattern().test(usuario)) {
      showRecoveryMessage('El usuario debe tener entre 4 y 30 caracteres válidos.', 'danger');
      return;
    }

    if (!getEmailPattern().test(correo)) {
      showRecoveryMessage('Ingresa un correo electrónico válido.', 'danger');
      return;
    }

    const defaultText = consultarPreguntaBtn.textContent;
    consultarPreguntaBtn.disabled = true;
    consultarPreguntaBtn.textContent = 'Consultando...';

    try {
      const result = await postAction('consultarPreguntaSeguridad', { usuario, correo });
      recoveryQuestionCode = result.data?.pregunta || '';

      if (recoverSecurityQuestionText) {
        recoverSecurityQuestionText.textContent = result.data?.pregunta_label || 'Pregunta configurada';
      }

      recoverSecurityQuestionWrap?.classList.remove('d-none');
      recoverSecurityFields?.classList.remove('d-none');
      btnCambiarContrasena?.classList.remove('d-none');
      showRecoveryMessage(result.message || 'Responde tu pregunta y define la nueva contraseña.', 'info');
      document.getElementById('recuperarRespuestaSeguridad')?.focus();
    } catch (error) {
      showRecoveryMessage(error.message, 'danger');
    } finally {
      consultarPreguntaBtn.disabled = false;
      consultarPreguntaBtn.textContent = defaultText;
    }
  });
}

if (recoverForm) {
  recoverForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const usuario = normalizeRegisterUsername(document.getElementById('recuperarUsuario')?.value || '');
    const correo = (document.getElementById('recuperarCorreo')?.value || '').trim().toLowerCase();
    const respuestaSeguridad = document.getElementById('recuperarRespuestaSeguridad')?.value.trim() || '';
    const contrasenaNueva = document.getElementById('recuperarContrasenaNueva')?.value.trim() || '';
    const confirmacion = document.getElementById('recuperarContrasenaConfirmacion')?.value.trim() || '';

    hideRecoveryMessage();

    if (!recoveryQuestionCode) {
      showRecoveryMessage('Consulta primero tu pregunta de seguridad.', 'warning');
      return;
    }

    if (!respuestaSeguridad || !contrasenaNueva || !confirmacion) {
      showRecoveryMessage('Completa la respuesta y la nueva contraseña.', 'danger');
      return;
    }

    if (respuestaSeguridad.length < 3) {
      showRecoveryMessage('La respuesta de seguridad debe tener al menos 3 caracteres.', 'danger');
      return;
    }

    if (contrasenaNueva.length < 8) {
      showRecoveryMessage('La nueva contraseña debe tener al menos 8 caracteres.', 'danger');
      return;
    }

    if (contrasenaNueva !== confirmacion) {
      showRecoveryMessage('La confirmación de la nueva contraseña no coincide.', 'danger');
      return;
    }

    try {
      const result = await postAction('recuperarContrasena', {
        usuario,
        correo,
        respuesta_seguridad: respuestaSeguridad,
        contrasena_nueva: contrasenaNueva,
      });

      showRecoveryMessage(result.message || 'Contraseña actualizada correctamente.', 'success');

      setTimeout(() => {
        toggleLoginMode('login');
        document.getElementById('usuario').value = usuario;
        document.getElementById('contrasena').value = '';
        document.getElementById('contrasena')?.focus();
      }, 1200);
    } catch (error) {
      showRecoveryMessage(error.message, 'danger');
    }
  });
}

const ACTIONS = [
  { id: 'btnGenerarReporte', handler: generarReporte },
  { id: 'btnImprimir', handler: () => window.print() },
  { id: 'btnReporteEstimulos', handler: generarReporteEstimulos },
  { id: 'btnImprimirEstimulos', handler: () => window.print() },
  { id: 'btnLogout', handler: cerrarSesion },
];

ACTIONS.forEach(({ id, handler }) => {
  const element = document.getElementById(id);
  if (element) {
    element.addEventListener('click', handler);
  }
});

function obtenerSeleccion(selector) {
  return Array.from(document.querySelectorAll(selector))
    .filter((checkbox) => checkbox.checked)
    .map((checkbox) => checkbox.nextElementSibling.textContent.trim());
}

function guardarSeleccion() {
  const seleccionadas = obtenerSeleccion('#disciplinariasAccordion input[type="checkbox"]');
  localStorage.setItem('faltasSeleccionadas', JSON.stringify(seleccionadas));
  alert('Selección guardada correctamente.');
}

function generarReporte() {
  if (typeof window.generarReporteDisciplinarioPdf === 'function') {
    window.generarReporteDisciplinarioPdf();
    return;
  }

  const lista = document.getElementById('listaReporte');
  const reporte = document.getElementById('reporteGenerado');
  if (!lista || !reporte) return;

  const seleccionadas = obtenerSeleccion('#disciplinariasAccordion input[type="checkbox"]');
  lista.innerHTML = '';

  if (seleccionadas.length === 0) {
    alert('No hay observaciones seleccionadas.');
    reporte.classList.add('d-none');
    return;
  }

  seleccionadas.forEach((item) => {
    const li = document.createElement('li');
    li.textContent = item;
    lista.appendChild(li);
  });

  reporte.classList.remove('d-none');
}

function generarReporteEstimulos() {
  const reporte = document.getElementById('reporteEstimulos');
  if (typeof window.generarReporteEstimulosPdf === 'function') {
    window.generarReporteEstimulosPdf();
    return;
  }

  if (!reporte) return;

  const seleccionadas = obtenerSeleccion('#seccionEstimulos input[type="checkbox"]');
  reporte.innerHTML = '';

  if (seleccionadas.length === 0) {
    alert('No hay estímulos seleccionados.');
    reporte.classList.add('d-none');
    localStorage.removeItem('estimulosSeleccionados');
    return;
  }

  localStorage.setItem('estimulosSeleccionados', JSON.stringify(seleccionadas));

  const titulo = document.createElement('h5');
  titulo.textContent = 'Estímulos seleccionados:';

  const lista = document.createElement('ul');
  lista.className = 'mb-0';

  seleccionadas.forEach((item) => {
    const li = document.createElement('li');
    li.textContent = item;
    lista.appendChild(li);
  });

  reporte.appendChild(titulo);
  reporte.appendChild(lista);
  reporte.classList.remove('d-none');
}

function cerrarSesion() {
  postAction('logout', {})
    .catch(() => null)
    .finally(() => {
      clearPersistedSession();

      if (!loginSection) {
        window.location.href = 'index.php';
        return;
      }

      const forms = document.querySelectorAll('form');
      forms.forEach((form) => form.reset());
      mensajeError?.classList.add('d-none');
      hideRegisterMessage();
      hideRecoveryMessage();
      clearRecoveryVerificationState();
      toggleLoginMode('login');
    });
}
// Chatbot y UI gestionados por chatbot/chatbot.js
// Se eliminó el código duplicado aquí para evitar conflictos con el módulo del chatbot.
