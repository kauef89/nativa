<?php
/**
 * Model: Campos ACF para Mesas
 * Define a capacidade e outros detalhes da mesa.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Mesa_Fields {

    public function init() {
        // Gancho específico do ACF para registrar campos via PHP
        add_action( 'acf/init', array( $this, 'register_fields' ) );
    }

    public function register_fields() {
        // Verifica se o ACF está ativo
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        acf_add_local_field_group( array(
            'key' => 'group_nativa_mesa_details',
            'title' => 'Detalhes da Mesa',
            'fields' => array(
                array(
                    'key' => 'field_mesa_capacidade',
                    'label' => 'Capacidade (Lugares)',
                    'name' => 'mesa_capacidade',
                    'type' => 'number',
                    'instructions' => 'Quantas pessoas cabem nesta mesa?',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '50',
                    ),
                    'default_value' => 4,
                    'min' => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'nativa_mesa', // Deve bater com o slug registrado no CPT
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal', // MUDANÇA: 'normal' coloca abaixo do título (mais visível)
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'active' => true,
        ));
    }
}