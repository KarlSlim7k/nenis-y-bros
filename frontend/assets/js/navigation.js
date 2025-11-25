/**
 * ============================================================================
 * NAVEGACIÓN - Menús dinámicos según rol de usuario
 * ============================================================================
 */

/**
 * Obtiene el menú de navegación según el tipo de usuario
 * Nota: BASE_PATH se define en config.js que debe cargarse antes
 */
function getMenuItems(tipoUsuario) {
    // Asegurar que BASE_PATH existe (fallback por si config.js no cargó)
    const bp = typeof BASE_PATH !== 'undefined' ? BASE_PATH : '';
    
    const baseMenu = {
        administrador: [
            { section: 'Administración', items: [
                { icon: '📊', text: 'Dashboard', url: `${bp}/frontend/pages/admin/dashboard.html` },
                { icon: '👥', text: 'Usuarios', url: `${bp}/frontend/pages/admin/usuarios.html` },
                { icon: '📚', text: 'Cursos', url: `${bp}/frontend/pages/admin/cursos.html` },
                { icon: '📋', text: 'Diagnósticos', url: `${bp}/frontend/pages/admin/diagnosticos.html` },
                { icon: '🛍️', text: 'Productos', url: `${bp}/frontend/pages/admin/productos.html` },
                { icon: '📖', text: 'Recursos', url: `${bp}/frontend/pages/admin/recursos.html` },
                { icon: '🔍', text: 'Auditoría', url: `${bp}/frontend/pages/admin/auditoria.html` },
                { icon: '⚙️', text: 'Configuración', url: `${bp}/frontend/pages/admin/configuracion.html` }
            ]}
        ],
        mentor: [
            { section: 'Principal', items: [
                { icon: '📊', text: 'Dashboard', url: `${bp}/frontend/pages/instructor/dashboard.html` },
                { icon: '📚', text: 'Mis Cursos', url: `${bp}/frontend/pages/instructor/cursos.html` },
                { icon: '👥', text: 'Mis Alumnos', url: `${bp}/frontend/pages/instructor/alumnos.html` },
                { icon: '💬', text: 'Mensajes', url: `${bp}/frontend/pages/user/mis-conversaciones.html` }
            ]},
            { section: 'Mentoría', items: [
                { icon: '🤖', text: 'Mentoría AI', url: `${bp}/frontend/pages/user/mentoria-ai.html` },
                { icon: '📅', text: 'Disponibilidad', url: `${bp}/frontend/pages/instructor/disponibilidad.html` }
            ]}
        ],
        empresario: [
            { section: 'Principal', items: [
                { icon: '📊', text: 'Diagnósticos', url: `${bp}/frontend/pages/user/diagnosticos.html` },
                { icon: '📈', text: 'Mi Progreso', url: `${bp}/frontend/pages/user/mi-progreso.html` },
                { icon: '🏢', text: 'Mi Empresa', url: `${bp}/frontend/pages/user/perfil-empresarial.html` }
            ]},
            { section: 'Formación', items: [
                { icon: '📚', text: 'Cursos', url: `${bp}/frontend/pages/cursos/catalogo.html` },
                { icon: '📖', text: 'Recursos', url: `${bp}/frontend/pages/recursos/biblioteca.html` },
                { icon: '🎓', text: 'Certificados', url: `${bp}/frontend/pages/user/mis-certificados.html` }
            ]},
            { section: 'Productos', items: [
                { icon: '🛍️', text: 'Mis Productos', url: `${bp}/frontend/pages/user/mis-productos.html` },
                { icon: '➕', text: 'Publicar', url: `${bp}/frontend/pages/user/publicar-producto.html` },
                { icon: '🏪', text: 'Vitrina', url: `${bp}/frontend/pages/user/vitrina-productos.html` }
            ]},
            { section: 'Gamificación', items: [
                { icon: '🏆', text: 'Mis Logros', url: `${bp}/frontend/pages/user/mis-logros.html` },
                { icon: '🥇', text: 'Ranking', url: `${bp}/frontend/pages/user/ranking.html` },
                { icon: '🔔', text: 'Notificaciones', url: `${bp}/frontend/pages/user/notificaciones.html` }
            ]}
        ],
        emprendedor: [
            { section: 'Principal', items: [
                { icon: '📊', text: 'Diagnósticos', url: `${bp}/frontend/pages/user/diagnosticos.html` },
                { icon: '📈', text: 'Mi Progreso', url: `${bp}/frontend/pages/user/mi-progreso.html` }
            ]},
            { section: 'Formación', items: [
                { icon: '📚', text: 'Cursos', url: `${bp}/frontend/pages/cursos/catalogo.html` },
                { icon: '📖', text: 'Recursos', url: `${bp}/frontend/pages/recursos/biblioteca.html` },
                { icon: '🤖', text: 'Mentoría AI', url: `${bp}/frontend/pages/user/mentoria-ai.html` },
                { icon: '🎓', text: 'Certificados', url: `${bp}/frontend/pages/user/mis-certificados.html` }
            ]},
            { section: 'Productos', items: [
                { icon: '🛍️', text: 'Vitrina', url: `${bp}/frontend/pages/user/vitrina-productos.html` }
            ]},
            { section: 'Gamificación', items: [
                { icon: '🏆', text: 'Mis Logros', url: `${bp}/frontend/pages/user/mis-logros.html` },
                { icon: '🥇', text: 'Ranking', url: `${bp}/frontend/pages/user/ranking.html` },
                { icon: '🔔', text: 'Notificaciones', url: `${bp}/frontend/pages/user/notificaciones.html` }
            ]}
        ]
    };

    return baseMenu[tipoUsuario] || baseMenu.emprendedor;
}

