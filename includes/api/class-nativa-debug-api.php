<?php
/**
 * API de Debug: Auditoria e Correção (Com Monitoramento de Auth Avançado)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Debug_API {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/debug-scan', array( 'methods' => 'GET', 'callback' => array( $this, 'scan_database' ), 'permission_callback' => '__return_true' ) );
        register_rest_route( 'nativa/v2', '/debug-schema', array( 'methods' => 'GET', 'callback' => array( $this, 'inspect_schema' ), 'permission_callback' => '__return_true' ) );
        register_rest_route( 'nativa/v2', '/debug-server', array( 'methods' => 'GET', 'callback' => array( $this, 'check_server_env' ), 'permission_callback' => '__return_true' ) );
        register_rest_route( 'nativa/v2', '/debug-db-fix', array( 'methods'  => 'GET', 'callback' => array( $this, 'force_db_update' ), 'permission_callback' => '__return_true', ) );

        add_filter( 'rest_authentication_errors', array( $this, 'monitor_auth_errors' ), 999 );
    }

    /**
     * Sonda Detalhada
     */
    public function monitor_auth_errors( $error ) {
        if ( is_wp_error( $error ) ) {
            $msg = $error->get_error_message();
            $code = $error->get_error_code();

            // Pega o Nonce enviado pelo App
            $nonce_received = $_SERVER['HTTP_X_WP_NONCE'] ?? 'Nenhum';
            
            // Verifica qual usuário o WP "acha" que é com base no Cookie
            $cookie_user_id = get_current_user_id();
            
            // Tenta verificar o Nonce manualmente
            $nonce_check_result = wp_verify_nonce( $nonce_received, 'wp_rest' );

            // [SONDA AVANÇADA] Gera o nonce que DEVERIA ter vindo
            $expected_nonce = ($cookie_user_id > 0) ? wp_create_nonce('wp_rest') : 'N/A (Visitante)';

            error_log( "🔴 [API AUTH FAIL] Erro: $msg ($code)" );
            error_log( "   -> User ID (Cookie): $cookie_user_id" );
            error_log( "   -> Nonce Recebido:   $nonce_received" );
            error_log( "   -> Nonce Esperado:   $expected_nonce (Aproximado, pode variar por tick)" );
            error_log( "   -> Veredito: " . ($nonce_check_result ? 'Válido' : 'INVÁLIDO') );
            
            if ( $cookie_user_id > 0 && !$nonce_check_result ) {
                error_log( "   -> DIAGNÓSTICO FINAL: O Frontend enviou o nonce [$nonce_received], mas o backend (usuário $cookie_user_id) esperava algo como [$expected_nonce]. O Frontend NÃO atualizou o cabeçalho!" );
            }
        }
        
        return $error;
    }

    // ... (restante dos métodos force_db_update, scan_database, etc mantidos iguais) ...
    public function force_db_update() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $results = [];

        $table_print = $wpdb->prefix . 'nativa_print_logs';
        $sql_print = "CREATE TABLE $table_print (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) DEFAULT NULL,
            user_name varchar(100) NOT NULL,
            type varchar(50) NOT NULL,
            service_type varchar(50) DEFAULT NULL,
            identifier varchar(100) DEFAULT NULL,
            station varchar(50) DEFAULT 'frente',
            status varchar(50) DEFAULT 'pending_server',
            payload longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_print );
        $results[] = "Tabela $table_print verificada/criada.";

        if ( class_exists('Nativa_Session') ) { $sess = new Nativa_Session(); $sess->create_table(); $results[] = 'Tabela Sessions verificada.'; }
        if ( class_exists('Nativa_Order_Item') ) { $items = new Nativa_Order_Item(); $items->create_table(); $results[] = 'Tabela Order Items verificada.'; }

        return new WP_REST_Response(['success' => true, 'log' => $results], 200);
    }

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

    public function check_server_env() {
        $upload_dir = wp_upload_dir();
        $write_test_file = $upload_dir['basedir'] . '/test_write.tmp';
        $is_writable = false;
        if ( file_put_contents( $write_test_file, 'test' ) ) {
            $is_writable = true;
            unlink( $write_test_file );
        }
        return new WP_REST_Response([
            'soap_active' => extension_loaded('soap'),
            'uploads_writable' => $is_writable,
            'uploads_path' => $upload_dir['basedir'],
            'php_version' => phpversion()
        ], 200);
    }
}