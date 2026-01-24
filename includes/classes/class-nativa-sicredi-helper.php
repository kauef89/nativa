<?php
/**
 * Helper: Integração Sicredi API Pix V2
 * Lida com certificados mTLS, OAuth2 e geração de Cobranças.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Sicredi_Helper {

    private $base_url = 'https://api-pix.sicredi.com.br/api/v2';
    private $auth_url = 'https://api-pix.sicredi.com.br/oauth/token';
    
    private $client_id;
    private $client_secret;
    private $cert_path;
    private $key_path;
    private $chave_pix;

    public function __construct() {
        // Carrega configurações do banco de dados (migrar do ACF/Settings API)
        $this->client_id     = get_option('nativa_sicredi_client_id');
        $this->client_secret = get_option('nativa_sicredi_client_secret');
        $this->chave_pix     = get_option('nativa_sicredi_pix_key');
        
        // Certificados devem estar em pasta segura fora do public_html idealmente
        // Para este exemplo, assumimos uploads protegidos
        $this->cert_path     = get_attached_file( get_option('nativa_sicredi_cert_id') ); 
        $this->key_path      = get_attached_file( get_option('nativa_sicredi_key_id') );
    }

    /**
     * Obtém o Token de Acesso (com Cache Inteligente)
     */
    private function get_token() {
        $cached_token = get_transient( 'nativa_sicredi_token' );
        if ( $cached_token ) return $cached_token;

        if ( ! file_exists($this->cert_path) || ! file_exists($this->key_path) ) {
            return new WP_Error( 'cert_missing', 'Certificados Sicredi não encontrados.' );
        }

        $auth = base64_encode( $this->client_id . ':' . $this->client_secret );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->auth_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials&scope=cob.write cob.read');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic $auth",
            "Content-Type: application/x-www-form-urlencoded"
        ]);
        curl_setopt($ch, CURLOPT_SSLCERT, $this->cert_path);
        curl_setopt($ch, CURLOPT_SSLKEY, $this->key_path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $body = json_decode($response, true);

        if ( $http_code !== 200 || ! isset($body['access_token']) ) {
            return new WP_Error( 'auth_failed', 'Falha na autenticação Sicredi: ' . ($body['error_description'] ?? 'Erro desconhecido') );
        }

        // Salva cache por 50 minutos (tokens duram 60)
        set_transient( 'nativa_sicredi_token', $body['access_token'], 50 * MINUTE_IN_SECONDS );

        return $body['access_token'];
    }

    /**
     * Cria uma Cobrança Imediata (Cob)
     */
    public function create_charge( $session_id, $amount ) {
        $token = $this->get_token();
        if ( is_wp_error($token) ) return $token;

        // O TxID deve ser único e ter entre 26 e 35 caracteres
        $txid = $this->generate_txid($session_id);

        $payload = [
            'calendario' => [ 'expiracao' => 300 ], // <--- MUDANÇA: 300 segundos (5 minutos)
            'devedor' => null,
            'valor' => [ 'original' => number_format($amount, 2, '.', '') ],
            'chave' => $this->chave_pix,
            'solicitacaoPagador' => "Pedido #$session_id - Nativa Delivery"
        ];

        $response = $this->request( "PUT", "/cob/$txid", $payload, $token );

        if ( is_wp_error($response) ) return $response;

        // Salva o TxID na sessão para consultas futuras
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'nativa_sessions',
            ['pix_txid' => $txid, 'pix_string' => $response['pixCopiaECola']],
            ['id' => $session_id]
        );

        return [
            'success' => true,
            'txid' => $txid,
            'pix_copy_paste' => $response['pixCopiaECola'] // Frontend vai transformar isso em QR
        ];
    }

    /**
     * Verifica se uma cobrança foi paga
     */
    public function check_status( $txid ) {
        $token = $this->get_token();
        if ( is_wp_error($token) ) return $token;

        $response = $this->request( "GET", "/cob/$txid", [], $token );
        
        if ( is_wp_error($response) ) return $response;

        return [
            'status' => $response['status'], // ATIVA, CONCLUIDA, REMOVIDA_PELO_USUARIO_RECEBEDOR, REMOVIDA_PELO_PSP
            'paid' => ($response['status'] === 'CONCLUIDA')
        ];
    }

    private function request( $method, $endpoint, $data, $token ) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url . $endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        $headers = [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ];
        
        if ( $method === 'POST' || $method === 'PUT' ) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSLCERT, $this->cert_path);
        curl_setopt($ch, CURLOPT_SSLKEY, $this->key_path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $body = json_decode($response, true);

        if ( $http_code >= 400 ) {
            return new WP_Error( 'request_error', 'Erro Sicredi: ' . ($body['mensagem'] ?? 'Erro API') );
        }

        return $body;
    }

    private function generate_txid($session_id) {
        // Formato: NATIVA + timestamp + sessionID (max 35 chars)
        // Ex: NATIVA1678901234SID5050
        return 'NATIVA' . time() . 'SID' . $session_id; 
    }
}