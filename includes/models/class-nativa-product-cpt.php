<?php
/**
 * Model: CPTs de Produtos e Adicionais (Espelho da V1)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Product_CPT {

    public function init() {
        add_action( 'init', array( $this, 'register_products_cpt' ) );
        add_action( 'init', array( $this, 'register_modifiers_cpt' ) );
    }

    // 1. Produtos (Slug: nativa_produto)
    public function register_products_cpt() {
        register_post_type( 'nativa_produto', array(
            'labels' => array(
                'name'          => 'Cardápio (Produtos)',
                'singular_name' => 'Produto',
                'menu_name'     => 'Cardápio',
                'add_new'       => 'Adicionar Produto',
                'edit_item'     => 'Editar Produto'
            ),
            'public'      => true,
            'show_ui'     => true,
            'menu_icon'   => 'dashicons-food',
            'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
            'rewrite'     => array( 'slug' => 'produto' ),
            'taxonomies'  => array( 'category' )
        ));
    }

    // 2. Grupos de Adicionais (Slug CORRETO do Banco V1: nativa_adic_grupo)
    public function register_modifiers_cpt() {
        register_post_type( 'nativa_adic_grupo', array( // <--- AQUI ESTAVA O ERRO
            'labels' => array(
                'name'          => 'Grupos de Adicionais',
                'singular_name' => 'Grupo',
                'menu_name'     => 'Adicionais',
                'add_new'       => 'Criar Grupo',
                'edit_item'     => 'Editar Grupo'
            ),
            'public'      => false,
            'show_ui'     => true, // Agora vai aparecer no menu!
            'menu_icon'   => 'dashicons-list-view',
            'supports'    => array( 'title' )
        ));
    }
}