<?php
/**
 * Clase para manejo de hash chaining
 *
 * @package ContactForm7_Audit
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase CFA_Hash_Chain
 */
class CFA_Hash_Chain {

    /**
     * Hash génesis - 64 ceros
     * Este hash se usa como punto de partida de la cadena
     */
    const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Nombre de la tabla
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'contactform_audit';
    }

    /**
     * Obtener el último hash de la cadena
     *
     * @return string Hash del último registro o hash génesis si no hay registros
     */
    public function get_last_hash() {
        global $wpdb;

        $last_hash = $wpdb->get_var(
            "SELECT current_hash FROM {$this->table_name} ORDER BY id DESC LIMIT 1"
        );

        // Si no hay registros, retornar hash génesis
        return $last_hash ? $last_hash : self::GENESIS_HASH;
    }

    /**
     * Calcular hash SHA-256 de un registro
     *
     * @param array $data Datos del registro
     * @param string $previous_hash Hash del registro anterior
     * @return string Hash calculado
     */
    public function calculate_hash($data, $previous_hash) {
        // Crear string concatenado para hashear (método más consistente)
        // Importante: submission_data y browser_data ya vienen como JSON strings
        $hash_string = 
            $data['form_id'] . '|' .
            $data['form_name'] . '|' .
            $data['submission_data'] . '|' .
            $data['user_ip'] . '|' .
            $data['user_agent'] . '|' .
            $data['browser_data'] . '|' .
            $data['created_at'] . '|' .
            $previous_hash;

        // Calcular hash SHA-256
        return hash('sha256', $hash_string);
    }

    /**
     * Insertar un nuevo registro en la cadena
     *
     * @param array $data Datos del registro
     * @return int|false ID del registro insertado o false en caso de error
     */
    public function insert_record($data) {
        global $wpdb;

        // Obtener el último hash
        $previous_hash = $this->get_last_hash();

        // Añadir timestamp si no existe
        if (empty($data['created_at'])) {
            $data['created_at'] = current_time('mysql');
        }

        // Calcular hash del nuevo registro
        $current_hash = $this->calculate_hash($data, $previous_hash);

        // Preparar datos para inserción
        $insert_data = array(
            'form_id' => $data['form_id'],
            'form_name' => $data['form_name'],
            'submission_data' => $data['submission_data'],
            'user_ip' => $data['user_ip'],
            'user_agent' => $data['user_agent'],
            'browser_data' => $data['browser_data'],
            'previous_hash' => $previous_hash,
            'current_hash' => $current_hash,
            'created_at' => $data['created_at']
        );

        // Insertar en la base de datos
        $result = $wpdb->insert(
            $this->table_name,
            $insert_data,
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

        if ($result === false) {
            error_log('CFA Error al insertar registro: ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Verificar la integridad de la cadena
     *
     * @param int $offset Offset para verificación por lotes
     * @param int $limit Límite de registros a verificar
     * @return array Resultado de la verificación
     */
    public function verify_chain($offset = 0, $limit = 100) {
        global $wpdb;

        // Obtener registros ordenados por ID
        $records = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY id ASC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );

        // Obtener total de registros
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");

        $result = array(
            'valid' => true,
            'total_records' => $total_records,
            'verified_records' => count($records),
            'offset' => $offset,
            'corrupted_record' => null,
            'error_details' => null
        );

        // Si es el primer lote, verificar el hash génesis
        if ($offset === 0 && count($records) > 0) {
            $first_record = $records[0];
            if ($first_record->previous_hash !== self::GENESIS_HASH) {
                $result['valid'] = false;
                $result['corrupted_record'] = $first_record->id;
                $result['error_details'] = 'El primer registro no tiene el hash génesis correcto';
                return $result;
            }
        }

        // Verificar cada registro
        foreach ($records as $index => $record) {
            // Verificar si es un registro génesis (previous_hash y current_hash son ambos GENESIS_HASH)
            $is_genesis = ($record->previous_hash === self::GENESIS_HASH && $record->current_hash === self::GENESIS_HASH);
            
            if ($is_genesis) {
                // Para el registro génesis, solo verificar que ambos hashes sean correctos
                if ($record->previous_hash !== self::GENESIS_HASH || $record->current_hash !== self::GENESIS_HASH) {
                    $result['valid'] = false;
                    $result['corrupted_record'] = $record->id;
                    $result['error_details'] = 'Registro génesis tiene hashes incorrectos';
                    return $result;
                }
                // No recalcular hash para registro génesis, continuar con el siguiente
                continue;
            }
            
            // Para registros normales, reconstruir datos para verificación
            $data = array(
                'form_id' => $record->form_id,
                'form_name' => $record->form_name,
                'submission_data' => $record->submission_data,
                'user_ip' => $record->user_ip,
                'user_agent' => $record->user_agent,
                'browser_data' => $record->browser_data,
                'created_at' => $record->created_at
            );

            // Calcular hash esperado
            $calculated_hash = $this->calculate_hash($data, $record->previous_hash);

            // Comparar hashes
            if ($calculated_hash !== $record->current_hash) {
                $result['valid'] = false;
                $result['corrupted_record'] = $record->id;
                $result['error_details'] = sprintf(
                    'Hash no coincide. Esperado: %s, Encontrado: %s',
                    $calculated_hash,
                    $record->current_hash
                );
                return $result;
            }

            // Verificar que el hash previo del siguiente registro coincida con el hash actual
            if ($index < count($records) - 1) {
                $next_record = $records[$index + 1];
                if ($next_record->previous_hash !== $record->current_hash) {
                    $result['valid'] = false;
                    $result['corrupted_record'] = $next_record->id;
                    $result['error_details'] = sprintf(
                        'Cadena rota entre registros %d y %d',
                        $record->id,
                        $next_record->id
                    );
                    return $result;
                }
            }
        }

        return $result;
    }

    /**
     * Obtener el nombre de la tabla
     *
     * @return string
     */
    public function get_table_name() {
        return $this->table_name;
    }
}
