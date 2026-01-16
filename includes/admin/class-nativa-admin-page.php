<?php
/**
 * Página Admin do PDV
 * Carrega o Vue.js dentro do WP Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Admin_Page {

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function add_menu_page() {
        add_menu_page(
            'Nativa PDV',
            'Nativa PDV',
            'manage_options',
            'nativa-pdv',
            array( $this, 'render_page' ),
            'dashicons-store',
            2
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'toplevel_page_nativa-pdv' ) {
            return;
        }

        // Caminho para o manifesto gerado pelo Vite
        $manifest_path = NATIVA_PLUGIN_DIR . 'assets/dist/.vite/manifest.json';

        if ( ! file_exists( $manifest_path ) ) {
            // Fallback útil: se não tiver build, tenta carregar do localhost (dev mode)
            // Mas por enquanto, vamos apenas abortar para não gerar erro 403
            return; 
        }

        $manifest = json_decode( file_get_contents( $manifest_path ), true );
        
        // CORREÇÃO: A chave no manifesto agora inclui o caminho 'assets/src/'
        $script_key = 'assets/src/main.js';

        if ( ! isset( $manifest[$script_key] ) ) {
            // Se não achar a chave, encerra para evitar erro de link quebrado
            return;
        }
        
        // Pega o arquivo JS principal do manifesto
        $js_file = 'assets/dist/' . $manifest[$script_key]['file'];
        $css_file = isset($manifest[$script_key]['css']) ? 'assets/dist/' . $manifest[$script_key]['css'][0] : null;

        // Carrega o JS do Vue
        wp_enqueue_script( 'nativa-pdv-app', NATIVA_PLUGIN_URL . $js_file, array(), '2.0.0', true );
        
        // Carrega o CSS do Vue/Quasar
        if ( $css_file ) {
            wp_enqueue_style( 'nativa-pdv-style', NATIVA_PLUGIN_URL . $css_file, array(), '2.0.0' );
        }
        
        // Passa dados importantes para o JS (API URL, Nonce)
        wp_localize_script( 'nativa-pdv-app', 'nativaData', array(
            'root'  => esc_url_raw( rest_url() ),
            'nonce' => wp_create_nonce( 'wp_rest' )
        ));
    }

    public function render_page() {
        echo '<div id="nativa-app"></div>';
    }
}