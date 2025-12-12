/**
 * instructor-chat.js
 * Maneja la interfaz de chat/mensajería para instructores
 */

let conversacionActual = null;
let conversaciones = [];
let intervaloPoll = null;

// Inicializar
document.addEventListener('DOMContentLoaded', async function () {
    // Verificar autenticación
    if (!verificarAutenticacion()) {
        return;
    }

    // Cargar conversaciones
    await cargarConversaciones();

    // Si hay un ID en la URL, abrir esa conversación
    const urlParams = new URLSearchParams(window.location.search);
    const idConversacion = urlParams.get('id');
    if (idConversacion) {
        await seleccionarConversacion(parseInt(idConversacion));
    }

    // Event listeners
    document.getElementById('search-conversations').addEventListener('input', filtrarConversaciones);
    document.getElementById('btn-send').addEventListener('click', enviarMensaje);
    document.getElementById('chat-input').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            enviarMensaje();
        }
    });
    document.getElementById('btn-archive').addEventListener('click', archivarConversacion);
});

/**
 * Cargar conversaciones del instructor
 */
async function cargarConversaciones() {
    const loadingEl = document.getElementById('conversations-loading');
    const emptyEl = document.getElementById('conversations-empty');
    const listEl = document.getElementById('conversations-list');

    loadingEl.style.display = 'block';
    emptyEl.style.display = 'none';
    listEl.innerHTML = '';

    try {
        const response = await fetch(`${API_BASE_URL}/chat/conversaciones?estado=activa`, {
            headers: {
                'Authorization': `Bearer ${obtenerToken()}`,
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('Error al cargar conversaciones');
        }

        const data = await response.json();
        conversaciones = data.data || [];

        loadingEl.style.display = 'none';

        if (conversaciones.length === 0) {
            emptyEl.style.display = 'block';
            return;
        }

        renderizarConversaciones(conversaciones);
    } catch (error) {
        console.error('Error al cargar conversaciones:', error);
        loadingEl.style.display = 'none';
        emptyEl.textContent = 'Error al cargar las conversaciones.';
        emptyEl.style.display = 'block';
        mostrarError('Error al cargar las conversaciones');
    }
}

/**
 * Renderizar lista de conversaciones
 */
function renderizarConversaciones(lista) {
    const listEl = document.getElementById('conversations-list');
    listEl.innerHTML = '';

    lista.forEach(conv => {
        const item = document.createElement('div');
        item.className = 'conversation-item';
        if (conversacionActual && conversacionActual.id_conversacion === conv.id_conversacion) {
            item.classList.add('active');
        }

        // Badge de mensajes no leídos
        const badgeUnread = conv.mensajes_no_leidos > 0
            ? `<span class="unread-badge">${conv.mensajes_no_leidos}</span>`
            : '';

        // Tiempo del último mensaje
        const tiempo = conv.ultimo_mensaje_fecha
            ? formatearTiempo(conv.ultimo_mensaje_fecha)
            : '';

        item.innerHTML = `
            <div class="mentor-avatar-glow" style="width: 48px; height: 48px; font-size: 1.2rem;">
                ${obtenerIniciales(conv.nombre_estudiante || 'Estudiante')}
            </div>
            <div style="flex: 1; min-width: 0;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="conversation-name">${conv.nombre_estudiante || 'Estudiante'}</div>
                    <div class="conversation-time">${tiempo}</div>
                </div>
                <div class="conversation-preview">${conv.ultimo_mensaje || 'Sin mensajes aún'}</div>
            </div>
            ${badgeUnread}
        `;

        item.addEventListener('click', () => seleccionarConversacion(conv.id_conversacion));
        listEl.appendChild(item);
    });
}

/**
 * Seleccionar una conversación
 */
async function seleccionarConversacion(idConversacion) {
    const chatPanel = document.getElementById('chat-panel');
    const chatEmpty = document.getElementById('chat-empty');
    const messagesEl = document.getElementById('chat-messages');

    // Detener polling anterior
    if (intervaloPoll) {
        clearInterval(intervaloPoll);
        intervaloPoll = null;
    }

    // Buscar conversación en la lista
    const conv = conversaciones.find(c => c.id_conversacion === idConversacion);
    if (!conv) {
        mostrarError('Conversación no encontrada');
        return;
    }

    conversacionActual = conv;

    // Actualizar header
    document.getElementById('chat-user-name').textContent = conv.nombre_estudiante || 'Estudiante';
    document.getElementById('chat-user-avatar').textContent = obtenerIniciales(conv.nombre_estudiante || 'E');
    document.getElementById('chat-user-status').textContent = '● Activo';
    document.getElementById('btn-archive').disabled = false;

    // Mostrar panel de chat
    chatPanel.style.display = 'flex';
    chatEmpty.style.display = 'none';

    // Marcar como activa en la lista
    document.querySelectorAll('.conversation-item').forEach(item => item.classList.remove('active'));
    const activeItem = Array.from(document.querySelectorAll('.conversation-item')).find(
        item => item.textContent.includes(conv.nombre_estudiante)
    );
    if (activeItem) {
        activeItem.classList.add('active');
    }

    // Cargar mensajes
    await cargarMensajes(idConversacion);

    // Iniciar polling cada 5 segundos
    intervaloPoll = setInterval(() => cargarMensajesNuevos(idConversacion), 5000);
}

/**
 * Cargar mensajes de una conversación
 */
async function cargarMensajes(idConversacion) {
    const messagesEl = document.getElementById('chat-messages');
    messagesEl.innerHTML = '<div style="text-align:center; padding:1rem; color: var(--text-muted);">Cargando mensajes...</div>';

    try {
        const response = await fetch(`${API_BASE_URL}/chat/conversaciones/${idConversacion}`, {
            headers: {
                'Authorization': `Bearer ${obtenerToken()}`,
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('Error al cargar mensajes');
        }

        const data = await response.json();
        const mensajes = data.data.mensajes || [];

        renderizarMensajes(mensajes);
        scrollToBottom();
    } catch (error) {
        console.error('Error al cargar mensajes:', error);
        messagesEl.innerHTML = '<div style="text-align:center; padding:1rem; color: var(--danger);">Error al cargar los mensajes.</div>';
    }
}

/**
 * Cargar solo mensajes nuevos (para polling)
 */
async function cargarMensajesNuevos(idConversacion) {
    if (!conversacionActual || conversacionActual.id_conversacion !== idConversacion) {
        return;
    }

    try {
        // Obtener el ID del último mensaje visible
        const messagesEl = document.getElementById('chat-messages');
        const lastMessage = messagesEl.querySelector('.chat-message:last-child');
        const ultimoId = lastMessage ? parseInt(lastMessage.dataset.messageId || '0') : 0;

        const response = await fetch(`${API_BASE_URL}/chat/mensajes/${idConversacion}/nuevos?ultimo_id=${ultimoId}`, {
            headers: {
                'Authorization': `Bearer ${obtenerToken()}`,
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) return;

        const data = await response.json();
        const mensajesNuevos = data.data || [];

        if (mensajesNuevos.length > 0) {
            mensajesNuevos.forEach(mensaje => {
                agregarMensaje(mensaje);
            });
            scrollToBottom();
        }
    } catch (error) {
        console.error('Error al cargar mensajes nuevos:', error);
    }
}

/**
 * Renderizar todos los mensajes
 */
function renderizarMensajes(mensajes) {
    const messagesEl = document.getElementById('chat-messages');
    messagesEl.innerHTML = '';

    if (mensajes.length === 0) {
        messagesEl.innerHTML = '<div style="text-align:center; padding:1.5rem; color: var(--text-muted);">No hay mensajes aún. ¡Inicia la conversación!</div>';
        return;
    }

    mensajes.forEach(mensaje => {
        agregarMensaje(mensaje);
    });
}

/**
 * Agregar un mensaje al chat
 */
function agregarMensaje(mensaje) {
    const messagesEl = document.getElementById('chat-messages');
    const isMine = mensaje.es_propio || false; // El backend debe indicar si es del usuario actual

    const messageDiv = document.createElement('div');
    messageDiv.className = `chat-message ${isMine ? 'sent' : 'received'}`;
    messageDiv.dataset.messageId = mensaje.id_mensaje;

    const tiempo = mensaje.fecha_envio ? formatearHora(mensaje.fecha_envio) : '';

    messageDiv.innerHTML = `
        <div class="message-bubble">
            ${mensaje.mensaje}
            <div class="message-time">${tiempo}</div>
        </div>
    `;

    messagesEl.appendChild(messageDiv);
}

/**
 * Enviar un mensaje
 */
async function enviarMensaje() {
    if (!conversacionActual) {
        mostrarError('Selecciona una conversación primero');
        return;
    }

    const inputEl = document.getElementById('chat-input');
    const mensaje = inputEl.value.trim();

    if (mensaje === '') {
        return;
    }

    // Deshabilitar input mientras se envía
    inputEl.disabled = true;
    document.getElementById('btn-send').disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}/chat/mensajes`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${obtenerToken()}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_conversacion: conversacionActual.id_conversacion,
                mensaje: mensaje
            })
        });

        if (!response.ok) {
            throw new Error('Error al enviar mensaje');
        }

        const data = await response.json();

        // Agregar mensaje al chat
        agregarMensaje({
            id_mensaje: data.data.id_mensaje,
            mensaje: mensaje,
            fecha_envio: new Date().toISOString(),
            es_propio: true
        });

        // Limpiar input
        inputEl.value = '';
        scrollToBottom();
    } catch (error) {
        console.error('Error al enviar mensaje:', error);
        mostrarError('Error al enviar el mensaje');
    } finally {
        inputEl.disabled = false;
        document.getElementById('btn-send').disabled = false;
        inputEl.focus();
    }
}

/**
 * Archivar conversación
 */
async function archivarConversacion() {
    if (!conversacionActual) return;

    if (!confirm('¿Estás seguro de que deseas archivar esta conversación?')) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}/chat/conversaciones/${conversacionActual.id_conversacion}/archivar`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${obtenerToken()}`,
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('Error al archivar conversación');
        }

        mostrarExito('Conversación archivada');

        // Recargar conversaciones
        conversacionActual = null;
        document.getElementById('chat-panel').style.display = 'none';
        await cargarConversaciones();
    } catch (error) {
        console.error('Error al archivar conversación:', error);
        mostrarError('Error al archivar la conversación');
    }
}

/**
 * Filtrar conversaciones por búsqueda
 */
function filtrarConversaciones() {
    const searchTerm = document.getElementById('search-conversations').value.toLowerCase();

    if (searchTerm === '') {
        renderizarConversaciones(conversaciones);
        return;
    }

    const filtradas = conversaciones.filter(conv => {
        const nombre = (conv.nombre_estudiante || '').toLowerCase();
        const ultimoMsg = (conv.ultimo_mensaje || '').toLowerCase();
        return nombre.includes(searchTerm) || ultimoMsg.includes(searchTerm);
    });

    renderizarConversaciones(filtradas);
}

/**
 * Scroll al final del chat
 */
function scrollToBottom() {
    const messagesEl = document.getElementById('chat-messages');
    messagesEl.scrollTop = messagesEl.scrollHeight;
}

/**
 * Obtener iniciales de un nombre
 */
function obtenerIniciales(nombre) {
    if (!nombre) return '?';
    const partes = nombre.trim().split(' ');
    if (partes.length >= 2) {
        return (partes[0][0] + partes[1][0]).toUpperCase();
    }
    return nombre[0].toUpperCase();
}

/**
 * Formatear tiempo relativo (ej: "hace 5 min", "ayer")
 */
function formatearTiempo(fecha) {
    const ahora = new Date();
    const entonces = new Date(fecha);
    const diffMs = ahora - entonces;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHoras = Math.floor(diffMs / 3600000);
    const diffDias = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'ahora';
    if (diffMins < 60) return `hace ${diffMins} min`;
    if (diffHoras < 24) return `hace ${diffHoras}h`;
    if (diffDias === 1) return 'ayer';
    if (diffDias < 7) return `hace ${diffDias}d`;

    // Formato de fecha corta
    return entonces.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit' });
}

/**
 * Formatear hora (ej: "14:35")
 */
function formatearHora(fecha) {
    const date = new Date(fecha);
    return date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
}

/**
 * Mostrar mensaje de error
 */
function mostrarError(mensaje) {
    // Implementación simple con alert, puedes mejorarla con toast
    alert(mensaje);
}

/**
 * Mostrar mensaje de éxito
 */
function mostrarExito(mensaje) {
    alert(mensaje);
}

/**
 * Cleanup al salir de la página
 */
window.addEventListener('beforeunload', () => {
    if (intervaloPoll) {
        clearInterval(intervaloPoll);
    }
});
