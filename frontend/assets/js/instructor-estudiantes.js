/**
 * ============================================================================
 * INSTRUCTOR - ESTUDIANTES
 * ============================================================================
 * Maneja las funciones de la página de estudiantes del instructor
 * Obtiene estudiantes de todos los cursos del instructor
 * ============================================================================
 */

// Estado global
let estudiantesData = [];
let estudiantesOriginales = [];
let statsData = {
    total: 0,
    activos: 0,
    sesiones: 0,
    progresoPromedio: 0
};

/**
 * Cargar estudiantes de todos los cursos del instructor
 */
async function cargarEstudiantes() {
    try {
        const token = localStorage.getItem(AUTH_TOKEN_KEY);
        
        // Primero obtenemos todos los cursos del instructor
        const cursosResponse = await fetch(`${API_BASE_URL}/courses?all=true`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });

        if (!cursosResponse.ok) {
            throw new Error('Error al cargar cursos');
        }

        const cursosResult = await cursosResponse.json();
        
        if (!cursosResult.success || !cursosResult.data) {
            mostrarEstadoVacio();
            return;
        }

        const cursos = cursosResult.data.data || cursosResult.data;
        
        if (!cursos || cursos.length === 0) {
            mostrarEstadoVacio();
            return;
        }

        // Obtener estudiantes de cada curso
        const estudiantesPorCurso = await Promise.all(
            cursos.map(async (curso) => {
                try {
                    const response = await fetch(`${API_BASE_URL}/courses/${curso.id_curso}/students`, {
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json'
                        }
                    });

                    if (!response.ok) return [];

                    const result = await response.json();
                    const estudiantes = result.data?.students || result.data || [];
                    
                    // Agregar información del curso a cada estudiante
                    return estudiantes.map(est => ({
                        ...est,
                        curso_nombre: curso.titulo,
                        curso_id: curso.id_curso
                    }));
                } catch (error) {
                    console.error(`Error al cargar estudiantes del curso ${curso.id_curso}:`, error);
                    return [];
                }
            })
        );

        // Combinar todos los estudiantes y eliminar duplicados
        const todosEstudiantes = estudiantesPorCurso.flat();
        
        // Agrupar estudiantes por ID para evitar duplicados
        const estudiantesUnicos = new Map();
        todosEstudiantes.forEach(est => {
            const id = est.id_usuario;
            if (!estudiantesUnicos.has(id)) {
                estudiantesUnicos.set(id, {
                    ...est,
                    cursos: [{ id: est.curso_id, nombre: est.curso_nombre }],
                    total_sesiones: 0 // Esto se puede obtener de otro endpoint si existe
                });
            } else {
                // Agregar curso adicional
                const existente = estudiantesUnicos.get(id);
                existente.cursos.push({ id: est.curso_id, nombre: est.curso_nombre });
            }
        });

        estudiantesOriginales = Array.from(estudiantesUnicos.values());
        estudiantesData = [...estudiantesOriginales];

        // Calcular estadísticas
        calcularEstadisticas();
        
        // Renderizar tabla
        renderizarEstudiantes();
        
    } catch (error) {
        console.error('Error al cargar estudiantes:', error);
        mostrarError('Error al cargar los estudiantes. Por favor, intenta de nuevo.');
    }
}

/**
 * Calcular estadísticas de los estudiantes
 */
function calcularEstadisticas() {
    statsData.total = estudiantesData.length;
    
    // Contar activos (con progreso > 0 o estado activo)
    statsData.activos = estudiantesData.filter(est => {
        const progreso = parseFloat(est.progreso_porcentaje) || 0;
        return progreso > 0 || est.estado === 'activo';
    }).length;
    
    // Calcular sesiones totales (si está disponible)
    statsData.sesiones = estudiantesData.reduce((sum, est) => {
        return sum + (parseInt(est.total_sesiones) || 0);
    }, 0);
    
    // Calcular progreso promedio
    if (estudiantesData.length > 0) {
        const sumaProgreso = estudiantesData.reduce((sum, est) => {
            return sum + (parseFloat(est.progreso_porcentaje) || 0);
        }, 0);
        statsData.progresoPromedio = Math.round(sumaProgreso / estudiantesData.length);
    } else {
        statsData.progresoPromedio = 0;
    }
    
    actualizarEstadisticas();
}

/**
 * Actualizar el DOM con las estadísticas
 */
function actualizarEstadisticas() {
    document.getElementById('totalStudents').textContent = statsData.total;
    document.getElementById('activeStudents').textContent = statsData.activos;
    document.getElementById('sessionsCount').textContent = statsData.sesiones;
    document.getElementById('avgProgress').textContent = `${statsData.progresoPromedio}%`;
}

/**
 * Renderizar la tabla de estudiantes
 */
