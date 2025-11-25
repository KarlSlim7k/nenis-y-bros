/**
 * ============================================================================
 * ONBOARDING WIZARD
 * ============================================================================
 * Gestiona el flujo del cuestionario inicial
 * ============================================================================
 */

let questions = [];
let currentQuestionIndex = 0;
let answers = {};
let isSubmitting = false;

document.addEventListener('DOMContentLoaded', function () {
    loadQuestions();
    setupEventListeners();
});

/**
 * Cargar preguntas del backend
 */
async function loadQuestions() {
    try {
        const response = await apiGet('/onboarding/preguntas');
        questions = response.data.preguntas || [];

        if (questions.length === 0) {
            showError('No se pudieron cargar las preguntas. Por favor intenta más tarde.');
            return;
        }

        // Inicializar UI
        document.getElementById('totalSteps').textContent = questions.length;
        document.getElementById('loadingState').style.display = 'none';
        document.getElementById('questionContainer').style.display = 'block';

        renderQuestion();

    } catch (error) {
        console.error('Error loading questions:', error);
        showError('Error al cargar preguntas', error);
    }
}

/**
 * Renderizar pregunta actual
 */
function renderQuestion() {
    const question = questions[currentQuestionIndex];

    // Actualizar textos
    document.getElementById('currentStep').textContent = currentQuestionIndex + 1;
    document.getElementById('questionText').textContent = question.pregunta;
    document.getElementById('categoryBadge').textContent = formatCategory(question.categoria);

    // Actualizar barra de progreso
    const progress = ((currentQuestionIndex + 1) / questions.length) * 100;
    document.getElementById('progressBar').style.width = `${progress}%`;

    // Renderizar opciones
    const optionsGrid = document.getElementById('optionsGrid');
    optionsGrid.innerHTML = '';

    question.opciones.forEach((option, index) => {
        const savedAnswer = answers[question.id_pregunta];
        const isSelected = savedAnswer && savedAnswer.valor_numerico === option.valor;

        const card = document.createElement('div');
        card.className = `option-card ${isSelected ? 'selected' : ''}`;
        card.onclick = () => selectOption(question.id_pregunta, option.valor, option.texto);

        card.innerHTML = `
            <div class="option-radio"></div>
            <span class="option-text">${option.texto}</span>
        `;

        optionsGrid.appendChild(card);
    });

    // Actualizar botones
    updateButtons();
}

/**
 * Seleccionar una opción
 */
function selectOption(questionId, value, text) {
    answers[questionId] = {
        id_pregunta: questionId,
        valor_numerico: value,
        valor_texto: text
    };

    renderQuestion(); // Re-render para actualizar selección
}

/**
 * Actualizar estado de botones
 */
function updateButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    // Botón Anterior
    prevBtn.style.visibility = currentQuestionIndex === 0 ? 'hidden' : 'visible';

    // Botón Siguiente/Finalizar
    const currentQuestionId = questions[currentQuestionIndex].id_pregunta;
    const hasAnswer = answers[currentQuestionId] !== undefined;

    nextBtn.disabled = !hasAnswer;

    if (currentQuestionIndex === questions.length - 1) {
        nextBtn.textContent = isSubmitting ? 'Procesando...' : 'Ver Resultados';
    } else {
        nextBtn.textContent = 'Siguiente';
    }
}

/**
 * Configurar listeners
 */
function setupEventListeners() {
    document.getElementById('prevBtn').addEventListener('click', () => {
        if (currentQuestionIndex > 0) {
            currentQuestionIndex--;
            renderQuestion();
        }
    });

    document.getElementById('nextBtn').addEventListener('click', () => {
        if (currentQuestionIndex < questions.length - 1) {
            currentQuestionIndex++;
            renderQuestion();
        } else {
            submitAnswers();
        }
    });
}

/**
 * Enviar respuestas
 */
async function submitAnswers() {
    if (isSubmitting) return;
    isSubmitting = true;
    updateButtons();

    try {
        // Convertir objeto de respuestas a array
        const respuestasArray = Object.values(answers);

        const response = await apiPost('/onboarding/guardar-respuestas', {
            respuestas: respuestasArray
        });

        // Guardar resultados en localStorage para la página de resultados
        localStorage.setItem('onboarding_results', JSON.stringify(response.data));

        // Redirigir
        window.location.href = 'resultados-inicial.html';

    } catch (error) {
        console.error('Error submitting answers:', error);
        alert('Hubo un error al procesar tus respuestas. Por favor intenta nuevamente.');
        isSubmitting = false;
        updateButtons();
    }
}

/**
 * Formatear categoría
 */
function formatCategory(category) {
    return category.charAt(0).toUpperCase() + category.slice(1);
}

/**
 * Mostrar error
 */
function showError(message, errorDetails = null) {
    const container = document.getElementById('wizardContent');
    let detailsHtml = '';

    if (errorDetails) {
        // Si es un objeto Error estándar, sus propiedades no son enumerables por JSON.stringify
        const errorMsg = errorDetails.message || 'Sin mensaje de error';
        const errorStack = errorDetails.stack || '';
        const errorJson = JSON.stringify(errorDetails, null, 2);

        detailsHtml = `
            <div style="text-align: left; background: #f8d7da; padding: 10px; border-radius: 5px; font-size: 0.8rem; overflow: auto; margin-top: 10px;">
                <strong>Mensaje:</strong> ${errorMsg}<br>
                <strong>Stack:</strong> <pre>${errorStack}</pre>
                <strong>JSON:</strong> <pre>${errorJson}</pre>
            </div>`;
    }

    container.innerHTML = `
        <div class="text-center">
            <h3 style="color: var(--danger)">Error</h3>
            <p>${message}</p>
            ${detailsHtml}
            <button class="btn btn-primary" onclick="location.reload()">Reintentar</button>
        </div>
    `;
}
