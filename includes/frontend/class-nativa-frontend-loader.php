<?php
/**
 * Frontend Loader
 * Cria uma rota "App" (/pdv) fora do Admin do WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Frontend_Loader {

    public function init() {
        add_action( 'init', array( $this, 'add_rewrite_rule' ) );
        add_filter( 'query_vars', array( $this, 'add_query_var' ) );
        add_action( 'template_redirect', array( $this, 'render_app' ) );
    }

    /**
     * 1. Cria a rota /pdv e ACEITA SUB-ROTAS (Regex atualizado)
     */
    public function add_rewrite_rule() {
        // Antes era: '^pdv/?$'
        // Agora aceita: /pdv, /pdv/tables, /pdv/delivery, etc.
        add_rewrite_rule( '^pdv(?:/(.*))?$', 'index.php?nativa_app=1', 'top' );
    }

    /**
     * 2. Registra a variável de consulta
     */
    public function add_query_var( $vars ) {
        $vars[] = 'nativa_app';
        return $vars;
    }

    /**
     * 3. Renderiza o App se a rota for acessada
     */
    public function render_app() {
        if ( get_query_var( 'nativa_app' ) == 1 ) {
            $this->output_html();
            exit; // Impede o WordPress de carregar o tema
        }
    }
/**
     * 4. Gera o HTML Limpo (Sem header/footer do WP)
     */
    private function output_html() {
        // --- 1. CONFIGURAÇÃO DE CAMINHOS ---
        // O Vite 5+ gera o manifesto dentro de .vite/
        $manifest_path = NATIVA_PLUGIN_DIR . 'assets/dist/.vite/manifest.json';
        
        // A chave de entrada deve bater com o "input" do vite.config.mjs
        // Como configuramos o alias '@' para 'assets/src', o Vite usa esse caminho relativo
        $script_key = 'assets/src/main.js';

        $js_url = '';
        $css_files = array();

        // --- 2. LEITURA DO MANIFESTO ---
        if ( file_exists( $manifest_path ) ) {
            $manifest = json_decode( file_get_contents( $manifest_path ), true );
            
            if ( isset( $manifest[$script_key] ) ) {
                // Arquivo JS Principal
                $js_url = NATIVA_PLUGIN_URL . 'assets/dist/' . $manifest[$script_key]['file'];
                
                // Arquivos CSS gerados pelo Vite (Tailwind + PrimeVue)
                if ( ! empty( $manifest[$script_key]['css'] ) ) {
                    foreach ( $manifest[$script_key]['css'] as $css_file ) {
                        $css_files[] = NATIVA_PLUGIN_URL . 'assets/dist/' . $css_file;
                    }
                }
            }
        }

        // Dados para injetar no JS (API Root e Nonce)
        $nativa_data = array(
            'root'  => esc_url_raw( rest_url() ),
            'nonce' => wp_create_nonce( 'wp_rest' )
        );
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR" class="app-dark"> <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Nativa PDV</title>
            <meta name="robots" content="noindex, nofollow">
            
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
            </script>
            
            <style>
                body, html { 
                    margin: 0; 
                    padding: 0; 
                    width: 100%; 
                    height: 100%; 
                    overflow: hidden; 
                    background-color: #09090b; /* Zinc 950 */
                    color: #e4e4e7; /* Zinc 200 */
                    font-family: 'Nunito', sans-serif;
                    -webkit-font-smoothing: antialiased;
                    font-weight: 500; /* Define o peso base como Medium */
                }
                /* Loading Spinner Centralizado (antes do Vue carregar) */
                #app-loading {
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                    display: flex; justify-content: center; align-items: center;
                    background-color: #09090b; z-index: 9999;
                }
            </style>
        </head>
        <body>
            <div id="nativa-app"></div>

            <div id="app-loading">
                <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: #673ab7;"></i>
            </div>
            <script>
                window.addEventListener('load', function() {
                    const loader = document.getElementById('app-loading');
                    if(loader) loader.style.display = 'none';
                });
            </script>

            <?php if ( $js_url ) : ?>
                <script type="module" src="<?php echo esc_url( $js_url ); ?>"></script>
            <?php else : ?>
                <div style="text-align:center; padding: 50px; color: #fff;">
                    <h1>Erro: Build não encontrado</h1>
                    <p>Verifique se a pasta <code>assets/dist</code> existe.</p>
                </div>
            <?php endif; ?>
        </body>
        </html>
        <?php
    }
}