<?php
/**
 * Model: Métodos de Pagamento (V2 com Categorias e Canais)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Payment_Method {

    public function init() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'init', array( $this, 'register_taxonomy' ) );
        add_action( 'acf/init', array( $this, 'register_acf_fields' ) );
        add_action( 'rest_api_init', array( $this, 'register_api_routes' ) );
    }

    public function register_cpt() {
        register_post_type( 'nativa_payment', array(
            'labels' => array(
                'name'               => 'Formas de Pagamento',
                'singular_name'      => 'Forma de Pagamento',
                'menu_name'          => 'Pagamentos',
                'add_new'            => 'Adicionar Nova',
                'add_new_item'       => 'Adicionar Nova Forma',
                'edit_item'          => 'Editar Forma de Pagamento',
                'new_item'           => 'Nova Forma',
                'view_item'          => 'Ver Forma',
                'search_items'       => 'Buscar Formas',
                'all_items'          => 'Todas as Formas'
            ),
            'public'              => false, 
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => true,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'hierarchical'        => false,
            'query_var'           => true,
            'menu_icon'           => 'dashicons-money-alt',
            'supports'            => array( 'title' ),
            'menu_position'       => 5,
        ));
    }
    
    public function register_taxonomy() {
        register_taxonomy( 'nativa_payment_cat', 'nativa_payment', array(
            'label'        => 'Categorias de Pagamento',
            'hierarchical' => true,
            'show_ui'      => true,
            'show_admin_column' => true,
            'query_var'    => true,
            'rewrite'      => false,
        ));
    }

    public function register_acf_fields() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

        acf_add_local_field_group( array(
            'key' => 'group_nativa_payment_settings',
            'title' => 'Configurações Avançadas de Pagamento',
            'fields' => array(
                // 1. COMPORTAMENTO
                array(
                    'key' => 'field_payment_type',
                    'label' => 'Tipo de Comportamento (Engine)',
                    'name' => 'nativa_payment_type',
                    'type' => 'select',
                    'instructions' => 'Define se o sistema deve abrir TEF, gerar QR Code Pix ou apenas registrar.',
                    'choices' => array(
                        'money'       => 'Dinheiro (Habilita Troco)',
                        'pix_sicredi' => 'Pix Sicredi (Automático)',
                        'tef'         => 'TEF (Cartão Integrado)',
                        'manual'      => 'Manual (Maquininha/Outros)',
                        'loyalty'     => 'Fidelidade (Gasta Pontos)',
                        'loss'        => 'Perda/Cortesia'
                    ),
                    'default_value' => 'manual',
                    'wrapper' => array('width' => '50'),
                ),
                array(
                    'key' => 'field_payment_icon',
                    'label' => 'Ícone (FontAwesome)',
                    'name' => 'nativa_payment_icon',
                    'type' => 'text',
                    'default_value' => 'fa-solid fa-credit-card',
                    'wrapper' => array('width' => '50'),
                ),
                // 2. EXIBIÇÃO (CANAIS)
                array(
                    'key' => 'field_show_on_pos',
                    'label' => 'Exibir no Caixa (PDV)?',
                    'name' => 'show_on_pos',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 1,
                    'wrapper' => array('width' => '33'),
                ),
                array(
                    'key' => 'field_show_on_delivery',
                    'label' => 'Exibir no Delivery?',
                    'name' => 'show_on_delivery',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 1,
                    'wrapper' => array('width' => '33'),
                ),
                array(
                    'key' => 'field_is_active',
                    'label' => 'Método Ativo?',
                    'name' => 'is_active',
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 1,
                    'wrapper' => array('width' => '34'),
                ),
            ),
            'location' => array(
                array(
                    array( 'param' => 'post_type', 'operator' => '==', 'value' => 'nativa_payment' ),
                ),
            ),
        ));
    }

    public function register_api_routes() {
        register_rest_route( 'nativa/v2', '/payment-methods', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_methods_endpoint' ),
            'permission_callback' => '__return_true',
        ));
    }

    public function get_methods_endpoint( $request ) {
        $context = $request->get_param('context') ?: 'pos'; // 'pos' ou 'delivery'

        // CORREÇÃO: Removemos a meta_query que excluía posts sem meta salvo.
        // Buscamos TODOS e filtramos no PHP via get_field() que respeita defaults.
        $args = array(
            'post_type'      => 'nativa_payment',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        );

        $posts = get_posts( $args );
        $structured_data = array();

        foreach ( $posts as $post ) {
            // 1. Verifica se está ativo
            // get_field retorna o valor padrão (1) se o meta não existir no banco
            $is_active = get_field( 'is_active', $post->ID );
            // ACF retorna boolean true/false ou '1'/'0' dependendo da versão
            if ( $is_active === false || $is_active === '0' ) continue;

            // 2. Verifica contexto
            if ( $context === 'pos' ) {
                $show = get_field( 'show_on_pos', $post->ID );
                if ( $show === false || $show === '0' ) continue;
            } else {
                $show = get_field( 'show_on_delivery', $post->ID );
                if ( $show === false || $show === '0' ) continue;
            }

            // 3. Organiza Categorias
            $cat_terms = wp_get_post_terms( $post->ID, 'nativa_payment_cat' );
            $cat_name = ! empty( $cat_terms ) ? $cat_terms[0]->name : 'Geral';
            $cat_id   = ! empty( $cat_terms ) ? $cat_terms[0]->term_id : 0;

            if ( ! isset( $structured_data[$cat_id] ) ) {
                $structured_data[$cat_id] = array(
                    'category' => $cat_name,
                    'methods'  => array()
                );
            }

            $structured_data[$cat_id]['methods'][] = array(
                'id'    => $post->ID,
                'label' => $post->post_title,
                // Aqui está a chave: lê o campo ACF corretamente
                'type'  => get_field( 'nativa_payment_type', $post->ID ) ?: 'manual',
                'icon'  => get_field( 'nativa_payment_icon', $post->ID ) ?: 'fa-solid fa-credit-card'
            );
        }

        return new WP_REST_Response( array( 
            'success' => true, 
            'categories' => array_values($structured_data) 
        ), 200 );
    }
}