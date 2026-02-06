<?php
/**
 * Model: Sistema de Fidelidade
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Loyalty {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'nativa_loyalty_ledger';
    }

    public function init() {
        $this->create_table();
        add_action( 'nativa_daily_loyalty_check', array( $this, 'process_daily_expiration' ) );
    }

    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            points int(11) NOT NULL,
            type varchar(50) NOT NULL,
            reference_id bigint(20) DEFAULT NULL,
            description varchar(255) DEFAULT NULL,
            status varchar(20) DEFAULT 'completed',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expiry_date datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function get_balance( $user_id ) {
        if ( ! $user_id ) return 0;
        global $wpdb;
        
        $has_history = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $this->table_name WHERE user_id = %d LIMIT 1", $user_id ) );
        if ( ! $has_history ) {
            $legacy_points = (int) get_user_meta( $user_id, 'nativa_user_points', true );
            if ( $legacy_points > 0 ) {
                $settings = get_option( 'nativa_loyalty_settings', [] );
                $days = intval($settings['validity_days'] ?? 365);
                $expiry = date('Y-m-d H:i:s', strtotime("+$days days"));

                $this->log_transaction( $user_id, $legacy_points, 'migration', 0, 'Saldo Migrado', 'completed', $expiry );
            }
        }

        $balance = $wpdb->get_var( $wpdb->prepare( 
            "SELECT SUM(points) FROM $this->table_name WHERE user_id = %d AND status = 'completed'", 
            $user_id 
        ) );

        return (int) $balance;
    }

    public function log_transaction( $user_id, $points, $type, $ref_id = 0, $desc = '', $status = 'completed', $expiry_date = null ) {
        global $wpdb;
        
        $wpdb->insert( $this->table_name, array(
            'user_id'      => $user_id,
            'points'       => $points,
            'type'         => $type,
            'reference_id' => $ref_id,
            'description'  => $desc,
            'status'       => $status,
            'created_at'   => current_time('mysql'),
            'expiry_date'  => $expiry_date
        ));

        return $wpdb->insert_id;
    }

    public function add_pending_points( $user_id, $amount, $order_id ) {
        $settings = get_option( 'nativa_loyalty_settings', [] );
        $days = intval($settings['validity_days'] ?? 365);
        $expiry = date('Y-m-d H:i:s', strtotime("+$days days"));

        return $this->log_transaction( 
            $user_id, 
            $amount, 
            'earn', 
            $order_id, 
            "Pontos do Pedido #$order_id (Aguardando Pagamento)", 
            'pending',
            $expiry 
        );
    }

    public function confirm_points( $order_id ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( 
            "UPDATE $this->table_name SET status = 'completed', description = REPLACE(description, '(Aguardando Pagamento)', '(Confirmado)') WHERE reference_id = %d AND type = 'earn' AND status = 'pending'", 
            $order_id 
        ));
    }

    public function cancel_points( $order_id ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( 
            "UPDATE $this->table_name SET status = 'cancelled' WHERE reference_id = %d AND status = 'pending'", 
            $order_id 
        ));
    }

    public function redeem_points( $user_id, $points_cost, $order_id ) {
        $balance = $this->get_balance( $user_id );
        if ( $balance < $points_cost ) return new WP_Error( 'insufficient_funds', 'Saldo insuficiente.' );

        $this->log_transaction( 
            $user_id, 
            -1 * abs($points_cost), 
            'redeem', 
            $order_id, 
            "Resgate no Pedido #$order_id", 
            'completed' 
        );
        return true;
    }

    public function refund_points( $order_id ) {
        global $wpdb;
        
        $redeem = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE reference_id = %d AND type = 'redeem' AND status = 'completed'", 
            $order_id
        ));

        if ( $redeem ) {
            $points_to_refund = abs( $redeem->points );
            
            $settings = get_option( 'nativa_loyalty_settings', [] );
            $days = intval($settings['validity_days'] ?? 365);
            $expiry = date('Y-m-d H:i:s', strtotime("+$days days"));

            $this->log_transaction(
                $redeem->user_id,
                $points_to_refund,
                'refund',
                $order_id,
                "Estorno de Resgate - Pedido #$order_id Cancelado",
                'completed',
                $expiry
            );
            return true;
        }
        return false;
    }

    public function process_daily_expiration() {
        global $wpdb;
        
        $users = $wpdb->get_col("SELECT DISTINCT user_id FROM $this->table_name WHERE status = 'completed' GROUP BY user_id HAVING SUM(points) > 0");

        if ( empty($users) ) return;

        $now = current_time('mysql');

        foreach ( $users as $user_id ) {
            $valid_earnings = $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(points) FROM $this->table_name 
                 WHERE user_id = %d AND status = 'completed' AND points > 0 
                 AND (expiry_date IS NULL OR expiry_date > %s)",
                $user_id, $now
            ) ) ?: 0;

            $total_spent_abs = $wpdb->get_var( $wpdb->prepare(
                "SELECT ABS(SUM(points)) FROM $this->table_name 
                 WHERE user_id = %d AND status = 'completed' AND points < 0",
                $user_id
            ) ) ?: 0;

            $virtual_balance = max(0, $valid_earnings - $total_spent_abs);
            $actual_balance = $this->get_balance($user_id);
            $expired_amount = $actual_balance - $virtual_balance;

            if ( $expired_amount > 0 ) {
                $this->log_transaction(
                    $user_id,
                    -1 * abs($expired_amount),
                    'expired',
                    0,
                    'Expiração automática de pontos',
                    'completed'
                );
            }
        }
    }
    
    public function get_history( $user_id, $limit = 20 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM $this->table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d", 
            $user_id, $limit 
        ));
    }

    public function get_today_redeemed_count( $user_id ) {
        global $wpdb;
        
        $sql = "SELECT SUM(oi.quantity) 
                FROM {$wpdb->prefix}nativa_order_items oi
                INNER JOIN {$wpdb->prefix}nativa_sessions s ON oi.session_id = s.id
                WHERE s.client_id = %d
                AND s.status != 'cancelled'
                AND DATE(s.created_at) = CURDATE()
                AND oi.unit_price = 0"; 

        $count = $wpdb->get_var( $wpdb->prepare($sql, $user_id) );
        return $count ? (int)$count : 0;
    }
}