<?php
/**
 * API de Pagamentos e Transações
 * Responsável por processar pagamentos e fechar sessões.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Payment_API {

    public function register_routes() {
        // Rota para Pagar e Fechar Conta
        register_rest_route( 'nativa/v2', '/pay-session', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'pay_session_endpoint' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * POST: Registra pagamento e fecha a sessão
     */
    public function pay_session_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_params();

        $session_id = isset($params['session_id']) ? $params['session_id'] : null;
        $method     = isset($params['method']) ? $params['method'] : null;
        $amount     = isset($params['amount']) ? floatval( $params['amount'] ) : 0;

        if ( empty( $session_id ) || empty( $amount ) || empty( $method ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Dados inválidos. Informe session_id, method e amount.' ), 400 );
        }

        // 1. Registra a Transação
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'nativa_transactions',
            array(
                'register_id' => 1,
                'session_id'  => $session_id,
                'type'        => 'sale',
                'method'      => $method,
                'amount'      => $amount,
                'created_at'  => current_time( 'mysql' )
            )
        );

        if ( false === $inserted ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Erro ao registrar transação no banco.' ), 500 );
        }

        // 2. Atualiza a Sessão
        $updated = $wpdb->update(
            $wpdb->prefix . 'nativa_sessions',
            array( 
                'status'    => 'paid',
                'closed_at' => current_time( 'mysql' )
            ),
            array( 'id' => $session_id )
        );

        if ( false === $updated ) {
             return new WP_REST_Response( array( 'success' => false, 'message' => 'Erro ao atualizar status da sessão.' ), 500 );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'message' => 'Pagamento confirmado! Mesa liberada.'
        ), 200 );
    }
}