<?php
/**
 * API de Delivery Unificada
 * Responsável por:
 * 1. Logística: Endereços, Ruas, Bairros e Cálculo de Frete
 * 2. Fluxo do Cliente: Cancelamento pelo próprio usuário
 * 3. Criação de Pedidos: Ponto de entrada para novos deliveries
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Delivery_API {

    public function register_routes() {
        
        // --- SEÇÃO 1: LOGÍSTICA (ENDEREÇOS E TAXAS) ---

        register_rest_route( 'nativa/v2', '/bairros', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_bairros' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'nativa/v2', '/search-address', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'search_address' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'nativa/v2', '/calculate-freight', array(
            'methods'             => array('POST'),
            'callback'            => array( $this, 'calculate_freight' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'nativa/v2', '/create-street', array(
            'methods'             => array('POST'),
            'callback'            => array( $this, 'create_street' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
        ) );

        // --- SEÇÃO 2: FLUXO DO CLIENTE ---

        // Cliente: Cancelar pedido próprio (Auto-atendimento)
        register_rest_route( 'nativa/v2', '/client/cancel-order', array(
            'methods' => 'POST',
            'callback' => array( $this, 'client_cancel_endpoint' ),
            'permission_callback' => '__return_true', 
        ));

        // --- SEÇÃO 3: CRIAÇÃO DE PEDIDOS (STAFF) ---
        
        register_rest_route( 'nativa/v2', '/create-delivery-order', array(
            'methods' => 'POST',
            'callback' => array( $this, 'create_delivery_order' ),
            'permission_callback' => function() { return current_user_can( 'nativa_view_delivery' ) || current_user_can('edit_posts'); },
        ));
    }

    /* ===========================================================
       MÉTODOS DE LOGÍSTICA
       =========================================================== */

    public function get_bairros() {
        $posts = get_posts( array(
            'post_type' => 'nativa_bairro', 
            'posts_per_page' => -1, 
            'orderby' => 'title', 
            'order' => 'ASC'
        ) );

        $bairros = array();
        foreach ( $posts as $post ) {
            $bairros[] = array(
                'id'         => $post->ID,
                'name'       => $post->post_title,
                'fee'        => (float) get_field( 'taxa_entrega', $post->ID ),
                'free_above' => (float) get_field( 'valor_minimo_frete_gratis', $post->ID )
            );
        }
        return new WP_REST_Response( array( 'success' => true, 'bairros' => $bairros ), 200 );
    }

    public function search_address( $request ) {
        $term = trim( $request->get_param( 'q' ) );
        if ( empty( $term ) || strlen( $term ) < 3 ) return new WP_REST_Response( [], 200 );

        // Busca exata nas ruas cadastradas
        $query = new WP_Query( array( 
            'post_type' => 'nativa_rua', 
            's' => $term, 
            'posts_per_page' => 10, 
            'post_status' => 'publish' 
        ) );
        
        $results = array();
        
        foreach ( $query->posts as $post ) {
            $segments = $this->get_rua_segments($post->ID);
            // Pega o primeiro bairro como "sugestão principal" para exibição
            $default_district = !empty($segments) ? $segments[0]['district'] : '';
            
            $results[] = array(
                'id' => $post->ID, 
                'name' => $post->post_title, 
                'district' => $default_district, 
                'segments' => $segments, 
                'type' => 'exact'
            );
        }

        // Opção de "Cadastrar Nova Rua" para usuários com permissão
        if ( current_user_can( 'edit_posts' ) ) {
            $results[] = array( 
                'id' => 'new_street', 
                'name' => 'Cadastrar: "' . $term . '"', 
                'type' => 'action', 
                'original_term' => $term 
            );
        }

        return new WP_REST_Response( array( 'success' => true, 'results' => $results ), 200 );
    }

    private function get_rua_segments($post_id) {
        $raw_segments = get_field( 'rua_segmentos', $post_id );
        $clean_segments = [];
        
        if ( !empty($raw_segments) && is_array($raw_segments) ) {
            foreach ( $raw_segments as $seg ) {
                if ( !$seg['bairro_associado'] ) continue;
                
                $clean_segments[] = array(
                    'min' => !empty($seg['numero_inicial']) ? (int)$seg['numero_inicial'] : 0,
                    'max' => !empty($seg['numero_final']) ? (int)$seg['numero_final'] : 999999,
                    'district' => get_the_title($seg['bairro_associado'])
                );
            }
        }
        return $clean_segments;
    }

    public function calculate_freight( $request ) {
        $rua_id = $request->get_param( 'rua_id' );
        $numero = (int) $request->get_param( 'numero' ); 

        if ( ! $rua_id ) return new WP_REST_Response( ['error' => 'Rua obrigatória'], 400 );

        // Busca os segmentos cadastrados na rua (ACF Repeater)
        $mapa = get_field( 'rua_segmentos', $rua_id );
        
        if ( empty( $mapa ) ) {
            return new WP_REST_Response( ['success' => false, 'message' => 'Rua sem bairros vinculados.'], 404 );
        }

        $bairro_id = null;

        foreach ( $mapa as $regra ) {
            // Lógica de intervalo rigorosa
            $inicio = ( $regra['numero_inicial'] !== '' && $regra['numero_inicial'] !== null ) 
                      ? (int) $regra['numero_inicial'] 
                      : 0;
            
            $fim = ( $regra['numero_final'] !== '' && $regra['numero_final'] !== null ) 
                   ? (int) $regra['numero_final'] 
                   : 999999;

            // Verifica se o número está dentro da faixa
            if ( $numero >= $inicio && $numero <= $fim ) { 
                $bairro_id = $regra['bairro_associado']; 
                break; 
            }
        }

        if ( ! $bairro_id ) {
            return new WP_REST_Response( [
                'success' => false, 
                'message' => "O número $numero não está atendido nesta rua. Verifique o cadastro."
            ], 404 ); 
        }

        return new WP_REST_Response( array(
            'success' => true,
            'bairro'  => get_the_title( $bairro_id ),
            'taxa'    => (float) get_field( 'taxa_entrega', $bairro_id ),
            'frete_gratis_acima_de' => (float) get_field( 'valor_minimo_frete_gratis', $bairro_id )
        ), 200 );
    }

    public function create_street( $request ) {
        $name = sanitize_text_field( $request->get_param( 'name' ) );
        $bairro_id = (int) $request->get_param( 'bairro_id' );

        if ( empty( $name ) || empty( $bairro_id ) ) return new WP_REST_Response( ['success' => false], 400 );

        $post_id = wp_insert_post( array( 
            'post_title' => $name, 
            'post_type' => 'nativa_rua', 
            'post_status' => 'publish' 
        ) );
        
        if ( is_wp_error( $post_id ) ) return new WP_REST_Response( ['success' => false], 500 );

        // Cria o primeiro segmento cobrindo toda a rua para o bairro selecionado
        update_field( 'rua_segmentos', array( 
            array( 
                'bairro_associado' => $bairro_id, 
                'numero_inicial' => '', 
                'numero_final' => '' 
            ) 
        ), $post_id );

        return new WP_REST_Response( array( 'success' => true, 'rua' => array( 'id' => $post_id, 'name' => $name ) ), 200 );
    }

    /* ===========================================================
       MÉTODOS DE FLUXO E CRIAÇÃO
       =========================================================== */

    /**
     * Endpoint para o CLIENTE cancelar seu próprio pedido
     * (Usado no App do Cliente)
     */
    public function client_cancel_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        $session_id = isset($params['session_id']) ? intval($params['session_id']) : 0;

        // Verifica status atual
        $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $session_id));

        // Regra: Só cancela se ainda for "novo" ou "pendente" (ainda não aceito pela cozinha)
        if ( !in_array($status, ['new', 'pending', 'pending_payment']) ) {
            return new WP_REST_Response(['success' => false, 'message' => 'Pedido já em preparo. Entre em contato para cancelar.'], 400);
        }

        // Executa Cancelamento no banco
        $wpdb->update($wpdb->prefix . 'nativa_sessions', ['status' => 'cancelled'], ['id' => $session_id]);
        $wpdb->update($wpdb->prefix . 'nativa_order_items', ['status' => 'cancelled'], ['session_id' => $session_id]);

        // LOG DO SISTEMA (ActivityFeed)
        if ( class_exists('Nativa_Session_Log') ) {
            $logger = new Nativa_Session_Log();
            $logger->log($session_id, 'order_cancelled', "Cliente cancelou o pedido #$session_id", [], 'completed');
        }

        // Avisa o STAFF via OneSignal
        if ( class_exists('Nativa_OneSignal') ) {
            Nativa_OneSignal::send("Cliente cancelou o pedido #$session_id", ['type' => 'order_cancelled']);
        }

        return new WP_REST_Response(['success' => true, 'message' => 'Pedido cancelado.'], 200);
    }
    
    /**
     * Endpoint para o STAFF criar pedido de Delivery/Retirada
     * (Usado no Modal Novo Pedido)
     */
    public function create_delivery_order( $request ) {
        $params = $request->get_json_params();
        
        $type = isset($params['type']) ? sanitize_text_field($params['type']) : 'delivery';
        $client_name = isset($params['client_name']) ? sanitize_text_field($params['client_name']) : 'Cliente Balcão';
        $client_phone = isset($params['client_phone']) ? sanitize_text_field($params['client_phone']) : '';
        $client_id = isset($params['client_id']) ? (int)$params['client_id'] : 0;
        
        $address = isset($params['address']) ? $params['address'] : null;
        
        // Se a taxa vier do front, usa ela. Senão, calcula pelo bairro (fallback).
        $delivery_fee = isset($params['delivery_fee']) ? floatval($params['delivery_fee']) : 0;
        
        if ( $type === 'delivery' && $delivery_fee <= 0 && !empty($address['district']) ) {
            $bairro = get_page_by_title($address['district'], OBJECT, 'nativa_bairro');
            if ($bairro) {
                $delivery_fee = (float) get_field('taxa_entrega', $bairro->ID);
            }
        }

        $payments = isset($params['payments']) ? $params['payments'] : [];

        // Validação básica
        if ( $type === 'delivery' && empty($address) ) {
            return new WP_REST_Response(['success' => false, 'message' => 'Endereço obrigatório para Delivery.'], 400);
        }

        if ( ! class_exists('Nativa_Session') ) require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-session.php';
        
        $session_model = new Nativa_Session();
        
        // Prepara dados estendidos para a sessão
        $session_data = [
            'name' => $client_name,
            'client_id' => $client_id,
            'phone' => $client_phone, 
            'address' => $address, // Será salvo como JSON
            'delivery_fee' => $delivery_fee,
            'payments' => $payments, // Será salvo como JSON
            'status' => 'new' // Staff cria já liberado para cozinha
        ];

        // Garante que o telefone esteja salvo no contexto do endereço também, caso útil
        if ($client_phone && $address) {
            $session_data['address']['phone'] = $client_phone;
        }

        // Abre a sessão
        $session_id = $session_model->open( $type, null, $session_data );

        if ( ! $session_id ) {
            return new WP_REST_Response(['success' => false, 'message' => 'Erro ao criar sessão.'], 500);
        }

        // LOG DO SISTEMA (ActivityFeed)
        if ( class_exists('Nativa_Session_Log') ) {
            $logger = new Nativa_Session_Log();
            $log_msg = ($type === 'delivery') ? "Novo Delivery #$session_id" : "Novo Balcão #$session_id";
            $logger->log($session_id, 'new_order', $log_msg, [], 'completed');
        }

        return new WP_REST_Response([
            'success' => true, 
            'session_id' => $session_id,
            'client' => $client_name
        ], 200);
    }
}