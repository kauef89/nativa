<?php
/**
 * API de Pagamentos e Transações
 * Responsável por registrar pagamentos, gerar Pix e baixar sessões.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Payment_API {

    public function register_routes() {
        // ... (Rotas mantidas) ...
        register_rest_route( 'nativa/v2', '/pay-session', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'pay_session_endpoint' ),
            'permission_callback' => '__return_true',
        ));
        register_rest_route( 'nativa/v2', '/generate-pix', array( 'methods' => 'POST', 'callback' => array( $this, 'generate_pix_endpoint' ), 'permission_callback' => '__return_true' ));
        register_rest_route( 'nativa/v2', '/check-pix-status', array( 'methods' => 'GET', 'callback' => array( $this, 'check_pix_status_endpoint' ), 'permission_callback' => '__return_true' ));
        register_rest_route( 'nativa/v2', '/cash/cancel-transaction', array( 'methods' => 'POST', 'callback' => array( $this, 'cancel_transaction' ), 'permission_callback' => function() { return current_user_can('nativa_manager') || current_user_can('administrator'); } ));
    }

    /**
     * Efetua o pagamento e fecha a sessão se quitado
     */
    public function pay_session_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        
        $session_id = isset($params['session_id']) ? intval($params['session_id']) : 0;
        $payments   = isset($params['payments']) ? $params['payments'] : [];
        $request_review = isset($params['request_review']) ? (bool)$params['request_review'] : false;
        
        // NOVO: Pega o nome da conta para a descrição
        $account_name = isset($params['account']) ? sanitize_text_field($params['account']) : 'Cliente';

        if ( ! $session_id || empty($payments) ) {
            return new WP_REST_Response(['success' => false, 'message' => 'Dados inválidos.'], 400);
        }

        $user_id = get_current_user_id();
        
        $table_trans = $wpdb->prefix . 'nativa_transactions';
        $table_registers = $wpdb->prefix . 'nativa_cash_registers'; 
        $table_sessions = $wpdb->prefix . 'nativa_sessions';
        $table_items = $wpdb->prefix . 'nativa_order_items';

        $wpdb->query('START TRANSACTION');

        try {
            $register_id = $wpdb->get_var("SELECT id FROM $table_registers WHERE status = 'open' OR status = 'aberto' ORDER BY id DESC LIMIT 1");
            if ( !$register_id ) $register_id = 0; 

            $sess = $wpdb->get_row($wpdb->prepare("SELECT id, status, delivery_fee, client_id, type, table_number FROM $table_sessions WHERE id = %d", $session_id));
            if (!$sess) throw new Exception("Sessão não encontrada.");
            if ($sess->status === 'closed') throw new Exception("Pedido já baixado.");

            $paid_sql = "SELECT SUM(amount) FROM $table_trans WHERE session_id = %d AND (type = 'sale' OR type = 'in' OR type = 'reversal')";
            $previous_paid = (float) $wpdb->get_var($wpdb->prepare($paid_sql, $session_id));

            $current_payment_total = 0;
            
            // Monta descrição base
            $context_label = '';
            if ($sess->type === 'table') $context_label = "Mesa {$sess->table_number}";
            else if ($sess->type === 'delivery') $context_label = "Delivery";
            else if ($sess->type === 'pickup') $context_label = "Retirada";
            else $context_label = "Balcão";

            // CORREÇÃO: Descrição rica com o nome da conta
            $description = "Venda $context_label #$session_id ($account_name)";
            
            foreach ($payments as $pay) {
                $amount = floatval($pay['amount']);
                if ($amount <= 0.001) continue;

                $current_payment_total += $amount;

                $method_to_save = !empty($pay['method_id']) ? sanitize_text_field($pay['method_id']) : sanitize_text_field($pay['method']);

                $wpdb->insert(
                    $table_trans,
                    [
                        'register_id'   => $register_id,
                        'session_id'    => $session_id,
                        'type'          => 'sale',
                        'amount'        => $amount,
                        'method'        => $method_to_save, 
                        'description'   => $description, // <--- NOVA DESCRIÇÃO
                        'created_at'    => current_time('mysql')
                    ]
                );
            }

            $items_total = $wpdb->get_var($wpdb->prepare("SELECT SUM(line_total) FROM $table_items WHERE session_id = %d AND status != 'cancelled'", $session_id));
            $order_total = (float)$items_total + (float)($sess->delivery_fee ?? 0);
            
            $total_paid_real = $previous_paid + $current_payment_total;
            $remaining = $order_total - $total_paid_real;

            if ( $remaining <= 0.05 ) {
                $wpdb->update($table_sessions, ['status' => 'closed', 'closed_at' => current_time('mysql'), 'payment_info_json' => json_encode($payments)], ['id' => $session_id]);
                if ( class_exists('Nativa_Loyalty') ) { (new Nativa_Loyalty())->confirm_points( $session_id ); }
                if ( class_exists('Nativa_Notification_Service') ) { Nativa_Notification_Service::notify_status_change( $session_id, 'finished', $sess->client_id, $sess->type, $request_review ); }
            }

            $wpdb->query('COMMIT');
            return new WP_REST_Response(['success' => true, 'remaining' => $remaining > 0 ? $remaining : 0], 200);

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // --- MÉTODOS AUXILIARES E PIX ---

    public function generate_pix_endpoint( $request ) {
        if ( ! class_exists('Nativa_Sicredi_Helper') ) require_once NATIVA_PLUGIN_DIR . 'includes/classes/class-nativa-sicredi-helper.php';
        
        $session_id = $request->get_param('session_id');
        global $wpdb;
        
        $total = $wpdb->get_var($wpdb->prepare("SELECT SUM(line_total) FROM {$wpdb->prefix}nativa_order_items WHERE session_id = %d", $session_id));
        $fee = $wpdb->get_var($wpdb->prepare("SELECT delivery_fee FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $session_id));
        $amount = (float)$total + (float)$fee;

        $sicredi = new Nativa_Sicredi_Helper();
        $res = $sicredi->create_charge($session_id, $amount);

        if ( is_wp_error($res) ) return new WP_REST_Response(['success'=>false, 'message'=>$res->get_error_message()], 400);
        
        return new WP_REST_Response([
            'success' => true,
            'pix_copy_paste' => $res['pix_copy_paste'],
            'txid' => $res['txid'],
            'created_at' => current_time('mysql')
        ], 200);
    }

    public function check_pix_status_endpoint( $request ) {
        if ( ! class_exists('Nativa_Sicredi_Helper') ) require_once NATIVA_PLUGIN_DIR . 'includes/classes/class-nativa-sicredi-helper.php';
        $txid = $request->get_param('txid');
        $sicredi = new Nativa_Sicredi_Helper();
        $status = $sicredi->check_status($txid);
        
        if ( is_wp_error($status) ) return new WP_REST_Response(['error'=>$status->get_error_message()], 400);
        
        return new WP_REST_Response($status, 200);
    }
    
    public function cancel_transaction( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        $trans_id = (int)$params['transaction_id'];
        $reason = sanitize_textarea_field($params['reason']);

        $trans = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}nativa_transactions WHERE id = %d", $trans_id));
        if (!$trans || $trans->type !== 'sale') return new WP_REST_Response(['success'=>false, 'message'=>'Transação inválida.'], 400);

        // Verifica nota fiscal
        $sess = $wpdb->get_row($wpdb->prepare("SELECT id, fiscal_status FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $trans->session_id));
        if ($sess && $sess->fiscal_status === 'emitido') {
            $wpdb->update($wpdb->prefix.'nativa_sessions', ['fiscal_status'=>'cancelado'], ['id'=>$sess->id]);
        }

        // Insere estorno
        $wpdb->insert($wpdb->prefix.'nativa_transactions', [
            'register_id' => $trans->register_id, 
            'session_id'  => $trans->session_id,
            'type'        => 'reversal', 
            'method'      => $trans->method, 
            'amount'      => -1 * abs($trans->amount),
            'description' => "ESTORNO: $reason", 
            'created_at'  => current_time('mysql')
        ]);

        // Reabre sessão
        $wpdb->update($wpdb->prefix.'nativa_sessions', ['status'=>'open', 'closed_at'=>null], ['id'=>$trans->session_id]);
        
        return new WP_REST_Response(['success'=>true, 'message'=>'Estorno realizado.'], 200);
    }
}