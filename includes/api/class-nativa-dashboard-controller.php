<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Dashboard_Controller {

    public function register_routes() {
        // Listagem de Pedidos (Kanban)
        register_rest_route( 'nativa/v2', '/orders', [
            'methods' => 'GET', 'callback' => [ $this, 'get_orders' ], 'permission_callback' => '__return_true',
        ]);
        
        // Detalhes do Pedido (Usado pelo App do Cliente e pelo Admin)
        register_rest_route( 'nativa/v2', '/order-details/(?P<id>\d+)', [
            'methods' => 'GET', 'callback' => [ $this, 'get_order_details_endpoint' ], 'permission_callback' => '__return_true',
        ]);
        
        // Atualizar Status (Kanban)
        register_rest_route( 'nativa/v2', '/update-order-status', [
            'methods' => 'POST', 'callback' => [ $this, 'update_order_status' ], 'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'nativa/v2', '/update-order-address', [
            'methods' => 'POST', 'callback' => [ $this, 'update_order_address' ], 'permission_callback' => '__return_true',
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
            
            // Filtra pedidos finalizados/cancelados da visão principal (opcional)
            if ( in_array( $status_slug, ['finalizado', 'cancelado', 'trash', 'entregue', 'wc-completed', 'wc-cancelled'] ) ) continue;

            $status_label = 'Novo';
            if ( $post->post_type === 'nativa_pedido' ) {
                $terms = wp_get_post_terms( $order_id, 'nativa_order_status' );
                if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                    $status_label = $terms[0]->name;
                    $status_slug  = $terms[0]->slug;
                }
            } else {
                // Compatibilidade WooCommerce
                $status_label = wc_get_order_status_name($status_slug);
            }

            $client_name = get_post_meta( $order_id, 'pedido_nome_cliente', true ) ?: 'Cliente #' . $order_id;
            $total = get_post_meta( $order_id, 'pedido_total_final', true ) ?: get_post_meta($order_id, '_order_total', true);
            $phone = get_post_meta($order_id, 'pedido_whatsapp_cliente', true) ?: get_post_meta($order_id, '_billing_phone', true);

            // Resumo do endereço
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
     * Detalhes COMPLETOS do Pedido (Corrige o erro 'undefined subtotal')
     */
    public function get_order_details_endpoint( $request ) {
        $order_id = $request->get_param('id');
        $post = get_post( $order_id );
        
        if ( ! $post ) return new WP_REST_Response(['success' => false, 'message' => 'Pedido não encontrado'], 404);

        // 1. Status
        $terms = wp_get_post_terms( $order_id, 'nativa_order_status' );
        $status_label = !empty($terms) && !is_wp_error($terms) ? $terms[0]->name : 'Novo';

        // 2. Itens (Lógica Blindada)
        $json_itens = get_post_meta($order_id, 'pedido_itens_json', true);
        $raw_items = $json_itens ? json_decode($json_itens, true) : [];
        $items = [];

        if ( is_array($raw_items) ) {
            foreach ( $raw_items as $item ) {
                // Recupera valores salvos ou usa 0
                // IMPORTANTE: line_total já vem calculado corretamente do place-web-order
                $line_total = isset($item['line_total']) ? (float)$item['line_total'] : 0;
                $unit_price = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
                
                // Fallback para pedidos antigos (V1) que não tinham line_total salvo
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

        // 3. Totais
        $subtotal = (float) get_post_meta($order_id, 'pedido_subtotal', true);
        $fee      = (float) get_post_meta($order_id, 'pedido_taxa_entrega', true);
        $total    = (float) get_post_meta($order_id, 'pedido_total_final', true);

        // 4. Endereço / Entrega
        $tipo_servico = get_post_meta($order_id, 'pedido_tipo_servico', true);
        $address_str = 'Balcão / Retirada';
        
        $addr_json = get_post_meta( $order_id, 'delivery_address_json', true );
        if ($addr_json) {
            $d = json_decode($addr_json, true);
            $address_str = "{$d['street']}, {$d['number']}";
            if (!empty($d['district'])) $address_str .= " - {$d['district']}";
        }

        // 5. Pagamento
        $payment_method = get_post_meta($order_id, 'pedido_metodo_pagamento', true);
        $change_for = get_post_meta($order_id, 'pedido_troco_para', true);
        
        // Estrutura FINAL
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

        // Mapeamento de slugs do frontend para slugs da taxonomia
        $term_map = [
            'novo'       => 'novo',
            'preparando' => 'preparando', // ou 'em-preparo'
            'entrega'    => 'em-rota',    // ou 'saiu-para-entrega'
            'concluido'  => 'concluido',
            'cancelado'  => 'cancelado'
        ];
        
        $slug = isset($term_map[$status]) ? $term_map[$status] : $status;

        $result = wp_set_object_terms( $order_id, $slug, 'nativa_order_status' );
        if ( is_wp_error($result) ) return new WP_REST_Response(['success'=>false, 'message'=>'Erro ao salvar termo'], 500);

        // Gatilho opcional para OneSignal aqui
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

        // Atualiza JSON V2
        update_post_meta($order_id, 'delivery_address_json', json_encode($address));

        // Atualiza ACF V1 (Compatibilidade)
        $acf_address = [
            'pedido_rua'        => $address['street'] ?? '',
            'pedido_numero'     => $address['number'] ?? '',
            'pedido_complemento'=> $address['complement'] ?? $address['reference'] ?? '',
            'pedido_bairro'     => $address['district'] ?? '',
        ];
        if(function_exists('update_field')) {
            update_field('pedido_endereco', $acf_address, $order_id);
        }

        // Recalcula Taxa (Opcional - mas recomendado se mudou o bairro)
        // Por simplicidade agora, apenas salvamos. O ideal seria recalcular o total.
        
        return new WP_REST_Response(['success'=>true, 'message'=>'Endereço atualizado.'], 200);
    }
}