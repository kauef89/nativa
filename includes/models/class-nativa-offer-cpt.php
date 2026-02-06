<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Offer_CPT {

    public function init() {
        add_action( 'init', array( $this, 'register_cpt' ) );
    }

    public function register_cpt() {
        $labels = array(
            'name'               => 'Ofertas (Upsell)',
            'singular_name'      => 'Oferta',
            'menu_name'          => 'Ofertas de Carrinho',
            'add_new'            => 'Nova Oferta',
            'add_new_item'       => 'Adicionar Nova Oferta',
            'edit_item'          => 'Editar Oferta',
            'new_item'           => 'Nova Oferta',
            'view_item'          => 'Ver Oferta',
            'search_items'       => 'Buscar Ofertas',
            'not_found'          => 'Nenhuma oferta encontrada',
            'not_found_in_trash' => 'Nenhuma oferta na lixeira'
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false, // Não é acessível via URL direta
            'show_ui'             => true,  // Mostra no Admin
            'show_in_menu'        => true,
            'menu_position'       => 25,
            'menu_icon'           => 'dashicons-megaphone',
            'supports'            => array( 'title' ),
            'has_archive'         => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'show_in_rest'        => true, // Importante para o ACF funcionar bem
        );

        register_post_type( 'nativa_oferta', $args );
    }
}