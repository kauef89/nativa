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

        // GRUPO 1: PRODUTO (Preço, Vínculo e ESTOQUE)
        acf_add_local_field_group( array(
            'key' => 'group_nativa_produto_data',
            'title' => 'Dados do Produto (V1 + V2)',
            'fields' => array(
                array(
                    'key' => 'field_produto_preco',
                    'label' => 'Preço (R$)',
                    'name' => 'produto_preco', // Chave V1
                    'type' => 'number',
                    'prepend' => 'R$',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_produto_promocional',
                    'label' => 'Preço Promocional (R$)',
                    'name' => 'produto_preco_promocional',
                    'type' => 'number',
                    'prepend' => 'R$',
                    'wrapper' => array('width' => '50'),
                ),
                // --- NOVOS CAMPOS DE VISIBILIDADE ---
                array(
                    'key' => 'field_nativa_exibir_delivery',
                    'label' => 'Exibir no Delivery (App/Site)',
                    'name' => 'nativa_exibir_delivery',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 1, // Padrão: Visível
                    'wrapper' => array('width' => '33'),
                ),
                array(
                    'key' => 'field_nativa_exibir_mesa',
                    'label' => 'Exibir na Mesa (Garçom)',
                    'name' => 'nativa_exibir_mesa',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 1, // Padrão: Visível
                    'wrapper' => array('width' => '33'),
                ),
                array(
                    'key' => 'field_nativa_apenas_maiores',
                    'label' => 'Apenas Maiores de 18 (Alcoólico)',
                    'name' => 'nativa_apenas_maiores',
                    'type' => 'true_false',
                    'ui' => 1,
                    'message' => 'Restringir idade',
                    'default_value' => 0,
                    'wrapper' => array('width' => '33'),
                ),
                // ------------------------------------
                array(
                    'key' => 'field_estoque_ativo',
                    'label' => 'Gerenciar Estoque?',
                    'name' => 'nativa_estoque_ativo',
                    'type' => 'true_false',
                    'ui' => 1,
                    'message' => 'Sim, controlar quantidade deste item',
                ),
                array(
                    'key' => 'field_estoque_qtd',
                    'label' => 'Quantidade Atual',
                    'name' => 'nativa_estoque_qtd',
                    'type' => 'number',
                    'default_value' => 0,
                    'conditional_logic' => array(
                        array(
                            array('field' => 'field_estoque_ativo', 'operator' => '==', 'value' => '1'),
                        ),
                    ),
                ),
                array(
                    'key' => 'field_produto_disponibilidade',
                    'label' => 'Disponibilidade (Manual)',
                    'name' => 'produto_disponibilidade',
                    'type' => 'select',
                    'choices' => array(
                        'disponivel' => 'Disponível',
                        'indisponivel' => 'Esgotado',
                        'oculto' => 'Oculto',
                    ),
                    'default_value' => 'disponivel',
                    'instructions' => 'Se o estoque estiver ativo e zerado, o sistema tratará como Esgotado automaticamente.',
                ),
                // O Campo de Conexão com Adicionais
                array(
                    'key' => 'field_produto_grupos_adicionais',
                    'label' => 'Grupos de Adicionais',
                    'name' => 'produto_grupos_adicionais', // Chave V1
                    'type' => 'relationship',
                    'post_type' => array( 'nativa_adic_grupo' ), 
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