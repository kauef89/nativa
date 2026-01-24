<?php
/**
 * API de Clientes (Busca, CRUD de Endereços e Histórico Unificado)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Profile_API {

    public function register_routes() {
        // 1. Busca de Clientes (Autocomplete)
        register_rest_route( 'nativa/v2', '/search-clients', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'search_clients' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
        ) );

        // 2. Detalhes do Cliente (Dossiê Completo)
        register_rest_route( 'nativa/v2', '/clients/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_client_details' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
        ) );

        // 3. Salvar Endereço (Create / Update)
        register_rest_route( 'nativa/v2', '/clients/address', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'save_address' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
        ) );

        // 4. Excluir Endereço (Delete)
        register_rest_route( 'nativa/v2', '/clients/address', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'delete_address' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
        ) );

        // 5. Meu Perfil (Dados + Endereços para o App)
        register_rest_route( 'nativa/v2', '/my-profile', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_my_profile' ),
            'permission_callback' => 'is_user_logged_in',
        ) );
    }

    /**
     * Busca Híbrida (Nome, CPF ou Telefone)
     */
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

    /**
     * Detalhes do Cliente (Mescla CRUD com Mineração de Histórico)
     */
    public function get_client_details( $request ) {
        $user_id = (int) $request->get_param( 'id' );
        $page    = (int) ( $request->get_param( 'page' ) ?: 1 );
        $per_page = (int) ( $request->get_param( 'per_page' ) ?: 10 );

        $user = get_userdata( $user_id );
        if ( ! $user ) return new WP_REST_Response( ['success' => false, 'message' => 'Cliente não encontrado'], 404 );

        // Dados Cadastrais
        $raw_phone = get_user_meta( $user_id, 'nativa_user_phone', true ) ?: get_user_meta( $user_id, 'billing_phone', true );
        $raw_cpf   = get_user_meta( $user_id, 'nativa_user_cpf', true ) ?: get_user_meta( $user_id, 'billing_cpf', true );
        $dob       = get_user_meta( $user_id, 'nativa_user_dob', true );
        $points    = (int) get_user_meta( $user_id, 'nativa_user_points', true );

        // 1. Recupera Endereços Salvos (CRUD)
        $saved_addresses = get_user_meta( $user_id, 'nativa_saved_addresses', true );
        if ( ! is_array( $saved_addresses ) ) $saved_addresses = [];

        // Prepara dados para a busca de histórico (Match por ID, CPF ou Telefone)
        $client_phone_clean = preg_replace( '/[^0-9]/', '', $raw_phone );
        $client_cpf_clean   = preg_replace( '/[^0-9]/', '', $raw_cpf );

        $meta_query = array( 'relation' => 'OR' );
        if ( $user_id ) $meta_query[] = array( 'key' => '_customer_user', 'value' => $user_id, 'compare' => '=' );
        if ( $client_cpf_clean ) $meta_query[] = array( 'key' => 'client_cpf', 'value' => $client_cpf_clean, 'compare' => '=' );

        // Query de Pedidos (V1 + V2 + Woo)
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
        $mined_addresses = [];
        $seen_mined_keys = [];
        
        // Estatísticas RFM
        $total_spent_lifetime = 0;
        $order_count_lifetime = 0;
        $last_purchase_date = null;
        $first_purchase_date = null;

        foreach ( $all_orders_query->posts as $order_id ) {
            // Verifica se o pedido pertence mesmo ao cliente (Validação extra de segurança)
            if ( $this->is_order_match( $order_id, $user_id, $client_phone_clean, $client_cpf_clean ) ) {
                $valid_order_ids[] = $order_id;
                
                // --- Cálculo de Estatísticas ---
                $status = get_post_status( $order_id );
                if ( get_post_type($order_id) === 'nativa_pedido' ) {
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

                // --- Mineração de Endereços (Fallback) ---
                // Só mineramos se tivermos menos de 3 endereços salvos manualmente para não poluir
                if ( count($mined_addresses) < 3 ) {
                    $mined = $this->extract_address_from_order( $order_id );
                    if ( $mined ) {
                        // Chave única para evitar duplicados na mineração
                        $key = md5( strtolower( trim($mined['street']) . trim($mined['number']) ) );
                        if ( ! in_array( $key, $seen_mined_keys ) ) {
                            $seen_mined_keys[] = $key;
                            $mined_addresses[] = $mined;
                        }
                    }
                }
            }
        }

        // --- Mesclagem Inteligente de Endereços ---
        // Prioridade: Salvos Manualmente > Minerados
        $final_addresses = $saved_addresses;

        foreach ( $mined_addresses as $mined ) {
            $is_duplicate = false;
            foreach ( $final_addresses as $saved ) {
                // Compara Rua e Número (com tolerância leve de string)
                if ( 
                    ( strpos( strtolower($saved['street']), strtolower($mined['street']) ) !== false || strpos( strtolower($mined['street']), strtolower($saved['street']) ) !== false ) 
                    && $saved['number'] == $mined['number'] 
                ) {
                    $is_duplicate = true;
                    break;
                }
            }
            if ( ! $is_duplicate ) {
                $final_addresses[] = $mined;
            }
        }

        // Paginação do Histórico
        $paged_ids = array_slice( $valid_order_ids, ($page - 1) * $per_page, $per_page );
        $history = [];
        foreach ( $paged_ids as $oid ) {
            $history[] = $this->format_order_for_list( $oid );
        }

        // Ticket Médio e Frequência
        $avg_ticket = $order_count_lifetime > 0 ? ($total_spent_lifetime / $order_count_lifetime) : 0;
        $days_since_last = $last_purchase_date ? floor( ( time() - strtotime($last_purchase_date) ) / (60 * 60 * 24) ) : 0;
        
        $frequency_label = 'Novo';
        if ( $order_count_lifetime > 1 && $first_purchase_date ) {
            $days_active = floor( ( time() - strtotime($first_purchase_date) ) / (60 * 60 * 24) );
            if ( $days_active > 30 ) {
                $orders_per_month = ($order_count_lifetime / ($days_active / 30));
                $frequency_label = number_format($orders_per_month, 1) . ' / mês';
            } else {
                $frequency_label = 'Recente';
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
                'addresses'   => $final_addresses,
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

    /**
     * Salvar Endereço (CRUD)
     */
    public function save_address( $request ) {
        $user_id = (int) $request->get_param('client_id');
        $address = $request->get_param('address'); // Array: { street, number, district, ... }

        if ( ! $user_id || ! is_array($address) ) {
            return new WP_REST_Response(['success'=>false, 'message'=>'Dados inválidos'], 400);
        }

        $current_addresses = get_user_meta( $user_id, 'nativa_saved_addresses', true );
        if ( ! is_array( $current_addresses ) ) $current_addresses = [];

        // Se for edição (tem ID), remove o antigo primeiro
        if ( !empty($address['id']) ) {
            $current_addresses = array_filter($current_addresses, function($a) use ($address) {
                return !isset($a['id']) || $a['id'] !== $address['id'];
            });
        } else {
            // Novo registro
            $address['id'] = uniqid();
        }

        $address['source'] = 'Salvo Manualmente';
        
        // Adiciona no topo da lista
        array_unshift( $current_addresses, $address );

        // Limita a 10 endereços salvos para não inchar o banco
        if ( count($current_addresses) > 10 ) {
            $current_addresses = array_slice($current_addresses, 0, 10);
        }

        update_user_meta( $user_id, 'nativa_saved_addresses', array_values($current_addresses) );

        return new WP_REST_Response([
            'success' => true, 
            'message' => 'Endereço salvo!', 
            'addresses' => $current_addresses
        ], 200);
    }

    /**
     * Excluir Endereço (CRUD)
     */
    public function delete_address( $request ) {
        $user_id = (int) $request->get_param('client_id');
        $address_id = $request->get_param('address_id'); 

        if ( ! $user_id || ! $address_id ) {
            return new WP_REST_Response(['success'=>false, 'message'=>'IDs obrigatórios'], 400);
        }

        $current_addresses = get_user_meta( $user_id, 'nativa_saved_addresses', true );
        if ( ! is_array( $current_addresses ) ) return new WP_REST_Response(['success'=>false], 400);

        $new_list = array_filter($current_addresses, function($addr) use ($address_id) {
            return isset($addr['id']) ? ($addr['id'] !== $address_id) : true;
        });

        update_user_meta( $user_id, 'nativa_saved_addresses', array_values($new_list) );

        return new WP_REST_Response(['success'=>true, 'message'=>'Endereço removido.'], 200);
    }

    // =========================================================================
    //  HELPERS INTERNOS
    // =========================================================================

    /**
     * Extrai endereço de pedidos antigos (Mineração)
     */
    private function extract_address_from_order( $order_id ) {
        // Tenta JSON V2
        $json = get_post_meta( $order_id, 'delivery_address_json', true );
        if ( $json ) {
            $d = json_decode( $json, true );
            if ( is_array( $d ) && !empty($d['street']) ) {
                return [
                    'street'   => $d['street'],
                    'number'   => $d['number'] ?? 'S/N',
                    'district' => $d['neighborhood'] ?? '',
                    'source'   => 'Histórico Recente'
                ];
            }
        }
        
        // Tenta V1 (Meta Array)
        $acf_addr = get_post_meta( $order_id, 'pedido_endereco', true );
        if ( is_array( $acf_addr ) && !empty( $acf_addr['pedido_rua'] ) ) {
            return [
                'street'   => $acf_addr['pedido_rua'],
                'number'   => $acf_addr['pedido_numero'] ?? '',
                'district' => $acf_addr['pedido_bairro'] ?? '',
                'source'   => 'Histórico V1'
            ];
        }

        // Tenta V1 (Meta Simples/Flattened)
        $rua = get_post_meta( $order_id, 'pedido_rua', true );
        if ( $rua ) {
            return [
                'street'   => $rua,
                'number'   => get_post_meta( $order_id, 'pedido_numero', true ) ?: '',
                'district' => get_post_meta( $order_id, 'pedido_bairro', true ) ?: '',
                'source'   => 'Histórico Antigo'
            ];
        }
        
        return null;
    }

    /**
     * Verifica se o pedido pertence ao cliente (Lógica Fuzzy)
     */
    private function is_order_match( $order_id, $user_id, $clean_phone, $clean_cpf ) {
        $oid_user = (int) get_post_meta( $order_id, '_customer_user', true );
        if ( $oid_user === $user_id ) return true;

        // Match por Telefone (Parcial - últimos 8 dígitos)
        if ( $clean_phone && strlen($clean_phone) >= 8 ) {
            $o_phone = get_post_meta( $order_id, 'client_phone', true ) 
                    ?: get_post_meta( $order_id, 'pedido_whatsapp_cliente', true )
                    ?: get_post_meta( $order_id, '_billing_phone', true );
            
            $o_phone_clean = preg_replace( '/[^0-9]/', '', $o_phone );
            
            if ( !empty($o_phone_clean) ) {
                if ( strpos( $clean_phone, substr($o_phone_clean, -8) ) !== false || strpos( $o_phone_clean, substr($clean_phone, -8) ) !== false ) {
                    return true;
                }
            }
        }

        // Match por CPF (Exato)
        if ( $clean_cpf ) {
            $o_cpf = get_post_meta( $order_id, 'client_cpf', true ) 
                  ?: get_post_meta( $order_id, 'pedido_cpf_cliente', true )
                  ?: get_post_meta( $order_id, '_billing_cpf', true );
            
            $o_cpf_clean = preg_replace( '/[^0-9]/', '', $o_cpf );
            if ( $o_cpf_clean === $clean_cpf ) return true;
        }
        
        return false;
    }

    /**
     * Formata resumo do pedido para a lista
     */
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

    public function get_my_profile( $request ) {
        $user_id = get_current_user_id();
        $user = get_userdata( $user_id );

        // Busca endereços salvos
        $addresses = get_user_meta( $user_id, 'nativa_saved_addresses', true );
        if ( ! is_array( $addresses ) ) $addresses = [];

        // Dados básicos
        $data = [
            'id' => $user_id,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => get_user_meta($user_id, 'nativa_user_phone', true) ?: get_user_meta($user_id, 'billing_phone', true),
            'addresses' => $addresses
        ];

        return new WP_REST_Response(['success' => true, 'user' => $data], 200);
    }
}