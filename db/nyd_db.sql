-- ============================================================================
-- BASE DE DATOS: SISTEMA DE APOYO A DIAGNÓSTICO Y FORMACIÓN EMPRESARIAL
-- ============================================================================
-- Descripción: Sistema integral para gestión de usuarios, cursos, diagnósticos
--              empresariales, logros y vitrina de productos
-- ============================================================================

CREATE DATABASE IF NOT EXISTS formacion_empresarial
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE formacion_empresarial;

-- ============================================================================
-- TABLA: usuarios
-- Descripción: Almacena información de los usuarios del sistema
-- ============================================================================
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    telefono VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    
    -- Tipo de usuario: 'emprendedor', 'empresario', 'mentor', 'administrador'
    tipo_usuario ENUM('emprendedor', 'empresario', 'mentor', 'administrador') NOT NULL DEFAULT 'emprendedor',
    
    -- Estado del usuario
    estado ENUM('activo', 'inactivo', 'suspendido') NOT NULL DEFAULT 'activo',
    
    -- Información adicional
    foto_perfil VARCHAR(255),
    biografia TEXT,
    ciudad VARCHAR(100),
    pais VARCHAR(100),
    
    -- Configuración de privacidad (JSON)
    configuracion_privacidad JSON COMMENT 'Configuración de privacidad del usuario',
    
    -- Auditoría
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso DATETIME,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_tipo_usuario (tipo_usuario),
    INDEX idx_estado (estado)
) ENGINE=InnoDB COMMENT='Usuarios del sistema de formación empresarial';

-- ============================================================================
-- TABLA: perfiles_empresariales
-- Descripción: Información del negocio/emprendimiento de cada usuario
-- ============================================================================
CREATE TABLE perfiles_empresariales (
    id_perfil INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    
    -- Información del negocio
    nombre_empresa VARCHAR(200) NOT NULL,
    descripcion TEXT,
    sector_industrial VARCHAR(100),
    tipo_empresa ENUM('emprendimiento', 'micro', 'pequeña', 'mediana', 'grande') NOT NULL,
    
    -- Datos de contacto empresarial
    sitio_web VARCHAR(255),
    email_empresa VARCHAR(150),
    telefono_empresa VARCHAR(20),
    direccion TEXT,
    
    -- Estado del negocio
    etapa_desarrollo ENUM('idea', 'validacion', 'desarrollo', 'lanzamiento', 'crecimiento', 'expansion') NOT NULL DEFAULT 'idea',
    anios_operacion INT DEFAULT 0,
    numero_empleados INT DEFAULT 0,
    
    -- Logo/imagen
    logo_empresa VARCHAR(255),
    
    -- Auditoría
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    INDEX idx_sector (sector_industrial),
    INDEX idx_tipo_empresa (tipo_empresa)
) ENGINE=InnoDB COMMENT='Perfiles empresariales de los usuarios';

-- ============================================================================
-- TABLA: categorias_cursos
-- Descripción: Categorías para organizar los cursos
-- ============================================================================
CREATE TABLE categorias_cursos (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    icono VARCHAR(100), -- Nombre del icono o ruta
    color VARCHAR(7), -- Código hexadecimal del color
    orden INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_activo (activo)
) ENGINE=InnoDB COMMENT='Categorías de cursos disponibles';

