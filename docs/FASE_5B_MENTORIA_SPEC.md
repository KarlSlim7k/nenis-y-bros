# FASE 5B: SISTEMA DE MENTORÍA Y ASISTENTE VIRTUAL
## MentorIA - Chat con Instructores + IA

---

## 📋 RESUMEN EJECUTIVO

**Duración estimada:** 2-3 semanas  
**Prioridad:** MEDIA  
**Dependencias:** Fase 1 (Auth), Fase 2A (Cursos), Fase 4 (Gamificación)

### Objetivo
Implementar un sistema híbrido de comunicación que permita a los alumnos contactar directamente con sus instructores en tiempo real, con respaldo de un asistente virtual con IA (MentorIA) disponible 24/7 cuando los instructores no estén disponibles.

### Alcance
- Sistema de chat en tiempo real (instructor ↔ alumno)
- Asistente virtual con IA (MentorIA) como fallback
- Gestión de disponibilidad de instructores
- Historial de conversaciones por curso
- Notificaciones en tiempo real
- Integración con gamificación

---

## 🎯 CASOS DE USO

### Caso 1: Alumno Contacta a Instructor Disponible
1. Alumno en curso ve botón "Contactar Instructor"
2. Verifica estado: ✅ En línea
3. Abre chat y envía mensaje
4. Instructor recibe notificación
5. Conversación en tiempo real
6. Historial guardado en la plataforma

### Caso 2: Instructor No Disponible - MentorIA Interviene
1. Alumno intenta contactar instructor
2. Estado: ⚫ Desconectado
3. Sistema muestra: "Instructor no disponible. ¿Consultar con MentorIA?"
4. Alumno acepta
5. MentorIA responde dudas automáticamente
6. Opción de dejar mensaje para instructor

### Caso 3: Timeout Automático
1. Alumno envía mensaje a instructor
2. Instructor no responde en 5 minutos
3. Sistema muestra notificación: "¿Necesitas ayuda inmediata? Consulta con MentorIA"
4. Alumno puede cambiar a MentorIA sin perder contexto

### Caso 4: Instructor Establece Horarios
1. Instructor accede a "Mi Disponibilidad"
2. Configura: Lunes-Viernes 9:00-17:00
3. Sistema marca automáticamente estado según horario
4. Fuera de horario, alumnos ven "Disponible desde [hora]"

---

## 🏗️ ARQUITECTURA DEL SISTEMA

### Componentes Principales

#### 1. Sistema de Chat Real-Time
- **Tecnología:** WebSockets (Socket.io o Pusher)
- **Alternativa:** Long-polling con AJAX (más simple, sin dependencias)
- **Persistencia:** Base de datos MySQL

#### 2. MentorIA (Asistente Virtual)
- **Backend:** API de IA (OpenAI GPT-4, Claude, o Gemini)
- **Contexto:** Curso actual, historial de preguntas, perfil del alumno
- **Fallback:** Respuestas pre-programadas si API falla
- **Aprendizaje:** Base de conocimientos actualizable

#### 3. Sistema de Presencia
- **Estados:** 
  - 🟢 En línea (activo en plataforma)
  - 🟡 Ausente (sin actividad >10 min)
  - 🔴 Ocupado (marcado manualmente)
  - ⚫ Desconectado
- **Actualización:** Heartbeat cada 30 segundos

#### 4. Sistema de Notificaciones
- **En plataforma:** Badge con contador de mensajes no leídos
- **Opcional:** Email si mensaje sin leer >1 hora
- **Push notifications:** Opcional (PWA)

---

## 📊 BASE DE DATOS

### Tabla: `conversaciones`
```sql
CREATE TABLE conversaciones (
    id_conversacion INT PRIMARY KEY AUTO_INCREMENT,
    id_curso INT NOT NULL,
    id_alumno INT NOT NULL,
    id_instructor INT NOT NULL,
    tipo_conversacion ENUM('instructor', 'mentoria') DEFAULT 'instructor',
    estado ENUM('activa', 'archivada') DEFAULT 'activa',
    ultimo_mensaje_fecha DATETIME,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso),
    FOREIGN KEY (id_alumno) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_instructor) REFERENCES usuarios(id_usuario),
    
    INDEX idx_alumno (id_alumno),
    INDEX idx_instructor (id_instructor),
    INDEX idx_curso (id_curso),
    INDEX idx_estado (estado)
);
```

