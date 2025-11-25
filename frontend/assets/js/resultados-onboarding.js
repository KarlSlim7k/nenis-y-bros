/**
 * ============================================================================
 * RESULTADOS ONBOARDING
 * ============================================================================
 * Gestiona la visualización de resultados y recomendaciones
 * ============================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    loadResults();
});

function loadResults() {
    // Obtener resultados de localStorage
    const resultsJson = localStorage.getItem('onboarding_results');

    if (!resultsJson) {
        // Si no hay resultados, redirigir al cuestionario
        window.location.href = 'cuestionario-inicial.html';
        return;
    }

    const results = JSON.parse(resultsJson);

    // Mostrar nivel con emoji
    const levelBadge = document.getElementById('levelBadge');
    const levelEmoji = getLevelEmoji(results.nivel);
    levelBadge.innerHTML = `<span>${levelEmoji}</span> ${formatLevel(results.nivel)}`;
    levelBadge.className = `level-badge level-${results.nivel}`;

    // Mostrar descripción
    const description = document.getElementById('levelDescription');
    description.innerHTML = getLevelDescription(results.nivel);

    // Renderizar gráfico
    renderChart(results.puntaje);

    // Renderizar cursos
    renderCourses(results.cursos_recomendados);
}

function formatLevel(level) {
    return level.charAt(0).toUpperCase() + level.slice(1);
}

function getLevelEmoji(level) {
    const emojis = {
        'principiante': '🌱',
        'intermedio': '🚀',
        'avanzado': '⭐'
    };
    return emojis[level] || '✨';
}

function getLevelDescription(level) {
    const descriptions = {
        'principiante': '<p>🌱 <strong>¡Bienvenido al mundo del emprendimiento!</strong> Estás dando tus primeros pasos. Es el momento perfecto para construir bases sólidas y aprender los fundamentos que te llevarán al éxito.</p>',
        'intermedio': '<p>🚀 <strong>¡Vas por buen camino!</strong> Ya tienes experiencia y tu negocio está en marcha. Ahora toca optimizar procesos, profesionalizar tu gestión y llevar tu emprendimiento al siguiente nivel.</p>',
        'avanzado': '<p>⭐ <strong>¡Eres un emprendedor experimentado!</strong> Tu enfoque debe ser el escalamiento, la estrategia avanzada y la innovación. Estás listo para grandes desafíos.</p>'
    };
    return descriptions[level] || '<p>Analizando tu perfil...</p>';
}

function renderChart(score) {
    const ctx = document.getElementById('radarChart').getContext('2d');

    // Datos simulados para el gráfico basados en el puntaje general
    // En una implementación real, vendrían desglosados por categoría
    const data = {
        labels: ['Experiencia', 'Conocimientos', 'Negocio', 'Objetivos'],
        datasets: [{
            label: 'Tu Perfil',
            data: [score, score > 50 ? score - 10 : score + 10, score, score > 70 ? score - 5 : score + 5],
            fill: true,
            backgroundColor: 'rgba(102, 126, 234, 0.2)',
            borderColor: 'rgb(102, 126, 234)',
            pointBackgroundColor: 'rgb(102, 126, 234)',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: 'rgb(102, 126, 234)'
        }]
    };

    new Chart(ctx, {
        type: 'radar',
        data: data,
        options: {
            elements: {
                line: { borderWidth: 3 }
            },
            scales: {
                r: {
                    angleLines: { display: false },
                    suggestedMin: 0,
                    suggestedMax: 100
                }
            }
        }
    });
}

function renderCourses(courses) {
    const container = document.getElementById('coursesGrid');

    if (!courses || courses.length === 0) {
        container.innerHTML = `
            <div class="text-center" style="grid-column: 1 / -1; padding: 2rem;">
                <p style="color: var(--text-light);">No hay cursos específicos para mostrar, pero puedes explorar todo nuestro catálogo.</p>
            </div>`;
        return;
    }

    // Emojis para diferentes temas/niveles
    const courseEmojis = ['📚', '💡', '📊', '🎯', '💪', '🌟'];

    container.innerHTML = courses.map((course, index) => `
        <div class="course-card">
            <div class="course-image">
                ${courseEmojis[index % courseEmojis.length]}
            </div>
            <div class="course-content">
                <span class="course-tag">${formatLevel(course.nivel_curso || 'General')}</span>
                <h4 class="course-title">${course.titulo}</h4>
                <p style="font-size: 0.875rem; color: var(--text-light); margin-bottom: 1rem; line-height: 1.5;">${course.descripcion ? course.descripcion.substring(0, 80) + '...' : 'Contenido diseñado para tu nivel.'}</p>
                <div class="course-meta">
                    <span>⏱️ ${course.duracion || '2h'}</span>
                    <span>📖 ${course.total_lecciones || '8'} lecciones</span>
                </div>
            </div>
        </div>
    `).join('');
}

function goToRegister() {
    window.location.href = '../auth/registro-onboarding.html';
}
