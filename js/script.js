const loginForm = document.getElementById('loginForm');
const registerForm = document.getElementById('registerForm');
const switchToLoginBtn = document.getElementById('switchToLogin');
const switchToRegisterBtn = document.getElementById('switchToRegister');
const mensajeRegistro = document.getElementById('mensajeRegistro');
const mensajeError = document.getElementById('mensajeError');
const loginSection = document.getElementById('loginSection');
const isLoginPage = Boolean(loginSection);

const API_ENDPOINT = 'api.php';
const AUTH_STORAGE_KEY = 'auth_user';
const DOCENTE_STORAGE_KEY = 'docente';
const SESSION_FLAG = 'authed';
const PANEL_PATHS = {
  administrador: 'panel_admin.php',
  docente: 'panel_docente.php',
};

function toggleLoginMode(mode) {
  const showLogin = mode === 'login';
  loginForm?.classList.toggle('d-none', !showLogin);
  registerForm?.classList.toggle('d-none', showLogin);
  switchToLoginBtn?.classList.toggle('active', showLogin);
  switchToRegisterBtn?.classList.toggle('active', !showLogin);
  hideRegisterMessage();
  mensajeError?.classList.add('d-none');
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

function persistSession(user) {
  if (!user) {
    return;
  }
  const serialized = JSON.stringify(user);
  sessionStorage.setItem(AUTH_STORAGE_KEY, serialized);
  sessionStorage.setItem(DOCENTE_STORAGE_KEY, serialized);
  sessionStorage.setItem(SESSION_FLAG, '1');
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

  const data = await response.json().catch(() => null);

  if (!response.ok || (data && data.success === false)) {
    const errorMessage = data?.error || data?.message || `Error HTTP ${response.status}`;
    throw new Error(errorMessage);
  }

  return data;
}

if (isLoginPage) {
  const stored = sessionStorage.getItem(AUTH_STORAGE_KEY);
  if (stored) {
    try {
      const parsed = JSON.parse(stored);
      const destination = getPanelPath(parsed?.rol);
      if (destination) {
        window.location.href = destination;
      }
    } catch (_error) {
      sessionStorage.removeItem(AUTH_STORAGE_KEY);
    }
  }
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
    const contrasena = document.getElementById('registroContrasena').value.trim();
    const rol = document.getElementById('registroRole').value;

    hideRegisterMessage();

    if (!nombre || !apellido || !usuario || !correo || !contrasena) {
      showRegisterMessage('Completa todos los campos para crear la cuenta.', 'danger');
      return;
    }

    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(correo)) {
      showRegisterMessage('Ingresa un correo electrónico válido.', 'danger');
      return;
    }

    try {
      const response = await postAction('crearDocente', {
        nombre,
        apellido,
        usuario,
        correo,
        contrasena,
        rol,
      });
      registerForm.reset();
      showRegisterMessage(response.message || 'Cuenta creada correctamente.', 'success');
    } catch (error) {
      showRegisterMessage(error.message, 'danger');
    }
  });
}

const recordarBtn = document.getElementById('recordarBtn');
const mensajeRecuperacion = document.getElementById('mensajeRecuperacion');

if (recordarBtn) {
  recordarBtn.addEventListener('click', () => {
    if (!mensajeRecuperacion) return;
    mensajeRecuperacion.classList.remove('d-none');
    setTimeout(() => mensajeRecuperacion.classList.add('d-none'), 5000);
  });
}

const ACTIONS = [
  { id: 'btnGuardarSeleccion', handler: guardarSeleccion },
  { id: 'btnGenerarReporte', handler: generarReporte },
  { id: 'btnImprimir', handler: () => window.print() },
  { id: 'btnGuardarEstimulos', handler: guardarEstimulos },
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

function guardarEstimulos() {
  const seleccionadas = obtenerSeleccion('#estimulos input[type="checkbox"]');
  localStorage.setItem('estimulosSeleccionados', JSON.stringify(seleccionadas));
  alert('Estímulos guardados correctamente.');
}

function generarReporteEstimulos() {
  const lista = document.getElementById('listaEstimulos');
  const reporte = document.getElementById('reporteEstimulos');
  if (!lista || !reporte) return;

  const seleccionadas = obtenerSeleccion('#estimulos input[type="checkbox"]');
  lista.innerHTML = '';

  if (seleccionadas.length === 0) {
    alert('No hay estímulos seleccionados.');
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

function cerrarSesion() {
  sessionStorage.removeItem(SESSION_FLAG);
  sessionStorage.removeItem(AUTH_STORAGE_KEY);
  sessionStorage.removeItem(DOCENTE_STORAGE_KEY);

  if (!loginSection) {
    window.location.href = 'index.php';
    return;
  }

  const forms = document.querySelectorAll('form');
  forms.forEach((form) => form.reset());
  mensajeError?.classList.add('d-none');
  hideRegisterMessage();
  toggleLoginMode('login');
}
// Chatbot y UI gestionados por chatbot/chatbot.js
// Se eliminó el código duplicado aquí para evitar conflictos con el módulo del chatbot.
