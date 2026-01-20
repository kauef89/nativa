<?php
/**
 * Model: CPT de Mesas
 * Responsável pelo registro do tipo de post e organização no Admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Mesa_CPT {

    public function init() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'init', array( $this, 'register_taxonomy' ) );
        
        // Personalização das Colunas no Admin
        add_filter( 'manage_nativa_mesa_posts_columns', array( $this, 'set_custom_columns' ) );
        add_action( 'manage_nativa_mesa_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
        add_filter( 'manage_edit-nativa_mesa_sortable_columns', array( $this, 'set_sortable_columns' ) );
        add_action( 'pre_get_posts', array( $this, 'order_mesas_by_title_number' ) );
    }

    public function register_cpt() {
        register_post_type( 'nativa_mesa', array(
            'labels' => array(
                'name'          => 'Mesas',
                'singular_name' => 'Mesa',
                'menu_name'     => 'Mesas',
                'add_new'       => 'Nova Mesa',
                'add_new_item'  => 'Adicionar Nova Mesa',
                'edit_item'     => 'Editar Mesa',
                'all_items'     => 'Todas as Mesas'
            ),
            'public'      => false, // Não acessível publicamente no front do WP
            'show_ui'     => true,  // Visível no Admin
            'menu_icon'   => 'dashicons-layout',
            'supports'    => array( 'title' ), // Título = Número/Nome da Mesa
            'menu_position' => 4,
            'rewrite'     => false
        ));
    }

    public function register_taxonomy() {
        register_taxonomy( 'nativa_zona_mesa', 'nativa_mesa', array(
            'labels' => array(
                'name'          => 'Zonas (Áreas)',
                'singular_name' => 'Zona',
                'menu_name'     => 'Zonas',
                'search_items'  => 'Buscar Zonas',
                'all_items'     => 'Todas as Zonas',
                'edit_item'     => 'Editar Zona',
                'update_item'   => 'Atualizar Zona',
                'add_new_item'  => 'Adicionar Nova Zona',
                'new_item_name' => 'Nome da Nova Zona',
            ),
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => false,
        ));
    }

    /**
     * Define as colunas da listagem
     */
    public function set_custom_columns( $columns ) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = 'Identificação (Número)';
        $new_columns['zona'] = 'Zona / Área'; // Substitui a coluna padrão de taxonomia para ter controle
        $new_columns['capacidade'] = 'Lugares';
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    /**
     * Renderiza o conteúdo das colunas
     */
    public function render_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'capacidade':
                $cap = get_field( 'mesa_capacidade', $post_id );
                echo $cap ? $cap . ' pessoas' : '-';
                break;

            case 'zona':
                $terms = get_the_terms( $post_id, 'nativa_zona_mesa' );
                if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                    $zonas = wp_list_pluck( $terms, 'name' );
                    echo implode( ', ', $zonas );
                } else {
                    echo '<span style="color:#aaa">Sem zona</span>';
                }
                break;
        }
    }

    /**
     * Torna a coluna "Identificação" ordenável
     */
    public function set_sortable_columns( $columns ) {
        $columns['title'] = 'title';
        return $columns;
    }

    /**
     * Força a ordenação numérica natural (1, 2, 10 vem depois de 9)
     */
    public function order_mesas_by_title_number( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) return;

        if ( 'nativa_mesa' === $query->get( 'post_type' ) && 'title' === $query->get( 'orderby' ) ) {
            $query->set( 'orderby', 'title' );
            $query->set( 'order', 'ASC' );
        }
    }
}