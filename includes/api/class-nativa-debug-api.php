<?php
/**
 * API de Debug: Auditoria Geral do Banco de Dados
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Debug_API {

    public function register_routes() {
        // Rota 1: Raio-X Geral (O que tem no banco?)
        register_rest_route( 'nativa/v2', '/debug-scan', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'scan_database' ),
            'permission_callback' => '__return_true',
        ) );

        // Rota 2: Inspecionar 1 Pedido Específico
        register_rest_route( 'nativa/v2', '/debug-order/(?P<id>\d+)', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'inspect_order' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function scan_database() {
        global $wpdb;
        
        // 1. Contagem por Tipo de Post (Para ver se 'nativa_pedido' existe)
        $post_types = $wpdb->get_results( 
            "SELECT post_type, COUNT(*) as qtd FROM {$wpdb->posts} GROUP BY post_type" 
        );

        // 2. Busca os 5 últimos pedidos da V1 (nativa_pedido) REAIS
        $latest_v1 = $wpdb->get_results( 
            "SELECT ID, post_title, post_status, post_date, post_author 
             FROM {$wpdb->posts} 
             WHERE post_type = 'nativa_pedido' 
             ORDER BY ID DESC LIMIT 5" 
        );

        // 3. Se achou algum, pega os metadados do primeiro para conferirmos as chaves
        $meta_sample = [];
        if ( !empty($latest_v1) ) {
            $sample_id = $latest_v1[0]->ID;
            $meta_sample = get_post_meta($sample_id);
        }

        return new WP_REST_Response([
            'ambiente' => site_url(), // Confirma onde estamos
            'tabela_posts' => $wpdb->posts, // Confirma o prefixo da tabela
            'resumo_conteudo' => $post_types, // Lista o que existe
            'ultimos_pedidos_v1' => $latest_v1, // Mostra IDs reais
            'exemplo_metadados' => $meta_sample // Mostra como os dados estão salvos
        ], 200);
    }

    public function inspect_order( $request ) {
        $order_id = $request->get_param('id');
        $post = get_post( $order_id );

        if ( !$post ) {
            return new WP_REST_Response(['error' => 'Post ID ' . $order_id . ' não existe na tabela wp_posts deste site.'], 404);
        }

        return new WP_REST_Response([
            'dados_basicos' => $post,
            'metadados' => get_post_meta( $order_id ),
            'taxonomias' => wp_get_post_terms( $order_id, 'nativa_order_status' )
        ], 200);
    }
}