<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Print_API {

    private const SOCKET_TRIGGER_URL = 'http://127.0.0.1:3001/emit-print';

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/print/add', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'dispatch_print' ),
            'permission_callback' => '__return_true',
        ));

        register_rest_route( 'nativa/v2', '/print/logs', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_print_logs' ),
            'permission_callback' => '__return_true',
        ));

        register_rest_route( 'nativa/v2', '/print/update-status', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'update_print_status' ),
            'permission_callback' => '__return_true',
        ));
    }

    public function dispatch_print( $request ) {
        $params = $request->get_json_params();
        
        $type = isset($params['type']) ? $params['type'] : 'kitchen';
        $data = isset($params['data']) ? $params['data'] : [];
        $station = isset($params['station']) ? $params['station'] : 'frente';

        // [SONDA 4] Log do PHP
        error_log("🕵️ [PHP API] Recebido pedido de impressão. Station: [$station]");

        $user = wp_get_current_user();
        $user_name = $user->exists() ? $user->display_name : 'Sistema';

        $first_ticket = !empty($data['tickets']) ? $data['tickets'][0] : [];
        $order_id = $first_ticket['orderId'] ?? 0;
        $service_type = $first_ticket['sessionType'] ?? 'pdv';
        $identifier = isset($first_ticket['table']) ? "Mesa " . $first_ticket['table'] : ($first_ticket['client'] ?? 'Pedido');

        $log_id = 0;
        if ( class_exists('Nativa_Print_Log') ) {
            $logger = new Nativa_Print_Log();
            $log_data = [
                'order_id'     => $order_id,
                'user_name'    => $user_name,
                'type'         => $type,
                'service_type' => $service_type,
                'identifier'   => $identifier,
                'station'      => $station,
                'status'       => 'pending_server',
                'payload'      => json_encode($data)
            ];
            $log_id = $logger->add_log($log_data);
        }

        $response = wp_remote_post( self::SOCKET_TRIGGER_URL, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => json_encode([
                'store_id' => 'loja_1', 
                'type'     => $type,
                'station'  => $station,
                'log_id'   => $log_id,
                'data'     => $data
            ]),
            'timeout' => 5,
            'blocking' => true,
            'sslverify' => false 
        ));

        if ( is_wp_error( $response ) ) {
            $error_msg = $response->get_error_message();
            error_log( 'Nativa Print API Error: ' . $error_msg );
            
            if ( isset($logger) && $log_id ) {
                $logger->update_status($log_id, 'server_error');
            }

            return new WP_REST_Response([
                'success' => false, 
                'message' => 'Falha na comunicação interna: ' . $error_msg
            ], 500);
        }

        return new WP_REST_Response(['success' => true, 'log_id' => $log_id], 200);
    }

    public function get_print_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nativa_print_logs';
        if ( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {
            return new WP_REST_Response(['success' => true, 'logs' => []], 200);
        }
        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 50");
        return new WP_REST_Response(['success' => true, 'logs' => $logs], 200);
    }

    public function update_print_status($request) {
        global $wpdb;
        $params = $request->get_json_params();
        $log_id = isset($params['log_id']) ? intval($params['log_id']) : 0;
        $status = isset($params['status']) ? sanitize_text_field($params['status']) : '';

        if ( $log_id && $status ) {
            $wpdb->update(
                $wpdb->prefix . 'nativa_print_logs', 
                ['status' => $status], 
                ['id' => $log_id]
            );
        }
        return new WP_REST_Response(['success' => true], 200);
    }
}