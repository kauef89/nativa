<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Tables_API {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/tables-status', array( 'methods' => 'GET', 'callback' => array( $this, 'get_tables_status' ), 'permission_callback' => '__return_true' ));
        register_rest_route( 'nativa/v2', '/swap-table', array( 'methods' => 'POST', 'callback' => array( $this, 'swap_table_endpoint' ), 'permission_callback' => '__return_true' ));
        register_rest_route( 'nativa/v2', '/toggle-table-status', array( 'methods' => 'POST', 'callback' => array( $this, 'toggle_table_status' ), 'permission_callback' => '__return_true' ));
    }

    public function get_tables_status() {
        $posts = get_posts( array( 'post_type' => 'nativa_mesa', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ));
        $tables = array();
        global $wpdb;
        
        $table_sessions = $wpdb->prefix . 'nativa_sessions';
        $table_items    = $wpdb->prefix . 'nativa_order_items';

        $active_sessions = $wpdb->get_results( "SELECT id, table_number, created_at FROM $table_sessions WHERE status = 'open' AND type = 'table'", OBJECT_K );
        $session_ids = array_keys($active_sessions);
        $totals_map = array();

        if ( !empty($session_ids) ) {
            $ids_str = implode(',', array_map('intval', $session_ids));
            $totals_results = $wpdb->get_results( "SELECT session_id, SUM(line_total) as total FROM $table_items WHERE session_id IN ($ids_str) AND status != 'cancelled' GROUP BY session_id" );
            foreach ($totals_results as $row) { $totals_map[$row->session_id] = (float)$row->total; }
        }
        
        $sessions_map = array();
        foreach ( $active_sessions as $s ) { $sessions_map[ $s->table_number ] = $s; }

        foreach ( $posts as $p ) {
            $zone_term = wp_get_post_terms( $p->ID, 'nativa_zona_mesa' );
            $zone = ! empty( $zone_term ) && ! is_wp_error( $zone_term ) ? $zone_term[0]->name : 'Salão Principal';
            $num = (int) $p->post_title; 
            $session = isset( $sessions_map[ $num ] ) ? $sessions_map[ $num ] : null;
            $is_active = get_post_meta( $p->ID, 'nativa_mesa_ativa', true ) !== '0';
            $total_value = ($session && isset($totals_map[$session->id])) ? $totals_map[$session->id] : 0.00;

            $tables[] = array(
                'id' => $p->ID, 'number' => $num, 'zone' => $zone,
                'status' => $session ? 'occupied' : 'free',
                'is_active' => $is_active,
                'sessionId' => $session ? (int)$session->id : null,
                'time' => $session ? $session->created_at : null,
                'total' => $total_value
            );
        }

        usort($tables, function($a, $b) { return $a['number'] - $b['number']; });
        return new WP_REST_Response( array( 'success' => true, 'tables' => $tables ), 200 );
    }

    public function toggle_table_status( $request ) {
        $table_id = (int) $request->get_param('id');
        $active   = (bool) $request->get_param('active');

        if ( ! $table_id ) return new WP_REST_Response(['success' => false, 'message' => 'ID inválido.'], 400);

        if ( ! $active ) {
            global $wpdb;
            $table_post = get_post($table_id);
            $table_number = (int) $table_post->post_title;
            $is_occupied = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}nativa_sessions WHERE table_number = %d AND status = 'open'", $table_number));
            if ( $is_occupied ) return new WP_REST_Response(['success' => false, 'message' => 'Mesa ocupada não pode ser desativada.'], 409);
        }
        update_post_meta( $table_id, 'nativa_mesa_ativa', $active ? '1' : '0' );
        return new WP_REST_Response(['success' => true, 'is_active' => $active], 200 );
    }

    public function swap_table_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_params();
        
        $from_number = isset($params['from_number']) ? (int)$params['from_number'] : 0;
        $to_number   = isset($params['to_number']) ? (int)$params['to_number'] : 0;
        
        $target_accounts = [];
        if ( !empty($params['accounts']) && is_array($params['accounts']) ) {
            $target_accounts = array_map('sanitize_text_field', $params['accounts']);
        } elseif ( !empty($params['account']) ) {
            $target_accounts = [ sanitize_text_field($params['account']) ];
        }

        if ( !$from_number || !$to_number ) return new WP_REST_Response(['success' => false, 'message' => 'Dados incompletos.'], 400);

        $table_sessions = $wpdb->prefix . 'nativa_sessions';
        $table_items    = $wpdb->prefix . 'nativa_order_items';

        $source_session = $wpdb->get_row($wpdb->prepare("SELECT id, accounts_json FROM $table_sessions WHERE table_number = %d AND status = 'open' AND type = 'table'", $from_number));
        if ( !$source_session ) return new WP_REST_Response(['success' => false, 'message' => "Mesa de origem vazia."], 404);

        $dest_session = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_sessions WHERE table_number = %d AND status = 'open' AND type = 'table'", $to_number));
        if ( $dest_session ) return new WP_REST_Response(['success' => false, 'message' => "A mesa destino já está ocupada."], 409);

        // 1. Transferência Total (Mesa inteira)
        if ( empty($target_accounts) ) {
            // Atualiza apenas a sessão (os itens estão vinculados pelo session_id, que não muda)
            $wpdb->update($table_sessions, ['table_number' => $to_number], ['id' => $source_session->id]);
            
            // Itens não precisam ser atualizados pois não guardam table_number, apenas session_id
            
            return new WP_REST_Response(['success' => true, 'message' => "Mesa $from_number movida para $to_number."], 200);
        }

        // 2. Transferência Parcial (Contas específicas)
        if ( ! class_exists('Nativa_Session') ) require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-session.php';
        $session_model = new Nativa_Session();
        $new_session_id = $session_model->open('table', $to_number);
        
        if ( !$new_session_id ) return new WP_REST_Response(['success' => false, 'message' => 'Erro ao abrir nova mesa.'], 500);

        // Prepara SQL IN para as contas
        $accounts_sql = "'" . implode("','", array_map('esc_sql', $target_accounts)) . "'";
        
        // CORREÇÃO: Removemos 'table_number' do UPDATE dos itens, pois essa coluna não existe
        // Atualizamos apenas o session_id dos itens que pertencem às contas selecionadas
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_items SET session_id = %d WHERE session_id = %d AND sub_account IN ($accounts_sql)",
            $new_session_id, $source_session->id
        ));

        // Atualiza JSON de contas na nova sessão
        $wpdb->update(
            $table_sessions,
            array( 'accounts_json' => json_encode($target_accounts) ),
            array( 'id' => $new_session_id )
        );

        // Remove contas da sessão antiga
        $old_accounts = json_decode($source_session->accounts_json, true) ?: [];
        $remaining_accounts = array_values(array_diff($old_accounts, $target_accounts));
        
        // Se não sobrar contas na mesa antiga, mas ainda houver sessão aberta (ex: itens sem conta?),
        // mantemos 'Principal' por segurança.
        // Se a mesa antiga ficou vazia de itens, ela continuará aberta até ser fechada manualmente ou o sistema detectar.
        if (empty($remaining_accounts)) $remaining_accounts = ['Principal']; 

        $wpdb->update(
            $table_sessions,
            array( 'accounts_json' => json_encode($remaining_accounts) ),
            array( 'id' => $source_session->id )
        );

        // Opcional: Se a mesa antiga ficou sem itens, poderíamos fechá-la, mas a lógica atual mantém aberta.

        return new WP_REST_Response(['success' => true, 'message' => "Contas transferidas com sucesso."], 200);
    }
}