### Tabla: `mensajes`
```sql
CREATE TABLE mensajes (
    id_mensaje INT PRIMARY KEY AUTO_INCREMENT,
    id_conversacion INT NOT NULL,
    id_remitente INT,  -- NULL si es MentorIA
    remitente_tipo ENUM('alumno', 'instructor', 'mentoria') NOT NULL,
    contenido TEXT NOT NULL,
    tipo_mensaje ENUM('texto', 'archivo', 'sistema') DEFAULT 'texto',
    leido BOOLEAN DEFAULT FALSE,
    fecha_leido DATETIME,
    metadata JSON,  -- Para adjuntos, referencias, etc.
    fecha_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_conversacion) REFERENCES conversaciones(id_conversacion),
    FOREIGN KEY (id_remitente) REFERENCES usuarios(id_usuario),
    
    INDEX idx_conversacion (id_conversacion),
    INDEX idx_fecha (fecha_envio),
    INDEX idx_leido (leido)
);
```

### Tabla: `disponibilidad_instructores`
```sql
CREATE TABLE disponibilidad_instructores (
    id_disponibilidad INT PRIMARY KEY AUTO_INCREMENT,
    id_instructor INT NOT NULL,
    dia_semana TINYINT NOT NULL,  -- 0=Domingo, 6=Sábado
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (id_instructor) REFERENCES usuarios(id_usuario),
    UNIQUE KEY unique_instructor_dia (id_instructor, dia_semana),
    INDEX idx_instructor (id_instructor)
);
```

### Tabla: `estado_presencia`
```sql
CREATE TABLE estado_presencia (
    id_usuario INT PRIMARY KEY,
    estado ENUM('en_linea', 'ausente', 'ocupado', 'desconectado') DEFAULT 'desconectado',
    ultima_actividad DATETIME,
    mensaje_estado VARCHAR(100),  -- Ej: "Volveré en 30 min"
    
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);
```

### Tabla: `mentoria_contexto`
```sql
CREATE TABLE mentoria_contexto (
    id_contexto INT PRIMARY KEY AUTO_INCREMENT,
    id_conversacion INT NOT NULL,
    prompt_sistema TEXT,  -- Contexto enviado a la IA
    tokens_usados INT DEFAULT 0,
    costo_estimado DECIMAL(10,4) DEFAULT 0,
    modelo_ia VARCHAR(50),  -- gpt-4, claude-3, etc.
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_conversacion) REFERENCES conversaciones(id_conversacion),
    INDEX idx_conversacion (id_conversacion)
);
```

---

## 🔌 API ENDPOINTS

### Chat con Instructores

#### `POST /chat/conversaciones`
Crear nueva conversación (alumno inicia chat)
```json
Request:
{
    "id_curso": 5,
    "id_instructor": 12
}

Response:
{
    "success": true,
    "data": {
        "id_conversacion": 45,
        "estado_instructor": "en_linea",
        "puede_contactar": true
    }
}
```

#### `GET /chat/conversaciones`
Listar conversaciones del usuario (alumno ve sus chats, instructor ve solicitudes)
```json
Response:
{
    "success": true,
    "data": [
        {
            "id_conversacion": 45,
            "curso": "Marketing Digital",
            "instructor_nombre": "Juan Pérez",
            "estado_instructor": "en_linea",
            "mensajes_no_leidos": 3,
            "ultimo_mensaje": "¿Cuándo es el deadline?",
            "ultimo_mensaje_fecha": "2025-11-18 14:30:00"
        }
    ]
}
```