-- ============================================================================
-- TABLA: cursos
-- Descripción: Cursos de formación disponibles en el sistema
-- ============================================================================
CREATE TABLE cursos (
    id_curso INT AUTO_INCREMENT PRIMARY KEY,
    id_categoria INT NOT NULL,
    id_instructor INT, -- Usuario que es mentor/instructor
    
    -- Información del curso
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    objetivo_aprendizaje TEXT,
    
    -- Nivel y duración
    nivel ENUM('basico', 'intermedio', 'avanzado', 'experto') NOT NULL DEFAULT 'basico',
    duracion_horas DECIMAL(5,2),
    
    -- Multimedia
    imagen_portada VARCHAR(255),
    video_presentacion VARCHAR(255),
    
    -- Control de acceso
    es_gratuito BOOLEAN DEFAULT TRUE,
    precio DECIMAL(10,2) DEFAULT 0.00,
    requiere_prerequisitos BOOLEAN DEFAULT FALSE,
    
    -- Estado
    estado ENUM('borrador', 'publicado', 'archivado') NOT NULL DEFAULT 'borrador',
    
    -- Estadísticas
    total_inscritos INT DEFAULT 0,
    calificacion_promedio DECIMAL(3,2) DEFAULT 0.00,
    total_calificaciones INT DEFAULT 0,
    
    -- Auditoría
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_publicacion DATETIME,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_categoria) REFERENCES categorias_cursos(id_categoria),
    FOREIGN KEY (id_instructor) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    INDEX idx_nivel (nivel),
    INDEX idx_estado (estado),
    INDEX idx_categoria (id_categoria)
) ENGINE=InnoDB COMMENT='Cursos de formación empresarial';

-- ============================================================================
-- TABLA: modulos
-- Descripción: Módulos que componen cada curso
-- ============================================================================
CREATE TABLE modulos (
    id_modulo INT AUTO_INCREMENT PRIMARY KEY,
    id_curso INT NOT NULL,
    
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    orden INT NOT NULL,
    duracion_horas DECIMAL(4,2),
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE,
    INDEX idx_curso_orden (id_curso, orden)
) ENGINE=InnoDB COMMENT='Módulos de cada curso';

-- ============================================================================
-- TABLA: lecciones
-- Descripción: Lecciones dentro de cada módulo
-- ============================================================================
CREATE TABLE lecciones (
    id_leccion INT AUTO_INCREMENT PRIMARY KEY,
    id_modulo INT NOT NULL,
    
    titulo VARCHAR(200) NOT NULL,
    contenido LONGTEXT,
    tipo_contenido ENUM('texto', 'video', 'presentacion', 'documento', 'quiz', 'practica') NOT NULL DEFAULT 'texto',
    url_recurso VARCHAR(500), -- URL del video, documento, etc.
    duracion_minutos INT,
    orden INT NOT NULL,
    
    -- Puntos que otorga al completar
    puntos_otorgados INT DEFAULT 10,
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_modulo) REFERENCES modulos(id_modulo) ON DELETE CASCADE,
    INDEX idx_modulo_orden (id_modulo, orden)
) ENGINE=InnoDB COMMENT='Lecciones de cada módulo';

-- ============================================================================
-- TABLA: inscripciones
-- Descripción: Registro de usuarios inscritos en cursos
-- ============================================================================
CREATE TABLE inscripciones (
    id_inscripcion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_curso INT NOT NULL,
    
    -- Control de progreso
    fecha_inscripcion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_inicio DATETIME,
    fecha_finalizacion DATETIME,
    
    -- Estado del curso
    estado ENUM('inscrito', 'en_progreso', 'completado', 'abandonado') NOT NULL DEFAULT 'inscrito',
    progreso_porcentaje DECIMAL(5,2) DEFAULT 0.00,
    
    -- Calificación del curso
    calificacion INT, -- 1-5 estrellas
    comentario_calificacion TEXT,
    fecha_calificacion DATETIME,
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE,
    UNIQUE KEY uk_usuario_curso (id_usuario, id_curso),
    INDEX idx_estado (estado),
    INDEX idx_fecha_inscripcion (fecha_inscripcion)
) ENGINE=InnoDB COMMENT='Inscripciones de usuarios a cursos';

-- ============================================================================
-- TABLA: progreso_lecciones
-- Descripción: Seguimiento del progreso en cada lección
-- ============================================================================
CREATE TABLE progreso_lecciones (
    id_progreso INT AUTO_INCREMENT PRIMARY KEY,
    id_inscripcion INT NOT NULL,
    id_leccion INT NOT NULL,
    
    completada BOOLEAN DEFAULT FALSE,
    fecha_inicio DATETIME,
    fecha_completado DATETIME,
    tiempo_dedicado_minutos INT DEFAULT 0,
    
    -- Calificación si es un quiz
    puntuacion DECIMAL(5,2),
    intentos INT DEFAULT 0,
    
    FOREIGN KEY (id_inscripcion) REFERENCES inscripciones(id_inscripcion) ON DELETE CASCADE,
    FOREIGN KEY (id_leccion) REFERENCES lecciones(id_leccion) ON DELETE CASCADE,
    UNIQUE KEY uk_inscripcion_leccion (id_inscripcion, id_leccion),
    INDEX idx_completada (completada)
) ENGINE=InnoDB COMMENT='Progreso de usuarios en lecciones';

