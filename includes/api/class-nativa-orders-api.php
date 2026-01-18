<?php
/**
 * API de Pedidos (Gerenciamento de Itens e Dashboard Unificado)
 * * Responsabilidades:
 * 1. Operações de Mesa/Sessão (Add/Get/Cancel Items) -> Mantido do original
 * 2. Dashboard de Delivery (Listar Pedidos V1/V2) -> Novo (O Tradutor)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Orders_API {

    public function register_routes() {
        // --- ROTAS ORIGINAIS (Operação de Mesa) ---
        
        // 1. Adicionar item (Carrinho)
        register_rest_route( 'nativa/v2', '/add-item', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'add_item_to_session' ),
            'permission_callback' => '__return_true',
        ) );

        // 2. Extrato da Sessão
        register_rest_route( 'nativa/v2', '/session-items', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_session_items' ),
            'permission_callback' => '__return_true',
        ) );

        // 3. Cancelar Item
        register_rest_route( 'nativa/v2', '/cancel-item', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'cancel_item_endpoint' ),
            'permission_callback' => '__return_true',
        ) );

        // --- NOVA ROTA (Dashboard Delivery) ---
        
        // 4. Listar Pedidos Ativos (V1 + V2)
        register_rest_route( 'nativa/v2', '/orders', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_orders' ), // O "Tradutor"
            'permission_callback' => '__return_true',
        ) );
    }

    // =========================================================================
    //  PARTE 1: OPERAÇÕES DE ITENS (MANTIDO DO ORIGINAL)
    // =========================================================================

    public function get_session_items( $request ) {
        $session_id = $request->get_param( 'session_id' );

        if ( ! $session_id ) {
            return new WP_REST_Response( ['success' => false, 'message' => 'Sessão inválida'], 400 );
        }

        $order_model = new Nativa_Order_Item();
        $raw_items = $order_model->get_items_by_session( $session_id );

        $formatted_items = array();
        $grand_total = 0;

        foreach ( $raw_items as $item ) {
            // 1. Decodifica os modificadores
            $modifiers = array();
            $modifiers_total = 0;

            if ( ! empty( $item->modifiers_json ) ) {
                $decoded = json_decode( $item->modifiers_json, true );
                if ( is_array( $decoded ) ) {
                    $modifiers = $decoded;
                    foreach ( $modifiers as $mod ) {
                        if ( isset( $mod['price'] ) ) {
                            $modifiers_total += (float) $mod['price'];
                        }
                    }
                }
            }

            // 2. Calcula Preços
            $unit_price_base = (float) $item->unit_price;
            $unit_final_price = $unit_price_base + $modifiers_total;
            $line_total = $unit_final_price * (int) $item->quantity;

            // 3. Adiciona ao Total Geral (SE NÃO ESTIVER CANCELADO)
            if ( $item->status !== 'cancelled' ) {
                $grand_total += $line_total;
            }

            // 4. Formata
            $formatted_items[] = array(
                'id'          => $item->id,
                'name'        => $item->product_name,
                'qty'         => (int) $item->quantity,
                'price'       => $unit_price_base,
                'modifiers'   => $modifiers,
                'line_total'  => $line_total,
                'final_total' => $line_total,
                'status'      => $item->status,
                'created_at'  => $item->created_at
            );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'items'   => $formatted_items,
            'total'   => $grand_total
        ), 200 );
    }

    public function add_item_to_session( $request ) {
        $params = $request->get_params();

        if ( empty( $params['session_id'] ) || empty( $params['product_id'] ) ) {
            return new WP_REST_Response( ['success' => false, 'message' => 'Dados incompletos.'], 400 );
        }

        $order_model = new Nativa_Order_Item();
        
        $result = $order_model->add_item(
            $params['session_id'],
            $params['product_id'],
            isset($params['qty']) ? $params['qty'] : 1,
            isset($params['modifiers']) ? $params['modifiers'] : null,
            isset($params['sub_account']) ? $params['sub_account'] : 'Principal'
        );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( ['success' => false, 'message' => $result->get_error_message()], 500 );
        }

        return new WP_REST_Response( ['success' => true, 'item_id' => $result], 200 );
    }

    public function cancel_item_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_params();
        
        $item_id = isset($params['item_id']) ? intval($params['item_id']) : 0;
        $reason  = isset($params['reason']) ? sanitize_text_field($params['reason']) : 'Cancelado pelo usuário';

        if ( !$item_id ) {
            return new WP_REST_Response( ['success' => false, 'message' => 'ID do item obrigatório.'], 400 );
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'nativa_order_items',
            array( 
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'updated_at' => current_time('mysql')
            ),
            array( 'id' => $item_id )
        );

        if ( $updated === false ) {
            return new WP_REST_Response( ['success' => false, 'message' => 'Erro ao cancelar item.'], 500 );
        }

        return new WP_REST_Response( ['success' => true, 'message' => 'Item cancelado.'], 200 );
    }

    // =========================================================================
    //  PARTE 2: O TRADUTOR V1/V2 (DASHBOARD)
    // =========================================================================

    /**
     * Busca pedidos ativos (V1 Legacy + V2 + Woo)
     */
    public function get_orders( $request ) {
        // Status que consideramos "Ativos" para o Dashboard
        $args = array(
            'post_type'      => array( 'nativa_order', 'nativa_pedido', 'shop_order' ),
            'post_status'    => array( 'publish', 'wc-processing', 'wc-pending', 'wc-on-hold' ), 
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC'
        );

        $query = new WP_Query( $args );
        $orders = array();

        foreach ( $query->posts as $post ) {
            $order_id = $post->ID;
            $type = $post->post_type;

            // --- 1. NORMALIZAÇÃO DE DADOS (O Tradutor) ---
            
            // Cliente
            $client_name = get_post_meta( $order_id, 'client_name', true ); // V2
            if ( ! $client_name ) $client_name = get_post_meta( $order_id, 'pedido_nome_cliente', true ); // V1
            if ( ! $client_name ) $client_name = get_post_meta( $order_id, '_billing_first_name', true ); // Woo
            if ( ! $client_name ) $client_name = 'Cliente #' . $order_id;

            // Total
            $total = get_post_meta( $order_id, 'order_total', true ); // V2
            if ( ! $total ) $total = get_post_meta( $order_id, 'pedido_total_final', true ); // V1
            if ( ! $total ) $total = get_post_meta( $order_id, '_order_total', true ); // Woo

            // Status (A parte complicada)
            $status_label = 'Novo';
            $status_slug = $post->post_status;

            if ( $type === 'nativa_pedido' ) {
                // V1: Busca na Taxonomia
                $terms = wp_get_post_terms( $order_id, 'nativa_order_status' );
                if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                    $status_label = $terms[0]->name; // Ex: "Finalizado", "Pendente"
                    $status_slug  = $terms[0]->slug;
                }
            } else {
                // V2/Woo: Formata o Post Status
                $status_label = $this->format_status( $status_slug );
            }

            // Endereço
            $address = get_post_meta( $order_id, 'delivery_address_simple', true ); // V2
            if ( ! $address && $type === 'nativa_pedido' ) {
                // V1 guardava endereço no meta 'pedido_endereco' (array) ou string
                $addr_meta = get_post_meta( $order_id, 'pedido_endereco', true );
                if ( is_array( $addr_meta ) ) {
                    $bairro = $addr_meta['pedido_bairro'] ?? '';
                    $address = $bairro ? "Bairro: $bairro" : 'Entrega';
                }
            }

            // Tempo decorrido
            $created_time = strtotime( $post->post_date );
            $elapsed_mins = round( ( time() - $created_time ) / 60 );

            // Filtro Lógico: Ignorar pedidos muito velhos ou finalizados da listagem ativa
            if ( in_array( $status_slug, ['finalizado', 'cancelado', 'trash', 'wc-completed', 'wc-cancelled'] ) ) {
                continue; 
            }

            $orders[] = array(
                'id'           => $order_id,
                'legacy_id'    => ($type === 'nativa_pedido') ? true : false,
                'client'       => $client_name,
                'status'       => ucfirst( $status_label ),
                'total'        => (float) $total,
                'time_elapsed' => $elapsed_mins . ' min',
                'type'         => 'delivery',
                'address'      => $address
            );
        }

        return new WP_REST_Response( array( 'success' => true, 'orders' => $orders ), 200 );
    }

    private function format_status( $slug ) {
        $map = array(
            'wc-processing' => 'Preparando',
            'wc-pending'    => 'Pendente',
            'wc-on-hold'    => 'Aguardando',
            'publish'       => 'Novo'
        );
        return isset($map[$slug]) ? $map[$slug] : ucfirst(str_replace('wc-', '', $slug));
    }
}