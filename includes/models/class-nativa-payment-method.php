<?php
/**
 * Model: Métodos de Pagamento (V2 com Categorias e Canais)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Payment_Method {

    public function init() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'init', array( $this, 'register_taxonomy' ) ); // Nova Taxonomia
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
                'not_found'          => 'Nenhuma forma encontrada',
                'not_found_in_trash' => 'Nenhuma forma na lixeira',
                'all_items'          => 'Todas as Formas'
            ),
            'public'              => false, 
            'show_ui'             => true,  // Exibe a interface
            'show_in_menu'        => true,  // Exibe no menu
            'show_in_admin_bar'   => true,
            'capability_type'     => 'post', // Usa permissões básicas de Post
            'map_meta_cap'        => true,   // <--- O FIX: Mapeia permissões corretamente
            'hierarchical'        => false,
            'query_var'           => true,
            'menu_icon'           => 'dashicons-money-alt',
            'supports'            => array( 'title' ), // Suporta título (o resto é ACF)
            'menu_position'       => 5,
            'can_export'          => true,
            'has_archive'         => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
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

        $args = array(
            'post_type'      => 'nativa_payment',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                'relation' => 'AND',
                array( 'key' => 'is_active', 'value' => '1', 'compare' => '=' ),
                array( 'key' => ($context === 'pos' ? 'show_on_pos' : 'show_on_delivery'), 'value' => '1', 'compare' => '=' )
            )
        );

        $posts = get_posts( $args );
        $structured_data = array();

        foreach ( $posts as $post ) {
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