### Personalización de Preguntas
Puedes agregar/modificar preguntas editando la tabla `ps_configuracion_preguntas`:

```sql
INSERT INTO ps_configuracion_preguntas 
(categoria_id, categoria_nombre, pregunta_numero, pregunta_texto, orden_pregunta) 
VALUES (1, 'Política de Seguridad', '1.3', '¿Tu nueva pregunta aquí?', 3);
```

### Limpieza Automática
Habilita el evento de limpieza automática:

```sql
SET GLOBAL event_scheduler = ON;
CREATE EVENT IF NOT EXISTS ps_limpiar_borradores_evento
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURDATE() + INTERVAL 1 DAY, '02:00:00')
DO CALL# 📱 Protexa Seguro - App de Recorridos de Seguridad

Una aplicación web progresiva (PWA) moderna para gestionar recorridos de seguridad en empresas, integrada con WordPress.

## 🚀 Características Principales

- ✅ **PWA completa** - Funciona como app nativa en móviles
- 📱 **Mobile-first** - Diseño optimizado para dispositivos móviles  
- 📷 **Captura de fotos** - Directamente desde la cámara del dispositivo
- 🔄 **Sincronización offline** - Funciona sin conexión y sincroniza después
- 🔐 **Integración WordPress** - Login unificado con tu sitio existente
- ⚡ **Guardado automático** - No pierdas tu progreso
- 📊 **Reportes en tiempo real** - Estadísticas y seguimiento
- 🚨 **Modo emergencia** - Para situaciones críticas con notificaciones automáticas

## 📋 Tipos de Recorrido

### Recorrido Programado
- Inspección completa de rutina
- 7 categorías de evaluación
- 26 preguntas específicas
- Ideal para auditorías regulares

### Recorrido de Emergencia
- Registro rápido de situaciones críticas
- Notificaciones automáticas
- Prioridad alta en el sistema
- Para condiciones inseguras inmediatas

## 🗂️ Categorías de Evaluación

1. **Política de Seguridad** - Visibilidad y actualización de políticas
2. **Rutas de Evacuación** - Señalización y accesos de emergencia
3. **Áreas de Tránsito** - Condiciones de pasillos y accesos
4. **Contraincendio** - Equipos y sistemas de protección
5. **Seguridad Eléctrica** - Instalaciones y señalización eléctrica
6. **Orden y Limpieza** - Estado general de las instalaciones
7. **Equipo de Protección Personal** - Uso correcto de EPP

## 🛠️ Instalación

### Prerrequisitos
- Servidor web con PHP 8.0+
- Base de datos MySQL 5.7+ o MariaDB 10.3+
- WordPress instalado y funcionando
- Extensión GD de PHP (opcional, para thumbnails)
- HTTPS habilitado (requerido para PWA)

### Paso 1: Subir Archivos
1. Crea la carpeta `app-protexa-seguro` en la raíz de tu servidor
2. Sube todos los archivos manteniendo la estructura:

```
app-protexa-seguro/
├── index.php
├── dashboard.php
├── recorrido.php
├── success.php
├── config.php
├── manifest.json
├── sw.js
├── database.sql
├── api/
│   ├── save-progress.php
│   ├── upload-photo.php
│   └── ping.php
├── assets/
│   ├── css/app.css
│   └── js/app.js
└── uploads/ (crear carpeta con permisos 755)
```

### Paso 2: Configurar Base de Datos
1. **Importa las tablas** del archivo `database.sql` en tu base de datos existente
2. **Todas las tablas usan el prefijo `ps_`** para evitar conflictos:
   - `ps_recorridos`
   - `ps_respuestas_categorias`
   - `ps_fotos_recorrido`
   - `ps_hallazgos_criticos`
   - `ps_configuracion_preguntas`
   - `ps_configuracion_app`
   - `ps_borradores_recorrido`
   - `ps_logs_sistema`

3. No necesitas crear una base de datos nueva, solo ejecuta el SQL en tu BD actual

### Paso 3: Configurar config.php
Edita el archivo `config.php` con los datos de tu base existente:

```php
// Configuración de base de datos (usar tu base existente)
define('DB_HOST', 'localhost');
define('DB_NAME', 'tu_base_datos_actual'); // Tu BD de WordPress o existente
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_password');

// El prefijo ps_ ya está configurado automáticamente
define('TABLE_PREFIX', 'ps_');

