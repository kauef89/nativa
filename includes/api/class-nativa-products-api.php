<?php
/**
 * API de Gestão de Produtos (Dashboard)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Products_API {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/products', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_products' ),
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ));

        register_rest_route( 'nativa/v2', '/products/(?P<id>\d+)/quick-update', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'quick_update' ),
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ));

        register_rest_route( 'nativa/v2', '/products/bulk-update', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'bulk_update' ),
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ));
    }

    public function get_products( $request ) {
        $args = array(
            'post_type'      => 'nativa_produto',
            'posts_per_page' => -1,
            'post_status'    => array('publish', 'private'), 
            'orderby'        => 'title',
            'order'          => 'ASC'
        );

        $posts = get_posts( $args );
        $data = array();

        foreach ( $posts as $p ) {
            $price_base = (float) get_post_meta( $p->ID, 'produto_preco', true );
            $stock_active = (bool) get_post_meta( $p->ID, 'nativa_estoque_ativo', true );
            $stock_qty = (int) get_post_meta( $p->ID, 'nativa_estoque_qtd', true );
            $availability = get_post_meta( $p->ID, 'produto_disponibilidade', true ) ?: 'disponivel';
            
            // --- NOVOS CAMPOS ---
            // Se não existir (null/empty), assume true (1) para compatibilidade, exceto 18+
            $meta_del = get_post_meta( $p->ID, 'nativa_exibir_delivery', true );
            $show_delivery = ($meta_del === '' || $meta_del === '1'); 
            
            $meta_mesa = get_post_meta( $p->ID, 'nativa_exibir_mesa', true );
            $show_table = ($meta_mesa === '' || $meta_mesa === '1');

            $is_18 = (bool) get_post_meta( $p->ID, 'nativa_apenas_maiores', true );
            // --------------------

            $img = get_the_post_thumbnail_url( $p->ID, 'thumbnail' );
            $sku = str_pad($p->ID, 4, '0', STR_PAD_LEFT); 

            $terms = get_the_terms( $p->ID, 'category' );
            $cat_name = 'Geral'; 
            
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $term = $terms[0];
                $cat_name = $term->name;
                if ( $term->parent ) {
                    $parent = get_term( $term->parent, 'category' );
                    if ( ! is_wp_error( $parent ) ) {
                        $cat_name = $parent->name . ' > ' . $term->name;
                    }
                }
            }

            $data[] = array(
                'id' => $p->ID,
                'name' => $p->post_title,
                'sku' => $sku,
                'price' => $price_base,
                'image' => $img,
                'manage_stock' => $stock_active,
                'stock_quantity' => $stock_qty,
                'availability' => $availability,
                'category_name' => $cat_name,
                // NOVOS DADOS
                'show_delivery' => $show_delivery,
                'show_table'    => $show_table,
                'is_18_plus'    => $is_18
            );
        }

        return new WP_REST_Response( array( 'success' => true, 'products' => $data ), 200 );
    }

    public function quick_update( $request ) {
        $id = (int) $request->get_param('id');
        $params = $request->get_params();
        $user_id = get_current_user_id();

        if ( ! $id ) return new WP_REST_Response( ['success'=>false, 'message'=>'ID inválido'], 400 );

        if ( isset( $params['availability'] ) ) {
            update_post_meta( $id, 'produto_disponibilidade', sanitize_text_field( $params['availability'] ) );
        }

        // --- NOVOS CAMPOS ---
        if ( isset( $params['show_delivery'] ) ) update_post_meta( $id, 'nativa_exibir_delivery', $params['show_delivery'] ? 1 : 0 );
        if ( isset( $params['show_table'] ) )    update_post_meta( $id, 'nativa_exibir_mesa', $params['show_table'] ? 1 : 0 );
        if ( isset( $params['is_18_plus'] ) )    update_post_meta( $id, 'nativa_apenas_maiores', $params['is_18_plus'] ? 1 : 0 );
        // --------------------

        if ( isset( $params['stock_quantity'] ) ) {
            $new_qty = (float) $params['stock_quantity'];
            update_post_meta( $id, 'nativa_estoque_ativo', 1 );
            $stock_model = new Nativa_Stock();
            $stock_model->adjust_stock( $id, $new_qty, 'adjustment', $user_id, 'Ajuste Rápido via PDV' );
        }

        return new WP_REST_Response( ['success' => true], 200 );
    }

    public function bulk_update( $request ) {
        $params = $request->get_params();
        $ids = isset($params['ids']) ? $params['ids'] : [];
        $data = isset($params['data']) ? $params['data'] : [];
        $user_id = get_current_user_id();

        if ( empty($ids) || !is_array($ids) ) {
            return new WP_REST_Response(['success'=>false, 'message'=>'Nenhum produto selecionado.'], 400);
        }

        $count = 0;
        $stock_model = new Nativa_Stock();

        foreach ( $ids as $id ) {
            $id = (int)$id;
            if ( isset( $data['availability'] ) ) update_post_meta( $id, 'produto_disponibilidade', sanitize_text_field( $data['availability'] ) );
            if ( isset( $data['manage_stock'] ) ) update_post_meta( $id, 'nativa_estoque_ativo', $data['manage_stock'] ? 1 : 0 );
            
            // --- NOVOS CAMPOS (Bulk) ---
            if ( isset( $data['show_delivery'] ) ) update_post_meta( $id, 'nativa_exibir_delivery', $data['show_delivery'] ? 1 : 0 );
            if ( isset( $data['show_table'] ) )    update_post_meta( $id, 'nativa_exibir_mesa', $data['show_table'] ? 1 : 0 );
            // ---------------------------

            if ( isset( $data['stock_quantity'] ) ) {
                $new_qty = (float)$data['stock_quantity'];
                update_post_meta( $id, 'nativa_estoque_ativo', 1 );
                $stock_model->adjust_stock( $id, $new_qty, 'adjustment', $user_id, 'Bulk Update PDV' );
            }
            $count++;
        }
        return new WP_REST_Response(['success'=>true, 'updated_count'=>$count], 200);
    }
}