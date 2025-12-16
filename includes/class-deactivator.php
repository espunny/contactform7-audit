<?php
/**
 * Clase para desactivación del plugin
 *
 * @package ContactForm7_Audit
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase CFA_Deactivator
 */
class CFA_Deactivator {

    /**
     * Ejecutar acciones de desactivación
     * Nota: No eliminamos la tabla aquí, solo en uninstall.php
     */
    public static function deactivate() {
        // Limpiar cualquier tarea programada si existiera
        wp_clear_scheduled_hook('cfa_cleanup_old_records');
        
        // Limpiar transients si existen
        delete_transient('cfa_last_hash');
    }
}
