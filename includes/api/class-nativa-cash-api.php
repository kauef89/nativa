<?php
/**
 * API: Operações de Caixa (Versão Definitiva)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Cash_API {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/cash/status', array( 'methods' => 'GET', 'callback' => array( $this, 'get_status' ), 'permission_callback' => '__return_true' ));
        register_rest_route( 'nativa/v2', '/cash/open', array( 'methods' => 'POST', 'callback' => array( $this, 'open_box' ), 'permission_callback' => '__return_true' ));
        register_rest_route( 'nativa/v2', '/cash/move', array( 'methods' => 'POST', 'callback' => array( $this, 'add_movement' ), 'permission_callback' => '__return_true' ));
        register_rest_route( 'nativa/v2', '/cash/close', array( 'methods' => 'POST', 'callback' => array( $this, 'close_box' ), 'permission_callback' => '__return_true' ));
        register_rest_route( 'nativa/v2', '/cash/ledger', array( 'methods' => 'GET', 'callback' => array( $this, 'get_ledger' ), 'permission_callback' => '__return_true' ));
    }

    public function get_status() {
        $model = new Nativa_Cash_Register();
        $open = $model->get_current_open_register();
        
        if ( !$open ) {
            // Se fechado, busca o último fechamento para sugerir as moedas
            $last = $model->get_last_closed_register();
            $last_breakdown = ($last && !empty($last->cash_count_json)) ? json_decode($last->cash_count_json) : null;

            return new WP_REST_Response([
                'status' => 'closed',
                'last_closing' => $last_breakdown // Envia o mapa recuperado do banco
            ], 200);
        }

        $summary = $model->get_balance_summary( $open->id );

        return new WP_REST_Response([
            'status' => 'open',
            'register' => $open,
            'summary' => $summary
        ], 200);
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
        $breakdown = $request->get_param('breakdown'); // Recebe o mapa {bills, coins}

        $model = new Nativa_Cash_Register();
        $open = $model->get_current_open_register();
        if (!$open) return new WP_REST_Response(['success'=>false, 'message'=>'Caixa fechado.'], 400);

        $model->close_register( $open->id, $final_amount, $notes, $breakdown );
        return new WP_REST_Response(['success'=>true], 200);
    }

    public function get_ledger( $request ) {
        $model = new Nativa_Cash_Register();
        $open = $model->get_current_open_register();
        if (!$open) return new WP_REST_Response(['transactions'=>[]], 200);
        $list = $model->get_transactions($open->id);
        return new WP_REST_Response(['transactions'=>$list], 200);
    }
}