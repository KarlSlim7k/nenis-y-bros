/**
 * ============================================================================
 * PERFIL EMPRESARIAL - Frontend JavaScript
 * ============================================================================
 * Gestiona la creación y edición del perfil empresarial del usuario
 * ============================================================================
 */

// Estado de la aplicación
let currentPerfil = null;
let isEditMode = false;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    checkAuth();
    loadPerfil();
    setupEventListeners();
});

/**
 * Cargar perfil empresarial del usuario
 */
async function loadPerfil() {
    showLoading();
    
    try {
        const response = await apiGet('/perfiles/mi-perfil');
        
        if (response.data.existe && response.data.perfil) {
            currentPerfil = response.data.perfil;
            isEditMode = true;
            showPerfilForm();
            populateForm(currentPerfil);
        } else {
            showNoPerfil();
        }
    } catch (error) {
        console.error('Error al cargar perfil:', error);
        showAlert('Error al cargar el perfil. Intenta de nuevo.', 'error');
        showNoPerfil();
    } finally {
        hideLoading();
    }
}

/**
 * Mostrar formulario de perfil
 */
function showPerfilForm() {
    document.getElementById('perfilContainer').style.display = 'block';
    document.getElementById('noPerfil').style.display = 'none';
    
    // Actualizar título del botón
    const submitBtn = document.querySelector('#submitBtn .btn-text');
    submitBtn.textContent = isEditMode ? 'Actualizar Perfil' : 'Guardar Perfil';
}

/**
 * Mostrar estado sin perfil
 */
function showNoPerfil() {
    document.getElementById('perfilContainer').style.display = 'none';
    document.getElementById('noPerfil').style.display = 'block';
}

/**
 * Poblar formulario con datos existentes
 */
function populateForm(perfil) {
    // Información básica
    document.getElementById('nombreEmpresa').value = perfil.nombre_empresa || '';
    document.getElementById('sector').value = perfil.sector || '';
    document.getElementById('tipoNegocio').value = perfil.tipo_negocio || '';
    document.getElementById('etapaNegocio').value = perfil.etapa_negocio || '';
    document.getElementById('numeroEmpleados').value = perfil.numero_empleados || '';
    document.getElementById('descripcion').value = perfil.descripcion || '';
    
    // Información financiera
    document.getElementById('facturacionAnual').value = perfil.facturacion_anual || '';
    document.getElementById('anioFundacion').value = perfil.anio_fundacion || '';
    
    // Información de contacto
    document.getElementById('emailEmpresa').value = perfil.email_empresa || '';
    document.getElementById('telefonoEmpresa').value = perfil.telefono_empresa || '';
    document.getElementById('sitioWeb').value = perfil.sitio_web || '';
    document.getElementById('direccion').value = perfil.direccion || '';
    document.getElementById('ciudad').value = perfil.ciudad || '';
    document.getElementById('estado').value = perfil.estado || '';
    document.getElementById('pais').value = perfil.pais || '';
    
    // Redes sociales
    if (perfil.redes_sociales) {
        const redes = typeof perfil.redes_sociales === 'string' 
            ? JSON.parse(perfil.redes_sociales) 
            : perfil.redes_sociales;
        
        document.getElementById('facebook').value = redes.facebook || '';
        document.getElementById('instagram').value = redes.instagram || '';
        document.getElementById('linkedin').value = redes.linkedin || '';
        document.getElementById('twitter').value = redes.twitter || '';
    }
}

/**
 * Obtener datos del formulario
 */
function getFormData() {
    const formData = {
        nombre_empresa: document.getElementById('nombreEmpresa').value.trim(),
        sector: document.getElementById('sector').value,
        tipo_negocio: document.getElementById('tipoNegocio').value,
        etapa_negocio: document.getElementById('etapaNegocio').value,
        descripcion: document.getElementById('descripcion').value.trim() || null
    };
    
    // Número de empleados
    const numEmpleados = document.getElementById('numeroEmpleados').value;
    if (numEmpleados) formData.numero_empleados = parseInt(numEmpleados);
    
    // Facturación
    const facturacion = document.getElementById('facturacionAnual').value;
    if (facturacion) formData.facturacion_anual = parseFloat(facturacion);
    
    // Año fundación
    const anio = document.getElementById('anioFundacion').value;
    if (anio) formData.anio_fundacion = parseInt(anio);
    
    // Contacto
    const email = document.getElementById('emailEmpresa').value.trim();
    if (email) formData.email_empresa = email;
    
    const telefono = document.getElementById('telefonoEmpresa').value.trim();
    if (telefono) formData.telefono_empresa = telefono;
    
    const web = document.getElementById('sitioWeb').value.trim();
    if (web) formData.sitio_web = web;
    
    // Dirección
    const direccion = document.getElementById('direccion').value.trim();
    if (direccion) formData.direccion = direccion;
    
    const ciudad = document.getElementById('ciudad').value.trim();
    if (ciudad) formData.ciudad = ciudad;
    
    const estado = document.getElementById('estado').value.trim();
    if (estado) formData.estado = estado;
    
    const pais = document.getElementById('pais').value.trim();
    if (pais) formData.pais = pais;
    
    // Redes sociales
    const redes = {};
    const facebook = document.getElementById('facebook').value.trim();
    if (facebook) redes.facebook = facebook;
    
    const instagram = document.getElementById('instagram').value.trim();
    if (instagram) redes.instagram = instagram;
    
    const linkedin = document.getElementById('linkedin').value.trim();
    if (linkedin) redes.linkedin = linkedin;
    
    const twitter = document.getElementById('twitter').value.trim();
    if (twitter) redes.twitter = twitter;
    
    if (Object.keys(redes).length > 0) {
        formData.redes_sociales = redes;
    }
    
    return formData;
}

