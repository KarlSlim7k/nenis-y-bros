/**
 * ============================================================================
 * AUTENTICACIÓN - Gestión de sesión y tokens
 * ============================================================================
 */

/**
 * Obtener token de autenticación
 */
function getAuthToken() {
    return localStorage.getItem(AUTH_TOKEN_KEY);
}

/**
 * Guardar token de autenticación
 */
function setAuthToken(token) {
    localStorage.setItem(AUTH_TOKEN_KEY, token);
}

/**
 * Obtener datos del usuario
 */
function getAuthUser() {
    const userData = localStorage.getItem(AUTH_USER_KEY);
    if (!userData) return null;
    
    try {
        return JSON.parse(userData);
    } catch (e) {
        console.error('Error parsing user data:', e);
        return null;
    }
}

/**
 * Guardar datos del usuario
 */
function setAuthUser(user) {
    localStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
}

/**
 * Limpiar datos de autenticación
 */
function clearAuth() {
    localStorage.removeItem(AUTH_TOKEN_KEY);
    localStorage.removeItem(AUTH_USER_KEY);
}

/**
 * Decodificar payload de un token JWT
 */
function decodeJWTPayload(token) {
    try {
        const parts = token.split('.');
        if (parts.length !== 3) return null;
        
        const payload = parts[1];
        // Decodificar base64url
        const decoded = atob(payload.replace(/-/g, '+').replace(/_/g, '/'));
        return JSON.parse(decoded);
    } catch (e) {
        console.error('Error decoding JWT:', e);
        return null;
    }
}

/**
 * Verificar si el token JWT ha expirado
 */
function isTokenExpired(token) {
    const payload = decodeJWTPayload(token);
    if (!payload || !payload.exp) return true;
    
    // exp está en segundos, Date.now() en milisegundos
    const now = Math.floor(Date.now() / 1000);
    return payload.exp < now;
}

/**
 * Verificar si el usuario está autenticado (con validación de expiración)
 */
function isAuthenticated() {
    const token = getAuthToken();
    const user = getAuthUser();
    
    if (!token || !user) return false;
    
    // Verificar si el token ha expirado
    if (isTokenExpired(token)) {
        console.log('Token expirado, limpiando sesión...');
        clearAuth();
        return false;
    }
    
    return true;
}

/**
 * Verificar si el usuario es admin
 */
function isAdmin() {
    const user = getAuthUser();
    console.log('🔍 isAdmin() - user:', user);
    console.log('🔍 isAdmin() - tipo_usuario:', user ? user.tipo_usuario : 'null');
    console.log('🔍 isAdmin() - typeof tipo_usuario:', user ? typeof user.tipo_usuario : 'null');
    console.log('🔍 isAdmin() - comparación:', user && user.tipo_usuario === 'administrador');
    return user && user.tipo_usuario === 'administrador';
}

/**
 * Verificar si el usuario es mentor/instructor
 */
function isMentor() {
    const user = getAuthUser();
    return user && user.tipo_usuario === 'mentor';
}

/**
 * Verificar si el usuario es empresario
 */
function isEmpresario() {
    const user = getAuthUser();
    return user && user.tipo_usuario === 'empresario';
}

/**
 * Verificar si el usuario es emprendedor
 */
function isEmprendedor() {
    const user = getAuthUser();
    return user && user.tipo_usuario === 'emprendedor';
}

/**
 * Obtener el tipo de usuario formateado
 */
function getUserType() {
    const user = getAuthUser();
    if (!user) return null;
    return user.tipo_usuario;
}

/**
 * Obtener el nombre completo del tipo de usuario
 */
function getUserTypeName() {
    const user = getAuthUser();
    if (!user) return '';
    
    const typeNames = {
        'administrador': 'Administrador',
        'mentor': 'Mentor/Instructor',
        'empresario': 'Empresario',
        'emprendedor': 'Emprendedor'
    };
    
    return typeNames[user.tipo_usuario] || user.tipo_usuario;
}

