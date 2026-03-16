// ==================== GESTIÓN DE ESTUDIANTES ====================

const API_ESTUDIANTES = 'api.php';

let estudiantes = [];
let estudianteSeleccionado = null;
let estudianteEnEdicion = null;

let selectEstudiante;
let buscarEstudiante;
let formEstudiante;
let infoEstudiante;
let btnSiguienteEstudiante;

let seccionEstudiantes;
let seccionPlantillas;
let seccionEstimulos;
let seccionAcudiente;

let historialContainer;
let historialPanel;
let historialList;
let historialEmpty;
let btnImprimirHistorialSeleccionados;
let historialRegistrosCache = new Map();

let acudienteLoading;
let planillaImportadaEnSesion = false;
let acudienteFetchToken = 0;

document.addEventListener('DOMContentLoaded', async () => {
  cacheDom();
  bindEvents();
  formEstudiante?.reset();
  restaurarEstudianteSeleccionado();
  mostrarHistorialEstudiante([]);
  await cargarEstudiantes();
  await importarPlanillaAcudientes(false);
});

function cacheDom() {
  selectEstudiante = document.getElementById('selectEstudiante');
  buscarEstudiante = document.getElementById('buscarEstudiante');
  formEstudiante = document.getElementById('formEstudiante');
  infoEstudiante = document.getElementById('infoEstudiante');
  btnSiguienteEstudiante = document.getElementById('btnSiguienteEstudiante');

  seccionEstudiantes = document.getElementById('seccionEstudiantes');
  seccionPlantillas = document.getElementById('seccionPlantillas');
  seccionEstimulos = document.getElementById('seccionEstimulos');
  seccionAcudiente = document.getElementById('seccionAcudiente');
  historialContainer = document.getElementById('historialEstudianteContainer');
  historialPanel = document.getElementById('historialEstudiantePanel');
  historialList = document.getElementById('historialEstudianteLista');
  historialEmpty = document.getElementById('historialEstudianteEmpty');
  btnImprimirHistorialSeleccionados = document.getElementById('btnImprimirHistorialSeleccionados');
  acudienteLoading = document.getElementById('acudienteLoading');
}

function bindEvents() {
  if (formEstudiante) {
    formEstudiante.addEventListener('submit', async (event) => {
      event.preventDefault();
      await procesarFormEstudiante();
    });
  }

  if (selectEstudiante) {
    selectEstudiante.addEventListener('change', seleccionarEstudiante);
  }

  if (buscarEstudiante) {
    buscarEstudiante.addEventListener('input', (event) => {
      llenarSelectEstudiantes(event.target.value);
    });
  }

  if (btnSiguienteEstudiante) {
    btnSiguienteEstudiante.addEventListener('click', avanzarAPlantillas);
  }

  document.getElementById('btnSiguientePlantillas')?.addEventListener('click', avanzarAEstimulos);
  document.getElementById('btnAtrasPlantillas')?.addEventListener('click', regresarAEstudiantes);
  document.getElementById('btnAtrasEstimulos')?.addEventListener('click', regresarAPlantillas);
  document.getElementById('btnSiguienteAcudiente')?.addEventListener('click', avanzarAAcudiente);
  document.getElementById('btnAtrasAcudiente')?.addEventListener('click', regresarAEstimulos);
  document.getElementById('btnGuardarAcudiente')?.addEventListener('click', async () => {
    await guardarAcudiente(true);
  });

  document.getElementById('btnEnviarCorreoAcudiente')?.addEventListener('click', async () => {
    await enviarCorreoAcudiente();
  });
  document.getElementById('btnGuardarRegistro')?.addEventListener('click', async () => {
    await finalizarRegistro();
  });

  document.getElementById('btnVolverInicio')?.addEventListener('click', volverAInicioDesdeAcudiente);
  document.getElementById('btnCancelarEdicion')?.addEventListener('click', cancelarEdicion);
  [
    'acudienteNombre',
    'acudienteParentesco',
    'acudienteTelefono',
    'acudienteCorreo',
    'acudienteDireccion',
    'asuntoNotificacionAcudiente',
    'notificacionAcudienteTexto',
  ].forEach((id) => {
    const input = document.getElementById(id);
    if (!input) {
      return;
    }
    input.addEventListener('input', guardarBorradorAcudienteLocal);
    input.addEventListener('change', guardarBorradorAcudienteLocal);
  });

  document
    .querySelectorAll('#disciplinariasAccordion input[type="checkbox"], #seccionEstimulos input[type="checkbox"]')
    .forEach((checkbox) => {
      checkbox.addEventListener('change', () => {
        generarNotificacionAcudiente(false);
      });
    });

  btnImprimirHistorialSeleccionados?.addEventListener('click', imprimirHistorialSeleccionados);
}

function mostrarCargandoAcudiente(activo) {
  if (!acudienteLoading) {
    return;
  }

  acudienteLoading.classList.toggle('d-none', !activo);
}

