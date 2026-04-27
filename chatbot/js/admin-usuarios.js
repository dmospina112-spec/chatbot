const ADMIN_USERS_ENDPOINT = 'api.php';
const ADMIN_AUTH_STORAGE_KEY = 'auth_user';
const ADMIN_DOCENTE_STORAGE_KEY = 'docente';

let adminUsers = [];
let adminUserEditingId = null;
let adminUsuariosSection;
let adminUsuariosForm;
let adminUsuariosList;
let adminUsuariosSearch;
let adminUsuariosMessage;
let adminUsuariosSummary;
let adminUsuariosRefreshBtn;
let adminUsuariosCancelBtn;
let adminUsuariosSubmitBtn;
let adminUsuariosPasswordHelp;
let adminMetricUsuarios;
let adminMetricActivos;
let adminUsersModalElement;
let adminUsersModalInstance;

document.addEventListener('DOMContentLoaded', async () => {
  adminUsuariosSection = document.getElementById('seccionUsuariosAdmin');
  if (!adminUsuariosSection) {
    return;
  }

  cacheAdminUsersDom();
  bindAdminUsersEvents();
  resetAdminUserForm();
  await loadAdminUsers();
});

function cacheAdminUsersDom() {
  adminUsuariosForm = document.getElementById('formUsuarioAdmin');
  adminUsuariosList = document.getElementById('listaUsuariosAdmin');
  adminUsuariosSearch = document.getElementById('buscarUsuarioAdmin');
  adminUsuariosMessage = document.getElementById('usuariosAdminMensaje');
  adminUsuariosSummary = document.getElementById('adminUsuariosResumen');
  adminUsuariosRefreshBtn = document.getElementById('btnRecargarUsuariosAdmin');
  adminUsuariosCancelBtn = document.getElementById('btnCancelarUsuarioAdmin');
  adminUsuariosSubmitBtn = document.getElementById('btnGuardarUsuarioAdmin');
  adminUsuariosPasswordHelp = document.getElementById('adminPasswordHelp');
  adminMetricUsuarios = document.getElementById('adminMetricUsuarios');
  adminMetricActivos = document.getElementById('adminMetricActivos');
  adminUsersModalElement = document.getElementById('adminUsersModal');
  if (adminUsersModalElement && window.bootstrap?.Modal) {
    adminUsersModalInstance = window.bootstrap.Modal.getOrCreateInstance(adminUsersModalElement);
  }
}

function bindAdminUsersEvents() {
  adminUsuariosForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    await submitAdminUserForm();
  });

  adminUsuariosCancelBtn?.addEventListener('click', () => {
    resetAdminUserForm();
    hideAdminUsersMessage();
  });

  adminUsuariosRefreshBtn?.addEventListener('click', async () => {
    await loadAdminUsers();
    showAdminUsersMessage('Listado actualizado correctamente.', 'info');
  });

  adminUsuariosSearch?.addEventListener('input', () => {
    renderAdminUsers();
  });

  adminUsersModalElement?.addEventListener('show.bs.modal', () => {
    hideAdminUsersMessage();
    loadAdminUsers().catch(() => null);
  });

  adminUsersModalElement?.addEventListener('hidden.bs.modal', () => {
    resetAdminUserForm();
    hideAdminUsersMessage();
  });
}

async function requestAdminUsers(action, method = 'GET', payload = null, query = {}) {
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

  const response = await fetch(`${ADMIN_USERS_ENDPOINT}?${params.toString()}`, options);
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
    throw new Error(fallbackMessage || 'La API devolvio una respuesta invalida.');
  }

  if (!response.ok || data.success === false) {
    throw new Error(data.error || data.message || `Error HTTP ${response.status}`);
  }

  return data;
}

function showAdminUsersMessage(text, variant = 'info') {
  if (!adminUsuariosMessage) {
    return;
  }

  adminUsuariosMessage.textContent = text;
  adminUsuariosMessage.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
  adminUsuariosMessage.classList.add(`alert-${variant}`);
}

function hideAdminUsersMessage() {
  if (!adminUsuariosMessage) {
    return;
  }

  adminUsuariosMessage.textContent = '';
  adminUsuariosMessage.classList.add('d-none');
  adminUsuariosMessage.classList.remove('alert-success', 'alert-danger', 'alert-warning', 'alert-info');
}

