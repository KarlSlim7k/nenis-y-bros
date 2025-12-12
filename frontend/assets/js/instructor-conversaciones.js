/**
 * ============================================================================
 * INSTRUCTOR - CONVERSACIONES
 * ============================================================================
 * Maneja las funciones de la página de conversaciones del instructor
 * ============================================================================
 */

// Estado global
let conversacionesData = [];
let conversacionesOriginales = [];
let filtroActual = 'todas';
let busquedaActual = '';

/**
 * Cargar conversaciones del instructor
 */
async function cargarConversaciones() {
    try {
        const token = localStorage.getItem(AUTH_TOKEN_KEY);
        const response = await fetch(`${API_BASE_URL}/chat/conversaciones?estado=activa`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('Error al cargar conversaciones');
        }

        const result = await response.json();
        
        if (result.success && result.data) {
            conversacionesOriginales = result.data.conversaciones || [];
            conversacionesData = [...conversacionesOriginales];
            renderizarConversaciones();
        } else {
            mostrarEstadoVacio();
        }
    } catch (error) {
        console.error('Error al cargar conversaciones:', error);
        mostrarEstadoVacio();
    }
}

/**
 * Filtrar conversaciones
 */
function filtrarConversaciones(filtro, elementoBtn) {
    filtroActual = filtro;

    // Actualizar botones
    if (elementoBtn) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        elementoBtn.classList.add('active');
    }

    aplicarFiltros();
}

/**
 * Buscar conversación
 */
function buscarConversacion(texto) {
    busquedaActual = texto.toLowerCase();
    aplicarFiltros();
}

/**
 * Aplicar filtros y búsqueda
 */
function aplicarFiltros() {
    conversacionesData = conversacionesOriginales.filter(c => {
        // Filtro por Tab
        if (filtroActual === 'no_leidas' && (!c.no_leidos || c.no_leidos === 0)) return false;
        if (filtroActual === 'estudiantes' && c.tipo_conversacion !== 'instructor') return false;

        // Filtro por Búsqueda
        if (busquedaActual) {
            const nombreAlumno = c.alumno_nombre?.toLowerCase() || '';
            const ultimoMensaje = c.ultimo_mensaje?.toLowerCase() || '';
            
            if (!nombreAlumno.includes(busquedaActual) && !ultimoMensaje.includes(busquedaActual)) {
                return false;
            }
        }

        return true;
    });

    renderizarConversaciones();
}

/**
 * Renderizar conversaciones
 */
function renderizarConversaciones() {
    const contenedor = document.getElementById('conversations-grid');
    const emptyState = document.getElementById('empty-state');

    if (!contenedor) {
        console.error('No se encontró el contenedor de conversaciones');
        return;
    }

    if (!conversacionesData || conversacionesData.length === 0) {
        contenedor.style.display = 'none';
        emptyState.style.display = 'flex';
        return;
    }

    contenedor.style.display = 'grid';
    emptyState.style.display = 'none';

    contenedor.innerHTML = conversacionesData.map(conv => {
        const avatar = (conv.alumno_nombre || 'E').charAt(0).toUpperCase();
        const nombreAlumno = conv.alumno_nombre || 'Estudiante';
        const rol = conv.alumno_tipo || 'Estudiante';
        const ultimoMensaje = conv.ultimo_mensaje || 'Sin mensajes';
        const fecha = conv.fecha_ultimo_mensaje ? new Date(conv.fecha_ultimo_mensaje) : new Date();
        const noLeidos = parseInt(conv.no_leidos) || 0;
        const tipo = conv.tipo_conversacion || 'instructor';

        return `
            <div class="conversation-card" onclick="irAlChat(${conv.id_conversacion})">
                <div class="card-header">
                    <div class="conversation-avatar" style="${tipo === 'sistema' ? 'background: var(--gradient-accent);' : ''}">
                        ${avatar}
                    </div>
                    <div class="conversation-info">
                        <div class="conversation-name">${nombreAlumno}</div>
                        <div class="conversation-role">
                            ${tipo === 'instructor' ? '👤' : '🛠️'} ${rol}
                        </div>
                    </div>
                    ${noLeidos > 0 ? `
                        <div class="unread-badge">${noLeidos}</div>
                    ` : ''}
                </div>
                
                <div class="conversation-preview">
                    ${ultimoMensaje}
                </div>

                <div class="card-footer">
                    <div class="conversation-time">
                        🕒 ${formatearFecha(fecha)}
                    </div>
                    <div style="color: var(--primary); font-size: 0.8rem; font-weight: 500;">
                        Ver chat →
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

/**
 * Mostrar estado vacío
 */
function mostrarEstadoVacio() {
    const contenedor = document.getElementById('conversations-grid');
    const emptyState = document.getElementById('empty-state');

    if (contenedor && emptyState) {
        contenedor.style.display = 'none';
        emptyState.style.display = 'flex';
    }
}

/**
 * Formatear fecha relativa
 */
function formatearFecha(date) {
    const ahora = new Date();
    const diff = ahora - date;
    const minutos = Math.floor(diff / 60000);
    const horas = Math.floor(minutos / 60);
    const dias = Math.floor(horas / 24);

    if (minutos < 1) return 'Ahora';
    if (minutos < 60) return `Hace ${minutos} min`;
    if (horas < 24) return `Hace ${horas} h`;
    if (dias === 1) return 'Ayer';
    if (dias < 7) return `Hace ${dias} días`;
    
    return date.toLocaleDateString('es-MX', { 
        day: 'numeric', 
        month: 'short',
        year: date.getFullYear() !== ahora.getFullYear() ? 'numeric' : undefined
    });
}

/**
 * Ir al chat
 */
function irAlChat(idConversacion) {
    window.location.href = `./chat.html?id=${idConversacion}`;
}

/**
 * Cargar datos de usuario
 */
function cargarDatosUsuario() {
    const user = getAuthUser();
    if (user) {
        document.getElementById('userName').textContent = user.nombre || 'Mentor';
        const avatar = document.querySelector('.mentor-avatar-glow');
        if (avatar && user.nombre) {
            avatar.textContent = user.nombre.charAt(0).toUpperCase();
        }
    }
}

/**
 * Función logout
 */
function logout() {
    localStorage.removeItem(AUTH_TOKEN_KEY);
    localStorage.removeItem(AUTH_USER_KEY);
    window.location.href = ROUTES.login;
}

/**
 * Inicializar página
 */
document.addEventListener('DOMContentLoaded', async function() {
    // Verificar autenticación
    if (!checkAuth() || !isMentor()) {
        alert('Acceso denegado. Solo instructores pueden acceder a esta página.');
        window.location.href = ROUTES.login;
        return;
    }

    cargarDatosUsuario();
    await cargarConversaciones();
});
