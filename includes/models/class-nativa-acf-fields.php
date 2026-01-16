<?php
/**
 * Model: Campos ACF (Compatibilidade V1)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_ACF_Fields {

    public function init() {
        add_action( 'acf/init', array( $this, 'register_fields' ) );
    }

    public function register_fields() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        // GRUPO 1: PRODUTO (Preço e Vínculo)
        acf_add_local_field_group( array(
            'key' => 'group_nativa_produto_data',
            'title' => 'Dados do Produto (V1)',
            'fields' => array(
                array(
                    'key' => 'field_produto_preco',
                    'label' => 'Preço (R$)',
                    'name' => 'produto_preco', // Chave V1
                    'type' => 'number',
                    'prepend' => 'R$',
                ),
                array(
                    'key' => 'field_produto_disponibilidade',
                    'label' => 'Disponibilidade',
                    'name' => 'produto_disponibilidade',
                    'type' => 'select',
                    'choices' => array(
                        'disponivel' => 'Disponível',
                        'indisponivel' => 'Esgotado',
                        'oculto' => 'Oculto',
                    ),
                    'default_value' => 'disponivel',
                ),
                // O Campo de Conexão com Adicionais
                array(
                    'key' => 'field_produto_grupos_adicionais',
                    'label' => 'Grupos de Adicionais',
                    'name' => 'produto_grupos_adicionais', // Chave V1
                    'type' => 'relationship',
                    'post_type' => array( 'nativa_adic_grupo' ), // Aponta para o slug correto
                    'return_format' => 'id',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'nativa_produto',
                    ),
                ),
            ),
        ));

        // GRUPO 2: ADICIONAIS (Regras e Itens)
        acf_add_local_field_group( array(
            'key' => 'group_nativa_adicional_config',
            'title' => 'Configuração do Grupo',
            'fields' => array(
                array(
                    'key' => 'field_grupo_tipo',
                    'label' => 'Tipo',
                    'name' => 'grupo_adicional_tipo_grupo', // Chave V1
                    'type' => 'select',
                    'choices' => array(
                        'adicional' => 'Múltipla Escolha',
                        'opcao'     => 'Escolha Única (Radio)',
                    ),
                ),
                array(
                    'key' => 'field_grupo_min',
                    'label' => 'Mínimo',
                    'name' => 'grupo_adicional_min_selecao',
                    'type' => 'number',
                    'default_value' => 0,
                ),
                array(
                    'key' => 'field_grupo_max',
                    'label' => 'Máximo',
                    'name' => 'grupo_adicional_max_selecao',
                    'type' => 'number',
                    'default_value' => 0,
                ),
                // O Repeater de Itens
                array(
                    'key' => 'field_grupo_itens',
                    'label' => 'Itens',
                    'name' => 'grupo_adicional_itens', // Chave V1
                    'type' => 'repeater',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_item_nome',
                            'label' => 'Nome',
                            'name' => 'item_nome',
                            'type' => 'text',
                        ),
                        array(
                            'key' => 'field_item_preco',
                            'label' => 'Preço (+R$)',
                            'name' => 'item_preco',
                            'type' => 'number',
                        ),
                        array(
                            'key' => 'field_item_status',
                            'label' => 'Status',
                            'name' => 'item_disponibilidade',
                            'type' => 'select',
                            'choices' => array('disponivel'=>'Ativo','indisponivel'=>'Esgotado'),
                            'default_value' => 'disponivel',
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'nativa_adic_grupo', // Slug correto
                    ),
                ),
            ),
        ));
    }
}