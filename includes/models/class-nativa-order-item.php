<?php
/**
 * Model: Nativa Order Item
 * Gerencia os itens dentro de uma sessão.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Order_Item {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'nativa_order_items';
    }

    public function init() {
        $this->create_table();
    }

    public function create_table() {
        global $wpdb;

        // Verifica se a tabela já existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") === $this->table_name;

        if ( ! $table_exists ) {
            // Cria do zero
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $this->table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                session_id bigint(20) NOT NULL,
                product_id bigint(20) NOT NULL,
                product_name varchar(255) NOT NULL,
                quantity int(11) NOT NULL DEFAULT 1,
                unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
                modifiers_json text DEFAULT NULL,
                status varchar(50) DEFAULT 'pending',
                sub_account varchar(100) DEFAULT 'Principal',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY session_id (session_id)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        } else {
            // Se já existe, apenas garante que a coluna sub_account exista
            // evitando erro de FK no session_id
            $column_check = $wpdb->get_results( "SHOW COLUMNS FROM $this->table_name LIKE 'sub_account'" );
            if ( empty( $column_check ) ) {
                $wpdb->query( "ALTER TABLE $this->table_name ADD COLUMN sub_account varchar(100) DEFAULT 'Principal'" );
            }
        }
    }

    public function add_item( $session_id, $product_id, $qty = 1, $modifiers = null, $sub_account = 'Principal' ) {
        global $wpdb;

        $product = get_post( $product_id );
        if ( ! $product ) return new WP_Error( 'invalid_product', 'Produto não encontrado.' );

        $price = get_post_meta( $product_id, 'price', true ) ?: get_post_meta( $product_id, 'produto_preco', true );
        
        $data = array(
            'session_id'   => $session_id,
            'product_id'   => $product_id,
            'product_name' => $product->post_title,
            'quantity'     => $qty,
            'unit_price'   => $price,
            'modifiers_json' => $modifiers ? json_encode( $modifiers ) : null,
            'status'       => 'pending',
            'sub_account'  => $sub_account 
        );

        $wpdb->insert( $this->table_name, $data );
        return $wpdb->insert_id;
    }

    public function get_items_by_session( $session_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM $this->table_name WHERE session_id = %d AND status != 'cancelled'", 
            $session_id 
        ) );
    }

    public function transfer_items( $item_ids, $target_session_id, $target_account ) {
        global $wpdb;

        if ( empty($item_ids) || !is_array($item_ids) ) return false;

        $data = array( 'sub_account' => $target_account );
        
        if ( $target_session_id ) {
            $data['session_id'] = $target_session_id;
        }

        $ids_sanitized = implode( ',', array_map( 'intval', $item_ids ) );
        
        $set_sql = [];
        foreach($data as $col => $val) {
            $set_sql[] = "$col = '" . esc_sql($val) . "'"; 
        }
        $set_string = implode(', ', $set_sql);

        $sql = "UPDATE $this->table_name SET $set_string WHERE id IN ($ids_sanitized)";
        
        return $wpdb->query( $sql );
    }
}