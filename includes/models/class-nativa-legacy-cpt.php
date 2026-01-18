<?php
/**
 * Registro de CPTs Legados (V1)
 * Necessário para importar o histórico antigo sem erros.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Legacy_CPT {

    public function init() {
        add_action( 'init', array( $this, 'register_legacy_structures' ) );
    }

    public function register_legacy_structures() {
        // 1. O Tipo de Post "Pedido V1"
        register_post_type( 'nativa_pedido', array(
            'labels'              => array( 'name' => 'Pedidos V1 (Legado)' ),
            'public'              => true, // Importante para o importador ver
            'show_ui'             => true, // Aparece no menu admin (útil para debug)
            'show_in_menu'        => true,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'hierarchical'        => false,
            'supports'            => array( 'title', 'editor', 'author', 'custom-fields' ), // 'custom-fields' é vital para os metadados!
            'has_archive'         => false,
            'rewrite'             => array( 'slug' => 'nativa-pedido-legacy' ),
        ) );

        // 2. A Taxonomia de Status da V1
        register_taxonomy( 'nativa_order_status', array( 'nativa_pedido' ), array(
            'labels'            => array( 'name' => 'Status V1' ),
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'status-pedido-v1' ),
        ) );
    }
}