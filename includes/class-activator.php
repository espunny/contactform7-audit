<?php
/**
 * Clase para activación del plugin
 *
 * @package ContactForm7_Audit
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase CFA_Activator
 */
class CFA_Activator {

    /**
     * Ejecutar acciones de activación
     */
    public static function activate() {
        self::create_audit_table();
        self::insert_genesis_record();
    }

    /**
     * Crear tabla de auditoría
     */
    private static function create_audit_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'contactform_audit';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id bigint(20) UNSIGNED NOT NULL,
            form_name varchar(255) NOT NULL DEFAULT '',
            submission_data longtext NOT NULL,
            user_ip varchar(45) NOT NULL DEFAULT '',
            user_agent text NOT NULL,
            browser_data longtext NOT NULL,
            previous_hash varchar(64) NOT NULL DEFAULT '',
            current_hash varchar(64) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY created_at (created_at),
            KEY current_hash (current_hash)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Verificar que la tabla se creó correctamente
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            wp_die('Error al crear la tabla de auditoría. Por favor, contacte al administrador.');
        }
    }

    /**
     * Insertar registro génesis
     * Hash génesis: 64 ceros (0000000000000000000000000000000000000000000000000000000000000000)
     */
    private static function insert_genesis_record() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'contactform_audit';

        // Verificar si ya existe un registro génesis
        $existing_records = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($existing_records > 0) {
            return; // Ya hay registros, no insertar génesis
        }

        // Hash génesis: 64 ceros
        $genesis_hash = str_repeat('0', 64);
        
        // Timestamp de instalación
        $installation_time = current_time('mysql');

        // Datos del registro génesis
        $genesis_data = array(
            'type' => 'genesis',
            'message' => 'Plugin ContactForm7 Audit instalado',
            'installation_timestamp' => $installation_time,
            'version' => CFA_VERSION
        );

        // Insertar registro génesis
        $wpdb->insert(
            $table_name,
            array(
                'form_id' => 0,
                'form_name' => 'Sistema',
                'submission_data' => wp_json_encode($genesis_data, JSON_UNESCAPED_UNICODE),
                'user_ip' => '',
                'user_agent' => 'System',
                'browser_data' => wp_json_encode(array('type' => 'genesis'), JSON_UNESCAPED_UNICODE),
                'previous_hash' => $genesis_hash,
                'current_hash' => $genesis_hash,
                'created_at' => $installation_time
            ),
            array(
                '%d', // form_id
                '%s', // form_name
                '%s', // submission_data
                '%s', // user_ip
                '%s', // user_agent
                '%s', // browser_data
                '%s', // previous_hash
                '%s', // current_hash
                '%s'  // created_at
            )
        );

        if ($wpdb->last_error) {
            error_log('CFA Error al insertar registro génesis: ' . $wpdb->last_error);
        }
    }
}
