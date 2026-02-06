<?php
/**
 * Service: Gestão Centralizada de Pedidos
 * Lida com DB, Estoque, Fidelidade e chama o Notification Service
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Order_Service {

    public static function update_status( $order_id, $new_status, $changed_by_user_id = 0, $request_review = false ) {
        global $wpdb;
        
        $session = $wpdb->get_row($wpdb->prepare("SELECT id, status, client_id, type FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $order_id));
        if ( ! $session ) return new WP_Error( 'not_found', 'Pedido não encontrado.' );

        $map = [ 'novo' => 'new', 'preparando' => 'preparing', 'entrega' => 'delivering', 'concluido' => 'finished', 'cancelado' => 'cancelled' ];
        $db_status = $map[$new_status] ?? $new_status;

        if ( $session->status === $db_status && $db_status !== 'finished' ) return true;

        // --- CANCELAMENTO ---
        if ( $db_status === 'cancelled' ) {
            self::process_cancellation( $order_id, $changed_by_user_id );
        }

        // --- ATUALIZAÇÃO ---
        $wpdb->update( 
            $wpdb->prefix . 'nativa_sessions', 
            ['status' => $db_status], 
            ['id' => $order_id] 
        );

        // --- NOTIFICAÇÃO ---
        if ( class_exists('Nativa_Notification_Service') ) {
            Nativa_Notification_Service::notify_status_change( 
                $order_id, 
                $db_status, 
                $session->client_id, 
                $session->type,
                $request_review // Repassa a opção de avaliação
            );
        }

        return $db_status;
    }

    private static function process_cancellation( $order_id, $user_id ) {
        global $wpdb;

        if ( class_exists('Nativa_Stock') ) {
            $stock_model = new Nativa_Stock();
            $items = $wpdb->get_results( $wpdb->prepare("SELECT product_id, quantity FROM {$wpdb->prefix}nativa_order_items WHERE session_id = %d", $order_id) );
            if ( $items ) {
                foreach ($items as $item) {
                    $current = (float) get_post_meta( $item->product_id, 'nativa_estoque_qtd', true );
                    $stock_model->adjust_stock($item->product_id, $current + (int)$item->quantity, 'restock', $user_id, "Cancelamento Pedido #$order_id");
                }
            }
        }

        if ( class_exists('Nativa_Loyalty') ) {
            $loyalty = new Nativa_Loyalty();
            $loyalty->cancel_points( $order_id );
            if ( method_exists($loyalty, 'refund_points') ) {
                $loyalty->refund_points( $order_id );
            }
        }

        $wpdb->update($wpdb->prefix . 'nativa_order_items', ['status' => 'cancelled'], ['session_id' => $order_id]);

        if ( class_exists('Nativa_Session_Log') ) {
            $logger = new Nativa_Session_Log();
            $logger->log($order_id, 'order_cancelled', "Pedido #$order_id cancelado", [], 'completed');
        }
    }
}