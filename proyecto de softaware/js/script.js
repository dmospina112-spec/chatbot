const loginForm = document.getElementById('loginForm');
const mensajeError = document.getElementById('mensajeError');
const loginSection = document.getElementById('loginSection');
const appContent = document.getElementById('appContent');

const isAuthenticated = sessionStorage.getItem('authed') === '1';
if (isAuthenticated) {
  mostrarContenido();
}

if (loginForm) {
  loginForm.addEventListener('submit', (e) => {
    e.preventDefault();

    const usuario = document.getElementById('usuario').value.trim();
    const contrasena = document.getElementById('contrasena').value.trim();

    const usuarioValido = 'admin';
    const contrasenaValida = '1234';

    if (usuario === usuarioValido && contrasena === contrasenaValida) {
      mensajeError.classList.add('d-none');
      sessionStorage.setItem('authed', '1');
      mostrarContenido();
      alert('✅ Bienvenido al sistema.');
    } else {
      mensajeError.classList.remove('d-none');
    }
  });
}

function mostrarContenido() {
  if (loginSection) loginSection.classList.add('d-none');
  if (appContent) appContent.classList.remove('d-none');
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
  alert('✅ Selección guardada correctamente.');
}

function generarReporte() {
  const lista = document.getElementById('listaReporte');
  const reporte = document.getElementById('reporteGenerado');
  if (!lista || !reporte) return;

  const seleccionadas = obtenerSeleccion('#disciplinariasAccordion input[type="checkbox"]');
  lista.innerHTML = '';

  if (seleccionadas.length === 0) {
    alert('⚠️ No hay observaciones seleccionadas.');
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
  alert('✅ Estímulos guardados correctamente.');
}

function generarReporteEstimulos() {
  const lista = document.getElementById('listaEstimulos');
  const reporte = document.getElementById('reporteEstimulos');
  if (!lista || !reporte) return;

  const seleccionadas = obtenerSeleccion('#estimulos input[type="checkbox"]');
  lista.innerHTML = '';

  if (seleccionadas.length === 0) {
    alert('⚠️ No hay estímulos seleccionados.');
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
  sessionStorage.removeItem('authed');
  if (appContent) appContent.classList.add('d-none');
  if (loginSection) loginSection.classList.remove('d-none');
  const forms = document.querySelectorAll('form');
  forms.forEach((form) => form.reset());
}