#### `GET /chat/conversaciones/{id}`
Obtener mensajes de una conversación
```json
Response:
{
    "success": true,
    "data": {
        "conversacion": {
            "id_conversacion": 45,
            "curso": "Marketing Digital",
            "participantes": {...}
        },
        "mensajes": [
            {
                "id_mensaje": 120,
                "remitente": "alumno",
                "remitente_nombre": "María García",
                "contenido": "¿Cuál es la diferencia entre SEO y SEM?",
                "leido": true,
                "fecha_envio": "2025-11-18 14:28:00"
            }
        ]
    }
}
```

#### `POST /chat/mensajes`
Enviar mensaje
```json
Request:
{
    "id_conversacion": 45,
    "contenido": "Gracias por tu ayuda",
    "tipo_mensaje": "texto"
}

Response:
{
    "success": true,
    "data": {
        "id_mensaje": 121,
        "fecha_envio": "2025-11-18 14:35:00"
    }
}
```

#### `PUT /chat/mensajes/{id}/leer`
Marcar mensaje como leído
```json
Response:
{
    "success": true,
    "message": "Mensaje marcado como leído"
}
```

#### `POST /chat/conversaciones/{id}/archivar`
Archivar conversación
```json
Response:
{
    "success": true,
    "message": "Conversación archivada"
}
```

---

### MentorIA (Asistente Virtual)

#### `POST /mentoria/iniciar`
Iniciar conversación con MentorIA
```json
Request:
{
    "id_curso": 5,
    "contexto": "Tengo dudas sobre la lección 3"
}

Response:
{
    "success": true,
    "data": {
        "id_conversacion": 46,
        "tipo_conversacion": "mentoria",
        "mensaje_bienvenida": "Hola, soy MentorIA. ¿En qué puedo ayudarte?"
    }
}
```

#### `POST /mentoria/preguntar`
Enviar pregunta a MentorIA
```json
Request:
{
    "id_conversacion": 46,
    "pregunta": "¿Qué es el funnel de ventas?"
}

Response:
{
    "success": true,
    "data": {
        "respuesta": "El funnel de ventas es un modelo que representa...",
        "confianza": 0.92,
        "fuentes": [
            {"tipo": "leccion", "titulo": "Lección 4: Estrategias de Venta"}
        ],
        "sugerencias": [
            "¿Quieres ejemplos prácticos?",
            "¿Te gustaría ver un video sobre este tema?"
        ]
    }
}
```

#### `POST /mentoria/feedback`
Calificar respuesta de MentorIA
```json
Request:
{
    "id_mensaje": 125,
    "util": true,
    "comentario": "Muy clara la explicación"
}

Response:
{
    "success": true,
    "message": "Gracias por tu feedback"
}
```

---

### Disponibilidad

#### `GET /chat/disponibilidad/{id_instructor}`
Ver disponibilidad de instructor
```json
Response:
{
    "success": true,
    "data": {
        "estado_actual": "en_linea",
        "horarios": [
            {"dia": 1, "hora_inicio": "09:00", "hora_fin": "17:00"},
            {"dia": 3, "hora_inicio": "10:00", "hora_fin": "16:00"}
        ],
        "proxima_disponibilidad": "2025-11-19 09:00:00"
    }
}
```

#### `POST /chat/disponibilidad` (Instructor)
Configurar disponibilidad
```json
Request:
{
    "horarios": [
        {"dia": 1, "hora_inicio": "09:00", "hora_fin": "13:00"},
        {"dia": 1, "hora_inicio": "15:00", "hora_fin": "18:00"}
    ]
}

Response:
{
    "success": true,
    "message": "Disponibilidad actualizada"
}
```

#### `PUT /chat/estado` (Instructor)
Cambiar estado manual
```json
Request:
{
    "estado": "ocupado",
    "mensaje": "En reunión, vuelvo en 1 hora"
}

Response:
{
    "success": true,
    "message": "Estado actualizado"
}
```

---

### Estadísticas

#### `GET /chat/estadisticas/instructor`
Stats para instructor
```json
Response:
{
    "success": true,
    "data": {
        "conversaciones_activas": 8,
        "mensajes_pendientes": 12,
        "tiempo_respuesta_promedio": "8 minutos",
        "satisfaccion_promedio": 4.7,
        "total_mensajes_mes": 145
    }
}
```

