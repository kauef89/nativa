<?php
/**
 * Model: CPT de Combos (nativa_combo)
 * Estrutura herdada da V1.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Combo_CPT {

    public function init() {
        add_action( 'init', array( $this, 'register_cpt' ) );
    }

    public function register_cpt() {
        register_post_type( 'nativa_combo', array(
            'labels' => array(
                'name'          => 'Combos',
                'singular_name' => 'Combo',
                'menu_name'     => 'Combos',
                'add_new'       => 'Criar Combo',
                'edit_item'     => 'Editar Combo'
            ),
            'public'      => true,
            'show_ui'     => true,
            'menu_icon'   => 'dashicons-grid-view', // Ícone de grid para combos
            'supports'    => array( 'title', 'thumbnail' ), // Título e Foto
            'rewrite'     => array( 'slug' => 'combo' ),
            'menu_position' => 6
        ));
    }
}