<?php
/**
 * Model: Campos ACF para Combos (Wizard)
 * Mantém compatibilidade com dados da V1 (nativa_combo_passos).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Combo_Fields {

    public function init() {
        add_action( 'acf/init', array( $this, 'register_fields' ) );
    }

    public function register_fields() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        acf_add_local_field_group( array(
            'key' => 'group_nativa_combo_data',
            'title' => 'Configuração do Combo (Wizard)',
            'fields' => array(
                // Descrição Curta
                array(
                    'key' => 'field_combo_desc',
                    'label' => 'Descrição do Combo',
                    'name' => 'nativa_combo_descricao',
                    'type' => 'textarea',
                    'rows' => 3,
                ),
                // Preço Base
                array(
                    'key' => 'field_combo_price',
                    'label' => 'Preço do Combo (R$)',
                    'name' => 'nativa_combo_preco_base',
                    'type' => 'number',
                    'prepend' => 'R$',
                ),
                // O CORAÇÃO DO WIZARD: Passos
                array(
                    'key' => 'field_combo_steps',
                    'label' => 'Passos do Combo',
                    'name' => 'nativa_combo_passos', // Chave da V1
                    'type' => 'repeater',
                    'button_label' => 'Adicionar Passo (Ex: Escolha a Bebida)',
                    'sub_fields' => array(
                        // Título do Passo
                        array(
                            'key' => 'field_step_title',
                            'label' => 'Título do Passo',
                            'name' => 'passo_titulo',
                            'type' => 'text',
                            'placeholder' => 'Ex: Escolha sua Bebida',
                        ),
                        // Tipo de Seleção (Produto específico ou Categoria inteira)
                        array(
                            'key' => 'field_step_type',
                            'label' => 'Tipo de Seleção',
                            'name' => 'passo_tipo', // Chave nova (V2) para facilitar, mas vamos tentar ler da V1
                            'type' => 'select',
                            'choices' => array(
                                'products' => 'Selecionar Produtos Específicos',
                                'category' => 'Selecionar uma Categoria Inteira'
                            ),
                        ),
                        // Se for Produtos Específicos
                        array(
                            'key' => 'field_step_products',
                            'label' => 'Produtos Permitidos',
                            'name' => 'produtos_permitidos',
                            'type' => 'relationship',
                            'post_type' => array( 'nativa_produto' ),
                            'conditional_logic' => array(
                                array(
                                    array('field' => 'field_step_type', 'operator' => '==', 'value' => 'products'),
                                ),
                            ),
                        ),
                        // Se for Categoria
                        array(
                            'key' => 'field_step_category',
                            'label' => 'Categoria Permitida',
                            'name' => 'categoria_permitida',
                            'type' => 'taxonomy',
                            'taxonomy' => 'category',
                            'field_type' => 'select',
                            'conditional_logic' => array(
                                array(
                                    array('field' => 'field_step_type', 'operator' => '==', 'value' => 'category'),
                                ),
                            ),
                        ),
                        // Quantidade (Quantos pode escolher neste passo?)
                        array(
                            'key' => 'field_step_qty',
                            'label' => 'Quantidade Selecionável',
                            'name' => 'quantidade',
                            'type' => 'number',
                            'default_value' => 1,
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'nativa_combo',
                    ),
                ),
            ),
        ));
    }
}