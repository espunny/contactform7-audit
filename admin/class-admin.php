<?php
/**
 * Panel de administración del plugin
 *
 * @package ContactForm7_Audit
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase CFA_Admin
 */
class CFA_Admin {

    /**
     * Instancia de hash chain
     */
    private $hash_chain;

    /**
     * Constructor
     */
    public function __construct() {
        $this->hash_chain = new CFA_Hash_Chain();

        // Agregar menú de administración
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Encolar scripts y estilos
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Registrar handlers AJAX
        add_action('wp_ajax_cfa_export_data', array($this, 'ajax_export_data'));
        add_action('wp_ajax_cfa_verify_chain', array($this, 'ajax_verify_chain'));
        add_action('wp_ajax_cfa_get_records', array($this, 'ajax_get_records'));
        add_action('wp_ajax_cfa_purge_database', array($this, 'ajax_purge_database'));
    }

    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            'Auditoría CF7',           // Título de la página
            'Auditoría CF7',           // Título del menú
            'manage_options',          // Capability
            'cfa-audit',               // Menu slug
            array($this, 'render_admin_page'), // Callback
            'dashicons-shield-alt',    // Icono
            30                         // Posición
        );
    }

    /**
     * Encolar assets del panel de administración
     */
    public function enqueue_admin_assets($hook) {
        // Solo cargar en nuestra página
        if ($hook !== 'toplevel_page_cfa-audit') {
            return;
        }

        // jQuery UI Datepicker
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css');

        // JavaScript personalizado
        wp_enqueue_script(
            'cfa-admin-js',
            CFA_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery', 'jquery-ui-datepicker'),
            CFA_VERSION,
            true
        );

        // CSS personalizado
        wp_enqueue_style(
            'cfa-admin-css',
            CFA_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            CFA_VERSION
        );

        // Ko-fi Widget
        wp_enqueue_script(
            'kofi-widget',
            'https://storage.ko-fi.com/cdn/widget/Widget_2.js',
            array(),
            '2.0',
            true
        );

        // Localizar script con datos AJAX
        wp_localize_script('cfa-admin-js', 'cfaAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cfa_ajax_nonce'),
            'chunk_size' => 100,
            'strings' => array(
                'verifying' => __('Verificando integridad...', 'contactform7-audit'),
                'verified' => __('registros verificados', 'contactform7-audit'),
                'valid_chain' => __('✓ Cadena de hash válida. Todos los registros verificados correctamente.', 'contactform7-audit'),
                'invalid_chain' => __('✗ Cadena de hash comprometida.', 'contactform7-audit'),
                'corrupted_at' => __('Registro comprometido ID:', 'contactform7-audit'),
                'error_details' => __('Detalles del error:', 'contactform7-audit'),
                'exporting' => __('Exportando datos...', 'contactform7-audit'),
                'export_complete' => __('Exportación completada', 'contactform7-audit'),
                'error' => __('Error:', 'contactform7-audit'),
                'purge_confirm' => __('⚠️ ADVERTENCIA CRÍTICA ⚠️\n\nEsta operación eliminará PERMANENTEMENTE todos los registros de auditoría de la base de datos.\n\nConsecuencias IRREVERSIBLES:\n• Se perderá TODA la evidencia de formularios enviados\n• NO se podrá demostrar que un usuario rellenó un formulario\n• NO se podrá verificar qué casillas marcó o no marcó\n• Se romperá la cadena de hash, invalidando auditorías\n• Esta acción NO se puede deshacer\n\nEscribe "ELIMINAR" (en mayúsculas) para confirmar:', 'contactform7-audit'),
                'purge_cancelled' => __('Operación cancelada', 'contactform7-audit'),
                'purge_invalid' => __('Confirmación incorrecta. Operación cancelada.', 'contactform7-audit'),
                'purging' => __('Eliminando registros...', 'contactform7-audit'),
                'purge_complete' => __('Base de datos purgada. Se eliminaron', 'contactform7-audit'),
                'records' => __('registros', 'contactform7-audit')
            )
        ));
    }

    /**
     * Renderizar página de administración
     */
    public function render_admin_page() {
        global $wpdb;
        $table_name = $this->hash_chain->get_table_name();

        // Obtener estadísticas
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $first_record = $wpdb->get_row("SELECT * FROM $table_name ORDER BY id ASC LIMIT 1");
        $last_record = $wpdb->get_row("SELECT * FROM $table_name ORDER BY id DESC LIMIT 1");

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- Explicación del plugin -->
            <div class="notice notice-info cfa-info-notice">
                <h3><?php _e('¿Para qué sirve este sistema de auditoría?', 'contactform7-audit'); ?></h3>
                <p><?php _e('Este plugin implementa un sistema de <strong>trazabilidad inmutable</strong> basado en hash chaining (similar a blockchain) que garantiza:', 'contactform7-audit'); ?></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('<strong>Evidencia legal irrefutable</strong>: Cada formulario enviado queda registrado de forma que no puede ser modificado sin detectarse.', 'contactform7-audit'); ?></li>
                    <li><?php _e('<strong>Cumplimiento normativo</strong>: Permite demostrar ante autoridades que un usuario aceptó términos y condiciones específicos en una fecha concreta.', 'contactform7-audit'); ?></li>
                    <li><?php _e('<strong>Protección legal</strong>: En caso de disputa, puede probar exactamente qué información envió el usuario, incluyendo qué casillas marcó y su texto completo.', 'contactform7-audit'); ?></li>
                    <li><?php _e('<strong>Integridad verificable</strong>: Cada registro está criptográficamente vinculado al anterior. Cualquier alteración rompe la cadena y es inmediatamente detectable.', 'contactform7-audit'); ?></li>
                </ul>
                <p><strong><?php _e('Casos de uso:', 'contactform7-audit'); ?></strong> <?php _e('Consentimientos RGPD/GDPR, contratos digitales, autorizaciones médicas, formularios legales, auditorías de cumplimiento.', 'contactform7-audit'); ?></p>
            </div>
            
            <div class="cfa-dashboard">
                <!-- Estadísticas -->
                <div class="cfa-stats">
                    <div class="cfa-stat-box">
                        <h3><?php _e('Total de Registros', 'contactform7-audit'); ?></h3>
                        <p class="cfa-stat-number"><?php echo number_format($total_records); ?></p>
                    </div>
                    <div class="cfa-stat-box">
                        <h3><?php _e('Primer Registro', 'contactform7-audit'); ?></h3>
                        <p class="cfa-stat-date">
                            <?php echo $first_record ? esc_html($first_record->created_at) : __('N/A', 'contactform7-audit'); ?>
                        </p>
                    </div>
                    <div class="cfa-stat-box">
                        <h3><?php _e('Último Registro', 'contactform7-audit'); ?></h3>
                        <p class="cfa-stat-date">
                            <?php echo $last_record ? esc_html($last_record->created_at) : __('N/A', 'contactform7-audit'); ?>
                        </p>
                    </div>
                    <div class="cfa-stat-box cfa-donation-box">
                        <h3><?php _e('¿Te gusta este plugin?', 'contactform7-audit'); ?></h3>
                        <p style="font-size: 14px; margin: 10px 0;"><?php _e('Apóyame con un café ☕', 'contactform7-audit'); ?></p>
                        <a href="https://ko-fi.com/T6T11XA77" target="_blank" class="cfa-kofi-button">
                            <span class="dashicons dashicons-heart"></span>
                            <?php _e('Apoyar en Ko-fi', 'contactform7-audit'); ?>
                        </a>
                    </div>
                </div>

                <!-- Verificación de integridad -->
                <div class="cfa-section">
                    <h2><?php _e('Verificación de Integridad', 'contactform7-audit'); ?></h2>
                    <p><?php _e('Verifica que la cadena de hash no haya sido alterada.', 'contactform7-audit'); ?></p>
                    
                    <button id="cfa-verify-btn" class="button button-primary button-large">
                        <span class="dashicons dashicons-shield-alt"></span>
                        <?php _e('Verificar Integridad de la Cadena', 'contactform7-audit'); ?>
                    </button>

                    <div id="cfa-verify-progress" class="cfa-progress-container" style="display: none;">
                        <div class="cfa-progress-bar">
                            <div class="cfa-progress-fill"></div>
                        </div>
                        <p class="cfa-progress-text">0%</p>
                    </div>

                    <div id="cfa-verify-result" class="cfa-result-container"></div>
                </div>

                <!-- Exportación de datos -->
                <div class="cfa-section">
                    <h2><?php _e('Exportar Datos de Auditoría', 'contactform7-audit'); ?></h2>
                    <p><?php _e('Selecciona un rango de fechas para exportar los registros en formato JSON.', 'contactform7-audit'); ?></p>
                    
                    <div class="cfa-export-form">
                        <div class="cfa-date-range">
                            <label>
                                <?php _e('Fecha Inicio:', 'contactform7-audit'); ?>
                                <input type="text" id="cfa-date-start" class="cfa-datepicker" placeholder="YYYY-MM-DD">
                            </label>
                            <label>
                                <?php _e('Fecha Fin:', 'contactform7-audit'); ?>
                                <input type="text" id="cfa-date-end" class="cfa-datepicker" placeholder="YYYY-MM-DD">
                            </label>
                        </div>

                        <button id="cfa-export-btn" class="button button-secondary button-large">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Exportar JSON', 'contactform7-audit'); ?>
                        </button>
                    </div>

                    <div id="cfa-export-result" class="cfa-result-container"></div>
                </div>

                <!-- Tabla de registros recientes -->
                <div class="cfa-section">
                    <h2><?php _e('Registros Recientes', 'contactform7-audit'); ?></h2>
                    
                    <div id="cfa-records-table">
                        <?php $this->render_records_table(); ?>
                    </div>
                </div>

                <!-- Zona peligrosa -->
                <div class="cfa-section cfa-danger-zone">
                    <h2 style="color: #d63638;">
                        <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                        <?php _e('Zona Peligrosa', 'contactform7-audit'); ?>
                    </h2>
                    
                    <div class="cfa-danger-content">
                        <h3><?php _e('Purgar Base de Datos de Auditoría', 'contactform7-audit'); ?></h3>
                        <p class="cfa-warning-text">
                            <?php _e('Esta acción eliminará <strong>PERMANENTEMENTE</strong> todos los registros de auditoría.', 'contactform7-audit'); ?>
                        </p>
                        <p class="cfa-warning-text">
                            <?php _e('⚠️ Consecuencias IRREVERSIBLES:', 'contactform7-audit'); ?>
                        </p>
                        <ul class="cfa-warning-list">
                            <li><?php _e('Se perderá TODA la evidencia legal de formularios enviados', 'contactform7-audit'); ?></li>
                            <li><?php _e('NO podrá demostrar que un usuario rellenó un formulario', 'contactform7-audit'); ?></li>
                            <li><?php _e('NO podrá verificar qué casillas de aceptación marcó el usuario', 'contactform7-audit'); ?></li>
                            <li><?php _e('Se romperá la cadena de hash, invalidando cualquier auditoría', 'contactform7-audit'); ?></li>
                            <li><?php _e('Esta operación NO se puede deshacer', 'contactform7-audit'); ?></li>
                        </ul>
                        
                        <button id="cfa-purge-btn" class="button button-danger button-large">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Purgar Todos los Registros', 'contactform7-audit'); ?>
                        </button>
                        
                        <div id="cfa-purge-result" class="cfa-result-container"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderizar tabla de registros
     */
    private function render_records_table($limit = 20) {
        global $wpdb;
        $table_name = $this->hash_chain->get_table_name();

        $records = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_name ORDER BY id DESC LIMIT %d", $limit)
        );

        if (empty($records)) {
            echo '<p>' . __('No hay registros disponibles.', 'contactform7-audit') . '</p>';
            return;
        }

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('ID', 'contactform7-audit'); ?></th>
                    <th><?php _e('Formulario', 'contactform7-audit'); ?></th>
                    <th><?php _e('IP Usuario', 'contactform7-audit'); ?></th>
                    <th><?php _e('Fecha', 'contactform7-audit'); ?></th>
                    <th><?php _e('Hash', 'contactform7-audit'); ?></th>
                    <th><?php _e('Acciones', 'contactform7-audit'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $record): ?>
                <tr>
                    <td><?php echo esc_html($record->id); ?></td>
                    <td><?php echo esc_html($record->form_name); ?> (ID: <?php echo esc_html($record->form_id); ?>)</td>
                    <td><?php echo esc_html($record->user_ip); ?></td>
                    <td><?php echo esc_html($record->created_at); ?></td>
                    <td><code class="cfa-hash"><?php echo esc_html(substr($record->current_hash, 0, 16)); ?>...</code></td>
                    <td>
                        <button class="button button-small cfa-view-details" data-record-id="<?php echo esc_attr($record->id); ?>">
                            <?php _e('Ver Detalles', 'contactform7-audit'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * AJAX: Exportar datos
     */
    public function ajax_export_data() {
        // Verificar nonce
        check_ajax_referer('cfa_ajax_nonce', 'nonce');

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'contactform7-audit')));
        }

        global $wpdb;
        $table_name = $this->hash_chain->get_table_name();

        // Obtener parámetros
        $date_start = isset($_POST['date_start']) ? sanitize_text_field($_POST['date_start']) : '';
        $date_end = isset($_POST['date_end']) ? sanitize_text_field($_POST['date_end']) : '';

        // Construir query
        $where = '';
        if (!empty($date_start) && !empty($date_end)) {
            $where = $wpdb->prepare(
                "WHERE created_at BETWEEN %s AND %s",
                $date_start . ' 00:00:00',
                $date_end . ' 23:59:59'
            );
        }

        // Límite de seguridad: máximo 10,000 registros
        $query = "SELECT * FROM $table_name $where ORDER BY id ASC LIMIT 10000";
        $records = $wpdb->get_results($query);

        if (empty($records)) {
            wp_send_json_error(array('message' => __('No se encontraron registros en el rango especificado', 'contactform7-audit')));
        }

        // Preparar datos para exportación
        $export_data = array(
            'export_info' => array(
                'plugin' => 'ContactForm7 Audit',
                'version' => CFA_VERSION,
                'export_date' => current_time('mysql'),
                'date_range' => array(
                    'start' => $date_start,
                    'end' => $date_end
                ),
                'total_records' => count($records)
            ),
            'records' => array()
        );

        foreach ($records as $record) {
            $export_data['records'][] = array(
                'id' => $record->id,
                'form_id' => $record->form_id,
                'form_name' => $record->form_name,
                'submission_data' => json_decode($record->submission_data, true),
                'user_ip' => $record->user_ip,
                'user_agent' => $record->user_agent,
                'browser_data' => json_decode($record->browser_data, true),
                'previous_hash' => $record->previous_hash,
                'current_hash' => $record->current_hash,
                'created_at' => $record->created_at
            );
        }

        wp_send_json_success(array(
            'data' => $export_data,
            'filename' => 'auditoria-cf7-' . date('Y-m-d') . '.json'
        ));
    }

    /**
     * AJAX: Verificar cadena de hash
     */
    public function ajax_verify_chain() {
        // Verificar nonce
        check_ajax_referer('cfa_ajax_nonce', 'nonce');

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'contactform7-audit')));
        }

        // Obtener parámetros
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;

        // Verificar chunk
        $result = $this->hash_chain->verify_chain($offset, $limit);

        wp_send_json_success($result);
    }

    /**
     * AJAX: Obtener registros
     */
    public function ajax_get_records() {
        // Verificar nonce
        check_ajax_referer('cfa_ajax_nonce', 'nonce');

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'contactform7-audit')));
        }

        global $wpdb;
        $table_name = $this->hash_chain->get_table_name();

        $record_id = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;

        if (!$record_id) {
            wp_send_json_error(array('message' => __('ID de registro inválido', 'contactform7-audit')));
        }

        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $record_id
        ));

        if (!$record) {
            wp_send_json_error(array('message' => __('Registro no encontrado', 'contactform7-audit')));
        }

        // Decodificar JSON
        $record->submission_data = json_decode($record->submission_data, true);
        $record->browser_data = json_decode($record->browser_data, true);

        wp_send_json_success(array('record' => $record));
    }

    /**
     * AJAX: Purgar base de datos
     */
    public function ajax_purge_database() {
        // Verificar nonce
        check_ajax_referer('cfa_ajax_nonce', 'nonce');

        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'contactform7-audit')));
        }

        global $wpdb;
        $table_name = $this->hash_chain->get_table_name();

        // Contar registros antes de eliminar
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // Truncar tabla (elimina todos los registros)
        $result = $wpdb->query("TRUNCATE TABLE $table_name");

        if ($result === false) {
            error_log('CFA: Error al purgar base de datos: ' . $wpdb->last_error);
            wp_send_json_error(array('message' => __('Error al purgar la base de datos', 'contactform7-audit')));
        }

        // Insertar nuevo registro génesis
        $genesis_hash = str_repeat('0', 64);
        $installation_time = current_time('mysql');

        $genesis_data = array(
            'type' => 'genesis_after_purge',
            'message' => 'Base de datos purgada y reiniciada',
            'purge_timestamp' => $installation_time,
            'records_deleted' => $count,
            'version' => CFA_VERSION
        );

        $wpdb->insert(
            $table_name,
            array(
                'form_id' => 0,
                'form_name' => 'Sistema',
                'submission_data' => wp_json_encode($genesis_data, JSON_UNESCAPED_UNICODE),
                'user_ip' => '',
                'user_agent' => 'System',
                'browser_data' => wp_json_encode(array('type' => 'genesis_after_purge'), JSON_UNESCAPED_UNICODE),
                'previous_hash' => $genesis_hash,
                'current_hash' => $genesis_hash,
                'created_at' => $installation_time
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        error_log('CFA: Base de datos purgada. ' . $count . ' registros eliminados.');

        wp_send_json_success(array(
            'message' => __('Base de datos purgada exitosamente', 'contactform7-audit'),
            'records_deleted' => $count
        ));
    }
}
