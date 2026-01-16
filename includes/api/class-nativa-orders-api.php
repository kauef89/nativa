<?php
/**
 * API de Pedidos e Itens da Sessão
 * Responsável por:
 * 1. Adicionar itens à mesa/sessão (POST /add-item)
 * 2. Calcular a conta e entregar o extrato formatado (GET /session-items)
 * 3. Cancelar itens (POST /cancel-item)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Orders_API {

    public function register_routes() {
        // Rota para adicionar item (Usada pelo Carrinho)
        register_rest_route( 'nativa/v2', '/add-item', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'add_item_to_session' ),
            'permission_callback' => '__return_true',
        ) );

        // Rota para buscar itens da sessão (Usada pelo Extrato/OrderSummary)
        register_rest_route( 'nativa/v2', '/session-items', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_session_items' ),
            'permission_callback' => '__return_true',
        ) );

        // Rota para Cancelar Item
        register_rest_route( 'nativa/v2', '/cancel-item', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'cancel_item_endpoint' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * GET: Busca itens, formata e calcula totais
     */
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

    /**
     * POST: Adiciona item à sessão
     */
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

    /**
     * POST: Cancelar Item
     */
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
}