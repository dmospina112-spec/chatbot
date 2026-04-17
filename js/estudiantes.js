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
let btnEliminarHistorialSeleccionados;
let historialRegistrosCache = new Map();
let historialRegistros = [];

let acudienteLoading;
let planillaImportadaEnSesion = false;
let acudienteFetchToken = 0;
let workflowButtons = [];
let adminMetricEstudiantes;

const WORKFLOW_STEPS = ['estudiantes', 'plantillas', 'estimulos', 'acudiente'];

document.addEventListener('DOMContentLoaded', async () => {
  cacheDom();
  bindEvents();
  formEstudiante?.reset();
  updateWorkflowUI('estudiantes');
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
  asegurarBotonEliminarHistorial();
  btnEliminarHistorialSeleccionados = document.getElementById('btnEliminarHistorialSeleccionados');
  acudienteLoading = document.getElementById('acudienteLoading');
  workflowButtons = Array.from(document.querySelectorAll('[data-workflow-step]'));
  adminMetricEstudiantes = document.getElementById('adminMetricEstudiantes');
}

function asegurarBotonEliminarHistorial() {
  const botonImprimir = document.getElementById('btnImprimirHistorialSeleccionados');
  if (!botonImprimir) {
    return;
  }

  const contenedorAcciones = botonImprimir.parentElement;
  if (!contenedorAcciones) {
    return;
  }

  contenedorAcciones.classList.add('historial-actions');

  let botonEliminar = document.getElementById('btnEliminarHistorialSeleccionados');
  if (!botonEliminar) {
    botonEliminar = document.createElement('button');
    botonEliminar.type = 'button';
    botonEliminar.id = 'btnEliminarHistorialSeleccionados';
    botonEliminar.disabled = true;
    botonEliminar.title = 'Eliminar del historial disciplinario los registros seleccionados';
    contenedorAcciones.insertBefore(botonEliminar, botonImprimir);
  }

  botonEliminar.className = 'btn btn-sm btn-danger btn-historial-eliminar';
  botonEliminar.textContent = 'Eliminar seleccionados';
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
  document.getElementById('btnEnviarCorreoAcudiente')?.addEventListener('click', async () => {
    await enviarCorreoAcudiente();
  });
  document.getElementById('btnGuardarRegistro')?.addEventListener('click', async () => {
    await finalizarRegistro();
  });

  document.getElementById('btnVolverInicio')?.addEventListener('click', volverAInicioDesdeAcudiente);
  document.getElementById('btnCancelarEdicion')?.addEventListener('click', cancelarEdicion);
  document.getElementById('btnEditarAcudienteDesdeGestion')?.addEventListener('click', editarEstudianteSeleccionadoDesdeAcudiente);
  workflowButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      await goToWorkflowStep(button.dataset.workflowStep || 'estudiantes');
    });
  });
  [
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
  btnEliminarHistorialSeleccionados?.addEventListener('click', eliminarHistorialSeleccionado);
}

function updateStudentMetrics() {
  if (adminMetricEstudiantes) {
    adminMetricEstudiantes.textContent = String(estudiantes.length);
  }
}

function updateWorkflowUI(step = 'estudiantes') {
  const currentIndex = WORKFLOW_STEPS.indexOf(step);

  workflowButtons.forEach((button) => {
    const buttonStep = button.dataset.workflowStep || 'estudiantes';
    const buttonIndex = WORKFLOW_STEPS.indexOf(buttonStep);

    button.classList.toggle('is-active', buttonStep === step);
    button.classList.toggle('is-complete', buttonIndex > -1 && buttonIndex < currentIndex);
    button.setAttribute('aria-current', buttonStep === step ? 'step' : 'false');
  });
}