#### `GET /mentoria/estadisticas`
Stats de MentorIA (admin)
```json
Response:
{
    "success": true,
    "data": {
        "consultas_totales": 342,
        "consultas_mes": 89,
        "satisfaccion_promedio": 4.2,
        "temas_frecuentes": [
            {"tema": "SEO", "cantidad": 45},
            {"tema": "Redes Sociales", "cantidad": 32}
        ],
        "costo_api_mes": 12.45
    }
}
```

---

## 🎨 FRONTEND

### Páginas

#### 1. `chat.html` - Interfaz Principal de Chat
**Componentes:**
- **Sidebar izquierda:** Lista de conversaciones
  - Tabs: "Instructores" | "MentorIA"
  - Badge de mensajes no leídos
  - Estado de cada instructor (🟢🟡🔴⚫)
  - Búsqueda de conversaciones
  
- **Panel central:** Mensajes
  - Header con nombre, estado, curso
  - Área de mensajes scrollable
  - Input de texto con emoji picker
  - Botón adjuntar (opcional)
  - Indicador "escribiendo..."
  
- **Sidebar derecha (opcional):** Info del curso
  - Lecciones relacionadas
  - Recursos sugeridos
  - Atajos rápidos

**Funcionalidades:**
- Auto-scroll al último mensaje
- Notificación sonora de mensaje nuevo
- Formato de texto (negrita, cursiva, código)
- Timestamps agrupados por fecha
- Indicador de leído/no leído

#### 2. `mis-conversaciones.html` - Vista de Lista
**Para alumnos:**
- Todas las conversaciones activas
- Filtros: Por curso, por instructor, archivadas
- Iniciar nueva conversación

**Para instructores:**
- Solicitudes de chat entrantes
- Conversaciones activas
- Priorización por urgencia
- Estadísticas rápidas

#### 3. `disponibilidad-instructor.html` (Solo Instructores)
**Componentes:**
- Calendario semanal interactivo
- Bloques de horarios drag-and-drop
- Toggle de disponibilidad automática
- Estado manual con mensaje personalizado
- Estadísticas de actividad

#### 4. `mentoria-config.html` (Solo Admin)
**Configuración de MentorIA:**
- Prompt del sistema (base knowledge)
- Modelo de IA seleccionado
- Parámetros (temperatura, max_tokens)
- Base de conocimientos (FAQs pre-cargadas)
- Logs de consultas
- Costos y uso de API

---

## 🤖 INTEGRACIÓN CON IA

### Flujo de Procesamiento MentorIA

1. **Recepción de Pregunta**
   - Usuario envía mensaje
   - Sistema captura contexto (curso, lección, perfil)

2. **Construcción del Prompt**
```text
Sistema:
Eres MentorIA, asistente virtual de la plataforma Nenis y Bros.
Ayudas a alumnos con dudas sobre cursos de formación empresarial.

Contexto:
- Curso: Marketing Digital
- Lección actual: Lección 3 - SEO Básico
- Alumno: Nivel principiante
- Historial: 2 preguntas previas sobre keywords

Pregunta del alumno:
¿Qué es el funnel de ventas?

Instrucciones:
- Responde en español
- Sé conciso pero completo
- Si el tema no está en el curso, sugiere recursos
- Usa ejemplos prácticos
- Ofrece enlaces a lecciones relacionadas
```

3. **Llamada a API de IA**
   - OpenAI GPT-4 / Claude 3 / Gemini
   - Timeout: 10 segundos
   - Fallback si falla

4. **Post-Procesamiento**
   - Validar respuesta (no tóxica, relevante)
   - Agregar enlaces internos a lecciones
   - Formatear con Markdown
   - Guardar en BD

5. **Respuesta al Usuario**
   - Mostrar respuesta
   - Botones de feedback (👍👎)
   - Sugerencias de follow-up

