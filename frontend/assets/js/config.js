/**
 * ============================================================================
 * CONFIGURACIÓN GLOBAL - Frontend
 * ============================================================================
 */

// Detectar si estamos en producción o desarrollo
const DEVELOPMENT_HOSTNAMES = ['localhost', '127.0.0.1', '::1', 'localhost.localdomain'];
const IS_PRODUCTION = !DEVELOPMENT_HOSTNAMES.includes(window.location.hostname) && 
                      !window.location.hostname.match(/^192\.168\.\d{1,3}\.\d{1,3}$/) &&
                      !window.location.hostname.match(/^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$/);

// Debug: Log de detección de entorno
console.log('🔧 Config Debug:', {
    hostname: window.location.hostname,
    href: window.location.href,
    IS_PRODUCTION: IS_PRODUCTION
});

// Base path para rutas del frontend
const BASE_PATH = IS_PRODUCTION ? '' : '/nenis_y_bros';

// URL base de la API - Configuración dinámica para desarrollo y producción
// Nota: En desarrollo local con XAMPP/Apache, el .htaccess redirige automáticamente a index.php
// Si el .htaccess no funciona, cambiar a: '/nenis_y_bros/backend/index.php/api/v1'
const API_BASE_URL = IS_PRODUCTION
    ? 'https://nenis-y-bros-production.up.railway.app/api/v1'
    : `http://${window.location.hostname}/nenis_y_bros/backend/api/v1`;

// Alias para compatibilidad (usado en algunos módulos admin)
const API_URL = API_BASE_URL;

// Debug: Log de URLs configuradas
console.log('🌐 API Config:', {
    API_BASE_URL: API_BASE_URL,
    API_URL: API_URL,
    BASE_PATH: BASE_PATH
});

// Configuración de autenticación
const AUTH_TOKEN_KEY = 'nyd_auth_token';
const AUTH_USER_KEY = 'nyd_user_data';

// Configuración de la aplicación
const APP_CONFIG = {
    name: 'Nenis y Bros',
    version: '1.0.0',
    apiTimeout: 30000, // 30 segundos
    maxFileSize: 5 * 1024 * 1024, // 5 MB
    allowedImageTypes: ['image/jpeg', 'image/png', 'image/webp'],
    pagination: {
        defaultLimit: 10,
        maxLimit: 100
    }
};

// Rutas de navegación
const ROUTES = {
    // Públicas
    home: `${BASE_PATH}/index.html`,
    login: `${BASE_PATH}/frontend/pages/auth/login.html`,
    register: `${BASE_PATH}/frontend/pages/auth/register.html`,

    // Dashboards por rol
    emprendedorDashboard: `${BASE_PATH}/frontend/pages/emprendedor/dashboard.html`,
    empresarioDashboard: `${BASE_PATH}/frontend/pages/empresario/dashboard.html`,
    
    // DEPRECATED: Rutas genéricas - Usar navigation.js para rutas específicas por rol
    // Las siguientes rutas apuntan a emprendedor por defecto para mantener compatibilidad
    diagnosticos: `${BASE_PATH}/frontend/pages/emprendedor/diagnosticos.html`,
    perfilEmpresarial: `${BASE_PATH}/frontend/pages/empresario/perfil-empresarial.html`,
    diagnosticoWizard: `${BASE_PATH}/frontend/pages/emprendedor/diagnostico-wizard.html`,
    diagnosticoResultados: `${BASE_PATH}/frontend/pages/emprendedor/diagnostico-resultados.html`,
    miProgreso: `${BASE_PATH}/frontend/pages/emprendedor/mi-progreso.html`,
    misLogros: `${BASE_PATH}/frontend/pages/emprendedor/mis-logros.html`,
    misCertificados: `${BASE_PATH}/frontend/pages/emprendedor/mis-certificados.html`,
    ranking: `${BASE_PATH}/frontend/pages/emprendedor/ranking.html`,
    notificaciones: `${BASE_PATH}/frontend/pages/emprendedor/notificaciones.html`,
    vitrinaProductos: `${BASE_PATH}/frontend/pages/emprendedor/vitrina-productos.html`,
    misProductos: `${BASE_PATH}/frontend/pages/empresario/mis-productos.html`,
    chat: `${BASE_PATH}/frontend/pages/emprendedor/chat.html`,
    evaluacion: `${BASE_PATH}/frontend/pages/emprendedor/evaluacion.html`,
    evaluacionResultados: `${BASE_PATH}/frontend/pages/emprendedor/evaluacion-resultados.html`,
    verificarCertificado: `${BASE_PATH}/frontend/pages/emprendedor/verificar-certificado.html`,

    // Admin
    adminDashboard: `${BASE_PATH}/frontend/pages/admin/dashboard.html`,
    adminUsers: `${BASE_PATH}/frontend/pages/admin/users.html`,
    adminCourses: `${BASE_PATH}/frontend/pages/admin/courses.html`,

    // Instructor/Mentor
    instructorDashboard: `${BASE_PATH}/frontend/pages/instructor/dashboard.html`,
    instructorEstudiantes: `${BASE_PATH}/frontend/pages/instructor/estudiantes.html`,
    instructorSesiones: `${BASE_PATH}/frontend/pages/instructor/sesiones.html`,
    instructorDisponibilidad: `${BASE_PATH}/frontend/pages/instructor/disponibilidad-instructor.html`,
    instructorCursos: `${BASE_PATH}/frontend/pages/instructor/cursos.html`,
    instructorMensajes: `${BASE_PATH}/frontend/pages/instructor/mensajes.html`
};