function normalizeAdminSearchValue(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

function normalizeAdminUsername(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(/\s+/g, '');
}

function getAdminUsersFilteredList() {
  const searchValue = normalizeAdminSearchValue(adminUsuariosSearch?.value || '');
  if (!searchValue) {
    return adminUsers;
  }

  return adminUsers.filter((user) => {
    const haystack = normalizeAdminSearchValue(
      `${user.nombre} ${user.apellido} ${user.usuario} ${user.correo} ${user.rol} ${user.activo ? 'activo' : 'inactivo'}`
    );
    return haystack.includes(searchValue);
  });
}

function updateAdminUsersSummary(filteredUsers) {
  if (!adminUsuariosSummary) {
    return;
  }

  const total = adminUsers.length;
  const active = adminUsers.filter((user) => user.activo).length;
  const admins = adminUsers.filter((user) => user.rol === 'administrador' && user.activo).length;
  const shown = filteredUsers.length;

  if (adminMetricUsuarios) {
    adminMetricUsuarios.textContent = String(total);
  }

  if (adminMetricActivos) {
    adminMetricActivos.textContent = String(active);
  }

  adminUsuariosSummary.textContent = `${shown} visibles de ${total} usuarios. Activos: ${active}. Administradores activos: ${admins}.`;
}

function setAdminUsersListMessage(message, variant = 'muted') {
  if (!adminUsuariosList) {
    return;
  }

  adminUsuariosList.innerHTML = '';
  const paragraph = document.createElement('p');
  paragraph.className = `admin-list-message text-${variant} text-center mb-0 py-4`;
  paragraph.textContent = message;
  adminUsuariosList.appendChild(paragraph);
}

function getAdminUserInitials(user) {
  const fullName = `${user.nombre || ''} ${user.apellido || ''}`.trim();
  const source = fullName || user.usuario || 'US';

  return source
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');
}

function renderAdminUsers() {
  if (!adminUsuariosList) {
    return;
  }

  const filteredUsers = getAdminUsersFilteredList();
  updateAdminUsersSummary(filteredUsers);
  adminUsuariosList.innerHTML = '';

  if (filteredUsers.length === 0) {
    setAdminUsersListMessage('No se encontraron usuarios con ese filtro.', 'muted');
    return;
  }

  filteredUsers.forEach((user) => {
    const row = document.createElement('article');
    row.className = 'admin-user-card';

    const main = document.createElement('div');
    main.className = 'admin-user-main';

    const identity = document.createElement('div');
    identity.className = 'admin-user-identity';

    const avatar = document.createElement('div');
    avatar.className = 'admin-user-avatar';
    avatar.textContent = getAdminUserInitials(user);

    const info = document.createElement('div');
    info.className = 'admin-user-content';

    const title = document.createElement('div');
    title.className = 'admin-user-title';

    const name = document.createElement('strong');
    name.textContent = `${user.nombre} ${user.apellido}`.trim() || user.usuario;
    title.appendChild(name);

    const badges = document.createElement('div');
    badges.className = 'admin-user-tags';

    const roleBadge = document.createElement('span');
    roleBadge.className = user.rol === 'administrador' ? 'badge text-bg-primary' : 'badge text-bg-secondary';
    roleBadge.textContent = user.rol === 'administrador' ? 'Administrador' : 'Docente';
    badges.appendChild(roleBadge);

    const statusBadge = document.createElement('span');
    statusBadge.className = user.activo ? 'badge text-bg-success' : 'badge text-bg-danger';
    statusBadge.textContent = user.activo ? 'Activo' : 'Inactivo';
    badges.appendChild(statusBadge);

    if (user.es_actual) {
      const currentBadge = document.createElement('span');
      currentBadge.className = 'badge text-bg-light border';
      currentBadge.textContent = 'Sesion actual';
      badges.appendChild(currentBadge);
    }

    title.appendChild(badges);
    info.appendChild(title);

    const meta = document.createElement('div');
    meta.className = 'admin-user-meta text-muted mt-2';
    [
      ['Usuario', user.usuario],
      ['Correo', user.correo || 'Sin correo'],
      ['Registro', formatAdminDate(user.fecha_registro)],
    ].forEach(([label, value]) => {
      const item = document.createElement('div');
      const strong = document.createElement('strong');
      strong.textContent = `${label}: `;
      item.appendChild(strong);
      item.appendChild(document.createTextNode(String(value)));
      meta.appendChild(item);
    });
    info.appendChild(meta);

    identity.appendChild(avatar);
    identity.appendChild(info);
    main.appendChild(identity);

    const actions = document.createElement('div');
    actions.className = 'admin-user-actions';

    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'btn btn-sm btn-outline-primary';
    editBtn.textContent = 'Editar';
    editBtn.addEventListener('click', () => {
      fillAdminUserForm(user);
    });
    actions.appendChild(editBtn);

    const actionBtn = document.createElement('button');
    actionBtn.type = 'button';

    if (user.activo) {
      actionBtn.className = 'btn btn-sm btn-outline-danger';
      actionBtn.textContent = 'Eliminar';
      actionBtn.disabled = Boolean(user.es_actual);
      actionBtn.addEventListener('click', async () => {
        await deleteAdminUser(user);
      });
    } else {
      actionBtn.className = 'btn btn-sm btn-success';
      actionBtn.textContent = 'Activar';
      actionBtn.addEventListener('click', async () => {
        await reactivateAdminUser(user);
      });
    }

    actions.appendChild(actionBtn);
    row.appendChild(main);
    row.appendChild(actions);
    adminUsuariosList.appendChild(row);
  });
}

function formatAdminDate(value) {
  if (!value) {
    return 'Sin fecha';
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return String(value);
  }

  return date.toLocaleString('es-CO', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

async function loadAdminUsers() {
  setAdminUsersListMessage('Cargando usuarios...', 'muted');

  try {
    const result = await requestAdminUsers('obtenerUsuariosAdmin');
    adminUsers = Array.isArray(result.data) ? result.data : [];
    renderAdminUsers();
  } catch (error) {
    setAdminUsersListMessage(error.message, 'danger');
    showAdminUsersMessage(error.message, 'danger');
  }
}

function updateAdminFormMode() {
  const isEditing = Number(adminUserEditingId) > 0;

  if (adminUsuariosSubmitBtn) {
    adminUsuariosSubmitBtn.textContent = isEditing ? 'Guardar cambios' : 'Crear usuario';
  }

  adminUsuariosCancelBtn?.classList.toggle('d-none', !isEditing);

  if (adminUsuariosPasswordHelp) {
    adminUsuariosPasswordHelp.textContent = isEditing
      ? 'Opcional al editar. Si la dejas vacia, se conserva la contraseña actual.'
      : 'Obligatoria al crear. Debe tener al menos 8 caracteres.';
  }
}

function resetAdminUserForm() {
  adminUserEditingId = null;
  adminUsuariosForm?.reset();
  document.getElementById('adminUsuarioId').value = '';
  document.getElementById('adminRol').value = 'docente';
  document.getElementById('adminEstado').value = '1';
  document.getElementById('adminRol').disabled = false;
  document.getElementById('adminEstado').disabled = false;
  updateAdminFormMode();
}

function fillAdminUserForm(user) {
  adminUsersModalInstance?.show();
  adminUserEditingId = Number(user.id);
  document.getElementById('adminUsuarioId').value = String(user.id);
  document.getElementById('adminNombre').value = user.nombre || '';
  document.getElementById('adminApellido').value = user.apellido || '';
  document.getElementById('adminUsuario').value = user.usuario || '';
  document.getElementById('adminCorreo').value = user.correo || '';
  document.getElementById('adminRol').value = user.rol || 'docente';
  document.getElementById('adminEstado').value = user.activo ? '1' : '0';
  document.getElementById('adminContrasena').value = '';
  document.getElementById('adminContrasenaConfirmacion').value = '';
  document.getElementById('adminRol').disabled = Boolean(user.es_actual);
  document.getElementById('adminEstado').disabled = Boolean(user.es_actual);
  updateAdminFormMode();
  hideAdminUsersMessage();
  adminUsuariosForm?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function buildAdminUserPayload() {
  return {
    id: Number(document.getElementById('adminUsuarioId').value || 0),
    nombre: document.getElementById('adminNombre').value.trim(),
    apellido: document.getElementById('adminApellido').value.trim(),
    usuario: normalizeAdminUsername(document.getElementById('adminUsuario').value),
    correo: document.getElementById('adminCorreo').value.trim().toLowerCase(),
    rol: document.getElementById('adminRol').value,
    activo: document.getElementById('adminEstado').value === '1',
    contrasena: document.getElementById('adminContrasena').value,
    confirmacion: document.getElementById('adminContrasenaConfirmacion').value,
  };
}

function validateAdminUserPayload(payload, isEditing) {
  if (!payload.nombre || !payload.apellido || !payload.usuario || !payload.correo) {
    throw new Error('Completa nombre, apellido, usuario y correo.');
  }

  if (payload.nombre.length < 2 || payload.apellido.length < 2) {
    throw new Error('Nombre y apellido deben tener al menos 2 caracteres.');
  }

  if (!/^[a-z0-9._-]{4,30}$/.test(payload.usuario)) {
    throw new Error('El usuario debe tener entre 4 y 30 caracteres y solo puede usar letras, numeros, punto, guion y guion bajo.');
  }

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.correo)) {
    throw new Error('Ingresa un correo electronico valido.');
  }

  if (!isEditing && payload.contrasena.length < 8) {
    throw new Error('La contraseña es obligatoria y debe tener al menos 8 caracteres.');
  }

  if (payload.contrasena && payload.contrasena.length < 8) {
    throw new Error('La contraseña debe tener al menos 8 caracteres.');
  }

  if (payload.contrasena !== payload.confirmacion) {
    throw new Error('La confirmacion de la contraseña no coincide.');
  }
}

async function submitAdminUserForm() {
  const payload = buildAdminUserPayload();
  const isEditing = payload.id > 0;

  try {
    validateAdminUserPayload(payload, isEditing);

    const action = isEditing ? 'actualizarUsuarioAdmin' : 'crearUsuarioAdmin';
    const result = await requestAdminUsers(action, 'POST', {
      id: payload.id,
      nombre: payload.nombre,
      apellido: payload.apellido,
      usuario: payload.usuario,
      correo: payload.correo,
      rol: payload.rol,
      activo: payload.activo,
      contrasena: payload.contrasena,
    });

    if (result.data?.auth_user) {
      syncAuthenticatedAdminUser(result.data.auth_user);
    }

    showAdminUsersMessage(result.message || 'Usuario guardado correctamente.', 'success');
    resetAdminUserForm();
    await loadAdminUsers();
  } catch (error) {
    showAdminUsersMessage(error.message, 'danger');
  }
}

async function deleteAdminUser(user) {
  const fullName = `${user.nombre} ${user.apellido}`.trim() || user.usuario;
  const confirmed = window.confirm(`¿Seguro que deseas eliminar a ${fullName}? Esta accion borrara la cuenta del listado.`);
  if (!confirmed) {
    return;
  }

  try {
    const result = await requestAdminUsers('eliminarUsuarioAdmin', 'POST', { id: Number(user.id) });
    showAdminUsersMessage(result.message || 'Usuario eliminado correctamente.', 'success');
    if (Number(adminUserEditingId) === Number(user.id)) {
      resetAdminUserForm();
    }
    await loadAdminUsers();
  } catch (error) {
    showAdminUsersMessage(error.message, 'danger');
  }
}

async function reactivateAdminUser(user) {
  try {
    const result = await requestAdminUsers('actualizarUsuarioAdmin', 'POST', {
      id: Number(user.id),
      nombre: user.nombre,
      apellido: user.apellido,
      usuario: user.usuario,
      correo: user.correo,
      rol: user.rol,
      activo: true,
      contrasena: '',
    });
    if (result.data?.auth_user) {
      syncAuthenticatedAdminUser(result.data.auth_user);
    }
    showAdminUsersMessage('Usuario activado correctamente.', 'success');
    await loadAdminUsers();
  } catch (error) {
    showAdminUsersMessage(error.message, 'danger');
  }
}

function syncAuthenticatedAdminUser(user) {
  if (!user) {
    return;
  }

  window.__panelUser = user;
  const serialized = JSON.stringify(user);
  sessionStorage.setItem(ADMIN_AUTH_STORAGE_KEY, serialized);
  sessionStorage.setItem(ADMIN_DOCENTE_STORAGE_KEY, serialized);
  sessionStorage.setItem('authed', '1');

  const badge = document.getElementById('userGreeting');
  if (badge) {
    const name = [user.nombre, user.apellido].filter(Boolean).join(' ');
    badge.textContent = name
      ? `Sesión activa: ${name} (${user.rol})`
      : `Sesión activa: ${user.rol}`;
  }
}