async function goToWorkflowStep(step) {
  switch (step) {
    case 'estudiantes':
      volverAInicioDesdeAcudiente();
      return;
    case 'plantillas':
      avanzarAPlantillas();
      return;
    case 'estimulos':
      if (!estudianteSeleccionado) {
        alert('Selecciona un estudiante antes de continuar.');
        return;
      }
      seccionEstudiantes?.classList.add('d-none');
      seccionPlantillas?.classList.add('d-none');
      seccionEstimulos?.classList.remove('d-none');
      seccionAcudiente?.classList.add('d-none');
      updateWorkflowUI('estimulos');
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    case 'acudiente':
      await avanzarAAcudiente();
      return;
    default:
      updateWorkflowUI('estudiantes');
  }
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
    updateStudentMetrics();
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
  historialRegistros = Array.isArray(registros) ? [...registros] : [];

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
  if (!btnImprimirHistorialSeleccionados && !btnEliminarHistorialSeleccionados) {
    return;
  }

  const seleccionados = document.querySelectorAll(
    '#historialEstudianteLista input.historial-select-checkbox:checked'
  );
  const disabled = seleccionados.length === 0;
  if (btnImprimirHistorialSeleccionados) {
    btnImprimirHistorialSeleccionados.disabled = disabled;
  }
  if (btnEliminarHistorialSeleccionados) {
    btnEliminarHistorialSeleccionados.disabled = disabled;
  }
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

async function eliminarHistorialSeleccionado() {
  if (!estudianteSeleccionado?.id) {
    alert('Selecciona un estudiante antes de eliminar registros del historial.');
    return;
  }

  const registrosSeleccionados = obtenerRegistrosHistorialSeleccionados().filter(
    (registro) => Number(registro?.id) > 0
  );

  if (registrosSeleccionados.length === 0) {
    alert('Selecciona al menos un registro del historial para eliminar.');
    return;
  }

  const cantidad = registrosSeleccionados.length;
  const confirmacion = confirm(
    cantidad === 1
      ? '¿Seguro que deseas eliminar el registro disciplinario seleccionado?'
      : `¿Seguro que deseas eliminar los ${cantidad} registros disciplinarios seleccionados?`
  );

  if (!confirmacion) {
    return;
  }

  const recordIds = registrosSeleccionados.map((registro) => Number(registro.id));

  try {
    if (btnEliminarHistorialSeleccionados) {
      btnEliminarHistorialSeleccionados.disabled = true;
      btnEliminarHistorialSeleccionados.textContent = 'Eliminando...';
    }

    const result = await request('eliminarRegistrosHistorial', 'POST', {
      estudiante_id: Number(estudianteSeleccionado.id),
      record_ids: recordIds,
    });

    await cargarHistorialEstudiante();
    alert(result.message || 'Registros eliminados correctamente.');
  } catch (error) {
    console.error(error);
    alert(`No se pudieron eliminar los registros seleccionados: ${error.message}`);
  } finally {
    if (btnEliminarHistorialSeleccionados) {
      btnEliminarHistorialSeleccionados.textContent = 'Eliminar seleccionados';
    }
    actualizarBotonImprimirHistorial();
  }
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function obtenerEstudianteActual() {
  return estudianteSeleccionado;
}

function contarObservaciones(items = []) {
  return Array.isArray(items) ? items.length : 0;
}

function contarObservacionesRegistros(registros = [], prop = '') {
  return registros.reduce((total, registro) => {
    const items = Array.isArray(registro[prop]) ? registro[prop] : [];
    return total + items.length;
  }, 0);
}

function construirBloqueListaReporte(titulo, items = [], modifier = '') {
  const className = modifier ? `disciplinary-report-group ${modifier}` : 'disciplinary-report-group';
  const contenido = items.length > 0
    ? `<ul class="disciplinary-report-list">${items.map((texto) => `<li>${escapeHtml(texto)}</li>`).join('')}</ul>`
    : '<p class="disciplinary-report-empty">Sin elementos registrados en esta sección.</p>';

  return `<article class="${className}">
    <h4 class="disciplinary-report-group-title">${escapeHtml(titulo)}</h4>
    ${contenido}
  </article>`;
}

function construirTarjetasMetricas(metrics = []) {
  return metrics
    .map(
      (metric) => `<article class="disciplinary-report-metric">
        <span class="disciplinary-report-meta-label">${escapeHtml(metric.label)}</span>
        <strong class="disciplinary-report-metric-value">${escapeHtml(metric.value)}</strong>
        <span class="disciplinary-report-metric-copy">${escapeHtml(metric.copy)}</span>
      </article>`
    )
    .join('');
}

function construirRegistrosHistorialMarkup(registros = []) {
  if (!Array.isArray(registros) || registros.length === 0) {
    return '<p class="disciplinary-report-empty">No hay registros disciplinarios previos para este estudiante.</p>';
  }

  return registros
    .map((registro, index) => {
      const bloques = [];
      const faltasTipo1 = Array.isArray(registro.faltas_tipo1) ? registro.faltas_tipo1 : [];
      const faltasTipo2 = Array.isArray(registro.faltas_tipo2) ? registro.faltas_tipo2 : [];
      const faltasTipo3 = Array.isArray(registro.faltas_tipo3) ? registro.faltas_tipo3 : [];

      if (faltasTipo1.length > 0) {
        bloques.push(construirBloqueListaReporte('Faltas tipo 1', faltasTipo1));
      }

      if (faltasTipo2.length > 0) {
        bloques.push(construirBloqueListaReporte('Faltas tipo 2', faltasTipo2, 'disciplinary-report-group--warning'));
      }

      if (faltasTipo3.length > 0) {
        bloques.push(construirBloqueListaReporte('Faltas tipo 3', faltasTipo3, 'disciplinary-report-group--danger'));
      }

      const estimulos = Array.isArray(registro.estimulos) ? registro.estimulos : [];
      if (estimulos.length > 0) {
        bloques.push(construirBloqueListaReporte('Estímulos asociados', estimulos, 'disciplinary-report-group--success'));
      }

      if (bloques.length === 0) {
        bloques.push('<p class="disciplinary-report-empty">Este registro no tiene detalles disciplinarios visibles.</p>');
      }

      return `<article class="disciplinary-report-record">
        <div class="disciplinary-report-record-header">
          <div>
            <span class="disciplinary-report-kicker">Registro ${escapeHtml(registro.id || index + 1)}</span>
            <h4 class="disciplinary-report-record-title">Seguimiento disciplinario consolidado</h4>
            <p class="disciplinary-report-record-copy">Docente responsable: ${escapeHtml(registro.docente_nombre || 'Sin registro')}</p>
          </div>
          <span class="disciplinary-report-record-date">${escapeHtml(formatearFecha(registro.fecha_registro))}</span>
        </div>
        <div class="disciplinary-report-group-grid">
          ${bloques.join('')}
        </div>
      </article>`;
    })
    .join('');
}

function construirDatosReporteDisciplinario() {
  const estudiante = obtenerEstudianteActual();
  const faltasActuales = obtenerFaltasPorTipo();
  const estimulosActuales = obtenerSeleccion('#seccionEstimulos input[type="checkbox"]');
  const registros = Array.isArray(historialRegistros) ? [...historialRegistros] : [];
  const docente = [window.__panelUser?.nombre, window.__panelUser?.apellido].filter(Boolean).join(' ') || 'Docente no identificado';

  const observacionesActualesTotal =
    contarObservaciones(faltasActuales.tipo1) +
    contarObservaciones(faltasActuales.tipo2) +
    contarObservaciones(faltasActuales.tipo3);

  const historialTipo1 = contarObservacionesRegistros(registros, 'faltas_tipo1');
  const historialTipo2 = contarObservacionesRegistros(registros, 'faltas_tipo2');
  const historialTipo3 = contarObservacionesRegistros(registros, 'faltas_tipo3');
  const historialEstimulos = contarObservacionesRegistros(registros, 'estimulos');
  const totalTipo1 = contarObservaciones(faltasActuales.tipo1);
  const totalTipo2 = contarObservaciones(faltasActuales.tipo2);
  const totalTipo3 = contarObservaciones(faltasActuales.tipo3);

  return {
    estudiante,
    docente,
    fechaGeneracion: formatearFecha(new Date().toISOString()),
    faltasActuales,
    estimulosActuales,
    registros,
    resumen: {
      registrosPrevios: registros.length,
      observacionesActuales: observacionesActualesTotal,
      seleccionTipo1: totalTipo1,
      seleccionTipo2: totalTipo2,
      seleccionTipo3: totalTipo3,
      faltasTipo1: historialTipo1,
      faltasTipo2: historialTipo2,
      faltasTipo3: historialTipo3,
      estimulos: historialEstimulos,
      ultimoRegistro: registros[0]?.fecha_registro ? formatearFecha(registros[0].fecha_registro) : 'Sin registros previos',
    },
  };
}

function construirMarkupReporteDisciplinario(data) {
  const nombreEstudiante = `${data.estudiante?.nombre || ''} ${data.estudiante?.apellido || ''}`.trim() || 'Estudiante sin nombre';
  const matricula = data.estudiante?.numero_matricula || 'Sin matrícula';

  return `<div class="disciplinary-report-preview">
    <div class="disciplinary-report-watermark" aria-hidden="true">
      <img src="img/Logo.png" alt="">
    </div>

    <header class="disciplinary-report-header">
      <div class="disciplinary-report-brand">
        <img src="img/Logo.png" alt="Logo institucional" class="disciplinary-report-logo">
        <div>
          <span class="disciplinary-report-kicker">Reporte disciplinario PDF</span>
          <h3 class="disciplinary-report-heading">Reporte disciplinario del estudiante</h3>
          <p class="disciplinary-report-copy">Documento institucional con las faltas disciplinarias seleccionadas para el estudiante.</p>
        </div>
      </div>
      <div class="disciplinary-report-meta-card">
        <span class="disciplinary-report-meta-label">Generado por</span>
        <strong class="disciplinary-report-meta-value">${escapeHtml(data.docente)}</strong>
        <p class="disciplinary-report-copy mb-0">Fecha de emisión: ${escapeHtml(data.fechaGeneracion)}</p>
      </div>
    </header>

    <section class="disciplinary-report-student">
      <article class="disciplinary-report-student-card">
        <span class="disciplinary-report-kicker">Estudiante seleccionado</span>
        <h4 class="disciplinary-report-student-name">${escapeHtml(nombreEstudiante)}</h4>
        <p class="disciplinary-report-student-meta mb-0">
          Matrícula: <strong>${escapeHtml(matricula)}</strong><br>
          Fecha del reporte: <strong>${escapeHtml(data.fechaGeneracion)}</strong>
        </p>
      </article>
    </section>

    <section class="disciplinary-report-section">
      <h4 class="disciplinary-report-section-title">Faltas disciplinarias seleccionadas</h4>
      <div class="disciplinary-report-group-grid">
        ${construirBloqueListaReporte('Faltas tipo 1', data.faltasActuales.tipo1)}
        ${construirBloqueListaReporte('Faltas tipo 2', data.faltasActuales.tipo2, 'disciplinary-report-group--warning')}
        ${construirBloqueListaReporte('Faltas tipo 3', data.faltasActuales.tipo3, 'disciplinary-report-group--danger')}
      </div>
    </section>

    <section class="disciplinary-report-section disciplinary-report-footer">
      <div class="disciplinary-report-footer-grid">
        <div class="disciplinary-report-signatures">
          <div class="disciplinary-report-signature">
            <span>Docente responsable</span>
          </div>
          <div class="disciplinary-report-signature">
            <span>Coordinación / convivencia</span>
          </div>
        </div>
      </div>
    </section>
  </div>`;
}

function obtenerRegistrosConEstimulos(registros = []) {
  return Array.isArray(registros)
    ? registros.filter((registro) => Array.isArray(registro.estimulos) && registro.estimulos.length > 0)
    : [];
}

function construirRegistrosEstimulosMarkup(registros = []) {
  const registrosConEstimulos = obtenerRegistrosConEstimulos(registros);
  if (registrosConEstimulos.length === 0) {
    return '<p class="disciplinary-report-empty">No hay registros previos de estímulos para este estudiante.</p>';
  }

  return registrosConEstimulos
    .map((registro, index) => {
      const estimulos = Array.isArray(registro.estimulos) ? registro.estimulos : [];

      return `<article class="disciplinary-report-record">
        <div class="disciplinary-report-record-header">
          <div>
            <span class="disciplinary-report-kicker">Registro ${escapeHtml(registro.id || index + 1)}</span>
            <h4 class="disciplinary-report-record-title">Reconocimientos asociados</h4>
            <p class="disciplinary-report-record-copy">Docente responsable: ${escapeHtml(registro.docente_nombre || 'Sin registro')}</p>
          </div>
          <span class="disciplinary-report-record-date">${escapeHtml(formatearFecha(registro.fecha_registro))}</span>
        </div>
        <div class="disciplinary-report-group-grid">
          ${construirBloqueListaReporte('Estímulos registrados', estimulos, 'disciplinary-report-group--success')}
        </div>
      </article>`;
    })
    .join('');
}

function construirDatosReporteEstimulos() {
  const estudiante = obtenerEstudianteActual();
  const estimulosActuales = obtenerSeleccion('#seccionEstimulos input[type="checkbox"]');
  const registros = Array.isArray(historialRegistros) ? [...historialRegistros] : [];
  const registrosConEstimulos = obtenerRegistrosConEstimulos(registros);
  const docente = [window.__panelUser?.nombre, window.__panelUser?.apellido].filter(Boolean).join(' ') || 'Docente no identificado';

  return {
    estudiante,
    docente,
    fechaGeneracion: formatearFecha(new Date().toISOString()),
    estimulosActuales,
    registros: registrosConEstimulos,
    resumen: {
      estimulosActuales: contarObservaciones(estimulosActuales),
      registrosConEstimulos: registrosConEstimulos.length,
      totalEstimulosHistoricos: contarObservacionesRegistros(registrosConEstimulos, 'estimulos'),
      ultimoRegistro: registrosConEstimulos[0]?.fecha_registro
        ? formatearFecha(registrosConEstimulos[0].fecha_registro)
        : 'Sin registros previos',
    },
  };
}

function construirMarkupReporteEstimulos(data) {
  const nombreEstudiante = `${data.estudiante?.nombre || ''} ${data.estudiante?.apellido || ''}`.trim() || 'Estudiante sin nombre';
  const matricula = data.estudiante?.numero_matricula || 'Sin matrícula';
  const resumenMarkup = construirTarjetasMetricas([
    {
      label: 'Seleccion actual',
      value: String(data.resumen.estimulosActuales),
      copy: 'Estímulos marcados para este reporte.',
    },
    {
      label: 'Historial',
      value: String(data.resumen.totalEstimulosHistoricos),
      copy: `${data.resumen.registrosConEstimulos} registro(s) previos con estímulos.`,
    },
    {
      label: 'Ultimo registro',
      value: data.resumen.ultimoRegistro,
      copy: 'Fecha del último registro con estímulos.',
    },
  ]);

  return `<div class="disciplinary-report-preview">
    <div class="disciplinary-report-watermark" aria-hidden="true">
      <img src="img/Logo.png" alt="">
    </div>

    <header class="disciplinary-report-header">
      <div class="disciplinary-report-brand">
        <img src="img/Logo.png" alt="Logo institucional" class="disciplinary-report-logo">
        <div>
          <span class="disciplinary-report-kicker">Reporte de estímulos PDF</span>
          <h3 class="disciplinary-report-heading">Reporte de estímulos del estudiante</h3>
          <p class="disciplinary-report-copy">Documento institucional con los reconocimientos seleccionados para el estudiante.</p>
        </div>
      </div>
      <div class="disciplinary-report-meta-card">
        <span class="disciplinary-report-meta-label">Generado por</span>
        <strong class="disciplinary-report-meta-value">${escapeHtml(data.docente)}</strong>
        <p class="disciplinary-report-copy mb-0">Fecha de emisión: ${escapeHtml(data.fechaGeneracion)}</p>
      </div>
    </header>

    <section class="disciplinary-report-student">
      <article class="disciplinary-report-student-card">
        <span class="disciplinary-report-kicker">Estudiante seleccionado</span>
        <h4 class="disciplinary-report-student-name">${escapeHtml(nombreEstudiante)}</h4>
        <p class="disciplinary-report-student-meta mb-0">
          Matrícula: <strong>${escapeHtml(matricula)}</strong><br>
          Fecha del reporte: <strong>${escapeHtml(data.fechaGeneracion)}</strong>
        </p>
      </article>
    </section>

    <section class="disciplinary-report-section">
      <h4 class="disciplinary-report-section-title">Resumen del reconocimiento</h4>
      <div class="disciplinary-report-summary-grid">
        ${resumenMarkup}
      </div>
    </section>

    <section class="disciplinary-report-section">
      <h4 class="disciplinary-report-section-title">Estímulos seleccionados</h4>
      <div class="disciplinary-report-group-grid">
        ${construirBloqueListaReporte('Reconocimientos actuales', data.estimulosActuales, 'disciplinary-report-group--success')}
      </div>
    </section>

    <section class="disciplinary-report-section">
      <h4 class="disciplinary-report-section-title">Historial de estímulos</h4>
      <div class="disciplinary-report-records">
        ${construirRegistrosEstimulosMarkup(data.registros)}
      </div>
    </section>

    <section class="disciplinary-report-section disciplinary-report-footer">
      <div class="disciplinary-report-footer-grid">
        <div class="disciplinary-report-signatures">
          <div class="disciplinary-report-signature">
            <span>Docente responsable</span>
          </div>
          <div class="disciplinary-report-signature">
            <span>Coordinación / convivencia</span>
          </div>
        </div>
      </div>
    </section>
  </div>`;
}

function renderizarVistaReporteDisciplinario(data) {
  const reporte = document.getElementById('reporteGenerado');
  const contenedor = document.getElementById('reporteResumenContenido');
  if (!reporte || !contenedor) {
    return;
  }

  contenedor.innerHTML = construirMarkupReporteDisciplinario(data);
  reporte.classList.remove('d-none');
}

function renderizarVistaReporteEstimulos(data) {
  const reporte = document.getElementById('reporteEstimulos');
  if (!reporte) {
    return;
  }

  reporte.innerHTML = construirMarkupReporteEstimulos(data);
  reporte.classList.remove('d-none');
}

function obtenerNombreArchivoReporte(data) {
  const nombreBase = `${data.estudiante?.nombre || ''} ${data.estudiante?.apellido || ''}`.trim() || 'estudiante';
  const seguro = nombreBase
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  return `reporte-disciplinario-${seguro || 'estudiante'}.pdf`;
}

function esperarImagenes(container) {
  const imagenes = Array.from(container.querySelectorAll('img'));
  return Promise.all(
    imagenes.map(
      (img) =>
        new Promise((resolve) => {
          if (img.complete) {
            resolve();
            return;
          }
          img.addEventListener('load', resolve, { once: true });
          img.addEventListener('error', resolve, { once: true });
        })
    )
  );
}

function abrirImpresionRespaldo(markup, options = {}) {
  const title = options.title || 'Reporte disciplinario';
  const ventana = window.open('', '_blank', 'width=980,height=720');
  if (!ventana) {
    alert('No se pudo abrir la vista de impresión del reporte.');
    return false;
  }

  ventana.document.open();
  ventana.document.write(`<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>${escapeHtml(title)}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles/styles.css?v=20260408-22">
</head>
<body style="background:#f8fbff;padding:24px;">
  <div class="disciplinary-pdf-sheet">${markup}</div>
  <script>
    (async function () {
      const waitImages = Array.from(document.images || []).map(
        (img) =>
          new Promise((resolve) => {
            if (img.complete) {
              resolve();
              return;
            }
            img.addEventListener('load', resolve, { once: true });
            img.addEventListener('error', resolve, { once: true });
          })
      );

      if (document.fonts && document.fonts.ready) {
        try {
          await document.fonts.ready;
        } catch (error) {
          console.error(error);
        }
      }

      await Promise.all(waitImages);
      setTimeout(() => {
        window.focus();
        window.print();
      }, 180);
    })();
  <\/script>
</body>
</html>`);
  ventana.document.close();
  return true;
}

async function descargarReporteDisciplinarioPdf(data) {
  const markup = construirMarkupReporteDisciplinario(data);
  const abierto = abrirImpresionRespaldo(markup);

  if (!abierto) {
    throw new Error('No se pudo abrir la vista de impresión del PDF.');
  }
}

async function descargarReporteEstimulosPdf(data) {
  const markup = construirMarkupReporteEstimulos(data);
  const abierto = abrirImpresionRespaldo(markup, {
    title: 'Reporte de estímulos',
  });

  if (!abierto) {
    throw new Error('No se pudo abrir la vista de impresión del PDF.');
  }
}

async function generarReporteDisciplinarioPdf() {
  if (!estudianteSeleccionado) {
    alert('Selecciona un estudiante antes de generar el reporte.');
    return;
  }

  const data = construirDatosReporteDisciplinario();
  const hayInformacion = data.resumen.observacionesActuales > 0;

  if (!hayInformacion) {
    alert('Selecciona al menos una observación disciplinaria para generar el PDF.');
    return;
  }

  const boton = document.getElementById('btnGenerarReporte');
  const textoOriginal = boton?.textContent || 'Generar reporte';
  if (boton) {
    boton.disabled = true;
    boton.textContent = 'Abriendo PDF...';
  }

  try {
    renderizarVistaReporteDisciplinario(data);
    await descargarReporteDisciplinarioPdf(data);
    document.getElementById('reporteGenerado')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } finally {
    if (boton) {
      boton.disabled = false;
      boton.textContent = textoOriginal;
    }
  }
}

async function generarReporteEstimulosPdf() {
  if (!estudianteSeleccionado) {
    alert('Selecciona un estudiante antes de generar el reporte.');
    return;
  }

  const data = construirDatosReporteEstimulos();
  const hayInformacion = data.resumen.estimulosActuales > 0;

  if (!hayInformacion) {
    alert('Selecciona al menos un estímulo para generar el PDF.');
    return;
  }

  const boton = document.getElementById('btnReporteEstimulos');
  const textoOriginal = boton?.textContent || 'Generar reporte';
  if (boton) {
    boton.disabled = true;
    boton.textContent = 'Abriendo PDF...';
  }

  try {
    renderizarVistaReporteEstimulos(data);
    await descargarReporteEstimulosPdf(data);
    document.getElementById('reporteEstimulos')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } finally {
    if (boton) {
      boton.disabled = false;
      boton.textContent = textoOriginal;
    }
  }
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
    row.className = 'student-manage-row';

    const identity = document.createElement('div');
    identity.className = 'student-manage-identity';

    const avatar = document.createElement('div');
    avatar.className = 'student-manage-avatar';
    avatar.textContent = `${item.nombre || ''} ${item.apellido || ''}`
      .trim()
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part.charAt(0).toUpperCase())
      .join('');

    const info = document.createElement('div');
    info.className = 'student-manage-info';

    const name = document.createElement('strong');
    name.textContent = `${item.nombre} ${item.apellido}`;

    const matricula = document.createElement('small');
    matricula.className = 'd-block text-muted';
    matricula.textContent = `Matrícula: ${item.numero_matricula}`;

    info.appendChild(name);
    info.appendChild(matricula);
    identity.appendChild(avatar);
    identity.appendChild(info);

    const buttons = document.createElement('div');
    buttons.className = 'student-manage-actions';

    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'btn btn-sm btn-outline-primary';
    editBtn.textContent = 'Editar';
    editBtn.addEventListener('click', () => editarEstudiante(item.id));

    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'btn btn-sm btn-outline-danger';
    deleteBtn.textContent = 'Eliminar';
    deleteBtn.addEventListener('click', () => eliminarEstudiante(item.id));

    buttons.appendChild(editBtn);
    buttons.appendChild(deleteBtn);

    row.appendChild(identity);
    row.appendChild(buttons);
    contenedor.appendChild(row);
  });
}

