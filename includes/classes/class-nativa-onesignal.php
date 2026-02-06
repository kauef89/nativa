<?php
/**
 * Class: Integração OneSignal (Push Notifications)
 * Gerencia envios para Staff (Segmentos) e Clientes (Player IDs)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_OneSignal {

    /**
     * Envia para Staff (Segmentos)
     */
    public static function send( $message, $data = [], $segment = 'All' ) {
        $fields = array(
            'included_segments' => array( $segment ),
            'contents'          => array( "en" => $message, "pt" => $message ),
            'data'              => $data
        );

        if ( !empty($data['heading']) ) {
            $fields['headings'] = array( "en" => $data['heading'], "pt" => $data['heading'] );
        } else {
            $fields['headings'] = array( "en" => "Nativa", "pt" => "Nativa" );
        }
        
        self::make_request( $fields );
    }

    /**
     * Envia notificação para um Cliente específico
     * CORREÇÃO: Agora respeita o título (heading) enviado no $data
     */
    public static function send_to_client( $player_id, $message, $data = [], $url = null ) {
        if ( empty( $player_id ) ) return;

        $fields = array(
            'include_player_ids' => array( $player_id ),
            'contents'           => array( "en" => $message, "pt" => $message ),
            'data'               => $data
        );

        // --- CORREÇÃO AQUI ---
        // Verifica se há um título personalizado nos dados, senão usa o padrão
        if ( !empty($data['heading']) ) {
            $fields['headings'] = array( "en" => $data['heading'], "pt" => $data['heading'] );
        } else {
            $fields['headings'] = array( "en" => "Atualização do Pedido", "pt" => "Atualização do Pedido" );
        }
        // ---------------------

        if ( $url ) {
            $fields['url'] = $url;
        }

        self::make_request( $fields );
    }

    private static function get_api_key() {
        if ( defined('NATIVA_ONESIGNAL_REST_API_KEY') ) {
            return NATIVA_ONESIGNAL_REST_API_KEY;
        }
        if ( defined('NATIVA_ONESIGNAL_API_KEY') ) {
            return NATIVA_ONESIGNAL_API_KEY;
        }
        return '';
    }

    private static function make_request( $fields ) {
        $fields['app_id'] = defined('NATIVA_ONESIGNAL_APP_ID') ? NATIVA_ONESIGNAL_APP_ID : '';
        $rest_key = self::get_api_key();
        
        if ( empty($fields['app_id']) || empty($rest_key) ) {
            error_log('🔴 [OneSignal] Chaves não configuradas.');
            return;
        }

        $json_fields = json_encode( $fields );
        
        // Logs para Debug (pode comentar depois)
        // error_log("🚀 [OneSignal] Enviando Payload: " . $json_fields);

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications" );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $rest_key
        ));
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
        curl_setopt( $ch, CURLOPT_HEADER, FALSE );
        curl_setopt( $ch, CURLOPT_POST, TRUE );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_fields );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );

        $response = curl_exec( $ch );
        
        if ($response === false) {
             error_log("🔴 [OneSignal Error] " . curl_error($ch));
        }

        curl_close( $ch );
        return $response;
    }
}