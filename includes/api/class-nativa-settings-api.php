<?php
/**
 * API de Configurações Gerais (Horários, Status e Contato)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Settings_API {

    public function register_routes() {
        // 1. Horários (Existente)
        register_rest_route( 'nativa/v2', '/settings/hours', array(
            'methods'  => array('GET', 'POST'),
            'callback' => array( $this, 'handle_hours' ),
            'permission_callback' => function() { return current_user_can('manage_options'); }
        ));

        // 2. Dados Gerais / Contato (NOVO)
        register_rest_route( 'nativa/v2', '/settings/general', array(
            'methods'  => array('GET', 'POST'),
            'callback' => array( $this, 'handle_general' ), 
            'permission_callback' => function() { return current_user_can('manage_options'); }
        ));

        // 3. Leitura Pública (NOVO - Para o App do Cliente)
        register_rest_route( 'nativa/v2', '/settings/public', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_public_settings' ),
            'permission_callback' => '__return_true'
        ));

        // 4. Status Realtime (Existente)
        register_rest_route( 'nativa/v2', '/store-status', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_realtime_status' ),
            'permission_callback' => '__return_true'
        ));
    }

    // --- MANIPULAÇÃO DE DADOS GERAIS ---
    public function handle_general( $request ) {
        if ( $request->get_method() === 'POST' ) {
            $data = $request->get_json_params();
            $clean_data = [
                'whatsapp' => preg_replace('/[^0-9]/', '', $data['contact']['whatsapp'] ?? ''),
                'address'  => sanitize_text_field($data['contact']['address'] ?? ''),
                'instagram'=> sanitize_text_field($data['contact']['instagram'] ?? ''),
                // NOVO CAMPO:
                'google_reviews' => esc_url_raw($data['contact']['google_reviews'] ?? '')
            ];
            update_option( 'nativa_general_settings', $clean_data );
            return new WP_REST_Response(['success' => true, 'message' => 'Configurações salvas!'], 200);
        }
        $saved = get_option( 'nativa_general_settings', [] );
        return new WP_REST_Response(['success' => true, 'contact' => $saved], 200);
    }

    // --- LEITURA PÚBLICA ---
    public function get_public_settings() {
        $saved = get_option( 'nativa_general_settings', [] );
        $contact = [
            'whatsapp' => isset($saved['whatsapp']) ? $saved['whatsapp'] : '5500000000000',
            'address'  => isset($saved['address']) ? $saved['address'] : '',
            'instagram'=> isset($saved['instagram']) ? $saved['instagram'] : '',
            // NOVO CAMPO:
            'google_reviews' => isset($saved['google_reviews']) ? $saved['google_reviews'] : ''
        ];
        return new WP_REST_Response(['success' => true, 'contact' => $contact], 200);
    }

    // ... (Mantenha handle_hours e get_realtime_status como estavam) ...
    public function handle_hours( $request ) {
        if ( $request->get_method() === 'POST' ) {
            $data = $request->get_param('hours');
            update_option( 'nativa_business_hours', $data ); 
            return new WP_REST_Response(['success' => true, 'message' => 'Horários salvos!'], 200);
        }
        $saved = get_option( 'nativa_business_hours', [] );
        $default_week = array_fill_keys(['mon','tue','wed','thu','fri','sat','sun'], ['start'=>'18:00', 'end'=>'23:00', 'active'=>true]);
        $structure = array(
            'general'  => isset($saved['general'])  ? $saved['general']  : $default_week,
            'delivery' => isset($saved['delivery']) ? $saved['delivery'] : $default_week,
            'pickup'   => isset($saved['pickup'])   ? $saved['pickup']   : $default_week,
            'manual_override' => isset($saved['manual_override']) ? $saved['manual_override'] : 'auto'
        );
        return new WP_REST_Response(['success' => true, 'hours' => $structure], 200);
    }

    public function get_realtime_status() {
       $hours = get_option( 'nativa_business_hours', [] );
       $override = isset($hours['manual_override']) ? $hours['manual_override'] : 'auto';

       if ( $override === 'force_closed' ) return new WP_REST_Response(['general' => false, 'message' => 'Fechado temporariamente'], 200);
       if ( $override === 'force_open' ) return new WP_REST_Response(['general' => true, 'message' => 'Aberto (Manual)'], 200);

       $tz = new DateTimeZone('America/Sao_Paulo'); 
       $now = new DateTime('now', $tz);
       $current_day = strtolower( $now->format('D') ); 
       $current_time = $now->format('H:i');

       $status = array(
           'general'  => $this->is_open_now( $hours, 'general', $current_day, $current_time ),
           'delivery' => $this->is_open_now( $hours, 'delivery', $current_day, $current_time ),
           'pickup'   => $this->is_open_now( $hours, 'pickup', $current_day, $current_time )
       );

       if ( ! $status['general'] ) { $status['delivery'] = false; $status['pickup'] = false; }

       return new WP_REST_Response( $status, 200 );
    }

    private function is_open_now( $all_hours, $type, $day, $time ) {
        if ( ! isset( $all_hours[$type][$day] ) ) return false; 
        $config = $all_hours[$type][$day];
        if ( ! $config['active'] ) return false; 
        return ( $time >= $config['start'] && $time <= $config['end'] );
    }
}