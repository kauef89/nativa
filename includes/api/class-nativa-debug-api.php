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

        // NOVA ROTA: Ler o código fonte do Loader
        register_rest_route( 'nativa/v2', '/debug-source', array(
            'methods'  => 'GET', 'callback' => array( $this, 'inspect_loader_source' ), 'permission_callback' => '__return_true',
        ) );
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
        $conflicts = [];
        $slugs_to_check = ['cardapio', 'pdv', 'app'];
        foreach ( $slugs_to_check as $slug ) {
            $page = get_page_by_path( $slug, OBJECT, array('post', 'page', 'attachment') );
            if ( $page ) {
                $conflicts[] = "ALERTA: Existe um(a) {$page->post_type} com slug '/$slug' (ID: {$page->ID}, Status: {$page->post_status}).";
            }
            $trashed = get_posts(['name' => $slug, 'post_type' => 'any', 'post_status' => 'trash']);
            if ( !empty($trashed) ) {
                $conflicts[] = "ALERTA: Item na lixeira com slug '/$slug'.";
            }
        }
        $has_var = in_array( 'nativa_app', $wp->public_query_vars );
        return new WP_REST_Response([
            'status_flush' => $flush_status,
            'variavel_nativa_app' => $has_var ? 'SIM' : 'NÃO (Erro Crítico)',
            'regras_ativas_db' => !empty($nativa_rules) ? $nativa_rules : 'NENHUMA (WP não conhece a rota)',
            'conflitos' => !empty($conflicts) ? $conflicts : 'Nenhum',
            'link_correcao' => rest_url('nativa/v2/debug-routing?fix=flush')
        ], 200);
    }

    /**
     * Lê o arquivo físico para ver se o código foi atualizado
     */
    public function inspect_loader_source() {
        $file_path = NATIVA_PLUGIN_DIR . 'includes/frontend/class-nativa-frontend-loader.php';
        
        if ( ! file_exists( $file_path ) ) {
            return new WP_REST_Response([ 'erro' => 'Arquivo não encontrado no disco: ' . $file_path ], 404);
        }

        $content = file_get_contents( $file_path );
        
        // Verifica se a string crítica existe no conteúdo
        $tem_cardapio = strpos( $content, 'cardapio' ) !== false;
        $tem_filtro_canonical = strpos( $content, 'redirect_canonical' ) !== false;

        return new WP_REST_Response([
            'caminho' => $file_path,
            'tamanho' => filesize( $file_path ) . ' bytes',
            'modificado_em' => date( 'Y-m-d H:i:s', filemtime( $file_path ) ),
            'contem_regra_cardapio' => $tem_cardapio ? 'SIM' : 'NÃO (Arquivo Antigo!)',
            'contem_fix_canonical' => $tem_filtro_canonical ? 'SIM' : 'NÃO',
            'conteudo_completo' => $content // Cuidado: Exibe o código na tela
        ], 200);
    }
}