-- ============================================================================
-- TABLA: categorias_logros
-- Descripción: Categorías para organizar los logros/badges
-- ============================================================================
CREATE TABLE categorias_logros (
    id_categoria_logro INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    icono VARCHAR(100),
    color VARCHAR(7),
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='Categorías de logros y badges';

-- ============================================================================
-- TABLA: logros
-- Descripción: Logros/badges que los usuarios pueden obtener
-- ============================================================================
CREATE TABLE logros (
    id_logro INT AUTO_INCREMENT PRIMARY KEY,
    id_categoria_logro INT,
    
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    icono VARCHAR(255), -- Ruta del icono/badge
    
    -- Criterios para obtener el logro
    tipo_logro ENUM('curso_completado', 'cursos_cantidad', 'puntos_acumulados', 'tiempo_plataforma', 'evaluacion_alta', 'racha_dias', 'diagnostico_completado', 'producto_publicado', 'otro') NOT NULL,
    criterio_valor INT, -- Valor necesario para obtener el logro
    
    -- Recompensa
    puntos_recompensa INT DEFAULT 0,
    
    -- Visibilidad
    es_secreto BOOLEAN DEFAULT FALSE, -- Si es un logro oculto
    nivel_dificultad ENUM('bronce', 'plata', 'oro', 'platino') DEFAULT 'bronce',
    
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_categoria_logro) REFERENCES categorias_logros(id_categoria_logro) ON DELETE SET NULL,
    INDEX idx_tipo_logro (tipo_logro)
) ENGINE=InnoDB COMMENT='Logros y badges del sistema';

-- ============================================================================
-- TABLA: logros_usuarios
-- Descripción: Logros obtenidos por cada usuario
-- ============================================================================
CREATE TABLE logros_usuarios (
    id_logro_usuario INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_logro INT NOT NULL,
    
    fecha_obtencion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    visto BOOLEAN DEFAULT FALSE, -- Si el usuario ha visto la notificación
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_logro) REFERENCES logros(id_logro) ON DELETE CASCADE,
    UNIQUE KEY uk_usuario_logro (id_usuario, id_logro),
    INDEX idx_fecha_obtencion (fecha_obtencion)
) ENGINE=InnoDB COMMENT='Logros obtenidos por usuarios';

-- ============================================================================
-- TABLA: puntos_usuarios
-- Descripción: Sistema de puntos para gamificación
-- ============================================================================
CREATE TABLE puntos_usuarios (
    id_punto INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    
    puntos INT NOT NULL,
    concepto VARCHAR(200) NOT NULL,
    tipo_transaccion ENUM('ganancia', 'canje', 'ajuste') NOT NULL DEFAULT 'ganancia',
    
    -- Referencias opcionales
    id_referencia INT, -- ID de la entidad relacionada (curso, logro, etc.)
    tipo_referencia VARCHAR(50), -- Tipo de entidad (curso, logro, diagnostico, etc.)
    
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    INDEX idx_usuario_fecha (id_usuario, fecha_registro)
) ENGINE=InnoDB COMMENT='Historial de puntos de usuarios';

-- ============================================================================
-- TABLA: diagnosticos_empresariales
-- Descripción: Tipos de diagnósticos disponibles
-- ============================================================================
CREATE TABLE diagnosticos_empresariales (
    id_diagnostico INT AUTO_INCREMENT PRIMARY KEY,
    
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    objetivo TEXT,
    
    -- Áreas que evalúa (finanzas, marketing, operaciones, etc.)
    areas_evaluacion TEXT,
    tiempo_estimado_minutos INT,
    
    icono VARCHAR(255),
    activo BOOLEAN DEFAULT TRUE,
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_activo (activo)
) ENGINE=InnoDB COMMENT='Tipos de diagnósticos empresariales';

