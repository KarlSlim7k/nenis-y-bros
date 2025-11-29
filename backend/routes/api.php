<?php
/**
 * ============================================================================
 * DEFINICIÓN DE RUTAS DE LA API
 * ============================================================================
 * Define todas las rutas disponibles en la aplicación
 * ============================================================================
 */

function registerRoutes(Router $router) {
    
    // =========================================================================
    // RUTAS PÚBLICAS (Sin autenticación)
    // =========================================================================
    
    // Ruta de bienvenida
    $router->get('/', function() {
        Response::success([
            'name' => APP_NAME,
            'version' => API_VERSION,
            'environment' => APP_ENV
        ], 'API funcionando correctamente');
    });
    
    // Health check
    $router->get('/health', function() {
        Response::success([
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    });
    
    // DEBUG: Diagnóstico de tablas (TEMPORAL - Eliminar después)
    $router->get('/debug/tables', function() {
        try {
            $db = Database::getInstance();
            $result = ['tables' => []];
            
            $tablesToCheck = ['cursos', 'categorias_cursos', 'modulos', 'modulos_curso', 
                              'lecciones', 'inscripciones', 'inscripciones_curso', 'progreso_lecciones'];
            
            foreach ($tablesToCheck as $table) {
                try {
                    $count = $db->fetchOne("SELECT COUNT(*) as total FROM $table");
                    $result['tables'][$table] = ['exists' => true, 'count' => $count['total']];
                } catch (Exception $e) {
                    $result['tables'][$table] = ['exists' => false];
                }
            }
            
            Response::success('Diagnóstico completado', $result);
        } catch (Exception $e) {
            Response::serverError('Error: ' . $e->getMessage());
        }
    });
    
    // DEBUG: Ejecutar migración de renombrado de tablas (TEMPORAL - Eliminar después)
    $router->post('/debug/migrate-tables', function() {
        try {
            $db = Database::getInstance();
            $results = [];
            
            // Verificar si necesita migración
            try {
                $db->fetchOne("SELECT 1 FROM modulos_curso LIMIT 1");
                $needsMigration = true;
            } catch (Exception $e) {
                $needsMigration = false;
            }
            
            if (!$needsMigration) {
                Response::success('No se necesita migración - tablas ya correctas', $results);
                return;
            }
            
            // Ejecutar renombrado
            $pdo = $db->getConnection();
            
            // Renombrar modulos_curso -> modulos
            $pdo->exec("RENAME TABLE modulos_curso TO modulos");
            $results[] = 'modulos_curso -> modulos: OK';
            
            // Renombrar inscripciones_curso -> inscripciones  
            $pdo->exec("RENAME TABLE inscripciones_curso TO inscripciones");
            $results[] = 'inscripciones_curso -> inscripciones: OK';
            
            Response::success('Migración completada exitosamente', $results);
        } catch (Exception $e) {
            Response::serverError('Error en migración: ' . $e->getMessage());
        }
    });
    
    // DEBUG: Test de cursos con error detallado
    $router->get('/debug/test-courses', function() {
        try {
            $db = Database::getInstance();
            $results = [];
            
            // Test 1: Consulta básica a cursos
            try {
                $cursos = $db->fetchAll("SELECT * FROM cursos LIMIT 5");
                $results['cursos_query'] = ['success' => true, 'count' => count($cursos)];
            } catch (Exception $e) {
                $results['cursos_query'] = ['success' => false, 'error' => $e->getMessage()];
            }
            
            // Test 2: Consulta con JOIN a categorias
            try {
                $test = $db->fetchAll("SELECT c.*, cat.nombre as categoria FROM cursos c LEFT JOIN categorias_cursos cat ON c.id_categoria = cat.id_categoria LIMIT 5");
                $results['cursos_with_categoria'] = ['success' => true, 'count' => count($test)];
            } catch (Exception $e) {
                $results['cursos_with_categoria'] = ['success' => false, 'error' => $e->getMessage()];
            }
            
            // Test 3: Consulta con subquery a modulos
            try {
                $test = $db->fetchAll("SELECT c.id_curso, c.titulo, (SELECT COUNT(*) FROM modulos WHERE id_curso = c.id_curso) as total_modulos FROM cursos c LIMIT 5");
                $results['cursos_with_modulos'] = ['success' => true, 'count' => count($test)];
            } catch (Exception $e) {
                $results['cursos_with_modulos'] = ['success' => false, 'error' => $e->getMessage()];
            }
            
            // Test 4: Listar columnas de tabla cursos
            try {
                $columns = $db->fetchAll("SHOW COLUMNS FROM cursos");
                $results['cursos_columns'] = array_column($columns, 'Field');
            } catch (Exception $e) {
                $results['cursos_columns'] = ['error' => $e->getMessage()];
            }
            
            // Test 5: Columnas de categorias_cursos
            try {
                $columns = $db->fetchAll("SHOW COLUMNS FROM categorias_cursos");
                $results['categorias_columns'] = array_column($columns, 'Field');
            } catch (Exception $e) {
                $results['categorias_columns'] = ['error' => $e->getMessage()];
            }
            
            // Test 6: Query EXACTA del modelo Curso->findAll
            try {
                $test = $db->fetchAll("SELECT 
                    c.*,
                    cat.nombre as categoria_nombre,
                    cat.slug as categoria_slug,
                    cat.color as categoria_color,
                    u.nombre as instructor_nombre,
                    u.apellido as instructor_apellido,
                    (SELECT COUNT(*) FROM modulos WHERE id_curso = c.id_curso) as total_modulos,
                    (SELECT COUNT(*) FROM lecciones l 
                     INNER JOIN modulos m ON l.id_modulo = m.id_modulo 
                     WHERE m.id_curso = c.id_curso) as total_lecciones
                FROM cursos c
                LEFT JOIN categorias_cursos cat ON c.id_categoria = cat.id_categoria
                LEFT JOIN usuarios u ON c.id_instructor = u.id_usuario
                WHERE c.estado = 'publicado'
                ORDER BY c.fecha_creacion DESC
                LIMIT 10 OFFSET 0");
                $results['full_query'] = ['success' => true, 'count' => count($test)];
            } catch (Exception $e) {
                $results['full_query'] = ['success' => false, 'error' => $e->getMessage()];
            }
            
            Response::success('Tests completados', $results);
        } catch (Exception $e) {
            Response::serverError('Error general: ' . $e->getMessage());
        }
    });

    // =========================================================================
    // RUTAS DE ONBOARDING (Públicas)
    // =========================================================================
    
    $onboardingController = new OnboardingController();
    
    $router->get('/onboarding/preguntas', function() use ($onboardingController) {
        $onboardingController->getPreguntas();
    });
    
    $router->post('/onboarding/guardar-respuestas', function() use ($onboardingController) {
        $onboardingController->guardarRespuestas();
    });
    
    $router->get('/onboarding/cursos-recomendados/{nivel}', function($nivel) use ($onboardingController) {
        $onboardingController->cursosRecomendados($nivel);
    });
    
    // =========================================================================
    // RUTAS DE AUTENTICACIÓN
    // =========================================================================
    
    $authController = new AuthController();
    
    $router->post('/auth/register', function() use ($authController) {
        $authController->register();
    });
    
    $router->post('/auth/login', function() use ($authController) {
        $authController->login();
    });
    
    $router->post('/auth/forgot-password', function() use ($authController) {
        $authController->forgotPassword();
    });
    
    $router->post('/auth/reset-password', function() use ($authController) {
        $authController->resetPassword();
    });
    
    // =========================================================================
    // RUTAS DE USUARIO (Requieren autenticación)
    // =========================================================================
    
    $router->get('/auth/me', function() use ($authController) {
        $authController->me();
    });
    
    $router->post('/auth/logout', function() use ($authController) {
        $authController->logout();
    });
    
    $router->post('/auth/change-password', function() use ($authController) {
        $authController->changePassword();
    });
    
    // =========================================================================
    // RUTAS DE PERFIL DE USUARIO
    // =========================================================================
    
    $userController = new UserController();
    
    $router->get('/users/profile', function() use ($userController) {
        $userController->getProfile();
    });
    
    $router->put('/users/profile', function() use ($userController) {
        $userController->updateProfile();
    });
    
    $router->post('/users/profile/photo', function() use ($userController) {
        $userController->uploadPhoto();
    });
    
    // Configuración de privacidad
    $router->get('/users/privacy-settings', function() use ($userController) {
        $userController->getPrivacySettings();
    });
    
    $router->put('/users/privacy-settings', function() use ($userController) {
        $userController->updatePrivacySettings();
    });
    
    $router->get('/users/{id}', function($id) use ($userController) {
        $userController->getUserById($id);
    });
    
    // =========================================================================
    // RUTAS DE CURSOS (Fase 2A)
    // =========================================================================
    
    $cursoController = new CursoController();
    $moduloController = new ModuloController();
    $leccionController = new LeccionController();
    $progresoController = new ProgresoController();
    
    // Listado y detalle de cursos (público/privado según estado)
    $router->get('/courses', function() use ($cursoController) {
        $cursoController->getCourses();
    });
    
    $router->get('/courses/{id}', function($id) use ($cursoController) {
        $cursoController->getCourseById($id);
    });
    
    // Crear, actualizar y eliminar cursos (instructor/admin)
    $router->post('/courses', function() use ($cursoController) {
        $cursoController->createCourse();
    });
    
    $router->put('/courses/{id}', function($id) use ($cursoController) {
        $cursoController->updateCourse($id);
    });
    
    $router->delete('/courses/{id}', function($id) use ($cursoController) {
        $cursoController->deleteCourse($id);
    });
    
    // Inscripción a cursos
    $router->post('/courses/{id}/enroll', function($id) use ($cursoController) {
        $cursoController->enrollCourse($id);
    });
    
    $router->delete('/courses/{id}/enroll', function($id) use ($cursoController) {
        $cursoController->unenrollCourse($id);
    });
    
    // Estudiantes de un curso
    $router->get('/courses/{id}/students', function($id) use ($cursoController) {
        $cursoController->getCourseStudents($id);
    });
    
    // Mis cursos inscritos
    $router->get('/my-courses', function() use ($cursoController) {
        $cursoController->getMyCourses();
    });
    
    // =========================================================================
    // RUTAS DE MÓDULOS
    // =========================================================================
    
    // Obtener módulos de un curso
    $router->get('/courses/{id}/modules', function($id) use ($moduloController) {
        $moduloController->getModulesByCourse($id);
    });
    
    // Crear módulo en un curso
    $router->post('/courses/{id}/modules', function($id) use ($moduloController) {
        $moduloController->createModule($id);
    });
    
    // Reordenar módulos
    $router->put('/courses/{id}/modules/reorder', function($id) use ($moduloController) {
        $moduloController->reorderModules($id);
    });
    
    // Obtener, actualizar y eliminar módulo específico
    $router->get('/modules/{id}', function($id) use ($moduloController) {
        $moduloController->getModuleById($id);
    });
    
    $router->put('/modules/{id}', function($id) use ($moduloController) {
        $moduloController->updateModule($id);
    });
    
    $router->delete('/modules/{id}', function($id) use ($moduloController) {
        $moduloController->deleteModule($id);
    });
    
    // =========================================================================
    // RUTAS DE LECCIONES
    // =========================================================================
    
    // Obtener lecciones de un módulo
    $router->get('/modules/{id}/lessons', function($id) use ($leccionController) {
        $leccionController->getLessonsByModule($id);
    });
    
    // Crear lección en un módulo
    $router->post('/modules/{id}/lessons', function($id) use ($leccionController) {
        $leccionController->createLesson($id);
    });
    
    // Reordenar lecciones
    $router->put('/modules/{id}/lessons/reorder', function($id) use ($leccionController) {
        $leccionController->reorderLessons($id);
    });
    
    // Obtener, actualizar y eliminar lección específica
    $router->get('/lessons/{id}', function($id) use ($leccionController) {
        $leccionController->getLessonById($id);
    });
    
    $router->put('/lessons/{id}', function($id) use ($leccionController) {
        $leccionController->updateLesson($id);
    });
    
    $router->delete('/lessons/{id}', function($id) use ($leccionController) {
        $leccionController->deleteLesson($id);
    });
    
    // =========================================================================
    // RUTAS DE PROGRESO
    // =========================================================================
    
    // Marcar lección como completada/incompleta
    $router->post('/lessons/{id}/complete', function($id) use ($progresoController) {
        $progresoController->completeLesson($id);
    });
    
    $router->delete('/lessons/{id}/complete', function($id) use ($progresoController) {
        $progresoController->uncompleteLesson($id);
    });
    
    // Registrar tiempo dedicado
    $router->post('/lessons/{id}/time', function($id) use ($progresoController) {
        $progresoController->recordTime($id);
    });
    
    // Progreso de un curso
    $router->get('/courses/{id}/progress', function($id) use ($progresoController) {
        $progresoController->getCourseProgress($id);
    });
    
    // Lecciones completadas y pendientes
    $router->get('/courses/{id}/completed-lessons', function($id) use ($progresoController) {
        $progresoController->getCompletedLessons($id);
    });
    
    $router->get('/courses/{id}/pending-lessons', function($id) use ($progresoController) {
        $progresoController->getPendingLessons($id);
    });
    
    // Siguiente lección pendiente
    $router->get('/courses/{id}/next-lesson', function($id) use ($progresoController) {
        $progresoController->getNextLesson($id);
    });
    
    // Certificado
    $router->post('/courses/{id}/certificate', function($id) use ($progresoController) {
        $progresoController->generateCertificate($id);
    });
    
    // Resetear progreso
    $router->post('/courses/{id}/reset-progress', function($id) use ($progresoController) {
        $progresoController->resetProgress($id);
    });
    
    // Estadísticas del usuario
    $router->get('/my-stats', function() use ($progresoController) {
        $progresoController->getMyStats();
    });
    
    // =========================================================================
    // RUTAS DE ADMINISTRACIÓN (Requieren rol administrador)
    // =========================================================================
    
    $adminController = new AdminController();
    
    $router->get('/admin/dashboard', function() use ($adminController) {
        $adminController->getDashboard();
    });
    
    $router->get('/admin/users', function() use ($adminController) {
        $adminController->getUsers();
    });
    
    $router->get('/admin/users/{id}', function($id) use ($adminController) {
        $adminController->getUserDetails($id);
    });
    
    $router->put('/admin/users/{id}/status', function($id) use ($adminController) {
        $adminController->updateUserStatus($id);
    });

    $router->put('/admin/users/{id}', function($id) use ($adminController) {
        $adminController->updateUser($id);
    });

    
    $router->delete('/admin/users/{id}', function($id) use ($adminController) {
        $adminController->deleteUser($id);
    });
    
    // =========================================================================
    // RUTAS DE PERFILES EMPRESARIALES (Fase 3)
    // =========================================================================
    
    $perfilController = new PerfilEmpresarialController();
    
    // Mi perfil
    $router->get('/perfiles/mi-perfil', function() use ($perfilController) {
        $perfilController->miPerfil();
    });
    
    // Crear perfil
    $router->post('/perfiles', function() use ($perfilController) {
        $perfilController->store();
    });
    
    // Actualizar perfil
    $router->put('/perfiles/{id}', function($id) use ($perfilController) {
        $perfilController->update($id);
    });
    
    // Ver perfil específico
    $router->get('/perfiles/{id}', function($id) use ($perfilController) {
        $perfilController->show($id);
    });
    
    // Eliminar perfil
    $router->delete('/perfiles/{id}', function($id) use ($perfilController) {
        $perfilController->delete($id);
    });
    
    // Listar perfiles (admin)
    $router->get('/perfiles', function() use ($perfilController) {
        $perfilController->index();
    });
    
    // Estadísticas (admin)
    $router->get('/perfiles/stats', function() use ($perfilController) {
        $perfilController->stats();
    });
    
    // Sectores disponibles
    $router->get('/perfiles/sectores', function() use ($perfilController) {
        $perfilController->sectores();
    });
    
    // =========================================================================
    // RUTAS DE DIAGNÓSTICOS (Fase 3)
    // =========================================================================
    
    $diagnosticoController = new DiagnosticoController();
    
    // Tipos de diagnósticos disponibles
    $router->get('/diagnosticos/tipos', function() use ($diagnosticoController) {
        $diagnosticoController->tiposDisponibles();
    });
    
    // Ver tipo de diagnóstico por ID
    $router->get('/diagnosticos/tipos/{id}', function($id) use ($diagnosticoController) {
        $diagnosticoController->verTipoDiagnostico($id);
    });
    
    // Ver tipo de diagnóstico por slug
    $router->get('/diagnosticos/tipos/slug/{slug}', function($slug) use ($diagnosticoController) {
        $diagnosticoController->verTipoDiagnosticoPorSlug($slug);
    });
    
    // Mis diagnósticos realizados
    $router->get('/diagnosticos/mis-diagnosticos', function() use ($diagnosticoController) {
        $diagnosticoController->misDiagnosticos();
    });
    
    // Iniciar nuevo diagnóstico
    $router->post('/diagnosticos/iniciar', function() use ($diagnosticoController) {
        $diagnosticoController->iniciar();
    });
    
    // Ver diagnóstico específico
    $router->get('/diagnosticos/{id}', function($id) use ($diagnosticoController) {
        $diagnosticoController->show($id);
    });
    
    // Responder una pregunta
    $router->post('/diagnosticos/{id}/responder', function($id) use ($diagnosticoController) {
        $diagnosticoController->responder($id);
    });
    
    // Responder múltiples preguntas
    $router->post('/diagnosticos/{id}/respuestas-multiples', function($id) use ($diagnosticoController) {
        $diagnosticoController->responderMultiples($id);
    });
    
    // Finalizar diagnóstico
    $router->post('/diagnosticos/{id}/finalizar', function($id) use ($diagnosticoController) {
        $diagnosticoController->finalizar($id);
    });
    
    // Ver resultados
    $router->get('/diagnosticos/{id}/resultados', function($id) use ($diagnosticoController) {
        $diagnosticoController->resultados($id);
    });
    
    // Generar recomendaciones
    $router->post('/diagnosticos/{id}/recomendaciones', function($id) use ($diagnosticoController) {
        $diagnosticoController->generarRecomendaciones($id);
    });
    
    // Comparar diagnósticos
    $router->get('/diagnosticos/{idActual}/comparar/{idAnterior}', function($idActual, $idAnterior) use ($diagnosticoController) {
        $diagnosticoController->comparar($idActual, $idAnterior);
    });
    
    // Eliminar/cancelar diagnóstico
    $router->delete('/diagnosticos/{id}', function($id) use ($diagnosticoController) {
        $diagnosticoController->delete($id);
    });
    
    // =========================================================================
    // RUTAS DE EVALUACIONES (FASE 2B)
    // =========================================================================
    $evaluacionController = new EvaluacionController();
    
    // CRUD Evaluaciones (Admin/Instructor)
    $router->post('/evaluaciones', function() use ($evaluacionController) {
        $evaluacionController->crear();
    });
    
    $router->get('/evaluaciones/{id}', function($id) use ($evaluacionController) {
        $evaluacionController->obtener($id);
    });
    
    $router->put('/evaluaciones/{id}', function($id) use ($evaluacionController) {
        $evaluacionController->actualizar($id);
    });
    
    $router->delete('/evaluaciones/{id}', function($id) use ($evaluacionController) {
        $evaluacionController->eliminar($id);
    });
    
    // Listar evaluaciones por lección/curso
    $router->get('/lecciones/{id}/evaluaciones', function($id) use ($evaluacionController) {
        $evaluacionController->listarPorLeccion($id);
    });
    
    $router->get('/cursos/{id}/evaluaciones', function($id) use ($evaluacionController) {
        $evaluacionController->listarPorCurso($id);
    });
    
    // Intentos de evaluación
    $router->post('/evaluaciones/{id}/iniciar', function($id) use ($evaluacionController) {
        $evaluacionController->iniciarIntento($id);
    });
    
    $router->post('/evaluaciones/intentos/{id}/responder', function($id) use ($evaluacionController) {
        $evaluacionController->responder($id);
    });
    
    $router->post('/evaluaciones/intentos/{id}/finalizar', function($id) use ($evaluacionController) {
        $evaluacionController->finalizarIntento($id);
    });
    
    $router->get('/evaluaciones/intentos/{id}/resultados', function($id) use ($evaluacionController) {
        $evaluacionController->obtenerResultados($id);
    });
    
    $router->get('/evaluaciones/{id}/mis-intentos', function($id) use ($evaluacionController) {
        $evaluacionController->misIntentos($id);
    });
    
    // Estadísticas (Admin/Instructor)
    $router->get('/evaluaciones/{id}/estadisticas', function($id) use ($evaluacionController) {
        $evaluacionController->obtenerEstadisticas($id);
    });
    
    // Certificados
    $router->get('/mis-certificados', function() use ($evaluacionController) {
        $evaluacionController->misCertificados();
    });
    
    $router->get('/certificados/{id}', function($id) use ($evaluacionController) {
        $evaluacionController->obtenerCertificado($id);
    });
    
    // Verificación pública de certificados (sin auth)
    $router->get('/certificados/verificar/{codigo}', function($codigo) use ($evaluacionController) {
        $evaluacionController->verificarCertificado($codigo);
    });
    
    // =========================================================================
    // GAMIFICACIÓN (Puntos, Logros, Rachas, Notificaciones)
    // =========================================================================
    
    $gamificacionController = new GamificacionController();
    
    // --- PUNTOS ---
    $router->get('/gamificacion/puntos', function() use ($gamificacionController) {
        $gamificacionController->misPuntos();
    });
    
    $router->get('/gamificacion/puntos/transacciones', function() use ($gamificacionController) {
        $gamificacionController->misTransacciones();
    });
    
    $router->get('/gamificacion/ranking', function() use ($gamificacionController) {
        $gamificacionController->ranking();
    });
    
    // --- LOGROS ---
    $router->get('/gamificacion/logros', function() use ($gamificacionController) {
        $gamificacionController->catalogoLogros();
    });
    
    $router->get('/gamificacion/logros/mis-logros', function() use ($gamificacionController) {
        $gamificacionController->misLogros();
    });
    
    $router->get('/gamificacion/logros/no-vistos', function() use ($gamificacionController) {
        $gamificacionController->logrosNoVistos();
    });
    
    $router->put('/gamificacion/logros/{id}/marcar-visto', function($id) use ($gamificacionController) {
        $gamificacionController->marcarLogroVisto($id);
    });
    
    // --- RACHAS ---
    $router->get('/gamificacion/racha', function() use ($gamificacionController) {
        $gamificacionController->miRacha();
    });
    
    $router->post('/gamificacion/racha/registrar', function() use ($gamificacionController) {
        $gamificacionController->registrarActividad();
    });
    
    $router->get('/gamificacion/racha/ranking', function() use ($gamificacionController) {
        $gamificacionController->rankingRachas();
    });
    
    // --- NOTIFICACIONES ---
    $router->get('/gamificacion/notificaciones', function() use ($gamificacionController) {
        $gamificacionController->misNotificaciones();
    });
    
    $router->get('/gamificacion/notificaciones/contador', function() use ($gamificacionController) {
        $gamificacionController->contadorNotificaciones();
    });
    
    $router->put('/gamificacion/notificaciones/{id}/leer', function($id) use ($gamificacionController) {
        $gamificacionController->marcarLeida($id);
    });
    
    $router->put('/gamificacion/notificaciones/leer-todas', function() use ($gamificacionController) {
        $gamificacionController->marcarTodasLeidas();
    });
    
    $router->delete('/gamificacion/notificaciones/{id}', function($id) use ($gamificacionController) {
        $gamificacionController->eliminarNotificacion($id);
    });
    
    $router->delete('/gamificacion/notificaciones/limpiar-leidas', function() use ($gamificacionController) {
        $gamificacionController->limpiarLeidas();
    });
    
    $router->get('/gamificacion/notificaciones/preferencias', function() use ($gamificacionController) {
        $gamificacionController->preferenciasNotificaciones();
    });
    
    $router->put('/gamificacion/notificaciones/preferencias', function() use ($gamificacionController) {
        $gamificacionController->actualizarPreferencias();
    });
    
    // --- DASHBOARD ---
    $router->get('/gamificacion/dashboard', function() use ($gamificacionController) {
        $gamificacionController->dashboard();
    });
    
    // =========================================================================
    // PRODUCTOS - MARKETPLACE (Fase 5A)
    // =========================================================================
    
    $productoController = new ProductoController();
    
    // --- CATEGORÍAS ---
    $router->get('/productos/categorias', function() use ($productoController) {
        $productoController->getCategorias();
    });
    
    $router->post('/productos/categorias', function() use ($productoController) {
        AuthMiddleware::requireAuth();
        // Solo admin puede crear categorías
        $productoController->crearCategoria();
    });
    
    // --- BÚSQUEDA Y CATÁLOGO PÚBLICO ---
    $router->get('/productos', function() use ($productoController) {
        $productoController->buscarProductos();
    });
    
    $router->get('/productos/slug/{slug}', function($slug) use ($productoController) {
        $productoController->getProductoPorSlug($slug);
    });
    
    // --- MIS PRODUCTOS (Vendedor) ---
    $router->get('/productos/mis-productos', function() use ($productoController) {
        AuthMiddleware::requireAuth();
        $productoController->getMisProductos();
    });
    
    $router->get('/productos/estadisticas-vendedor', function() use ($productoController) {
        AuthMiddleware::requireAuth();
        $productoController->getEstadisticasVendedor();
    });
    
    // --- CRUD PRODUCTOS ---
    $router->post('/productos', function() use ($productoController) {
        AuthMiddleware::requireAuth();
        $productoController->crearProducto();
    });
    
    $router->get('/productos/{id}', function($id) use ($productoController) {
        $productoController->getProducto($id);
    });
    
    $router->put('/productos/{id}', function($id) use ($productoController) {
        AuthMiddleware::requireAuth();
        $productoController->actualizarProducto($id);
    });
    
    $router->delete('/productos/{id}', function($id) use ($productoController) {
        AuthMiddleware::requireAuth();
        $productoController->eliminarProducto($id);
    });
    
    $router->post('/productos/{id}/estado', function($id) use ($productoController) {
        AuthMiddleware::requireAuth();
        $productoController->cambiarEstado($id);
    });
    
    // --- IMÁGENES ---
    $router->post('/productos/{id}/imagenes', function($id) use ($productoController) {
        AuthMiddleware::requireAuth();
        $productoController->agregarImagen($id);
    });
    
    $router->delete('/productos/imagenes/{id}', function($id) use ($productoController) {
        AuthMiddleware::requireAuth();
        $productoController->eliminarImagen($id);
    });
    
    $router->post('/productos/imagenes/{id}/principal', function($id) use ($productoController) {
        AuthMiddleware::requireAuth();
        $productoController->establecerImagenPrincipal($id);
    });
    
    // --- FAVORITOS ---
    $router->post('/productos/{id}/favorito', function($id) use ($productoController) {
        AuthMiddleware::requireAuth();
        $productoController->toggleFavorito($id);
    });
    
    $router->get('/productos/favoritos', function() use ($productoController) {
        AuthMiddleware::requireAuth();
        $productoController->getFavoritos();
    });
    
    // --- INTERACCIONES ---
    $router->post('/productos/{id}/contacto', function($id) use ($productoController) {
        $productoController->registrarContacto($id);
    });
    
    // =========================================================================
    // RUTAS DE MENTORÍA Y CHAT (FASE 5B)
    // =========================================================================
    
    $mentoriaController = new MentoriaController();
    
    // --- CONVERSACIONES ---
    $router->post('/chat/conversaciones', function() use ($mentoriaController) {
        $mentoriaController->crearConversacion();
    });
    
    $router->get('/chat/conversaciones', function() use ($mentoriaController) {
        $mentoriaController->getConversaciones();
    });
    
    $router->get('/chat/conversaciones/{id}', function($id) use ($mentoriaController) {
        $mentoriaController->getConversacion($id);
    });
    
    $router->post('/chat/conversaciones/{id}/archivar', function($id) use ($mentoriaController) {
        $mentoriaController->archivarConversacion($id);
    });
    
    // --- MENSAJES ---
    $router->post('/chat/mensajes', function() use ($mentoriaController) {
        $mentoriaController->enviarMensaje();
    });
    
    $router->get('/chat/mensajes/{id_conversacion}/nuevos', function($idConversacion) use ($mentoriaController) {
        $mentoriaController->getMensajesNuevos($idConversacion);
    });
    
    $router->put('/chat/mensajes/{id}/leer', function($id) use ($mentoriaController) {
        $mentoriaController->marcarLeido($id);
    });
    
    // --- DISPONIBILIDAD ---
    $router->get('/chat/disponibilidad/{id_instructor}', function($idInstructor) use ($mentoriaController) {
        $mentoriaController->getDisponibilidad($idInstructor);
    });
    
    $router->post('/chat/disponibilidad', function() use ($mentoriaController) {
        $mentoriaController->configurarDisponibilidad();
    });
    
    $router->put('/chat/estado', function() use ($mentoriaController) {
        $mentoriaController->cambiarEstado();
    });
    
    // --- ESTADÍSTICAS ---
    $router->get('/chat/estadisticas/instructor', function() use ($mentoriaController) {
        $mentoriaController->getEstadisticasInstructor();
    });
    
    // --- MENTORÍA IA (Placeholders para Fase 5B.2) ---
    $router->post('/mentoria/iniciar', function() use ($mentoriaController) {
        $mentoriaController->iniciarMentoria();
    });
    
    $router->post('/mentoria/preguntar', function() use ($mentoriaController) {
        $mentoriaController->preguntarMentoria();
    });
    
    $router->post('/mentoria/feedback', function() use ($mentoriaController) {
        $mentoriaController->feedbackMentoria();
    });
    
    $router->get('/mentoria/estadisticas', function() use ($mentoriaController) {
        $mentoriaController->getEstadisticasMentoria();
    });
    
    // =========================================================================
    // RUTAS DE RECURSOS (FASE 6 - Biblioteca)
    // =========================================================================
    
    $recursoController = new RecursoController();
    
    // --- CATEGORÍAS ---
    $router->get('/recursos/categorias', function() use ($recursoController) {
        $recursoController->listarCategorias();
    });
    
    $router->get('/recursos/categorias/{id}', function($id) use ($recursoController) {
        $recursoController->obtenerCategoria($id);
    });
    
    $router->post('/recursos/categorias', function() use ($recursoController) {
        $recursoController->crearCategoria();
    });
    
    $router->put('/recursos/categorias/{id}', function($id) use ($recursoController) {
        $recursoController->actualizarCategoria($id);
    });
    
    $router->delete('/recursos/categorias/{id}', function($id) use ($recursoController) {
        $recursoController->eliminarCategoria($id);
    });
    
    // --- RECURSOS ---
    $router->get('/recursos', function() use ($recursoController) {
        $recursoController->listarRecursos();
    });
    
    $router->get('/recursos/destacados', function() use ($recursoController) {
        $recursoController->listarDestacados();
    });
    
    $router->get('/recursos/buscar', function() use ($recursoController) {
        $recursoController->buscarRecursos();
    });
    
    $router->get('/recursos/mis-descargas', function() use ($recursoController) {
        $recursoController->misDescargas();
    });
    
    $router->get('/recursos/estadisticas', function() use ($recursoController) {
        $recursoController->estadisticas();
    });
    
    $router->get('/recursos/{id}', function($id) use ($recursoController) {
        $recursoController->obtenerRecurso($id);
    });
    
    $router->get('/recursos/slug/{slug}', function($slug) use ($recursoController) {
        $recursoController->obtenerRecursoPorSlug($slug);
    });
    
    $router->post('/recursos', function() use ($recursoController) {
        $recursoController->crearRecurso();
    });
    
    $router->put('/recursos/{id}', function($id) use ($recursoController) {
        $recursoController->actualizarRecurso($id);
    });
    
    $router->delete('/recursos/{id}', function($id) use ($recursoController) {
        $recursoController->eliminarRecurso($id);
    });
    
    // --- DESCARGAS ---
    $router->post('/recursos/{id}/descargar', function($id) use ($recursoController) {
        $recursoController->descargarRecurso($id);
    });
    
    // --- CALIFICACIONES ---
    $router->post('/recursos/{id}/calificar', function($id) use ($recursoController) {
        $recursoController->calificarRecurso($id);
    });
    
    $router->get('/recursos/{id}/calificaciones', function($id) use ($recursoController) {
        $recursoController->obtenerCalificaciones($id);
    });
    
    // --- RELACIONADOS ---
    $router->get('/recursos/{id}/relacionados', function($id) use ($recursoController) {
        $recursoController->obtenerRelacionados($id);
    });
    
    // --- VERSIONADO ---
    $router->get('/recursos/{id}/versiones', function($id) use ($recursoController) {
        $recursoController->obtenerVersiones($id);
    });
    
    $router->get('/recursos/{id}/versiones/{numero}', function($id, $numero) use ($recursoController) {
        $recursoController->obtenerVersion($id, $numero);
    });
    
    $router->post('/recursos/{id}/versiones/{numero}/restaurar', function($id, $numero) use ($recursoController) {
        $recursoController->restaurarVersion($id, $numero);
    });
    
    $router->get('/recursos/{id}/versiones/comparar', function($id) use ($recursoController) {
        $recursoController->compararVersiones($id);
    });
    
    $router->get('/recursos/versiones/estadisticas', function() use ($recursoController) {
        $recursoController->estadisticasVersionado();
    });
    
    $router->get('/recursos/versiones/recientes', function() use ($recursoController) {
        $recursoController->cambiosRecientes();
    });
    
    // --- ANALYTICS AVANZADO ---
    $router->get('/recursos/analytics/dashboard', function() use ($recursoController) {
        $recursoController->analyticsDashboard();
    });
    
    $router->get('/recursos/analytics/descargas-tiempo', function() use ($recursoController) {
        $recursoController->analyticsDescargasTiempo();
    });
    
    $router->get('/recursos/analytics/mas-descargados', function() use ($recursoController) {
        $recursoController->analyticsMasDescargados();
    });
    
    $router->get('/recursos/analytics/mas-vistos', function() use ($recursoController) {
        $recursoController->analyticsMasVistos();
    });
    
    $router->get('/recursos/analytics/mejor-calificados', function() use ($recursoController) {
        $recursoController->analyticsMejorCalificados();
    });
    
    $router->get('/recursos/analytics/tasa-conversion', function() use ($recursoController) {
        $recursoController->analyticsTasaConversion();
    });
    
    $router->get('/recursos/analytics/distribucion-categoria', function() use ($recursoController) {
        $recursoController->analyticsDistribucionCategoria();
    });
    
    $router->get('/recursos/analytics/distribucion-tipo', function() use ($recursoController) {
        $recursoController->analyticsDistribucionTipo();
    });
    
    $router->get('/recursos/analytics/tendencias', function() use ($recursoController) {
        $recursoController->analyticsTendencias();
    });
    
    $router->get('/recursos/analytics/usuarios-activos', function() use ($recursoController) {
        $recursoController->analyticsUsuariosActivos();
    });
    
    // =========================================================================
    // DESCARGA SEGURA Y OPTIMIZACIÓN DE ARCHIVOS
    // =========================================================================
    
    // Descargar recurso con URL firmada (no requiere autenticación, la seguridad está en el token)
    $router->get('/recursos/download/{token}', function($token) use ($recursoController) {
        $recursoController->descargarRecursoSeguro($token);
    });
    
    // Generar URL de descarga temporal (requiere autenticación)
    $router->post('/recursos/{id}/generar-url-descarga', function($id) use ($recursoController) {
        $recursoController->generarUrlDescarga($id);
    });
    
    // Optimizar imagen al subir (requiere autenticación y permisos)
    $router->post('/recursos/optimizar-imagen', function() use ($recursoController) {
        $recursoController->optimizarImagen();
    });
    
    // =========================================================================
    // RUTA NO ENCONTRADA
    // =========================================================================
    
    $router->setNotFound(function() {
        Response::notFound('Endpoint no encontrado');
    });
}