/**
 * Verificar autenticación y redirigir si es necesario
 */
function checkAuth() {
    if (!isAuthenticated()) {
        window.location.href = ROUTES.login + '?redirect=' + encodeURIComponent(window.location.pathname);
        return false;
    }
    return true;
}

/**
 * Verificar que sea admin y redirigir si no lo es
 */
function requireAdmin() {
    if (!checkAuth()) return false;
    
    if (!isAdmin()) {
        window.location.href = ROUTES.emprendedorDashboard;
        return false;
    }
    
    return true;
}

/**
 * Login de usuario
 */
async function login(email, password) {
    try {
        const response = await apiPost('/auth/login', {
            email,
            password
        });
        
        if (response.data && response.data.token && response.data.user) {
            // Guardar token y datos del usuario
            setAuthToken(response.data.token);
            setAuthUser(response.data.user);
            
            return response.data;
        }
        
        throw new Error('Respuesta de login inválida');
        
    } catch (error) {
        throw error;
    }
}

/**
 * Registro de usuario
 */
async function register(userData) {
    try {
        const response = await apiPost('/auth/register', userData);
        
        if (response.data && response.data.token && response.data.user) {
            // Guardar token y datos del usuario
            setAuthToken(response.data.token);
            setAuthUser(response.data.user);
            
            return response.data;
        }
        
        throw new Error('Respuesta de registro inválida');
        
    } catch (error) {
        throw error;
    }
}

/**
 * Logout de usuario
 */
async function logout() {
    try {
        // Intentar hacer logout en el servidor
        await apiPost('/auth/logout', {});
    } catch (error) {
        console.error('Error en logout:', error);
    } finally {
        // Limpiar datos locales siempre
        clearAuth();
        
        // Redirigir al login
        window.location.href = ROUTES.login;
    }
}

/**
 * Actualizar datos del usuario en sesión
 */
async function refreshUserData() {
    try {
        const response = await apiGet('/auth/me');
        
        if (response.data && response.data.user) {
            setAuthUser(response.data.user);
            return response.data.user;
        }
        
        return null;
        
    } catch (error) {
        console.error('Error al refrescar datos de usuario:', error);
        return null;
    }
}

/**
 * Mostrar información del usuario en la UI
 */
function displayUserInfo() {
    const user = getAuthUser();
    if (!user) return;
    
    // Actualizar elementos con clase .user-name
    const nameElements = document.querySelectorAll('.user-name');
    nameElements.forEach(el => {
        el.textContent = user.nombre || user.email;
    });
    
    // Actualizar elementos con clase .user-email
    const emailElements = document.querySelectorAll('.user-email');
    emailElements.forEach(el => {
        el.textContent = user.email;
    });
    
    // Actualizar elementos con clase .user-rol
    const rolElements = document.querySelectorAll('.user-rol, .user-tipo');
    rolElements.forEach(el => {
        el.textContent = getUserTypeName();
    });
    
    // Actualizar avatares
    const avatarElements = document.querySelectorAll('.user-avatar');
    avatarElements.forEach(el => {
        if (user.avatar) {
            el.innerHTML = `<img src="${user.avatar}" alt="${user.nombre}">`;
        } else {
            const initials = Utils.getInitials(user.nombre || user.email);
            el.textContent = initials;
        }
    });
}

/**
 * Inicializar eventos de autenticación en la página
 */
function initAuthUI() {
    // Mostrar info del usuario
    displayUserInfo();
    
    // Botones de logout
    const logoutButtons = document.querySelectorAll('[data-action="logout"]');
    logoutButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('¿Estás seguro de que deseas cerrar sesión?')) {
                logout();
            }
        });
    });
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        if (isAuthenticated()) {
            displayUserInfo();
        }
    });
} else {
    if (isAuthenticated()) {
        displayUserInfo();
    }
}

// Aliases para compatibilidad
function getToken() {
    return getAuthToken();
}

function requireAuth() {
    return checkAuth();
}

function verificarAutenticacion() {
    return isAuthenticated();
}
