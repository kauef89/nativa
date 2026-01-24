<?php
/**
 * API de Autenticação (Google e Sessão)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Auth_Controller {

    public function register_routes() {
        $namespace = 'nativa/v2';

        register_rest_route( $namespace, '/auth/google', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'handle_google_login' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $namespace, '/auth/me', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_current_user' ),
            'permission_callback' => 'is_user_logged_in',
        ) );
        
        register_rest_route( $namespace, '/auth/logout', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'logout_user' ),
            'permission_callback' => '__return_true',
        ) );
    }

    private function get_user_flags( $user ) {
        $is_admin = in_array( 'administrator', (array) $user->roles );

        // Mapeamento das permissões para flags do Frontend
        return [
            'isAdmin'      => $is_admin,
            'canCash'      => $user->has_cap('nativa_access_cash') || $is_admin,
            'canTeam'      => $user->has_cap('nativa_access_team') || $is_admin,
            'canTables'    => $user->has_cap('nativa_access_tables') || $is_admin,
            'canKitchen'   => $user->has_cap('nativa_view_kds') || $is_admin,
            'canDelivery'  => $user->has_cap('nativa_view_delivery') || $is_admin,
            'canSettings'  => $user->has_cap('nativa_access_settings') || $is_admin,
            'canProducts'  => $user->has_cap('nativa_manage_products') || $is_admin,
            'canCustomers' => $user->has_cap('nativa_access_customers') || $is_admin,
            // Flag explícita para POS (Lançamento de pedidos)
            'canPos'       => $user->has_cap('nativa_access_pos') || $is_admin
        ];
    }

    public function handle_google_login( $request ) {
        $params = $request->get_json_params();
        $id_token = isset($params['credential']) ? $params['credential'] : '';

        if ( empty( $id_token ) ) return new WP_Error( 'no_token', 'Token ausente', array( 'status' => 400 ) );

        $response = wp_remote_get( 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $id_token );
        if ( is_wp_error( $response ) ) return new WP_Error( 'invalid_token', 'Token inválido', array( 'status' => 401 ) );

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        // Validação básica do retorno do Google
        if ( empty($body['email']) ) return new WP_Error( 'invalid_token_data', 'Dados do token inválidos', array( 'status' => 401 ) );

        $email = $body['email'];
        $picture = isset($body['picture']) ? $body['picture'] : '';

        $user = get_user_by( 'email', $email );

        if ( ! $user ) {
            $password = wp_generate_password( 20, true );
            $user_id = wp_create_user( $email, $password, $email );
            if ( is_wp_error( $user_id ) ) return $user_id;
            
            $user = get_user_by( 'id', $user_id );
            update_user_meta( $user_id, 'first_name', $body['given_name'] ?? '' );
            update_user_meta( $user_id, 'nativa_avatar_url', $picture );
            $user->set_role( 'customer' ); 
        }

        wp_clear_auth_cookie();
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );

        $roles = $user->roles;
        $role = !empty($roles) ? $roles[0] : 'customer';

        return new WP_REST_Response( array(
            'success' => true,
            'user' => array(
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'avatar' => $picture,
                'role' => $role,
                'flags' => $this->get_user_flags( $user ),
                'onboarding_complete' => !!get_user_meta( $user->ID, 'nativa_onboarding_complete', true )
            ),
            'nonce' => wp_create_nonce( 'wp_rest' )
        ), 200 );
    }

    public function get_current_user() {
        $user = wp_get_current_user();
        if ( ! $user->exists() ) return new WP_REST_Response(['success'=>false], 401);

        $avatar = get_user_meta( $user->ID, 'nativa_avatar_url', true );
        $roles = $user->roles;
        $role = !empty($roles) ? $roles[0] : 'customer';

        return array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar' => $avatar ? $avatar : get_avatar_url( $user->ID ),
            'role' => $role,
            'flags' => $this->get_user_flags( $user ),
            'onboarding_complete' => !!get_user_meta( $user->ID, 'nativa_onboarding_complete', true ),
            'addresses' => get_user_meta($user->ID, 'nativa_saved_addresses', true) ?: []
        );
    }

    public function logout_user() {
        wp_logout();
        return array( 'success' => true );
    }
}