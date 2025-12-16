<?php
/**
 * Plugin Name: ContactForm7 Audit
 * Plugin URI: https://github.com/yourusername/contactform7-audit
 * Description: Sistema de trazabilidad completa para formularios Contact Form 7 con hash chaining para auditorías
 * Version: 1.0.1
 * Author: Rubén García
 * Author URI: www.linkedin.com/in/ruben-garcia-4383853a
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: contactform7-audit
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('CFA_VERSION', '1.0.1');
define('CFA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CFA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CFA_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Verificar si Contact Form 7 está activo
 */
function cfa_check_contact_form_7() {
    // Verificar si Contact Form 7 está activo mediante función
    if (!function_exists('wpcf7')) {
        add_action('admin_notices', 'cfa_contact_form_7_missing_notice');
        return false;
    }
    return true;
}

/**
 * Notificación de Contact Form 7 faltante
 */
function cfa_contact_form_7_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('ContactForm7 Audit requiere que Contact Form 7 esté instalado y activado.', 'contactform7-audit'); ?></p>
    </div>
    <?php
}

/**
 * Código de activación del plugin
 */
function cfa_activate_plugin() {
    require_once CFA_PLUGIN_DIR . 'includes/class-activator.php';
    CFA_Activator::activate();
}

/**
 * Código de desactivación del plugin
 */
function cfa_deactivate_plugin() {
    require_once CFA_PLUGIN_DIR . 'includes/class-deactivator.php';
    CFA_Deactivator::deactivate();
}

// Registrar hooks de activación y desactivación
register_activation_hook(__FILE__, 'cfa_activate_plugin');
register_deactivation_hook(__FILE__, 'cfa_deactivate_plugin');

/**
 * Cargar clases del plugin
 */
function cfa_load_classes() {
    // Cargar clases principales siempre
    require_once CFA_PLUGIN_DIR . 'includes/class-hash-chain.php';
    require_once CFA_PLUGIN_DIR . 'includes/class-form-handler.php';
    
    // Cargar panel de administración
    if (is_admin()) {
        require_once CFA_PLUGIN_DIR . 'admin/class-admin.php';
        new CFA_Admin();
    }
    
    // Inicializar captura de formularios si CF7 está disponible
    if (function_exists('wpcf7')) {
        new CFA_Form_Handler();
    }
}
add_action('plugins_loaded', 'cfa_load_classes');

/**
 * Verificar Contact Form 7 al activar
 */
function cfa_check_dependencies() {
    if (!cfa_check_contact_form_7()) {
        deactivate_plugins(CFA_PLUGIN_BASENAME);
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}
add_action('admin_init', 'cfa_check_dependencies');

/**
 * Encolar scripts del frontend
 */
function cfa_enqueue_public_scripts() {
    // Cargar en todas las páginas para asegurar que esté disponible
    // El script verifica internamente si hay formularios CF7
    wp_enqueue_script(
        'cfa-browser-capture',
        CFA_PLUGIN_URL . 'public/js/browser-capture.js',
        array('jquery'),
        CFA_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'cfa_enqueue_public_scripts');

/**
 * Cargar traducciones
 */
function cfa_load_textdomain() {
    load_plugin_textdomain('contactform7-audit', false, dirname(CFA_PLUGIN_BASENAME) . '/languages');
}
add_action('init', 'cfa_load_textdomain');
