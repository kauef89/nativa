<?php
/**
 * API de Onboarding (Validação de CPF e Finalização de Cadastro)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Onboarding_API {

    public function register_routes() {
        // Valida CPF (Duplicidade + Governo)
        register_rest_route( 'nativa/v2', '/onboarding/validate-cpf', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'validate_cpf' ),
            'permission_callback' => 'is_user_logged_in', 
        ) );

        // Salva os dados finais
        register_rest_route( 'nativa/v2', '/onboarding/complete', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'complete_onboarding' ),
            'permission_callback' => 'is_user_logged_in',
        ) );
    }

    public function validate_cpf( $request ) {
        // Limpa o CPF recebido (apenas números)
        $cpf_clean = preg_replace( '/[^0-9]/', '', $request->get_param( 'cpf' ) );
        $user_id   = get_current_user_id();

        if ( strlen( $cpf_clean ) !== 11 ) {
            return new WP_REST_Response(['success' => false, 'message' => 'CPF inválido.'], 400);
        }

        // --- 1. VERIFICAÇÃO LOCAL ROBUSTA (Antes de gastar API) ---
        
        // Cria versão formatada (XXX.XXX.XXX-XX) para buscar legados
        $cpf_formatted = preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "\$1.\$2.\$3-\$4", $cpf_clean);

        // Busca usuários que tenham esse CPF (Limpo ou Formatado)
        // Verificamos tanto a chave nova 'nativa_user_cpf' quanto a antiga 'billing_cpf' (WooCommerce)
        $users = get_users(array(
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key'     => 'nativa_user_cpf',
                    'value'   => $cpf_clean,
                    'compare' => '='
                ),
                array(
                    'key'     => 'nativa_user_cpf',
                    'value'   => $cpf_formatted,
                    'compare' => '='
                ),
                array(
                    'key'     => 'billing_cpf', // Legado WooCommerce
                    'value'   => $cpf_clean,
                    'compare' => '='
                ),
                array(
                    'key'     => 'billing_cpf', // Legado WooCommerce Formatado
                    'value'   => $cpf_formatted,
                    'compare' => '='
                ),
            ),
            'exclude' => array( $user_id ), // Ignora o próprio usuário se estiver tentando corrigir o cadastro
            'number'  => 1,
            'fields'  => ['ID', 'user_email'] // Otimização: traz só o necessário
        ));

        if ( ! empty( $users ) ) {
            $existing_user = $users[0];
            $email = $existing_user->user_email;
            
            // Mascara o e-mail para LGPD (ex: jo***@gmail.com)
            $parts = explode( '@', $email );
            $masked_email = substr( $parts[0], 0, 2 ) . '***@' . ($parts[1] ?? 'email.com');

            return new WP_REST_Response([
                'success' => false,
                'code'    => 'cpf_exists', // Código que o Vue escuta
                'message' => 'CPF já cadastrado.',
                'hint'    => "Este documento pertence à conta: $masked_email. Por favor, faça login com ela."
            ], 200); 
        }

        // --- 2. CONSULTA API DO GOVERNO (Só chega aqui se não existir no banco) ---
        
        require_once NATIVA_PLUGIN_DIR . 'includes/classes/class-nativa-gov-api.php';
        
        $gov_data = Nativa_Gov_API::consult_cpf( $cpf_clean );

        if ( is_wp_error( $gov_data ) ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $gov_data->get_error_message()
            ], 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => $gov_data
        ], 200);
    }

    public function complete_onboarding( $request ) {
        $user_id = get_current_user_id();
        
        // Sanitização
        $cpf     = preg_replace( '/[^0-9]/', '', $request->get_param('cpf') );
        $phone   = sanitize_text_field( $request->get_param('phone') );
        $name    = sanitize_text_field( $request->get_param('name') );
        $dob     = sanitize_text_field( $request->get_param('dob') ); // Vem YYYY-MM-DD

        if ( empty($cpf) || empty($phone) ) {
            return new WP_REST_Response(['success'=>false, 'message'=>'Dados incompletos.'], 400);
        }

        // Salva CPF limpo (padrão novo)
        update_user_meta( $user_id, 'nativa_user_cpf', $cpf );
        
        // Também salva/atualiza no padrão WooCommerce para compatibilidade futura
        update_user_meta( $user_id, 'billing_cpf', $cpf );
        update_user_meta( $user_id, 'billing_phone', $phone );
        
        update_user_meta( $user_id, 'nativa_user_phone', $phone );
        update_user_meta( $user_id, 'nativa_user_dob', $dob );
        
        // Atualiza nome de exibição (Prioridade para o nome oficial/social retornado)
        if ( ! empty($name) ) {
            wp_update_user([ 
                'ID' => $user_id, 
                'display_name' => $name,
                'first_name' => explode(' ', $name)[0],
                'last_name'  => substr(strstr($name, ' '), 1)
            ]);
        }

        // Marca flag final
        update_user_meta( $user_id, 'nativa_onboarding_complete', 1 );

        return new WP_REST_Response(['success' => true, 'message' => 'Cadastro concluído!'], 200);
    }
}