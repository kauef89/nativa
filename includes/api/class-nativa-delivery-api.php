<?php
/**
 * API de Delivery Unificada
 * 1. Logística: Endereços, Ruas e Taxas
 * 2. Fluxo: Atualização de Status e Cancelamento
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

        // --- SEÇÃO 2: FLUXO DO PEDIDO (STATUS E CLIENTE) ---

        // Cliente: Cancelar pedido próprio
        register_rest_route( 'nativa/v2', '/client/cancel-order', array(
            'methods' => 'POST',
            'callback' => array( $this, 'client_cancel_endpoint' ),
            'permission_callback' => '__return_true', 
        ));

        // Gerente/KDS: Atualizar Status (Avançar etapa)
        register_rest_route( 'nativa/v2', '/update-order-status', array(
            'methods' => 'POST',
            'callback' => array( $this, 'update_status_endpoint' ),
            'permission_callback' => '__return_true',
        ));
    }

    /* ===========================================================
       MÉTODOS DE LOGÍSTICA
       =========================================================== */

    public function get_bairros() {
        $posts = get_posts( array(
            'post_type' => 'nativa_bairro', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'
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

        // Busca exata
        $query = new WP_Query( array( 'post_type' => 'nativa_rua', 's' => $term, 'posts_per_page' => 10, 'post_status' => 'publish' ) );
        $results = array();
        
        foreach ( $query->posts as $post ) {
            $segments = $this->get_rua_segments($post->ID);
            $default_district = !empty($segments) ? $segments[0]['district'] : '';
            $results[] = array(
                'id' => $post->ID, 'name' => $post->post_title, 'district' => $default_district, 'segments' => $segments, 'type' => 'exact'
            );
        }

        // Opção Nova Rua (Admin/Manager)
        if ( current_user_can( 'edit_posts' ) ) {
            $results[] = array( 'id' => 'new_street', 'name' => 'Cadastrar: "' . $term . '"', 'type' => 'action', 'original_term' => $term );
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
            // LÓGICA DE INTERVALO RIGOROSA
            
            // Se o início estiver vazio, consideramos 0 (começo da rua)
            $inicio = ( $regra['numero_inicial'] !== '' && $regra['numero_inicial'] !== null ) 
                      ? (int) $regra['numero_inicial'] 
                      : 0;
            
            // Se o fim estiver vazio, consideramos 999999 (fim da rua/infinito)
            $fim = ( $regra['numero_final'] !== '' && $regra['numero_final'] !== null ) 
                   ? (int) $regra['numero_final'] 
                   : 999999;

            // Verifica se o número digitado está EXATAMENTE dentro desta faixa
            if ( $numero >= $inicio && $numero <= $fim ) { 
                $bairro_id = $regra['bairro_associado']; 
                break; // Encontrou a faixa correta, para o loop.
            }
        }

        // --- MUDANÇA CRÍTICA: SEM "CHUTE" ---
        // Se o número não caiu em nenhuma faixa cadastrada, retornamos erro.
        // Isso evita cobrar taxa errada se houver um "buraco" no cadastro.
        if ( ! $bairro_id ) {
            return new WP_REST_Response( [
                'success' => false, 
                'message' => "O número $numero não está atendido nesta rua. Verifique se digitou corretamente."
            ], 404 ); 
        }

        // Se achou, retorna os dados do bairro correto
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

        $post_id = wp_insert_post( array( 'post_title' => $name, 'post_type' => 'nativa_rua', 'post_status' => 'publish' ) );
        if ( is_wp_error( $post_id ) ) return new WP_REST_Response( ['success' => false], 500 );

        update_field( 'rua_segmentos', array( array( 'bairro_associado' => $bairro_id, 'numero_inicial' => '', 'numero_final' => '' ) ), $post_id );

        return new WP_REST_Response( array( 'success' => true, 'rua' => array( 'id' => $post_id, 'name' => $name ) ), 200 );
    }

    /* ===========================================================
       MÉTODOS DE FLUXO (STATUS & CANCELAMENTO)
       =========================================================== */

    /**
     * Cliente tenta cancelar o próprio pedido via App
     */
    public function client_cancel_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        $session_id = isset($params['session_id']) ? intval($params['session_id']) : 0;

        // Verifica status atual
        $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $session_id));

        // Regra: Só cancela se ainda for "novo" ou "pendente" (ainda não aceito pela cozinha)
        if ( !in_array($status, ['new', 'pending']) ) {
            return new WP_REST_Response(['success' => false, 'message' => 'Pedido já em preparo. Entre em contato para cancelar.'], 400);
        }

        // Executa Cancelamento
        $wpdb->update($wpdb->prefix . 'nativa_sessions', ['status' => 'cancelled'], ['id' => $session_id]);
        $wpdb->update($wpdb->prefix . 'nativa_order_items', ['status' => 'cancelled'], ['session_id' => $session_id]);

        // AVISA O GERENTE (Push Interno)
        if ( class_exists('Nativa_OneSignal') ) {
            Nativa_OneSignal::send("Cliente cancelou o pedido #$session_id", ['type' => 'order_cancelled']);
        }

        return new WP_REST_Response(['success' => true, 'message' => 'Pedido cancelado.'], 200);
    }

    /**
     * Staff muda o status (Ex: Aceitar, Pronto, Saiu para Entrega)
     */
    public function update_status_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        $session_id = isset($params['session_id']) ? intval($params['session_id']) : 0;
        $new_status = isset($params['status']) ? sanitize_text_field($params['status']) : ''; // 'preparing', 'delivering', 'finished'

        if (!$session_id || !$new_status) return new WP_REST_Response(['success' => false], 400);

        // 1. Atualiza Banco
        $wpdb->update($wpdb->prefix . 'nativa_sessions', ['status' => $new_status], ['id' => $session_id]);

        // 2. Define Mensagem para o Cliente
        $msg_client = "";
        switch ($new_status) {
            case 'preparing': 
                $msg_client = "👩‍🍳 Seu pedido começou a ser preparado!"; 
                break;
            case 'delivering': 
                $msg_client = "🛵 Saiu para entrega! Acompanhe pelo app."; 
                break;
            case 'finished': 
                $msg_client = "✅ Pedido entregue. Bom apetite!"; 
                break;
            case 'cancelled':
                $msg_client = "❌ Seu pedido foi cancelado pelo restaurante.";
                break;
        }

        // 3. Busca Player ID do Cliente e Envia
        if ( $msg_client && class_exists('Nativa_OneSignal') ) {
            $client_player_id = $wpdb->get_var($wpdb->prepare("SELECT onesignal_id FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $session_id));
            
            if ( $client_player_id ) {
                Nativa_OneSignal::send_to_client($client_player_id, $msg_client, ['type' => 'status_change', 'status' => $new_status]);
            }
            
            // 4. Atualiza Dashboards Internos
            Nativa_OneSignal::send("Status atualizado: Pedido #$session_id", ['type' => 'order_update']);
        }

        return new WP_REST_Response(['success' => true], 200);
    }
}