### Providers de IA Soportados

| Provider | Modelo | Costo/1K tokens | Latencia | Ventaja |
|----------|--------|-----------------|----------|---------|
| OpenAI | GPT-4o | $0.005 | ~2s | Mejor calidad |
| Anthropic | Claude 3.5 Sonnet | $0.003 | ~1.5s | Más rápido |
| Google | Gemini Pro | $0.00025 | ~3s | Más económico |
| Groq | Llama 3 | Gratis (límite) | ~0.5s | Ultra rápido |

**Recomendación:** Claude 3.5 Sonnet (balance calidad/precio/velocidad)

### Base de Conocimientos

**Estructura:**
```json
{
    "cursos": [
        {
            "id_curso": 5,
            "nombre": "Marketing Digital",
            "temas": [
                {
                    "keyword": "SEO",
                    "descripcion": "...",
                    "lecciones": [3, 4, 5],
                    "recursos": ["guia-seo.pdf"]
                }
            ]
        }
    ],
    "faqs": [
        {
            "pregunta": "¿Cómo publico un producto?",
            "respuesta": "...",
            "categoria": "marketplace"
        }
    ]
}
```

**Actualización:** Admin puede agregar/editar FAQs desde panel

---

## 🎮 INTEGRACIÓN CON GAMIFICACIÓN

### Puntos Otorgados

| Acción | Puntos | Tipo |
|--------|--------|------|
| Alumno envía primera pregunta a instructor | +10 | interaccion |
| Instructor responde mensaje | +15 | enseñanza |
| Alumno califica positivamente respuesta | +5 | feedback |
| Alumno usa MentorIA (primera vez) | +5 | aprendizaje |
| Instructor mantiene 100% de respuestas <10 min | +50 | logro_semanal |

### Logros Desbloqueables

**Para Alumnos:**
- 🏆 **"Primera Consulta"** - Envía tu primer mensaje a un instructor
- 🏆 **"Curioso Incansable"** - Realiza 10 consultas a MentorIA
- 🏆 **"Aprendiz Activo"** - Mantén 5 conversaciones simultáneas

**Para Instructores:**
- 🏆 **"Mentor Dedicado"** - Responde 50 mensajes
- 🏆 **"Rayo de Luz"** - Tiempo respuesta promedio <5 minutos (semana)
- 🏆 **"Gurú del Chat"** - Satisfacción 4.8+ con 20+ valoraciones

---

## 🔔 SISTEMA DE NOTIFICACIONES

### Tipos de Notificaciones

#### En Plataforma
- Badge numérico en ícono de chat (header)
- Lista de notificaciones desplegable
- Sonido al recibir mensaje (configurable)
- Banner temporal (toast) con preview

#### Email (Opcional)
- Mensaje no leído >1 hora
- Resumen diario (instructor: mensajes pendientes)
- Nueva conversación iniciada

#### Push Notifications (PWA)
- Mensaje nuevo cuando app en background
- Requiere consentimiento del usuario

### Configuración Usuario
```json
{
    "notificaciones": {
        "en_plataforma": true,
        "sonido": true,
        "email": false,
        "push": true,
        "no_molestar_desde": "22:00",
        "no_molestar_hasta": "07:00"
    }
}
```

---

## 🔒 SEGURIDAD Y PRIVACIDAD

### Restricciones

1. **Inicio de Conversación:**
   - Solo alumnos inscritos pueden contactar a instructor del curso
   - Instructores pueden iniciar chat con sus alumnos
   - MentorIA accesible para todos los usuarios autenticados

2. **Visibilidad de Mensajes:**
   - Conversaciones privadas (1:1)
   - No hay chats grupales (por ahora)
   - Admin no puede leer conversaciones (excepto reportes)

3. **Rate Limiting:**
   - Alumno: Max 5 mensajes/minuto
   - MentorIA: Max 10 preguntas/hora/usuario (evitar spam)
   - Instructor: Sin límite

4. **Moderación:**
   - Filtro de palabras ofensivas
   - Botón "Reportar mensaje"
   - Bloqueo automático tras 3 reportes

