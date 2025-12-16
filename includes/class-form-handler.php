<?php
/**
 * Clase para captura de envíos de Contact Form 7
 *
 * @package ContactForm7_Audit
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase CFA_Form_Handler
 */
class CFA_Form_Handler {

    /**
     * Instancia de hash chain
     */
    private $hash_chain;

    /**
     * Constructor
     */
    public function __construct() {
        $this->hash_chain = new CFA_Hash_Chain();
        
        // Hook para capturar envíos de Contact Form 7
        // Usamos wpcf7_mail_sent que es más confiable y se ejecuta después del envío exitoso
        add_action('wpcf7_mail_sent', array($this, 'capture_form_submission'), 10, 1);
        
        // También capturamos con before_send_mail como respaldo
        add_action('wpcf7_before_send_mail', array($this, 'capture_form_submission_before'), 10, 3);
    }

    /**
     * Capturar envío de formulario (hook wpcf7_mail_sent)
     *
     * @param WPCF7_ContactForm $contact_form Objeto del formulario
     */
    public function capture_form_submission($contact_form) {
        // Obtener el objeto submission
        $submission = WPCF7_Submission::get_instance();
        
        if (!$submission) {
            error_log('CFA: No se pudo obtener el objeto submission');
            return;
        }
        
        $this->process_submission($contact_form, $submission);
    }

    /**
     * Capturar envío de formulario (hook wpcf7_before_send_mail - respaldo)
     *
     * @param WPCF7_ContactForm $contact_form Objeto del formulario
     * @param bool $abort Indicador para abortar envío
     * @param WPCF7_Submission $submission Objeto de envío
     */
    public function capture_form_submission_before($contact_form, &$abort, $submission) {
        // Solo registrar si no se ha registrado ya
        // Este es un hook de respaldo
    }

    /**
     * Procesar el envío del formulario
     *
     * @param WPCF7_ContactForm $contact_form Objeto del formulario
     * @param WPCF7_Submission $submission Objeto de envío
     */
    private function process_submission($contact_form, $submission) {
        // Obtener datos del formulario
        $posted_data = $submission->get_posted_data();
        
        // Obtener información del formulario
        $form_id = $contact_form->id();
        $form_name = $contact_form->title();

        // Capturar campos de aceptación
        $acceptance_fields = $this->get_acceptance_fields($contact_form, $posted_data);

        // Combinar datos del formulario con campos de aceptación
        $form_data = array(
            'fields' => $posted_data,
            'acceptance_fields' => $acceptance_fields
        );

        // Capturar datos del usuario
        $user_ip = $this->get_user_ip();
        $user_agent = $this->get_user_agent();
        
        // Capturar datos del navegador
        $browser_data = $this->get_browser_data($posted_data);

        // Preparar datos para inserción
        $record_data = array(
            'form_id' => $form_id,
            'form_name' => $form_name,
            'submission_data' => wp_json_encode($form_data, JSON_UNESCAPED_UNICODE),
            'user_ip' => $user_ip,
            'user_agent' => $user_agent,
            'browser_data' => wp_json_encode($browser_data, JSON_UNESCAPED_UNICODE)
        );

        // Insertar en la cadena de hash
        $result = $this->hash_chain->insert_record($record_data);

        if ($result === false) {
            error_log('CFA: Error al guardar registro de auditoría para formulario ID: ' . $form_id);
        } else {
            error_log('CFA: Registro guardado exitosamente. ID: ' . $result . ', Formulario: ' . $form_name);
        }
    }

    /**
     * Obtener campos de aceptación del formulario
     *
     * @param WPCF7_ContactForm $contact_form Objeto del formulario
     * @param array $posted_data Datos enviados
     * @return array Campos de aceptación con texto y estado
     */
    private function get_acceptance_fields($contact_form, $posted_data) {
        $acceptance_fields = array();

        // Escanear etiquetas del formulario
        $form_tags = $contact_form->scan_form_tags();

        foreach ($form_tags as $tag) {
            // Buscar campos de tipo acceptance
            if ($tag->basetype === 'acceptance') {
                $field_name = $tag->name;
                
                // Obtener el estado (checked/unchecked)
                $is_checked = !empty($posted_data[$field_name]);

                // Obtener el texto del campo
                $label_text = $this->get_acceptance_label($tag, $contact_form);

                $acceptance_fields[] = array(
                    'field_name' => $field_name,
                    'label_text' => $label_text,
                    'is_checked' => $is_checked,
                    'status' => $is_checked ? 'checked' : 'unchecked'
                );
            }
        }

        return $acceptance_fields;
    }

