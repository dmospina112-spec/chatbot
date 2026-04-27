(function () {
  const bubble = document.getElementById('chatbot-bubble');
  const windowEl = document.getElementById('chatbot-window');
  const header = document.getElementById('chatbot-header');
  const closeBtn = document.getElementById('chatbot-close');
  const messagesEl = document.getElementById('chatbot-messages');
  const inputEl = document.getElementById('chatbot-input');
  const sendBtn = document.getElementById('chatbot-send');

  if (!bubble || !windowEl || !messagesEl || !inputEl || !sendBtn || !closeBtn || !header) {
    return;
  }

  const STORAGE_KEY = 'app_educativa_chatbot_state_v2';
  const panelUser = window.__panelUser || {};
  const role = String(panelUser.rol || 'usuario').toLowerCase();
  const roleLabel = role === 'administrador' ? 'administrador' : 'docente';

  const predefinedQuestions = [
    {
      question: '¿Cómo agrego un estudiante?',
      answer:
        'Para agregar un estudiante ve a la etapa "Estudiantes". En el formulario de la derecha completa nombre, apellido, matrícula y los datos del acudiente. Luego pulsa "Agregar Estudiante".'
    },
    {
      question: '¿Cómo envío el correo al acudiente?',
      answer:
        'En la etapa "Acudiente" revisa el nombre, parentesco, teléfono y correo del acudiente asociado al estudiante. Después verifica el asunto y el contenido del mensaje, y luego usa la opción de envío'
    },
    {
      question: '¿Cómo funcionan las faltas tipo 1, 2 y 3?',
      answer:
        'Las faltas tipo 1 agrupan llamados de atención por comportamiento, convivencia o comunicación inadecuada. Las faltas tipo 2 corresponden a situaciones más serias como agresiones, daño a implementos, fraude o acoso. Las faltas tipo 3 abarcan situaciones de alto riesgo o presuntamente delictivas, como amenazas graves, armas, sustancias o abuso sexual.'
    },
    {
      question: '¿Cómo administro usuarios?',
      role: 'administrador',
      answer:
        'Como administrador puedes abrir "Administrar usuarios", crear cuentas, editar datos, cambiar el rol entre docente y administrador, y activar o desactivar accesos del sistema.'
    },
    {
      question: '¿Cómo guardo el informe?',
      answer:
        'Cuando termines de seleccionar faltas y revisar la notificación, usa "Guardar informe disciplinario". Eso registra el caso en la base de datos y conserva el historial del estudiante.'
    }
  ];

  const quickActions = predefinedQuestions
    .filter((entry) => !entry.role || entry.role === role)
    .slice(0, 5)
    .map((entry) => entry.question);

  const faqEntries = [
    {
      keywords: ['agregar estudiante', 'crear estudiante', 'nuevo estudiante', 'registrar estudiante'],
      answer:
        'Para agregar un estudiante ve a la etapa "Estudiantes". En el formulario de la derecha completa nombre, apellido, matrícula y los datos del acudiente. Luego pulsa "Agregar Estudiante".'
    },
    {
      keywords: ['editar estudiante', 'actualizar estudiante', 'modificar estudiante'],
      answer:
        'En "Gestionar Estudiantes" busca el estudiante en la lista reciente, pulsa "Editar", cambia los datos y luego usa "Actualizar Estudiante".'
    },
    {
      keywords: ['buscar estudiante', 'seleccionar estudiante', 'escoger estudiante'],
      answer:
        'Usa el buscador por nombre, apellido o matrícula. Después selecciónalo en la lista desplegable y verás su información y el historial disciplinario si existe.'
    },
    {
      keywords: ['historial', 'registros previos', 'disciplinario anterior'],
      answer:
        'Al seleccionar un estudiante se muestra el bloque "Historial disciplinario". Allí puedes revisar registros anteriores y también imprimir los seleccionados.'
    },
    {
      keywords: ['faltas tipo 1', 'tipo 1'],
      answer:
        'Las faltas tipo 1 agrupan llamados de atención por comportamiento, convivencia o comunicación inadecuada que no escalan a situaciones graves. Se seleccionan en la etapa "Plantillas".'
    },
    {
      keywords: ['faltas tipo 2', 'tipo 2'],
      answer:
        'Las faltas tipo 2 corresponden a situaciones más serias: agresiones, daño a implementos, fraude, acoso o conductas que afectan de forma relevante la convivencia.'
    },
    {
      keywords: ['faltas tipo 3', 'tipo 3', 'delito', 'graves'],
      answer:
        'Las faltas tipo 3 abarcan situaciones presuntamente delictivas o de alto riesgo, como agresiones graves, armas, sustancias, abuso sexual o amenazas serias.'
    },
    {
      keywords: ['estimulos', 'reconocimientos'],
      answer:
        'Después de las plantillas disciplinarias puedes pasar a "Estímulos" para registrar reconocimientos positivos. También puedes generar un reporte de estímulos en PDF.'
    },
    {
      keywords: ['acudiente', 'correo acudiente', 'notificacion'],
      answer:
        'En la etapa "Acudiente" se cargan automáticamente nombre, apellido, parentesco, teléfono y correo del acudiente asociado al estudiante. Allí puedes revisar o generar la notificación antes de enviarla.'
    },
    {
      keywords: ['enviar correo', 'mandar correo', 'remitente', 'smtp', 'gmail'],
      answer:
        'En la etapa "Acudiente" revisa el nombre, parentesco, teléfono y correo del acudiente asociado al estudiante. Después verifica el asunto y el contenido del mensaje, y luego usa la opción de envío'
    },
    {
      keywords: ['guardar informe', 'guardar registro', 'guardar reporte'],
      answer:
        'Cuando termines de seleccionar faltas y revisar la notificación, usa "Guardar informe disciplinario". Eso registra el caso en la base y conserva el historial del estudiante.'
    },
    {
      keywords: ['pdf', 'imprimir', 'reporte'],
      answer:
        'Puedes generar reportes PDF desde las secciones de plantillas, estímulos e historial. El sistema usa html2pdf para construir el documento desde la vista actual.'
    },
    {
      keywords: ['usuarios', 'administrar usuarios', 'crear usuario', 'docente', 'administrador'],
      role: 'administrador',
      answer:
        'Como administrador puedes abrir "Administrar usuarios", crear cuentas, editar datos, cambiar rol entre docente y administrador, y activar o desactivar accesos del sistema.'
    },
    {
      keywords: ['recuperar contrasena', 'olvide mi contrasena', 'pregunta de seguridad', 'recuperar contraseña', 'olvide mi contraseña'],
      answer:
        'En la pantalla de inicio existe el flujo "¿Olvidaste tu contraseña?". El usuario debe consultar su pregunta de seguridad, responderla correctamente y definir una nueva clave.'
    },
    {
      keywords: ['cerrar sesion', 'salir'],
      answer:
        'Para salir del sistema usa el botón "Cerrar sesión" en la parte superior del panel.'
    }
  ];

  const blockedQuestionPatterns = [
    'como importo acudientes',
    'cómo importo acudientes',
    'importo acudientes',
    'importar acudientes'
  ];

  function normalize(value) {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^\w\s]/g, ' ')
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim();
  }

  function getCurrentStep() {
    const steps = [
      { id: 'seccionEstudiantes', label: 'Estudiantes' },
      { id: 'seccionPlantillas', label: 'Plantillas' },
      { id: 'seccionEstimulos', label: 'Estímulos' },
      { id: 'seccionAcudiente', label: 'Acudiente' }
    ];

    const visible = steps.find((step) => {
      const el = document.getElementById(step.id);
      return el && !el.classList.contains('d-none');
    });

    return visible ? visible.label : 'Panel principal';
  }

  function getSelectedStudentName() {
    const nameEl = document.getElementById('nombreEstudianteSeleccionado');
    const matriculaEl = document.getElementById('matriculaEstudianteSeleccionado');
    const name = (nameEl?.textContent || '').trim();
    const matricula = (matriculaEl?.textContent || '').trim();

    if (!name) {
      return '';
    }

    return matricula ? `${name} (Matrícula: ${matricula})` : name;
  }

  function buildContextAnswer() {
    const currentStep = getCurrentStep();
    const selectedStudent = getSelectedStudentName();

    let answer = `Ahora mismo estás en la etapa "${currentStep}".`;

    if (selectedStudent) {
      answer += ` El estudiante activo es ${selectedStudent}.`;
    } else {
      answer += ' Todavía no hay un estudiante seleccionado.';
    }

    if (currentStep === 'Acudiente') {
      answer += ' Aquí puedes revisar los datos del acudiente, el asunto y el contenido del correo antes de guardar o enviar.';
    }

    return answer;
  }

  function findBestFaqMatch(message) {
    const text = normalize(message);
    if (!text) {
      return null;
    }

    if (blockedQuestionPatterns.some((pattern) => text.includes(normalize(pattern)))) {
      return null;
    }

    let bestEntry = null;
    let bestScore = 0;

    faqEntries.forEach((entry) => {
      if (entry.role && entry.role !== role) {
        return;
      }

      let score = 0;
      entry.keywords.forEach((keyword) => {
        const normalizedKeyword = normalize(keyword);
        if (text.includes(normalizedKeyword)) {
          score += normalizedKeyword.split(' ').length + 1;
        }
      });

      if (score > bestScore) {
        bestScore = score;
        bestEntry = entry;
      }
    });

    return bestScore > 0 ? bestEntry : null;
  }

  function getFallbackAnswer(message) {
    const text = normalize(message);

    if (text.includes('donde estoy') || text.includes('en que paso') || text.includes('paso actual')) {
      return buildContextAnswer();
    }

    return [
      'Puedo ayudarte con el uso de esta página: estudiantes, faltas tipo 1/2/3, estímulos, acudientes, envío de correos, PDFs, historial, importaciones y administración de usuarios.',
      '',
      `Rol actual detectado: ${roleLabel}.`,
      `Etapa visible: ${getCurrentStep()}.`,
      '',
      'Prueba una de estas preguntas predefinidas:',
      `- ${quickActions[0] || '¿Cómo agrego un estudiante?'}`,
      `- ${quickActions[1] || '¿Cómo envío el correo al acudiente?'}`,
      `- ${quickActions[2] || (role === 'administrador' ? '¿Cómo administro usuarios?' : '¿Cómo guardo el informe?')}`,
      `- ${quickActions[3] || '¿Cómo guardo el informe?'}`
    ].join('\n');
  }

  function resolveBotReply(message) {
    if (!message.trim()) {
      return 'Escríbeme una pregunta sobre el uso del sistema y te guío paso a paso.';
    }

    const normalizedMessage = normalize(message);
    if (blockedQuestionPatterns.some((pattern) => normalizedMessage.includes(normalize(pattern)))) {
      return 'Selecciona una de las preguntas disponibles o escribe una consulta sobre estudiantes, faltas, acudientes, correos, historial o usuarios.';
    }

    const predefinedMatch = predefinedQuestions.find((entry) => {
      if (entry.role && entry.role !== role) {
        return false;
      }

      return normalize(entry.question) === normalizedMessage;
    });

    if (predefinedMatch) {
      return predefinedMatch.answer;
    }

    if (normalizedMessage.includes('que puedes hacer') || normalizedMessage.includes('ayuda')) {
      return getFallbackAnswer(message);
    }

    const faqMatch = findBestFaqMatch(message);
    if (faqMatch) {
      return faqMatch.answer;
    }

    return 'Selecciona una de las preguntas disponibles o escribe una consulta parecida sobre estudiantes, faltas, acudientes, correos, historial o usuarios.';
  }

  function scrollMessagesToBottom() {
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function appendMessage(type, text, meta = '') {
    const wrapper = document.createElement('div');
    wrapper.className = `chatbot-message ${type}`;

    const bubbleEl = document.createElement('div');
    bubbleEl.className = 'chatbot-bubble-message';
    bubbleEl.textContent = text;

    if (meta) {
      const metaEl = document.createElement('span');
      metaEl.className = 'chatbot-meta';
      metaEl.textContent = meta;
      bubbleEl.appendChild(metaEl);
    }

    wrapper.appendChild(bubbleEl);
    messagesEl.appendChild(wrapper);
    scrollMessagesToBottom();
  }

  function appendQuickActions() {
    const wrap = document.createElement('div');
    wrap.className = 'chatbot-quick-actions';

    quickActions.forEach((label) => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'chatbot-chip';
      chip.textContent = label;
      chip.addEventListener('click', () => {
        inputEl.value = label;
        handleSend();
      });
      wrap.appendChild(chip);
    });

    messagesEl.appendChild(wrap);
    scrollMessagesToBottom();
  }

  function saveState(isOpen) {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ isOpen }));
  }

  function renderHeader() {
    header.innerHTML = `
      <div class="chatbot-title-wrap">
        <span class="chatbot-kicker">Centro de ayuda</span>
        <strong class="chatbot-title">Asistente virtual</strong>
        <span class="chatbot-subtitle">Respuestas rápidas sobre cómo usar la plataforma</span>
      </div>
      <button class="close-btn" id="chatbot-close" aria-label="Cerrar chat" title="Cerrar chat">×</button>
    `;
  }

  function renderBubble() {
    bubble.innerHTML = `
      <div class="chatbot-bubble-inner" aria-hidden="true">
        <span class="chatbot-bubble-icon">💬</span>
        <span class="chatbot-bubble-label">Ayuda</span>
      </div>
    `;
  }

  function setOpenState(isOpen) {
    windowEl.classList.toggle('is-closed', !isOpen);
    bubble.classList.toggle('is-hidden', isOpen);
    bubble.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
    windowEl.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    saveState(isOpen);
  }

  function openChat() {
    setOpenState(true);
    window.setTimeout(() => inputEl.focus(), 80);
  }

  function closeChat() {
    setOpenState(false);
  }

  function handleSend() {
    const message = inputEl.value.trim();
    if (!message) {
      return;
    }

    appendMessage('user', message, 'Tú');
    inputEl.value = '';

    window.setTimeout(() => {
      const reply = resolveBotReply(message);
      appendMessage('bot', reply, 'Asistente');
      saveState(true);
    }, 180);
  }

  function restoreState() {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) {
      return false;
    }

    try {
      const parsed = JSON.parse(raw);
      setOpenState(Boolean(parsed && parsed.isOpen));
      return true;
    } catch (_error) {
      sessionStorage.removeItem(STORAGE_KEY);
      return false;
    }
  }

  function bindEvents() {
    bubble.addEventListener('click', openChat);

    windowEl.addEventListener('click', (event) => {
      if (event.target instanceof HTMLElement && event.target.id === 'chatbot-close') {
        closeChat();
      }
    });

    sendBtn.addEventListener('click', handleSend);

    inputEl.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleSend();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !windowEl.classList.contains('is-closed')) {
        closeChat();
      }
    });
  }

  function renderWelcome() {
    messagesEl.innerHTML = '';
    appendMessage(
      'bot',
      [
        `Hola. Soy tu asistente virtual para el panel ${roleLabel}.`,
        '',
        'Puedo orientarte sobre cómo usar estudiantes, plantillas disciplinarias, estímulos, acudientes, correos, historial, PDFs e importaciones.'
      ].join('\n'),
      'Asistente'
    );
    appendMessage('bot', buildContextAnswer(), 'Contexto actual');
    appendQuickActions();
  }

  renderBubble();
  renderHeader();
  bindEvents();

  if (!restoreState()) {
    setOpenState(false);
  }

  renderWelcome();
})();
