<?php
/**
 * Model: Gestão de Estoque (Logs e Movimentações)
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
            qty_change decimal(10,2) NOT NULL, /* Ex: -1, +10 */
            balance_after decimal(10,2) NOT NULL, /* Saldo final após o ajuste */
            reason varchar(50) NOT NULL, /* 'sale', 'supply', 'adjustment', 'waste' */
            note varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY product_id (product_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Atualiza o estoque de um produto e gera log
     */
    public function adjust_stock( $product_id, $new_qty, $reason, $user_id = 0, $note = '' ) {
        // 1. Verifica se o produto gerencia estoque
        $manage_stock = get_post_meta( $product_id, 'nativa_estoque_ativo', true );
        if ( ! $manage_stock ) return false; // Se não gerencia, ignora

        $current_qty = (float) get_post_meta( $product_id, 'nativa_estoque_qtd', true );
        $diff = $new_qty - $current_qty;

        if ( $diff == 0 ) return true; // Nada mudou

        // 2. Atualiza o Meta Principal
        update_post_meta( $product_id, 'nativa_estoque_qtd', $new_qty );

        // 3. Sincroniza com status V1 (Opcional, mas recomendado)
        // Se zerou, marca como indisponível? 
        // Por enquanto vamos deixar independente, mas podemos descomentar abaixo:
        /*
        if ( $new_qty <= 0 ) {
            update_post_meta( $product_id, 'produto_disponibilidade', 'indisponivel' );
        } elseif ( $new_qty > 0 && get_post_meta($product_id, 'produto_disponibilidade', true) === 'indisponivel' ) {
            update_post_meta( $product_id, 'produto_disponibilidade', 'disponivel' );
        }
        */

        // 4. Grava o Log
        global $wpdb;
        $wpdb->insert( $this->table_name, array(
            'product_id'    => $product_id,
            'user_id'       => $user_id ?: get_current_user_id(),
            'qty_change'    => $diff,
            'balance_after' => $new_qty,
            'reason'        => $reason,
            'note'          => $note
        ));

        return true;
    }

    public function get_logs( $product_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE product_id = %d ORDER BY id DESC LIMIT 50", $product_id ) );
    }
}