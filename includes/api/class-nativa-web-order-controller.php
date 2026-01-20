<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Web_Order_Controller {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/place-web-order', [
            'methods'  => 'POST',
            'callback' => [ $this, 'place_web_order' ],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // NOVAS ROTAS
        register_rest_route( 'nativa/v2', '/my-active-order', [
            'methods'  => 'GET', 'callback' => [ $this, 'get_active_order' ], 'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route( 'nativa/v2', '/cancel-my-order', [
            'methods'  => 'POST', 'callback' => [ $this, 'cancel_my_order' ], 'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route( 'nativa/v2', '/update-payment', [
            'methods'  => 'POST', 'callback' => [ $this, 'update_payment_method' ], 'permission_callback' => 'is_user_logged_in',
        ]);
    }

    public function place_web_order( $request ) {
        $user_id = get_current_user_id();
        $params  = $request->get_json_params();
        
        $items_raw = $params['items'] ?? [];
        $address   = $params['address'] ?? [];
        $payment   = $params['payment'] ?? [];
        $notes     = $params['notes'] ?? '';

        if ( empty($items_raw) ) return new WP_REST_Response(['success' => false, 'message' => 'Carrinho vazio.'], 400);

        // 1. RECALCULA PREÇOS (Segurança)
        $calculated_cart = $this->calculate_cart_totals( $items_raw );
        if ( is_wp_error($calculated_cart) ) return new WP_REST_Response(['success' => false, 'message' => $calculated_cart->get_error_message()], 400);

        $subtotal = $calculated_cart['subtotal'];
        $delivery_fee = isset($address['fee']) ? floatval($address['fee']) : 0;
        $final_total = ($subtotal + $delivery_fee);

        // 2. CRIA O POST
        $user_info = get_userdata($user_id);
        $client_name = get_user_meta($user_id, 'first_name', true) ?: $user_info->display_name;
        
        $order_id = wp_insert_post([
            'post_title'  => "Pedido Web de " . $client_name . " em " . current_time('d/m/Y H:i'),
            'post_type'   => 'nativa_pedido',
            'post_status' => 'publish',
            'post_author' => $user_id
        ]);

        if ( is_wp_error($order_id) ) return new WP_REST_Response(['success' => false, 'message' => 'Erro ao gravar pedido.'], 500);

        // 3. SALVA METADADOS
        update_post_meta($order_id, 'pedido_nome_cliente', $client_name);
        update_post_meta($order_id, 'pedido_cpf_cliente', get_user_meta($user_id, 'nativa_user_cpf', true));
        update_post_meta($order_id, 'pedido_whatsapp_cliente', get_user_meta($user_id, 'nativa_user_phone', true));
        
        if ( !empty($address) ) {
            update_post_meta($order_id, 'pedido_tipo_servico', 'delivery');
            $acf_address = [
                'pedido_rua'        => $address['street'] ?? '',
                'pedido_numero'     => $address['number'] ?? '',
                'pedido_complemento'=> $address['complement'] ?? '',
                'pedido_bairro'     => $address['district'] ?? '',
            ];
            update_field('pedido_endereco', $acf_address, $order_id);
            update_post_meta($order_id, 'delivery_address_json', json_encode($address));
        } else {
            update_post_meta($order_id, 'pedido_tipo_servico', 'pickup');
        }

        update_post_meta($order_id, 'pedido_subtotal', $subtotal);
        update_post_meta($order_id, 'pedido_taxa_entrega', $delivery_fee);
        update_post_meta($order_id, 'pedido_total_final', $final_total);
        update_post_meta($order_id, 'order_total', $final_total); // Legado
        update_post_meta($order_id, 'pedido_itens_json', json_encode($calculated_cart['items'], JSON_UNESCAPED_UNICODE));

        $method = $payment['method'] ?? 'dinheiro';
        update_post_meta($order_id, 'pedido_metodo_pagamento', $method);
        if ($method === 'dinheiro' && !empty($payment['change_for'])) {
            update_post_meta($order_id, 'pedido_troco_para', $payment['change_for']);
        }

        if ($notes) update_post_meta($order_id, 'pedido_observacoes', sanitize_textarea_field($notes));

        wp_set_object_terms($order_id, 'novo', 'nativa_order_status');

        if ( class_exists('Nativa_OneSignal') ) {
            Nativa_OneSignal::send("Novo Pedido Web #$order_id", ['type' => 'new_order', 'id' => $order_id]);
        }

        return new WP_REST_Response(['success' => true, 'order_id' => $order_id], 200);
    }

    /**
     * Busca o pedido ativo (último pedido)
     */
    public function get_active_order( $request ) {
        $user_id = get_current_user_id();
        
        $args = [
            'post_type' => 'nativa_pedido',
            'post_status' => 'publish',
            'author' => $user_id,
            'posts_per_page' => 1, // Pega apenas o ÚLTIMO
            'orderby' => 'date',
            'order' => 'DESC'
            // REMOVIDO: tax_query que excluía cancelados
        ];

        $query = new WP_Query($args);

        if ( empty($query->posts) ) {
            return new WP_REST_Response(['success' => true, 'order' => null], 200);
        }

        $post = $query->posts[0];
        $order_id = $post->ID;

        // Recupera Status
        $terms = wp_get_post_terms( $order_id, 'nativa_order_status' );
        $status_slug = !empty($terms) && !is_wp_error($terms) ? $terms[0]->slug : 'novo';
        $status_name = !empty($terms) && !is_wp_error($terms) ? $terms[0]->name : 'Novo';

        // LÓGICA DE EXIBIÇÃO:
        // Se estiver concluído ou cancelado há mais de 24h, retorna null (Empty State real).
        // Se for recente, retorna o pedido para mostrar a tela de Feedback/Cancelamento.
        $is_final = in_array($status_slug, ['concluido', 'cancelado', 'entregue']);
        if ( $is_final ) {
            $modified = strtotime($post->post_modified);
            // 24 horas = 86400 segundos
            if ( (time() - $modified) > 86400 ) {
                return new WP_REST_Response(['success' => true, 'order' => null], 200);
            }
        }

        $order = [
            'id' => $order_id,
            'status' => $status_slug,
            'status_label' => $status_name,
            'total' => (float) get_post_meta($order_id, 'pedido_total_final', true),
            'date' => get_the_date('d/m/Y H:i', $order_id),
            'payment_method' => get_post_meta($order_id, 'pedido_metodo_pagamento', true),
            'address' => get_post_meta($order_id, 'delivery_address_json', true) ? json_decode(get_post_meta($order_id, 'delivery_address_json', true)) : null
        ];

        return new WP_REST_Response(['success' => true, 'order' => $order], 200);
    }

    public function cancel_my_order( $request ) {
        $user_id = get_current_user_id();
        $order_id = $request->get_param('order_id');
        
        $post = get_post($order_id);
        if (!$post || $post->post_author != $user_id) return new WP_REST_Response(['success'=>false], 403);

        // Valida status (Só pode cancelar se Novo ou Pendente)
        $terms = wp_get_post_terms( $order_id, 'nativa_order_status' );
        $slug = $terms[0]->slug ?? '';
        
        if (!in_array($slug, ['novo', 'pendente', 'aguardando-pagamento'])) {
            return new WP_REST_Response(['success'=>false, 'message' => 'Pedido já está em preparo. Entre em contato.'], 400);
        }

        wp_set_object_terms($order_id, 'cancelado', 'nativa_order_status');
        
        return new WP_REST_Response(['success' => true], 200);
    }

    public function update_payment_method( $request ) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        $order_id = $params['order_id'];
        $method = $params['method'];
        $change = $params['change_for'] ?? 0;

        $post = get_post($order_id);
        if (!$post || $post->post_author != $user_id) return new WP_REST_Response(['success'=>false], 403);

        // Valida status (Não pode trocar se já saiu pra entrega)
        $terms = wp_get_post_terms( $order_id, 'nativa_order_status' );
        $slug = $terms[0]->slug ?? '';
        
        if (in_array($slug, ['em-rota', 'entregue', 'concluido'])) {
            return new WP_REST_Response(['success'=>false, 'message' => 'Pedido já saiu para entrega.'], 400);
        }

        update_post_meta($order_id, 'pedido_metodo_pagamento', $method);
        if ($method === 'dinheiro') {
            update_post_meta($order_id, 'pedido_troco_para', $change);
        } else {
            delete_post_meta($order_id, 'pedido_troco_para');
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    private function calculate_cart_totals( $items_raw ) {
        $items_clean = [];
        $subtotal = 0;

        foreach ( $items_raw as $item ) {
            $product_id = (int) $item['id'];
            $qty = (int) $item['qty'];
            
            $product_post = get_post($product_id);
            if ( !$product_post || $product_post->post_type !== 'nativa_produto' ) continue;

            // --- CORREÇÃO V1: Campos corretos do banco ---
            // 1. Preço Base
            // BLINDAGEM DE PREÇO: Tenta 3 chaves diferentes
            $price_base = (float) get_post_meta($product_id, 'produto_preco', true);
            
            if ( !$price_base ) {
                $price_base = (float) get_post_meta($product_id, 'price', true); // Chave alternativa
            }
            if ( !$price_base ) {
                $price_base = (float) get_post_meta($product_id, '_regular_price', true); // Chave WooCommerce
            }

            // Log de erro se continuar zero (veja no debug.log)
            if ( !$price_base ) {
                error_log("NATIVA ALERTA: Produto ID $product_id está com preço zero ou meta incorreto.");
            }
            
            // 2. Preço Promocional (Lógica: se existe e é maior que 0, usa ele)
            $price_promo = (float) get_post_meta($product_id, 'produto_preco_promocional', true);
            
            // Define preço inicial
            $current_price = ($price_promo > 0 && $price_promo < $price_base) ? $price_promo : $price_base;

            // 3. Adicionais
            $modifiers_total = 0;
            $modifiers_clean = [];
            
            if ( !empty($item['modifiers']) && is_array($item['modifiers']) ) {
                foreach ( $item['modifiers'] as $mod ) {
                    $mod_price = (float) ($mod['price'] ?? 0);
                    $modifiers_total += $mod_price;
                    
                    $modifiers_clean[] = [ 
                        'name' => sanitize_text_field($mod['name']), 
                        'price' => $mod_price, 
                        'qty' => $mod['qty'] ?? 1 
                    ];
                }
            }

            // 4. Totais da Linha
            // O preço unitário final é a soma do produto + soma dos adicionais unitários
            $unit_final = $current_price + $modifiers_total;
            
            // O total da linha é o unitário final x quantidade
            $line_total = $unit_final * $qty;
            
            $subtotal += $line_total;

            $items_clean[] = [
                'id' => $product_id,
                'name' => $product_post->post_title,
                'unit_price' => $unit_final, // Preço cheio (Base + Adicionais)
                'base_price' => $current_price, // Guardamos o base só por garantia
                'qty' => $qty,
                'line_total' => $line_total, // Nomenclatura padronizada
                'modifiers' => $modifiers_clean
            ];
        }

        if ( empty($items_clean) ) return new WP_Error('invalid_cart', 'Itens inválidos.');

        return [ 'items' => $items_clean, 'subtotal' => $subtotal ];
    }
}