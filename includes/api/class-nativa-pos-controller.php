<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_POS_Controller {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/add-item', [
            'methods' => 'POST', 'callback' => [ $this, 'add_item_to_session' ], 'permission_callback' => '__return_true',
        ]);
        register_rest_route( 'nativa/v2', '/session-items', [
            'methods' => 'GET', 'callback' => [ $this, 'get_session_data' ], 'permission_callback' => '__return_true',
        ]);
        register_rest_route( 'nativa/v2', '/session/(?P<id>\d+)', [
            'methods' => 'GET', 'callback' => [ $this, 'get_session_summary' ], 'permission_callback' => '__return_true',
        ]);
        register_rest_route( 'nativa/v2', '/cancel-item', [
            'methods' => 'POST', 'callback' => [ $this, 'cancel_item_endpoint' ], 'permission_callback' => '__return_true',
        ]);
        register_rest_route( 'nativa/v2', '/create-account', [
            'methods' => 'POST', 'callback' => [ $this, 'create_account' ], 'permission_callback' => '__return_true',
        ]);
        register_rest_route( 'nativa/v2', '/transfer-items', [
            'methods' => 'POST', 'callback' => [ $this, 'transfer_items' ], 'permission_callback' => '__return_true',
        ]);
        register_rest_route( 'nativa/v2', '/void-session', [
            'methods' => 'POST', 'callback' => [ $this, 'void_session_endpoint' ], 'permission_callback' => '__return_true',
        ]);
    }

    public function add_item_to_session( $request ) {
        $params = $request->get_params();
        $session_id = !empty($params['session_id']) ? (int)$params['session_id'] : 0;
        $product_id = isset($params['product_id']) ? (int)$params['product_id'] : 0;

        if ( empty( $product_id ) ) return new WP_REST_Response( ['success' => false, 'message' => 'Produto obrigatório.'], 400 );

        if ( $session_id === 0 ) {
            $table_number = isset($params['table_number']) ? (int)$params['table_number'] : null;
            $type = isset($params['type']) ? $params['type'] : 'table';
            $client_name = isset($params['client_name']) ? sanitize_text_field($params['client_name']) : null;

            if ( $type === 'table' && ! $table_number ) return new WP_REST_Response( ['success' => false, 'message' => 'Mesa necessária.'], 400 );

            $session_model = new Nativa_Session();
            $session_id = $session_model->open( $type, $table_number, $client_name );
            
            if ( ! $session_id ) return new WP_REST_Response( ['success' => false, 'message' => 'Erro ao criar sessão.'], 500 );
        }

        $order_model = new Nativa_Order_Item();
        $result = $order_model->add_item( 
            $session_id, 
            $product_id, 
            isset($params['qty']) ? $params['qty'] : 1, 
            isset($params['modifiers']) ? $params['modifiers'] : null, 
            isset($params['sub_account']) ? $params['sub_account'] : 'Principal' 
        );

        if ( is_wp_error( $result ) ) return new WP_REST_Response( ['success' => false, 'message' => $result->get_error_message()], 500 );

        // Envia PUSH silencioso para atualizar painéis
        if ( class_exists('Nativa_OneSignal') ) {
            Nativa_OneSignal::send( "Update Mesa", ['type' => 'update_session', 'id' => $session_id] );
        }

        return new WP_REST_Response( ['success' => true, 'item_id' => $result, 'session_id' => $session_id], 200 );
    }

    public function get_session_data( $request ) {
        $session_id = $request->get_param( 'session_id' );
        if ( ! $session_id ) return new WP_REST_Response( ['success' => false, 'message' => 'ID inválido'], 400 );

        $session_model = new Nativa_Session();
        $session = $session_model->get( $session_id );
        if (!$session) return new WP_REST_Response( ['success' => false, 'message' => 'Sessão não encontrada'], 404 );

        $accounts = ['Principal'];
        if ( !empty($session->accounts_json) ) {
            $decoded = json_decode( $session->accounts_json, true );
            $accounts = is_array($decoded) ? $decoded : ['Principal'];
        }

        $order_model = new Nativa_Order_Item();
        $raw_items = $order_model->get_items_by_session( $session_id );
        
        $formatted_items = [];
        $grand_total = 0;
        $items_by_account = array_fill_keys( $accounts, [] );

        foreach ( $raw_items as $item ) {
            $modifiers = []; 
            $modifiers_total = 0;
            if ( ! empty( $item->modifiers_json ) ) {
                $decoded = json_decode( $item->modifiers_json, true );
                if ( is_array( $decoded ) ) {
                    $modifiers = $decoded;
                    foreach ( $modifiers as $mod ) {
                        if ( isset( $mod['price'] ) ) $modifiers_total += (float) $mod['price'];
                    }
                }
            }
            
            $unit_final_price = (float) $item->unit_price + $modifiers_total;
            $line_total = $unit_final_price * (int) $item->quantity;
            if ( $item->status !== 'cancelled' ) $grand_total += $line_total;

            $item_data = array(
                'id' => $item->id, 
                'name' => $item->product_name, 
                'qty' => (int) $item->quantity,
                'price' => (float) $item->unit_price, 
                'modifiers' => $modifiers, 
                'line_total' => $line_total, 
                'status' => $item->status,
                'sub_account' => $item->sub_account ?: 'Principal',
                'created_at' => $item->created_at
            );

            $formatted_items[] = $item_data;
            $acc_key = in_array( $item->sub_account, $accounts ) ? $item->sub_account : 'Principal';
            if (!isset($items_by_account[$acc_key])) $items_by_account[$acc_key] = [];
            $items_by_account[$acc_key][] = $item_data;
        }

        return new WP_REST_Response( array( 
            'success' => true, 
            'items' => $formatted_items,
            'accounts' => $accounts,
            'grouped_items' => $items_by_account,
            'total' => $grand_total 
        ), 200 );
    }

    public function get_session_summary( $request ) {
        global $wpdb;
        $session_id = $request->get_param( 'id' );
        $table_name = $wpdb->prefix . 'nativa_sessions';
        $accounts_json = $wpdb->get_var( $wpdb->prepare( "SELECT accounts_json FROM $table_name WHERE id = %d", $session_id ) );
        
        $accounts = ['Principal'];
        if ( $accounts_json ) {
            $decoded = json_decode( $accounts_json, true );
            if ( is_array( $decoded ) ) $accounts = $decoded;
        }
        
        $formatted_accounts = array_map( function($name) { return array( 'name' => $name ); }, $accounts );

        return new WP_REST_Response( array( 'success' => true, 'session' => array( 'id' => $session_id, 'accounts' => $formatted_accounts ) ), 200 );
    }

    public function create_account( $request ) {
        $params = $request->get_params();
        $session_id = $params['session_id'];
        $name = trim( $params['name'] );
        $is_command = isset($params['is_command']) && $params['is_command'];
        
        if ( ! $session_id || ! $name ) return new WP_REST_Response( ['success' => false, 'message' => 'Nome obrigatório.'], 400 );

        $session_model = new Nativa_Session();
        if ( $is_command ) {
            $in_use_session = $session_model->is_command_in_use( $name, $session_id );
            if ( $in_use_session ) return new WP_REST_Response( ['success' => false, 'message' => "Comanda $name em uso na mesa #$in_use_session."], 400 );
        }

        $new_accounts = $session_model->add_account( $session_id, $name );
        return $new_accounts ? new WP_REST_Response( ['success' => true, 'accounts' => $new_accounts], 200 ) : new WP_REST_Response( ['success' => false, 'message' => 'Erro ao criar conta.'], 500 );
    }

    public function transfer_items( $request ) {
        $params = $request->get_params();
        $order_model = new Nativa_Order_Item();
        $result = $order_model->transfer_items( $params['item_ids'], isset($params['target_session_id']) ? $params['target_session_id'] : null, $params['target_account'] );
        
        return $result !== false 
            ? new WP_REST_Response( ['success' => true, 'message' => 'Itens transferidos.'], 200 )
            : new WP_REST_Response( ['success' => false, 'message' => 'Erro ao transferir.'], 500 );
    }

    public function cancel_item_endpoint( $request ) {
        global $wpdb;
        $item_id = $request->get_param('item_id');
        $updated = $wpdb->update( $wpdb->prefix . 'nativa_order_items', ['status' => 'cancelled', 'updated_at' => current_time('mysql')], ['id' => $item_id] );
        return $updated !== false ? new WP_REST_Response(['success'=>true], 200) : new WP_REST_Response(['success'=>false], 500);
    }

    public function void_session_endpoint( $request ) {
        global $wpdb;
        $session_id = $request->get_param('session_id');
        $updated = $wpdb->update( $wpdb->prefix . 'nativa_sessions', ['status' => 'closed', 'closed_at' => current_time('mysql')], ['id' => $session_id] );
        return $updated !== false ? new WP_REST_Response(['success'=>true], 200) : new WP_REST_Response(['success'=>false], 500);
    }
}