-- ============================================================================
-- TABLA: preguntas_diagnostico
-- Descripción: Preguntas de cada diagnóstico
-- ============================================================================
CREATE TABLE preguntas_diagnostico (
    id_pregunta INT AUTO_INCREMENT PRIMARY KEY,
    id_diagnostico INT NOT NULL,
    
    pregunta TEXT NOT NULL,
    tipo_pregunta ENUM('multiple_choice', 'escala', 'texto_corto', 'texto_largo', 'si_no') NOT NULL,
    opciones_respuesta JSON, -- Para almacenar opciones de respuesta
    
    -- Área a la que pertenece la pregunta
    area VARCHAR(100),
    peso_ponderacion DECIMAL(4,2) DEFAULT 1.00,
    orden INT NOT NULL,
    
    obligatoria BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (id_diagnostico) REFERENCES diagnosticos_empresariales(id_diagnostico) ON DELETE CASCADE,
    INDEX idx_diagnostico_orden (id_diagnostico, orden)
) ENGINE=InnoDB COMMENT='Preguntas de diagnósticos empresariales';

-- ============================================================================
-- TABLA: diagnosticos_realizados
-- Descripción: Diagnósticos completados por usuarios
-- ============================================================================
CREATE TABLE diagnosticos_realizados (
    id_diagnostico_realizado INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_diagnostico INT NOT NULL,
    
    fecha_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_completado DATETIME,
    
    estado ENUM('en_progreso', 'completado', 'abandonado') NOT NULL DEFAULT 'en_progreso',
    
    -- Resultados generales
    puntuacion_total DECIMAL(5,2),
    puntuacion_por_area JSON, -- Puntuación por cada área evaluada
    nivel_madurez ENUM('inicial', 'en_desarrollo', 'establecido', 'avanzado', 'optimizado'),
    
    -- Recomendaciones generadas
    recomendaciones TEXT,
    cursos_recomendados JSON, -- IDs de cursos sugeridos
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_diagnostico) REFERENCES diagnosticos_empresariales(id_diagnostico) ON DELETE CASCADE,
    INDEX idx_usuario_fecha (id_usuario, fecha_completado),
    INDEX idx_estado (estado)
) ENGINE=InnoDB COMMENT='Diagnósticos realizados por usuarios';

-- ============================================================================
-- TABLA: respuestas_diagnostico
-- Descripción: Respuestas a preguntas de diagnóstico
-- ============================================================================
CREATE TABLE respuestas_diagnostico (
    id_respuesta INT AUTO_INCREMENT PRIMARY KEY,
    id_diagnostico_realizado INT NOT NULL,
    id_pregunta INT NOT NULL,
    
    respuesta TEXT NOT NULL,
    puntuacion DECIMAL(5,2), -- Puntuación obtenida en esta pregunta
    
    fecha_respuesta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_diagnostico_realizado) REFERENCES diagnosticos_realizados(id_diagnostico_realizado) ON DELETE CASCADE,
    FOREIGN KEY (id_pregunta) REFERENCES preguntas_diagnostico(id_pregunta) ON DELETE CASCADE,
    UNIQUE KEY uk_diagnostico_pregunta (id_diagnostico_realizado, id_pregunta)
) ENGINE=InnoDB COMMENT='Respuestas de diagnósticos';

-- ============================================================================
-- TABLA: categorias_productos
-- Descripción: Categorías para la vitrina de productos
-- ============================================================================
CREATE TABLE categorias_productos (
    id_categoria_producto INT AUTO_INCREMENT PRIMARY KEY,
    
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    icono VARCHAR(100),
    orden INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_activo (activo)
) ENGINE=InnoDB COMMENT='Categorías de productos en vitrina';

