<?php
/**
 * API de Configurações Gerais + Fiscal
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Settings_API {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/settings/hours', array( 'methods' => ['GET', 'POST'], 'callback' => array( $this, 'handle_hours' ), 'permission_callback' => function() { return current_user_can('manage_options'); } ));
        register_rest_route( 'nativa/v2', '/settings/general', array( 'methods' => ['GET', 'POST'], 'callback' => array( $this, 'handle_general' ), 'permission_callback' => function() { return current_user_can('manage_options'); } ));
        register_rest_route( 'nativa/v2', '/settings/public', array( 'methods' => 'GET', 'callback' => array( $this, 'get_public_settings' ), 'permission_callback' => '__return_true' ));
        register_rest_route( 'nativa/v2', '/store-status', array( 'methods' => 'GET', 'callback' => array( $this, 'get_realtime_status' ), 'permission_callback' => '__return_true' ));
        register_rest_route( 'nativa/v2', '/settings/loyalty', array( 'methods' => ['GET', 'POST'], 'callback' => array( $this, 'handle_loyalty' ), 'permission_callback' => function() { return current_user_can('manage_options'); } ));

        register_rest_route( 'nativa/v2', '/settings/fiscal', array(
            'methods'  => array('GET', 'POST'),
            'callback' => array( $this, 'handle_fiscal' ),
            'permission_callback' => function() { return current_user_can('manage_options'); }
        ));
    }

    // ... (handle_fiscal, handle_hours e handle_general mantidos iguais) ...
    public function handle_fiscal( $request ) {
        // (Código mantido da versão anterior)
        if ( $request->get_method() === 'POST' ) {
            $cert_id = 0;
            $files = $request->get_file_params();
            if ( !empty($files['pfx_file']) ) {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
                $upload_overrides = array( 'test_form' => false, 'mimes' => array('pfx' => 'application/x-pkcs12') );
                $movefile = wp_handle_upload( $files['pfx_file'], $upload_overrides );
                if ( $movefile && ! isset( $movefile['error'] ) ) {
                    $attachment = array( 'guid' => $movefile['url'], 'post_mime_type' => $movefile['type'], 'post_title' => 'Certificado A1 Nativa', 'post_content' => '', 'post_status' => 'inherit' );
                    $cert_id = wp_insert_attachment( $attachment, $movefile['file'] );
                }
            }
            $params = $request->get_body_params();
            $current_settings = get_option('nativa_fiscal_settings', []);
            if ( $cert_id === 0 && !empty($current_settings['cert_id']) ) { $cert_id = $current_settings['cert_id']; }
            $new_settings = [ 'razao_social' => sanitize_text_field($params['razao_social']), 'cnpj' => preg_replace('/\D/', '', $params['cnpj']), 'ie' => preg_replace('/\D/', '', $params['ie']), 'csc_id' => sanitize_text_field($params['csc_id']), 'csc_token' => sanitize_text_field($params['csc_token']), 'cert_password'=> sanitize_text_field($params['cert_password']), 'environment' => sanitize_text_field($params['environment']), 'cert_id' => $cert_id ];
            update_option( 'nativa_fiscal_settings', $new_settings );
            return new WP_REST_Response(['success' => true, 'message' => 'Configurações fiscais salvas!'], 200);
        }
        $saved = get_option( 'nativa_fiscal_settings', [] );
        $has_cert = !empty($saved['cert_id']);
        unset($saved['cert_password']); unset($saved['cert_id']);
        $saved['has_certificate'] = $has_cert;
        return new WP_REST_Response(['success' => true, 'fiscal' => $saved], 200);
    }

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

    public function handle_general( $request ) {
        if ( $request->get_method() === 'POST' ) {
            $data = $request->get_json_params();
            $clean_data = [
                'whatsapp' => preg_replace('/[^0-9]/', '', $data['contact']['whatsapp'] ?? ''),
                'address'  => sanitize_text_field($data['contact']['address'] ?? ''),
                'instagram'=> sanitize_text_field($data['contact']['instagram'] ?? ''),
                'google_reviews' => esc_url_raw($data['contact']['google_reviews'] ?? '')
            ];
            update_option( 'nativa_general_settings', $clean_data );
            return new WP_REST_Response(['success' => true, 'message' => 'Configurações salvas!'], 200);
        }
        $saved = get_option( 'nativa_general_settings', [] );
        return new WP_REST_Response(['success' => true, 'contact' => $saved], 200);
    }

    public function get_public_settings() {
        $saved = get_option( 'nativa_general_settings', [] );
        $hours_saved = get_option( 'nativa_business_hours', [] ); // <--- Carrega Horários
        $loyalty = get_option( 'nativa_loyalty_settings', [] );
        
        // Garante estrutura padrão se vazio
        $default_week = array_fill_keys(['mon','tue','wed','thu','fri','sat','sun'], ['start'=>'18:00', 'end'=>'23:00', 'active'=>true]);
        $hours = array(
            'general' => isset($hours_saved['general']) ? $hours_saved['general'] : $default_week
        );

        $contact = [
            'whatsapp' => isset($saved['whatsapp']) ? $saved['whatsapp'] : '',
            'address'  => isset($saved['address']) ? $saved['address'] : '',
            'instagram'=> isset($saved['instagram']) ? $saved['instagram'] : '',
            'google_reviews' => isset($saved['google_reviews']) ? $saved['google_reviews'] : '',
            'loyalty_active' => $loyalty['enabled'] ?? false
        ];

        // Retorna contato E horários
        return new WP_REST_Response([
            'success' => true, 
            'contact' => $contact,
            'hours'   => $hours
        ], 200);
    }

    // ... (get_realtime_status e handle_loyalty mantidos) ...
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
    public function handle_loyalty( $request ) {
        if ( $request->get_method() === 'POST' ) {
            $params = $request->get_json_params();
            $settings = [ 'enabled' => (bool) ($params['enabled'] ?? true), 'points_earn_rate' => floatval($params['points_earn_rate'] ?? 1), 'points_redeem_rate' => floatval($params['points_redeem_rate'] ?? 1), 'validity_days' => intval($params['validity_days'] ?? 365), 'min_redeem' => intval($params['min_redeem'] ?? 0), 'daily_limit' => intval($params['daily_limit'] ?? 0) ];
            update_option( 'nativa_loyalty_settings', $settings );
            return new WP_REST_Response(['success' => true, 'message' => 'Configurações salvas!'], 200);
        }
        $saved = get_option( 'nativa_loyalty_settings', [] );
        return new WP_REST_Response([ 'success' => true, 'settings' => [ 'enabled' => $saved['enabled'] ?? false, 'points_earn_rate' => $saved['points_earn_rate'] ?? 1.0, 'points_redeem_rate' => $saved['points_redeem_rate'] ?? 1.0, 'validity_days' => $saved['validity_days'] ?? 365, 'min_redeem' => $saved['min_redeem'] ?? 0, 'daily_limit' => $saved['daily_limit'] ?? 0 ] ], 200);
    }
}