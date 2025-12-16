<?php
/**
 * Ejecutado cuando se desinstala el plugin
 *
 * @package ContactForm7_Audit
 */

// Si uninstall.php no es llamado por WordPress, salir
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Eliminar la tabla de auditoría
global $wpdb;

$table_name = $wpdb->prefix . 'contactform_audit';

// Eliminar tabla
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Limpiar opciones del plugin si las hay
delete_option('cfa_plugin_version');
delete_transient('cfa_last_hash');

// Limpiar tareas programadas
wp_clear_scheduled_hook('cfa_cleanup_old_records');

// Log de desinstalación
if (defined('WP_DEBUG') && WP_DEBUG === true) {
    error_log('CFA: Plugin desinstalado. Tabla ' . $table_name . ' eliminada.');
}
