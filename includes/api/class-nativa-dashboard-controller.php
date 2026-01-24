<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Dashboard_Controller {

    public function register_routes() {
        // Rotas de Pedidos (Delivery / Kanban)
        register_rest_route( 'nativa/v2', '/orders', [ 'methods' => 'GET', 'callback' => [ $this, 'get_orders' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/order-details/(?P<id>\d+)', [ 'methods' => 'GET', 'callback' => [ $this, 'get_order_details_endpoint' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/update-order-status', [ 'methods' => 'POST', 'callback' => [ $this, 'update_order_status' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/update-order-address', [ 'methods' => 'POST', 'callback' => [ $this, 'update_order_address' ], 'permission_callback' => '__return_true' ]);

        // Rotas de Aprovação (Gerente / ActivityFeed)
        register_rest_route( 'nativa/v2', '/approvals', [
            'methods' => 'GET', 
            'callback' => [ $this, 'get_activity_logs' ], 
            'permission_callback' => function() { return current_user_can('nativa_manager') || current_user_can('administrator'); }
        ]);

        register_rest_route( 'nativa/v2', '/approve-request', [
            'methods' => 'POST', 
            'callback' => [ $this, 'process_approval' ], 
            'permission_callback' => function() { return current_user_can('nativa_manager') || current_user_can('administrator'); }
        ]);
    }

    /**
     * Lista pedidos resumidos para o Kanban
     */
    public function get_orders( $request ) {
        $args = [
            'post_type' => ['nativa_pedido', 'shop_order'],
            'post_status' => ['publish', 'wc-processing', 'wc-pending'],
            'posts_per_page' => 50,
            'orderby' => 'date', 'order' => 'DESC'
        ];

        $query = new WP_Query( $args );
        $orders = [];

        foreach ( $query->posts as $post ) {
            $order_id = $post->ID;
            $status_slug = $post->post_status;
            
            if ( in_array( $status_slug, ['finalizado', 'cancelado', 'trash', 'entregue', 'wc-completed', 'wc-cancelled'] ) ) continue;

            $status_label = 'Novo';
            if ( $post->post_type === 'nativa_pedido' ) {
                $terms = wp_get_post_terms( $order_id, 'nativa_order_status' );
                if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                    $status_label = $terms[0]->name;
                    $status_slug  = $terms[0]->slug;
                }
            } else {
                $status_label = wc_get_order_status_name($status_slug);
            }

            $client_name = get_post_meta( $order_id, 'pedido_nome_cliente', true ) ?: 'Cliente #' . $order_id;
            $total = get_post_meta( $order_id, 'pedido_total_final', true ) ?: get_post_meta($order_id, '_order_total', true);
            $phone = get_post_meta($order_id, 'pedido_whatsapp_cliente', true) ?: get_post_meta($order_id, '_billing_phone', true);

            $address = 'Retirada';
            $addr_json = get_post_meta( $order_id, 'delivery_address_json', true );
            if ($addr_json) {
                $d = json_decode($addr_json, true);
                if ($d) $address = "{$d['district']}, {$d['street']}";
            } else {
                $acf = get_post_meta( $order_id, 'pedido_endereco', true );
                if (is_array($acf) && !empty($acf['pedido_bairro'])) $address = $acf['pedido_bairro'];
            }

            $created_time = strtotime( $post->post_date );
            $elapsed_mins = round( ( time() - $created_time ) / 60 );
            $elapsed_string = $elapsed_mins > 60 
                ? floor($elapsed_mins/60) . 'h ' . ($elapsed_mins%60) . 'm' 
                : $elapsed_mins . ' min';

            $orders[] = [
                'id' => $order_id,
                'client' => $client_name,
                'phone' => $phone,
                'status' => ucfirst( $status_label ),
                'status_slug' => $status_slug,
                'total' => (float) $total,
                'time_elapsed' => $elapsed_string,
                'address' => $address
            ];
        }

        return new WP_REST_Response( ['success' => true, 'orders' => $orders], 200 );
    }

    /**
     * Detalhes COMPLETOS do Pedido
     */
    public function get_order_details_endpoint( $request ) {
        $order_id = $request->get_param('id');
        $post = get_post( $order_id );
        
        if ( ! $post ) return new WP_REST_Response(['success' => false, 'message' => 'Pedido não encontrado'], 404);

        $terms = wp_get_post_terms( $order_id, 'nativa_order_status' );
        $status_label = !empty($terms) && !is_wp_error($terms) ? $terms[0]->name : 'Novo';

        $json_itens = get_post_meta($order_id, 'pedido_itens_json', true);
        $raw_items = $json_itens ? json_decode($json_itens, true) : [];
        $items = [];

        if ( is_array($raw_items) ) {
            foreach ( $raw_items as $item ) {
                $line_total = isset($item['line_total']) ? (float)$item['line_total'] : 0;
                $unit_price = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
                
                if ( $line_total == 0 && $unit_price > 0 ) {
                    $qty = (int) ($item['qty'] ?? 1);
                    $line_total = $unit_price * $qty;
                }

                $items[] = [
                    'name' => $item['name'] ?? 'Item',
                    'qty'  => (int) ($item['qty'] ?? 1),
                    'unit_price' => $unit_price,
                    'line_total' => $line_total,
                    'modifiers'  => $item['modifiers'] ?? []
                ];
            }
        }

        $subtotal = (float) get_post_meta($order_id, 'pedido_subtotal', true);
        $fee      = (float) get_post_meta($order_id, 'pedido_taxa_entrega', true);
        $total    = (float) get_post_meta($order_id, 'pedido_total_final', true);

        $tipo_servico = get_post_meta($order_id, 'pedido_tipo_servico', true);
        $address_str = 'Balcão / Retirada';
        
        $addr_json = get_post_meta( $order_id, 'delivery_address_json', true );
        if ($addr_json) {
            $d = json_decode($addr_json, true);
            $address_str = "{$d['street']}, {$d['number']}";
            if (!empty($d['district'])) $address_str .= " - {$d['district']}";
        }

        $payment_method = get_post_meta($order_id, 'pedido_metodo_pagamento', true);
        $change_for = get_post_meta($order_id, 'pedido_troco_para', true);
        
        $data = [
            'id' => $order_id,
            'date' => get_the_date('d/m/Y H:i', $order_id),
            'status' => $status_label,
            'modality' => $tipo_servico === 'delivery' ? 'Entrega' : 'Retirada',
            'customer' => [
                'name' => get_post_meta($order_id, 'pedido_nome_cliente', true),
                'phone' => get_post_meta($order_id, 'pedido_whatsapp_cliente', true)
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => $subtotal,
                'fee'      => $fee,
                'discount' => 0,
                'total'    => $total
            ],
            'delivery' => [
                'address' => $address_str,
                'driver'  => get_post_meta($order_id, 'pedido_entregador_nome', true)
            ],
            'payment' => [
                'method' => ucfirst($payment_method),
                'change' => $change_for
            ]
        ];

        return new WP_REST_Response(['success' => true] + $data, 200);
    }

    /**
     * Atualiza status (Ação do Kanban)
     */
    public function update_order_status( $request ) {
        $params = $request->get_json_params();
        $order_id = intval($params['order_id'] ?? 0);
        $status   = sanitize_text_field($params['status'] ?? '');

        if ( ! $order_id || ! $status ) return new WP_REST_Response(['success'=>false, 'message'=>'Dados inválidos'], 400);

        $term_map = [
            'novo'       => 'novo',
            'preparando' => 'preparando', 
            'entrega'    => 'em-rota',    
            'concluido'  => 'concluido',
            'cancelado'  => 'cancelado'
        ];
        
        $slug = isset($term_map[$status]) ? $term_map[$status] : $status;

        $result = wp_set_object_terms( $order_id, $slug, 'nativa_order_status' );
        if ( is_wp_error($result) ) return new WP_REST_Response(['success'=>false, 'message'=>'Erro ao salvar termo'], 500);

        if ( class_exists('Nativa_OneSignal') ) {
            Nativa_OneSignal::send("Pedido #$order_id atualizado para " . ucfirst($status), ['type'=>'order_update', 'id'=>$order_id]);
        }

        return new WP_REST_Response(['success'=>true, 'new_status'=>$slug], 200);
    }

    public function update_order_address( $request ) {
        $params = $request->get_json_params();
        $order_id = intval($params['order_id'] ?? 0);
        $address  = $params['address'] ?? [];

        if ( !$order_id || empty($address) ) return new WP_REST_Response(['success'=>false, 'message'=>'Dados inválidos'], 400);

        update_post_meta($order_id, 'delivery_address_json', json_encode($address));

        $acf_address = [
            'pedido_rua'        => $address['street'] ?? '',
            'pedido_numero'     => $address['number'] ?? '',
            'pedido_complemento'=> $address['complement'] ?? $address['reference'] ?? '',
            'pedido_bairro'     => $address['district'] ?? '',
        ];
        if(function_exists('update_field')) {
            update_field('pedido_endereco', $acf_address, $order_id);
        }
        
        return new WP_REST_Response(['success'=>true, 'message'=>'Endereço atualizado.'], 200);
    }

    /**
     * Lista Solicitações Pendentes E Histórico Recente (Activity Feed)
     */
    public function get_activity_logs() {
        global $wpdb;
        $table = $wpdb->prefix . 'nativa_session_logs';
        
        // Busca Pendentes (Prioridade) + Últimos 30 Processados
        $sql = "SELECT * FROM $table 
                ORDER BY (status = 'pending') DESC, created_at DESC 
                LIMIT 30";

        $results = $wpdb->get_results( $sql );
        
        foreach ($results as $row) {
            $row->meta = json_decode($row->meta_json, true);
        }

        return new WP_REST_Response(['success' => true, 'requests' => $results], 200);
    }

/**
     * Processa a decisão com Segurança (Transação + PIN)
     */
    public function process_approval($request) {
        global $wpdb;
        $params = $request->get_json_params();
        $log_id = $params['log_id'];
        $action = $params['action']; 
        $pin    = isset($params['pin']) ? sanitize_text_field($params['pin']) : '';

        $user = wp_get_current_user();
        $approver_name = $user->first_name ?: $user->display_name;

        // Validação de PIN
        $saved_pin = get_user_meta($user->ID, 'nativa_access_pin', true);
        if ( empty($saved_pin) || $saved_pin !== $pin ) {
            return new WP_REST_Response(['success'=>false, 'message'=>'PIN inválido.'], 403);
        }

        $log_table  = $wpdb->prefix . 'nativa_session_logs';
        $item_table = $wpdb->prefix . 'nativa_order_items';

        $log = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $log_table WHERE id = %d", $log_id) );
        if (!$log || $log->status !== 'pending') {
            return new WP_REST_Response(['success'=>false, 'message'=>'Solicitação já processada.'], 400);
        }

        $meta = json_decode($log->meta_json, true);
        $new_status_log = ($action === 'approve') ? 'approved' : 'rejected';

        // --- INÍCIO DA TRANSAÇÃO ATÔMICA ---
        $wpdb->query('START TRANSACTION');

        try {
            if ($log->type === 'cancel') {
                $item_id = $meta['item_id'];
                if ($action === 'approve') {
                    $wpdb->update($item_table, ['status' => 'cancelled'], ['id' => $item_id]);
                } else {
                    // Se rejeitar cancelamento, volta para pending ou printed? 
                    // Vamos assumir pending para segurança, ou recuperar o status anterior seria ideal, 
                    // mas pending garante visibilidade.
                    $wpdb->update($item_table, ['status' => 'pending'], ['id' => $item_id]); 
                }
            } 
            elseif ($log->type === 'swap') {
                $old_id = $meta['old_id'];
                $new_id = $meta['new_id'];
                
                if ($action === 'approve') {
                    // 1. ATIVAR NOVO ITEM (MUDANÇA: status 'pending')
                    // Isso garante que ele apareça na comanda imediatamente.
                    $wpdb->update($item_table, ['status' => 'pending'], ['id' => $new_id]);
                    
                    // 2. CANCELAR ANTIGO
                    $wpdb->update($item_table, ['status' => 'cancelled'], ['id' => $old_id]);
                } else {
                    // Rejeitou a troca:
                    $wpdb->update($item_table, ['status' => 'pending'], ['id' => $old_id]); // Restaura antigo
                    $wpdb->update($item_table, ['status' => 'cancelled'], ['id' => $new_id]); // Mata novo
                }
            }

            // Atualiza o Log
            $wpdb->update(
                $log_table, 
                ['status' => $new_status_log, 'approver_name' => $approver_name], 
                ['id' => $log_id]
            );

            $wpdb->query('COMMIT');

            if ( class_exists('Nativa_OneSignal') ) {
                $msg = ($action === 'approve') ? "Aprovado por $approver_name" : "Negado por $approver_name";
                Nativa_OneSignal::send($msg, ['type' => 'request_processed', 'session_id' => $log->session_id]);
            }

            return new WP_REST_Response(['success' => true], 200);

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_REST_Response(['success'=>false, 'message'=>'Erro no banco de dados.'], 500);
        }
    }
}