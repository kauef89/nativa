<?php
/**
 * API do Cardápio (Menu) - Com Filtros de Visibilidade e 18+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Menu_API {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/menu', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_full_menu' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function get_full_menu() {
        $menu_structure = array();

        // 1. COMBOS (Categoria Virtual)
        $combos = $this->get_combos();
        if ( ! empty( $combos ) ) {
            $menu_structure[] = array(
                'id'       => 'combos-virtual',
                'name'     => '🔥 Combos e Promoções',
                'slug'     => 'combos',
                'image'    => null, 
                'products' => $combos,
                'children' => array()
            );
        }

        // 2. CATEGORIAS PAI
        $parent_categories = get_terms( array(
            'taxonomy'   => 'category',
            'hide_empty' => true,
            'parent'     => 0,
            'orderby'    => 'menu_order',
        ) );

        foreach ( $parent_categories as $parent ) {
            if ( strtolower($parent->name) === 'combos' ) continue;

            $parent_products = $this->get_products_for_term( $parent->term_id );
            
            $thumb_id = get_term_meta( $parent->term_id, 'thumbnail_id', true );
            $image_url = $thumb_id ? wp_get_attachment_url( $thumb_id ) : null;

            // 3. SUBCATEGORIAS
            $children_categories = get_terms( array(
                'taxonomy'   => 'category',
                'hide_empty' => true,
                'parent'     => $parent->term_id,
                'orderby'    => 'menu_order',
            ) );

            $children_data = array();
            
            foreach ( $children_categories as $child ) {
                $child_products = $this->get_products_for_term( $child->term_id );
                
                if ( ! empty( $child_products ) ) {
                    $child_thumb = get_term_meta( $child->term_id, 'thumbnail_id', true );
                    $children_data[] = array(
                        'id'       => $child->term_id,
                        'name'     => $child->name,
                        'slug'     => $child->slug,
                        'image'    => $child_thumb ? wp_get_attachment_url( $child_thumb ) : null,
                        'products' => $child_products,
                        'parent_id'=> $parent->term_id
                    );
                }
            }

            if ( ! empty( $parent_products ) || ! empty( $children_data ) ) {
                $menu_structure[] = array(
                    'id'       => $parent->term_id,
                    'name'     => $parent->name,
                    'slug'     => $parent->slug,
                    'image'    => $image_url,
                    'products' => $parent_products,
                    'children' => $children_data
                );
            }
        }

        return new WP_REST_Response( array( 'success' => true, 'menu' => $menu_structure ), 200 );
    }

    private function get_products_for_term( $term_id ) {
        $products_query = new WP_Query( array(
            'post_type'      => 'nativa_produto',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'tax_query'      => array(
                array(
                    'taxonomy' => 'category',
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                    'include_children' => false 
                ),
            ),
        ) );

        $products_data = array();
        if ( $products_query->have_posts() ) {
            foreach ( $products_query->posts as $post ) {
                $formatted = $this->format_product_data( $post->ID );
                // Só adiciona se o formatador retornar dados (ele retorna null se for oculto ou não delivery)
                if ( $formatted ) $products_data[] = $formatted;
            }
        }
        return $products_data;
    }

    private function get_combos() {
        $query = new WP_Query( array(
            'post_type'      => 'nativa_combo',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ) );

        $combos = array();
        foreach ( $query->posts as $post ) {
            $price = (float) get_field( 'nativa_combo_preco_base', $post->ID );
            $combos[] = array(
                'id'           => $post->ID,
                'type'         => 'combo',
                'name'         => $post->post_title,
                'description'  => get_field( 'nativa_combo_descricao', $post->ID ),
                'price'        => $price,
                'image'        => get_the_post_thumbnail_url( $post->ID, 'medium' ),
                'is_available' => true,
                'is_18_plus'   => false // Combos geralmente não são 18+ por padrão, mas pode ajustar
            );
        }
        return $combos;
    }

    private function format_product_data( $post_id ) {
        // 1. Checagem de Visibilidade Delivery
        $show_delivery = get_post_meta( $post_id, 'nativa_exibir_delivery', true );
        // Se for string vazia, assume true (legado). Se for '0', é false.
        if ( $show_delivery === '0' ) return null;

        // 2. Checagem de Status
        $availability = get_post_meta( $post_id, 'produto_disponibilidade', true );
        if ( $availability === 'oculto' ) return null;

        $price_base = (float) get_post_meta( $post_id, 'produto_preco', true );
        $promo = (float) get_post_meta( $post_id, 'produto_preco_promocional', true );
        $final_price = ($promo > 0 && $promo < $price_base) ? $promo : $price_base;
        
        $image_url = get_the_post_thumbnail_url( $post_id, 'medium' );
        $modifiers = $this->get_modifiers_raw( $post_id );

        // 3. Flag 18+
        $is_18 = (bool) get_post_meta( $post_id, 'nativa_apenas_maiores', true );

        return array(
            'id'           => $post_id,
            'type'         => 'product',
            'name'         => get_the_title( $post_id ),
            'description'  => get_the_excerpt( $post_id ),
            'price'        => $final_price,
            'old_price'    => ($promo > 0) ? $price_base : null,
            'image'        => $image_url,
            'is_available' => ($availability !== 'indisponivel'),
            'is_18_plus'   => $is_18, // <--- Enviado para o Front
            'modifiers'    => $modifiers
        );
    }

    // ... (get_modifiers_raw mantém-se igual) ...
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