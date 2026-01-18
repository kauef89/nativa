<?php
/**
 * Model: CPT de Ruas (Mapeamento Geográfico)
 * CORRIGIDO: Alinhado com a estrutura de dados da V1 para ler o banco existente.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Rua_CPT {

    public function init() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'acf/init', array( $this, 'register_acf_fields' ) );
    }

    public function register_cpt() {
        register_post_type( 'nativa_rua', array(
            'labels' => array(
                'name'          => 'Ruas (Mapeamento)',
                'singular_name' => 'Rua',
                'menu_name'     => 'Ruas',
                'add_new'       => 'Cadastrar Rua',
                'edit_item'     => 'Editar Rua',
                'search_items'  => 'Buscar Rua'
            ),
            'public'      => false,
            'show_ui'     => true,
            'menu_icon'   => 'dashicons-location-alt',
            'supports'    => array( 'title' ),
            'menu_position' => 8
        ));
    }

    public function register_acf_fields() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

        acf_add_local_field_group( array(
            'key' => 'group_nativa_rua_settings', // Key original da V1
            'title' => 'Configurações da Rua',
            'fields' => array(
                array(
                    'key' => 'field_nativa_rua_segmentos', // Key original
                    'label' => 'Segmentos da Rua por Bairro',
                    'name' => 'rua_segmentos', // Name original (CRUCIAL)
                    'type' => 'repeater',
                    'instructions' => 'Defina os segmentos desta rua (V1 Legacy Data).',
                    'button_label' => 'Adicionar Segmento',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_nativa_rua_segmento_bairro', // Key original
                            'label' => 'Bairro Associado',
                            'name' => 'bairro_associado', // Name original
                            'type' => 'post_object',
                            'post_type' => array('nativa_bairro'),
                            'return_format' => 'id',
                            'required' => 1,
                        ),
                        array(
                            'key' => 'field_nativa_rua_segmento_num_inicial', // Key original
                            'label' => 'Número Inicial',
                            'name' => 'numero_inicial', // Name original
                            'type' => 'number', // Mantive number para facilitar calculo
                            'placeholder' => 'Ex: 1',
                            'wrapper' => array('width' => '50'),
                        ),
                        array(
                            'key' => 'field_nativa_rua_segmento_num_final', // Key original
                            'label' => 'Número Final',
                            'name' => 'numero_final', // Name original
                            'type' => 'number',
                            'placeholder' => 'Ex: 99999',
                            'wrapper' => array('width' => '50'),
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array( 'param' => 'post_type', 'operator' => '==', 'value' => 'nativa_rua' ),
                ),
            ),
        ));
    }
}