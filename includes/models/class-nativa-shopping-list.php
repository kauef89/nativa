<?php
/**
 * Model: Tabela de Lista de Compras
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Shopping_List {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'nativa_shopping_list';
    }

    public function init() {
        $this->create_table();
    }

    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            insumo_id bigint(20) NOT NULL,
            quantity decimal(10,2) NOT NULL DEFAULT 1,
            unit varchar(20) DEFAULT 'un',
            status varchar(20) DEFAULT 'pending',
            purchased_at datetime DEFAULT NULL,
            location_id bigint(20) DEFAULT NULL,
            cost decimal(10,2) DEFAULT 0.00,
            purchase_url text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY status (status)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function add_item( $insumo_id, $qty, $unit = 'un', $url = '' ) {
        global $wpdb;
        $existing = $wpdb->get_row( $wpdb->prepare("SELECT id, quantity FROM $this->table_name WHERE insumo_id = %d AND status = 'pending'", $insumo_id) );
        if ( $existing ) {
            $new_qty = $existing->quantity + $qty;
            $data = ['quantity' => $new_qty];
            if (!empty($url)) $data['purchase_url'] = $url;
            $updated = $wpdb->update( $this->table_name, $data, ['id' => $existing->id] );
            if ($updated === false) {
                error_log("❌ [Nativa DB Error] Falha ao atualizar item #{$existing->id}: " . $wpdb->last_error);
            }
            return $existing->id;
        }

        $inserted = $wpdb->insert( $this->table_name, array(
            'insumo_id' => $insumo_id,
            'quantity'  => $qty,
            'unit'      => $unit,
            'purchase_url' => $url,
            'status'    => 'pending',
            'created_by'=> get_current_user_id()
        ));

        if ( $inserted === false ) {
            error_log("❌ [Nativa DB Error] Falha ao inserir na lista de compras: " . $wpdb->last_error);
            return 0;
        }

        return $wpdb->insert_id;
    }

    public function mark_purchased( $id, $location_id, $cost ) {
        global $wpdb;
        return $wpdb->update( 
            $this->table_name, 
            array(
                'status' => 'purchased',
                'purchased_at' => current_time('mysql'),
                'location_id' => $location_id,
                'cost' => $cost
            ),
            array( 'id' => $id )
        );
    }
    
    public function get_pending_items() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM $this->table_name WHERE status = 'pending' ORDER BY created_at DESC" );
    }

    public function get_history( $limit = 50 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare("SELECT * FROM $this->table_name WHERE status = 'purchased' ORDER BY purchased_at DESC LIMIT %d", $limit) );
    }
}