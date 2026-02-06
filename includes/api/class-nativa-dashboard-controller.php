<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Dashboard_Controller {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/orders', [ 'methods' => 'GET', 'callback' => [ $this, 'get_orders' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/order-details/(?P<id>\d+)', [ 'methods' => 'GET', 'callback' => [ $this, 'get_order_details_endpoint' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/update-order-status', [ 'methods' => 'POST', 'callback' => [ $this, 'update_order_status' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/update-order-address', [ 'methods' => 'POST', 'callback' => [ $this, 'update_order_address' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/update-order-mode', [ 'methods' => 'POST', 'callback' => [ $this, 'update_order_mode' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/approvals', [ 'methods' => 'GET', 'callback' => [ $this, 'get_activity_logs' ], 'permission_callback' => function() { return current_user_can('nativa_manager') || current_user_can('administrator'); } ]);
        register_rest_route( 'nativa/v2', '/approve-request', [ 'methods' => 'POST', 'callback' => [ $this, 'process_approval' ], 'permission_callback' => function() { return current_user_can('nativa_manager') || current_user_can('administrator'); } ]);
    }

    // --- REFATORADO: Usa o Order Service ---
    public function update_order_status( $request ) {
        $params = $request->get_json_params();
        $order_id = intval($params['order_id'] ?? 0);
        $status   = sanitize_text_field($params['status'] ?? '');
        $user_id  = get_current_user_id();
        
        $request_review = isset($params['request_review']) ? (bool)$params['request_review'] : false;

        if ( ! $order_id || ! $status ) {
            return new WP_REST_Response(['success'=>false, 'message'=>'Dados inválidos'], 400);
        }
        
        if ( class_exists('Nativa_Order_Service') ) {
            $result = Nativa_Order_Service::update_status( $order_id, $status, $user_id, $request_review );
            
            if ( is_wp_error($result) ) {
                return new WP_REST_Response(['success'=>false, 'message'=>$result->get_error_message()], 404);
            }

            return new WP_REST_Response(['success'=>true, 'new_status'=>$result], 200);
        }

        return new WP_REST_Response(['success'=>false, 'message'=>'Erro interno: Serviço indisponível.'], 500);
    }

    // --- GETTERS (Mantidos) ---
    public function get_orders( $request ) {
        global $wpdb;
        $orders = [];

        // 1. Pedidos do WooCommerce (Web)
        $args = [ 
            'post_type' => ['nativa_pedido', 'shop_order'], 
            'post_status' => ['publish', 'wc-processing', 'wc-pending', 'wc-completed', 'wc-cancelled'], 
            'posts_per_page' => 50, 
            'orderby' => 'date', 
            'order' => 'DESC',
            'date_query' => [
                [ 'after' => '24 hours ago' ] 
            ]
        ];
        
        $query = new WP_Query( $args );

        foreach ( $query->posts as $post ) {
            $order_id = $post->ID;
            $status_slug = $post->post_status;
            
            $status_label = 'Novo';
            if ( $post->post_type === 'nativa_pedido' ) {
                $terms = wp_get_post_terms( $order_id, 'nativa_order_status' );
                if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) { $status_label = $terms[0]->name; $status_slug  = $terms[0]->slug; }
            } else {
                $status_label = function_exists('wc_get_order_status_name') ? wc_get_order_status_name($status_slug) : $status_slug;
            }

            if (in_array($status_slug, ['wc-completed', 'entregue', 'finalizado'])) $status_slug = 'finished';
            if (in_array($status_slug, ['wc-cancelled', 'cancelado', 'trash'])) $status_slug = 'cancelled';

            $client_name = get_post_meta( $order_id, 'pedido_nome_cliente', true ) ?: 'Cliente Web';
            $total = get_post_meta( $order_id, 'pedido_total_final', true ) ?: get_post_meta($order_id, '_order_total', true);
            $phone = get_post_meta($order_id, 'pedido_whatsapp_cliente', true) ?: get_post_meta($order_id, '_billing_phone', true);
            $client_id = get_post_meta($order_id, '_customer_user', true) ?: 0;
            $modality = get_post_meta($order_id, 'pedido_tipo_servico', true) ?: 'delivery';
            
            $address = 'Retirada';
            $addr_json = get_post_meta( $order_id, 'delivery_address_json', true );
            if ( !empty($addr_json) ) { $d = json_decode($addr_json, true); if ($d && isset($d['street'])) $address = "{$d['district']}, {$d['street']}"; } 
            else { $acf = get_post_meta( $order_id, 'pedido_endereco', true ); if (is_array($acf) && !empty($acf['pedido_bairro'])) $address = $acf['pedido_bairro']; }

            $orders[] = [ 'id' => $order_id, 'source' => 'web', 'client' => $client_name, 'client_id' => (int)$client_id, 'phone' => $phone, 'status' => ucfirst( $status_label ), 'status_slug' => $status_slug, 'modality' => $modality, 'total' => (float) $total, 'time_elapsed' => $this->calc_time_elapsed(strtotime($post->post_date)), 'address' => $address ];
        }

        // 2. Pedidos do PDV (SQL)
        $sql_sessions = "
            SELECT s.*, 
            (SELECT SUM(line_total) FROM {$wpdb->prefix}nativa_order_items WHERE session_id = s.id AND status != 'cancelled') as items_total 
            FROM {$wpdb->prefix}nativa_sessions s 
            WHERE 
                s.status NOT IN ('closed', 'cancelled', 'finished') 
                OR 
                ( s.status IN ('closed', 'cancelled', 'finished') AND s.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) )
            ORDER BY s.created_at DESC
        ";
        
        $sessions = $wpdb->get_results($sql_sessions);

        foreach ($sessions as $sess) {
            $status_map = [ 
                'open' => ['slug' => 'open', 'label' => 'Aberto'], 
                'new' => ['slug' => 'new', 'label' => 'Novo'], 
                'pending_payment' => ['slug' => 'new', 'label' => 'Aguardando Pagamento'], 
                'preparing' => ['slug' => 'preparing', 'label' => 'Preparando'], 
                'delivering' => ['slug' => 'delivery', 'label' => 'Em Rota'], 
                'finished' => ['slug' => 'finished', 'label' => 'Concluído'], 
                'closed' => ['slug' => 'finished', 'label' => 'Concluído'], 
                'cancelled' => ['slug' => 'cancelled', 'label' => 'Cancelado'] 
            ];

            $st = $status_map[$sess->status] ?? ['slug' => 'new', 'label' => 'Novo'];
            
            $address_str = 'Retirada';
            if ($sess->type === 'delivery' && !empty($sess->delivery_address_json)) { $d = json_decode($sess->delivery_address_json, true); if ($d && isset($d['street'])) $address_str = "{$d['district']}, {$d['street']}"; } 
            elseif ($sess->type === 'table') { $address_str = "Mesa " . $sess->table_number; }

            $orders[] = [ 
                'id' => (int)$sess->id, 
                'source' => 'pos', 
                'client' => $sess->client_name ?: ($sess->type === 'table' ? "Mesa {$sess->table_number}" : 'Balcão'), 
                'client_id' => (int)$sess->client_id, 
                'phone' => '', 
                'status' => $st['label'], 
                'status_slug' => $st['slug'], 
                'modality' => $sess->type, 
                'total' => (float) $sess->items_total + (float)($sess->delivery_fee ?? 0), 
                'time_elapsed' => $this->calc_time_elapsed(strtotime($sess->created_at)), 
                'address' => $address_str 
            ];
        }

        usort($orders, function($a, $b) { return $b['id'] - $a['id']; });
        return new WP_REST_Response( ['success' => true, 'orders' => $orders], 200 );
    }

    public function get_order_details_endpoint( $request ) {
        $id = $request->get_param('id');
        global $wpdb;
        $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $id));
        
        if ($session) {
            $items_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}nativa_order_items WHERE session_id = %d AND status != 'cancelled'", $id));
            
            // ... (Lógica de Combo Helper mantida) ...
            if ( ! class_exists('Nativa_Combo_Helper') ) {
                $helper_path = NATIVA_PLUGIN_DIR . 'includes/classes/class-nativa-combo-helper.php';
                if ( file_exists( $helper_path ) ) require_once $helper_path;
            }
            if ( class_exists('Nativa_Combo_Helper') ) {
                $items_rows = Nativa_Combo_Helper::expand_items($items_rows, 'production');
            }

            $formatted_items = [];
            $subtotal = 0;
            
            foreach($items_rows as $i) {
                $line_total = isset($i->line_total) ? (float)$i->line_total : 0;
                $subtotal += $line_total;

                // --- LÓGICA DE ESTAÇÃO CORRIGIDA ---
                $station = 'default'; 
                $station_id = 'default'; 
                
                if ($i->product_id) {
                    // Tenta ID numérico primeiro (Meta)
                    $meta_val = get_post_meta($i->product_id, 'nativa_estacao_impressao', true);
                    if (is_numeric($meta_val) && $meta_val > 0) {
                        $station = (int)$meta_val;
                        $station_id = (int)$meta_val;
                    } 
                    // Tenta ACF se meta falhar
                    elseif (function_exists('get_field')) {
                        $field_val = get_field('nativa_estacao_impressao', $i->product_id);
                        if (is_object($field_val)) { $station = $field_val->ID; $station_id = $field_val->ID; }
                        elseif (is_numeric($field_val)) { $station = (int)$field_val; $station_id = (int)$field_val; }
                    }
                }
                // -----------------------------------

                $modifiers = [];
                if (!empty($i->modifiers_json)) {
                    $modifiers = json_decode($i->modifiers_json, true);
                }

                $requires_prep = true;
                if ($i->product_id) {
                    $meta_prep = get_post_meta($i->product_id, 'nativa_requires_preparation', true);
                    if ($meta_prep === '0') $requires_prep = false;
                }

                $formatted_items[] = [ 
                    'id' => (int)($i->id ?? 0), 
                    'product_id' => (int)$i->product_id, 
                    'name' => $i->product_name, 
                    'qty' => (int)$i->quantity, 
                    'line_total' => $line_total, 
                    'modifiers'  => $modifiers, 
                    'station' => $station, 
                    'station_id' => $station_id, 
                    'status' => $i->status ?? 'pending', 
                    'requires_prep' => $requires_prep 
                ];
            }

            $addr = $session->delivery_address_json ? json_decode($session->delivery_address_json, true) : null;
            $addr_str = $addr ? "{$addr['street']}, {$addr['number']} - {$addr['district']}" : 'Retirada';
            if ($session->type === 'table') { $addr_str = "Mesa {$session->table_number}"; }
            $phone = ''; if ($addr && !empty($addr['phone'])) $phone = $addr['phone'];
            if (empty($phone) && $session->client_id > 0) { $phone = get_user_meta($session->client_id, 'nativa_user_phone', true) ?: get_user_meta($session->client_id, 'billing_phone', true); }
            $fee = (float)($session->delivery_fee ?? 0);
            $total = $subtotal + $fee;
            
            return new WP_REST_Response([
                'success' => true, 'id' => $id, 'date' => date('d/m/Y H:i', strtotime($session->created_at)), 'status' => $session->status, 'modality' => $session->type,
                'customer' => ['name' => $session->client_name, 'phone' => $phone], 'items' => $formatted_items, 'totals' => ['subtotal' => $subtotal, 'fee' => $fee, 'discount' => 0, 'total' => $total],
                'delivery' => ['address' => $addr_str, 'driver' => ''], 'payment' => ['method' => 'Pendente', 'change' => 0], 'source' => 'pos', 'server_name' => 'Garçom'
            ], 200);
        }
        
        $post = get_post( $id );
        if ( ! $post ) return new WP_REST_Response(['success' => false, 'message' => 'Pedido não encontrado'], 404);
        
        $terms = wp_get_post_terms( $id, 'nativa_order_status' );
        $status_label = !empty($terms) && !is_wp_error($terms) ? $terms[0]->name : 'Novo';
        $subtotal = (float) get_post_meta($id, 'pedido_subtotal', true);
        $fee      = (float) get_post_meta($id, 'pedido_taxa_entrega', true);
        $total    = (float) get_post_meta($id, 'pedido_total_final', true);
        return new WP_REST_Response([
            'success' => true, 'id' => $id, 'date' => get_the_date('d/m/Y H:i', $id), 'status' => $status_label,
            'modality' => get_post_meta($id, 'pedido_tipo_servico', true) === 'delivery' ? 'Entrega' : 'Retirada',
            'customer' => [ 'name' => get_post_meta($id, 'pedido_nome_cliente', true) ],
            'items' => [], 'totals' => ['subtotal' => $subtotal, 'fee' => $fee, 'discount' => 0, 'total' => $total],
            'source' => 'web'
        ], 200);
    }

    // --- OUTRAS AÇÕES ---
    public function update_order_address( $request ) {
        $params = $request->get_json_params();
        $order_id = intval($params['order_id'] ?? 0);
        $address  = $params['address'] ?? [];
        if ( !$order_id || empty($address) ) return new WP_REST_Response(['success'=>false, 'message'=>'Dados inválidos'], 400);
        global $wpdb;
        $is_session = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $order_id));
        if ($is_session) { $wpdb->update( $wpdb->prefix . 'nativa_sessions', ['delivery_address_json' => json_encode($address)], ['id' => $order_id] ); } 
        else { update_post_meta($order_id, 'delivery_address_json', json_encode($address)); $acf_address = [ 'pedido_rua' => $address['street'] ?? '', 'pedido_numero' => $address['number'] ?? '', 'pedido_complemento'=> $address['complement'] ?? $address['reference'] ?? '', 'pedido_bairro' => $address['district'] ?? '' ]; if(function_exists('update_field')) update_field('pedido_endereco', $acf_address, $order_id); }
        return new WP_REST_Response(['success'=>true, 'message'=>'Endereço atualizado.'], 200);
    }

    public function update_order_mode( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        $order_id = intval($params['order_id'] ?? 0);
        $new_mode = sanitize_text_field($params['mode'] ?? ''); 
        $address  = $params['address'] ?? [];
        if ( !$order_id || !in_array($new_mode, ['delivery', 'pickup']) ) return new WP_REST_Response(['success'=>false, 'message'=>'Dados inválidos'], 400);
        $is_session = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $order_id));
        if ($is_session) {
            $data = ['type' => $new_mode];
            if ($new_mode === 'delivery') {
                $data['delivery_address_json'] = json_encode($address);
                $fee = 0;
                if ( !empty($address['district']) ) { $bairro = get_page_by_title($address['district'], OBJECT, 'nativa_bairro'); if ($bairro) { $fee = (float) get_field('taxa_entrega', $bairro->ID); } }
                $data['delivery_fee'] = $fee;
            } else { $data['delivery_fee'] = 0; }
            $wpdb->update($wpdb->prefix . 'nativa_sessions', $data, ['id' => $order_id]);
        } else {
            update_post_meta($order_id, 'pedido_tipo_servico', $new_mode);
            if ($new_mode === 'delivery') { update_post_meta($order_id, 'delivery_address_json', json_encode($address)); } else { update_post_meta($order_id, 'pedido_taxa_entrega', 0); }
        }
        return new WP_REST_Response(['success'=>true, 'message'=>"Pedido alterado para " . ucfirst($new_mode)], 200);
    }

    public function get_activity_logs() {
        global $wpdb;
        $table = $wpdb->prefix . 'nativa_session_logs';
        if ( $wpdb->get_var("SHOW TABLES LIKE '$table'") != $table ) return new WP_REST_Response(['success' => true, 'requests' => []], 200);
        $sql = "SELECT * FROM $table ORDER BY (status = 'pending') DESC, created_at DESC LIMIT 30";
        $results = $wpdb->get_results( $sql );
        if ( ! is_array( $results ) ) return new WP_REST_Response(['success' => true, 'requests' => []], 200);
        foreach ($results as $row) { $row->meta = !empty($row->meta_json) ? json_decode($row->meta_json, true) : []; }
        return new WP_REST_Response(['success' => true, 'requests' => $results], 200);
    }

    public function process_approval($request) {
        global $wpdb;
        $params = $request->get_json_params();
        $log_id = $params['log_id'];
        $action = $params['action']; 
        $pin    = isset($params['pin']) ? sanitize_text_field($params['pin']) : '';
        $user = wp_get_current_user();
        $approver_name = $user->first_name ?: $user->display_name;
        $saved_pin = get_user_meta($user->ID, 'nativa_access_pin', true);
        if ( empty($saved_pin) || $saved_pin !== $pin ) return new WP_REST_Response(['success'=>false, 'message'=>'PIN inválido.'], 403);
        $log = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}nativa_session_logs WHERE id = %d", $log_id) );
        if (!$log || $log->status !== 'pending') return new WP_REST_Response(['success'=>false, 'message'=>'Solicitação já processada.'], 400);
        $meta = json_decode($log->meta_json, true);
        $new_status_log = ($action === 'approve') ? 'approved' : 'rejected';
        $wpdb->query('START TRANSACTION');
        try {
            if ($log->type === 'cancel') {
                $item_id = $meta['item_id'];
                $status = ($action === 'approve') ? 'cancelled' : 'pending';
                $wpdb->update($wpdb->prefix . 'nativa_order_items', ['status' => $status], ['id' => $item_id]);
            } elseif ($log->type === 'swap') {
                $old_id = $meta['old_id']; $new_id = $meta['new_id'];
                if ($action === 'approve') {
                    $wpdb->update($wpdb->prefix . 'nativa_order_items', ['status' => 'pending'], ['id' => $new_id]);
                    $wpdb->update($wpdb->prefix . 'nativa_order_items', ['status' => 'cancelled'], ['id' => $old_id]);
                } else {
                    $wpdb->update($wpdb->prefix . 'nativa_order_items', ['status' => 'pending'], ['id' => $old_id]); 
                    $wpdb->update($wpdb->prefix . 'nativa_order_items', ['status' => 'cancelled'], ['id' => $new_id]); 
                }
            }
            $wpdb->update($wpdb->prefix . 'nativa_session_logs', ['status' => $new_status_log, 'approver_name' => $approver_name], ['id' => $log_id]);
            $wpdb->query('COMMIT');
            return new WP_REST_Response(['success' => true], 200);
        } catch (Exception $e) { $wpdb->query('ROLLBACK'); return new WP_REST_Response(['success'=>false, 'message'=>'Erro no banco.'], 500); }
    }

    private function calc_time_elapsed($timestamp) {
        $elapsed_mins = round( ( time() - $timestamp ) / 60 );
        return $elapsed_mins > 60 ? floor($elapsed_mins/60) . 'h ' . ($elapsed_mins%60) . 'm' : $elapsed_mins . ' min';
    }
}