<?php
// app-protexa-seguro/includes/head.php
// Template reutilizable para el <head>

$page_title = $page_title ?? APP_NAME;
$page_description = $page_description ?? PWA_DESCRIPTION;
$theme_color = $theme_color ?? '#2563eb';
$is_emergency = $is_emergency ?? false;

if ($is_emergency) {
    $theme_color = '#dc2626';
}
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="theme-color" content="<?php echo $theme_color; ?>">
    
    <!-- PWA Meta Tags -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?php echo PWA_SHORT_NAME; ?>">
    
    <!-- Prevenir cache en páginas dinámicas -->
    <?php if (isset($no_cache) && $no_cache): ?>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <?php endif; ?>
    
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">
    
    <!-- Iconos para diferentes dispositivos - Solo si existen -->
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/images/icon-152x152.png" onerror="this.remove()">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>assets/images/icon-32x32.png" onerror="this.remove()">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo BASE_URL; ?>assets/images/icon-16x16.png" onerror="this.remove()">
    
    <!-- Fallback icon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app.css?v=<?php echo APP_VERSION; ?>">
    
    <!-- CSS adicional específico de página -->
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link rel="stylesheet" href="<?php echo BASE_URL . $css; ?>?v=<?php echo APP_VERSION; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- CSS inline para emergency mode -->
    <?php if ($is_emergency): ?>
    <style>
        .app-header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%) !important;
        }
        .wizard-title, .page-title {
            color: #dc2626 !important;
        }
        .emergency-mode .category-header {
            background: linear-gradient(135deg, var(--danger-color), #ef4444) !important;
        }
    </style>
    <?php endif; ?>
    
    <!-- Meta tags adicionales específicos de página -->
    <?php if (isset($additional_meta)): ?>
        <?php echo $additional_meta; ?>
    <?php endif; ?>
</head>