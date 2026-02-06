<?php
/**
 * Registo de CPTs para Logística de Produção e Impressão
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Logistica_CPT {

    public function init() {
        add_action( 'init', array( $this, 'register_cozinhas' ) );
        add_action( 'init', array( $this, 'register_impressoras' ) );
    }

    // 1. Estações de Cozinha (Slug: nativa_cozinha)
    public function register_cozinhas() {
        register_post_type( 'nativa_cozinha', array(
            'labels' => array(
                'name'          => 'Estações de Cozinha',
                'singular_name' => 'Estação de Cozinha',
                'menu_name'     => 'Cozinhas',
                'add_new'       => 'Nova Estação',
                'edit_item'     => 'Editar Estação'
            ),
            'public'      => false,
            'show_ui'     => true,
            'menu_icon'   => 'dashicons-store',
            'supports'    => array( 'title' ),
            'show_in_rest' => true,
            'menu_position' => 26
        ));
    }

    // 2. Impressoras (Slug: nativa_impressora)
    public function register_impressoras() {
        register_post_type( 'nativa_impressora', array(
            'labels' => array(
                'name'          => 'Impressoras',
                'singular_name' => 'Impressora',
                'menu_name'     => 'Impressoras',
                'add_new'       => 'Nova Impressora',
                'edit_item'     => 'Editar Impressora'
            ),
            'public'      => false,
            'show_ui'     => true,
            'menu_icon'   => 'dashicons-printer',
            'supports'    => array( 'title' ),
            'show_in_rest' => true,
            'menu_position' => 27
        ));
    }
}