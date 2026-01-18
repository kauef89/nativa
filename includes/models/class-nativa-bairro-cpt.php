<?php
/**
 * Model: CPT de Bairros e Taxas
 * Baseado na V1, mas limpo para a V2.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Bairro_CPT {

    public function init() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'acf/init', array( $this, 'register_acf_fields' ) );
    }

    public function register_cpt() {
        register_post_type( 'nativa_bairro', array(
            'labels' => array(
                'name'          => 'Bairros e Taxas',
                'singular_name' => 'Bairro',
                'menu_name'     => 'Bairros e Taxas',
                'add_new'       => 'Adicionar Bairro',
                'edit_item'     => 'Editar Taxa'
            ),
            'public'      => false, // Não precisa de página no site
            'show_ui'     => true,  // Aparece no admin
            'menu_icon'   => 'dashicons-location',
            'supports'    => array( 'title' ),
            'menu_position' => 7
        ));
    }

    public function register_acf_fields() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

        acf_add_local_field_group( array(
            'key' => 'group_nativa_bairro_config',
            'title' => 'Configuração da Taxa',
            'fields' => array(
                array(
                    'key' => 'field_taxa_entrega',
                    'label' => 'Valor da Taxa (R$)',
                    'name' => 'taxa_entrega',
                    'type' => 'number',
                    'prepend' => 'R$',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_frete_gratis',
                    'label' => 'Valor Mínimo para Frete Grátis',
                    'name' => 'valor_minimo_frete_gratis',
                    'type' => 'number',
                    'prepend' => 'R$',
                    'instructions' => 'Deixe 0 se não tiver isenção.',
                    'default_value' => 0
                ),
            ),
            'location' => array(
                array(
                    array( 'param' => 'post_type', 'operator' => '==', 'value' => 'nativa_bairro' ),
                ),
            ),
        ));
    }
}