function renderizarEstudiantes() {
    const tbody = document.getElementById('studentsTableBody');
    
    if (!tbody) {
        console.error('No se encontró el tbody de la tabla');
        return;
    }

    if (!estudiantesData || estudiantesData.length === 0) {
        mostrarEstadoVacio();
        return;
    }

    tbody.innerHTML = estudiantesData.map(estudiante => {
        const inicial = (estudiante.nombre || 'E').charAt(0).toUpperCase();
        const nombreCompleto = `${estudiante.nombre || ''} ${estudiante.apellido || ''}`.trim();
        const email = estudiante.email || 'Sin email';
        const progreso = parseFloat(estudiante.progreso_porcentaje) || 0;
        const sesiones = estudiante.total_sesiones || 0;
        const cursosCount = estudiante.cursos?.length || 1;
        
        // Determinar última sesión (si está disponible)
        let ultimaSesion = 'N/A';
        if (estudiante.fecha_inscripcion) {
            const fecha = new Date(estudiante.fecha_inscripcion);
            ultimaSesion = fecha.toLocaleDateString('es-MX', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        }
        
        // Determinar estado
        let estadoClass = 'badge-inactivo';
        let estadoTexto = '○ Inactivo';
        
        if (progreso > 0) {
            estadoClass = 'badge-activo';
            estadoTexto = '✓ Activo';
        }

        return `
            <tr>
                <td>
                    <div class="student-cell">
                        <div class="student-avatar">${inicial}</div>
                        <div class="student-info">
                            <span class="student-name">${nombreCompleto}</span>
                            <span class="student-email">${email}</span>
                        </div>
                    </div>
                </td>
                <td>${sesiones}</td>
                <td>${ultimaSesion}</td>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="flex: 1; background: rgba(255,255,255,0.1); height: 6px; border-radius: 3px; overflow: hidden;">
                            <div style="width: ${progreso}%; height: 100%; background: var(--gradient-primary); border-radius: 3px;"></div>
                        </div>
                        <span style="font-size: 0.85rem; color: var(--text-muted);">${progreso}%</span>
                    </div>
                </td>
                <td><span class="badge ${estadoClass}">${estadoTexto}</span></td>
                <td>
                    <button class="btn-action btn-view" onclick="verEstudiante(${estudiante.id_usuario})">
                        👁️ Ver Perfil
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Mostrar estado vacío cuando no hay estudiantes
 */
function mostrarEstadoVacio() {
    const tbody = document.getElementById('studentsTableBody');
    
    if (!tbody) return;

    tbody.innerHTML = `
        <tr>
            <td colspan="6">
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <div class="empty-title">No hay estudiantes asignados</div>
                    <div class="empty-text">Los estudiantes aparecerán aquí cuando se inscriban en tus cursos</div>
                </div>
            </td>
        </tr>
    `;
    
    // Limpiar estadísticas
    document.getElementById('totalStudents').textContent = '0';
    document.getElementById('activeStudents').textContent = '0';
    document.getElementById('sessionsCount').textContent = '0';
    document.getElementById('avgProgress').textContent = '0%';
}

/**
 * Mostrar mensaje de error
 */
function mostrarError(mensaje) {
    const tbody = document.getElementById('studentsTableBody');
    
    if (!tbody) return;

    tbody.innerHTML = `
        <tr>
            <td colspan="6">
                <div class="empty-state">
                    <div class="empty-icon">⚠️</div>
                    <div class="empty-title">Error al cargar estudiantes</div>
                    <div class="empty-text">${mensaje}</div>
                    <button class="btn-primary" onclick="cargarEstudiantes()" style="margin-top: 1rem;">
                        🔄 Reintentar
                    </button>
                </div>
            </td>
        </tr>
    `;
}

/**
 * Filtrar estudiantes por búsqueda
 */
function filterStudents() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    
    if (!searchTerm) {
        estudiantesData = [...estudiantesOriginales];
    } else {
        estudiantesData = estudiantesOriginales.filter(est => {
            const nombreCompleto = `${est.nombre} ${est.apellido}`.toLowerCase();
            const email = (est.email || '').toLowerCase();
            return nombreCompleto.includes(searchTerm) || email.includes(searchTerm);
        });
    }
    
    calcularEstadisticas();
    renderizarEstudiantes();
}

/**
 * Ver perfil de un estudiante
 */
function verEstudiante(idEstudiante) {
    // Redirigir a la página de perfil del estudiante
    window.location.href = `./estudiante-perfil.html?id=${idEstudiante}`;
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
 * Highlight active link
 */
function highlightActiveLink() {
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-menu .nav-link').forEach(link => {
        link.classList.remove('active');
        const href = link.getAttribute('href');
        if (href === './' + currentPage) {
            link.classList.add('active');
        }
    });
}

/**
 * Inicializar página
 */
document.addEventListener('DOMContentLoaded', async function () {
    // Verificar autenticación
    if (!checkAuth() || !isMentor()) {
        alert('Acceso denegado. Solo instructores pueden acceder a esta página.');
        window.location.href = ROUTES.login;
        return;
    }

    cargarDatosUsuario();
    highlightActiveLink();
    await cargarEstudiantes();
});
