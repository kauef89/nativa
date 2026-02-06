<?php
/**
 * API: Operações de Caixa (V2 Corrigida com JOIN)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Cash_API {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/cash/status', array( 'methods' => 'GET', 'callback' => array( $this, 'get_status' ), 'permission_callback' => '__return_true' ));
        register_rest_route( 'nativa/v2', '/cash/open', array( 'methods' => 'POST', 'callback' => array( $this, 'open_box' ), 'permission_callback' => '__return_true' ));
        register_rest_route( 'nativa/v2', '/cash/move', array( 'methods' => 'POST', 'callback' => array( $this, 'add_movement' ), 'permission_callback' => '__return_true' ));
        register_rest_route( 'nativa/v2', '/cash/close', array( 'methods' => 'POST', 'callback' => array( $this, 'close_box' ), 'permission_callback' => '__return_true' ));
        
        // Rota principal do histórico - Usa o método enriquecido
        register_rest_route( 'nativa/v2', '/cash/ledger', array( 'methods' => 'GET', 'callback' => array( $this, 'get_ledger_enriched' ), 'permission_callback' => '__return_true' ));
        
        register_rest_route( 'nativa/v2', '/cash/cancel-transaction', array(
            'methods' => 'POST',
            'callback' => array( $this, 'cancel_transaction' ),
            'permission_callback' => function() { return current_user_can('nativa_manager') || current_user_can('administrator'); }
        ));
    }

    public function get_status() {
        $model = new Nativa_Cash_Register();
        $open = $model->get_current_open_register();
        
        if ( !$open ) {
            $last = $model->get_last_closed_register();
            $last_breakdown = ($last && !empty($last->cash_count_json)) ? json_decode($last->cash_count_json) : null;
            return new WP_REST_Response(['status' => 'closed', 'last_closing' => $last_breakdown], 200);
        }

        $summary = $model->get_balance_summary( $open->id );
        return new WP_REST_Response(['status' => 'open', 'register' => $open, 'summary' => $summary], 200);
    }

    public function open_box( $request ) {
        $amount = (float) $request->get_param('amount');
        $user_id = get_current_user_id() ?: 1;
        $model = new Nativa_Cash_Register();
        $res = $model->open_register($amount, $user_id);
        if ( is_wp_error($res) ) return new WP_REST_Response(['success'=>false, 'message'=>$res->get_error_message()], 400);
        return new WP_REST_Response(['success'=>true, 'register_id'=>$res], 200);
    }

    public function add_movement( $request ) {
        $type = $request->get_param('type');
        $amount = (float) $request->get_param('amount');
        $desc = sanitize_text_field($request->get_param('description'));
        $model = new Nativa_Cash_Register();
        $open = $model->get_current_open_register();
        if (!$open) return new WP_REST_Response(['success'=>false, 'message'=>'Caixa fechado.'], 400);
        
        $model->add_transaction( $open->id, $type, 'money', $amount, $desc );
        return new WP_REST_Response(['success'=>true], 200);
    }

    public function close_box( $request ) {
        $final_amount = (float) $request->get_param('closing_balance');
        $notes = sanitize_text_field($request->get_param('notes'));
        $breakdown = $request->get_param('breakdown');

        $model = new Nativa_Cash_Register();
        $open = $model->get_current_open_register();
        if (!$open) return new WP_REST_Response(['success'=>false, 'message'=>'Caixa fechado.'], 400);

        $model->close_register( $open->id, $final_amount, $notes, $breakdown );
        return new WP_REST_Response(['success'=>true], 200);
    }

    /**
     * MÉTODO ENRIQUECIDO: Faz JOIN para trazer detalhes da venda
     */
    public function get_ledger_enriched( $request ) {
        global $wpdb;
        
        // Tabelas do banco V2
        $table_registers = $wpdb->prefix . 'nativa_cash_registers';
        $table_trans     = $wpdb->prefix . 'nativa_transactions';
        $table_orders    = $wpdb->prefix . 'nativa_sessions';

        // 1. Busca caixa aberto
        $register_id = $wpdb->get_var("SELECT id FROM $table_registers WHERE status = 'open' LIMIT 1");
        
        $where = "";
        if ( $register_id ) {
            $where = $wpdb->prepare("WHERE t.register_id = %d", $register_id);
        } else {
            // Se fechado, mostra histórico recente (24h)
            $where = "WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        }

        // 2. Query com JOIN
        $sql = "
            SELECT 
                t.id,
                t.session_id,
                t.type,
                t.amount,
                t.method,
                t.description,
                t.created_at,
                -- Dados da Sessão
                s.type as session_type,
                s.table_number,
                s.client_name,
                s.fiscal_status
            FROM $table_trans t
            LEFT JOIN $table_orders s ON t.session_id = s.id
            $where
            ORDER BY t.created_at DESC
        ";

        $transactions = $wpdb->get_results($sql);

        // 3. Normalização
        foreach ($transactions as $t) {
            $t->amount = (float)$t->amount;
            $t->id = (int)$t->id;
            
            // Corrige tipos 'in' antigos para 'sale' na visualização
            if ($t->type === 'in' && $t->session_id > 0) $t->type = 'sale';
            
            // Garante uma descrição amigável se estiver vazia
            if (empty($t->description) && $t->type === 'sale') {
                $label = '';
                if ($t->session_type === 'table') {
                    $label = "Mesa {$t->table_number}";
                } elseif ($t->session_type === 'counter') {
                    $label = "Balcão"; // <--- MELHORIA: Tradução explícita
                } else {
                    $label = ucfirst($t->session_type);
                }
                
                $t->description = "Venda {$label}";
            }
        }

        return new WP_REST_Response(['transactions' => $transactions], 200);
    }

    public function cancel_transaction( $request ) {
        // Redireciona para a Payment API que já tem a lógica completa
        $payment_api = new Nativa_Payment_API();
        return $payment_api->cancel_transaction($request);
    }
}