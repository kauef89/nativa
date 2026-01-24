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

        // Verifica se a tabela já existe para aplicar patches
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") === $this->table_name;

        $charset_collate = $wpdb->get_charset_collate();
        
        // ADICIONADO: Campo line_total para armazenar o valor final (ou saldo devedor) do item
        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            product_name varchar(255) NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
            line_total decimal(10,2) NOT NULL DEFAULT 0.00, 
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

        // Patch de segurança: Garante que a coluna exista mesmo se o dbDelta falhar na alteração
        if ( $table_exists ) {
            $column = $wpdb->get_results( "SHOW COLUMNS FROM $this->table_name LIKE 'line_total'" );
            if ( empty( $column ) ) {
                $wpdb->query( "ALTER TABLE $this->table_name ADD COLUMN line_total decimal(10,2) NOT NULL DEFAULT 0.00" );
            }
        }
    }

    public function add_item( $session_id, $product_id, $qty = 1, $modifiers = null, $sub_account = 'Principal' ) {
        global $wpdb;

        $product = get_post( $product_id );
        if ( ! $product ) return new WP_Error( 'invalid_product', 'Produto não encontrado.' );

        // 1. Preço Base
        $price = get_post_meta( $product_id, 'price', true ) ?: get_post_meta( $product_id, 'produto_preco', true );
        $price = (float) $price;

        // 2. Calcula Adicionais (CORREÇÃO: O total deve incluir os modificadores)
        $modifiers_total = 0;
        if ( !empty($modifiers) && is_array($modifiers) ) {
            foreach ( $modifiers as $mod ) {
                // Suporta formato objeto ou array
                $m_price = is_array($mod) ? ($mod['price'] ?? 0) : ($mod->price ?? 0);
                $modifiers_total += (float) $m_price;
            }
        }

        // 3. Calcula Total da Linha (Inicialmente é o valor total a pagar)
        $unit_final = $price + $modifiers_total;
        $line_total = $unit_final * $qty;
        
        $data = array(
            'session_id'   => $session_id,
            'product_id'   => $product_id,
            'product_name' => $product->post_title,
            'quantity'     => $qty,
            'unit_price'   => $price, // Salva o base
            'line_total'   => $line_total, // Salva o total real (Base + Mods * Qtd)
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
        
        // Monta SET string
        $set_sql = [];
        foreach($data as $col => $val) {
            $set_sql[] = "$col = '" . esc_sql($val) . "'"; 
        }
        $set_string = implode(', ', $set_sql);

        $sql = "UPDATE $this->table_name SET $set_string WHERE id IN ($ids_sanitized)";
        
        return $wpdb->query( $sql );
    }
}