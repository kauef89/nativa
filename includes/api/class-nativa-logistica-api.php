<?php
/**
 * API de Logística (Atualizada: Cozinha -> Impressoras)
 * CORREÇÃO: Uso de get_post_meta para garantir leitura dos relacionamentos.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Logistica_API {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/logistica/cozinhas', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_cozinhas' ),
            'permission_callback' => '__return_true',
        ));

        register_rest_route( 'nativa/v2', '/logistica/impressoras', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_mapeamento' ),
            'permission_callback' => '__return_true',
        ));
    }

    public function get_cozinhas() {
        $posts = get_posts( array(
            'post_type'      => 'nativa_cozinha',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC'
        ));

        $cozinhas = array();
        foreach ( $posts as $p ) {
            $cozinhas[] = array( 'id' => $p->ID, 'name' => $p->post_title );
        }
        return new WP_REST_Response( array( 'success' => true, 'cozinhas' => $cozinhas ), 200 );
    }

    /**
     * Gera o mapa: ID da Cozinha => Lista de Configs de Impressora
     */
    public function get_mapeamento() {
        $cozinhas = get_posts( array(
            'post_type'      => 'nativa_cozinha',
            'posts_per_page' => -1,
            'post_status'    => 'publish'
        ));

        $mapeamento = array();

        foreach ( $cozinhas as $cozinha ) {
            // 1. Busca os IDs das impressoras vinculadas (Native Meta)
            // O ACF salva relacionamentos como array serializado no banco
            $raw_relation = get_post_meta( $cozinha->ID, 'cozinha_impressoras_vinculo', true );
            $impressoras_ids = maybe_unserialize( $raw_relation );

            // Se estiver vazio, tenta buscar como string simples (caso seja 1-pra-1 antigo)
            if ( empty($impressoras_ids) && is_numeric($raw_relation) ) {
                $impressoras_ids = array( $raw_relation );
            }

            if ( ! empty( $impressoras_ids ) && is_array( $impressoras_ids ) ) {
                foreach ( $impressoras_ids as $imp_id ) {
                    // Garante que é um ID válido
                    $imp_id = intval($imp_id);
                    if (!$imp_id) continue;

                    // 2. Busca dados da impressora (Native Meta)
                    $tipo    = get_post_meta( $imp_id, 'impressora_tipo', true ) ?: 'ethernet';
                    $caminho = get_post_meta( $imp_id, 'impressora_caminho', true );

                    // Adiciona ao mapa mesmo se o caminho estiver vazio (para debug no front)
                    $mapeamento[] = array(
                        'cozinha_id' => $cozinha->ID,
                        'printer_id' => $imp_id,
                        'name'       => get_the_title( $imp_id ),
                        'interface'  => $tipo,
                        'path'       => $caminho ?: '' // Envia string vazia se nulo
                    );
                }
            }
        }

        return new WP_REST_Response( array( 'success' => true, 'impressoras' => $mapeamento ), 200 );
    }
}