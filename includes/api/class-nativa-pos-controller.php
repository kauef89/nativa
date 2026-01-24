<?php
/**
 * Controller: API do Ponto de Venda (POS)
 * Versão: 2.3 (Segurança: Force Pending Request & PIN Workflow)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_POS_Controller {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/session-items', [ 'methods' => 'GET', 'callback' => [ $this, 'get_session_data' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/create-account', [ 'methods' => 'POST', 'callback' => [ $this, 'create_account_endpoint' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/print-account', [ 'methods' => 'POST', 'callback' => [ $this, 'print_account_endpoint' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/print-conference', [ 'methods' => 'POST', 'callback' => [ $this, 'print_conference_endpoint' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/add-item', [ 'methods' => 'POST', 'callback' => [ $this, 'add_item_endpoint' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/cancel-item', [ 'methods' => 'POST', 'callback' => [ $this, 'cancel_item_endpoint' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/swap-items', [ 'methods' => 'POST', 'callback' => [ $this, 'swap_item_endpoint' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/void-session', [ 'methods' => 'POST', 'callback' => [ $this, 'void_session_endpoint' ], 'permission_callback' => '__return_true' ]);
    }

    public function get_session_data( $request ) {
        global $wpdb;
        $session_id = $request->get_param( 'session_id' );
        if ( ! $session_id ) return new WP_REST_Response( ['success' => false, 'message' => 'ID inválido'], 400 );

        $table_items = $wpdb->prefix . 'nativa_order_items';
        $raw_items = $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM $table_items WHERE session_id = %d AND status != 'cancelled' ORDER BY id DESC", 
            $session_id 
        ) );

        $active_items = [];
        $paid_items = [];
        $grand_total_remaining = 0;
        $accounts_found = [];
        $grouped_by_account = [];

        foreach ( $raw_items as $item ) {
            // Itens em 'hold' (esperando aprovação de troca) não aparecem na comanda ainda
            if ( $item->status === 'hold' ) continue;

            $modifiers = !empty($item->modifiers_json) ? json_decode($item->modifiers_json, true) : [];
            $line_total = (float)$item->line_total;
            
            if ( $line_total <= 0.001 ) {
                $unit_price = (float)$item->unit_price;
                $modifiers_total = 0;
                if ( is_array($modifiers) ) { foreach ( $modifiers as $mod ) $modifiers_total += (float)($mod['price'] ?? 0); }
                $line_total = ($unit_price + $modifiers_total) * (int)$item->quantity;
            }
            
            $real_unit_price = ($item->quantity > 0) ? ($line_total / $item->quantity) : 0;

            $item_data = [
                'id' => $item->id, 
                'name' => $item->product_name, 
                'qty' => (int)$item->quantity,
                'price' => $real_unit_price, 
                'modifiers' => $modifiers, 
                'line_total' => $line_total, 
                'status' => $item->status,
                'sub_account' => $item->sub_account ?: 'Principal',
                'created_at' => $item->created_at
            ];

            $acc_name = $item->sub_account ?: 'Principal';
            if (!in_array($acc_name, $accounts_found)) $accounts_found[] = $acc_name;
            $grouped_by_account[$acc_name][] = $item_data;

            if ($item->status === 'paid') {
                $paid_items[] = $item_data;
            } else {
                $active_items[] = $item_data;
                $grand_total_remaining += $line_total; 
            }
        }

        $session_row = $wpdb->get_row( $wpdb->prepare( "SELECT accounts_json FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $session_id ) );
        $registered_accounts = ($session_row && !empty($session_row->accounts_json)) ? json_decode($session_row->accounts_json, true) : [];
        if (!is_array($registered_accounts)) $registered_accounts = [];

        $final_accounts = array_unique(array_merge($registered_accounts, $accounts_found));
        if (empty($final_accounts)) $final_accounts = ['Principal'];
        $final_accounts = array_values($final_accounts); 

        $transactions = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nativa_transactions WHERE session_id = %d ORDER BY created_at DESC",
            $session_id
        ) );

        return new WP_REST_Response([ 
            'success' => true, 
            'items' => $active_items,      
            'paid_items' => $paid_items,   
            'total' => $grand_total_remaining,
            'accounts' => $final_accounts,
            'grouped_items' => $grouped_by_account,
            'transactions' => $transactions
        ], 200);
    }

    // ... (create_account, print_*, add_item mantidos iguais) ...
    public function create_account_endpoint( $request ) {
        $params = $request->get_json_params();
        $session_id = isset($params['session_id']) ? intval($params['session_id']) : 0;
        $name = isset($params['name']) ? sanitize_text_field($params['name']) : '';
        if ( !$session_id && !empty($params['table_number']) ) {
            $session_model = new Nativa_Session();
            global $wpdb;
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}nativa_sessions WHERE table_number = %d AND status = 'open'", $params['table_number']));
            $session_id = $existing ? $existing : $session_model->open('table', $params['table_number']);
        }
        if ( !$session_id || empty($name) ) return new WP_REST_Response(['success' => false], 400);
        $session_model = new Nativa_Session();
        $accounts = $session_model->add_account( $session_id, $name );
        return new WP_REST_Response(['success' => true, 'accounts' => $accounts, 'session_id' => $session_id], 200);
    }

    public function print_account_endpoint( $request ) { return new WP_REST_Response(['success' => true], 200); }
    public function print_conference_endpoint( $request ) { return new WP_REST_Response(['success' => true], 200); }

    public function add_item_endpoint( $request ) {
        $params = $request->get_json_params();
        if ( empty($params['session_id']) && isset($params['table_number']) ) {
            $session_model = new Nativa_Session();
            global $wpdb;
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}nativa_sessions WHERE table_number = %d AND status = 'open'", $params['table_number']));
            $params['session_id'] = $existing ? $existing : $session_model->open('table', $params['table_number'], $params['client_name'] ?? null);
        }
        if ( empty($params['session_id']) ) return new WP_REST_Response(['success' => false], 400);
        $order_model = new Nativa_Order_Item();
        $items = isset($params['items']) ? $params['items'] : [ $params ];
        foreach ($items as $item) {
            $pid = isset($item['id']) ? $item['id'] : (isset($item['product_id']) ? $item['product_id'] : 0);
            $conta_alvo = isset($item['sub_account']) && !empty($item['sub_account']) ? $item['sub_account'] : 'Principal';
            if ($pid) {
                $order_model->add_item($params['session_id'], $pid, $item['qty'], isset($item['modifiers']) ? $item['modifiers'] : [], $conta_alvo);
            }
        }
        if ( class_exists('Nativa_OneSignal') ) Nativa_OneSignal::send("Novos itens", ['type' => 'new_order']);
        return new WP_REST_Response(['success' => true, 'session_id' => $params['session_id']], 200);
    }

    // --- MÉTODOS BLINDADOS (Sempre geram solicitação) ---

    public function cancel_item_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        $item_id = intval($params['item_id']);
        if (!$item_id) return new WP_REST_Response(['success' => false], 400);

        // --- MUDANÇA: Não verifica mais role. Todos geram solicitação. ---
        
        $session_id = $wpdb->get_var($wpdb->prepare("SELECT session_id FROM {$wpdb->prefix}nativa_order_items WHERE id = %d", $item_id));
        $item_name  = $wpdb->get_var($wpdb->prepare("SELECT product_name FROM {$wpdb->prefix}nativa_order_items WHERE id = %d", $item_id));

        // 1. Marca item como 'Solicitado Cancelamento'
        $wpdb->update( 
            $wpdb->prefix . 'nativa_order_items', 
            ['status' => 'cancellation_requested'], 
            ['id' => $item_id] 
        );

        // 2. Cria Log Pendente
        if ( class_exists('Nativa_Session_Log') ) {
            $log = new Nativa_Session_Log();
            $log->log($session_id, 'cancel', "Solicitação Cancelamento: $item_name", ['item_id' => $item_id], 'pending');
        }

        // 3. Notifica ActivityFeed
        if ( class_exists('Nativa_OneSignal') ) {
            Nativa_OneSignal::send("Aprovação Necessária: Cancelamento", ['type' => 'new_request']);
        }

        return new WP_REST_Response(['success' => true, 'message' => 'Solicitação enviada para aprovação.'], 200);
    }

    public function swap_item_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        $old_id = intval($params['original_item_id']);
        $new_item = $params['new_item'];
        $session_id = intval($params['session_id']);

        if (!$old_id || !$new_item) return new WP_REST_Response(['success'=>false], 400);

        // --- MUDANÇA: Não verifica mais role. Todos geram solicitação. ---

        $order_model = new Nativa_Order_Item();
        
        // Recupera conta original para manter o dono
        $original_account = $wpdb->get_var( $wpdb->prepare(
            "SELECT sub_account FROM {$wpdb->prefix}nativa_order_items WHERE id = %d", 
            $old_id
        ) );
        $target_account = $original_account ? $original_account : 'Principal';
        $old_name = $wpdb->get_var($wpdb->prepare("SELECT product_name FROM {$wpdb->prefix}nativa_order_items WHERE id = %d", $old_id));

        // 1. Marca item antigo como 'Pendente Troca'
        $wpdb->update($wpdb->prefix.'nativa_order_items', ['status'=>'swap_pending'], ['id'=>$old_id]);
        
        // 2. Cria novo item como 'Hold' (invisível na conta até aprovar)
        $new_id = $order_model->add_item(
            $session_id, 
            $new_item['id'], 
            $new_item['qty'], 
            $new_item['modifiers'], 
            $target_account
        );
        $wpdb->update($wpdb->prefix.'nativa_order_items', ['status'=>'hold'], ['id'=>$new_id]);
        
        // 3. Cria Log e Notifica
        if ( class_exists('Nativa_Session_Log') ) {
            $log = new Nativa_Session_Log();
            $log->log($session_id, 'swap', "Troca: $old_name por {$new_item['name']}", ['old_id'=>$old_id, 'new_id'=>$new_id], 'pending');
        }
        if ( class_exists('Nativa_OneSignal') ) {
            Nativa_OneSignal::send("Aprovação Necessária: Troca", ['type' => 'new_request']);
        }

        return new WP_REST_Response(['success'=>true, 'message' => 'Solicitação de troca enviada.'], 200);
    }

    public function void_session_endpoint( $request ) {
        global $wpdb;
        $session_id = intval($request->get_param('session_id'));
        if (!$session_id) return new WP_REST_Response(['success'=>false], 400);
        
        $wpdb->update($wpdb->prefix.'nativa_sessions', ['status'=>'closed', 'closed_at'=>current_time('mysql')], ['id'=>$session_id]);
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}nativa_order_items SET status='cancelled' WHERE session_id=%d AND status!='paid'", $session_id));
        $tid = $wpdb->get_var($wpdb->prepare("SELECT table_id FROM {$wpdb->prefix}nativa_sessions WHERE id=%d", $session_id));
        if($tid) $wpdb->update($wpdb->prefix.'nativa_tables', ['status'=>'available'], ['id'=>$tid]);
        
        if ( class_exists('Nativa_OneSignal') ) Nativa_OneSignal::send("Mesa Liberada", ['type' => 'update_session']);
        return new WP_REST_Response(['success'=>true], 200);
    }
}