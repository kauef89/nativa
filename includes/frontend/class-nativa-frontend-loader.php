<?php
/**
 * Frontend Loader (PWA Fixed + Root Fix + OneSignal Full Integration)
 * Versão: Super Worker (Híbrido) + Kill Switch (?reset=true)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Frontend_Loader {

    public function init() {
        add_action( 'init', array( $this, 'add_rewrite_rules' ), 1 ); 
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_requests' ) );
        add_filter( 'redirect_canonical', array( $this, 'prevent_wp_redirects' ) );
        add_filter( 'do_redirect_guess_404_permalink', '__return_false' );
    }

    public function add_rewrite_rules() {
        // Regras do App
        add_rewrite_rule( '^(pdv|cardapio|staff|home)(?:/(.*))?$', 'index.php?nativa_app=1', 'top' );
        
        // Regras PWA (Virtuais)
        add_rewrite_rule( '^nativa-sw\.js$', 'index.php?nativa_pwa=sw', 'top' );
        add_rewrite_rule( '^nativa-manifest\.json$', 'index.php?nativa_pwa=manifest', 'top' );

        // Regra para o Service Worker do OneSignal (Raiz Virtual)
        add_rewrite_rule( '^OneSignalSDKWorker\.js$', 'index.php?nativa_pwa=onesignal_worker', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'nativa_app';
        $vars[] = 'nativa_pwa';
        return $vars;
    }

    public function prevent_wp_redirects( $redirect_url ) {
        if ( get_query_var( 'nativa_app' ) || get_query_var( 'nativa_pwa' ) ) return false;
        return $redirect_url;
    }

    public function handle_requests() {
        $pwa_var = get_query_var( 'nativa_pwa' );

        // 1. Service Worker Nativo (PWA) - Servido aqui para ser importado
        if ( $pwa_var === 'sw' ) {
            $this->serve_file( NATIVA_PLUGIN_DIR . 'sw.js', 'application/javascript' );
        }

        // 2. Manifest
        if ( $pwa_var === 'manifest' ) {
            status_header( 200 );
            header( 'Content-Type: application/json; charset=utf-8' );
            $this->output_manifest_json();
            exit;
        }

        // 3. SUPER WORKER (OneSignal + PWA Híbrido)
        if ( $pwa_var === 'onesignal_worker' ) {
            status_header( 200 );
            header( 'Content-Type: application/javascript; charset=utf-8' );
            header( 'Service-Worker-Allowed: /' );
            header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
            
            // A. Importa o motor do OneSignal (CDN)
            echo "importScripts('https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js');";
            
            // B. Importa o motor do PWA (Cache) para rodar no mesmo contexto
            echo "importScripts('/nativa-sw.js');";
            
            // C. Sondas e Customizações de Log
            echo "
            self.addEventListener('push', function(event) {
                console.log('[Nativa SW] 🔔 Push Recebido! Payload:', event.data ? event.data.text() : 'Sem dados');
            });
            
            self.addEventListener('notificationclick', function(event) {
                console.log('[Nativa SW] Notificação clicada.');
                event.notification.close();
                event.waitUntil(
                    clients.matchAll({ type: 'window' }).then(function(clientList) {
                        // Tenta focar numa janela aberta, senão abre a home
                        if (clientList.length > 0) {
                            return clientList[0].focus();
                        }
                        return clients.openWindow('/');
                    })
                );
            });
            ";
            exit;
        }

        // 4. App Vue (Frontend)
        $request_uri = strtok( $_SERVER['REQUEST_URI'], '?' );
        $is_app_route = preg_match( '/^\/(staff|pdv|cardapio|home)/', $request_uri );
        $is_home = ( $request_uri === '/' || is_front_page() || is_home() );

        if ( $is_app_route || $is_home ) {
            set_query_var( 'nativa_app', 1 );
            status_header( 200 );
            $this->output_html();
            exit;
        }
    }

    // Helper para servir arquivos
    private function serve_file($path, $mime) {
        status_header( 200 );
        header( "Content-Type: $mime; charset=utf-8" );
        header( 'Service-Worker-Allowed: /' );
        header( 'Cache-Control: no-cache' );
        if ( file_exists( $path ) ) readfile( $path );
        else echo "// Arquivo não encontrado: $path";
        exit;
    }

    private function output_manifest_json() {
        $icon_base = NATIVA_PLUGIN_URL . 'assets/pwa/';
        $manifest = [
            "name" => "Nativa",
            "short_name" => "Nativa",
            "start_url" => "/home",
            "display" => "standalone",
            "background_color" => "#09090b",
            "theme_color" => "#09090b",
            "orientation" => "portrait",
            "scope" => "/", 
            "icons" => [
                [ "src" => $icon_base . "icon-192x192.png", "sizes" => "192x192", "type" => "image/png" ],
                [ "src" => $icon_base . "icon-512x512.png", "sizes" => "512x512", "type" => "image/png" ]
            ]
        ];
        echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
            'mapsApiKey' => defined('NATIVA_GOOGLE_MAPS_API_KEY') ? NATIVA_GOOGLE_MAPS_API_KEY : '',
            'googleClientId' => defined('NATIVA_GOOGLE_CLIENT_ID') ? NATIVA_GOOGLE_CLIENT_ID : ''
        );
        
        $onesignal_data = array(
            'app_id' => defined('NATIVA_ONESIGNAL_APP_ID') ? NATIVA_ONESIGNAL_APP_ID : '',
            'safari_id' => defined('NATIVA_ONESIGNAL_SAFARI_ID') ? NATIVA_ONESIGNAL_SAFARI_ID : ''
        );

        $pwa_icons_url = NATIVA_PLUGIN_URL . 'assets/pwa/';
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR" class="app-dark"> 
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
            <title>Nativa</title>
            
            <link rel="manifest" href="/nativa-manifest.json">
            
            <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>

            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@500;600;700;800;900&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
            
            <?php foreach ( $css_files as $css_url ) : ?>
                <link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>">
            <?php endforeach; ?>

            <script src="https://accounts.google.com/gsi/client" async defer></script>

            <script>
                window.nativaData = <?php echo json_encode( $nativa_data ); ?>;
                window.nativaOneSignal = <?php echo json_encode( $onesignal_data ); ?>;
                
                // NOTA: O registro manual do SW foi removido daqui para evitar conflito.
                
                // --- HACK 1: MATAR SERVICE WORKERS (Limpeza de Cache) ---
                if (window.location.search.includes('reset=true')) {
                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.getRegistrations().then(function(registrations) {
                            for(let registration of registrations) {
                                registration.unregister();
                                console.log('SW Removido:', registration);
                            }
                            alert('🧹 Service Workers removidos com sucesso! Recarregando limpo...');
                            window.location.href = window.location.pathname;
                        });
                    }
                }
            </script>

            <?php if ( isset($_GET['debug']) && $_GET['debug'] === 'true' ) : ?>
                <script src="https://unpkg.com/vconsole@latest/dist/vconsole.min.js"></script>
                <script>
                    // Inicia o console flutuante
                    var vConsole = new VConsole();
                    console.log('📱 Modo de Depuração Mobile Ativado');
                    console.log('ℹ️ Dica: Use a aba "Network" para ver se o push está chegando.');
                </script>
            <?php endif; ?>

            <style>
                body, html { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; background-color: #09090b; color: #e4e4e7; font-family: 'Nunito', sans-serif; -webkit-font-smoothing: antialiased; }
                #app-loading { position: fixed; top: 0; left: 0; width: 100%; height: 100%; display: flex; justify-content: center; align-items: center; background-color: #09090b; z-index: 9999; }
            </style>
        </head>
        <body>
            <div id="nativa-app"></div>
            <?php if ( $js_url ) : ?>
                <script type="module" src="<?php echo esc_url( $js_url ); ?>"></script>
            <?php endif; ?>
        </body>
        </html>
        <?php
    }
}