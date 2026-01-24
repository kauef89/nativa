<?php
/**
 * API de Debug: Auditoria Geral (Banco, Estrutura, Rotas e Código)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Debug_API {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/debug-scan', array(
            'methods'  => 'GET', 'callback' => array( $this, 'scan_database' ), 'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'nativa/v2', '/debug-schema', array(
            'methods'  => 'GET', 'callback' => array( $this, 'inspect_schema' ), 'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'nativa/v2', '/debug-routing', array(
            'methods'  => 'GET', 'callback' => array( $this, 'inspect_routing' ), 'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'nativa/v2', '/debug-source', array(
            'methods'  => 'GET', 'callback' => array( $this, 'inspect_loader_source' ), 'permission_callback' => '__return_true',
        ) );

        // --- ROTA DE CORREÇÃO DE BANCO ---
        register_rest_route( 'nativa/v2', '/debug-db-fix', array(
            'methods'  => 'GET', 
            'callback' => array( $this, 'force_db_update' ), 
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Força a criação/atualização das tabelas
     */
    public function force_db_update() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
        $results = [];

        // 1. Tabela de Logs
        if ( class_exists('Nativa_Session_Log') ) {
            $logs = new Nativa_Session_Log();
            $logs->create_table();
            $results[] = 'Tabela Session Logs verificada.';
        }

        // 2. Tabela de Sessões
        if ( class_exists('Nativa_Session') ) {
            $session = new Nativa_Session();
            $session->create_table();
            $results[] = 'Tabela Sessions verificada.';
        }

        // 3. Tabela de Itens (AQUI ESTÁ A CORREÇÃO CRÍTICA)
        if ( class_exists('Nativa_Order_Item') ) {
            $items = new Nativa_Order_Item();
            $items->create_table(); // Isso vai rodar o dbDelta e criar a coluna line_total
            $results[] = 'Tabela Order Items atualizada (Coluna line_total adicionada).';
        } else {
            $results[] = 'ERRO: Classe Nativa_Order_Item não encontrada.';
        }

        return new WP_REST_Response([
            'success' => true, 
            'log' => $results,
            'message' => 'Atualização de banco executada.'
        ], 200);
    }

    // ... (Mantenha os outros métodos scan_database, inspect_schema, etc iguais) ...
    public function scan_database() {
        global $wpdb;
        $post_types = $wpdb->get_results( "SELECT post_type, COUNT(*) as qtd FROM {$wpdb->posts} GROUP BY post_type" );
        return new WP_REST_Response([ 'resumo_conteudo' => $post_types ], 200);
    }

    public function inspect_schema() {
        global $wpdb;
        $tables = $wpdb->get_results( "SHOW TABLES LIKE '%nativa%'" );
        $report = [];
        foreach ( $tables as $t ) {
            $table_name = array_values((array)$t)[0];
            $ddl_query = $wpdb->get_row( "SHOW CREATE TABLE $table_name", ARRAY_A );
            $report[$table_name] = [
                'exists' => true,
                'ddl' => $ddl_query['Create Table'] ?? 'Erro DDL',
                'columns' => $wpdb->get_results( "SHOW COLUMNS FROM $table_name" )
            ];
        }
        return new WP_REST_Response([ 'tabelas' => $report ], 200);
    }

    public function inspect_routing( $request ) {
        global $wp_rewrite, $wp;
        $flush_status = 'Não solicitado';
        if ( $request->get_param('fix') === 'flush' ) {
            flush_rewrite_rules();
            $flush_status = 'Rotas recriadas (Flush Realizado)';
        }
        $rules = get_option( 'rewrite_rules' );
        $nativa_rules = [];
        if ( is_array( $rules ) ) {
            foreach ( $rules as $regex => $query ) {
                if ( strpos( $regex, 'pdv' ) !== false || strpos( $regex, 'cardapio' ) !== false ) {
                    $nativa_rules[$regex] = $query;
                }
            }
        }
        $has_var = in_array( 'nativa_app', $wp->public_query_vars );
        return new WP_REST_Response([
            'status_flush' => $flush_status,
            'variavel_nativa_app' => $has_var ? 'SIM' : 'NÃO',
            'regras_ativas' => $nativa_rules
        ], 200);
    }

    public function inspect_loader_source() {
        return new WP_REST_Response(['status' => 'ok'], 200);
    }
}