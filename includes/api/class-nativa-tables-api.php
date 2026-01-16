<?php
/**
 * API de Gestão de Mesas e Sessões
 * Responsável por:
 * 1. Listar status das mesas (GET /tables-status)
 * 2. Abrir nova mesa/sessão (POST /open-session)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Renomeei de ND_Tables... para Nativa_Tables... para manter o padrão do projeto
class Nativa_Tables_API {

    public function register_routes() {
        // Rota 1: Status das Mesas (Mapa)
        register_rest_route( 'nativa/v2', '/tables-status', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_tables_status' ),
            'permission_callback' => '__return_true',
        ) );

        // Rota 2: Abrir Mesa (Substitui o antigo /test-session)
        register_rest_route( 'nativa/v2', '/open-session', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'open_session_endpoint' ),
            'permission_callback' => '__return_true',
        ) );

        // NOVA ROTA: Atualizar dados da sessão (ex: Nome do Cliente)
        register_rest_route( 'nativa/v2', '/update-session', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'update_session_endpoint' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function get_tables_status() {
        global $wpdb;

        $results = $wpdb->get_results( "
            SELECT id as session_id, table_number 
            FROM {$wpdb->prefix}nativa_sessions 
            WHERE status = 'open' 
            AND type = 'table'
        " );

        return new WP_REST_Response( array(
            'success' => true,
            'occupied_tables' => $results
        ), 200 );
    }

    public function open_session_endpoint( $request ) {
        $params = $request->get_params();
        
        // Usa o Model Nativa_Session para criar
        $session_model = new Nativa_Session();
        $result = $session_model->create( $params );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => $result->get_error_message() ), 500 );
        }

        return new WP_REST_Response( array(
            'success'    => true,
            'session_id' => $result,
            'message'    => 'Mesa aberta com sucesso!'
        ), 200 );
    }

    /**
     * NOVA FUNÇÃO: Atualiza o nome do cliente na sessão aberta
     */
    public function update_session_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_params();
        
        $session_id = isset($params['session_id']) ? intval($params['session_id']) : 0;
        $client_name = isset($params['client_name']) ? sanitize_text_field($params['client_name']) : '';

        if ( ! $session_id ) {
            return new WP_REST_Response( ['success' => false, 'message' => 'ID da sessão obrigatório'], 400 );
        }

        // Monta o JSON de endereço/cliente
        $address_data = array( 'name' => $client_name );
        
        $updated = $wpdb->update(
            $wpdb->prefix . 'nativa_sessions',
            array( 'delivery_address_json' => json_encode( $address_data, JSON_UNESCAPED_UNICODE ) ),
            array( 'id' => $session_id )
        );

        return new WP_REST_Response( ['success' => true, 'message' => 'Cliente atualizado'], 200 );
    }
}