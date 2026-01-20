<?php
/**
 * API de Autenticação (Google e Sessão)
 * Atualizado para namespace V2 e flag de Onboarding
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Auth_Controller {

    public function register_routes() {
        // Namespace atualizado para V2 para alinhar com o frontend
        $namespace = 'nativa/v2';

        // Login via Google
        register_rest_route( $namespace, '/auth/google', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'handle_google_login' ),
            'permission_callback' => '__return_true',
        ) );

        // Dados do Usuário Logado
        register_rest_route( $namespace, '/auth/me', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_current_user' ),
            'permission_callback' => 'is_user_logged_in',
        ) );
        
        // Logout
        register_rest_route( $namespace, '/auth/logout', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'logout_user' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Recebe o credential (JWT) do Google, valida e loga o usuário
     */
    public function handle_google_login( $request ) {
        $params = $request->get_json_params();
        $id_token = isset($params['credential']) ? $params['credential'] : '';

        if ( empty( $id_token ) ) {
            return new WP_Error( 'no_token', 'Token não fornecido', array( 'status' => 400 ) );
        }

        // 1. Valida o token diretamente com o Google
        $response = wp_remote_get( 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $id_token );
        
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'invalid_token', 'Erro ao validar token', array( 'status' => 401 ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        // Verifica se o Audience bate com nosso Client ID (Segurança)
        if ( defined('NATIVA_GOOGLE_CLIENT_ID') && ( ! isset( $body['aud'] ) || $body['aud'] !== NATIVA_GOOGLE_CLIENT_ID ) ) {
             // return new WP_Error( 'invalid_aud', 'Token não pertence a este app', array( 'status' => 401 ) );
        }

        $email = $body['email'];
        $name  = isset($body['name']) ? $body['name'] : 'Cliente';
        $picture = isset($body['picture']) ? $body['picture'] : '';
        $google_id = $body['sub'];

        // 2. Verifica se o usuário já existe
        $user = get_user_by( 'email', $email );

        if ( ! $user ) {
            // Cria novo usuário
            $password = wp_generate_password( 20, true );
            $user_id = wp_create_user( $email, $password, $email ); // Username = Email

            if ( is_wp_error( $user_id ) ) {
                return $user_id;
            }

            $user = get_user_by( 'id', $user_id );
            
            // Salva metadados extras
            update_user_meta( $user_id, 'first_name', $body['given_name'] ?? '' );
            update_user_meta( $user_id, 'last_name', $body['family_name'] ?? '' );
            update_user_meta( $user_id, 'nativa_google_id', $google_id );
            update_user_meta( $user_id, 'nativa_avatar_url', $picture );
            
            // Define papel de Cliente
            $user->set_role( 'customer' ); 
        } else {
            // Atualiza avatar caso tenha mudado
            update_user_meta( $user->ID, 'nativa_avatar_url', $picture );
        }

        // 3. Loga o usuário no WordPress
        wp_clear_auth_cookie();
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true ); // 'true' mantém logado por 14 dias

        // 4. Verifica status do Onboarding
        $onboarding_done = get_user_meta( $user->ID, 'nativa_onboarding_complete', true );

        return new WP_REST_Response( array(
            'success' => true,
            'user' => array(
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'avatar' => $picture,
                'onboarding_complete' => !!$onboarding_done 
            ),
            'nonce' => wp_create_nonce( 'wp_rest' ) // Novo nonce para sessões futuras
        ), 200 );
    }

    /**
     * Retorna dados do usuário atual (Persistência de Sessão)
     */
    public function get_current_user() {
        $user = wp_get_current_user();
        $avatar = get_user_meta( $user->ID, 'nativa_avatar_url', true );
        $onboarding_done = get_user_meta( $user->ID, 'nativa_onboarding_complete', true );
        
        // Recupera endereços salvos (se existirem) para o checkout
        $saved_addresses = get_user_meta($user->ID, 'nativa_saved_addresses', true) ?: [];

        return array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar' => $avatar ? $avatar : get_avatar_url( $user->ID ),
            'onboarding_complete' => !!$onboarding_done,
            'addresses' => $saved_addresses // Importante para o CartDrawer
        );
    }

    public function logout_user() {
        wp_logout();
        return array( 'success' => true );
    }
}