/**
 * Genera el HTML de una sidebar de navegación
 */
function generateSidebar(currentPage = '') {
    const user = getAuthUser();
    if (!user) return '';

    const menuSections = getMenuItems(user.tipo_usuario);
    const userName = user.nombre || user.email;
    const userTypeName = getUserTypeName();

    let html = `
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="/nenis_y_bros/index.html" class="sidebar-logo">🚀 Nenis y Bros</a>
                <div class="sidebar-user">
                    <div class="user-avatar-small">${userName.charAt(0).toUpperCase()}</div>
                    <div class="user-info-small">
                        <div class="user-name-small">${userName}</div>
                        <div class="user-type-small">${userTypeName}</div>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
    `;

    menuSections.forEach(section => {
        html += `
                <div class="nav-section">
                    <div class="nav-section-title">${section.section}</div>
        `;

        section.items.forEach(item => {
            const isActive = currentPage && (currentPage === item.url || window.location.pathname.includes(item.url));
            const activeClass = isActive ? ' active' : '';
            
            html += `
                    <a href="${item.url}" class="nav-link${activeClass}">
                        <span class="nav-icon">${item.icon}</span>
                        <span>${item.text}</span>
                    </a>
            `;
        });

        html += `
                </div>
        `;
    });

    html += `
            </nav>
            
            <div class="sidebar-footer">
                <a href="#" class="nav-link" onclick="logout(); return false;">
                    <span class="nav-icon">🚪</span>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </aside>
    `;

    return html;
}

/**
 * Inyecta la sidebar en el DOM
 */
function injectSidebar(containerId = 'sidebar-container', currentPage = '') {
    const container = document.getElementById(containerId);
    if (!container) {
        console.warn('Sidebar container not found:', containerId);
        return;
    }

    const sidebarHtml = generateSidebar(currentPage);
    container.innerHTML = sidebarHtml;
}

/**
 * Obtiene los breadcrumbs según la página actual
 */
function generateBreadcrumbs() {
    const path = window.location.pathname;
    const parts = path.split('/').filter(p => p);
    
    const breadcrumbs = [{ text: 'Inicio', url: '/nenis_y_bros/index.html' }];
    
    // Construir breadcrumbs basado en la ruta
    if (path.includes('/admin/')) {
        breadcrumbs.push({ text: 'Admin', url: '/nenis_y_bros/frontend/pages/admin/dashboard.html' });
    } else if (path.includes('/instructor/')) {
        breadcrumbs.push({ text: 'Instructor', url: '/nenis_y_bros/frontend/pages/instructor/dashboard.html' });
    } else if (path.includes('/user/')) {
        breadcrumbs.push({ text: 'Usuario', url: '/nenis_y_bros/frontend/pages/user/diagnosticos.html' });
    }
    
    return breadcrumbs;
}

/**
 * Verifica si el usuario tiene acceso a una página
 */
function checkPageAccess(requiredRole = null) {
    if (!isAuthenticated()) {
        window.location.href = ROUTES.login + '?redirect=' + encodeURIComponent(window.location.pathname);
        return false;
    }

    if (requiredRole) {
        const user = getAuthUser();
        if (user.tipo_usuario !== requiredRole) {
            alert('No tienes permisos para acceder a esta página.');
            // Redirigir al dashboard correcto según tipo de usuario
            redirectToDashboard();
            return false;
        }
    }

    return true;
}

/**
 * Redirige al dashboard correcto según el tipo de usuario
 */
function redirectToDashboard() {
    const user = getAuthUser();
    if (!user) {
        window.location.href = ROUTES.login;
        return;
    }

    const dashboards = {
        'administrador': '/nenis_y_bros/frontend/pages/admin/dashboard.html',
        'mentor': '/nenis_y_bros/frontend/pages/instructor/dashboard.html',
        'empresario': '/nenis_y_bros/frontend/pages/user/dashboard.html',
        'emprendedor': '/nenis_y_bros/frontend/pages/user/dashboard.html'
    };

    window.location.href = dashboards[user.tipo_usuario] || dashboards.emprendedor;
}
