/**
 * ============================================================================
 * CONFIGURACIÓN GLOBAL - Frontend
 * ============================================================================
 */

// URL base de la API - Configuración dinámica para desarrollo y producción
const API_BASE_URL = (() => {
    // En producción (Vercel), usar variable de entorno o URL del backend desplegado
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        // URL del backend en Railway (actualizar con tu URL real de Railway)
        return 'https://nenis-y-bros-backend-production.up.railway.app/api/v1';
    }
    // En desarrollo local
    return '/nenis_y_bros/backend/index.php/api/v1';
})();

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
    home: '/nenis_y_bros/index.html',
    login: '/nenis_y_bros/frontend/pages/auth/login.html',
    register: '/nenis_y_bros/frontend/pages/auth/register.html',

    // Usuario/Emprendedor - Páginas que existen
    emprendedorDashboard: '/nenis_y_bros/frontend/pages/user/dashboard.html',
    diagnosticos: '/nenis_y_bros/frontend/pages/user/diagnosticos.html',
    perfilEmpresarial: '/nenis_y_bros/frontend/pages/user/perfil-empresarial.html',
    diagnosticoWizard: '/nenis_y_bros/frontend/pages/user/diagnostico-wizard.html',
    diagnosticoResultados: '/nenis_y_bros/frontend/pages/user/diagnostico-resultados.html',
    miProgreso: '/nenis_y_bros/frontend/pages/user/mi-progreso.html',
    misLogros: '/nenis_y_bros/frontend/pages/user/mis-logros.html',
    misCertificados: '/nenis_y_bros/frontend/pages/user/mis-certificados.html',
    ranking: '/nenis_y_bros/frontend/pages/user/ranking.html',
    notificaciones: '/nenis_y_bros/frontend/pages/user/notificaciones.html',
    vitrinaProductos: '/nenis_y_bros/frontend/pages/user/vitrina-productos.html',
    misProductos: '/nenis_y_bros/frontend/pages/user/mis-productos.html',
    chat: '/nenis_y_bros/frontend/pages/user/chat.html',
    evaluacion: '/nenis_y_bros/frontend/pages/user/evaluacion.html',
    evaluacionResultados: '/nenis_y_bros/frontend/pages/user/evaluacion-resultados.html',
    verificarCertificado: '/nenis_y_bros/frontend/pages/user/verificar-certificado.html',

    // Admin
    adminDashboard: '/nenis_y_bros/frontend/pages/admin/dashboard.html',
    adminUsers: '/nenis_y_bros/frontend/pages/admin/users.html',
    adminCourses: '/nenis_y_bros/frontend/pages/admin/courses.html',

    // Instructor/Mentor
    instructorDashboard: '/nenis_y_bros/frontend/pages/instructor/dashboard.html',
    instructorEstudiantes: '/nenis_y_bros/frontend/pages/instructor/estudiantes.html',
    instructorSesiones: '/nenis_y_bros/frontend/pages/instructor/sesiones.html',
    instructorDisponibilidad: '/nenis_y_bros/frontend/pages/instructor/disponibilidad-instructor.html',
    instructorCursos: '/nenis_y_bros/frontend/pages/instructor/cursos.html',
    instructorMensajes: '/nenis_y_bros/frontend/pages/instructor/mensajes.html'
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