async function request(action, method = 'GET', payload = null, query = {}) {
  const params = new URLSearchParams({ action });

  Object.entries(query).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') {
      return;
    }
    params.append(key, String(value));
  });

  const options = {
    method,
    headers: {
      'Content-Type': 'application/json',
    },
  };

  if (payload !== null) {
    options.body = JSON.stringify(payload);
  }

  const response = await fetch(`${API_ESTUDIANTES}?${params.toString()}`, options);
  const body = await response.json().catch(() => ({}));

  if (!response.ok || body.success === false) {
    const message = body.error || `Error HTTP ${response.status}`;
    throw new Error(message);
  }

  return body;
}

async function cargarEstudiantes() {
  try {
    const result = await request('obtenerEstudiantes');
    estudiantes = Array.isArray(result.data) ? result.data : [];

    llenarSelectEstudiantes();
    llenarListaGestion();
    restaurarSeleccionEnInterfaz();
  } catch (error) {
    console.error(error);
    alert(`No se pudieron cargar estudiantes: ${error.message}`);
  }
}

async function cargarHistorialEstudiante() {
  if (!estudianteSeleccionado) {
    mostrarHistorialEstudiante([]);
    return;
  }

  try {
    const result = await request('historialEstudiante', 'GET', null, {
      estudiante_id: Number(estudianteSeleccionado.id),
    });
    const registros = Array.isArray(result.data) ? result.data : [];
    mostrarHistorialEstudiante(registros);
  } catch (error) {
    console.error(error);
    mostrarHistorialEstudiante([]);
  }
}

function mostrarHistorialEstudiante(registros = []) {
  if (!historialContainer || !historialList || !historialEmpty) {
    return;
  }

  historialRegistrosCache.clear();

  if (registros.length === 0) {
    historialContainer.style.display = '';
    historialList.innerHTML = '';
    historialEmpty.classList.remove('d-none');
    actualizarBotonImprimirHistorial();
    return;
  }

  historialContainer.style.display = '';
  historialEmpty.classList.add('d-none');
  historialList.innerHTML = '';

  registros.forEach((registro, index) => {
    const recordKey = registro.id ? String(registro.id) : `registro-${index}`;
    historialRegistrosCache.set(recordKey, registro);

    const item = document.createElement('div');
    item.className = 'list-group-item py-3 border';

    const header = document.createElement('div');
    header.className = 'd-flex justify-content-between align-items-start mb-2 gap-2 flex-wrap';

    const titleWrapper = document.createElement('div');
    titleWrapper.className = 'd-flex align-items-center gap-2';

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'form-check-input historial-select-checkbox';
    checkbox.dataset.recordKey = recordKey;
    checkbox.addEventListener('change', actualizarBotonImprimirHistorial);

    const title = document.createElement('strong');
    title.textContent = `Registro ${registro.id || 'N/D'}`;

    titleWrapper.appendChild(checkbox);
    titleWrapper.appendChild(title);
    header.appendChild(titleWrapper);

    const fecha = document.createElement('span');
    fecha.className = 'text-muted small';
    fecha.textContent = formatearFecha(registro.fecha_registro);
    header.appendChild(fecha);

    item.appendChild(header);

    const docente = document.createElement('p');
    docente.className = 'mb-1 small text-secondary';
    docente.textContent = `Docente responsable: ${registro.docente_nombre || 'Sin registro'}`;
    item.appendChild(docente);

    ['tipo1', 'tipo2', 'tipo3'].forEach((tipo) => {
      const faltas = registro[`faltas_tipo${tipo.replace('tipo', '')}`] || [];
      const detalle = renderDetalleLista(`Faltas tipo ${tipo.replace('tipo', ' ')}`, faltas);
      if (detalle) {
        item.appendChild(detalle);
      }
    });

    const estimulos = registro.estimulos || [];
    const bloqueEstimulos = renderDetalleLista('Estímulos', estimulos);
    if (bloqueEstimulos) {
      item.appendChild(bloqueEstimulos);
    }

    historialList.appendChild(item);
  });

  actualizarBotonImprimirHistorial();
}

function actualizarBotonImprimirHistorial() {
  if (!btnImprimirHistorialSeleccionados) {
    return;
  }

  const seleccionados = document.querySelectorAll(
    '#historialEstudianteLista input.historial-select-checkbox:checked'
  );
  btnImprimirHistorialSeleccionados.disabled = seleccionados.length === 0;
}

function obtenerRegistrosHistorialSeleccionados() {
  const seleccionados = [];
  document
    .querySelectorAll('#historialEstudianteLista input.historial-select-checkbox:checked')
    .forEach((checkbox) => {
      const registro = historialRegistrosCache.get(checkbox.dataset.recordKey);
      if (registro) {
        seleccionados.push(registro);
      }
    });
  return seleccionados;
}