/**
 * Validar formulario
 */
function validateForm(data) {
    const errors = [];
    
    if (!data.nombre_empresa || data.nombre_empresa.length < 2) {
        errors.push('El nombre de la empresa debe tener al menos 2 caracteres');
    }
    
    if (!data.sector) {
        errors.push('Debes seleccionar un sector');
    }
    
    if (!data.tipo_negocio) {
        errors.push('Debes seleccionar el tipo de negocio');
    }
    
    if (!data.etapa_negocio) {
        errors.push('Debes seleccionar la etapa del negocio');
    }
    
    // Validar email si está presente
    if (data.email_empresa && !isValidEmail(data.email_empresa)) {
        errors.push('El email empresarial no es válido');
    }
    
    // Validar URL si está presente
    if (data.sitio_web && !isValidURL(data.sitio_web)) {
        errors.push('La URL del sitio web no es válida');
    }
    
    return errors;
}

/**
 * Validar email
 */
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Validar URL
 */
function isValidURL(url) {
    try {
        new URL(url);
        return true;
    } catch {
        return false;
    }
}

/**
 * Guardar o actualizar perfil
 */
async function savePerfil(event) {
    event.preventDefault();
    
    // Obtener datos
    const formData = getFormData();
    
    // Validar
    const errors = validateForm(formData);
    if (errors.length > 0) {
        showAlert(errors.join('<br>'), 'error');
        return;
    }
    
    // Mostrar loading
    showButtonLoading(true);
    
    try {
        let response;
        
        if (isEditMode && currentPerfil) {
            // Actualizar perfil existente
            response = await apiPut(`/perfiles/${currentPerfil.id_perfil}`, formData);
            showAlert('Perfil actualizado exitosamente', 'success');
        } else {
            // Crear nuevo perfil
            response = await apiPost('/perfiles', formData);
            showAlert('Perfil creado exitosamente', 'success');
            isEditMode = true;
        }
        
        // Actualizar datos actuales
        currentPerfil = response.data.perfil;
        
        // Esperar un momento y recargar
        setTimeout(() => {
            loadPerfil();
        }, 1500);
        
    } catch (error) {
        console.error('Error al guardar perfil:', error);
        
        if (error.errors) {
            // Errores de validación del backend
            const errorMessages = Object.values(error.errors).flat().join('<br>');
            showAlert(errorMessages, 'error');
        } else {
            showAlert(error.message || 'Error al guardar el perfil', 'error');
        }
    } finally {
        showButtonLoading(false);
    }
}

/**
 * Configurar event listeners
 */
function setupEventListeners() {
    // Formulario
    const form = document.getElementById('perfilForm');
    if (form) {
        form.addEventListener('submit', savePerfil);
    }
    
    // Botón crear perfil
    const crearBtn = document.getElementById('crearPerfilBtn');
    if (crearBtn) {
        crearBtn.addEventListener('click', function() {
            isEditMode = false;
            currentPerfil = null;
            showPerfilForm();
        });
    }
    
    // Botón cancelar
    const cancelBtn = document.getElementById('cancelBtn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            if (confirm('¿Estás seguro? Los cambios no guardados se perderán.')) {
                if (currentPerfil) {
                    populateForm(currentPerfil);
                } else {
                    showNoPerfil();
                }
            }
        });
    }
    
    // Contador de caracteres para descripción
    const descripcionField = document.getElementById('descripcion');
    if (descripcionField) {
        descripcionField.addEventListener('input', function() {
            const length = this.value.length;
            const helpText = this.nextElementSibling;
            if (helpText) {
                helpText.textContent = `${length}/1000 caracteres`;
                if (length > 1000) {
                    this.value = this.value.substring(0, 1000);
                }
            }
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
 * Mostrar loading
 */
function showLoading() {
    document.getElementById('loadingState').style.display = 'flex';
    document.getElementById('perfilContainer').style.display = 'none';
    document.getElementById('noPerfil').style.display = 'none';
}

/**
 * Ocultar loading
 */
function hideLoading() {
    document.getElementById('loadingState').style.display = 'none';
}

/**
 * Mostrar loading en botón
 */
function showButtonLoading(show) {
    const btn = document.getElementById('submitBtn');
    const btnText = btn.querySelector('.btn-text');
    const btnLoading = btn.querySelector('.btn-loading');
    
    if (show) {
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline-flex';
        btn.disabled = true;
    } else {
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
        btn.disabled = false;
    }
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
            ${type === 'success' ? '<i class="icon-check-circle"></i>' : 
              type === 'error' ? '<i class="icon-x-circle"></i>' : 
              '<i class="icon-info-circle"></i>'}
        </span>
        <span class="alert-message">${message}</span>
        <button class="alert-close" onclick="this.parentElement.remove()">
            <i class="icon-x"></i>
        </button>
    `;
    
    container.appendChild(alert);
    
    // Auto-remover después de 5 segundos
    setTimeout(() => {
        alert.remove();
    }, 5000);
    
    // Scroll al inicio
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
