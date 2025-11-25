/**
 * ============================================================================
 * API CLIENT - Funciones para consumir la API REST
 * ============================================================================
 */

/**
 * Realizar petición GET
 */
async function apiGet(endpoint, params = {}) {
    const url = buildURL(endpoint, params);
    return await fetchAPI(url, {
        method: 'GET'
    });
}

/**
 * Realizar petición POST
 */
async function apiPost(endpoint, data) {
    const url = buildURL(endpoint);
    return await fetchAPI(url, {
        method: 'POST',
        body: JSON.stringify(data)
    });
}

/**
 * Realizar petición PUT
 */
async function apiPut(endpoint, data) {
    const url = buildURL(endpoint);
    return await fetchAPI(url, {
        method: 'PUT',
        body: JSON.stringify(data)
    });
}

/**
 * Realizar petición DELETE
 */
async function apiDelete(endpoint) {
    const url = buildURL(endpoint);
    return await fetchAPI(url, {
        method: 'DELETE'
    });
}

/**
 * Construir URL con parámetros
 */
function buildURL(endpoint, params = {}) {
    // Remover slash inicial si existe
    endpoint = endpoint.replace(/^\//, '');
    
    let url = `${API_BASE_URL}/${endpoint}`;
    
    // Agregar parámetros de query
    const queryParams = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
        if (value !== null && value !== undefined && value !== '') {
            queryParams.append(key, value);
        }
    }
    
    const queryString = queryParams.toString();
    if (queryString) {
        url += '?' + queryString;
    }
    
    return url;
}

/**
 * Realizar petición fetch con manejo de errores
 */
async function fetchAPI(url, options = {}) {
    // Headers por defecto
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    };
    
    // Agregar token de autenticación si existe
    const token = getAuthToken();
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }
    
    // Merge de opciones
    const config = {
        ...options,
        headers: {
            ...headers,
            ...options.headers
        }
    };
    
    try {
        // Realizar petición
        const response = await fetch(url, config);
        
        // Intentar parsear JSON
        let data;
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            data = await response.json();
        } else {
            data = await response.text();
        }
        
        // Si la respuesta no es exitosa
        if (!response.ok) {
            // Error 401 - No autorizado
            if (response.status === 401) {
                handleUnauthorized();
                throw {
                    status: 401,
                    message: 'No autorizado. Inicia sesión nuevamente.',
                    errors: data.errors || {}
                };
            }
            
            // Error 404 - No encontrado
            if (response.status === 404) {
                throw {
                    status: 404,
                    message: data.message || 'Recurso no encontrado',
                    errors: data.errors || {}
                };
            }
            
            // Error 422 - Validación
            if (response.status === 422) {
                throw {
                    status: 422,
                    message: data.message || 'Error de validación',
                    errors: data.errors || {}
                };
            }
            
            // Otros errores
            throw {
                status: response.status,
                message: data.message || MESSAGES.error.generic,
                errors: data.errors || {}
            };
        }
        
        // Respuesta exitosa
        return data;
        
    } catch (error) {
        // Error de red o fetch
        if (error.name === 'TypeError' || error.message === 'Failed to fetch') {
            throw {
                status: 0,
                message: MESSAGES.error.network,
                errors: {}
            };
        }
        
        // Re-lanzar error estructurado
        throw error;
    }
}

/**
 * Manejar error de autorización
 */
function handleUnauthorized() {
    // Limpiar datos de autenticación
    clearAuth();
    
    // Redirigir a login después de un momento
    setTimeout(() => {
        window.location.href = ROUTES.login + '?redirect=' + encodeURIComponent(window.location.pathname);
    }, 1500);
}

/**
 * Upload de archivos
 */
async function apiUpload(endpoint, formData) {
    const url = buildURL(endpoint);
    
    const headers = {};
    const token = getAuthToken();
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }
    
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: headers,
            body: formData // FormData se envía sin Content-Type
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            if (response.status === 401) {
                handleUnauthorized();
            }
            throw {
                status: response.status,
                message: data.message || MESSAGES.error.generic,
                errors: data.errors || {}
            };
        }
        
        return data;
        
    } catch (error) {
        if (error.name === 'TypeError' || error.message === 'Failed to fetch') {
            throw {
                status: 0,
                message: MESSAGES.error.network,
                errors: {}
            };
        }
        throw error;
    }
}

/**
 * Helper para debug
 */
function logAPICall(method, endpoint, data = null) {
    if (console && console.log) {
        console.log(`[API ${method}] ${endpoint}`, data);
    }
}
