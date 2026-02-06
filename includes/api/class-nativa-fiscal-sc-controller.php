<?php
/**
 * Controller: Obrigações Fiscais SC (Ato DIAT 032/2023)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Fiscal_SC_Controller {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/fiscal/menu', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_fiscal_menu_data' ),
            'permission_callback' => '__return_true', // Menu Fiscal deve ser acessível
        ));

        register_rest_route( 'nativa/v2', '/fiscal/generate-file-i', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'generate_file_i' ),
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ));
    }

    /**
     * Retorna lista de arquivos gerados
     */
    public function get_fiscal_menu_data() {
        $upload_dir = wp_upload_dir();
        $fiscal_dir = $upload_dir['basedir'] . '/nativa_fiscal';
        $fiscal_url = $upload_dir['baseurl'] . '/nativa_fiscal';

        if ( ! file_exists( $fiscal_dir ) ) {
            wp_mkdir_p( $fiscal_dir );
        }

        $files = [];
        foreach ( glob( "$fiscal_dir/*.txt" ) as $filename ) {
            $basename = basename( $filename );
            $files[] = [
                'name' => $basename,
                'url'  => $fiscal_url . '/' . $basename,
                'date' => date( 'd/m/Y H:i', filemtime( $filename ) ),
                'type' => 'Arquivo I (Itens)'
            ];
        }

        return new WP_REST_Response([
            'success' => true,
            'files'   => array_reverse( $files ),
            'company' => get_option( 'nativa_fiscal_settings', [] )
        ], 200);
    }

    /**
     * Gera Arquivo I (Lista de Itens) - Layout 40 posições
     */
    public function generate_file_i( $request ) {
        global $wpdb;
        
        $settings = get_option( 'nativa_fiscal_settings', [] );
        $ie = preg_replace( '/[^0-9]/', '', $settings['ie'] ?? '000000000' );
        $ano = date('Y');
        $mes = date('m');
        
        // Nome: IE_ANO_MES_ITENS.txt
        $filename = "{$ie}_{$ano}_{$mes}_ITENS.txt";
        
        $products = get_posts([
            'post_type' => 'nativa_produto',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $content = "";

        foreach ( $products as $prod ) {
            // Layout:
            // 1-10: Código (Numérico, zeros à esquerda)
            // 11-40: Descrição (Texto, espaços à direita)
            
            $codigo = str_pad( $prod->ID, 10, '0', STR_PAD_LEFT );
            
            // Limpa caracteres especiais e limita a 30 chars
            $desc_raw = strtoupper( remove_accents( $prod->post_title ) );
            $desc_raw = preg_replace( '/[^A-Z0-9 ]/', '', $desc_raw );
            $descricao = str_pad( substr( $desc_raw, 0, 30 ), 30, ' ', STR_PAD_RIGHT );

            $content .= $codigo . $descricao . "\r\n";
        }

        // Salva arquivo
        $upload_dir = wp_upload_dir();
        $fiscal_dir = $upload_dir['basedir'] . '/nativa_fiscal';
        if ( ! file_exists( $fiscal_dir ) ) wp_mkdir_p( $fiscal_dir );

        $file_path = $fiscal_dir . '/' . $filename;
        file_put_contents( $file_path, $content );

        // Log de Auditoria
        if ( class_exists('Nativa_Session_Log') ) {
            $logger = new Nativa_Session_Log();
            $logger->log( 0, 'fiscal_generation', "Arquivo Fiscal I gerado: $filename", [], 'completed' );
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Arquivo gerado com sucesso!',
            'filename' => $filename
        ], 200);
    }
}