function obtenerDatosAcudienteGestion() {
  return {
    nombre: document.getElementById('gestionAcudienteNombre')?.value.trim() || '',
    apellido: document.getElementById('gestionAcudienteApellido')?.value.trim() || '',
    parentesco: document.getElementById('gestionAcudienteParentesco')?.value.trim() || '',
    telefono: document.getElementById('gestionAcudienteTelefono')?.value.trim() || '',
    correo: document.getElementById('gestionAcudienteCorreo')?.value.trim() || '',
    direccion: document.getElementById('gestionAcudienteDireccion')?.value.trim() || '',
  };
}

function llenarFormularioAcudienteGestion(data = null) {
  document.getElementById('gestionAcudienteNombre').value = data?.nombre || '';
  document.getElementById('gestionAcudienteApellido').value = data?.apellido || '';
  document.getElementById('gestionAcudienteParentesco').value = data?.parentesco || '';
  document.getElementById('gestionAcudienteTelefono').value = data?.telefono || '';
  document.getElementById('gestionAcudienteCorreo').value = data?.correo || '';
  document.getElementById('gestionAcudienteDireccion').value = data?.direccion || '';
}

async function cargarAcudienteGestionEdicion(estudianteId) {
  if (!estudianteId) {
    llenarFormularioAcudienteGestion();
    return;
  }

  try {
    const result = await request('obtenerAcudiente', 'GET', null, {
      estudiante_id: Number(estudianteId),
    });

    if (Number(estudianteEnEdicion) !== Number(estudianteId)) {
      return;
    }

    llenarFormularioAcudienteGestion(result.data || null);
    const estudiante = estudiantes.find((item) => Number(item.id) === Number(estudianteId));
    if (estudiante) {
      estudiante.acudiente = result.data || null;
    }
  } catch (error) {
    console.warn('No se pudo cargar el acudiente para edición:', error.message);
  }
}

