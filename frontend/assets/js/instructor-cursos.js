/**
 * ============================================================================
 * INSTRUCTOR - CURSOS
 * ============================================================================
 * Maneja las funciones de la página de cursos del instructor
 * ============================================================================
 */

// Estado global
let cursosData = [];
let categoriasData = [];
let cursoEditando = null;
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
        // No pasar ?all=true para que solo devuelva los cursos del instructor autenticado
        const response = await fetch(`${API_BASE_URL}/courses`, {
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
        // No pasar ?all=true para que solo devuelva los cursos del instructor autenticado
        const response = await fetch(`${API_BASE_URL}/courses`, {
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
                        <button class="btn-course btn-course-outline" onclick="editarCurso(${curso.id_curso})">
                            ✏️ Editar
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
                Crea tu primer curso y comienza a compartir tu conocimiento.
            </p>
            <button class="btn-primary" onclick="abrirModalCurso()" style="margin-top: 1rem;">
                ➕ Crear mi primer curso
            </button>
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
    await cargarCategorias();
    await cargarEstadisticas();
    await cargarCursos();

    // Verificar si hay parámetro de edición en la URL
    const urlParams = new URLSearchParams(window.location.search);
    const editarId = urlParams.get('editar');
    if (editarId) {
        // Esperar un poco para asegurar que los datos estén cargados
        setTimeout(() => {
            abrirModalCurso(parseInt(editarId));
            // Limpiar URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }, 300);
    }
});

/**
 * Cargar categorías desde el API
 */
async function cargarCategorias() {
    try {
        const token = localStorage.getItem(AUTH_TOKEN_KEY);
        const response = await fetch(`${API_BASE_URL}/categories`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('Error al cargar categorías');
        }

        const result = await response.json();
        
        if (result.success && result.data) {
            categoriasData = result.data.data || result.data;
            actualizarSelectCategorias();
        }
    } catch (error) {
        console.error('Error al cargar categorías:', error);
    }
}

/**
 * Actualizar el select de categorías
 */
function actualizarSelectCategorias() {
    const select = document.getElementById('cursoCategoria');
    if (!select) return;

    select.innerHTML = '<option value="">Selecciona una categoría</option>';
    
    categoriasData.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat.id_categoria;
        option.textContent = cat.nombre;
        select.appendChild(option);
    });
}

/**
 * Abrir modal para crear nuevo curso
 */
function abrirModalCurso(idCurso = null) {
    const modal = document.getElementById('modalCurso');
    const form = document.getElementById('formCurso');
    
    // Resetear formulario
    form.reset();
    document.getElementById('cursoId').value = '';
    cursoEditando = null;

    if (idCurso) {
        // Modo edición
        const curso = cursosData.find(c => c.id_curso == idCurso);
        if (curso) {
            cursoEditando = curso;
            document.getElementById('modalIcon').textContent = '✏️';
            document.getElementById('modalTitulo').textContent = 'Editar Curso';
            document.getElementById('btnGuardarTexto').textContent = '💾 Actualizar Curso';
            
            // Rellenar campos
            document.getElementById('cursoId').value = curso.id_curso;
            document.getElementById('cursoTitulo').value = curso.titulo || '';
            document.getElementById('cursoCategoria').value = curso.id_categoria || '';
            document.getElementById('cursoDescripcion').value = curso.descripcion || '';
            document.getElementById('cursoNivel').value = curso.nivel || 'basico';
            document.getElementById('cursoDuracion').value = curso.duracion_horas || '';
            document.getElementById('cursoPrecio').value = curso.precio || '';
            document.getElementById('cursoEstado').value = curso.estado || 'borrador';
            document.getElementById('cursoImagen').value = curso.imagen_url || '';
        }
    } else {
        // Modo creación
        document.getElementById('modalIcon').textContent = '➕';
        document.getElementById('modalTitulo').textContent = 'Nuevo Curso';
        document.getElementById('btnGuardarTexto').textContent = '💾 Guardar Curso';
    }

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Focus en el primer campo
    setTimeout(() => {
        document.getElementById('cursoTitulo').focus();
    }, 100);
}

/**
 * Cerrar modal
 */
function cerrarModalCurso() {
    const modal = document.getElementById('modalCurso');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    cursoEditando = null;
}

/**
 * Cerrar modal si click fuera del contenido
 */
function cerrarModalSiClickFuera(event) {
    if (event.target.classList.contains('modal-overlay')) {
        cerrarModalCurso();
    }
}

/**
 * Guardar curso (crear o actualizar)
 */
async function guardarCurso(event) {
    event.preventDefault();
    
    const btn = document.getElementById('btnGuardarCurso');
    const btnTexto = document.getElementById('btnGuardarTexto');
    const textoOriginal = btnTexto.textContent;
    
    // Deshabilitar botón
    btn.disabled = true;
    btnTexto.innerHTML = '<span class="spinner-small"></span> Guardando...';

    const cursoId = document.getElementById('cursoId').value;
    const esEdicion = !!cursoId;

    const data = {
        titulo: document.getElementById('cursoTitulo').value.trim(),
        id_categoria: parseInt(document.getElementById('cursoCategoria').value),
        descripcion: document.getElementById('cursoDescripcion').value.trim(),
        nivel: document.getElementById('cursoNivel').value,
        estado: document.getElementById('cursoEstado').value
    };

    // Campos opcionales
    const duracion = document.getElementById('cursoDuracion').value;
    if (duracion) data.duracion_horas = parseInt(duracion);
    
    const precio = document.getElementById('cursoPrecio').value;
    if (precio) data.precio = parseFloat(precio);
    
    const imagen = document.getElementById('cursoImagen').value.trim();
    if (imagen) data.imagen_url = imagen;

    try {
        const token = localStorage.getItem(AUTH_TOKEN_KEY);
        const url = esEdicion 
            ? `${API_BASE_URL}/courses/${cursoId}` 
            : `${API_BASE_URL}/courses`;
        
        const response = await fetch(url, {
            method: esEdicion ? 'PUT' : 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (response.ok && result.success) {
            alert(esEdicion ? '✅ Curso actualizado exitosamente' : '✅ Curso creado exitosamente');
            cerrarModalCurso();
            
            // Recargar datos
            await cargarEstadisticas();
            await cargarCursos();
        } else {
            const errores = result.errors 
                ? Object.values(result.errors).flat().join('\n')
                : result.message || 'Error al guardar';
            alert('❌ Error:\n' + errores);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('❌ Error de conexión al guardar el curso');
    } finally {
        btn.disabled = false;
        btnTexto.textContent = textoOriginal;
    }
}

/**
 * Editar un curso existente
 */
function editarCurso(idCurso) {
    abrirModalCurso(idCurso);
}
