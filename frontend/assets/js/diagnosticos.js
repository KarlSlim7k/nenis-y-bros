/**
 * ============================================================================
 * DIAGNÓSTICOS - Frontend JavaScript
 * ============================================================================
 * Gestiona la lista de diagnósticos disponibles y el historial del usuario
 * ============================================================================
 */

// Estado
let tiposDiagnostico = [];
let misDiagnosticos = [];
let currentPage = 1;
const itemsPerPage = 10;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    checkAuth();
    loadData();
    setupEventListeners();
});

/**
 * Cargar todos los datos necesarios
 */
async function loadData() {
    showLoading();
    
    try {
        // Verificar si tiene perfil empresarial
        const perfilResponse = await apiGet('/perfiles/mi-perfil');
        
        if (!perfilResponse.data.existe) {
            showNeedsPerfil();
            return;
        }
        
        // Cargar tipos de diagnóstico y mis diagnósticos en paralelo
        await Promise.all([
            loadTiposDiagnostico(),
            loadMisDiagnosticos()
        ]);
        
        showDiagnosticosContainer();
        
    } catch (error) {
        console.error('Error al cargar datos:', error);
        showAlert('Error al cargar los diagnósticos', 'error');
    } finally {
        hideLoading();
    }
}

/**
 * Cargar tipos de diagnóstico disponibles
 */
async function loadTiposDiagnostico() {
    try {
        const response = await apiGet('/diagnosticos/tipos');
        tiposDiagnostico = response.data.tipos || [];
        
        renderTiposDiagnostico();
        populateTipoFilter();
        
    } catch (error) {
        console.error('Error al cargar tipos de diagnóstico:', error);
        throw error;
    }
}

/**
 * Cargar diagnósticos del usuario
 */
async function loadMisDiagnosticos(filters = {}) {
    try {
        const params = {
            page: currentPage,
            limit: itemsPerPage,
            ...filters
        };
        
        const response = await apiGet('/diagnosticos/mis-diagnosticos', params);
        misDiagnosticos = response.data.diagnosticos || [];
        
        renderMisDiagnosticos();
        
        // Pagination
        if (response.data.pagination) {
            renderPagination(response.data.pagination);
        }
        
    } catch (error) {
        console.error('Error al cargar mis diagnósticos:', error);
        throw error;
    }
}

/**
 * Renderizar tipos de diagnóstico disponibles
 */
