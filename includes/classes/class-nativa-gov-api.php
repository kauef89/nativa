<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Gov_API {

    private const AUTH_URL = 'https://gateway.apiserpro.serpro.gov.br/token';
    private const API_URL  = 'https://gateway.apiserpro.serpro.gov.br/consulta-cpf-df/v2/cpf/';

    private static function get_access_token() {
        $token = get_transient( 'nativa_gov_api_token' );
        if ( $token ) return $token;

        $client_id = defined('NATIVA_GOV_CLIENT_ID') ? NATIVA_GOV_CLIENT_ID : '';
        $client_secret = defined('NATIVA_GOV_CLIENT_SECRET') ? NATIVA_GOV_CLIENT_SECRET : '';

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            return new WP_Error( 'missing_config', 'Chaves do Serpro não configuradas.' );
        }

        $auth_string = base64_encode( $client_id . ':' . $client_secret );

        $response = wp_remote_post( self::AUTH_URL, array(
            'headers' => array( 'Authorization' => 'Basic ' . $auth_string, 'Content-Type'  => 'application/x-www-form-urlencoded' ),
            'body'    => 'grant_type=client_credentials',
            'timeout' => 15,
        ));

        if ( is_wp_error( $response ) ) return $response;
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( ! isset( $body['access_token'] ) ) return new WP_Error( 'auth_failed', 'Falha na autenticação Gov.' );

        set_transient( 'nativa_gov_api_token', $body['access_token'], intval( $body['expires_in'] ) - 60 );
        return $body['access_token'];
    }

    /**
     * Valida o algoritmo matemático do CPF (Luhn)
     */
    private static function validate_cpf_algorithm( $cpf ) {
        $cpf = preg_replace( '/[^0-9]/is', '', $cpf );
        if ( strlen( $cpf ) != 11 || preg_match( '/(\d)\1{10}/', $cpf ) ) return false;
        
        for ( $t = 9; $t < 11; $t++ ) {
            for ( $d = 0, $c = 0; $c < $t; $c++ ) $d += $cpf[ $c ] * ( ( $t + 1 ) - $c );
            $d = ( ( 10 * $d ) % 11 ) % 10;
            if ( $cpf[ $c ] != $d ) return false;
        }
        return true;
    }

    public static function consult_cpf( $cpf ) {
        $cpf_clean = preg_replace( '/[^0-9]/', '', $cpf );
        
        // 1. Validação de Formato e Dígitos Repetidos
        if ( strlen( $cpf_clean ) !== 11 ) return new WP_Error( 'invalid_format', 'CPF deve ter 11 dígitos.' );
        
        // 2. Validação Matemática (NOVO)
        // Bypass para o CPF de Teste específico
        if ( $cpf_clean !== '00000000000' && ! self::validate_cpf_algorithm( $cpf_clean ) ) {
            return new WP_Error( 'invalid_cpf', 'CPF inválido.' );
        }

        // Bypass de teste (Economia)
        if ( $cpf_clean === '00000000000' ) {
            return ['success' => true, 'name' => 'Usuário Teste Nativa', 'dob' => '2000-01-01', 'status' => 'Regular (Teste)'];
        }

        $token = self::get_access_token();
        if ( is_wp_error( $token ) ) return $token;

        $response = wp_remote_get( self::API_URL . $cpf_clean, array(
            'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ),
            'timeout' => 20,
        ));

        if ( is_wp_error( $response ) ) return $response;
        
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 404 ) return new WP_Error( 'not_found', 'CPF não encontrado na base.' );
        
        // Tratamento de Erro da API (ex: 400 da receita)
        if ( $code !== 200 && $code !== 206 ) {
            $body_err = json_decode( wp_remote_retrieve_body( $response ), true );
            $msg = isset($body_err['message']) ? $body_err['message'] : "Erro Gov: $code";
            return new WP_Error( 'api_error', $msg );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        
        // Validação de Situação
        $status_code = $data['situacao']['codigo'] ?? 0;
        if ( in_array( $status_code, [3, 5, 8, 9] ) ) return new WP_Error( 'cpf_blocked', 'CPF Irregular na Receita.' );

        // Formatação
        $dob = $data['nascimento'] ?? '';
        $dob_fmt = (strlen($dob) === 8) ? substr($dob,4,4).'-'.substr($dob,2,2).'-'.substr($dob,0,2) : '';
        $name = !empty($data['nomeSocial']) ? $data['nomeSocial'] : ($data['nome'] ?? '');

        return [
            'success' => true,
            'name'    => ucwords(strtolower($name)),
            'dob'     => $dob_fmt,
            'status'  => $data['situacao']['descricao'] ?? 'Regular'
        ];
    }
}