5. **Datos Sensibles:**
   - No compartir datos de contacto personales
   - Advertencia si se detecta email/teléfono en mensaje
   - Encriptación end-to-end (opcional, futuro)

---

## 📱 RESPONSIVE DESIGN

### Mobile-First Approach

**Vista Móvil (<768px):**
- Solo panel de mensajes visible
- Botón "Atrás" para volver a lista de conversaciones
- Input de texto sticky en bottom
- Acciones (adjuntar, emoji) en menú collapse

**Tablet (768-1024px):**
- Layout 2 columnas: Lista | Mensajes
- Sidebar derecha oculta

**Desktop (>1024px):**
- Layout 3 columnas completo
- Sidebar derecha con info adicional

---

## ⚡ OPTIMIZACIONES DE RENDIMIENTO

### Backend
- **Paginación de Mensajes:** Cargar últimos 50, infinite scroll para anteriores
- **Caché de Presencia:** Redis para estados en tiempo real
- **Queue de Notificaciones:** Background jobs para emails
- **Índices de BD:** Optimizar queries de mensajes recientes

### Frontend
- **Lazy Loading:** Cargar conversaciones on-demand
- **Virtual Scrolling:** Lista de mensajes para conversaciones largas
- **Debounce:** Indicador "escribiendo..." con 300ms delay
- **Service Worker:** Cache de conversaciones recientes (offline-first)

### IA
- **Streaming de Respuesta:** Mostrar texto mientras se genera (SSE)
- **Cache de Respuestas:** Preguntas frecuentes en Redis (1 hora)
- **Fallback Local:** Respuestas pre-programadas si API falla

---

## 🧪 TESTING

### Casos de Prueba Críticos

#### Funcionales
- [ ] Alumno puede iniciar chat con instructor de su curso
- [ ] Alumno NO puede contactar instructor de curso no inscrito
- [ ] Mensajes se entregan en orden correcto
- [ ] Estado de presencia actualiza cada 30s
- [ ] MentorIA responde en <5 segundos
- [ ] Notificaciones llegan solo al destinatario correcto
- [ ] Mensajes leídos actualizan badge correctamente

#### Integración
- [ ] WebSocket reconecta automáticamente tras desconexión
- [ ] API de IA tiene fallback funcional
- [ ] Puntos de gamificación se otorgan correctamente
- [ ] Emails de notificación se envían con delay correcto

#### Seguridad
- [ ] Usuario no autenticado no puede acceder a mensajes
- [ ] Alumno no puede leer conversaciones de otros
- [ ] Rate limiting bloquea spam
- [ ] Filtro de palabras ofensivas funciona

#### Performance
- [ ] Carga de conversación <500ms
- [ ] Envío de mensaje <200ms
- [ ] Streaming de respuesta IA <1s para primeras palabras
- [ ] Lista de 100 conversaciones carga <1s

---

## 📦 ENTREGABLES

### Backend
- [x] 5 tablas de BD creadas
- [ ] 15 endpoints REST implementados
- [ ] Integración con API de IA (OpenAI/Claude)
- [ ] Sistema de WebSockets o long-polling
- [ ] Sistema de presencia con heartbeat
- [ ] Queue de notificaciones
- [ ] Middleware de rate limiting
- [ ] Tests unitarios (70%+ cobertura)

### Frontend
- [ ] `chat.html` - Interfaz principal (~600 líneas)
- [ ] `mis-conversaciones.html` - Lista de chats (~400 líneas)
- [ ] `disponibilidad-instructor.html` - Config disponibilidad (~350 líneas)
- [ ] `mentoria-config.html` - Admin MentorIA (~300 líneas)
- [ ] Componente reutilizable de chat widget
- [ ] Responsive design (mobile + desktop)
- [ ] Tests E2E (Playwright/Cypress)

### Documentación
- [ ] `FASE_5B_MENTORIA_COMPLETADA.md`
- [ ] Guía de configuración de MentorIA
- [ ] Manual de usuario (alumnos)
- [ ] Manual de instructor (gestión de disponibilidad)
- [ ] API documentation (Postman collection)