function renderTiposDiagnostico() {
    const container = document.getElementById('tiposDiagnosticoGrid');
    
    if (tiposDiagnostico.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <p>No hay diagnósticos disponibles en este momento</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = tiposDiagnostico.map(tipo => `
        <div class="diagnostico-card">
            <div class="diagnostico-card-icon">${tipo.icono || '📊'}</div>
            <h3 class="diagnostico-card-title">${tipo.nombre}</h3>
            <p class="diagnostico-card-description">${tipo.descripcion || ''}</p>
            
            <div class="diagnostico-card-meta">
                <div class="meta-item">
                    <span class="meta-icon">📝</span>
                    <span>${tipo.total_preguntas || 0} preguntas</span>
                </div>
                <div class="meta-item">
                    <span class="meta-icon">⏱️</span>
                    <span>${tipo.duracion_estimada || 15} min</span>
                </div>
            </div>
            
            <button 
                class="btn btn-primary btn-block"
                onclick="iniciarDiagnostico(${tipo.id_tipo_diagnostico})"
            >
                Iniciar Diagnóstico
            </button>
        </div>
    `).join('');
}

/**
 * Renderizar mis diagnósticos
 */
function renderMisDiagnosticos() {
    const container = document.getElementById('diagnosticosList');
    const emptyState = document.getElementById('noDiagnosticos');
    
    if (misDiagnosticos.length === 0) {
        container.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    
    container.innerHTML = misDiagnosticos.map(diag => {
        const estado = diag.estado || 'en_progreso';
        const isCompleto = estado === 'completado';
        const fechaDisplay = isCompleto 
            ? Utils.formatDate(diag.fecha_finalizacion || diag.fecha_completado)
            : Utils.formatDate(diag.fecha_inicio);
        
        return `
            <div class="diagnostico-item">
                <div class="diagnostico-item-header">
                    <div>
                        <h3 class="diagnostico-item-title">${diag.nombre_diagnostico}</h3>
                        <p class="diagnostico-item-date">
                            ${isCompleto ? 'Completado el' : 'Iniciado el'} ${fechaDisplay}
                        </p>
                    </div>
                    <span class="badge badge-${isCompleto ? 'success' : 'warning'}">
                        ${isCompleto ? 'Completado' : 'En Progreso'}
                    </span>
                </div>
                
                ${isCompleto ? `
                    <div class="diagnostico-item-results">
                        <div class="result-item">
                            <div class="result-label">Puntuación Global</div>
                            <div class="result-value">${diag.puntuacion_total || 0}%</div>
                        </div>
                        <div class="result-item">
                            <div class="result-label">Nivel de Madurez</div>
                            <div class="result-value">${diag.nivel_madurez || 'N/A'}</div>
                        </div>
                        <div class="result-item">
                            <div class="result-label">Progreso</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${diag.progreso || 0}%"></div>
                            </div>
                        </div>
                    </div>
                ` : `
                    <div class="diagnostico-item-progress">
                        <div class="progress-info">
                            <span>Progreso: ${diag.progreso || 0}%</span>
                            <span>${diag.respuestas_count || 0} de ${diag.total_preguntas || 0} preguntas</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${diag.progreso || 0}%"></div>
                        </div>
                    </div>
                `}
                
                <div class="diagnostico-item-actions">
                    ${isCompleto ? `
                        <button 
                            class="btn btn-primary"
                            onclick="verResultados(${diag.id_diagnostico_realizado})"
                        >
                            Ver Resultados
                        </button>
                        <button 
                            class="btn btn-secondary"
                            onclick="compararDiagnosticos(${diag.id_diagnostico_realizado})"
                        >
                            Comparar
                        </button>
                        <button 
                            class="btn btn-secondary"
                            onclick="verRecomendaciones(${diag.id_diagnostico_realizado})"
                        >
                            Ver Recomendaciones
                        </button>
                    ` : `
                        <button 
                            class="btn btn-primary"
                            onclick="continuarDiagnostico(${diag.id_diagnostico_realizado})"
                        >
                            Continuar
                        </button>
                        <button 
                            class="btn btn-secondary"
                            onclick="eliminarDiagnostico(${diag.id_diagnostico_realizado})"
                        >
                            Eliminar
                        </button>
                    `}
                </div>
            </div>
        `;
    }).join('');
}

/**
 * Renderizar paginación
 */
function renderPagination(pagination) {
    const container = document.getElementById('pagination');
    
    if (pagination.total_pages <= 1) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'flex';
    
    const pages = [];
    for (let i = 1; i <= pagination.total_pages; i++) {
        pages.push(`
            <button 
                class="pagination-btn ${i === pagination.current_page ? 'active' : ''}"
                onclick="goToPage(${i})"
                ${i === pagination.current_page ? 'disabled' : ''}
            >
                ${i}
            </button>
        `);
    }
    
    container.innerHTML = `
        <button 
            class="pagination-btn"
            onclick="goToPage(${pagination.current_page - 1})"
            ${pagination.current_page === 1 ? 'disabled' : ''}
        >
            ← Anterior
        </button>
        ${pages.join('')}
        <button 
            class="pagination-btn"
            onclick="goToPage(${pagination.current_page + 1})"
            ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}
        >
            Siguiente →
        </button>
    `;
}

/**
 * Poblar filtro de tipos
 */
function populateTipoFilter() {
    const select = document.getElementById('filterTipo');
    
    const options = tiposDiagnostico.map(tipo => 
        `<option value="${tipo.id_tipo_diagnostico}">${tipo.nombre}</option>`
    ).join('');
    
    select.innerHTML = '<option value="">Todos los tipos</option>' + options;
}

/**
 * Iniciar nuevo diagnóstico
 */
async function iniciarDiagnostico(tipoId) {
    try {
        const response = await apiPost('/diagnosticos/iniciar', {
            id_tipo_diagnostico: tipoId
        });
        
        const diagnosticoId = response.data.diagnostico.id_diagnostico_realizado;
        
        showAlert('Diagnóstico iniciado exitosamente', 'success');
        
        // Redirigir al wizard después de un momento
        setTimeout(() => {
            window.location.href = `diagnostico-wizard.html?id=${diagnosticoId}`;
        }, 1000);
        
    } catch (error) {
        console.error('Error al iniciar diagnóstico:', error);
        showAlert(error.message || 'Error al iniciar el diagnóstico', 'error');
    }
}

/**
 * Continuar diagnóstico existente
 */
function continuarDiagnostico(diagnosticoId) {
    window.location.href = `diagnostico-wizard.html?id=${diagnosticoId}`;
}

/**
 * Ver resultados
 */
function verResultados(diagnosticoId) {
    window.location.href = `diagnostico-resultados.html?id=${diagnosticoId}`;
}

/**
 * Ver recomendaciones
 */
function verRecomendaciones(diagnosticoId) {
    window.location.href = `diagnostico-resultados.html?id=${diagnosticoId}#recomendaciones`;
}

/**
 * Comparar diagnósticos
 */
function compararDiagnosticos(diagnosticoId) {
    window.location.href = `diagnostico-comparar.html?id1=${diagnosticoId}`;
}

/**
 * Eliminar diagnóstico
 */
async function eliminarDiagnostico(diagnosticoId) {
    if (!confirm('¿Estás seguro de que deseas eliminar este diagnóstico?')) {
        return;
    }
    
    try {
        await apiDelete(`/diagnosticos/${diagnosticoId}`);
        showAlert('Diagnóstico eliminado exitosamente', 'success');
        
        // Recargar lista
        await loadMisDiagnosticos();
        
    } catch (error) {
        console.error('Error al eliminar diagnóstico:', error);
        showAlert(error.message || 'Error al eliminar el diagnóstico', 'error');
    }
}

/**
 * Ir a página
 */
function goToPage(page) {
    currentPage = page;
    loadMisDiagnosticos(getFilters());
}

/**
 * Obtener filtros actuales
 */
function getFilters() {
    const filters = {};
    
    const estado = document.getElementById('filterEstado').value;
    if (estado) filters.estado = estado;
    
    const tipo = document.getElementById('filterTipo').value;
    if (tipo) filters.id_tipo_diagnostico = tipo;
    
    return filters;
}

/**
 * Configurar event listeners
 */
function setupEventListeners() {
    // Filtros
    const filterEstado = document.getElementById('filterEstado');
    if (filterEstado) {
        filterEstado.addEventListener('change', function() {
            currentPage = 1;
            loadMisDiagnosticos(getFilters());
        });
    }
    
    const filterTipo = document.getElementById('filterTipo');
    if (filterTipo) {
        filterTipo.addEventListener('change', function() {
            currentPage = 1;
            loadMisDiagnosticos(getFilters());
        });
    }
    
    // Logout
    const logoutBtn = document.getElementById('logout');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            logout();
        });
    }
}

/**
 * Mostrar contenedor de diagnósticos
 */
function showDiagnosticosContainer() {
    document.getElementById('diagnosticosContainer').style.display = 'block';
    document.getElementById('needsPerfil').style.display = 'none';
}

/**
 * Mostrar mensaje de perfil requerido
 */
function showNeedsPerfil() {
    document.getElementById('diagnosticosContainer').style.display = 'none';
    document.getElementById('needsPerfil').style.display = 'block';
}

/**
 * Mostrar loading
 */
function showLoading() {
    document.getElementById('loadingState').style.display = 'flex';
    document.getElementById('diagnosticosContainer').style.display = 'none';
    document.getElementById('needsPerfil').style.display = 'none';
}

/**
 * Ocultar loading
 */
function hideLoading() {
    document.getElementById('loadingState').style.display = 'none';
}

/**
 * Mostrar alerta
 */
function showAlert(message, type = 'info') {
    const container = document.getElementById('alertContainer');
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `
        <span class="alert-icon">
            ${type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ'}
        </span>
        <span class="alert-message">${message}</span>
        <button class="alert-close" onclick="this.parentElement.remove()">✕</button>
    `;
    
    container.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
