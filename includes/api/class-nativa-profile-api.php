<?php
/**
 * API de Clientes (Busca Híbrida, RFM e Mineração Reforçada de Endereços)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Profile_API {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/search-clients', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'search_clients' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
        ) );

        register_rest_route( 'nativa/v2', '/clients/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_client_details' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
        ) );
    }

    public function search_clients( $request ) {
        $term = sanitize_text_field( $request->get_param( 'q' ) );
        if ( empty( $term ) || strlen( $term ) < 3 ) return new WP_REST_Response( [], 200 );

        $numbers_only = preg_replace( '/[^0-9]/', '', $term );
        $is_numeric = !empty($numbers_only) && strlen($numbers_only) >= 3;

        $args = array( 'number' => 10, 'fields' => array( 'ID', 'display_name', 'user_email' ) );

        if ( $is_numeric ) {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array( 'key' => 'nativa_user_cpf', 'value' => $numbers_only, 'compare' => 'LIKE' ),
                array( 'key' => 'billing_cpf', 'value' => $numbers_only, 'compare' => 'LIKE' ),
                array( 'key' => 'nativa_user_phone', 'value' => $numbers_only, 'compare' => 'LIKE' ),
                array( 'key' => 'billing_phone', 'value' => $numbers_only, 'compare' => 'LIKE' ),
                array( 'key' => 'celular', 'value' => $numbers_only, 'compare' => 'LIKE' )
            );
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
            if ($cpf) $label .= " (CPF: $cpf)";
            elseif ($phone) $label .= " (Tel: $phone)";

            $clients[] = array(
                'id'     => $user->ID,
                'name'   => $user->display_name,
                'phone'  => $phone,
                'cpf'    => $cpf,
                'points' => $points,
                'label'  => $label
            );
        }

        return new WP_REST_Response( array( 'success' => true, 'clients' => $clients ), 200 );
    }

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

        $client_phone_clean = preg_replace( '/[^0-9]/', '', $raw_phone );
        $client_cpf_clean   = preg_replace( '/[^0-9]/', '', $raw_cpf );

        $meta_query = array( 'relation' => 'OR' );
        if ( $user_id ) $meta_query[] = array( 'key' => '_customer_user', 'value' => $user_id, 'compare' => '=' );
        if ( $client_cpf_clean ) {
            $meta_query[] = array( 'key' => 'client_cpf', 'value' => $client_cpf_clean, 'compare' => '=' );
            $meta_query[] = array( 'key' => 'pedido_cpf_cliente', 'value' => $client_cpf_clean, 'compare' => 'LIKE' );
        }
        if ( strlen($client_phone_clean) >= 8 ) {
            $last_4 = substr( $client_phone_clean, -4 );
            $meta_query[] = array( 'key' => 'client_phone', 'value' => $last_4, 'compare' => 'LIKE' );
            $meta_query[] = array( 'key' => 'pedido_whatsapp_cliente', 'value' => $last_4, 'compare' => 'LIKE' );
        }

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
        $first_purchase_date = null;
        
        $addresses = [];
        $seen_address_keys = [];

        foreach ( $all_orders_query->posts as $order_id ) {
            if ( $this->is_order_match( $order_id, $user_id, $client_phone_clean, $client_cpf_clean ) ) {
                $valid_order_ids[] = $order_id;
                
                $post_type = get_post_type($order_id);
                $status = get_post_status( $order_id );
                
                if ( $post_type === 'nativa_pedido' ) {
                    $terms = wp_get_post_terms( $order_id, 'nativa_order_status' );
                    if ( !empty($terms) ) $status = $terms[0]->name;
                }

                $is_completed = in_array( strtolower($status), ['wc-completed', 'finalizado', 'publish', 'concluido', 'entregue'] );
                if ( $is_completed ) {
                    $total = (float) ( get_post_meta( $order_id, 'order_total', true ) ?: get_post_meta( $order_id, 'pedido_total_final', true ) ?: get_post_meta( $order_id, '_order_total', true ) );
                    $total_spent_lifetime += $total;
                    $order_count_lifetime++;
                    
                    $order_date = get_the_date( 'Y-m-d H:i:s', $order_id );
                    if ( !$last_purchase_date || $order_date > $last_purchase_date ) $last_purchase_date = $order_date;
                    if ( !$first_purchase_date || $order_date < $first_purchase_date ) $first_purchase_date = $order_date;
                }

                // --- MINERAÇÃO DE ENDEREÇOS REFORÇADA ---
                if ( count($addresses) < 3 ) {
                    $street = ''; $number = ''; $district = ''; $source = '';

                    // 1. Tenta ACF Array (Padrão V1 Normal)
                    $acf_addr = get_post_meta( $order_id, 'pedido_endereco', true );
                    if ( is_array( $acf_addr ) && !empty($acf_addr['pedido_rua']) ) {
                        $street = $acf_addr['pedido_rua'];
                        $number = $acf_addr['pedido_numero'] ?? '';
                        $district = $acf_addr['pedido_bairro'] ?? '';
                        $source = 'V1 (Grupo)';
                    } 
                    // 2. Tenta Chaves Flattened (Padrão V1 Legado/Importação XML)
                    elseif ( $temp_rua = get_post_meta($order_id, 'pedido_rua', true) ) {
                        $street = $temp_rua;
                        $number = get_post_meta($order_id, 'pedido_numero', true) ?: '';
                        $district = get_post_meta($order_id, 'pedido_bairro', true) ?: '';
                        $source = 'V1 (Meta Direto)';
                    }
                    // 3. Tenta Chaves com Prefixo de Grupo (Importação Complexa)
                    elseif ( $temp_rua = get_post_meta($order_id, 'pedido_endereco_pedido_rua', true) ) {
                        $street = $temp_rua;
                        $number = get_post_meta($order_id, 'pedido_endereco_pedido_numero', true) ?: '';
                        $district = get_post_meta($order_id, 'pedido_endereco_pedido_bairro', true) ?: '';
                        $source = 'V1 (Prefixo)';
                    }
                    // 4. Tenta V2 (JSON)
                    elseif ( $json_addr = get_post_meta( $order_id, 'delivery_address_json', true ) ) {
                        $decoded = json_decode($json_addr, true);
                        if ( is_array($decoded) ) {
                            $street = $decoded['street'] ?? '';
                            $number = $decoded['number'] ?? '';
                            $district = $decoded['neighborhood'] ?? '';
                            $source = 'V2 (Recente)';
                        }
                    }

                    if ( !empty($street) ) {
                        $key = strtolower( trim($street) . '-' . trim($number) );
                        if ( !in_array($key, $seen_address_keys) ) {
                            $seen_address_keys[] = $key;
                            $addresses[] = [
                                'street' => $street,
                                'number' => $number ?: 'S/N',
                                'district' => $district,
                                'source' => $source
                            ];
                        }
                    }
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
        
        $frequency_label = 'Novo';
        if ( $order_count_lifetime > 1 && $first_purchase_date ) {
            $days_active = floor( ( time() - strtotime($first_purchase_date) ) / (60 * 60 * 24) );
            if ( $days_active > 0 ) {
                $orders_per_month = ($order_count_lifetime / ($days_active / 30));
                $frequency_label = number_format($orders_per_month, 1) . ' / mês';
            }
        }

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
                'addresses'   => $addresses, 
                'stats' => [
                    'total_spent' => $total_spent_lifetime,
                    'order_count' => $order_count_lifetime,
                    'avg_ticket'  => $avg_ticket,
                    'last_buy_days' => $days_since_last,
                    'frequency'   => $frequency_label
                ]
            ),
            'history' => $history,
            'pagination' => [
                'current_page' => $page,
                'has_more' => count($valid_order_ids) > ($page * $per_page)
            ]
        ), 200 );
    }

    private function is_order_match( $order_id, $user_id, $clean_phone, $clean_cpf ) {
        $oid_user = (int) get_post_meta( $order_id, '_customer_user', true );
        if ( $oid_user === $user_id ) return true;

        if ( $clean_phone ) {
            $o_phone = get_post_meta( $order_id, 'client_phone', true ) 
                    ?: get_post_meta( $order_id, 'pedido_whatsapp_cliente', true )
                    ?: get_post_meta( $order_id, '_billing_phone', true );
            $o_phone_clean = preg_replace( '/[^0-9]/', '', $o_phone );
            if ( !empty($o_phone_clean) && (strpos($clean_phone, $o_phone_clean) !== false || strpos($o_phone_clean, $clean_phone) !== false) ) return true;
        }

        if ( $clean_cpf ) {
            $o_cpf = get_post_meta( $order_id, 'client_cpf', true ) 
                  ?: get_post_meta( $order_id, 'pedido_cpf_cliente', true )
                  ?: get_post_meta( $order_id, '_billing_cpf', true );
            $o_cpf_clean = preg_replace( '/[^0-9]/', '', $o_cpf );
            if ( $o_cpf_clean === $clean_cpf ) return true;
        }
        return false;
    }

    private function format_order_for_list( $order_id ) {
        $total = (float) ( get_post_meta( $order_id, 'order_total', true ) ?: get_post_meta( $order_id, 'pedido_total_final', true ) ?: get_post_meta( $order_id, '_order_total', true ) );
        $status = get_post_status( $order_id );
        $type = 'V2';
        if ( get_post_type($order_id) === 'nativa_pedido' ) {
            $type = 'V1';
            $terms = wp_get_post_terms( $order_id, 'nativa_order_status' );
            if ( !empty($terms) && !is_wp_error($terms) ) $status = $terms[0]->name;
        }
        return array(
            'id'     => $order_id,
            'date'   => get_the_date( 'd/m/Y', $order_id ),
            'status' => ucfirst( str_replace('wc-', '', $status) ),
            'total'  => $total,
            'type'   => $type
        );
    }
}