-- ============================================================================
-- TABLA: productos_vitrina
-- Descripción: Productos/servicios exhibidos por los usuarios
-- ============================================================================
CREATE TABLE productos_vitrina (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_perfil INT, -- Perfil empresarial asociado
    id_categoria_producto INT,
    
    -- Información del producto
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    descripcion_corta VARCHAR(500),
    
    -- Precio
    precio DECIMAL(10,2),
    moneda VARCHAR(3) DEFAULT 'MXN',
    
    -- Multimedia
    imagenes JSON, -- Array de URLs de imágenes
    video_url VARCHAR(500),
    
    -- Etiquetas y características
    etiquetas JSON, -- Tags del producto
    caracteristicas TEXT,
    
    -- Control de inventario (opcional)
    tiene_inventario BOOLEAN DEFAULT FALSE,
    stock INT,
    
    -- Contacto
    url_contacto VARCHAR(500),
    telefono_contacto VARCHAR(20),
    email_contacto VARCHAR(150),
    
    -- Visibilidad
    estado ENUM('borrador', 'publicado', 'pausado', 'agotado') NOT NULL DEFAULT 'borrador',
    destacado BOOLEAN DEFAULT FALSE,
    
    -- Estadísticas
    vistas INT DEFAULT 0,
    contactos_recibidos INT DEFAULT 0,
    
    -- Auditoría
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_publicacion DATETIME,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_perfil) REFERENCES perfiles_empresariales(id_perfil) ON DELETE SET NULL,
    FOREIGN KEY (id_categoria_producto) REFERENCES categorias_productos(id_categoria_producto) ON DELETE SET NULL,
    INDEX idx_estado (estado),
    INDEX idx_categoria (id_categoria_producto),
    INDEX idx_destacado (destacado)
) ENGINE=InnoDB COMMENT='Productos y servicios en vitrina';

-- ============================================================================
-- TABLA: mentorías
-- Descripción: Sesiones de mentoría entre usuarios
-- ============================================================================
CREATE TABLE mentorias (
    id_mentoria INT AUTO_INCREMENT PRIMARY KEY,
    id_mentor INT NOT NULL, -- Usuario mentor
    id_aprendiz INT NOT NULL, -- Usuario que recibe mentoría
    
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    area_enfoque VARCHAR(100), -- Área de la mentoría
    
    -- Programación
    fecha_programada DATETIME,
    duracion_minutos INT,
    modalidad ENUM('presencial', 'virtual', 'hibrida') DEFAULT 'virtual',
    url_reunion VARCHAR(500),
    
    -- Estado
    estado ENUM('solicitada', 'confirmada', 'completada', 'cancelada') NOT NULL DEFAULT 'solicitada',
    
    -- Feedback
    calificacion_mentor INT, -- 1-5 estrellas
    calificacion_aprendiz INT,
    comentarios_mentor TEXT,
    comentarios_aprendiz TEXT,
    
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_mentor) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_aprendiz) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    INDEX idx_mentor (id_mentor),
    INDEX idx_aprendiz (id_aprendiz),
    INDEX idx_estado (estado)
) ENGINE=InnoDB COMMENT='Sesiones de mentoría';

-- ============================================================================
-- TABLA: notificaciones
-- Descripción: Sistema de notificaciones para usuarios
-- ============================================================================
CREATE TABLE notificaciones (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    
    tipo ENUM('logro', 'curso', 'mentoria', 'producto', 'sistema', 'diagnostico') NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT NOT NULL,
    
    -- Referencia opcional a otra entidad
    id_referencia INT,
    tipo_referencia VARCHAR(50),
    url_accion VARCHAR(500),
    
    leida BOOLEAN DEFAULT FALSE,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_lectura DATETIME,
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    INDEX idx_usuario_leida (id_usuario, leida),
    INDEX idx_fecha_creacion (fecha_creacion)
) ENGINE=InnoDB COMMENT='Notificaciones de usuarios';