// Mensajes comunes
const MESSAGES = {
    error: {
        generic: 'Ha ocurrido un error. Intenta de nuevo.',
        network: 'Error de conexión. Verifica tu internet.',
        unauthorized: 'No tienes autorización. Inicia sesión nuevamente.',
        notFound: 'Recurso no encontrado.',
        validation: 'Por favor verifica los datos ingresados.'
    },
    success: {
        saved: 'Guardado exitosamente',
        updated: 'Actualizado exitosamente',
        deleted: 'Eliminado exitosamente',
        created: 'Creado exitosamente'
    }
};

// Sectores disponibles
const SECTORES = [
    'Tecnología',
    'Comercio',
    'Servicios',
    'Manufactura',
    'Agricultura',
    'Construcción',
    'Educación',
    'Salud',
    'Finanzas',
    'Turismo',
    'Transporte',
    'Otro'
];

// Tipos de negocio
const TIPOS_NEGOCIO = [
    'Emprendimiento',
    'Microempresa',
    'Pequeña Empresa',
    'Mediana Empresa',
    'Gran Empresa'
];

// Etapas de negocio
const ETAPAS_NEGOCIO = [
    'Idea',
    'Validación',
    'Operación',
    'Crecimiento',
    'Consolidación'
];

// Utilidades
const Utils = {
    /**
     * Formatear fecha
     */
    formatDate(dateString, includeTime = false) {
        if (!dateString) return '';

        const date = new Date(dateString);
        const options = {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };

        if (includeTime) {
            options.hour = '2-digit';
            options.minute = '2-digit';
        }

        return date.toLocaleDateString('es-ES', options);
    },

    /**
     * Formatear moneda
     */
    formatCurrency(amount, currency = 'EUR') {
        if (amount === null || amount === undefined) return '';

        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: currency
        }).format(amount);
    },

    /**
     * Formatear número
     */
    formatNumber(number) {
        if (number === null || number === undefined) return '';
        return new Intl.NumberFormat('es-ES').format(number);
    },

    /**
     * Truncar texto
     */
    truncate(text, maxLength = 100) {
        if (!text || text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    },

    /**
     * Obtener iniciales
     */
    getInitials(name) {
        if (!name) return '';
        return name
            .split(' ')
            .map(word => word[0])
            .join('')
            .toUpperCase()
            .substring(0, 2);
    },

    /**
     * Debounce
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};
