<?php
/**
 * Model: Gestão de Estoque
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Stock {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'nativa_stock_logs';
    }

    public function init() {
        $this->create_table();
    }

    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            qty_change decimal(10,2) NOT NULL,
            balance_after decimal(10,2) NOT NULL,
            reason varchar(50) NOT NULL,
            note varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY product_id (product_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function adjust_stock( $product_id, $change_qty, $reason, $user_id = 0, $note = '' ) {
        $is_controlled = get_post_meta( $product_id, 'nativa_stock_controlled', true );
        
        if ( $is_controlled === '' ) {
            $is_controlled = get_post_meta( $product_id, 'nativa_estoque_ativo', true );
        }

        if ( !$is_controlled ) return false;

        $current_qty = (float) get_post_meta( $product_id, 'nativa_estoque_qtd', true );
        $new_qty = $current_qty + $change_qty;

        if ( $change_qty == 0 ) return true;

        update_post_meta( $product_id, 'nativa_estoque_qtd', $new_qty );

        $min_qty = (float) get_post_meta( $product_id, 'nativa_stock_min', true );
        
        if ( $min_qty > 0 && $new_qty <= $min_qty && $current_qty > $min_qty ) {
            $this->trigger_low_stock_alert( $product_id, $new_qty );
        }

        global $wpdb;
        $wpdb->insert( $this->table_name, array(
            'product_id'    => $product_id,
            'user_id'       => $user_id ?: get_current_user_id(),
            'qty_change'    => $change_qty,
            'balance_after' => $new_qty,
            'reason'        => $reason,
            'note'          => $note
        ));

        return true;
    }

    private function trigger_low_stock_alert( $product_id, $current_qty ) {
        if ( class_exists('Nativa_Session_Log') ) {
            $product = get_post( $product_id );
            $name = $product ? $product->post_title : "Produto #$product_id";
            
            $logger = new Nativa_Session_Log();
            $logger->log(
                0, 
                'stock_low', 
                "Estoque Baixo: $name restam apenas $current_qty un.", 
                ['product_id' => $product_id, 'qty' => $current_qty], 
                'pending'
            );
        }
    }

    public function get_logs( $product_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE product_id = %d ORDER BY id DESC LIMIT 50", $product_id ) );
    }
}