// Configuración de la aplicación
define('BASE_URL', 'https://tudominio.com/app-protexa-seguro/');

// Ruta a WordPress (ajustar según tu instalación)
define('WP_PATH', '../wp-load.php');
```

### Paso 4: Configurar Permisos
```bash
# Permisos para la carpeta de uploads
chmod 755 app-protexa-seguro/uploads/
chown www-data:www-data app-protexa-seguro/uploads/

# Permisos para archivos PHP
chmod 644 app-protexa-seguro/*.php
chmod 644 app-protexa-seguro/api/*.php
```

### Paso 5: Configurar Iconos PWA
1. Crea los iconos en `assets/images/` con los siguientes tamaños:
   - `icon-72x72.png`
   - `icon-96x96.png` 
   - `icon-128x128.png`
   - `icon-144x144.png`
   - `icon-152x152.png`
   - `icon-192x192.png`
   - `icon-384x384.png`
   - `icon-512x512.png`

2. Opcional: Agrega tu logo como `logo.png`

### Paso 6: Configurar HTTPS y Headers
En tu `.htaccess` o configuración del servidor:

```apache
# Headers para PWA
<IfModule mod_headers.c>
    Header set Service-Worker-Allowed "/"
    
    <FilesMatch "\.(js|css|png|jpg|jpeg|gif|ico|svg)$">
        Header set Cache-Control "public, max-age=31536000"
    </FilesMatch>
    
    <FilesMatch "sw.js$">
        Header set Cache-Control "no-cache, no-store, must-revalidate"
    </FilesMatch>
</IfModule>

# Redirección HTTPS (si no está configurada)
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

## 🔧 Configuración Avanzada

### Notificaciones de Emergencia
Para habilitar notificaciones automáticas por email:

```sql
UPDATE configuracion_app 
SET config_value = 'email1@empresa.com,email2@empresa.com' 
WHERE config_key = 'emergency_notification_emails';
```

### Personalización de Preguntas
Puedes agregar/modificar preguntas editando la tabla `configuracion_preguntas`:

```sql
INSERT INTO configuracion_preguntas 
(categoria_id, categoria_nombre, pregunta_numero, pregunta_texto, orden_pregunta) 
VALUES (1, 'Política de Seguridad', '1.3', '¿Tu nueva pregunta aquí?', 3);
```

### Limpieza Automática
Habilita el evento de limpieza automática:

```sql
SET GLOBAL event_scheduler = ON;
CREATE EVENT IF NOT EXISTS limpiar_borradores_evento
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURDATE() + INTERVAL 1 DAY, '02:00:00')
DO CALL limpiar_borradores_antiguos();
```

## 📱 Uso de la Aplicación

### Para Usuarios Finales

1. **Acceso**: Ir a `https://tudominio.com/app-protexa-seguro/`
2. **Login**: Usar credenciales de WordPress
3. **Instalación PWA**: El navegador sugerirá "Agregar a pantalla de inicio"
4. **Recorridos**: Seleccionar tipo y completar formulario paso a paso
5. **Fotos**: Tocar el área de cámara para capturar imágenes
6. **Sincronización**: La app funciona offline y sincroniza cuando hay conexión

### Flujo de Trabajo

1. **Inicio de Sesión** → Dashboard
2. **Selección de Tipo** → Emergencia o Programado  
3. **Configuración** → Ubicación, división, motivo
4. **Formulario Wizard** → 7 pasos con preguntas
5. **Respuestas** → Sí/No/N/A + fotos + comentarios
6. **Resumen** → Revisar antes de enviar
7. **Completado** → Confirmación y estadísticas

## 📊 Reportes y Administración

### Consultas Útiles

```sql
-- Recorridos por usuario
SELECT user_name, COUNT(*) as total, 
       AVG(answered_questions/total_questions*100) as promedio_completado
FROM ps_recorridos 
WHERE status = 'completed'
GROUP BY user_id, user_name;

-- Hallazgos críticos pendientes  
SELECT * FROM ps_vista_hallazgos_pendientes
ORDER BY nivel_prioridad DESC, created_at ASC;

-- Estadísticas por ubicación
SELECT location, 
       COUNT(*) as total_recorridos,
       AVG(yes_count) as promedio_si,
       AVG(no_count) as promedio_no
FROM ps_recorridos 
WHERE status = 'completed'
GROUP BY location;

-- Ver todas las tablas de Protexa Seguro
SHOW TABLES LIKE 'ps_%';
```

### Mantenimiento

```bash
# Backup de la base de datos
mysqldump -u root -p protexa_seguro > backup_protexa_$(date +%Y%m%d).sql

# Limpiar archivos temporales
find app-protexa-seguro/uploads/ -name "tmp_*" -type f -delete

# Verificar espacio en disco
du -sh app-protexa-seguro/uploads/
```

## 🐛 Solución de Problemas

### Problemas Comunes

**Error: "No autorizado"**
- Verificar que WordPress esté funcionando
- Comprobar la ruta `WP_PATH` en config.php
- Verificar permisos de archivos

**Error: "No se pueden subir fotos"**
- Verificar permisos de la carpeta `uploads/`
- Comprobar límites de PHP: `upload_max_filesize`, `post_max_size`
- Verificar extensión GD de PHP

**PWA no se instala**
- Verificar que el sitio esté en HTTPS
- Comprobar que `manifest.json` sea accesible
- Verificar que `sw.js` se registre correctamente

**Problemas de sincronización offline**
- Verificar que el Service Worker esté activo
- Comprobar almacenamiento local del navegador
- Revisar logs de JavaScript en DevTools

### Logs y Debugging

```php
// Habilitar logs de PHP
ini_set('log_errors', 1);
ini_set('error_log', 'app-protexa-seguro/logs/php_errors.log');

// Debug de JavaScript
console.log('Estado de la aplicación:', window.protexaApp);
```

## 🔒 Consideraciones de Seguridad

- Cambiar contraseñas por defecto en `config.php`
- Configurar firewall para limitar acceso a archivos `.php`
- Implementar rate limiting en el servidor
- Revisar logs regularmente en `logs_sistema`
- Mantener WordPress actualizado
- Usar HTTPS en producción

## 🆘 Soporte

Para soporte técnico:
1. Revisar logs en `logs_sistema` de la base de datos
2. Verificar logs del servidor web
3. Comprobar DevTools del navegador para errores JavaScript
4. Documentar pasos para reproducir el problema

## 🚀 Nuevas Características Implementadas

### ✅ **Templates Reutilizables**
- `includes/head.php` - Header HTML reutilizable con PWA
- `includes/header.php` - Navigation con menú dropdown avanzado
- `includes/footer.php` - Footer con scripts y funcionalidades

### ✅ **Sistema de Menú Mejorado**
- Menú dropdown responsive con información del usuario
- Navegación intuitiva entre secciones
- Indicadores de borradores pendientes
- Enlaces rápidos a funcionalidades principales

### ✅ **Gestión de Borradores**
- `borradores.php` - Lista y gestión de recorridos en borrador
- `borrador-guardado.php` - Página de confirmación al guardar
- Indicadores de progreso y tiempo transcurrido
- Continuación automática desde donde se dejó

### ✅ **Panel de Administración**
- `admin/recorridos.php` - Vista completa para supervisores
- Filtros avanzados y búsqueda
- Estadísticas en tiempo real
- Gestión de hallazgos críticos
- Exportación de datos

### ✅ **Historial de Recorridos**
- `mis-recorridos.php` - Vista personal de todos los recorridos
- Filtros por estado, tipo, ubicación y fecha
- Paginación eficiente
- Estadísticas personales

### ✅ **Notificaciones y UX**
- Sistema de toast notifications
- Loading overlays
- Mensajes de éxito/error contextuales
- Guardado automático con feedback visual

### ✅ **Estilos Modernos**
- `assets/css/admin.css` - Estilos específicos de administración
- Mejoras en `app.css` con nuevos componentes
- Responsive design mejorado
- Animaciones y transiciones suaves

## 📁 Estructura Actualizada del Proyecto

```
app-protexa-seguro/
├── index.php                    # Login page
├── dashboard.php                # Dashboard principal (actualizado)
├── recorrido.php               # Formulario wizard de recorrido
├── success.php                 # Página de éxito
├── borradores.php              # Gestión de borradores (NUEVO)
├── borrador-guardado.php       # Confirmación borrador (NUEVO)
├── mis-recorridos.php          # Historial personal (NUEVO)
├── config.php                  # Configuración con prefijos
├── install.php                 # Instalador automático
├── manifest.json              # PWA manifest
├── sw.js                      # Service Worker
├── database.sql               # BD con prefijos ps_
│
├── includes/                   # Templates reutilizables (NUEVO)
│   ├── head.php               # HTML head con PWA
│   ├── header.php             # Header con menú avanzado
│   └── footer.php             # Footer con scripts
│
├── admin/                     # Panel de administración (NUEVO)
│   ├── recorridos.php         # Vista admin de recorridos
│   ├── hallazgos.php          # Gestión de hallazgos críticos
│   ├── estadisticas.php       # Dashboard de estadísticas
│   └── exportar.php           # Exportación de datos
│
├── api/                       # API endpoints (actualizados)
│   ├── save-progress.php      # Guardar progreso (con redirecciones)
│   ├── upload-photo.php       # Subir fotos
│   └── ping.php               # Check conectividad
│
├── assets/
│   ├── css/
│   │   ├── app.css            # Estilos principales (mejorados)
│   │   └── admin.css          # Estilos de administración (NUEVO)
│   ├── js/
│   │   └── app.js             # JavaScript principal (mejorado)
│   └── images/                # Iconos PWA
│
└── uploads/                   # Fotos de recorridos
```

## 🎯 **Nuevas Funcionalidades en Detalle**

### **1. Sistema de Borradores Inteligente**
- Guardado automático cada vez que se responde una pregunta
- Página dedicada de confirmación (`borrador-guardado.php`)
- Lista de borradores con indicadores de progreso
- Continuación desde el punto exacto donde se dejó
- Limpieza automática de borradores antiguos

### **2. Panel de Administración Completo**
- Vista tabular con filtros avanzados
- Estadísticas en tiempo real
- Gestión de hallazgos críticos con prioridades
- Acciones masivas (exportar, eliminar)
- Ordenamiento por múltiples campos
- Paginación eficiente

### **3. Menú de Navegación Avanzado**
- Dropdown responsive con información del usuario
- Badges para indicar borradores pendientes
- Enlaces contextuales según permisos
- Integración con sistema de roles de WordPress

### **4. Sistema de Notificaciones**
- Toast notifications con diferentes tipos
- Loading overlays durante operaciones
- Mensajes contextuales de éxito/error
- Indicadores de estado offline/online

### **5. Templates Reutilizables**
- Configuración centralizada de PWA
- Headers y footers consistentes
- Scripts y estilos optimizados
- Fácil mantenimiento y actualizaciones

## 🔧 **Instrucciones de Actualización**

### **Si ya tienes la versión anterior instalada:**

1. **Hacer backup** de tu instalación actual
2. **Subir los nuevos archivos** manteniendo la estructura
3. **Ejecutar las nuevas migraciones SQL** (si las hay)
4. **Actualizar config.php** con las nuevas constantes
5. **Probar funcionalidades** en un ambiente de desarrollo

### **Para nueva instalación:**
1. Seguir las instrucciones de instalación normales
2. El instalador `install.php` detectará automáticamente las mejoras
3. Todos los templates y funcionalidades nuevas estarán disponibles

## 📱 **Experiencia de Usuario Mejorada**

### **Para Inspectores:**
- Dashboard más informativo con borradores pendientes
- Navegación intuitiva entre secciones
- Guardado automático sin pérdida de datos
- Continuación fluida de recorridos incompletos
- Historial completo de sus inspecciones

### **Para Supervisores:**
- Panel de administración completo
- Vista consolidada de todos los recorridos
- Filtros avanzados para análisis
- Alertas de hallazgos críticos
- Exportación de datos para reportes

### **Para Administradores del Sistema:**
- Templates centralizados para fácil mantenimiento
- Estructura modular y escalable
- Sistema de logs para auditoría
- Configuración flexible via base de datos

## 🚀 **Próximas Mejoras Sugeridas**

- [ ] **Dashboard de estadísticas** con gráficos interactivos
- [ ] **Sistema de reportes PDF** automáticos
- [ ] **Notificaciones push** para hallazgos críticos
- [ ] **Integración con mapas** para ubicación de hallazgos
- [ ] **API REST completa** para integraciones
- [ ] **App móvil nativa** complementaria
- [ ] **Sistema de workflow** para seguimiento de hallazgos
- [ ] **Integración con calendarios** para recorridos programados

---

**La aplicación ahora es mucho más robusta, intuitiva y completa, ofreciendo una experiencia profesional tanto para usuarios finales como para administradores.** 🎉