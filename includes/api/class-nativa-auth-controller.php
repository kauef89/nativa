<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Auth_Controller {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/auth/google', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'handle_google_login' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'nativa/v2', '/auth/me', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_current_user' ),
            'permission_callback' => 'is_user_logged_in',
        ) );
        
        register_rest_route( 'nativa/v2', '/auth/logout', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'logout_user' ),
            'permission_callback' => '__return_true',
        ) );

        // NOVA ROTA (Turno 18): Renova o Nonce (Salva-Vidas para Erro 403)
        // Permite que o frontend peça um novo crachá se o antigo expirar.
        register_rest_route( 'nativa/v2', '/auth/nonce', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_fresh_nonce' ),
            'permission_callback' => '__return_true', 
        ) );

        // Bypass de Nonce (Filtro Global de Autenticação REST)
        add_filter( 'rest_authentication_errors', array( $this, 'bypass_nonce_check_for_auth' ), 10 );
    }

    /**
     * Retorna um novo Nonce Fresco (usado pelo Axios Auto-Recover)
     */
    public function get_fresh_nonce() {
        return new WP_REST_Response( array( 
            'success' => true, 
            'nonce' => wp_create_nonce( 'wp_rest' ) 
        ), 200 );
    }

    /**
     * Correção Robusta para o Erro 403 (e proteção do WP Admin)
     */
    public function bypass_nonce_check_for_auth( $result ) {
        // 1. SEGURANÇA: Se não for uma requisição da API REST, saia imediatamente.
        // Isso impede que este código interfira no wp-admin ou no login padrão do WP.
        if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
            return $result;
        }

        // 2. Só tentamos o bypass se houver um erro de autenticação (como Nonce expirado ou ausente)
        // Se $result for true (já logado via cookie válido) ou null, não mexemos.
        if ( is_wp_error( $result ) ) {
            
            // Tenta detectar a rota de várias formas
            $route = '';
            if ( !empty( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
                $route = $GLOBALS['wp']->query_vars['rest_route'];
            } elseif ( !empty( $_GET['rest_route'] ) ) {
                $route = $_GET['rest_route'];
            } else {
                $route = $_SERVER['REQUEST_URI'] ?? '';
            }

            // Lista de rotas que PODEM ignorar o erro de nonce
            // - Login Google: Cria a sessão, não tem nonce ainda.
            // - Logout: Encerra a sessão, nonce pode já estar inválido.
            // - Nonce Refresh: Serve justamente para corrigir o erro de nonce.
            $allowed_routes = [
                '/nativa/v2/auth/google',
                '/nativa/v2/auth/logout',
                '/nativa/v2/auth/nonce'
            ];

            foreach ( $allowed_routes as $allowed ) {
                if ( strpos( $route, $allowed ) !== false ) {
                    return true; // Autoriza o acesso (Bypass)
                }
            }
        }

        return $result;
    }

    private function get_user_flags( $user ) {
        $roles = (array) $user->roles;
        $is_admin = in_array( 'administrator', $roles );
        
        $can_view_delivery = $user->has_cap('nativa_view_delivery') || in_array('nativa_driver', $roles) || in_array('nativa_waiter', $roles) || $is_admin;
        $can_view_kds = $user->has_cap('nativa_view_kds') || in_array('nativa_kitchen', $roles) || $is_admin;

        return [
            'isStaff'      => $is_admin || count(array_intersect(['nativa_manager', 'nativa_waiter', 'nativa_kitchen', 'nativa_driver'], $roles)) > 0,
            'isAdmin'      => $is_admin,
            'canCash'      => $user->has_cap('nativa_access_cash') || $is_admin,
            'canTables'    => $user->has_cap('nativa_access_tables') || $is_admin,
            'canKitchen'   => $can_view_kds,
            'canDelivery'  => $can_view_delivery,
            'canSettings'  => $user->has_cap('nativa_access_settings') || $is_admin,
            'canLoyalty'   => $user->has_cap('nativa_access_loyalty') || $is_admin,
            'canTeam'      => $user->has_cap('nativa_access_team') || $is_admin,
            'canProducts'  => $user->has_cap('nativa_manage_products') || $is_admin,
            'canCustomers' => $user->has_cap('nativa_access_customers') || $is_admin,
            'canPrint'     => $user->has_cap('nativa_access_prints') || $is_admin,
            'canRestock'   => $user->has_cap('nativa_access_restock') || $is_admin,
            'canPurchases' => $user->has_cap('nativa_access_purchases') || $is_admin,
            'canOffers'    => $user->has_cap('nativa_manage_offers') || $is_admin,
            'canFiscal'    => $user->has_cap('nativa_access_fiscal') || $is_admin,
        ];
    }

    public function handle_google_login( $request ) {
        $params = $request->get_json_params();
        $id_token = $params['credential'] ?? '';

        if ( empty( $id_token ) ) {
            return new WP_Error( 'no_token', 'Token Google ausente', array( 'status' => 400 ) );
        }

        $response = wp_remote_get( 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $id_token );
        if ( is_wp_error( $response ) ) return new WP_Error( 'invalid_token', 'Token inválido', array( 'status' => 401 ) );

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty($body['email']) ) return new WP_Error( 'invalid_data', 'Dados inválidos do Google', array( 'status' => 401 ) );

        $email = $body['email'];
        $name  = $body['name'] ?? 'Usuario';
        $picture = $body['picture'] ?? '';

        $user = get_user_by( 'email', $email );

        if ( ! $user ) {
            $password = wp_generate_password( 20, true );
            $user_id = wp_create_user( $email, $password, $email );
            if ( is_wp_error( $user_id ) ) return $user_id;
            
            $user = get_user_by( 'id', $user_id );
            $user->set_role( 'customer' );
            
            wp_update_user([
                'ID' => $user_id,
                'display_name' => $name,
                'first_name' => $body['given_name'] ?? '',
                'last_name' => $body['family_name'] ?? ''
            ]);
        }

        update_user_meta( $user->ID, 'nativa_avatar_url', $picture );

        wp_clear_auth_cookie();
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );

        $new_nonce = wp_create_nonce( 'wp_rest' );

        return new WP_REST_Response( array(
            'success' => true,
            'user' => $this->prepare_user_response( $user ),
            'nonce' => $new_nonce 
        ), 200 );
    }

    public function get_current_user() {
        $user = wp_get_current_user();
        if ( ! $user->exists() ) return new WP_REST_Response(['success'=>false], 401);
        
        $new_nonce = wp_create_nonce( 'wp_rest' );
        return array( 
            'success' => true, 
            'user' => $this->prepare_user_response($user),
            'nonce' => $new_nonce
        );
    }

    public function logout_user() {
        wp_logout();
        return array( 'success' => true );
    }

    // Dentro de prepare_user_response($user)
    private function prepare_user_response( $user ) {
        $hash = $this->generate_onesignal_hash( $user->ID );
        
        // Recupera Cozinha Principal
        $main_kitchen = get_user_meta($user->ID, 'nativa_main_kitchen', true);

        return [
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar' => get_user_meta( $user->ID, 'nativa_avatar_url', true ) ?: get_avatar_url( $user->ID ),
            'role' => !empty($user->roles) ? reset($user->roles) : 'customer',
            'flags' => $this->get_user_flags( $user ),
            'onboarding_complete' => !!get_user_meta( $user->ID, 'nativa_onboarding_complete', true ),
            'onesignal_hash' => $hash,
            'main_kitchen' => $main_kitchen ? (int)$main_kitchen : null // <--- ENVIADO PARA O FRONT
        ];
    }
    
    private function generate_onesignal_hash( $user_id ) {
        $rest_key = defined('NATIVA_ONESIGNAL_REST_API_KEY') ? NATIVA_ONESIGNAL_REST_API_KEY : '';
        
        if ( empty( $rest_key ) ) {
            // error_log("⚠️ [Nativa Auth] Chave REST do OneSignal não configurada.");
            return null;
        }

        return hash_hmac( 'sha256', (string) $user_id, $rest_key );
    }
}