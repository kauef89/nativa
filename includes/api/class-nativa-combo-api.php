<?php
/**
 * API de Detalhes do Combo (CORRIGIDA)
 * Resolve os passos usando as chaves corretas do Banco de Dados (V1)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Combo_API {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/combo-details', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_combo_details' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function get_combo_details( $request ) {
        $combo_id = $request->get_param( 'id' );
        if ( ! $combo_id ) return new WP_REST_Response( ['error' => 'ID missing'], 400 );

        // CORREÇÃO: O nome do repeater no banco V1 é 'passos_do_combo'
        $steps_count = (int) get_post_meta( $combo_id, 'passos_do_combo', true );
        
        // Fallback: Se não achar, tenta o nome novo (caso tenha criado combos novos na V2)
        if ( !$steps_count ) $steps_count = (int) get_post_meta( $combo_id, 'nativa_combo_passos', true );

        $steps_data = array();

        for ( $i = 0; $i < $steps_count; $i++ ) {
            // Tenta prefixo antigo (V1) primeiro
            $prefix = "passos_do_combo_{$i}_";
            $title = get_post_meta( $combo_id, $prefix . 'passo_titulo', true );
            
            // Se vazio, tenta prefixo novo (V2)
            if ( empty($title) ) {
                $prefix = "nativa_combo_passos_{$i}_";
                $title = get_post_meta( $combo_id, $prefix . 'passo_titulo', true );
            }

            $type = get_post_meta( $combo_id, $prefix . 'passo_tipo', true );
            $qty  = (int) get_post_meta( $combo_id, $prefix . 'quantidade', true ) ?: 1;

            $options = array();

            // Lógica de Produtos
            if ( $type === 'products' || empty($type) ) {
                $rel_raw = get_post_meta( $combo_id, $prefix . 'produtos_permitidos', true );
                $p_ids = maybe_unserialize( $rel_raw );
                
                if ( is_array( $p_ids ) ) {
                    foreach ( $p_ids as $pid ) {
                        $p_data = $this->get_simple_product_data( $pid );
                        if ( $p_data ) $options[] = $p_data;
                    }
                }
            }
            // Lógica de Categoria
            elseif ( $type === 'category' ) {
                $cat_id = get_post_meta( $combo_id, $prefix . 'categoria_permitida', true );
                if ( $cat_id ) {
                    $cat_products = get_posts( array(
                        'post_type' => 'nativa_produto',
                        'numberposts' => -1,
                        'tax_query' => array(
                            array('taxonomy' => 'category', 'field' => 'term_id', 'terms' => $cat_id)
                        )
                    ));
                    foreach ( $cat_products as $p ) {
                        $p_data = $this->get_simple_product_data( $p->ID );
                        if ( $p_data ) $options[] = $p_data;
                    }
                }
            }

            if ( ! empty( $options ) ) {
                $steps_data[] = array(
                    'id'      => $i,
                    'title'   => $title,
                    'max_qty' => $qty,
                    'options' => $options
                );
            }
        }

        return new WP_REST_Response( array(
            'success' => true,
            'combo_id' => $combo_id,
            'steps' => $steps_data
        ), 200 );
    }

    private function get_simple_product_data( $pid ) {
        if ( get_post_status( $pid ) !== 'publish' ) return null;
        $disp = get_post_meta( $pid, 'produto_disponibilidade', true );
        if ( $disp === 'oculto' || $disp === 'indisponivel' ) return null;

        return array(
            'id'        => $pid,
            'name'      => get_the_title( $pid ),
            'image'     => get_the_post_thumbnail_url( $pid, 'thumbnail' ),
            'price'     => (float) get_post_meta( $pid, 'produto_preco', true ),
            'modifiers' => $this->get_modifiers_raw( $pid )
        );
    }

    private function get_modifiers_raw( $product_id ) {
        $raw_relation = get_post_meta( $product_id, 'produto_grupos_adicionais', true );
        $group_ids = maybe_unserialize( $raw_relation );

        if ( empty( $group_ids ) || ! is_array( $group_ids ) ) return array();

        $modifiers_list = array();

        foreach ( $group_ids as $gid ) {
            if ( is_object( $gid ) ) $gid = $gid->ID;
            $gid = intval( $gid );
            if ( get_post_status( $gid ) !== 'publish' ) continue;

            $type = get_post_meta( $gid, 'grupo_adicional_tipo_grupo', true );
            $min  = (int) get_post_meta( $gid, 'grupo_adicional_min_selecao', true );
            $max  = (int) get_post_meta( $gid, 'grupo_adicional_max_selecao', true );
            $free_limit = (int) get_post_meta( $gid, 'grupo_adicional_minimo_gratis', true ); 
            $increment  = (float) get_post_meta( $gid, 'grupo_adicional_preco_sabor_adicional', true );

            if ( $free_limit > 0 || $increment > 0 ) $type = 'ingrediente';

            $display_title = get_post_meta( $gid, 'grupo_adicional_nome_exibicao', true );
            $title = !empty($display_title) ? $display_title : get_the_title( $gid );

            $count = (int) get_post_meta( $gid, 'grupo_adicional_itens', true );
            $group_items = array();

            if ( $count > 0 ) {
                for ( $i = 0; $i < $count; $i++ ) {
                    $nome  = get_post_meta( $gid, "grupo_adicional_itens_{$i}_item_nome", true );
                    $preco = get_post_meta( $gid, "grupo_adicional_itens_{$i}_item_preco", true );
                    $disp  = get_post_meta( $gid, "grupo_adicional_itens_{$i}_item_disponibilidade", true );

                    if ( empty( $nome ) ) continue;
                    $status = $disp ? $disp : 'disponivel';
                    if ( $status === 'oculto' || $status === 'indisponivel' ) continue;

                    $group_items[] = array('name' => $nome, 'price' => (float) $preco);
                }
            }

            if ( ! empty( $group_items ) ) {
                $modifiers_list[] = array(
                    'id' => $gid, 'title' => $title, 'type' => $type,
                    'min' => $min, 'max' => $max, 'free_limit' => $free_limit, 'increment' => $increment,
                    'items' => $group_items
                );
            }
        }
        return $modifiers_list;
    }
}