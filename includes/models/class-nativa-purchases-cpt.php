<?php
/**
 * Model: CPT de Insumos e Locais de Compra
 * Mantém o cadastro de itens de compra separado do cardápio.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Purchases_CPT {

    public function init() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'init', array( $this, 'register_taxonomy' ) );
    }

    public function register_cpt() {
        register_post_type( 'nativa_insumo', array(
            'labels' => array(
                'name'          => 'Insumos / Materiais',
                'singular_name' => 'Insumo',
                'menu_name'     => 'Insumos',
                'add_new'       => 'Cadastrar Insumo',
                'edit_item'     => 'Editar Insumo',
                'search_items'  => 'Buscar Insumo'
            ),
            'public'      => false,
            'show_ui'     => true,
            'menu_icon'   => 'dashicons-cart',
            'supports'    => array( 'title' ), // Apenas o nome do insumo
            'menu_position' => 9
        ));
    }

    public function register_taxonomy() {
        register_taxonomy( 'nativa_local_compra', 'nativa_insumo', array(
            'labels' => array(
                'name'          => 'Locais de Compra',
                'singular_name' => 'Local',
                'menu_name'     => 'Locais',
                'search_items'  => 'Buscar Locais',
                'all_items'     => 'Todos os Locais',
                'edit_item'     => 'Editar Local',
                'update_item'   => 'Atualizar Local',
                'add_new_item'  => 'Adicionar Novo Local',
                'new_item_name' => 'Nome do Novo Local',
            ),
            'hierarchical'      => true, // Checkbox style
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => false,
        ));
    }
}