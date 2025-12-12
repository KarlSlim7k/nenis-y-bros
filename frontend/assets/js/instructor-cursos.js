/**
 * ============================================================================
 * INSTRUCTOR - CURSOS
 * ============================================================================
 * Maneja las funciones de la página de cursos del instructor
 * ============================================================================
 */

// Estado global
let cursosData = [];
let statsData = {
    totalCursos: 0,
    activos: 0,
    totalEstudiantes: 0,
    promedioCalificacion: 0
};

/**
 * Cargar estadísticas del instructor
 */
async function cargarEstadisticas() {
    try {
        const token = localStorage.getItem(AUTH_TOKEN_KEY);
        const response = await fetch(`${API_BASE_URL}/courses?all=true`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('Error al cargar estadísticas');
        }

        const result = await response.json();
        
        if (result.success && result.data) {
            const cursos = result.data.data || result.data;
            
            // Calcular estadísticas
            statsData.totalCursos = cursos.length;
            statsData.activos = cursos.filter(c => c.estado === 'publicado').length;
            statsData.totalEstudiantes = cursos.reduce((sum, c) => sum + (parseInt(c.total_inscripciones) || 0), 0);
            
            // Calcular promedio de calificaciones
            const cursosConCalificacion = cursos.filter(c => c.promedio_calificacion > 0);
            if (cursosConCalificacion.length > 0) {
                statsData.promedioCalificacion = (
                    cursosConCalificacion.reduce((sum, c) => sum + parseFloat(c.promedio_calificacion), 0) / 
                    cursosConCalificacion.length
                ).toFixed(1);
            }
            
            actualizarEstadisticas();
        }
    } catch (error) {
        console.error('Error al cargar estadísticas:', error);
    }
}

/**
 * Actualizar el DOM con las estadísticas
 */
function actualizarEstadisticas() {
    const statsMini = document.querySelector('.stats-mini');
    if (statsMini) {
        statsMini.innerHTML = `
            <div class="stat-mini-card">
                <span class="stat-mini-icon">📚</span>
                <div>
                    <div class="stat-mini-value">${statsData.totalCursos}</div>
                    <div class="stat-mini-label">Total Cursos</div>
                </div>
            </div>
            <div class="stat-mini-card">
                <span class="stat-mini-icon">✅</span>
                <div>
                    <div class="stat-mini-value">${statsData.activos}</div>
                    <div class="stat-mini-label">Activos</div>
                </div>
            </div>
            <div class="stat-mini-card">
                <span class="stat-mini-icon">👥</span>
                <div>
                    <div class="stat-mini-value">${statsData.totalEstudiantes}</div>
                    <div class="stat-mini-label">Estudiantes</div>
                </div>
            </div>
            <div class="stat-mini-card">
                <span class="stat-mini-icon">⭐</span>
                <div>
                    <div class="stat-mini-value">${statsData.promedioCalificacion || 'N/A'}</div>
                    <div class="stat-mini-label">Calificación</div>
                </div>
            </div>
        `;
    }
}

/**
 * Cargar cursos del instructor
 */
async function cargarCursos() {
    try {
        const token = localStorage.getItem(AUTH_TOKEN_KEY);
        const response = await fetch(`${API_BASE_URL}/courses?all=true`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('Error al cargar cursos');
        }

        const result = await response.json();
        
        if (result.success && result.data) {
            cursosData = result.data.data || result.data;
            renderizarCursos();
        } else {
            mostrarEstadoVacio();
        }
    } catch (error) {
        console.error('Error al cargar cursos:', error);
        mostrarError('Error al cargar los cursos. Por favor, intenta de nuevo.');
    }
}

/**
 * Renderizar la lista de cursos
 */
