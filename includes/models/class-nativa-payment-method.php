<?php
/**
 * Model: Métodos de Pagamento
 * Gerencia CPT, Campos ACF e API
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Payment_Method {

    public function init() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'acf/init', array( $this, 'register_acf_fields' ) ); // Registra campos ACF
        add_action( 'rest_api_init', array( $this, 'register_api_routes' ) );
    }

    /**
     * 1. CPT
     */
    public function register_cpt() {
        register_post_type( 'nativa_payment', array(
            'labels' => array(
                'name'          => 'Formas de Pagamento',
                'singular_name' => 'Forma de Pagamento',
                'menu_name'     => 'Pagamentos',
                'add_new_item'  => 'Adicionar Forma',
            ),
            'public'      => false,
            'show_ui'     => true,
            'menu_icon'   => 'dashicons-money-alt',
            'supports'    => array( 'title' ),
            'menu_position' => 5
        ));
    }

    /**
     * 2. Campos ACF (Via Código)
     * Cria os campos automaticamente sem precisar clicar no painel
     */
    public function register_acf_fields() {
        if ( function_exists( 'acf_add_local_field_group' ) ) {
            acf_add_local_field_group( array(
                'key' => 'group_nativa_payment_settings',
                'title' => 'Configurações do Pagamento',
                'fields' => array(
                    // Campo: Tipo de Integração (Slug)
                    array(
                        'key' => 'field_payment_type',
                        'label' => 'Tipo de Integração (Slug)',
                        'name' => 'nativa_payment_type',
                        'type' => 'select',
                        'instructions' => 'Define como o sistema processa este pagamento.',
                        'choices' => array(
                            'money'       => 'Dinheiro (Espécie)',
                            'pix_manual'  => 'Pix (Manual/Maquininha)',
                            'pix_auto'    => 'Pix (Automático API)',
                            'credit_card' => 'Cartão de Crédito',
                            'debit_card'  => 'Cartão de Débito',
                            'meal_voucher'=> 'Vale Refeição',
                            'courtesy'    => 'Cortesia / Casa'
                        ),
                        'default_value' => 'money',
                        'return_format' => 'value',
                    ),
                    // Campo: Ícone (FontAwesome)
                    array(
                        'key' => 'field_payment_icon',
                        'label' => 'Ícone (FontAwesome)',
                        'name' => 'nativa_payment_icon',
                        'type' => 'text',
                        'instructions' => 'Ex: fa-solid fa-money-bill-wave. Veja em fontawesome.com',
                        'default_value' => 'fa-solid fa-money-bill',
                    ),
                ),
                'location' => array(
                    array(
                        array(
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'nativa_payment',
                        ),
                    ),
                ),
            ));
        }
    }

    /**
     * 3. API Routes
     */
    public function register_api_routes() {
        register_rest_route( 'nativa/v2', '/payment-methods', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_methods_endpoint' ),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * 4. Endpoint Lógica
     */
    public function get_methods_endpoint() {
        $args = array(
            'post_type'      => 'nativa_payment',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title', // Ou 'menu_order' se usar plugin de ordenação
            'order'          => 'ASC',
        );

        $posts = get_posts( $args );
        $methods = array();

        foreach ( $posts as $post ) {
            // Pega dados do ACF
            $type = get_field( 'nativa_payment_type', $post->ID ) ?: 'money';
            $icon = get_field( 'nativa_payment_icon', $post->ID ) ?: 'fa-solid fa-circle-question';

            $methods[] = array(
                'id'    => $post->ID,
                'label' => $post->post_title,
                'type'  => $type, // O slug técnico (ex: pix_auto)
                'icon'  => $icon
            );
        }

        return new WP_REST_Response( array( 
            'success' => true, 
            'methods' => $methods 
        ), 200 );
    }
}