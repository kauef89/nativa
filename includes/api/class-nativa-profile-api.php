<?php
/**
 * API de Clientes Nativa (V2) - VERSÃO ATUALIZADA
 * Inclui endpoint para salvar Token do OneSignal (Player ID)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Profile_API {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/search-clients', array( 'methods' => 'GET', 'callback' => array( $this, 'search_clients' ), 'permission_callback' => function() { return current_user_can( 'edit_posts' ); } ));
        register_rest_route( 'nativa/v2', '/clients/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( $this, 'get_client_details' ), 'permission_callback' => function() { return current_user_can( 'edit_posts' ); } ));
        register_rest_route( 'nativa/v2', '/clients/address', array( 'methods' => 'POST', 'callback' => array( $this, 'save_address' ), 'permission_callback' => 'is_user_logged_in' ));
        register_rest_route( 'nativa/v2', '/clients/address', array( 'methods' => 'DELETE', 'callback' => array( $this, 'delete_address' ), 'permission_callback' => 'is_user_logged_in' ));
        register_rest_route( 'nativa/v2', '/my-profile', array( 'methods' => 'GET', 'callback' => array( $this, 'get_my_profile' ), 'permission_callback' => 'is_user_logged_in' ));
        
        // --- NOVA ROTA: Salvar ID do OneSignal ---
        register_rest_route( 'nativa/v2', '/my-profile/device-token', array(
            'methods' => 'POST',
            'callback' => array( $this, 'update_device_token' ),
            'permission_callback' => 'is_user_logged_in'
        ));
    }

    // --- NOVO MÉTODO ---
    public function update_device_token( $request ) {
        $user_id = get_current_user_id();
        $token = sanitize_text_field( $request->get_param( 'token' ) );

        if ( empty( $token ) ) {
            return new WP_REST_Response(['success' => false, 'message' => 'Token vazio.'], 400);
        }

        // Salva o ID do OneSignal no meta do usuário
        update_user_meta( $user_id, 'nativa_onesignal_id', $token );

        return new WP_REST_Response(['success' => true, 'message' => 'Token atualizado.'], 200);
    }

    // --- BUSCA (Mantida Igual) ---
    public function search_clients( $request ) {
        $term = sanitize_text_field( $request->get_param( 'q' ) );
        if ( empty( $term ) || strlen( $term ) < 3 ) return new WP_REST_Response( [], 200 );
        $numbers_only = preg_replace( '/[^0-9]/', '', $term );
        $is_numeric = !empty($numbers_only) && strlen($numbers_only) >= 3;
        $args = array( 'number' => 10, 'fields' => array( 'ID', 'display_name', 'user_email' ) );
        if ( $is_numeric ) {
            $args['meta_query'] = array( 'relation' => 'OR', array( 'key' => 'nativa_user_cpf', 'value' => $numbers_only, 'compare' => 'LIKE' ), array( 'key' => 'billing_cpf', 'value' => $numbers_only, 'compare' => 'LIKE' ), array( 'key' => 'nativa_user_phone', 'value' => $numbers_only, 'compare' => 'LIKE' ), array( 'key' => 'billing_phone', 'value' => $numbers_only, 'compare' => 'LIKE' ), array( 'key' => 'celular', 'value' => $numbers_only, 'compare' => 'LIKE' ) );
        } else {
            $args['search'] = '*' . $term . '*';
            $args['search_columns'] = array( 'display_name', 'user_email' );
        }
        $user_query = new WP_User_Query( $args );
        $results = $user_query->get_results();
        $clients = array();
        foreach ( $results as $user ) {
            $phone = get_user_meta( $user->ID, 'nativa_user_phone', true ) ?: get_user_meta( $user->ID, 'billing_phone', true );
            $cpf = get_user_meta( $user->ID, 'nativa_user_cpf', true ) ?: get_user_meta( $user->ID, 'billing_cpf', true );
            $points = (int) get_user_meta( $user->ID, 'nativa_user_points', true );
            $label = $user->display_name;
            if ($cpf) $label .= " (CPF: $cpf)"; elseif ($phone) $label .= " (Tel: $phone)";
            $clients[] = array( 'id' => $user->ID, 'name' => $user->display_name, 'phone' => $phone, 'cpf' => $cpf, 'points' => $points, 'label' => $label );
        }
        return new WP_REST_Response( array( 'success' => true, 'clients' => $clients ), 200 );
    }

    // --- SALVAR (Mantido com melhorias de segurança) ---
    public function save_address( $request ) {
        $req_client_id = (int) $request->get_param('client_id');
        $current_user_id = get_current_user_id();
        
        if ( ! $req_client_id ) { $user_id = $current_user_id; } 
        else {
            if ( $req_client_id !== $current_user_id && ! current_user_can('edit_posts') ) return new WP_REST_Response(['success'=>false, 'message'=>'Acesso negado'], 403);
            $user_id = $req_client_id;
        }

        $address_raw = $request->get_param('address');
        $address = is_string($address_raw) ? json_decode(stripslashes($address_raw), true) : $address_raw;

        if ( ! is_array($address) ) return new WP_REST_Response(['success'=>false, 'message'=>'Dados inválidos'], 400);

        // Upload
        $files = $request->get_file_params();
        if ( !empty($files['facade_photo']) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            $upload_id = media_handle_upload( 'facade_photo', 0 );
            if ( !is_wp_error($upload_id) ) $address['photo_url'] = wp_get_attachment_url($upload_id);
        }

        $current_addresses = get_user_meta( $user_id, 'nativa_saved_addresses', true );
        if ( ! is_array( $current_addresses ) ) $current_addresses = [];

        if ( !empty($address['is_primary']) && $address['is_primary'] ) {
            foreach ($current_addresses as &$addr) $addr['is_primary'] = false;
        }

        if ( !empty($address['id']) ) {
            $current_addresses = array_filter($current_addresses, function($a) use ($address) { return !isset($a['id']) || $a['id'] !== $address['id']; });
        } else {
            $address['id'] = uniqid();
            if ( empty($current_addresses) ) $address['is_primary'] = true;
        }

        $address['source'] = 'Salvo Manualmente';
        array_unshift( $current_addresses, $address );
        if ( count($current_addresses) > 10 ) $current_addresses = array_slice($current_addresses, 0, 10);

        update_user_meta( $user_id, 'nativa_saved_addresses', array_values($current_addresses) );

        return new WP_REST_Response(['success' => true, 'message' => 'Salvo!', 'addresses' => array_values($current_addresses)], 200);
    }

    // --- DELETAR (Idempotente e Seguro) ---
    public function delete_address( $request ) {
        $params = $request->get_json_params(); 
        $req_client_id = isset($params['client_id']) ? (int) $params['client_id'] : (int) $request->get_param('client_id');
        $address_id    = isset($params['address_id']) ? $params['address_id'] : $request->get_param('address_id');
        
        // Se tentar apagar histórico (que agora nem aparece mais), retorna sucesso fake
        if ( strpos( (string)$address_id, 'hist_' ) === 0 ) return new WP_REST_Response(['success'=>true], 200);

        $current_user_id = get_current_user_id();
        if ( ! $req_client_id ) { $user_id = $current_user_id; } 
        else {
            if ( $req_client_id !== $current_user_id && ! current_user_can('edit_posts') ) return new WP_REST_Response(['success'=>false], 403);
            $user_id = $req_client_id;
        }

        if ( ! $address_id ) return new WP_REST_Response(['success'=>false, 'message'=>'ID necessário'], 200); // 200 para evitar crash

        $current_addresses = get_user_meta( $user_id, 'nativa_saved_addresses', true );
        if ( ! is_array( $current_addresses ) ) return new WP_REST_Response(['success'=>true, 'message'=>'Já removido'], 200);

        $new_list = array_values(array_filter($current_addresses, function($addr) use ($address_id) {
            return isset($addr['id']) ? ($addr['id'] !== $address_id) : true;
        }));

        if ( count($new_list) < count($current_addresses) ) {
            if ( !empty($new_list) ) {
                $has_primary = false;
                foreach($new_list as $a) { if (!empty($a['is_primary'])) $has_primary = true; }
                if (!$has_primary) $new_list[0]['is_primary'] = true;
            }
            update_user_meta( $user_id, 'nativa_saved_addresses', $new_list );
        }

        return new WP_REST_Response(['success'=>true, 'message'=>'Removido.', 'addresses' => $new_list], 200);
    }

    // --- DOSSIÊ DO CLIENTE ---
    public function get_client_details( $request ) {
        $user_id = (int) $request->get_param( 'id' );
        $page    = (int) ( $request->get_param( 'page' ) ?: 1 );
        $per_page = (int) ( $request->get_param( 'per_page' ) ?: 10 );

        $user = get_userdata( $user_id );
        if ( ! $user ) return new WP_REST_Response( ['success' => false, 'message' => 'Cliente não encontrado'], 404 );

        $raw_phone = get_user_meta( $user_id, 'nativa_user_phone', true ) ?: get_user_meta( $user_id, 'billing_phone', true );
        $raw_cpf   = get_user_meta( $user_id, 'nativa_user_cpf', true ) ?: get_user_meta( $user_id, 'billing_cpf', true );
        $dob       = get_user_meta( $user_id, 'nativa_user_dob', true );
        $points    = (int) get_user_meta( $user_id, 'nativa_user_points', true );

        $saved_addresses = get_user_meta( $user_id, 'nativa_saved_addresses', true );
        if ( ! is_array( $saved_addresses ) ) $saved_addresses = [];

        // HISTÓRICO DE PEDIDOS (Para estatísticas)
        $client_phone_clean = preg_replace( '/[^0-9]/', '', $raw_phone );
        $client_cpf_clean   = preg_replace( '/[^0-9]/', '', $raw_cpf );

        $meta_query = array( 'relation' => 'OR' );
        if ( $user_id ) $meta_query[] = array( 'key' => '_customer_user', 'value' => $user_id, 'compare' => '=' );
        if ( $client_cpf_clean ) $meta_query[] = array( 'key' => 'client_cpf', 'value' => $client_cpf_clean, 'compare' => '=' );

        $all_orders_query = new WP_Query( array(
            'post_type'      => array( 'nativa_order', 'nativa_pedido', 'shop_order' ),
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_query'     => $meta_query,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC'
        ) );

        $valid_order_ids = [];
        $total_spent_lifetime = 0;
        $order_count_lifetime = 0;
        $last_purchase_date = null;

        foreach ( $all_orders_query->posts as $order_id ) {
            if ( $this->is_order_match( $order_id, $user_id, $client_phone_clean, $client_cpf_clean ) ) {
                $valid_order_ids[] = $order_id;
                
                $status = $this->get_unified_status($order_id);
                $is_completed = in_array( strtolower($status), ['completed', 'finalizado', 'publish', 'concluido', 'entregue'] );
                
                if ( $is_completed ) {
                    $total = (float) ( get_post_meta( $order_id, 'order_total', true ) ?: get_post_meta( $order_id, 'pedido_total_final', true ) ?: get_post_meta( $order_id, '_order_total', true ) );
                    $total_spent_lifetime += $total;
                    $order_count_lifetime++;
                    $order_date = get_the_date( 'Y-m-d H:i:s', $order_id );
                    if ( !$last_purchase_date || $order_date > $last_purchase_date ) $last_purchase_date = $order_date;
                }
            }
        }

        $paged_ids = array_slice( $valid_order_ids, ($page - 1) * $per_page, $per_page );
        $history = [];
        foreach ( $paged_ids as $oid ) {
            $history[] = $this->format_order_for_list( $oid );
        }

        $avg_ticket = $order_count_lifetime > 0 ? ($total_spent_lifetime / $order_count_lifetime) : 0;
        $days_since_last = $last_purchase_date ? floor( ( time() - strtotime($last_purchase_date) ) / (60 * 60 * 24) ) : 0;
        
        return new WP_REST_Response( array(
            'success' => true,
            'client'  => array(
                'id'          => $user_id,
                'name'        => $user->display_name,
                'email'       => $user->user_email,
                'phone'       => $raw_phone,
                'cpf'         => $raw_cpf,
                'birth_date'  => $dob,
                'loyalty_points' => $points,
                'addresses'   => $saved_addresses,
                'stats' => [
                    'total_spent' => $total_spent_lifetime,
                    'order_count' => $order_count_lifetime,
                    'avg_ticket'  => $avg_ticket,
                    'last_buy_days' => $days_since_last
                ]
            ),
            'history' => $history,
            'pagination' => [
                'current_page' => $page,
                'has_more' => count($valid_order_ids) > ($page * $per_page)
            ]
        ), 200 );
    }

    // --- MEU PERFIL (App) ---
    public function get_my_profile( $request ) {
        $user_id = get_current_user_id();
        $user = get_userdata( $user_id );
        $addresses = get_user_meta( $user_id, 'nativa_saved_addresses', true );
        if ( ! is_array( $addresses ) ) $addresses = [];

        $data = [
            'id' => $user_id,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => get_user_meta($user_id, 'nativa_user_phone', true) ?: get_user_meta($user_id, 'billing_phone', true),
            'addresses' => $addresses
        ];
        return new WP_REST_Response(['success' => true, 'user' => $data], 200);
    }

    // --- HELPERS ---
    private function get_unified_status($order_id) {
        $status = get_post_status( $order_id );
        if ( get_post_type($order_id) === 'nativa_pedido' ) {
            $terms = wp_get_post_terms( $order_id, 'nativa_order_status' );
            if ( !empty($terms) && !is_wp_error($terms) ) $status = $terms[0]->name;
        }
        return str_replace('wc-', '', $status);
    }

    private function is_order_match( $order_id, $user_id, $clean_phone, $clean_cpf ) {
        $oid_user = (int) get_post_meta( $order_id, '_customer_user', true );
        if ( $oid_user === $user_id ) return true;
        if ( $clean_phone && strlen($clean_phone) >= 8 ) {
            $o_phone = get_post_meta( $order_id, 'client_phone', true ) ?: get_post_meta( $order_id, 'pedido_whatsapp_cliente', true ) ?: get_post_meta( $order_id, '_billing_phone', true );
            $o_phone_clean = preg_replace( '/[^0-9]/', '', $o_phone );
            if ( !empty($o_phone_clean) && strpos( $o_phone_clean, substr($clean_phone, -8) ) !== false ) return true;
        }
        if ( $clean_cpf ) {
            $o_cpf = get_post_meta( $order_id, 'client_cpf', true ) ?: get_post_meta( $order_id, 'pedido_cpf_cliente', true ) ?: get_post_meta( $order_id, '_billing_cpf', true );
            $o_cpf_clean = preg_replace( '/[^0-9]/', '', $o_cpf );
            if ( $o_cpf_clean === $clean_cpf ) return true;
        }
        return false;
    }

    private function format_order_for_list( $order_id ) {
        $total = (float) ( get_post_meta( $order_id, 'order_total', true ) ?: get_post_meta( $order_id, 'pedido_total_final', true ) ?: get_post_meta( $order_id, '_order_total', true ) );
        $status = $this->get_unified_status($order_id);
        $type = ( get_post_type($order_id) === 'nativa_pedido' ) ? 'V1' : 'V2';
        return array( 'id' => $order_id, 'date' => get_the_date( 'd/m/Y', $order_id ), 'status' => ucfirst( $status ), 'total' => $total, 'type' => $type );
    }
}