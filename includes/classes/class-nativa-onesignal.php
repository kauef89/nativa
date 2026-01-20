<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Nativa_OneSignal {

    public static function send( $message, $data = array() ) {
        
        // 1. Verifica se as constantes foram definidas no wp-config.php
        if ( ! defined( 'NATIVA_ONESIGNAL_APP_ID' ) || ! defined( 'NATIVA_ONESIGNAL_REST_KEY' ) ) {
            error_log( '[Nativa OneSignal] ERRO: Constantes de configuração ausentes no wp-config.php.' );
            return;
        }

        $app_id = NATIVA_ONESIGNAL_APP_ID;
        $rest_key = NATIVA_ONESIGNAL_REST_KEY;

        // Proteção extra: não tenta enviar se for o valor placeholder
        if ( $app_id === 'SEU_APP_ID_AQUI' ) return;

        $fields = array(
            'app_id' => $app_id,
            'included_segments' => array( 'All' ), 
            'contents' => array( 'en' => $message ),
            'data' => $data,
            'small_icon' => 'ic_stat_onesignal_default'
        );

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Basic ' . $rest_key // <--- Usa a constante aqui
            ),
            'body' => json_encode( $fields ),
            'timeout' => 5
        );

        $response = wp_remote_post( 'https://onesignal.com/api/v1/notifications', $args );

        if ( is_wp_error( $response ) ) {
            error_log( '[Nativa OneSignal] Falha no envio: ' . $response->get_error_message() );
        }
    }
}