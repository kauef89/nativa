<?php
/**
 * Campos ACF para Logística (Correção de Rota: Cozinha define Impressoras)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Logistica_Fields {

    public function init() {
        add_action( 'acf/init', array( $this, 'register_fields' ) );
    }

    public function register_fields() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

        // 1. Configuração da Impressora (Apenas dados técnicos)
        acf_add_local_field_group(array(
            'key' => 'group_nativa_impressora_tech',
            'title' => 'Dados de Conexão',
            'fields' => array(
                array(
                    'key' => 'field_imp_tipo',
                    'label' => 'Interface',
                    'name' => 'impressora_tipo',
                    'type' => 'select',
                    'choices' => array('ethernet' => 'Rede (TCP/IP)', 'usb' => 'USB Local', 'windows' => 'Spooler Windows'),
                    'default_value' => 'ethernet',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_imp_ip',
                    'label' => 'Caminho / IP',
                    'name' => 'impressora_caminho',
                    'type' => 'text',
                    'instructions' => 'IP (192.168...) ou Nome do Compartilhamento',
                    'required' => 0,
                    'wrapper' => array('width' => '50'),
                ),
            ),
            'location' => array(array(array('param' => 'post_type', 'operator' => '==', 'value' => 'nativa_impressora'))),
        ));

        // 2. Configuração da Cozinha (Define quais impressoras usa)
        acf_add_local_field_group(array(
            'key' => 'group_nativa_cozinha_hardware',
            'title' => 'Hardware de Saída',
            'fields' => array(
                array(
                    'key' => 'field_cozinha_impressoras',
                    'label' => 'Impressoras de Produção',
                    'name' => 'cozinha_impressoras_vinculo',
                    'type' => 'relationship', // Permite selecionar várias impressoras
                    'post_type' => array('nativa_impressora'),
                    'return_format' => 'id',
                    'instructions' => 'Selecione as impressoras onde os pedidos desta cozinha devem sair.',
                )
            ),
            'location' => array(array(array('param' => 'post_type', 'operator' => '==', 'value' => 'nativa_cozinha'))),
        ));

        // 3. Vínculo no Produto (Mantido)
        acf_add_local_field_group(array(
            'key' => 'group_nativa_produto_logistica',
            'title' => 'Logística de Produção',
            'fields' => array(
                array(
                    'key' => 'field_nativa_estacao_impressao_v2',
                    'label' => 'Cozinha Responsável',
                    'name' => 'nativa_estacao_impressao',
                    'type' => 'post_object',
                    'post_type' => array('nativa_cozinha'),
                    'return_format' => 'id',
                    'required' => 0,
                )
            ),
            'location' => array(array(array('param' => 'post_type', 'operator' => '==', 'value' => 'nativa_produto'))),
            'position' => 'side',
        ));
    }
}