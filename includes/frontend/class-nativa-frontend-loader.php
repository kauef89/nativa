<?php
/**
 * Frontend Loader
 * Define as rotas onde o App Vue será carregado.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Frontend_Loader {

    public function init() {
        // Prioridade 1 para rodar antes de outros plugins
        add_action( 'init', array( $this, 'add_rewrite_rule' ), 1 ); 
        add_filter( 'query_vars', array( $this, 'add_query_var' ) );
        add_action( 'template_redirect', array( $this, 'render_app' ) );

        // Bloqueia redirecionamentos "inteligentes" do WP que causam 404
        add_filter( 'redirect_canonical', array( $this, 'prevent_wp_guessing' ) );
        add_filter( 'do_redirect_guess_404_permalink', '__return_false' );
    }

    public function add_rewrite_rule() {
        // Rota 1: PDV (Gestão)
        add_rewrite_rule( '^pdv(?:/(.*))?$', 'index.php?nativa_app=1', 'top' );
        
        // Rota 2: Cardápio (Cliente) <--- ESTA É A LINHA QUE FALTAVA
        add_rewrite_rule( '^cardapio(?:/(.*))?$', 'index.php?nativa_app=1', 'top' );
    }

    public function add_query_var( $vars ) {
        $vars[] = 'nativa_app';
        return $vars;
    }

    public function prevent_wp_guessing( $redirect_url ) {
        if ( get_query_var( 'nativa_app' ) == 1 ) return false;
        
        // Proteção extra contra redirecionamento por URL
        $uri = $_SERVER['REQUEST_URI'];
        if ( strpos($uri, '/cardapio') !== false || strpos($uri, '/pdv') !== false ) {
            return false;
        }
        return $redirect_url;
    }

    public function render_app() {
        if ( get_query_var( 'nativa_app' ) == 1 || is_front_page() || is_home() ) {
            $this->output_html();
            exit;
        }
    }

    private function output_html() {
        $manifest_path = NATIVA_PLUGIN_DIR . 'assets/dist/.vite/manifest.json';
        $script_key = 'assets/src/main.js';
        $js_url = '';
        $css_files = array();

        if ( file_exists( $manifest_path ) ) {
            $manifest = json_decode( file_get_contents( $manifest_path ), true );
            if ( isset( $manifest[$script_key] ) ) {
                $js_url = NATIVA_PLUGIN_URL . 'assets/dist/' . $manifest[$script_key]['file'];
                if ( ! empty( $manifest[$script_key]['css'] ) ) {
                    foreach ( $manifest[$script_key]['css'] as $css_file ) {
                        $css_files[] = NATIVA_PLUGIN_URL . 'assets/dist/' . $css_file;
                    }
                }
            }
        }

        $nativa_data = array(
        'root'  => esc_url_raw( rest_url() ),
        'nonce' => wp_create_nonce( 'wp_rest' ),
        // ADICIONE ESTA LINHA:
        'mapsApiKey' => defined('NATIVA_GOOGLE_MAPS_API_KEY') ? NATIVA_GOOGLE_MAPS_API_KEY : ''
    );
    
        $onesignal_data = array(
            'app_id' => defined('NATIVA_ONESIGNAL_APP_ID') ? NATIVA_ONESIGNAL_APP_ID : '',
            'safari_id' => defined('NATIVA_ONESIGNAL_SAFARI_ID') ? NATIVA_ONESIGNAL_SAFARI_ID : ''
        );

        ?>
        <!DOCTYPE html>
        <html lang="pt-BR" class="app-dark"> <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Nativa</title>
            <meta name="robots" content="index, follow">
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@500;600;700;800;900&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://unpkg.com/primeicons@6.0.1/primeicons.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
            <?php foreach ( $css_files as $css_url ) : ?>
                <link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>">
            <?php endforeach; ?>
            <script>
                window.nativaData = <?php echo json_encode( $nativa_data ); ?>;
                window.nativaOneSignal = <?php echo json_encode( $onesignal_data ); ?>;
            </script>
            <style>
                body, html { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; background-color: #09090b; color: #e4e4e7; font-family: 'Nunito', sans-serif; -webkit-font-smoothing: antialiased; font-weight: 500; }
                #app-loading { position: fixed; top: 0; left: 0; width: 100%; height: 100%; display: flex; justify-content: center; align-items: center; background-color: #09090b; z-index: 9999; }
            </style>
        </head>
        <body>
            <div id="nativa-app"></div>
            <div id="app-loading"><i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: #10b981;"></i></div>
            <script>window.addEventListener('load', function() { const loader = document.getElementById('app-loading'); if(loader) loader.style.display = 'none'; });</script>
            <?php if ( $js_url ) : ?>
                <script type="module" src="<?php echo esc_url( $js_url ); ?>"></script>
            <?php else : ?>
                <div style="text-align:center; padding: 50px; color: #fff;"><h1>Erro: Build não encontrado</h1></div>
            <?php endif; ?>
        </body>
        </html>
        <?php
    }
}