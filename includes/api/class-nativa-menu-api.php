<?php
/**
 * API do Cardápio (Menu) - Com Suporte a Combos
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

        // 1. INJEÇÃO DE COMBOS (Mantemos como categoria virtual no topo)
        $combos = $this->get_combos();
        if ( ! empty( $combos ) ) {
            $menu_structure[] = array(
                'id'       => 'combos-virtual',
                'name'     => '🔥 Combos e Promoções',
                'slug'     => 'combos',
                'products' => $combos,
                'children' => array() // Combos não tem subcategorias
            );
        }

        // 2. BUSCA CATEGORIAS PAI (parent = 0)
        $parent_categories = get_terms( array(
            'taxonomy'   => 'category',
            'hide_empty' => true,
            'parent'     => 0, // Só as principais
            'orderby'    => 'menu_order',
        ) );

        foreach ( $parent_categories as $parent ) {
            // Se for "Combos" do WP, pula (pois já tratamos acima)
            if ( strtolower($parent->name) === 'combos' ) continue;

            // Busca os produtos DIRETOS desta categoria pai
            $parent_products = $this->get_products_for_term( $parent->term_id );

            // 3. BUSCA SUBCATEGORIAS (Filhos deste Pai)
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
                    $children_data[] = array(
                        'id'       => $child->term_id,
                        'name'     => $child->name,
                        'slug'     => $child->slug,
                        'products' => $child_products,
                        'parent_id'=> $parent->term_id
                    );
                }
            }

            // Só adiciona o Pai se ele tiver produtos OU se tiver filhos com produtos
            if ( ! empty( $parent_products ) || ! empty( $children_data ) ) {
                $menu_structure[] = array(
                    'id'       => $parent->term_id,
                    'name'     => $parent->name,
                    'slug'     => $parent->slug,
                    'products' => $parent_products, // Produtos diretos do pai
                    'children' => $children_data    // Lista de subcategorias
                );
            }
        }

        return new WP_REST_Response( array( 'success' => true, 'menu' => $menu_structure ), 200 );
    }

    /**
     * Helper para buscar produtos de um termo específico
     */
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
                    'include_children' => false // Importante: Não pegar produtos dos filhos automaticamente
                ),
            ),
        ) );

        $products_data = array();
        if ( $products_query->have_posts() ) {
            foreach ( $products_query->posts as $post ) {
                $formatted = $this->format_product_data( $post->ID );
                if ( $formatted ) $products_data[] = $formatted;
            }
        }
        return $products_data;
    }

    /**
     * Busca Combos Ativos
     */
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
                'type'         => 'combo', // Identificador vital para o Frontend
                'name'         => $post->post_title,
                'description'  => get_field( 'nativa_combo_descricao', $post->ID ),
                'price'        => $price,
                'image'        => get_the_post_thumbnail_url( $post->ID, 'medium' ),
                'is_available' => true,
                // Não enviamos os passos aqui para não pesar. Buscaremos via /combo-details
            );
        }
        return $combos;
    }

    // ... Mantenha a função format_product_data() e get_modifiers_raw() iguais ...
    private function format_product_data( $post_id ) {
        // (Copie a função format_product_data da versão anterior aqui)
        // Vou resumir para economizar espaço, mas use a versão completa que já validamos!
        $availability = get_post_meta( $post_id, 'produto_disponibilidade', true );
        if ( ! $availability ) $availability = get_field( 'produto_disponibilidade', $post_id );
        if ( $availability === 'oculto' ) return null;

        $price_base = (float) get_post_meta( $post_id, 'produto_preco', true );
        if ( ! $price_base ) $price_base = (float) get_field( 'produto_preco', $post_id );
        $promo = (float) get_post_meta( $post_id, 'produto_preco_promocional', true );
        $final_price = ($promo > 0 && $promo < $price_base) ? $promo : $price_base;
        $image_url = get_the_post_thumbnail_url( $post_id, 'medium' );
        $modifiers = $this->get_modifiers_raw( $post_id );

        return array(
            'id'           => $post_id,
            'type'         => 'product', // Padrão
            'name'         => get_the_title( $post_id ),
            'description'  => get_the_excerpt( $post_id ),
            'price'        => $final_price,
            'old_price'    => ($promo > 0) ? $price_base : null,
            'image'        => $image_url,
            'is_available' => ($availability !== 'indisponivel'),
            'modifiers'    => $modifiers
        );
    }

    /**
     * Busca os grupos usando APENAS get_post_meta (Ignora helpers do ACF)
     * Isso garante que dados legados sejam lidos mesmo sem re-salvar os posts.
     */
    private function get_modifiers_raw( $product_id ) {
        $raw_relation = get_post_meta( $product_id, 'produto_grupos_adicionais', true );
        $group_ids = maybe_unserialize( $raw_relation );

        if ( empty( $group_ids ) || ! is_array( $group_ids ) ) return array();

        $modifiers_list = array();

        foreach ( $group_ids as $gid ) {
            if ( is_object( $gid ) ) $gid = $gid->ID;
            $gid = intval( $gid );
            if ( get_post_status( $gid ) !== 'publish' ) continue;

            // 1. Configurações Básicas
            $type = get_post_meta( $gid, 'grupo_adicional_tipo_grupo', true );
            $min  = (int) get_post_meta( $gid, 'grupo_adicional_min_selecao', true );
            $max  = (int) get_post_meta( $gid, 'grupo_adicional_max_selecao', true );

            // 2. Configurações de Ingrediente (Lógica da Bolha)
            $free_limit = (int) get_post_meta( $gid, 'grupo_adicional_minimo_gratis', true ); 
            $increment  = (float) get_post_meta( $gid, 'grupo_adicional_preco_sabor_adicional', true );

            // --- A CORREÇÃO MÁGICA ---
            // Se o grupo tem regras de "Bolha" (Incremento ou Grátis), forçamos o tipo para 'ingrediente'
            // Isso sobrescreve o 'adicional' genérico do banco e ativa o Tag Cloud no Vue.
            if ( $free_limit > 0 || $increment > 0 ) {
                $type = 'ingrediente';
            }

            // Título
            $display_title = get_post_meta( $gid, 'grupo_adicional_nome_exibicao', true );
            $title = !empty($display_title) ? $display_title : get_the_title( $gid );

            // 3. Itens
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

                    $group_items[] = array(
                        'name'  => $nome,
                        'price' => (float) $preco,
                    );
                }
            }

            if ( ! empty( $group_items ) ) {
                $modifiers_list[] = array(
                    'id'         => $gid,
                    'title'      => $title,
                    'type'       => $type, // 'adicional', 'opcao' ou 'sabor' (que trataremos como Ingrediente)
                    'min'        => $min,
                    'max'        => $max,
                    'free_limit' => $free_limit, // NOVO
                    'increment'  => $increment,  // NOVO
                    'items'      => $group_items
                );
            }
        }
        return $modifiers_list;
    }
}