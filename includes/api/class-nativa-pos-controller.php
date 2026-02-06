<?php
/**
 * Controller: API do Ponto de Venda (POS) - V2.11
 * - Correção: Suporte a criação automática de sessão para 'counter' (Balcão)
 * - Correção V2.12: Registro automático de sub-contas em accounts_json
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_POS_Controller {

    public function register_routes() {
        // Leitura de Sessão
        register_rest_route( 'nativa/v2', '/session-items', [ 
            'methods' => 'GET', 
            'callback' => [ $this, 'get_session_data' ], 
            'permission_callback' => '__return_true' 
        ]);

        // Criação de Conta/Comanda
        register_rest_route( 'nativa/v2', '/create-account', [ 
            'methods' => 'POST', 
            'callback' => [ $this, 'create_account_endpoint' ], 
            'permission_callback' => '__return_true' 
        ]);

        // Vínculo de Cliente
        register_rest_route( 'nativa/v2', '/link-client', [ 
            'methods' => 'POST', 
            'callback' => [ $this, 'link_client_to_session' ], 
            'permission_callback' => '__return_true' 
        ]);

        // Atualização de Metadados
        register_rest_route( 'nativa/v2', '/update-session-meta', [ 
            'methods' => 'POST', 
            'callback' => [ $this, 'update_session_meta_endpoint' ], 
            'permission_callback' => '__return_true' 
        ]);

        // Manipulação de Itens
        register_rest_route( 'nativa/v2', '/add-item', [ 
            'methods' => 'POST', 
            'callback' => [ $this, 'add_item_endpoint' ], 
            'permission_callback' => '__return_true' 
        ]);

        register_rest_route( 'nativa/v2', '/cancel-item', [ 
            'methods' => 'POST', 
            'callback' => [ $this, 'cancel_item_endpoint' ], 
            'permission_callback' => '__return_true' 
        ]);

        register_rest_route( 'nativa/v2', '/swap-items', [ 
            'methods' => 'POST', 
            'callback' => [ $this, 'swap_item_endpoint' ], 
            'permission_callback' => '__return_true' 
        ]);

        // Encerramento / Void
        register_rest_route( 'nativa/v2', '/void-session', [ 
            'methods' => 'POST', 
            'callback' => [ $this, 'void_session_endpoint' ], 
            'permission_callback' => '__return_true' 
        ]);

        // Impressão (Placeholders)
        register_rest_route( 'nativa/v2', '/print-account', [ 'methods' => 'POST', 'callback' => [ $this, 'print_account_endpoint' ], 'permission_callback' => '__return_true' ]);
        register_rest_route( 'nativa/v2', '/print-conference', [ 'methods' => 'POST', 'callback' => [ $this, 'print_conference_endpoint' ], 'permission_callback' => '__return_true' ]);
    }

    public function update_session_meta_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        $session_id = (int) ($params['session_id'] ?? 0);
        
        if ( ! $session_id ) return new WP_REST_Response(['success' => false, 'message' => 'ID inválido.'], 400);

        $data = [];
        if ( isset($params['payment_info']) ) {
            $data['payment_info_json'] = json_encode($params['payment_info']);
        }

        if ( empty($data) ) return new WP_REST_Response(['success' => true, 'message' => 'Nada a atualizar.'], 200);

        $wpdb->update( $wpdb->prefix . 'nativa_sessions', $data, ['id' => $session_id] );
        return new WP_REST_Response(['success' => true], 200);
    }

    public function link_client_to_session( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        $session_id = intval($params['session_id']);
        $client_id = intval($params['client_id']);

        if ( !$session_id || !$client_id ) return new WP_REST_Response(['success' => false, 'message' => 'IDs inválidos.'], 400);

        $user = get_userdata($client_id);
        if ( !$user ) return new WP_REST_Response(['success' => false, 'message' => 'Cliente não encontrado.'], 404);

        $client_name = $user->first_name ? "$user->first_name $user->last_name" : $user->display_name;

        $updated = $wpdb->update(
            $wpdb->prefix . 'nativa_sessions',
            [ 'client_id' => $client_id, 'client_name' => $client_name ],
            [ 'id' => $session_id ]
        );

        if ( $updated === false ) return new WP_REST_Response(['success' => false, 'message' => 'Erro de banco de dados.'], 500);

        if ( ! class_exists('Nativa_Session') ) require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-session.php';
        $session_model = new Nativa_Session();
        $accounts = $session_model->add_account( $session_id, $client_name );

        return new WP_REST_Response([
            'success' => true, 
            'client_name' => $client_name,
            'accounts' => $accounts, 
            'message' => "Cliente vinculado e conta '$client_name' criada!"
        ], 200);
    }

    public function get_session_data( $request ) {
        global $wpdb;
        $session_id = $request->get_param( 'session_id' );
        if ( ! $session_id ) return new WP_REST_Response( ['success' => false, 'message' => 'ID inválido'], 400 );

        $session_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $session_id ) );

        if ( ! $session_row ) return new WP_REST_Response( ['success' => false, 'message' => 'Sessão não encontrada'], 404 );

        $table_items = $wpdb->prefix . 'nativa_order_items';
        $raw_items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_items WHERE session_id = %d AND status != 'cancelled' ORDER BY id DESC", $session_id ) );

        $active_items = [];
        $paid_items = [];
        $items_total = 0;
        $accounts_found = [];
        $grouped_by_account = [];

        foreach ( $raw_items as $item ) {
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

            // --- BUSCA ESTAÇÃO (COZINHA) ---
            $station = 'default';
            if ( $item->product_id ) {
                // Tenta ID numérico
                $meta_station = get_post_meta( $item->product_id, 'nativa_estacao_impressao', true );
                if ( is_numeric($meta_station) && $meta_station > 0 ) {
                    $station = (int) $meta_station;
                }
                // Tenta objeto ACF
                elseif ( empty($meta_station) && function_exists('get_field') ) {
                    $field = get_field('nativa_estacao_impressao', $item->product_id);
                    if (is_object($field)) $station = $field->ID;
                    elseif (is_numeric($field)) $station = (int)$field;
                }
            }
            // -------------------------------

            $item_data = [
                'id' => $item->id, 
                'product_id' => $item->product_id, 
                'name' => $item->product_name, 
                'qty' => (int)$item->quantity,
                'price' => $real_unit_price, 
                'modifiers' => $modifiers, 
                'line_total' => $line_total, 
                'status' => $item->status,
                'sub_account' => $item->sub_account ?: 'Principal',
                'created_at' => $item->created_at,
                'station' => $station 
            ];

            $acc_name = $item->sub_account ?: 'Principal';
            if (!in_array($acc_name, $accounts_found)) $accounts_found[] = $acc_name;
            $grouped_by_account[$acc_name][] = $item_data;

            if ($item->status === 'paid') {
                $paid_items[] = $item_data;
            } else {
                $active_items[] = $item_data;
                $items_total += $line_total; 
            }
        }

        // --- RETORNO PADRONIZADO ---
        
        $session_accounts = json_decode($session_row->accounts_json) ?: ['Principal'];
        
        // Garante que todas as contas encontradas nos itens estejam na lista oficial
        $session_accounts = array_unique(array_merge($session_accounts, $accounts_found));

        $payments_promised = json_decode($session_row->payment_info_json) ?: [];

        return new WP_REST_Response([
            'success' => true,
            'session_id' => $session_row->id,
            'items' => $active_items,
            'paid_items' => $paid_items,
            'accounts' => array_values($session_accounts),
            'grouped_items' => $grouped_by_account,
            'total' => $items_total + (float)($session_row->delivery_fee ?? 0),
            'payments_promised' => $payments_promised,
            'session_info' => [
                'id' => $session_row->id,
                'type' => $session_row->type,
                'identifier' => $session_row->table_number ?: $session_row->client_name,
                'client_name' => $session_row->client_name,
                'client_id' => $session_row->client_id,
                'status' => $session_row->status,
                'delivery_fee' => (float)$session_row->delivery_fee
            ]
        ], 200);
    }

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
        if ( ! class_exists('Nativa_Session') ) require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-session.php';
        if ( ! class_exists('Nativa_Order_Item') ) require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-order-item.php';
        if ( ! class_exists('Nativa_Stock') ) require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-stock.php';

        $params = $request->get_json_params();
        $user_id = get_current_user_id();
        
        $session_model = new Nativa_Session();

        // --- LÓGICA DE CRIAÇÃO AUTOMÁTICA DE SESSÃO ---
        if ( empty($params['session_id']) ) {
            // 1. É Mesa? (Tem número)
            if ( !empty($params['table_number']) ) {
                global $wpdb;
                $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}nativa_sessions WHERE table_number = %d AND status = 'open'", $params['table_number']));
                $params['session_id'] = $existing ? $existing : $session_model->open('table', $params['table_number'], $params['client_name'] ?? null);
            }
            // 2. É Balcão? (Tipo explícito)
            elseif ( isset($params['type']) && $params['type'] === 'counter' ) {
                $params['session_id'] = $session_model->open('counter', null, $params['client_name'] ?? 'Cliente Balcão');
            }
        }

        if ( empty($params['session_id']) ) return new WP_REST_Response(['success' => false, 'message' => 'Sessão inválida ou não pôde ser criada.'], 400);
        
        $order_model = new Nativa_Order_Item();
        $stock_model = new Nativa_Stock();
        $items = isset($params['items']) ? $params['items'] : [ $params ];
        
        $updated_accounts = false;

        foreach ($items as $item) {
            $pid = isset($item['id']) ? $item['id'] : (isset($item['product_id']) ? $item['product_id'] : 0);
            $qty = (int)($item['qty'] ?? 1);
            $conta_alvo = isset($item['sub_account']) && !empty($item['sub_account']) ? $item['sub_account'] : 'Principal';
            
            if ($pid) {
                // CORREÇÃO: Garante que a conta de destino esteja registrada na sessão
                if ($conta_alvo !== 'Principal') {
                    $session_model->add_account( $params['session_id'], $conta_alvo );
                }

                $order_model->add_item(
                    $params['session_id'], 
                    $pid, 
                    $qty, 
                    isset($item['modifiers']) ? $item['modifiers'] : [], 
                    $conta_alvo
                );
                $stock_model->adjust_stock($pid, $qty * -1, 'sale', $user_id, "Venda POS Sessão #{$params['session_id']}");
            }
        }
        
        if ( class_exists('Nativa_OneSignal') ) {
            Nativa_OneSignal::send("Novos itens lançados", ['type' => 'new_order']);
            if ( !empty($params['session_id']) ) {
                global $wpdb;
                $client_id = $wpdb->get_var( $wpdb->prepare("SELECT client_id FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $params['session_id']));
                if ( $client_id ) {
                    $player_id = get_user_meta( $client_id, 'nativa_onesignal_id', true );
                    if ( $player_id ) {
                        Nativa_OneSignal::send_to_client( $player_id, "Seu pedido #{$params['session_id']} foi atualizado!", ['type' => 'order_update', 'id' => $params['session_id']] );
                    }
                }
            }
        }

        return new WP_REST_Response(['success' => true, 'session_id' => $params['session_id']], 200);
    }

    public function cancel_item_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        $item_id = intval($params['item_id']);
        if (!$item_id) return new WP_REST_Response(['success' => false], 400);

        $session_id = $wpdb->get_var($wpdb->prepare("SELECT session_id FROM {$wpdb->prefix}nativa_order_items WHERE id = %d", $item_id));
        $item_name  = $wpdb->get_var($wpdb->prepare("SELECT product_name FROM {$wpdb->prefix}nativa_order_items WHERE id = %d", $item_id));

        $wpdb->update( $wpdb->prefix . 'nativa_order_items', ['status' => 'cancellation_requested'], ['id' => $item_id] );

        $log_id = 0;
        if ( class_exists('Nativa_Session_Log') ) {
            $log = new Nativa_Session_Log();
            $log_id = $log->log($session_id, 'cancel', "Solicitação Cancelamento: $item_name", ['item_id' => $item_id], 'pending');
        }

        if ( class_exists('Nativa_OneSignal') ) {
            Nativa_OneSignal::send("Aprovação Necessária: Cancelamento", ['type' => 'new_request']);
        }

        return new WP_REST_Response(['success' => true, 'message' => 'Solicitação enviada para aprovação.', 'log_id' => $log_id], 200);
    }

    public function swap_item_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        $old_id = intval($params['original_item_id']);
        $new_item = $params['new_item'];
        $session_id = intval($params['session_id']);

        if (!$old_id || !$new_item) return new WP_REST_Response(['success'=>false], 400);

        $order_model = new Nativa_Order_Item();
        $original_account = $wpdb->get_var( $wpdb->prepare("SELECT sub_account FROM {$wpdb->prefix}nativa_order_items WHERE id = %d", $old_id) );
        $target_account = $original_account ? $original_account : 'Principal';
        $old_name = $wpdb->get_var($wpdb->prepare("SELECT product_name FROM {$wpdb->prefix}nativa_order_items WHERE id = %d", $old_id));

        $wpdb->update($wpdb->prefix.'nativa_order_items', ['status'=>'swap_pending'], ['id'=>$old_id]);
        $new_id = $order_model->add_item($session_id, $new_item['id'], $new_item['qty'], $new_item['modifiers'], $target_account);
        $wpdb->update($wpdb->prefix.'nativa_order_items', ['status'=>'hold'], ['id'=>$new_id]);
        
        $log_id = 0;
        if ( class_exists('Nativa_Session_Log') ) {
            $log = new Nativa_Session_Log();
            $log_id = $log->log($session_id, 'swap', "Troca: $old_name por {$new_item['name']}", ['old_id'=>$old_id, 'new_id'=>$new_id], 'pending');
        }

        if ( class_exists('Nativa_OneSignal') ) {
            Nativa_OneSignal::send("Aprovação Necessária: Troca", ['type' => 'new_request']);
        }

        return new WP_REST_Response(['success'=>true, 'message' => 'Solicitação de troca enviada.', 'log_id' => $log_id], 200);
    }

    public function void_session_endpoint( $request ) {
        global $wpdb;
        $session_id = intval($request->get_param('session_id'));
        if (!$session_id) return new WP_REST_Response(['success'=>false], 400);
        
        // 1. Fecha a sessão
        $wpdb->update($wpdb->prefix.'nativa_sessions', ['status'=>'closed', 'closed_at'=>current_time('mysql')], ['id'=>$session_id]);
        
        // 2. Cancela todos os itens da sessão que não foram pagos
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}nativa_order_items SET status='cancelled' WHERE session_id=%d AND status!='paid'", $session_id));
        
        // 3. (CORREÇÃO) Não acessamos tabelas extras. O grid de mesas verifica sessions 'open'.
        // Ao mudar para 'closed', a mesa automaticamente fica livre.
        
        if ( class_exists('Nativa_OneSignal') ) Nativa_OneSignal::send("Mesa Liberada", ['type' => 'update_session']);
        return new WP_REST_Response(['success'=>true], 200);
    }
}