async function procesarFormEstudiante() {
  const nombre = document.getElementById('nombres')?.value.trim() || '';
  const apellido = document.getElementById('apellidos')?.value.trim() || '';
  const matricula = document.getElementById('matricula')?.value.trim() || '';
  const acudiente = obtenerDatosAcudienteGestion();

  if (!nombre || !apellido || !matricula) {
    alert('Completa nombre, apellido y matrícula.');
    return;
  }

  if (!acudiente.nombre || !acudiente.apellido) {
    alert('Completa nombre y apellido del acudiente en la ficha del estudiante.');
    return;
  }

  const payload = {
    nombre,
    apellido,
    numero_matricula: matricula,
    acudiente,
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
  llenarFormularioAcudienteGestion(estudiante.acudiente || null);

  estudianteEnEdicion = estudiante.id;

  document.getElementById('btnActualizarEstudiante')?.classList.remove('d-none');
  document.getElementById('btnCancelarEdicion')?.classList.remove('d-none');
  formEstudiante?.querySelector('button[type="submit"]')?.classList.add('d-none');

  document.getElementById('btnActualizarEstudiante').onclick = async (event) => {
    event.preventDefault();
    await procesarFormEstudiante();
  };

  void cargarAcudienteGestionEdicion(estudiante.id);
}

function cancelarEdicion() {
  estudianteEnEdicion = null;
  formEstudiante?.reset();
  llenarFormularioAcudienteGestion();

  document.getElementById('btnActualizarEstudiante')?.classList.add('d-none');
  document.getElementById('btnCancelarEdicion')?.classList.add('d-none');
  formEstudiante?.querySelector('button[type="submit"]')?.classList.remove('d-none');
}

function editarEstudianteSeleccionadoDesdeAcudiente() {
  if (!estudianteSeleccionado?.id) {
    alert('Selecciona un estudiante antes de editar su acudiente.');
    return;
  }

  volverAInicioDesdeAcudiente();
  editarEstudiante(estudianteSeleccionado.id);
  formEstudiante?.scrollIntoView({ behavior: 'smooth', block: 'start' });
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

  const resumenReporte = document.getElementById('reporteResumenContenido');
  if (resumenReporte) {
    resumenReporte.innerHTML = '';
  }
  const reporte = document.getElementById('reporteGenerado');
  if (reporte) {
    reporte.classList.add('d-none');
  }

  const reporteEstimulos = document.getElementById('reporteEstimulos');
  if (reporteEstimulos) {
    reporteEstimulos.innerHTML = '';
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
  updateWorkflowUI('plantillas');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function avanzarAEstimulos() {
  seccionPlantillas?.classList.add('d-none');
  seccionEstimulos?.classList.remove('d-none');
  seccionAcudiente?.classList.add('d-none');
  updateWorkflowUI('estimulos');
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

  updateWorkflowUI('acudiente');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function regresarAEstudiantes() {
  seccionPlantillas?.classList.add('d-none');
  seccionEstudiantes?.classList.remove('d-none');
  seccionEstimulos?.classList.add('d-none');
  seccionAcudiente?.classList.add('d-none');
  updateWorkflowUI('estudiantes');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function regresarAPlantillas() {
  seccionEstimulos?.classList.add('d-none');
  seccionPlantillas?.classList.remove('d-none');
  seccionAcudiente?.classList.add('d-none');
  updateWorkflowUI('plantillas');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function regresarAEstimulos() {
  seccionAcudiente?.classList.add('d-none');
  seccionEstimulos?.classList.remove('d-none');
  updateWorkflowUI('estimulos');
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

function actualizarEstadoAcudiente(tipo = '', mensaje = '', mostrarEdicion = false) {
  const estado = document.getElementById('acudienteEstado');
  const boton = document.getElementById('btnEditarAcudienteDesdeGestion');

  if (estado) {
    estado.className = mensaje ? `alert alert-${tipo} mt-3` : 'alert d-none';
    estado.textContent = mensaje;
    estado.classList.toggle('d-none', !mensaje);
  }

  if (boton) {
    boton.classList.toggle('d-none', !mostrarEdicion);
  }
}

function limpiarFormularioAcudiente() {
  document.getElementById('acudienteNombre').value = '';
  document.getElementById('acudienteApellido').value = '';
  document.getElementById('acudienteParentesco').value = '';
  document.getElementById('acudienteTelefono').value = '';
  document.getElementById('acudienteCorreo').value = '';
  document.getElementById('acudienteDireccion').value = '';
}

function llenarFormularioAcudiente(data = null) {
  document.getElementById('acudienteNombre').value = data?.nombre || '';
  document.getElementById('acudienteApellido').value = data?.apellido || '';
  document.getElementById('acudienteParentesco').value = data?.parentesco || '';
  document.getElementById('acudienteTelefono').value = data?.telefono || '';
  document.getElementById('acudienteCorreo').value = data?.correo || '';
  document.getElementById('acudienteDireccion').value = data?.direccion || '';
}

function obtenerDatosAcudiente() {
  return {
    nombre: document.getElementById('acudienteNombre')?.value.trim() || '',
    apellido: document.getElementById('acudienteApellido')?.value.trim() || '',
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
    actualizarEstadoAcudiente();
    mostrarCargandoAcudiente(false);
    return;
  }

  actualizarEstadoAcudiente('info', 'Buscando el acudiente asociado a este estudiante...');
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
    if (result.data) {
      const mensajeBase = result.hint
        ? 'Se cargó el acudiente desde la base anterior para este estudiante.'
        : 'El acudiente se cargó automáticamente desde la ficha del estudiante.';
      actualizarEstadoAcudiente('success', mensajeBase, false);
    } else {
      actualizarEstadoAcudiente(
        'warning',
        'Este estudiante no tiene acudiente registrado en Gestión de Estudiantes. Complétalo allí para que aparezca automáticamente en esta etapa.',
        true
      );
    }

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
    actualizarEstadoAcudiente(
      'danger',
      `No se pudo cargar el acudiente del estudiante: ${error.message}`,
      true
    );
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

  if (!datos.nombre || !datos.apellido) {
    if (mostrarAlertas) {
      alert('El nombre y apellido del acudiente son obligatorios para guardar el perfil.');
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

    if (result.data) {
      llenarFormularioAcudiente(result.data);
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
  const nombreAcudiente = normalizeCompareText(
    [
      document.getElementById('acudienteNombre')?.value.trim() || '',
      document.getElementById('acudienteApellido')?.value.trim() || '',
    ].join(' ').trim()
  );

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

  const nombreCompletoAcudiente = [datosAcudiente.nombre, datosAcudiente.apellido].filter(Boolean).join(' ');
  const saludo = nombreCompletoAcudiente
    ? `Señor(a) ${nombreCompletoAcudiente}${datosAcudiente.parentesco ? ` (${datosAcudiente.parentesco})` : ''},`
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

  const datosAcudiente = obtenerDatosAcudiente();
  const correo = document.getElementById('acudienteCorreo')?.value.trim() || '';

  if (!datosAcudiente.nombre || !datosAcudiente.apellido) {
    alert('El estudiante no tiene acudiente asociado. Actualízalo desde Gestión de Estudiantes.');
    return;
  }

  if (!correo) {
    alert('El acudiente asociado no tiene correo registrado. Actualízalo desde Gestión de Estudiantes.');
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
    updateWorkflowUI('estudiantes');

    llenarFormularioAcudiente();
    actualizarEstadoAcudiente();
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
  updateWorkflowUI('estudiantes');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

window.editarEstudiante = editarEstudiante;
window.eliminarEstudiante = eliminarEstudiante;
window.generarReporteDisciplinarioPdf = generarReporteDisciplinarioPdf;
window.generarReporteEstimulosPdf = generarReporteEstimulosPdf;


