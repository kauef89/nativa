<?php
/**
 * API de Mesas (Gestão e Status)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Tables_API {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/tables-status', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_tables_status' ),
            'permission_callback' => '__return_true',
        ));

        register_rest_route( 'nativa/v2', '/swap-table', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'swap_table_endpoint' ),
            'permission_callback' => '__return_true',
        ));
    }

    public function get_tables_status() {
        $posts = get_posts( array(
            'post_type'      => 'nativa_mesa',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC'
        ));

        $tables = array();
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'nativa_sessions';

        $active_sessions = $wpdb->get_results( 
            "SELECT id, table_number, created_at FROM $table_sessions WHERE status = 'open' AND type = 'table'", 
            OBJECT_K 
        );
        
        $sessions_map = array();
        foreach ( $active_sessions as $s ) {
            $sessions_map[ $s->table_number ] = $s;
        }

        foreach ( $posts as $p ) {
            $zone_term = wp_get_post_terms( $p->ID, 'nativa_zona_mesa' );
            $zone = ! empty( $zone_term ) && ! is_wp_error( $zone_term ) ? $zone_term[0]->name : 'Salão Principal';
            $num = (int) $p->post_title; 

            $session = isset( $sessions_map[ $num ] ) ? $sessions_map[ $num ] : null;

            $tables[] = array(
                'id'        => $p->ID,
                'number'    => $num,
                'zone'      => $zone,
                'status'    => $session ? 'occupied' : 'free',
                'sessionId' => $session ? (int)$session->id : null,
                'time'      => $session ? $session->created_at : null
            );
        }

        usort($tables, function($a, $b) {
            return $a['number'] - $b['number'];
        });

        return new WP_REST_Response( array( 'success' => true, 'tables' => $tables ), 200 );
    }

    /**
     * Realiza a troca física da mesa (Total ou Parcial)
     */
    public function swap_table_endpoint( $request ) {
        global $wpdb;
        $params = $request->get_params();
        
        $from_number = isset($params['from_number']) ? (int)$params['from_number'] : 0;
        $to_number   = isset($params['to_number']) ? (int)$params['to_number'] : 0;
        $target_account = isset($params['account']) ? sanitize_text_field($params['account']) : null; // "Conta X" ou null

        if ( !$from_number || !$to_number ) {
            return new WP_REST_Response(['success' => false, 'message' => 'Dados incompletos.'], 400);
        }

        $table_sessions = $wpdb->prefix . 'nativa_sessions';
        $table_items    = $wpdb->prefix . 'nativa_order_items';

        // 1. Busca sessão de origem
        $source_session = $wpdb->get_row($wpdb->prepare(
            "SELECT id, accounts_json FROM $table_sessions WHERE table_number = %d AND status = 'open' AND type = 'table'", 
            $from_number
        ));

        if ( !$source_session ) {
            return new WP_REST_Response(['success' => false, 'message' => "Mesa de origem vazia."], 404);
        }

        // 2. Verifica destino
        $dest_session = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_sessions WHERE table_number = %d AND status = 'open' AND type = 'table'", 
            $to_number
        ));

        if ( $dest_session ) {
            return new WP_REST_Response(['success' => false, 'message' => "A mesa destino já está ocupada."], 409);
        }

        // --- CENÁRIO A: TRANSFERÊNCIA TOTAL ---
        if ( empty($target_account) ) {
            
            // Atualiza Sessão
            $wpdb->update($table_sessions, ['table_number' => $to_number], ['id' => $source_session->id]);
            // Atualiza Itens
            $wpdb->update($table_items, ['table_number' => $to_number], ['session_id' => $source_session->id]);

            return new WP_REST_Response(['success' => true, 'message' => "Mesa $from_number movida para $to_number."], 200);
        }

        // --- CENÁRIO B: TRANSFERÊNCIA PARCIAL (CONTA ESPECÍFICA) ---
        
        // 1. Cria Nova Sessão na mesa destino
        $session_model = new Nativa_Session();
        $new_session_id = $session_model->open('table', $to_number);
        
        if ( !$new_session_id ) return new WP_REST_Response(['success' => false, 'message' => 'Erro ao abrir nova mesa.'], 500);

        // 2. Move os Itens
        $moved = $wpdb->update(
            $table_items,
            array( 'session_id' => $new_session_id, 'table_number' => $to_number ), // Novos donos
            array( 'session_id' => $source_session->id, 'sub_account' => $target_account ), // Filtro
            array( '%d', '%d' ),
            array( '%d', '%s' )
        );

        // 3. Atualiza lista de contas na Sessão Nova
        // (Como acabou de criar, só tem 'Principal'. Vamos adicionar a conta movida)
        $session_model->add_account($new_session_id, $target_account);

        // 4. Remove conta da Sessão Antiga
        $old_accounts = json_decode($source_session->accounts_json, true) ?: [];
        $new_accounts_list = array_values(array_diff($old_accounts, [$target_account]));
        
        // Se a lista ficar vazia (não deveria, pois tem Principal), garante algo
        if (empty($new_accounts_list)) $new_accounts_list = ['Principal'];

        $wpdb->update(
            $table_sessions,
            array( 'accounts_json' => json_encode($new_accounts_list) ),
            array( 'id' => $source_session->id )
        );

        // DISPARO PUSH
        $msg = "Mesa $from_number transferida para Mesa $to_number";
        if ( !empty($target_account) ) {
            $msg = "Conta $target_account (Mesa $from_number) movida para Mesa $to_number";
        }
        
        Nativa_OneSignal::send( $msg, [
            'type' => 'swap',
            'from' => $from_number,
            'to' => $to_number
        ]);

        return new WP_REST_Response(['success' => true, 'message' => "Conta $target_account transferida com sucesso."], 200);
    }
}