---

## 🚀 PLAN DE IMPLEMENTACIÓN

### Sprint 1: Fundamentos (Semana 1)
**Días 1-2:**
- [ ] Diseño de base de datos
- [ ] Creación de tablas y relaciones
- [ ] Endpoints básicos de conversaciones

**Días 3-4:**
- [ ] Sistema de mensajes (CRUD)
- [ ] Paginación de mensajes
- [ ] Frontend: Estructura base de `chat.html`

**Día 5:**
- [ ] Sistema de presencia (heartbeat)
- [ ] Estados en tiempo real (polling)
- [ ] Testing básico

### Sprint 2: MentorIA (Semana 2)
**Días 1-2:**
- [ ] Integración con API de IA
- [ ] Construcción de prompts con contexto
- [ ] Base de conocimientos inicial

**Días 3-4:**
- [ ] Frontend: Interfaz de MentorIA
- [ ] Streaming de respuestas
- [ ] Sistema de feedback

**Día 5:**
- [ ] Fallback y manejo de errores
- [ ] Optimizaciones de IA
- [ ] Testing de integración

### Sprint 3: Features Avanzados (Semana 3)
**Días 1-2:**
- [ ] Sistema de disponibilidad de instructores
- [ ] Notificaciones (email + push)
- [ ] Integración con gamificación

**Días 3-4:**
- [ ] Estadísticas y reportes
- [ ] Panel de admin para MentorIA
- [ ] Optimizaciones de rendimiento

**Día 5:**
- [ ] Testing completo
- [ ] Documentación
- [ ] Deploy y validación

---

## 🎯 MÉTRICAS DE ÉXITO

### Indicadores Clave (KPIs)

**Uso del Sistema:**
- Conversaciones activas por día
- Mensajes enviados por usuario (promedio)
- Tasa de adopción de instructores (% usando chat)
- Ratio chat instructor vs MentorIA

**Calidad del Servicio:**
- Tiempo de respuesta promedio (instructor): <10 minutos
- Satisfacción con MentorIA: >4.0/5.0
- Tasa de resolución de dudas: >80%
- % de consultas escaladas (MentorIA → instructor)

**Engagement:**
- Usuarios activos diarios en chat
- Conversaciones por alumno/mes
- Retorno a la plataforma (triggered by notifications)

**Costos:**
- Costo por consulta a MentorIA: <$0.05
- Ahorro de tiempo de instructores (estimado)

---

## 🔮 MEJORAS FUTURAS (Post-MVP)

### Fase 5B.1 (Corto plazo)
- [ ] Compartir archivos (PDF, imágenes)
- [ ] Mensajes de voz
- [ ] Reacciones rápidas (emoji reactions)
- [ ] Búsqueda en conversaciones
- [ ] Encriptación end-to-end

### Fase 5B.2 (Mediano plazo)
- [ ] Chats grupales (curso completo)
- [ ] Videollamadas integradas (WebRTC)
- [ ] Transcripción automática de videollamadas
- [ ] MentorIA con voz (speech-to-text)
- [ ] Integración con calendario (agendar sesiones)

### Fase 5B.3 (Largo plazo)
- [ ] MentorIA multimodal (imágenes, diagramas)
- [ ] Traducción automática (multi-idioma)
- [ ] Análisis de sentimiento (detectar frustración)
- [ ] Sugerencias proactivas ("Parece que tienes dudas sobre X")
- [ ] Integración con herramientas externas (Slack, Discord)

---

## 📞 CONTACTO Y SOPORTE

Para preguntas sobre la implementación de esta fase:
- Revisar documentación técnica en `/docs`
- Consultar ejemplos de código en `/backend/controllers/MentoriaController.php`
- Revisar tests en `/tests/Feature/MentoriaTest.php`

---

**Última actualización:** 18 de Noviembre, 2025  
**Versión del documento:** 1.0  
**Estado:** ✅ Listo para desarrollo
