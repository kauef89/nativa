<?php
/**
 * Class: Integração OneSignal (Push Notifications)
 * Gerencia envios para Staff (Segmentos) e Clientes (Player IDs)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_OneSignal {

    /**
     * Envia notificação para o Staff (Gerentes, Cozinha, etc)
     * @param string $message A mensagem a ser exibida
     * @param array $data Dados adicionais (JSON) para lógica do frontend (ex: {type: 'new_order'})
     * @param string $segment O segmento alvo no OneSignal (Default: 'All' ou 'Staff')
     */
    public static function send( $message, $data = [], $segment = 'All' ) {
        $fields = array(
            'included_segments' => array( $segment ),
            'contents'          => array( "en" => $message, "pt" => $message ),
            'data'              => $data
        );
        
        self::make_request( $fields );
    }

    /**
     * Envia notificação para um Cliente específico
     * @param string $player_id O ID único do dispositivo do cliente (salvo na sessão)
     * @param string $message A mensagem
     * @param array $data Dados adicionais
     */
    public static function send_to_client( $player_id, $message, $data = [] ) {
        if ( empty( $player_id ) ) return;

        $fields = array(
            'include_player_ids' => array( $player_id ),
            'contents'           => array( "en" => $message, "pt" => $message ),
            'data'               => $data
        );

        self::make_request( $fields );
    }

    /**
     * Executa a requisição cURL para a API do OneSignal
     */
    private static function make_request( $fields ) {
        // Injeta o App ID automaticamente
        $fields['app_id'] = defined('NATIVA_ONESIGNAL_APP_ID') ? NATIVA_ONESIGNAL_APP_ID : '';
        
        if ( empty($fields['app_id']) ) {
            error_log('Nativa OneSignal: APP ID não configurado.');
            return;
        }

        $fields = json_encode( $fields );

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications" );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . (defined('NATIVA_ONESIGNAL_API_KEY') ? NATIVA_ONESIGNAL_API_KEY : '')
        ));
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
        curl_setopt( $ch, CURLOPT_HEADER, FALSE );
        curl_setopt( $ch, CURLOPT_POST, TRUE );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );

        $response = curl_exec( $ch );
        curl_close( $ch );

        return $response;
    }
}