function imprimirHistorialSeleccionados() {
  const registros = obtenerRegistrosHistorialSeleccionados();
  if (registros.length === 0) {
    alert('Selecciona al menos un registro para imprimir.');
    return;
  }

  const estudiante = obtenerEstudianteActual();
  if (!estudiante) {
    alert('Selecciona un estudiante antes de imprimir los registros.');
    return;
  }

  const tipoSections = [
    { prop: 'faltas_tipo1', label: 'Faltas tipo 1' },
    { prop: 'faltas_tipo2', label: 'Faltas tipo 2' },
    { prop: 'faltas_tipo3', label: 'Faltas tipo 3' },
  ];

  const registrosHtml = registros
    .map((registro) => {
      const detalleFaltas = tipoSections
        .map(({ prop, label }) => {
          const faltas = Array.isArray(registro[prop]) ? registro[prop] : [];
          if (faltas.length === 0) {
            return '';
          }

          const items = faltas.map((texto) => `<li>${escapeHtml(texto)}</li>`).join('');
          return `<div class="registro-section">
            <p class="registro-section__title">${label}</p>
            <ul>${items}</ul>
          </div>`;
        })
        .join('');

      const estimulos = Array.isArray(registro.estimulos) ? registro.estimulos : [];
      const estimulosHtml =
        estimulos.length > 0
          ? `<div class="registro-section">
            <p class="registro-section__title">Estímulos</p>
            <ul>${estimulos.map((texto) => `<li>${escapeHtml(texto)}</li>`).join('')}</ul>
          </div>`
          : '';

      return `<section class="registro-card">
        <div class="registro-card__header">
          <strong>Registro ${registro.id || 'N/D'}</strong>
          <span>${escapeHtml(formatearFecha(registro.fecha_registro))}</span>
        </div>
        <p class="text-muted small mb-3">Docente responsable: ${escapeHtml(
          registro.docente_nombre || 'Sin registro'
        )}</p>
        ${detalleFaltas}
        ${estimulosHtml}
      </section>`;
    })
    .join('');

  const nombreEstudiante = `${estudiante.nombre} ${estudiante.apellido}`.trim();
  const matriculaEstudiante = estudiante.numero_matricula || 'N/A';
  const html = `<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Historial disciplinario seleccionado</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 28px; color: #111; }
    h1 { margin-bottom: 8px; }
    .sub { margin-bottom: 24px; color: #4b5563; }
    .registro-card { border: 1px solid #cbd5f5; border-radius: 0.5rem; padding: 16px; margin-bottom: 16px; background: #fff; box-shadow: 0 2px 6px rgba(15,23,42,0.08); }
    .registro-card__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .registro-section { margin-bottom: 12px; }
    .registro-section__title { margin: 0 0 4px; font-weight: 600; color: #0f172a; }
    ul { margin: 0 0 0 16px; padding-left: 0; }
    ul li { margin-bottom: 4px; }
  </style>
</head>
<body>
  <h1>Historial disciplinario</h1>
  <p class="sub">Estudiante: ${escapeHtml(nombreEstudiante || 'Sin nombre')} (Matrícula: ${escapeHtml(
    matriculaEstudiante
  )})</p>
  ${registrosHtml}
</body>
</html>`;

  const ventana = window.open('', '_blank', 'width=900,height=700');
  if (!ventana) {
    alert('El navegador bloqueó la ventana de impresión. Permite ventanas emergentes para continuar.');
    return;
  }

  ventana.document.open();
  ventana.document.write(html);
  ventana.document.close();
  ventana.focus();
  ventana.print();
}

function renderDetalleLista(titulo, items) {
  if (!Array.isArray(items) || items.length === 0) {
    return null;
  }

  const container = document.createElement('div');
  container.className = 'mb-2';

  const titleEl = document.createElement('p');
  titleEl.className = 'mb-1 fw-semibold';
  titleEl.textContent = titulo;
  container.appendChild(titleEl);

  const list = document.createElement('ul');
  list.className = 'mb-0 ps-3';
  items.forEach((texto) => {
    const li = document.createElement('li');
    li.textContent = texto;
    list.appendChild(li);
  });

  container.appendChild(list);
  return container;
}

