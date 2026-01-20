<?php
/**
 * API de Pagamentos e Transações (ATUALIZADA)
 * Agora vincula a venda ao Caixa Aberto corretamente.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Payment_API {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/pay-session', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'pay_session_endpoint' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function pay_session_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_params();

        $session_id = isset($params['session_id']) ? $params['session_id'] : null;
        $method     = isset($params['method']) ? $params['method'] : null;
        $amount     = isset($params['amount']) ? floatval( $params['amount'] ) : 0;

        if ( empty( $session_id ) || empty( $amount ) || empty( $method ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Dados inválidos.' ), 400 );
        }

        // 1. Descobre o Caixa Aberto (INTEGRAÇÃO NOVA)
        // Se não tiver a classe (ex: plugin desativado), usa ID 0
        $register_id = 0;
        if ( class_exists( 'Nativa_Cash_Register' ) ) {
            $cash_model = new Nativa_Cash_Register();
            $open_register = $cash_model->get_current_open_register();
            if ( $open_register ) {
                $register_id = $open_register->id;
            } else {
                // Opcional: Bloquear venda se caixa estiver fechado?
                // Por enquanto, permitimos, mas registramos no limbo (0)
            }
        }

        // 2. Registra a Transação com o REGISTER_ID correto
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'nativa_transactions',
            array(
                'register_id' => $register_id, // <--- AQUI MUDOU
                'session_id'  => $session_id,
                'type'        => 'sale',
                'method'      => $method,
                'amount'      => $amount,
                'created_at'  => current_time( 'mysql' )
            )
        );

        if ( false === $inserted ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Erro ao registrar transação.' ), 500 );
        }

        // 3. Atualiza a Sessão
        $updated = $wpdb->update(
            $wpdb->prefix . 'nativa_sessions',
            array( 
                'status'    => 'paid',
                'closed_at' => current_time( 'mysql' )
            ),
            array( 'id' => $session_id )
        );

        return new WP_REST_Response( array(
            'success' => true,
            'message' => 'Pagamento confirmado! Mesa liberada.'
        ), 200 );
    }
}