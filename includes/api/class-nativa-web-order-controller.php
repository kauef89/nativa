<?php
/**
 * Controller Web V3.0 (Refatorado)
 * - Delega notificações e criação para os Services
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Web_Order_Controller {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/place-web-order', [
            'methods'  => 'POST',
            'callback' => [ $this, 'place_web_order' ],
            'permission_callback' => 'is_user_logged_in',
        ]);
        register_rest_route( 'nativa/v2', '/my-active-order', [ 'methods' => 'GET', 'callback' => [ $this, 'get_active_order' ], 'permission_callback' => 'is_user_logged_in' ]);
        register_rest_route( 'nativa/v2', '/cancel-my-order', [ 'methods' => 'POST', 'callback' => [ $this, 'cancel_my_order' ], 'permission_callback' => 'is_user_logged_in' ]);
        register_rest_route( 'nativa/v2', '/update-payment', [ 'methods' => 'POST', 'callback' => [ $this, 'update_payment_method' ], 'permission_callback' => 'is_user_logged_in' ]);
    }

    public function place_web_order( $request ) {
        $user_id = get_current_user_id();
        $params  = $request->get_json_params();
        
        $items_raw = $params['items'] ?? [];
        if ( empty($items_raw) ) return new WP_REST_Response(['success' => false, 'message' => 'Carrinho vazio.'], 400);

        // 1. Recalcula Carrinho
        $cart = $this->calculate_cart_totals( $items_raw );
        if ( is_wp_error($cart) ) return new WP_REST_Response(['success' => false, 'message' => $cart->get_error_message()], 400);

        // 2. Instancia Models
        $session_model = new Nativa_Session();
        $items_model   = new Nativa_Order_Item();
        $stock_model   = new Nativa_Stock();
        $loyalty_model = new Nativa_Loyalty();
        $session_log   = new Nativa_Session_Log();

        // 3. Prepara Dados
        $address = $params['address'] ?? null;
        $delivery_fee = 0;
        if ( $address && isset($address['fee']) ) $delivery_fee = floatval($address['fee']);
        $type = $address ? 'delivery' : 'pickup';
        if ($type === 'pickup') $delivery_fee = 0;
        
        $user_data = get_userdata($user_id);
        $client_name = $user_data->first_name ? $user_data->first_name . ' ' . $user_data->last_name : $user_data->display_name;

        $has_pix = false;
        $payments = $params['payments'] ?? [];
        foreach ($payments as $p) { if (($p['method'] ?? '') === 'pix_sicredi') $has_pix = true; }
        
        $session_data = [
            'name' => $client_name,
            'client_id' => $user_id,
            'address' => $address,
            'status' => $has_pix ? 'pending_payment' : 'new',
            'delivery_fee' => $delivery_fee,
            'payments' => $payments,
            'notes' => $params['notes'] ?? '' 
        ];

        // 4. Cria Sessão
        $session_id = $session_model->open( $type, null, $session_data );
        
        if ( $session_id ) {
            if ($address) $this->auto_save_user_address($user_id, $address);

            // Itens e Estoque
            foreach ($cart['items'] as $item) {
                $override_price = (!empty($item['is_reward'])) ? 0.00 : ($item['is_offer'] ? $item['unit_price'] : null);
                $items_model->add_item($session_id, $item['id'], $item['qty'], $item['modifiers'], 'Cliente Web', $override_price);
                $stock_model->adjust_stock($item['id'], $item['qty'] * -1, 'sale', $user_id, "Pedido Web #$session_id");
            }
            
            // Pontos (se não for puramente resgate)
            if ($cart['subtotal'] > 0) $loyalty_model->add_pending_points( $user_id, floor($cart['subtotal']), $session_id );

            // Log
            $final_total = $cart['subtotal'] + $delivery_fee; 
            $session_log->log($session_id, 'new_order', "Novo Pedido Web", ['total' => $final_total], 'completed');

            // --- REFATORADO: USA O SERVIÇO DE NOTIFICAÇÃO ---
            if ( class_exists('Nativa_Notification_Service') ) {
                Nativa_Notification_Service::notify_new_order( 
                    $session_id, 
                    $client_name, 
                    $final_total, 
                    $has_pix, 
                    $user_id 
                );
            }
        }

        return new WP_REST_Response([ 'success' => true, 'order_id' => $session_id, 'needs_payment' => $has_pix ], 200);
    }

    public function cancel_my_order( $request ) {
        $user_id = get_current_user_id();
        $order_id = (int) $request->get_param('order_id');
        global $wpdb;
        
        // Verifica propriedade
        $check = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}nativa_sessions WHERE id = %d AND client_id = %d", $order_id, $user_id));
        if (!$check) return new WP_REST_Response(['success'=>false, 'message'=>'Pedido não encontrado.'], 404);

        // --- REFATORADO: USA O ORDER SERVICE ---
        if ( class_exists('Nativa_Order_Service') ) {
            Nativa_Order_Service::update_status( $order_id, 'cancelled', $user_id );
            return new WP_REST_Response(['success' => true], 200);
        }

        return new WP_REST_Response(['success' => false, 'message' => 'Erro interno (Serviço indisponível).'], 500);
    }

    // ... (Métodos de leitura e auxiliares mantidos iguais) ...
    public function get_active_order( $request ) { $user_id = get_current_user_id(); global $wpdb; $sql = "SELECT * FROM {$wpdb->prefix}nativa_sessions WHERE client_id = %d AND status NOT IN ('closed', 'cancelled') ORDER BY id DESC LIMIT 1"; $session = $wpdb->get_row( $wpdb->prepare($sql, $user_id) ); if ( ! $session ) { return new WP_REST_Response(['success' => true, 'order' => null], 200); } $items_total = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(line_total) FROM {$wpdb->prefix}nativa_order_items WHERE session_id = %d AND status != 'cancelled'", $session->id )); $final_total = (float)$items_total + (float)$session->delivery_fee; $order_data = [ 'id' => $session->id, 'status' => $session->status, 'status_label' => $this->get_status_label($session->status), 'total' => $final_total, 'date' => date('d/m/Y H:i', strtotime($session->created_at)), 'type' => $session->type, 'address' => json_decode($session->delivery_address_json), 'payment_info' => json_decode($session->payment_info_json) ]; return new WP_REST_Response(['success' => true, 'order' => $order_data], 200); }
    public function update_payment_method( $request ) { $user_id = get_current_user_id(); $params = $request->get_json_params(); $order_id = (int) $params['order_id']; $payments = $params['payments'] ?? []; if (empty($payments) && !empty($params['method'])) { $payments = [[ 'method' => $params['method'], 'change_for' => $params['change_for'] ?? 0, 'amount' => 0 ]]; } global $wpdb; $session = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}nativa_sessions WHERE id = %d AND client_id = %d", $order_id, $user_id) ); if ( ! $session ) return new WP_REST_Response(['success'=>false], 404); if ( in_array($session->status, ['delivering', 'finished', 'closed']) ) { return new WP_REST_Response(['success'=>false, 'message' => 'Pedido já saiu para entrega.'], 400); } $wpdb->update("{$wpdb->prefix}nativa_sessions", ['payment_info_json' => json_encode($payments)], ['id' => $order_id]); return new WP_REST_Response(['success' => true], 200); }
    private function calculate_cart_totals( $items_raw ) { $subtotal = 0; $items_clean = []; foreach ( $items_raw as $item ) { $product_id = (int) $item['id']; $qty = (int) $item['qty']; $is_reward = isset($item['is_reward']) && $item['is_reward']; $points_cost = isset($item['points_cost']) ? intval($item['points_cost']) : 0; $is_offer = isset($item['is_offer']) && $item['is_offer']; $offer_source_id = isset($item['offer_source_id']) ? (int) $item['offer_source_id'] : 0; $product_post = get_post($product_id); if ( !$product_post || $product_post->post_type !== 'nativa_produto' ) continue; $price_base = (float) get_post_meta($product_id, 'produto_preco', true); $price_promo = (float) get_post_meta($product_id, 'produto_preco_promocional', true); $current_price = ($price_promo > 0 && $price_promo < $price_base) ? $price_promo : $price_base; if ( $is_offer && $offer_source_id ) { $offer_price = (float) get_field('preco_promocional', $offer_source_id); $offer_product_check = (int) get_field('produto_ofertado', $offer_source_id); if ( $offer_price > 0 && $offer_product_check === $product_id ) { $current_price = $offer_price; } else { $is_offer = false; } } $modifiers_total = 0; $modifiers_clean = []; if ( !empty($item['modifiers']) && is_array($item['modifiers']) ) { foreach ( $item['modifiers'] as $mod ) { $mod_price = (float) ($mod['price'] ?? 0); $modifiers_total += $mod_price; $modifiers_clean[] = [ 'name' => sanitize_text_field($mod['name']), 'price' => $mod_price, 'qty' => 1 ]; } } if ($is_reward) { $unit_final = 0 + $modifiers_total; } else { $unit_final = $current_price + $modifiers_total; } $line_total = $unit_final * $qty; $subtotal += $line_total; $items_clean[] = [ 'id' => $product_id, 'name' => $product_post->post_title, 'unit_price' => $unit_final, 'qty' => $qty, 'modifiers' => $modifiers_clean, 'is_reward' => $is_reward, 'points_cost' => $points_cost, 'is_offer' => $is_offer, 'offer_source_id' => $offer_source_id ]; } if ( empty($items_clean) ) return new WP_Error('invalid_cart', 'Itens inválidos.'); return [ 'items' => $items_clean, 'subtotal' => $subtotal ]; }
    private function auto_save_user_address( $user_id, $new_address ) { $saved = get_user_meta( $user_id, 'nativa_saved_addresses', true ); if ( ! is_array( $saved ) ) $saved = []; foreach ( $saved as $addr ) { if ( strcasecmp($addr['street'], $new_address['street']) === 0 && $addr['number'] == $new_address['number'] ) return; } $new_address['source'] = 'Recente'; array_unshift( $saved, $new_address ); if ( count($saved) > 5 ) $saved = array_slice($saved, 0, 5); update_user_meta( $user_id, 'nativa_saved_addresses', $saved ); }
    private function get_status_label($slug) { $map = [ 'new' => 'Aguardando Confirmação', 'pending_payment' => 'Aguardando Pagamento', 'preparing' => 'Em Preparo', 'delivering' => 'Saiu para Entrega', 'finished' => 'Entregue', 'cancelled' => 'Cancelado' ]; return $map[$slug] ?? ucfirst($slug); }
}