-- ============================================================================
-- TABLA: recursos_aprendizaje
-- Descripción: Biblioteca de recursos adicionales
-- ============================================================================
CREATE TABLE recursos_aprendizaje (
    id_recurso INT AUTO_INCREMENT PRIMARY KEY,
    
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    tipo_recurso ENUM('articulo', 'ebook', 'plantilla', 'herramienta', 'video', 'infografia', 'podcast') NOT NULL,
    
    url_recurso VARCHAR(500),
    archivo_recurso VARCHAR(255),
    imagen_portada VARCHAR(255),
    
    -- Categorización
    categorias JSON, -- Array de categorías
    etiquetas JSON,
    
    -- Acceso
    es_gratuito BOOLEAN DEFAULT TRUE,
    nivel ENUM('basico', 'intermedio', 'avanzado') DEFAULT 'basico',
    
    -- Estadísticas
    descargas INT DEFAULT 0,
    vistas INT DEFAULT 0,
    calificacion_promedio DECIMAL(3,2) DEFAULT 0.00,
    
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_tipo_recurso (tipo_recurso),
    INDEX idx_activo (activo)
) ENGINE=InnoDB COMMENT='Recursos de aprendizaje adicionales';

-- ============================================================================
-- TABLA: configuracion_sistema
-- Descripción: Configuraciones generales del sistema
-- ============================================================================
CREATE TABLE configuracion_sistema (
    id_config INT AUTO_INCREMENT PRIMARY KEY,
    
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    tipo_dato ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    descripcion TEXT,
    categoria VARCHAR(50),
    
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='Configuración del sistema';

-- ============================================================================
-- VISTAS ÚTILES
-- ============================================================================

-- Vista: Resumen de progreso de usuarios
CREATE VIEW vista_progreso_usuarios AS
SELECT 
    u.id_usuario,
    u.nombre,
    u.apellido,
    u.email,
    COUNT(DISTINCT ic.id_curso) AS cursos_inscritos,
    COUNT(DISTINCT CASE WHEN ic.estado = 'completado' THEN ic.id_curso END) AS cursos_completados,
    SUM(COALESCE(pu.puntos, 0)) AS puntos_totales,
    COUNT(DISTINCT lu.id_logro) AS logros_obtenidos,
    u.fecha_registro
FROM usuarios u
LEFT JOIN inscripciones ic ON u.id_usuario = ic.id_usuario
LEFT JOIN puntos_usuarios pu ON u.id_usuario = pu.id_usuario AND pu.tipo_transaccion = 'ganancia'
LEFT JOIN logros_usuarios lu ON u.id_usuario = lu.id_usuario
GROUP BY u.id_usuario;

-- Vista: Cursos más populares
CREATE VIEW vista_cursos_populares AS
SELECT 
    c.id_curso,
    c.titulo,
    c.nivel,
    cat.nombre AS categoria,
    c.total_inscritos,
    c.calificacion_promedio,
    COUNT(DISTINCT ic.id_usuario) AS inscritos_activos,
    COUNT(DISTINCT CASE WHEN ic.estado = 'completado' THEN ic.id_usuario END) AS completados
FROM cursos c
LEFT JOIN categorias_cursos cat ON c.id_categoria = cat.id_categoria
LEFT JOIN inscripciones ic ON c.id_curso = ic.id_curso
WHERE c.estado = 'publicado'
GROUP BY c.id_curso
ORDER BY c.total_inscritos DESC;

-- Vista: Productos destacados en vitrina
CREATE VIEW vista_productos_destacados AS
SELECT 
    p.id_producto,
    p.nombre,
    p.descripcion_corta,
    p.precio,
    p.moneda,
    u.nombre AS vendedor_nombre,
    u.apellido AS vendedor_apellido,
    pe.nombre_empresa,
    cp.nombre AS categoria,
    p.vistas,
    p.estado,
    p.fecha_publicacion
FROM productos_vitrina p
INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
LEFT JOIN perfiles_empresariales pe ON p.id_perfil = pe.id_perfil
LEFT JOIN categorias_productos cp ON p.id_categoria_producto = cp.id_categoria_producto
WHERE p.estado = 'publicado' AND p.destacado = TRUE
ORDER BY p.fecha_publicacion DESC;

-- ============================================================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ============================================================================

-- Índices compuestos para consultas frecuentes
CREATE INDEX idx_curso_estado_categoria ON cursos(estado, id_categoria);
CREATE INDEX idx_inscripcion_usuario_estado ON inscripciones(id_usuario, estado);
CREATE INDEX idx_producto_estado_categoria ON productos_vitrina(estado, id_categoria_producto);