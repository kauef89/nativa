<?php
/**
 * Model: Campos ACF (Compatibilidade V1 + V2)
 * Define todos os grupos de campos personalizados do sistema.
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

        // =================================================================
        // GRUPO 1: DADOS DO PRODUTO (Preço, Vínculo e Estoque)
        // =================================================================
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
                // --- LOGÍSTICA & KDS (GRAB & GO) ---
                array(
                    'key' => 'field_nativa_requires_preparation',
                    'label' => 'Requer Preparo na Cozinha?',
                    'name' => 'nativa_requires_preparation',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 1, 
                    'message' => 'Desmarque para itens prontos (bebidas lata, doces)',
                    'wrapper' => array('width' => '100'),
                ),
                // --- ESTOQUE AVANÇADO ---
                array(
                    'key' => 'field_estoque_controlado',
                    'label' => 'Controlar Estoque?',
                    'name' => 'nativa_stock_controlled', // Nova Flag Mestra
                    'type' => 'true_false',
                    'ui' => 1,
                    'message' => 'Sim, gerenciar quantidades',
                ),
                array(
                    'key' => 'field_estoque_qtd',
                    'label' => 'Quantidade Atual',
                    'name' => 'nativa_estoque_qtd',
                    'type' => 'number',
                    'default_value' => 0,
                    'conditional_logic' => array(
                        array(
                            array('field' => 'field_estoque_controlado', 'operator' => '==', 'value' => '1'),
                        ),
                    ),
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_estoque_min',
                    'label' => 'Margem de Segurança (Mínimo)',
                    'name' => 'nativa_stock_min',
                    'type' => 'number',
                    'default_value' => 5,
                    'instructions' => 'Avisa no ActivityFeed quando atingir este valor.',
                    'conditional_logic' => array(
                        array(
                            array('field' => 'field_estoque_controlado', 'operator' => '==', 'value' => '1'),
                        ),
                    ),
                    'wrapper' => array('width' => '50'),
                ),
                // ------------------------------------
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
                array(
                    'key' => 'field_tab_fiscal',
                    'label' => 'Fiscal (NFC-e)',
                    'type' => 'tab',
                ),
                array(
                    'key' => 'field_prod_ncm',
                    'label' => 'NCM (Obrigatório)',
                    'name' => 'nativa_prod_ncm',
                    'type' => 'text',
                    'instructions' => 'Ex: 21069090 (Apenas números). Procure na tabela TIPI.',
                    'required' => 0, 
                    'maxlength' => 8,
                ),
                array(
                    'key' => 'field_prod_cest',
                    'label' => 'CEST (Se houver Substituição Tributária)',
                    'name' => 'nativa_prod_cest',
                    'type' => 'text',
                    'maxlength' => 7,
                ),
                array(
                    'key' => 'field_prod_cfop',
                    'label' => 'CFOP',
                    'name' => 'nativa_prod_cfop',
                    'type' => 'text',
                    'default_value' => '5102', 
                    'maxlength' => 4,
                ),
                array(
                    'key' => 'field_prod_origem',
                    'label' => 'Origem da Mercadoria',
                    'name' => 'nativa_prod_origem',
                    'type' => 'select',
                    'choices' => array(
                        '0' => '0 - Nacional',
                        '1' => '1 - Estrangeira (Importação direta)',
                        '2' => '2 - Estrangeira (Adquirida no mercado interno)',
                    ),
                    'default_value' => '0',
                ),
                // Campos de Tributação Simplificados (Simples Nacional)
                array(
                    'key' => 'field_prod_csosn',
                    'label' => 'CSOSN (Simples Nacional)',
                    'name' => 'nativa_prod_csosn',
                    'type' => 'select',
                    'choices' => array(
                        '102' => '102 - Tributada sem permissão de crédito',
                        '500' => '500 - ICMS cobrado anteriormente (ST)',
                        '900' => '900 - Outros',
                    ),
                    'default_value' => '102',
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

        // =================================================================
        // GRUPO 2: CONFIGURAÇÃO DE ADICIONAIS (ATUALIZADO)
        // =================================================================
        acf_add_local_field_group( array(
            'key' => 'group_nativa_adicional_config',
            'title' => 'Configuração do Grupo',
            'fields' => array(
                // --- NOVOS CAMPOS DE VISIBILIDADE DE GRUPO ---
                array(
                    'key' => 'field_grupo_ativo',
                    'label' => 'Ativo?',
                    'name' => 'nativa_grupo_ativo',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 1,
                    'wrapper' => array('width' => '25'),
                ),
                array(
                    'key' => 'field_grupo_show_delivery',
                    'label' => 'Exibir no Delivery?',
                    'name' => 'nativa_grupo_show_delivery',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 1,
                    'wrapper' => array('width' => '25'),
                ),
                array(
                    'key' => 'field_grupo_show_table',
                    'label' => 'Exibir na Mesa?',
                    'name' => 'nativa_grupo_show_table',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 1,
                    'wrapper' => array('width' => '25'),
                ),
                // --- NOVO CAMPO DE QUANTIDADE ---
                array(
                    'key' => 'field_grupo_enable_qty',
                    'label' => 'Habilita Quantidade?',
                    'name' => 'nativa_grupo_enable_qty',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 0,
                    'message' => 'Permitir selecionar +1 do mesmo item',
                    'wrapper' => array('width' => '25'),
                ),
                // ---------------------------------------------
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
                        'value' => 'nativa_adic_grupo',
                    ),
                ),
            ),
        ));

        // =================================================================
        // GRUPO 3: CONFIGURAÇÕES DA OFERTA (UPSELL - V2 Lógica Avançada)
        // =================================================================
        acf_add_local_field_group( array(
            'key' => 'group_nativa_oferta_settings',
            'title' => 'Configurações da Oferta (Upsell)',
            'fields' => array(
                // Aba 1: O que ofertar? (Mantido igual)
                array(
                    'key' => 'field_oferta_tab_produto',
                    'label' => 'Produto e Preço',
                    'type' => 'tab',
                ),
                array(
                    'key' => 'field_oferta_produto_ofertado',
                    'label' => 'Produto a ser Ofertado',
                    'name' => 'produto_ofertado',
                    'type' => 'post_object',
                    'post_type' => array('nativa_produto'),
                    'return_format' => 'id',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_oferta_preco_promocional',
                    'label' => 'Preço na Oferta (R$)',
                    'name' => 'preco_promocional',
                    'type' => 'number',
                    'prepend' => 'R$',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_oferta_texto_chamada',
                    'label' => 'Frase de Chamada',
                    'name' => 'texto_da_oferta',
                    'type' => 'text',
                    'default_value' => 'Uma oferta especial para você!',
                ),
                
                // Aba 2: Regras Avançadas
                array(
                    'key' => 'field_oferta_tab_regras',
                    'label' => 'Regras de Ativação',
                    'type' => 'tab',
                ),
                array(
                    'key' => 'field_oferta_grupos_regras',
                    'label' => 'Grupos de Condições (Lógica OU)',
                    'name' => 'grupos_de_regras',
                    'type' => 'repeater',
                    'button_label' => 'Adicionar Grupo de Regras (OU)',
                    'instructions' => 'A oferta será ativada se PELO MENOS UM destes grupos for atendido.',
                    'layout' => 'block', 
                    'sub_fields' => array(
                        array(
                            'key' => 'field_oferta_regras_internas',
                            'label' => 'Condições deste Grupo (Lógica E)',
                            'name' => 'regras',
                            'type' => 'repeater',
                            'button_label' => 'Adicionar Condição',
                            'instructions' => 'Todas as condições deste grupo devem ser verdadeiras.',
                            'layout' => 'table', 
                            'sub_fields' => array(
                                array(
                                    'key' => 'field_regra_tipo',
                                    'label' => 'Tipo',
                                    'name' => 'tipo_regra',
                                    'type' => 'select',
                                    'choices' => array(
                                        'subtotal_carrinho' => 'Subtotal do Carrinho',
                                        'total_itens_carrinho' => 'Qtd. Itens no Carrinho',
                                        'categoria_no_carrinho' => 'Contém Categoria',
                                        'produto_no_carrinho' => 'Contém Produto',
                                    ),
                                ),
                                array(
                                    'key' => 'field_regra_operador',
                                    'label' => 'Operador',
                                    'name' => 'operador',
                                    'type' => 'select',
                                    'choices' => array(
                                        'maior_igual' => '>= (Maior ou Igual)',
                                        'menor_igual' => '<= (Menor ou Igual)',
                                        'maior'       => '> (Maior que)',
                                        'menor'       => '< (Menor que)',
                                        'igual'       => '= (Igual)',
                                        'diferente'   => '!= (Diferente)',
                                        'contem'      => 'Contém',
                                        'nao_contem'  => 'Não Contém',
                                    ),
                                ),
                                array(
                                    'key' => 'field_regra_valor',
                                    'label' => 'Valor',
                                    'name' => 'valor',
                                    'type' => 'number',
                                ),
                                array(
                                    'key' => 'field_regra_categoria',
                                    'label' => 'Categoria',
                                    'name' => 'valor_categoria',
                                    'type' => 'taxonomy',
                                    'taxonomy' => 'category',
                                    'return_format' => 'id',
                                    'field_type' => 'select',
                                    'conditional_logic' => array(
                                        array(array('field' => 'field_regra_tipo', 'operator' => '==', 'value' => 'categoria_no_carrinho')),
                                    ),
                                ),
                                array(
                                    'key' => 'field_regra_produto',
                                    'label' => 'Produto',
                                    'name' => 'valor_produto',
                                    'type' => 'post_object',
                                    'post_type' => array('nativa_produto'),
                                    'return_format' => 'id',
                                    'conditional_logic' => array(
                                        array(array('field' => 'field_regra_tipo', 'operator' => '==', 'value' => 'produto_no_carrinho')),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),

                // Aba 3: Status
                array(
                    'key' => 'field_oferta_tab_status',
                    'label' => 'Status',
                    'type' => 'tab',
                ),
                array(
                    'key' => 'field_oferta_status',
                    'label' => 'Ativa?',
                    'name' => 'oferta_status',
                    'type' => 'true_false',
                    'default_value' => 1,
                    'ui' => 1,
                ),
                array(
                    'key' => 'field_oferta_limite_global',
                    'label' => 'Limite Global',
                    'name' => 'limite_total_usos',
                    'type' => 'number',
                    'default_value' => 0,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'nativa_oferta',
                    ),
                ),
            ),
        ));
    }
}