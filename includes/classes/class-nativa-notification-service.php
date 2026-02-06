<?php
/**
 * Service: Centralizador de Notificações
 * Gerencia o copywriting, lógica de mensagens e o disparo para OneSignal.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Notification_Service {

    /**
     * 1. Notifica criação de pedido (Envia para Staff e Cliente)
     */
    public static function notify_new_order( $session_id, $client_name, $total, $has_pix, $client_user_id = 0 ) {
        if ( ! class_exists('Nativa_OneSignal') ) return;

        // A. Notifica Staff (Segmento 'Staff')
        $heading_staff = "🛵 Novo Pedido #$session_id";
        $msg_staff = "$client_name fez um pedido de R$ " . number_format($total, 2, ',', '.') . ".";
        
        if ($has_pix) {
            $msg_staff .= " (Aguardando Pix)";
        } else {
            $msg_staff .= " Confirmado, pode preparar!";
        }

        Nativa_OneSignal::send(
            $msg_staff, 
            [
                'type' => 'new_order', 
                'order_id' => $session_id, 
                'heading' => $heading_staff
            ], 
            'Staff'
        );

        // B. Notifica Cliente (se identificado)
        if ( $client_user_id ) {
            $player_id = get_user_meta($client_user_id, 'nativa_onesignal_id', true);
            
            if ($player_id) {
                Nativa_OneSignal::send_to_client(
                    $player_id, 
                    "Avisaremos quando for para a cozinha 🧑‍🍳", 
                    [
                        'type' => 'order_created', 
                        'id' => $session_id, 
                        'heading' => "Recebemos seu pedido"
                    ]
                );
            }
        }
    }

    /**
     * 2. Notifica mudança de status para o Cliente (Copywriting Personalizado)
     * * @param int $session_id ID do Pedido
     * @param string $new_status Novo status (preparando, delivery, finished, cancelled)
     * @param int $client_id ID do Usuário WP do cliente
     * @param string $session_type 'delivery' ou 'pickup'
     * @param bool $request_review Se true, envia link de avaliação (apenas em finished)
     */
    public static function notify_status_change( $session_id, $new_status, $client_id, $session_type = 'delivery', $request_review = false ) {
        if ( ! class_exists('Nativa_OneSignal') || !$client_id ) return;

        $player_id = get_user_meta($client_id, 'nativa_onesignal_id', true);
        if ( ! $player_id ) return;

        $msg_title = "Atualização do Pedido";
        $msg_body = "Seu pedido mudou de status.";
        $url = null;

        // --- REGRAS DE TEXTO (COPYWRITING) ---
        switch ( $new_status ) {
            case 'preparing':
                $msg_title = "Já estamos preparando";
                $msg_body = "Aguenta aí que logo fica pronto 😋";
                break;
            
            case 'delivering':
                if ( $session_type === 'pickup' ) {
                    $msg_title = "Seu pedido já está pronto";
                    $msg_body = "Não deixe ele esfriar aqui 🫶";
                } else {
                    // Padrão Delivery
                    $msg_title = "Seu pedido já está em rota";
                    $msg_body = "Se prepare para receber o motoboy 🫶";
                }
                break;
            
            case 'finished':
            case 'closed': // Aceita 'closed' como sinônimo de finished para o cliente
                if ( $request_review ) {
                    // Busca link nas configurações do Nativa
                    $settings = get_option( 'nativa_general_settings', [] );
                    $review_link = $settings['google_reviews'] ?? '';

                    if ( !empty($review_link) ) {
                        $msg_title = "Gostou da sua experiência?";
                        $msg_body = "Deixe uma avaliação 5 estrelas pra gente! 😍";
                        $url = $review_link;
                    } else {
                        // Fallback se não tiver link configurado
                        $msg_title = "Seu pedido foi finalizado";
                        $msg_body = "Agradecemos a preferência 🥰";
                    }
                } else {
                    $msg_title = "Seu pedido foi finalizado";
                    $msg_body = "Agradecemos a preferência 🥰";
                }
                break;

            case 'cancelled':
                $msg_title = "Seu pedido foi cancelado";
                $msg_body = "Até a próxima! 😀👋";
                break;
        }

        // Envia para o Cliente
        Nativa_OneSignal::send_to_client(
            $player_id, 
            $msg_body, 
            [
                'type' => 'order_update', 
                'id' => $session_id, 
                'heading' => $msg_title
            ],
            $url // Se houver URL (avaliação), o OneSignal abrirá ao clicar
        );
    }

    /**
     * 3. Notifica o Staff sobre solicitações do Activity Feed
     * (Trocas, Cancelamentos de Item, Descontos)
     */
    public static function notify_staff_activity( $log_id, $type, $description, $session_id ) {
        if ( ! class_exists('Nativa_OneSignal') ) return;

        $titles = [
            'item_cancel'  => '🗑️ Cancelamento de Item',
            'item_swap'    => '🔄 Troca de Item',
            'discount'     => '💸 Solicitação de Desconto',
            'order_cancel' => '🚫 Cancelamento de Pedido',
            'generic'      => '⚠️ Solicitação Pendente'
        ];

        $heading_base = $titles[$type] ?? $titles['generic'];
        
        // Formata Título: "🗑️ Cancelamento de Item (Pedido #123)"
        $heading = "$heading_base (Pedido #$session_id)";

        // Envia para o segmento 'Staff'
        Nativa_OneSignal::send(
            $description, // Ex: "Garçom João pede para cancelar 1x Coxinha"
            [
                'type' => 'activity_request', 
                'log_id' => $log_id,
                'heading' => $heading
            ], 
            'Staff'
        );
    }
}