  <div id="appContent">
    <div class="container my-4 workspace-shell">
      <div class="workspace-toolbar">
        <div class="workspace-toolbar-copy">
          <span class="workspace-kicker">Ruta académica</span>
          <h2 class="workspace-title">Seguimiento disciplinario y acompañamiento</h2>
          <p class="workspace-copy">
            Selecciona el estudiante, construye el reporte y consolida la comunicación con el acudiente
            en un flujo visual más claro y continuo.
          </p>
        </div>
        <button type="button" class="btn btn-outline-danger btn-sm" id="btnLogout">Cerrar sesión</button>
      </div>

      <div class="workflow-stage-bar" id="workflowStageBar" aria-label="Etapas del flujo">
        <button type="button" class="workflow-stage is-active" data-workflow-step="estudiantes">
          <span class="workflow-stage-index">1</span>
          <span class="workflow-stage-body">
            <span class="workflow-stage-label">Estudiantes</span>
            <span class="workflow-stage-copy">Selección y gestión</span>
          </span>
        </button>

        <button type="button" class="workflow-stage" data-workflow-step="plantillas">
          <span class="workflow-stage-index">2</span>
          <span class="workflow-stage-body">
            <span class="workflow-stage-label">Plantillas</span>
            <span class="workflow-stage-copy">Observaciones disciplinarias</span>
          </span>
        </button>

        <button type="button" class="workflow-stage" data-workflow-step="estimulos">
          <span class="workflow-stage-index">3</span>
          <span class="workflow-stage-body">
            <span class="workflow-stage-label">Estímulos</span>
            <span class="workflow-stage-copy">Reconocimientos y avance</span>
          </span>
        </button>

        <button type="button" class="workflow-stage" data-workflow-step="acudiente">
          <span class="workflow-stage-index">4</span>
          <span class="workflow-stage-body">
            <span class="workflow-stage-label">Acudiente</span>
            <span class="workflow-stage-copy">Notificación y cierre</span>
          </span>
        </button>
      </div>

      <!-- SECCIÓN 1: SELECCIÓN DE ESTUDIANTE Y GESTIÓN -->
      <section id="seccionEstudiantes" aria-label="Selección y gestión de estudiantes" class="workspace-section">
        <div class="container px-0">
          <div class="row">
            <!-- Columna izquierda: Buscar y seleccionar estudiante -->
            <div class="col-lg-7">
              <div class="card p-4 shadow-sm">
                <h3 class="text-primary mb-4">Seleccionar Estudiante</h3>
                
                <div class="mb-3">
                  <label for="buscarEstudiante" class="form-label fw-bold">Buscar estudiante:</label>
                  <input 
                    type="text" 
                    class="form-control" 
                    id="buscarEstudiante" 
                    placeholder="Escribe nombre, apellido o matrícula..."
                  >
                </div>

                <div class="mb-3">
                  <label for="selectEstudiante" class="form-label fw-bold">Lista de estudiantes:</label>
                  <select class="form-select form-select-lg" id="selectEstudiante">
                    <option value="">Selecciona un estudiante...</option>
                  </select>
                </div>

                <div id="infoEstudiante" class="alert alert-info d-none" role="alert">
                  <strong>Estudiante seleccionado:</strong><br>
                  <span id="nombreEstudianteSeleccionado"></span><br>
                  <small class="text-muted">Matrícula: <span id="matriculaEstudianteSeleccionado"></span></small>
                </div>

                <div id="historialEstudianteContainer" class="mt-3 card workspace-history-card" style="display: none;">
                  <div class="card-body">
                    <details id="historialEstudianteDetalles" class="historial-details" open>
                      <summary aria-controls="historialEstudiantePanel" class="d-flex align-items-center gap-2">
                        <span class="historial-details-title">Historial disciplinario</span>
                        <span class="historial-details-icon fw-bold" aria-hidden="true">&#8594;</span>
                      </summary>
                    <div id="historialEstudiantePanel" class="historial-scroll">
                      <div id="historialEstudianteLista" class="list-group list-group-flush"></div>
                      <p id="historialEstudianteEmpty" class="text-muted small mt-2 mb-0 d-none">
                        No hay registros previos.
                      </p>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-3 historial-actions">
                      <button
                        type="button"
                        class="btn btn-sm btn-danger btn-historial-eliminar"
                        id="btnEliminarHistorialSeleccionados"
                        title="Eliminar del historial disciplinario los registros seleccionados"
                        disabled
                      >
                        Eliminar seleccionados
                      </button>
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        id="btnImprimirHistorialSeleccionados"
                        disabled
                      >
                        Imprimir seleccionados
                      </button>
                    </div>
                  </details>
                </div>
              </div>

                <div class="d-flex gap-2">
                  <button
                    type="button"
                    class="btn btn-primary btn-lg d-flex align-items-center justify-content-center gap-2"
                    id="btnSiguienteEstudiante"
                  >
                    <span>Siguiente</span>
                    <span aria-hidden="true" class="fw-bold">&#8594;</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Columna derecha: Agregar/Editar estudiantes -->
            <div class="col-lg-5">
              <div class="card p-4 shadow-sm">
                <h4 class="text-success mb-4">Gestionar Estudiantes</h4>
                
                <form id="formEstudiante" class="mb-4" autocomplete="off">
                  <div class="mb-3">
                    <label for="nombres" class="form-label">Nombre:</label>
                    <input type="text" class="form-control" id="nombres" placeholder="*****" autocomplete="off" required>
                  </div>
                  <div class="mb-3">
                    <label for="apellidos" class="form-label">Apellido:</label>
                    <input type="text" class="form-control" id="apellidos" placeholder="*****" autocomplete="off" required>
                  </div>
                  <div class="mb-3">
                    <label for="matricula" class="form-label">Número de Matrícula:</label>
                    <input type="text" class="form-control" id="matricula" placeholder="*****" autocomplete="off" required>
                  </div>
                  <div class="border rounded p-3 bg-light mb-3">
                    <h5 class="text-primary mb-2">Acudiente asociado</h5>
                    <p class="text-muted small mb-3">
                      Estos datos se guardan junto con el estudiante y luego se muestran automáticamente
                      en la etapa final del informe.
                    </p>
                    <div class="mb-3">
                      <label for="gestionAcudienteNombre" class="form-label">Nombre del acudiente</label>
                      <input type="text" class="form-control" id="gestionAcudienteNombre" placeholder="Nombre del acudiente" autocomplete="off" required>
                    </div>
                    <div class="mb-3">
                      <label for="gestionAcudienteApellido" class="form-label">Apellido del acudiente</label>
                      <input type="text" class="form-control" id="gestionAcudienteApellido" placeholder="Apellido del acudiente" autocomplete="off" required>
                    </div>
                    <div class="mb-3">
                      <label for="gestionAcudienteParentesco" class="form-label">Parentesco</label>
                      <select class="form-select" id="gestionAcudienteParentesco">
                        <option value="">Selecciona...</option>
                        <option value="Madre">Madre</option>
                        <option value="Padre">Padre</option>
                        <option value="Abuela">Abuela</option>
                        <option value="Abuelo">Abuelo</option>
                        <option value="Tía">Tía</option>
                        <option value="Tío">Tío</option>
                        <option value="Acudiente legal">Acudiente legal</option>
                        <option value="Otro">Otro</option>
                      </select>
                    </div>
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label for="gestionAcudienteTelefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="gestionAcudienteTelefono" placeholder="Ej. 3001234567" autocomplete="off">
                      </div>
                      <div class="col-md-6">
                        <label for="gestionAcudienteCorreo" class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" id="gestionAcudienteCorreo" placeholder="acudiente@correo.com" autocomplete="off">
                      </div>
                    </div>
                    <div class="mt-3">
                      <label for="gestionAcudienteDireccion" class="form-label">Dirección (opcional)</label>
                      <input type="text" class="form-control" id="gestionAcudienteDireccion" placeholder="Dirección del acudiente" autocomplete="off">
                    </div>
                  </div>
                  <button type="submit" class="btn btn-success w-100">Agregar Estudiante</button>
                  <button type="button" class="btn btn-warning w-100 mt-2 d-none" id="btnActualizarEstudiante">
                    Actualizar Estudiante
                  </button>
                  <button type="button" class="btn btn-secondary w-100 mt-2 d-none" id="btnCancelarEdicion">
                    Cancelar
                  </button>
                </form>

                <div id="listaEstudiantesGestion" class="border rounded p-3 student-manage-list" style="max-height: 250px; overflow-y: auto;">
                  <small class="text-muted">Estudiantes recientes...</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- SECCIÓN 2: PLANTILLAS DISCIPLINARIAS -->
      <section id="seccionPlantillas" aria-label="Plantillas disciplinarias" class="workspace-section d-none">
        <div class="text-center mb-4">
          <h2 class="fw-bold text-uppercase text-primary">Plantillas Disciplinarias</h2>
          <p class="text-muted">Registro de observaciones según el tipo de falta cometida por el estudiante.</p>
        </div>

        <div class="accordion" id="disciplinariasAccordion">
          <div class="accordion-item">
            <h2 class="accordion-header" id="faltasTipo1Heading">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faltasTipo1" aria-expanded="true" aria-controls="faltasTipo1">
                Faltas Tipo 1
              </button>
            </h2>
            <div id="faltasTipo1" class="accordion-collapse collapse show" data-bs-parent="#disciplinariasAccordion">
              <div class="accordion-body">
                <h5 class="text-primary mb-3">EVENTOS DE LLAMADO DE ATENCIÓN EN RELACIÓN COMPORTAMIENTO EN EL AULA</h5>
                <div class="mb-3">
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="aula1"><label class="form-check-label" for="aula1">Hace comentarios inadecuados con temas fuera de contexto.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="aula2"><label class="form-check-label" for="aula2">Juega en clase y/o cambia de puesto, lanza objetos, basuras, saliva dentro del aula.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="aula3"><label class="form-check-label" for="aula3">Llega tarde al salón de clase sin autorización; situación que perturba el normal desarrollo de las clases, por lo que si es reiterativo pasará a tipo II.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="aula4"><label class="form-check-label" for="aula4">Interrumpe el desarrollo de clase con gritos, silbidos, abucheos u otros sonidos.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="aula5"><label class="form-check-label" for="aula5">Usa celulares, radios, audífonos y juegos electrónicos o cualquier elemento distractor en el aula sin propósito pedagógico ni autorización del docente.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="aula6"><label class="form-check-label" for="aula6">Ingresa a las aulas en horas de descanso sin autorización del docente.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="aula7"><label class="form-check-label" for="aula7">Consume alimentos en clase sin autorización del docente.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="aula8"><label class="form-check-label" for="aula8">Se niega a contribuir con el aseo y buena presentación de las aulas.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="aula9"><label class="form-check-label" for="aula9">Lanza objetos, basuras, tapas, botellas con agua, borradores, papeles, lapiceros entre otros.</label></div>
                </div>

                <h5 class="text-success mt-4 mb-3">EVENTOS DE LLAMADO DE ATENCIÓN EN RELACIÓN A COMPORTAMIENTOS INADECUADOS EN ESPACIOS INSTITUCIONALES</h5>
                <div class="mb-3">
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="espacios1"><label class="form-check-label" for="espacios1">Juega y/o cambia de puesto, lanza objetos, basuras, saliva en actos comunitarios.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="espacios2"><label class="form-check-label" for="espacios2">No justifica por escrito su ausencia y/o retardo a la institución educativa.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="espacios3"><label class="form-check-label" for="espacios3">Falta con su higiene y presentación personal.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="espacios4"><label class="form-check-label" for="espacios4">Interrumpe el desarrollo de actos comunitarios con gritos, silbidos, abucheos u otros sonidos.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="espacios5"><label class="form-check-label" for="espacios5">Lanza objetos, basuras, tapas, botellas con agua, borradores, papeles, lapiceros fuera del aula.</label></div>
                </div>

                <h5 class="text-warning mt-4 mb-3">EVENTOS DE LLAMADO DE ATENCIÓN QUE AFECTAN UNA ADECUADA COMUNICACIÓN</h5>
                <div class="mb-3">
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="comunicacion1"><label class="form-check-label" for="comunicacion1">Utiliza vocabulario descortés y/o soez y/o en tono alto para dirigirse a miembros de la comunidad educativa (tipo I si no hay agresión o bullying).</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="comunicacion2"><label class="form-check-label" for="comunicacion2">Indispone con falsas acusaciones a cualquier miembro de la comunidad educativa (tipo II si hay amenaza o consecuencias graves; tipo III si hay delitos).</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="comunicacion3"><label class="form-check-label" for="comunicacion3">Coloca apodos a cualquier miembro de la comunidad educativa.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="comunicacion4"><label class="form-check-label" for="comunicacion4">Justificaciones falsas de sus acciones, sin afectar a compañeros.</label></div>
                </div>
              </div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="faltasTipo2Heading">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faltasTipo2" aria-expanded="false" aria-controls="faltasTipo2">
                Faltas Tipo 2
              </button>
            </h2>
            <div id="faltasTipo2" class="accordion-collapse collapse" data-bs-parent="#disciplinariasAccordion">
              <div class="accordion-body">
                <h5 class="text-danger mb-3">EVENTOS DE LLAMADO DE ATENCIÓN EN RELACIÓN COMPORTAMIENTO EN EL AULA</h5>
                <div class="mb-3">
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2aula1"><label class="form-check-label" for="tipo2aula1">Realiza acoso escolar al interior del aula a sus compañeros(as) y/o docentes.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2aula2"><label class="form-check-label" for="tipo2aula2">Ocasiona lesiones físicas a sus compañeros que no ameritan reparación ni consideración como situación tipo III.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2aula3"><label class="form-check-label" for="tipo2aula3">Realiza fraude en evaluaciones, trabajos o documentación requerida.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2aula4"><label class="form-check-label" for="tipo2aula4">Causa daños a implementos escolares de compañeros y/o dotación del aula.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2aula5"><label class="form-check-label" for="tipo2aula5">Usa dispositivos electrónicos sin autorización, afectando el desarrollo de clases e involucrando a otros compañeros.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2aula6"><label class="form-check-label" for="tipo2aula6">Evade clase, quedándose por fuera del aula, poniendo en riesgo su integridad y afectando otras actividades.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2aula7"><label class="form-check-label" for="tipo2aula7">No justifica por escrito su ausencia o retardo a clase.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2aula8"><label class="form-check-label" for="tipo2aula8">Se presenta sin materiales requeridos, interrumpiendo el desarrollo de las clases.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2aula9"><label class="form-check-label" for="tipo2aula9">Esconde o saca útiles escolares de la maleta de sus compañeros ("Tortugazo").</label></div>
                </div>

                <h5 class="text-success mt-4 mb-3">EVENTOS DE LLAMADO DE ATENCIÓN EN RELACIÓN A COMPORTAMIENTOS INADECUADOS EN ESPACIOS INSTITUCIONALES</h5>
                <div class="mb-3">
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2espacios1"><label class="form-check-label" for="tipo2espacios1">Difunde información privada tratada en mecanismos institucionales.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2espacios2"><label class="form-check-label" for="tipo2espacios2">Escribe mensajes insultantes y/o vulgares a cualquier integrante de la institución.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2espacios3"><label class="form-check-label" for="tipo2espacios3">Causa daños a implementos institucionales y/o planta física.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2espacios4"><label class="form-check-label" for="tipo2espacios4">Atenta contra el ambiente y uso racional de servicios públicos.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2espacios5"><label class="form-check-label" for="tipo2espacios5">Usa dispositivos electrónicos en actos comunitarios sin autorización.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2espacios6"><label class="form-check-label" for="tipo2espacios6">Permanece fuera de la institución sin autorización, poniendo en riesgo su integridad.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2espacios7"><label class="form-check-label" for="tipo2espacios7">Atenta y/o destruye símbolos patrios o institucionales.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2espacios8"><label class="form-check-label" for="tipo2espacios8">Esconde o saca útiles escolares de la maleta de otro miembro de la comunidad educativa.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2espacios9"><label class="form-check-label" for="tipo2espacios9">Agresiones físicas y verbales (si hay daños por reparar, pasa a tipo III).</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2espacios10"><label class="form-check-label" for="tipo2espacios10">Consume sustancias de posible adicción dentro o fuera de la institución portando uniforme escolar.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2espacios11"><label class="form-check-label" for="tipo2espacios11">Llega tarde reiteradamente sin justificación, afectando el desarrollo académico.</label></div>
                </div>

                <h5 class="text-warning mt-4 mb-3">EVENTOS DE LLAMADO DE ATENCIÓN QUE AFECTAN UNA ADECUADA COMUNICACIÓN</h5>
                <div class="mb-3">
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2comunicacion1"><label class="form-check-label" for="tipo2comunicacion1">Agrede verbalmente a miembros de la comunidad educativa.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2comunicacion2"><label class="form-check-label" for="tipo2comunicacion2">Ocasiona lesiones físicas (empujones, puñetazos, patadas, etc.).</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2comunicacion3"><label class="form-check-label" for="tipo2comunicacion3">Promueve o participa en peleas dentro o fuera de la institución.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2comunicacion4"><label class="form-check-label" for="tipo2comunicacion4">Oculta hechos o miente para evitar sanciones (si hay delito, pasa a tipo III).</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2comunicacion5"><label class="form-check-label" for="tipo2comunicacion5">No asume los llamados de atención con respeto.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo2comunicacion6"><label class="form-check-label" for="tipo2comunicacion6">Es negligente en la presentación de citaciones o información al acudiente.</label></div>
                </div>
              </div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="faltasTipo3Heading">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faltasTipo3" aria-expanded="false" aria-controls="faltasTipo3">
                Faltas Tipo 3
              </button>
            </h2>
            <div id="faltasTipo3" class="accordion-collapse collapse" data-bs-parent="#disciplinariasAccordion">
              <div class="accordion-body">
                <h5 class="text-danger mb-3">
                  DENTRO DE ESTE TIPO DE SITUACIONES SE ENCUENTRAN AQUELLAS QUE SON CONSTITUTIVAS DE PRESUNTOS DELITOS. POR EJEMPLO, AQUELLOS DELITOS CONTRA LA LIBERTAD, LA INTEGRIDAD, LA IDENTIDAD DE GÉNERO Y LA ORIENTACIÓN SEXUAL. SE DEBE TENER EN CUENTA QUE ESTE TIPO DE CASOS PUEDEN SUCEDER TANTO DENTRO Y FUERA DE LA INSTITUCIÓN EDUCATIVA.
                </h5>

                <div class="mb-3">
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_1"><label class="form-check-label" for="tipo3_1">Presentarse en estado de embriaguez o bajo efectos de sustancias alucinógenas.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_2"><label class="form-check-label" for="tipo3_2">Consumir dentro de la institución sustancias alucinógenas.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_3"><label class="form-check-label" for="tipo3_3">Traficar dentro de la institución educativa con sustancias alucinógenas.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_4"><label class="form-check-label" for="tipo3_4">Explotar detonantes o compuestos químicos que generen riesgo para la comunidad educativa.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_5"><label class="form-check-label" for="tipo3_5">Hurtar implementos de la institución u objetos personales a cualquier miembro de la comunidad educativa.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_6"><label class="form-check-label" for="tipo3_6">Destruir total o parcialmente la propiedad ajena (rallar, tirar, esconder, quemar, partir, mojar, entre otras).</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_7"><label class="form-check-label" for="tipo3_7">Ejercer actos que atenten contra la libertad, la integridad y la formación sexual de niños, niñas y adolescentes.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_8"><label class="form-check-label" for="tipo3_8">Agresión sexual sin contacto (comentarios, chistes, gestos o miradas obscenas).</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_9"><label class="form-check-label" for="tipo3_9">Agresión sexual con contacto (incluye halar o bajar prendas de vestir).</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_10"><label class="form-check-label" for="tipo3_10">Contacto sexual abusivo entre menores de edad.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_11"><label class="form-check-label" for="tipo3_11">Violación.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_12"><label class="form-check-label" for="tipo3_12">Actos sexuales abusivos.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_13"><label class="form-check-label" for="tipo3_13">Acoso sexual.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_14"><label class="form-check-label" for="tipo3_14">Trata de personas.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_15"><label class="form-check-label" for="tipo3_15">Explotación sexual y comercial de niños, niñas y adolescentes.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_16"><label class="form-check-label" for="tipo3_16">Ejercer actos que atenten contra el derecho a la vida.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_17"><label class="form-check-label" for="tipo3_17">Guardar, ocultar o portar armas o explosivos dentro de la institución educativa.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_18"><label class="form-check-label" for="tipo3_18">Conformar combos, barras o pandillas con fines delictivos en la institución educativa.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_19"><label class="form-check-label" for="tipo3_19">Cometer actos delictivos portando el uniforme escolar dentro o fuera de la institución.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_20"><label class="form-check-label" for="tipo3_20">Amenaza o intimida a miembros de la comunidad educativa por medios directos o indirectos.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_21"><label class="form-check-label" for="tipo3_21">Utiliza el nombre de la institución para actividades de lucro sin autorización del consejo directivo.</label></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" id="tipo3_22"><label class="form-check-label" for="tipo3_22">Altera documentos oficiales como informes, registros o firmas.</label></div>
                </div>
              </div>
            </div>
          </div>

            <div class="mt-4 d-flex flex-wrap gap-2">
              <button type="button" class="btn btn-primary" id="btnGenerarReporte">Generar reporte</button>
            </div>

          <div id="reporteGenerado" class="mt-4 p-3 border border-info rounded bg-light d-none workspace-report-box disciplinary-report-box">
            <div id="reporteResumenContenido"></div>
          </div>
        </div>

        <!-- Botones de navegación para plantillas -->
        <div class="mt-4 d-flex justify-content-between">
          <button
            type="button"
            class="btn btn-success d-flex align-items-center gap-2"
            id="btnAtrasPlantillas"
          >
            <span aria-hidden="true" class="fw-bold">&#8592;</span>
            <span>Atrás</span>
          </button>
          <button
            type="button"
            class="btn btn-primary d-flex align-items-center gap-2"
            id="btnSiguientePlantillas"
          >
            <span>Siguiente</span>
            <span aria-hidden="true" class="fw-bold">&#8594;</span>
          </button>
        </div>
      </section>
    </div>

    <!-- SECCIÓN 3: ESTÍMULOS -->
    <section id="seccionEstimulos" class="container my-5 d-none workspace-section">
      <div class="text-center mb-4">
        <h2 class="text-success fw-bold text-uppercase">Plantillas de Estímulos</h2>
        <p class="text-muted fs-5">ESTÍMULOS</p>
      </div>

      <div class="card p-4 shadow-sm">
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="estimulo1">
          <label class="form-check-label" for="estimulo1">
            Candidato a promoción anticipada.
          </label>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="estimulo2">
          <label class="form-check-label" for="estimulo2">
            Los estudiantes que obtengan resultados destacados en las pruebas externas, olimpiadas del conocimiento, procesos investigativos se hará un reconocimiento público.
          </label>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="estimulo3">
          <label class="form-check-label" for="estimulo3">
            Al finalizar cada periodo académico se le hará reconocimiento al estudiante destacado de cada grupo en cuanto a su desempeño académico como a los valores institucionales.
          </label>
        </div>

          <div class="mt-4 d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary" id="btnReporteEstimulos">Generar reporte</button>
          </div>

        <div id="reporteEstimulos" class="mt-4 p-3 border border-success rounded bg-light d-none workspace-report-box disciplinary-report-box"></div>

        <!-- Botones de navegación para estímulos -->
        <div class="mt-4 d-flex justify-content-between">
          <button
            type="button"
            class="btn btn-success d-flex align-items-center gap-2"
            id="btnAtrasEstimulos"
          >
            <span aria-hidden="true" class="fw-bold">&#8592;</span>
            <span>Atrás</span>
          </button>
          <button
            type="button"
            class="btn btn-primary d-flex align-items-center gap-2"
            id="btnSiguienteAcudiente"
          >
            <span>Siguiente</span>
            <span aria-hidden="true" class="fw-bold">&#8594;</span>
          </button>
        </div>
      </div>
    </section>

    <!-- SECCIÓN 4: PERFIL DEL ACUDIENTE -->
    <section id="seccionAcudiente" class="container my-5 d-none workspace-section">
      <div class="text-center mb-4">
        <h2 class="text-primary fw-bold text-uppercase">Acudiente Asociado</h2>
        <p class="text-muted fs-5">Datos cargados desde la ficha del estudiante y notificación del informe disciplinario</p>
      </div>

      <div class="card p-4 shadow-sm">
        <div class="alert alert-info">
          <strong>Estudiante:</strong>
          <span id="acudienteNombreEstudiante">Sin selección</span>
          <br>
          <small class="text-muted">Matrícula: <span id="acudienteMatriculaEstudiante">N/A</span></small>
        </div>

        <div id="acudienteEstado" class="alert d-none" role="status"></div>

        <div class="mb-3 d-flex justify-content-end">
          <button type="button" class="btn btn-outline-primary btn-sm d-none" id="btnEditarAcudienteDesdeGestion">
            Editar acudiente en gestión de estudiantes
          </button>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label for="acudienteNombre" class="form-label">Nombre del acudiente</label>
            <input type="text" class="form-control" id="acudienteNombre" placeholder="Nombre del acudiente" readonly>
          </div>
          <div class="col-md-6">
            <label for="acudienteApellido" class="form-label">Apellido del acudiente</label>
            <input type="text" class="form-control" id="acudienteApellido" placeholder="Apellido del acudiente" readonly>
          </div>
          <div class="col-md-6">
            <label for="acudienteParentesco" class="form-label">Parentesco</label>
            <select class="form-select" id="acudienteParentesco" disabled>
              <option value="">Selecciona...</option>
              <option value="Madre">Madre</option>
              <option value="Padre">Padre</option>
              <option value="Abuela">Abuela</option>
              <option value="Abuelo">Abuelo</option>
              <option value="Tía">Tía</option>
              <option value="Tío">Tío</option>
              <option value="Acudiente legal">Acudiente legal</option>
              <option value="Otro">Otro</option>
            </select>
          </div>
          <div class="col-md-6">
            <label for="acudienteTelefono" class="form-label">Teléfono</label>
            <input type="text" class="form-control" id="acudienteTelefono" placeholder="Ej. 3001234567" readonly>
          </div>
          <div class="col-md-6">
            <label for="acudienteCorreo" class="form-label">Correo electrónico</label>
            <input type="email" class="form-control" id="acudienteCorreo" placeholder="acudiente@correo.com" readonly>
          </div>
          <div class="col-12">
            <label for="acudienteDireccion" class="form-label">Dirección (opcional)</label>
            <input type="text" class="form-control" id="acudienteDireccion" placeholder="Dirección del acudiente" readonly>
          </div>
        </div>

        <div id="acudienteLoading" class="alert alert-info mt-3 d-none">
          Cargando información del acudiente…
        </div>

        <div class="mt-3">
          <label for="asuntoNotificacionAcudiente" class="form-label">Asunto del correo/notificación</label>
          <input type="text" class="form-control" id="asuntoNotificacionAcudiente" value="Informe disciplinario del estudiante">
        </div>

        <div class="mt-3">
          <label for="notificacionAcudienteTexto" class="form-label">Contenido de notificación</label>
          <textarea class="form-control" id="notificacionAcudienteTexto" rows="8" placeholder="Aquí se generará la notificación para el acudiente..."></textarea>
        </div>

        <div class="mt-4">
          <button type="button" class="btn btn-primary w-100" id="btnGuardarRegistro">
            Guardar informe disciplinario
          </button>
        </div>

        <div class="mt-4 d-flex justify-content-between">
          <button
            type="button"
            class="btn btn-success d-flex align-items-center gap-2"
            id="btnAtrasAcudiente"
          >
            <span aria-hidden="true" class="fw-bold">&#8592;</span>
            <span>Atrás</span>
          </button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary d-flex align-items-center gap-2" id="btnEnviarCorreoAcudiente">
              <span>Guardar y enviar al correo electrónico</span>
              <span aria-hidden="true" class="fw-bold">&#8594;</span>
            </button>
            <button type="button" class="btn btn-success d-flex align-items-center gap-2" id="btnVolverInicio">
              <span>Volver al inicio</span>
              <span aria-hidden="true" class="fw-bold">&#8594;</span>
            </button>
          </div>
        </div>
      </div>
    </section>
  </div>