    /**
     * Obtener el texto del label de un campo de aceptación
     *
     * @param WPCF7_FormTag $tag Etiqueta del formulario
     * @param WPCF7_ContactForm $contact_form Formulario completo
     * @return string Texto del label
     */
    private function get_acceptance_label($tag, $contact_form) {
        // Intentar obtener el contenido del tag
        $text = '';
        $field_name = $tag->name;
        $form_html = $contact_form->prop('form');
        
        // Método 1: Buscar el <label> que viene DESPUÉS del campo de acceptance
        // Estructura típica en CF7:
        // <span class="wpcf7-form-control-wrap" data-name="privacidad">
        //   <span class="wpcf7-form-control wpcf7-acceptance">...</span>
        // </span>
        // <label>Acepto la política de privacidad</label>
        
        // Patrón: buscar data-name="campo" seguido de un <label>
        $pattern = '/data-name=["\']' . preg_quote($field_name, '/') . '["\'][^>]*>.*?<\/span>\s*<label[^>]*>(.*?)<\/label>/is';
        if (preg_match($pattern, $form_html, $matches)) {
            $text = wp_strip_all_tags($matches[1]);
            $text = trim($text);
        }
        
        // Método 2: Buscar dentro de [acceptance nombre]...[/acceptance] en el código del formulario
        if (empty($text)) {
            $pattern = '/\[acceptance\s+' . preg_quote($field_name, '/') . '[^\]]*\]\s*(.*?)\s*\[\/acceptance\]/is';
            if (preg_match($pattern, $form_html, $matches)) {
                $text = wp_strip_all_tags($matches[1]);
                $text = trim($text);
            }
        }
        
        // Método 3: Buscar <label> que contiene el campo de acceptance
        if (empty($text)) {
            $pattern = '/<label[^>]*>(.*?\[acceptance\s+' . preg_quote($field_name, '/') . '[^\]]*\].*?)<\/label>/is';
            if (preg_match($pattern, $form_html, $matches)) {
                // Extraer todo el contenido del label
                $label_content = $matches[1];
                // Eliminar el shortcode de acceptance
                $label_content = preg_replace('/\[acceptance\s+[^\]]*\]/', '', $label_content);
                $text = wp_strip_all_tags($label_content);
                $text = trim($text);
            }
        }
        
        // Método 4: Propiedad content del tag (CF7 antiguo)
        if (empty($text) && isset($tag->content) && !empty($tag->content)) {
            $text = wp_strip_all_tags($tag->content);
            $text = trim($text);
        }
        
        // Método 5: Buscar cualquier <label> cerca del nombre del campo
        if (empty($text)) {
            $pattern = '/' . preg_quote($field_name, '/') . '.*?<label[^>]*>(.*?)<\/label>/is';
            if (preg_match($pattern, $form_html, $matches)) {
                $text = wp_strip_all_tags($matches[1]);
                $text = trim($text);
            }
        }

        // Log para debugging
        if (empty($text)) {
            error_log('CFA Debug - No se pudo extraer label para campo: ' . $field_name);
            error_log('CFA Debug - HTML del formulario: ' . substr($form_html, 0, 500));
        }

        return !empty($text) ? $text : 'Campo de aceptación';
    }

    /**
     * Obtener dirección IP del usuario
     *
     * @return string Dirección IP
     */
    private function get_user_ip() {
        $ip = '';

        // Intentar obtener IP de diferentes fuentes
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        // Validar y sanitizar IP
        $ip = filter_var($ip, FILTER_VALIDATE_IP);

        return $ip ? $ip : 'unknown';
    }

    /**
     * Obtener User Agent del navegador
     *
     * @return string User Agent completo
     */
    private function get_user_agent() {
        return !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
    }

    /**
     * Obtener datos del navegador desde campos ocultos
     *
     * @param array $posted_data Datos del formulario
     * @return array Datos del navegador
     */
    private function get_browser_data($posted_data) {
        $browser_data = array();

        // Debug: registrar todos los campos recibidos
        error_log('CFA Debug - Campos recibidos: ' . print_r(array_keys($posted_data), true));

        // Cookies del dominio - probar ambos nombres por compatibilidad
        if (isset($posted_data['_cfa_cookies'])) {
            $browser_data['cookies'] = $posted_data['_cfa_cookies'];
        } elseif (isset($posted_data['cfa-cookies'])) {
            $browser_data['cookies'] = $posted_data['cfa-cookies'];
        } else {
            $browser_data['cookies'] = 'no-data';
            error_log('CFA Debug - No se encontraron cookies en los datos del formulario');
        }

        // Resolución de pantalla - probar ambos nombres
        $width = null;
        $height = null;
        
        if (isset($posted_data['_cfa_screen_width'])) {
            $width = intval($posted_data['_cfa_screen_width']);
        } elseif (isset($posted_data['cfa-screen-width'])) {
            $width = intval($posted_data['cfa-screen-width']);
        }
        
        if (isset($posted_data['_cfa_screen_height'])) {
            $height = intval($posted_data['_cfa_screen_height']);
        } elseif (isset($posted_data['cfa-screen-height'])) {
            $height = intval($posted_data['cfa-screen-height']);
        }
        
        if ($width && $height) {
            $browser_data['screen_resolution'] = array(
                'width' => $width,
                'height' => $height
            );
        } else {
            $browser_data['screen_resolution'] = 'no-data';
            error_log('CFA Debug - No se encontró resolución de pantalla en los datos del formulario');
        }

        // Información adicional del navegador
        $browser_data['timestamp'] = current_time('mysql');
        $browser_data['referer'] = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        
        return $browser_data;
    }
}
