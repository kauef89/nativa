<?php
/**
 * API de Pagamentos e Transações (V5 - Final)
 * - Processamento de pagamentos parciais (Caixa)
 * - Integração Pix Sicredi (Geração e Consulta)
 * - Encerramento inteligente de sessão e Lógica de Timeout
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Payment_API {

    public function register_routes() {
        
        // 1. Processar Pagamento Manual (Dinheiro, Cartão, Fiado - Caixa)
        register_rest_route( 'nativa/v2', '/pay-session', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'pay_session_endpoint' ),
            'permission_callback' => '__return_true', // Ideal: check_staff_permission
        ));

        // 2. Gerar Pix Sicredi (Copia e Cola)
        register_rest_route( 'nativa/v2', '/generate-pix', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'generate_pix_endpoint' ),
            'permission_callback' => '__return_true',
        ));

        // 3. Checar Status Pix (Polling & Timeout)
        register_rest_route( 'nativa/v2', '/check-pix-status', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'check_pix_status_endpoint' ),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Endpoint 1: Pagamento Manual / Parcial no Caixa
     */
    public function pay_session_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_json_params();

        $session_id = isset($params['session_id']) ? (int)$params['session_id'] : null;
        $method     = isset($params['method']) ? sanitize_text_field($params['method']) : null;
        $total_paid = isset($params['amount']) ? floatval($params['amount']) : 0;
        $items_to_pay = isset($params['items']) ? $params['items'] : []; 
        
        // NOVO: Recebe o nome da conta (Ex: João)
        $account_name = isset($params['account']) ? sanitize_text_field($params['account']) : '';

        if ( empty($session_id) || $total_paid <= 0 || empty($method) ) {
            return new WP_REST_Response(['success' => false, 'message' => 'Dados inválidos.'], 400);
        }

        $register_id = 0;
        if ( class_exists('Nativa_Cash_Register') ) {
            $cash_model = new Nativa_Cash_Register();
            $open_register = $cash_model->get_current_open_register();
            $register_id = $open_register ? $open_register->id : 0;
        }

        $wpdb->query('START TRANSACTION');

        try {
            $items_table = $wpdb->prefix . 'nativa_order_items';
            
            foreach ($items_to_pay as $payment_data) {
                $item_id = (int)$payment_data['itemId'];
                $val_paid = floatval($payment_data['valueToPay']);
                $item = $wpdb->get_row($wpdb->prepare("SELECT line_total, status FROM $items_table WHERE id = %d", $item_id));
                if (!$item) continue;
                $new_total = max(0, $item->line_total - $val_paid);
                $new_status = ($new_total <= 0.01) ? 'paid' : 'confirmed';
                $wpdb->update($items_table, array('line_total' => $new_total, 'status' => $new_status, 'updated_at' => current_time('mysql')), array('id' => $item_id));
            }

            // Descrição rica com o nome da conta
            $desc = "Pagamento PDV";
            if ($account_name) $desc .= " - " . $account_name;

            $wpdb->insert(
                $wpdb->prefix . 'nativa_transactions',
                array(
                    'register_id' => $register_id,
                    'session_id'  => $session_id,
                    'type'        => 'sale',
                    'method'      => $method,
                    'amount'      => $total_paid,
                    'description' => $desc, // Salva aqui!
                    'created_at'  => current_time('mysql')
                )
            );

            $remaining_debt = $wpdb->get_var($wpdb->prepare("SELECT SUM(line_total) FROM $items_table WHERE session_id = %d AND status != 'cancelled'", $session_id));
            $session_closed = false;
            
            if ( (float)$remaining_debt <= 0.01 ) {
                $wpdb->update($wpdb->prefix . 'nativa_sessions', array('status' => 'paid', 'closed_at' => current_time('mysql')), array('id' => $session_id));
                $table_id = $wpdb->get_var($wpdb->prepare("SELECT table_id FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $session_id));
                if($table_id) $wpdb->update($wpdb->prefix . 'nativa_tables', ['status' => 'available'], ['id' => $table_id]);
                $session_closed = true;
            }

            if ( class_exists('Nativa_OneSignal') ) Nativa_OneSignal::send("Pagamento: R$ $total_paid ($account_name)", ['type' => 'order_update']);

            $wpdb->query('COMMIT');

            return new WP_REST_Response([
                'success' => true,
                'message' => $session_closed ? 'Mesa encerrada.' : 'Pagamento registrado.',
                'remaining_total' => (float)$remaining_debt,
                'session_closed' => $session_closed
            ], 200);

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_REST_Response(['success' => false, 'message' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint 2: Integração Sicredi - Gerar Cobrança
     */
    public function generate_pix_endpoint( $request ) {
        $params = $request->get_json_params();
        $session_id = isset($params['session_id']) ? (int)$params['session_id'] : 0;

        if ( !$session_id ) return new WP_REST_Response(['success' => false, 'message' => 'Sessão inválida'], 400);
        
        global $wpdb;
        
        // 1. Verifica cache no banco
        $existing = $wpdb->get_row($wpdb->prepare("SELECT pix_string, pix_txid, updated_at FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $session_id));
        
        if ( !empty($existing->pix_string) ) {
             return new WP_REST_Response([
                'success' => true, 
                'pix_copy_paste' => $existing->pix_string,
                'txid' => $existing->pix_txid,
                'created_at' => $existing->updated_at, // Envia timestamp original para o timer
                'cached' => true
            ], 200);
        }

        // 2. Calcula total pendente
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(line_total) FROM {$wpdb->prefix}nativa_order_items WHERE session_id = %d AND status != 'cancelled' AND status != 'paid'",
            $session_id
        ));

        $delivery_fee = $wpdb->get_var($wpdb->prepare("SELECT delivery_fee FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $session_id));
        $total += (float)$delivery_fee;

        if ( $total <= 0 ) return new WP_REST_Response(['success' => false, 'message' => 'Nada a pagar.'], 400);

        // 3. Chama o Helper Sicredi
        if ( ! class_exists('Nativa_Sicredi_Helper') ) {
            require_once NATIVA_PLUGIN_DIR . 'includes/classes/class-nativa-sicredi-helper.php';
        }
        
        $sicredi = new Nativa_Sicredi_Helper();
        $result = $sicredi->create_charge( $session_id, $total );

        if ( is_wp_error($result) ) {
            return new WP_REST_Response(['success' => false, 'message' => $result->get_error_message()], 500);
        }

        // Salva timestamp atual para controle do timeout
        $now = current_time('mysql');
        $wpdb->update(
            $wpdb->prefix . 'nativa_sessions',
            ['updated_at' => $now], // Atualiza horário para contar os 5 min a partir de agora
            ['id' => $session_id]
        );

        // Injeta o created_at na resposta do helper
        $result['created_at'] = $now;

        return new WP_REST_Response($result, 200);
    }

    /**
     * Endpoint 3: Checar Status (Polling) com Timeout de 5min
     */
    public function check_pix_status_endpoint( $request ) {
        $txid = $request->get_param('txid');
        $session_id = (int)$request->get_param('session_id');

        if ( ! class_exists('Nativa_Sicredi_Helper') ) {
            require_once NATIVA_PLUGIN_DIR . 'includes/classes/class-nativa-sicredi-helper.php';
        }

        $sicredi = new Nativa_Sicredi_Helper();
        $status_data = $sicredi->check_status($txid);

        if ( is_wp_error($status_data) ) return new WP_REST_Response(['paid' => false], 200);

        global $wpdb;
        $session = $wpdb->get_row($wpdb->prepare("SELECT status, updated_at, type FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $session_id));

        // --- LÓGICA DE TIMEOUT (5 MINUTOS) ---
        if ( !$status_data['paid'] && $session->status === 'pending_payment' ) {
            $created_time = strtotime($session->updated_at); // Hora que gerou o Pix
            $time_diff = current_time('timestamp') - $created_time;

            if ( $time_diff > 305 ) { // 5 min + 5s tolerância
                
                // Se for Delivery, cancela para não travar a cozinha
                if ( $session->type === 'delivery' ) {
                    $wpdb->update(
                        $wpdb->prefix . 'nativa_sessions', 
                        ['status' => 'cancelled'], 
                        ['id' => $session_id]
                    );
                    
                    // Cancela itens também
                    $wpdb->update(
                        $wpdb->prefix . 'nativa_order_items', 
                        ['status' => 'cancelled'], 
                        ['session_id' => $session_id]
                    );

                    return new WP_REST_Response(['paid' => false, 'expired' => true, 'status' => 'cancelled'], 200);
                }
            }
        }

        // --- LÓGICA DE SUCESSO ---
        if ( $status_data['paid'] ) {
            
            // 1. Marca itens como pagos
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}nativa_order_items SET status = 'paid' WHERE session_id = %d AND status != 'cancelled'",
                $session_id
            ));

            // 2. Registra Transação
            $wpdb->insert(
                $wpdb->prefix . 'nativa_transactions',
                [
                    'session_id' => $session_id,
                    'type' => 'sale',
                    'method' => 'pix_sicredi',
                    'amount' => 0, 
                    'description' => 'Pix Sicredi (Automático)',
                    'created_at' => current_time('mysql')
                ]
            );

            // 3. Atualiza Status da Sessão
            if ( $session->type === 'delivery' && $session->status === 'pending_payment' ) {
                // DELIVERY: Libera para a Cozinha (Entra no Kanban)
                $wpdb->update(
                    $wpdb->prefix . 'nativa_sessions',
                    ['status' => 'new', 'payment_status' => 'paid'],
                    ['id' => $session_id]
                );
                
                if ( class_exists('Nativa_OneSignal') ) {
                    Nativa_OneSignal::send("Novo Pedido Delivery (Pix Pago) #$session_id", ['type' => 'new_order']);
                }

            } else {
                // MESA: Marca como pago
                $wpdb->update(
                    $wpdb->prefix . 'nativa_sessions',
                    ['status' => 'paid'],
                    ['id' => $session_id]
                );
                
                if ( class_exists('Nativa_OneSignal') ) {
                    Nativa_OneSignal::send("Mesa #$session_id pagou via Pix", ['type' => 'order_update']);
                }
            }
        }

        return new WP_REST_Response($status_data, 200);
    }
}