/**
 * ============================================================================
 * REGISTRO ONBOARDING
 * ============================================================================
 * Gestiona el registro de usuarios provenientes del onboarding
 * ============================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    checkOnboardingData();
    setupForm();
});

/**
 * Verificar si hay datos del onboarding y mostrar banner
 */
function checkOnboardingData() {
    const resultsJson = localStorage.getItem('onboarding_results');

    if (resultsJson) {
        const results = JSON.parse(resultsJson);
        const banner = document.getElementById('onboardingInfo');
        const levelSpan = document.getElementById('userLevel');
        
        // Emoji según nivel
        const levelEmojis = {
            'principiante': '🌱',
            'intermedio': '🚀', 
            'avanzado': '⭐'
        };
        
        const emoji = levelEmojis[results.nivel] || '✨';
        const levelText = results.nivel.charAt(0).toUpperCase() + results.nivel.slice(1);
        
        levelSpan.innerHTML = `${emoji} ${levelText}`;
        banner.classList.add('show');
    }
}

/**
 * Configurar el formulario de registro
 */
function setupForm() {
    const form = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');
    const errorMessage = document.getElementById('errorMessage');
    const successMessage = document.getElementById('successMessage');

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        
        // Ocultar mensajes previos
        hideMessages();

        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-small"></span> Creando cuenta...';

        try {
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            // Validaciones básicas
            if (data.nombre.trim().length < 2) {
                throw new Error('El nombre debe tener al menos 2 caracteres');
            }
            
            if (data.apellido.trim().length < 2) {
                throw new Error('El apellido debe tener al menos 2 caracteres');
            }
            
            if (data.password.length < 8) {
                throw new Error('La contraseña debe tener al menos 8 caracteres');
            }
            
            if (data.password !== data.password_confirmation) {
                throw new Error('Las contraseñas no coinciden');
            }

            // Agregar token de onboarding si existe
            const resultsJson = localStorage.getItem('onboarding_results');
            if (resultsJson) {
                const results = JSON.parse(resultsJson);
                data.token_cuestionario = results.token;
                data.auto_enroll = true;
            }

            const response = await apiPost('/auth/register', data);

            // Mostrar éxito
            showSuccess('¡Cuenta creada exitosamente! Redirigiendo...');

            // Limpiar localStorage del onboarding
            localStorage.removeItem('onboarding_results');

            // Guardar token y datos de usuario (usando las claves de config.js)
            localStorage.setItem(AUTH_TOKEN_KEY, response.data.token);
            localStorage.setItem(AUTH_USER_KEY, JSON.stringify(response.data.user));

            // Redirigir al dashboard después de un momento
            setTimeout(() => {
                window.location.href = '../user/dashboard.html';
            }, 1500);

        } catch (error) {
            console.error('Error registering:', error);
            showError(error.message || 'Error al crear la cuenta. Intenta de nuevo.');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
}

/**
 * Mostrar mensaje de error
 */
function showError(message) {
    const errorEl = document.getElementById('errorMessage');
    errorEl.textContent = message;
    errorEl.classList.add('show');
    
    // Scroll al mensaje
    errorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/**
 * Mostrar mensaje de éxito
 */
function showSuccess(message) {
    const successEl = document.getElementById('successMessage');
    successEl.textContent = message;
    successEl.classList.add('show');
}

/**
 * Ocultar todos los mensajes
 */
function hideMessages() {
    document.getElementById('errorMessage').classList.remove('show');
    document.getElementById('successMessage').classList.remove('show');
}