function renderizarCursos() {
    const coursesGrid = document.querySelector('.courses-grid');
    
    if (!coursesGrid) {
        console.error('No se encontró el contenedor de cursos');
        return;
    }

    if (!cursosData || cursosData.length === 0) {
        mostrarEstadoVacio();
        return;
    }

    const gradients = ['gradient-1', 'gradient-2', 'gradient-3', 'gradient-4'];
    const emojis = ['📚', '💼', '📊', '🎯', '🚀', '💡', '🔬', '🎨'];

    coursesGrid.innerHTML = cursosData.map((curso, index) => {
        const gradientClass = gradients[index % gradients.length];
        const emoji = emojis[index % emojis.length];
        const totalLecciones = parseInt(curso.total_lecciones) || 0;
        const totalInscripciones = parseInt(curso.total_inscripciones) || 0;
        const promedioCalificacion = parseFloat(curso.promedio_calificacion) || 0;
        
        // Calcular progreso promedio (si está disponible)
        const progresoPromedio = curso.progreso_promedio || 0;
        
        // Determinar etiqueta y clase según estado
        let tagClass = 'tag-borrador';
        let tagText = '📝 Borrador';
        
        if (curso.estado === 'publicado') {
            tagClass = 'tag-activo';
            tagText = '✓ Activo';
        }

        return `
            <div class="course-card">
                <div class="course-image ${gradientClass}">
                    <span>${emoji}</span>
                    <span class="course-tag ${tagClass}">${tagText}</span>
                </div>
                <div class="course-body">
                    <h3 class="course-title">${curso.titulo}</h3>
                    <p class="course-description">${curso.descripcion_corta || curso.descripcion || 'Sin descripción disponible'}</p>
                    <div class="course-meta">
                        <span class="course-meta-item">👥 ${totalInscripciones} estudiantes</span>
                        <span class="course-meta-item">📖 ${totalLecciones} lecciones</span>
                    </div>
                    ${curso.estado === 'publicado' && totalInscripciones > 0 ? `
                    <div class="course-progress">
                        <div class="progress-header">
                            <span class="progress-label">Progreso promedio</span>
                            <span class="progress-value">${progresoPromedio}%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: ${progresoPromedio}%;"></div>
                        </div>
                    </div>
                    ` : ''}
                    <div class="course-actions">
                        <button class="btn-course btn-course-primary" onclick="verCurso(${curso.id_curso})">
                            👁️ Ver Curso
                        </button>
                        <button class="btn-course btn-course-outline" onclick="verEstudiantes(${curso.id_curso})">
                            👥 Estudiantes
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

/**
 * Mostrar estado vacío cuando no hay cursos
 */
function mostrarEstadoVacio() {
    const coursesGrid = document.querySelector('.courses-grid');
    
    if (!coursesGrid) return;

    coursesGrid.innerHTML = `
        <div class="empty-state" style="grid-column: 1/-1;">
            <div class="empty-icon">📚</div>
            <h3 class="empty-title">No hay cursos todavía</h3>
            <p class="empty-text">
                Aún no tienes cursos asignados. Contacta al administrador para que te asignen cursos.
            </p>
        </div>
    `;
}

/**
 * Mostrar mensaje de error
 */
function mostrarError(mensaje) {
    const coursesGrid = document.querySelector('.courses-grid');
    
    if (!coursesGrid) return;

    coursesGrid.innerHTML = `
        <div class="empty-state" style="grid-column: 1/-1;">
            <div class="empty-icon">⚠️</div>
            <h3 class="empty-title">Error al cargar cursos</h3>
            <p class="empty-text">${mensaje}</p>
            <button class="btn-primary" onclick="cargarCursos()" style="margin-top: 1rem;">
                🔄 Reintentar
            </button>
        </div>
    `;
}

/**
 * Ver detalles de un curso
 */
function verCurso(idCurso) {
    // Redirigir a la página de detalle del curso
    window.location.href = `./curso-detalle.html?id=${idCurso}`;
}

/**
 * Ver estudiantes de un curso
 */
function verEstudiantes(idCurso) {
    // Redirigir a la página de estudiantes del curso
    window.location.href = `./curso-estudiantes.html?id=${idCurso}`;
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

    // Cargar datos
    await cargarEstadisticas();
    await cargarCursos();
});