function formatearFecha(valor) {
  try {
    const fecha = new Date(valor);
    if (Number.isNaN(fecha.getTime())) {
      return 'Fecha desconocida';
    }
    return fecha.toLocaleString('es-CO', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch (_error) {
    return 'Fecha desconocida';
  }
}

function llenarSelectEstudiantes(filtro = '') {
  if (!selectEstudiante) {
    return;
  }

  const textoFiltro = filtro.trim().toLowerCase();
  selectEstudiante.innerHTML = '<option value="">Selecciona un estudiante...</option>';

  estudiantes
    .filter((item) => {
      const texto = `${item.nombre} ${item.apellido} ${item.numero_matricula}`.toLowerCase();
      return texto.includes(textoFiltro);
    })
    .forEach((item) => {
      const option = document.createElement('option');
      option.value = item.id;
      option.textContent = `${item.apellido}, ${item.nombre} (${item.numero_matricula})`;
      selectEstudiante.appendChild(option);
    });

  if (estudianteSeleccionado?.id) {
    selectEstudiante.value = String(estudianteSeleccionado.id);
  }
}

function llenarListaGestion() {
  const contenedor = document.getElementById('listaEstudiantesGestion');
  if (!contenedor) {
    return;
  }

  contenedor.innerHTML = '';

  if (estudiantes.length === 0) {
    contenedor.innerHTML = '<p class="text-muted text-center mb-0">No hay estudiantes registrados.</p>';
    return;
  }

  estudiantes.forEach((item) => {
    const row = document.createElement('div');
    row.className = 'border-bottom py-2 d-flex justify-content-between align-items-center gap-2';

    const info = document.createElement('div');

    const name = document.createElement('strong');
    name.textContent = `${item.nombre} ${item.apellido}`;

    const matricula = document.createElement('small');
    matricula.className = 'd-block text-muted';
    matricula.textContent = `Matrícula: ${item.numero_matricula}`;

    info.appendChild(name);
    info.appendChild(matricula);

    const buttons = document.createElement('div');
    buttons.className = 'btn-group btn-group-sm';

    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'btn btn-warning';
    editBtn.textContent = 'Editar';
    editBtn.addEventListener('click', () => editarEstudiante(item.id));

    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'btn btn-danger';
    deleteBtn.textContent = 'Eliminar';
    deleteBtn.addEventListener('click', () => eliminarEstudiante(item.id));

    buttons.appendChild(editBtn);
    buttons.appendChild(deleteBtn);

    row.appendChild(info);
    row.appendChild(buttons);
    contenedor.appendChild(row);
  });
}

async function procesarFormEstudiante() {
  const nombre = document.getElementById('nombres')?.value.trim() || '';
  const apellido = document.getElementById('apellidos')?.value.trim() || '';
  const matricula = document.getElementById('matricula')?.value.trim() || '';

  if (!nombre || !apellido || !matricula) {
    alert('Completa nombre, apellido y matrícula.');
    return;
  }

  const payload = {
    nombre,
    apellido,
    numero_matricula: matricula,
  };

  const action = estudianteEnEdicion ? 'actualizarEstudiante' : 'agregarEstudiante';
  if (estudianteEnEdicion) {
    payload.id = estudianteEnEdicion;
  }

  try {
    const result = await request(action, 'POST', payload);
    alert(result.message || 'Operación completada.');

    formEstudiante?.reset();
    cancelarEdicion();
    await cargarEstudiantes();
  } catch (error) {
    console.error(error);
    alert(`No se pudo guardar el estudiante: ${error.message}`);
  }
}

function editarEstudiante(id) {
  const estudiante = estudiantes.find((item) => Number(item.id) === Number(id));
  if (!estudiante) {
    alert('El estudiante seleccionado no existe.');
    return;
  }

  document.getElementById('nombres').value = estudiante.nombre;
  document.getElementById('apellidos').value = estudiante.apellido;
  document.getElementById('matricula').value = estudiante.numero_matricula;

  estudianteEnEdicion = estudiante.id;

  document.getElementById('btnActualizarEstudiante')?.classList.remove('d-none');
  document.getElementById('btnCancelarEdicion')?.classList.remove('d-none');
  formEstudiante?.querySelector('button[type="submit"]')?.classList.add('d-none');

  document.getElementById('btnActualizarEstudiante').onclick = async (event) => {
    event.preventDefault();
    await procesarFormEstudiante();
  };
}

function cancelarEdicion() {
  estudianteEnEdicion = null;

  document.getElementById('btnActualizarEstudiante')?.classList.add('d-none');
  document.getElementById('btnCancelarEdicion')?.classList.add('d-none');
  formEstudiante?.querySelector('button[type="submit"]')?.classList.remove('d-none');
}

async function eliminarEstudiante(id) {
  const estudiante = estudiantes.find((item) => Number(item.id) === Number(id));
  const etiquetaEstudiante = estudiante
    ? `${estudiante.nombre} ${estudiante.apellido} (${estudiante.numero_matricula})`
    : 'este estudiante';

  const confirmacion = confirm(`¿Seguro que deseas eliminar a ${etiquetaEstudiante}?`);
  if (!confirmacion) {
    return;
  }

  try {
    const result = await request('eliminarEstudiante', 'POST', { id });
    alert(result.message || 'Estudiante eliminado.');

    if (estudianteSeleccionado?.id === id) {
      estudianteSeleccionado = null;
      sessionStorage.removeItem('estudianteActual');
    }

    await cargarEstudiantes();
  } catch (error) {
    console.error(error);
    alert(`No se pudo eliminar el estudiante: ${error.message}`);
  }
}

function limpiarSeleccionesPlantilla() {
  document
    .querySelectorAll('#disciplinariasAccordion input[type="checkbox"], #seccionEstimulos input[type="checkbox"]')
    .forEach((checkbox) => {
      checkbox.checked = false;
    });

  const listaReporte = document.getElementById('listaReporte');
  if (listaReporte) {
    listaReporte.innerHTML = '';
  }
  const reporte = document.getElementById('reporteGenerado');
  if (reporte) {
    reporte.classList.add('d-none');
  }

  const listaEstimulos = document.getElementById('listaEstimulos');
  if (listaEstimulos) {
    listaEstimulos.innerHTML = '';
  }
  const reporteEstimulos = document.getElementById('reporteEstimulos');
  if (reporteEstimulos) {
    reporteEstimulos.classList.add('d-none');
  }

  localStorage.removeItem('faltasSeleccionadas');
  localStorage.removeItem('estimulosSeleccionados');
}

function seleccionarEstudiante() {
  if (!selectEstudiante) {
    return;
  }

  const prevId = estudianteSeleccionado?.id;
  const id = Number(selectEstudiante.value || 0);

  if (!id) {
    limpiarSeleccionesPlantilla();
    estudianteSeleccionado = null;
    sessionStorage.removeItem('estudianteActual');
    infoEstudiante?.classList.add('d-none');
    mostrarHistorialEstudiante([]);
    return;
  }

  const estudiante = estudiantes.find((item) => Number(item.id) === id);
  if (!estudiante) {
    limpiarSeleccionesPlantilla();
    estudianteSeleccionado = null;
    infoEstudiante?.classList.add('d-none');
    mostrarHistorialEstudiante([]);
    return;
  }

  if (prevId && Number(prevId) !== Number(estudiante.id)) {
    limpiarSeleccionesPlantilla();
  }

  estudianteSeleccionado = estudiante;
  sessionStorage.setItem('estudianteActual', JSON.stringify(estudiante));

  document.getElementById('nombreEstudianteSeleccionado').textContent = `${estudiante.nombre} ${estudiante.apellido}`;
  document.getElementById('matriculaEstudianteSeleccionado').textContent = estudiante.numero_matricula;
  infoEstudiante?.classList.remove('d-none');

  cargarHistorialEstudiante();
  cargarAcudiente();
}

function restaurarEstudianteSeleccionado() {
  const raw = sessionStorage.getItem('estudianteActual');
  if (!raw) {
    return;
  }

  try {
    const parsed = JSON.parse(raw);
    if (parsed && parsed.id) {
      estudianteSeleccionado = parsed;
    }
  } catch (_error) {
    sessionStorage.removeItem('estudianteActual');
  }
}

function restaurarSeleccionEnInterfaz() {
  if (!estudianteSeleccionado) {
    return;
  }

  const estudiante = estudiantes.find((item) => Number(item.id) === Number(estudianteSeleccionado.id));
  if (!estudiante) {
    estudianteSeleccionado = null;
    sessionStorage.removeItem('estudianteActual');
    return;
  }

  estudianteSeleccionado = estudiante;

  if (selectEstudiante) {
    selectEstudiante.value = String(estudiante.id);
  }

  document.getElementById('nombreEstudianteSeleccionado').textContent = `${estudiante.nombre} ${estudiante.apellido}`;
  document.getElementById('matriculaEstudianteSeleccionado').textContent = estudiante.numero_matricula;
  infoEstudiante?.classList.remove('d-none');

  cargarHistorialEstudiante();
}

function avanzarAPlantillas() {
  if (!estudianteSeleccionado) {
    alert('Selecciona un estudiante antes de continuar.');
    return;
  }

  seccionEstudiantes?.classList.add('d-none');
  seccionPlantillas?.classList.remove('d-none');
  seccionEstimulos?.classList.add('d-none');
  seccionAcudiente?.classList.add('d-none');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function avanzarAEstimulos() {
  seccionPlantillas?.classList.add('d-none');
  seccionEstimulos?.classList.remove('d-none');
  seccionAcudiente?.classList.add('d-none');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function avanzarAAcudiente() {
  if (!estudianteSeleccionado) {
    alert('Selecciona un estudiante antes de continuar.');
    return;
  }

  seccionEstimulos?.classList.add('d-none');
  seccionAcudiente?.classList.remove('d-none');

  actualizarCabeceraAcudiente();
  await cargarAcudiente();

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function regresarAEstudiantes() {
  seccionPlantillas?.classList.add('d-none');
  seccionEstudiantes?.classList.remove('d-none');
  seccionEstimulos?.classList.add('d-none');
  seccionAcudiente?.classList.add('d-none');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function regresarAPlantillas() {
  seccionEstimulos?.classList.add('d-none');
  seccionPlantillas?.classList.remove('d-none');
  seccionAcudiente?.classList.add('d-none');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function regresarAEstimulos() {
  seccionAcudiente?.classList.add('d-none');
  seccionEstimulos?.classList.remove('d-none');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function obtenerFaltasPorTipo() {
  return {
    tipo1: obtenerSeleccion('#faltasTipo1 input[type="checkbox"]'),
    tipo2: obtenerSeleccion('#faltasTipo2 input[type="checkbox"]'),
    tipo3: obtenerSeleccion('#faltasTipo3 input[type="checkbox"]'),
  };
}

function actualizarCabeceraAcudiente() {
  const nombreEl = document.getElementById('acudienteNombreEstudiante');
  const matriculaEl = document.getElementById('acudienteMatriculaEstudiante');

  if (!estudianteSeleccionado) {
    if (nombreEl) {
      nombreEl.textContent = 'Sin selección';
    }
    if (matriculaEl) {
      matriculaEl.textContent = 'N/A';
    }
    return;
  }

  if (nombreEl) {
    nombreEl.textContent = `${estudianteSeleccionado.nombre} ${estudianteSeleccionado.apellido}`;
  }

  if (matriculaEl) {
    matriculaEl.textContent = estudianteSeleccionado.numero_matricula;
  }
}

function limpiarFormularioAcudiente() {
  document.getElementById('acudienteNombre').value = '';
  document.getElementById('acudienteParentesco').value = '';
  document.getElementById('acudienteTelefono').value = '';
  document.getElementById('acudienteCorreo').value = '';
  document.getElementById('acudienteDireccion').value = '';
}

function llenarFormularioAcudiente(data = null) {
  document.getElementById('acudienteNombre').value = data?.nombre || '';
  document.getElementById('acudienteParentesco').value = data?.parentesco || '';
  document.getElementById('acudienteTelefono').value = data?.telefono || '';
  document.getElementById('acudienteCorreo').value = data?.correo || '';
  document.getElementById('acudienteDireccion').value = data?.direccion || '';
}

function obtenerDatosAcudiente() {
  return {
    nombre: document.getElementById('acudienteNombre')?.value.trim() || '',
    parentesco: document.getElementById('acudienteParentesco')?.value.trim() || '',
    telefono: document.getElementById('acudienteTelefono')?.value.trim() || '',
    correo: document.getElementById('acudienteCorreo')?.value.trim() || '',
    direccion: document.getElementById('acudienteDireccion')?.value.trim() || '',
  };
}

function hayDatosAcudiente(datos) {
  return Object.values(datos).some((value) => value !== '');
}

async function cargarAcudiente() {
  if (!estudianteSeleccionado) {
    llenarFormularioAcudiente();
    restaurarBorradorAcudienteLocal();
    mostrarCargandoAcudiente(false);
    return;
  }

  mostrarCargandoAcudiente(true);
  limpiarFormularioAcudiente();
  const fetchToken = ++acudienteFetchToken;
  try {
    const result = await request('obtenerAcudiente', 'GET', null, {
      estudiante_id: Number(estudianteSeleccionado.id),
    });

    if (fetchToken !== acudienteFetchToken) {
      return;
    }

    llenarFormularioAcudiente(result.data || null);
    const asuntoInput = document.getElementById('asuntoNotificacionAcudiente');
    const notificacionInput = document.getElementById('notificacionAcudienteTexto');
    if (asuntoInput) {
      asuntoInput.value = '';
    }
    if (notificacionInput) {
      notificacionInput.value = '';
    }

    const tieneBorrador = restaurarBorradorAcudienteLocal();
    if (!tieneBorrador) {
      generarNotificacionAcudiente(false);
    }
  } catch (error) {
    console.error(error);
    alert(`No se pudo cargar el perfil del acudiente: ${error.message}`);
  } finally {
    if (fetchToken === acudienteFetchToken) {
      mostrarCargandoAcudiente(false);
    }
  }
}

async function guardarAcudiente(mostrarAlertas = true) {
  if (!estudianteSeleccionado) {
    if (mostrarAlertas) {
      alert('No hay estudiante seleccionado.');
    }
    return null;
  }

  const datos = obtenerDatosAcudiente();
  if (!hayDatosAcudiente(datos)) {
    if (mostrarAlertas) {
      alert('No hay datos del acudiente para guardar.');
    }
    return null;
  }

  if (!datos.nombre) {
    if (mostrarAlertas) {
      alert('El nombre del acudiente es obligatorio para guardar el perfil.');
    }
    return null;
  }

  try {
    const result = await request('guardarAcudiente', 'POST', {
      estudiante_id: Number(estudianteSeleccionado.id),
      ...datos,
    });

    if (mostrarAlertas) {
      alert(result.message || 'Perfil del acudiente guardado.');
    }

    guardarBorradorAcudienteLocal();
    return result;
  } catch (error) {
    console.error(error);
    if (mostrarAlertas) {
      alert(`No se pudo guardar el acudiente: ${error.message}`);
    }
    throw error;
  }
}

function formatearBloqueInforme(titulo, items) {
  if (!items || items.length === 0) {
    return `${titulo}:\n- Sin registros`;
  }

  return `${titulo}:\n${items.map((item) => `- ${item}`).join('\n')}`;
}

function normalizeCompareText(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

function mensajeCorrespondeAEstudianteActual(mensaje) {
  if (!estudianteSeleccionado) {
    return false;
  }

  const texto = normalizeCompareText(mensaje);
  if (!texto) {
    return false;
  }

  const nombreEstudiante = normalizeCompareText(`${estudianteSeleccionado.nombre} ${estudianteSeleccionado.apellido}`);
  const matricula = normalizeCompareText(estudianteSeleccionado.numero_matricula || '');
  const nombreAcudiente = normalizeCompareText(document.getElementById('acudienteNombre')?.value.trim() || '');

  if (!texto.includes(nombreEstudiante) || (matricula && !texto.includes(matricula))) {
    return false;
  }

  // Si hay nombre de acudiente cargado, también debe estar en el contenido.
  if (nombreAcudiente && !texto.includes(nombreAcudiente)) {
    return false;
  }

  return true;
}

function construirTextoNotificacion() {
  const datosAcudiente = obtenerDatosAcudiente();
  const nombreEstudiante = estudianteSeleccionado
    ? `${estudianteSeleccionado.nombre} ${estudianteSeleccionado.apellido}`
    : 'Estudiante sin selección';

  const matricula = estudianteSeleccionado?.numero_matricula || 'N/A';
  const fecha = new Date().toLocaleString('es-CO');
  const faltas = obtenerFaltasPorTipo();
  const estimulos = obtenerSeleccion('#seccionEstimulos input[type="checkbox"]');

  const bloques = [
    formatearBloqueInforme('Faltas tipo 1', faltas.tipo1),
    formatearBloqueInforme('Faltas tipo 2', faltas.tipo2),
    formatearBloqueInforme('Faltas tipo 3', faltas.tipo3),
    formatearBloqueInforme('Estímulos', estimulos),
  ];

  const saludo = datosAcudiente.nombre
    ? `Señor(a) ${datosAcudiente.nombre}${datosAcudiente.parentesco ? ` (${datosAcudiente.parentesco})` : ''},`
    : 'Señor(a) acudiente,';

  return [
    saludo,
    '',
    `Por medio de la presente se comparte el informe del estudiante ${nombreEstudiante} (Matrícula: ${matricula}) con fecha ${fecha}.`,
    '',
    'Resumen del informe:',
    bloques.join('\n\n'),
    '',
    'Agradecemos su acompañamiento y seguimiento del proceso formativo.',
    '',
    'Atentamente,',
    'Docente responsable',
  ].join('\n');
}

function generarNotificacionAcudiente(mostrarAlerta = true) {
  if (!estudianteSeleccionado) {
    alert('Selecciona un estudiante antes de generar la notificación.');
    return;
  }

  const asuntoInput = document.getElementById('asuntoNotificacionAcudiente');
  const notificacionInput = document.getElementById('notificacionAcudienteTexto');

  if (asuntoInput && !asuntoInput.value.trim()) {
    asuntoInput.value = `Informe disciplinario de ${estudianteSeleccionado.nombre} ${estudianteSeleccionado.apellido}`;
  }

  if (notificacionInput) {
    notificacionInput.value = construirTextoNotificacion();
  }

  guardarBorradorAcudienteLocal();

  if (mostrarAlerta) {
    alert('Notificación generada correctamente.');
  }
}

function getClaveBorradorAcudiente() {
  if (!estudianteSeleccionado?.id) {
    return '';
  }

  return `acudienteBorrador_${Number(estudianteSeleccionado.id)}`;
}

function guardarBorradorAcudienteLocal() {
  const key = getClaveBorradorAcudiente();
  if (!key) {
    return;
  }

  const payload = {
    ...obtenerDatosAcudiente(),
    asunto: document.getElementById('asuntoNotificacionAcudiente')?.value.trim() || '',
    mensaje: document.getElementById('notificacionAcudienteTexto')?.value || '',
  };

  sessionStorage.setItem(key, JSON.stringify(payload));
}

function restaurarBorradorAcudienteLocal() {
  const key = getClaveBorradorAcudiente();
  if (!key) {
    return false;
  }

  const raw = sessionStorage.getItem(key);
  if (!raw) {
    return false;
  }

  try {
    const draft = JSON.parse(raw);
    if (!draft || typeof draft !== 'object') {
      return false;
    }

    let restaurado = false;

    if (typeof draft.nombre === 'string') {
      document.getElementById('acudienteNombre').value = draft.nombre;
      restaurado = restaurado || draft.nombre.trim() !== '';
    }
    if (typeof draft.parentesco === 'string') {
      document.getElementById('acudienteParentesco').value = draft.parentesco;
      restaurado = restaurado || draft.parentesco.trim() !== '';
    }
    if (typeof draft.telefono === 'string') {
      document.getElementById('acudienteTelefono').value = draft.telefono;
      restaurado = restaurado || draft.telefono.trim() !== '';
    }
    if (typeof draft.correo === 'string') {
      document.getElementById('acudienteCorreo').value = draft.correo;
      restaurado = restaurado || draft.correo.trim() !== '';
    }
    if (typeof draft.direccion === 'string') {
      document.getElementById('acudienteDireccion').value = draft.direccion;
      restaurado = restaurado || draft.direccion.trim() !== '';
    }
    if (typeof draft.asunto === 'string' && draft.asunto.trim() !== '') {
      document.getElementById('asuntoNotificacionAcudiente').value = draft.asunto;
      restaurado = true;
    }
    if (typeof draft.mensaje === 'string' && draft.mensaje.trim() !== '') {
      document.getElementById('notificacionAcudienteTexto').value = draft.mensaje;
      restaurado = true;
    }

    return restaurado;
  } catch (_error) {
    sessionStorage.removeItem(key);
    return false;
  }
}

function limpiarBorradorAcudienteLocal(estudianteId) {
  if (!estudianteId) {
    return;
  }
  sessionStorage.removeItem(`acudienteBorrador_${Number(estudianteId)}`);
}

async function enviarCorreoAcudiente() {
  if (!estudianteSeleccionado) {
    alert('Selecciona un estudiante antes de enviar el correo.');
    return;
  }

  const correo = document.getElementById('acudienteCorreo')?.value.trim() || '';

  if (!correo) {
    alert('Debes registrar el correo del acudiente.');
    return;
  }

  const asuntoInput = document.getElementById('asuntoNotificacionAcudiente');
  const mensajeInput = document.getElementById('notificacionAcudienteTexto');
  if ((asuntoInput && !asuntoInput.value.trim()) || (mensajeInput && !mensajeInput.value.trim())) {
    generarNotificacionAcudiente(false);
  }

  const asunto = asuntoInput?.value.trim() || '';
  let mensaje = mensajeInput?.value.trim() || '';

  if (!asunto || !mensaje) {
    alert('No se pudo construir el asunto o mensaje de notificación.');
    return;
  }

  if (!mensajeCorrespondeAEstudianteActual(mensaje)) {
    generarNotificacionAcudiente(false);
    mensaje = mensajeInput?.value.trim() || '';

    if (!mensaje || !mensajeCorrespondeAEstudianteActual(mensaje)) {
      alert('El contenido no coincide con el estudiante actual. Se regeneró la notificación, revisa y vuelve a enviar.');
      return;
    }
  }

  try {
    await guardarAcudiente(false);

    const result = await request('enviarCorreoAcudiente', 'POST', {
      estudiante_id: Number(estudianteSeleccionado.id),
      correo,
      asunto,
      mensaje,
    });

    guardarBorradorAcudienteLocal();
    alert(result.message || 'Correo enviado correctamente.');
  } catch (error) {
    console.error(error);
    alert(`No se pudo enviar el correo: ${error.message}`);
  }
}

async function importarPlanillaAcudientes(forzar) {
  if (planillaImportadaEnSesion && !forzar) {
    return;
  }

  try {
    const result = await request('importarPlanillaAcudientes', 'POST', {
      forzar: Boolean(forzar),
    });

    planillaImportadaEnSesion = true;

    if (forzar) {
      const resumen = [
        `Filas procesadas: ${result.total || 0}`,
        `Acudientes guardados: ${result.guardados || 0}`,
        `Sin estudiante relacionado: ${result.sin_estudiante || 0}`,
      ].join('\n');
      alert(`Importación completada.\n${resumen}`);
    }
  } catch (error) {
    if (forzar) {
      alert(`No se pudo importar la planilla: ${error.message}`);
    }
    console.warn('No se pudo importar la planilla de acudientes:', error.message);
  }
}

async function guardarNotificacionAcudiente(registroId) {
  const asunto = document.getElementById('asuntoNotificacionAcudiente')?.value.trim() || '';
  const mensaje = document.getElementById('notificacionAcudienteTexto')?.value.trim() || '';
  const correo = document.getElementById('acudienteCorreo')?.value.trim() || '';

  if (!estudianteSeleccionado || !asunto || !mensaje) {
    return;
  }

  try {
    await request('guardarNotificacionAcudiente', 'POST', {
      registro_id: registroId,
      estudiante_id: Number(estudianteSeleccionado.id),
      asunto,
      mensaje,
      correo,
    });
  } catch (error) {
    console.warn('No se pudo guardar la notificación del acudiente:', error.message);
  }
}

async function finalizarRegistro() {
  if (!estudianteSeleccionado) {
    alert('No hay un estudiante seleccionado para guardar el registro.');
    return;
  }

  const docenteRaw = sessionStorage.getItem('docente');
  let docenteId = 0;

  if (docenteRaw) {
    try {
      const docente = JSON.parse(docenteRaw);
      docenteId = Number(docente.id || 0);
    } catch (_error) {
      docenteId = 0;
    }
  }

  const payload = {
    estudiante_id: Number(estudianteSeleccionado.id),
    docente_id: docenteId,
    faltas: obtenerFaltasPorTipo(),
    estimulos: obtenerSeleccion('#seccionEstimulos input[type="checkbox"]'),
  };

  try {
    const estudianteIdFinal = Number(estudianteSeleccionado.id);
    await guardarAcudiente(false);

    const result = await request('guardarRegistro', 'POST', payload);
    await guardarNotificacionAcudiente(result.id);

    alert(`${result.message || 'Registro guardado'} ID: ${result.id}`);

    document.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
      checkbox.checked = false;
    });

    estudianteSeleccionado = null;
    sessionStorage.removeItem('estudianteActual');
    limpiarBorradorAcudienteLocal(estudianteIdFinal);

    if (selectEstudiante) {
      selectEstudiante.value = '';
    }

    infoEstudiante?.classList.add('d-none');
    seccionAcudiente?.classList.add('d-none');
    seccionEstimulos?.classList.add('d-none');
    seccionPlantillas?.classList.add('d-none');
    seccionEstudiantes?.classList.remove('d-none');

    llenarFormularioAcudiente();
    document.getElementById('asuntoNotificacionAcudiente').value = 'Informe disciplinario del estudiante';
    document.getElementById('notificacionAcudienteTexto').value = '';

    window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch (error) {
    console.error(error);
    alert(`No se pudo guardar el registro: ${error.message}`);
  }
}

function volverAInicioDesdeAcudiente() {
  seccionAcudiente?.classList.add('d-none');
  seccionEstimulos?.classList.add('d-none');
  seccionPlantillas?.classList.add('d-none');
  seccionEstudiantes?.classList.remove('d-none');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

window.editarEstudiante = editarEstudiante;
window.eliminarEstudiante = eliminarEstudiante;


