<?php
/**
 * Model: Item do Pedido
 * Gerencia a tabela wp_nativa_order_items e wp_nativa_sub_accounts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Order_Item {

    private $table_items;
    private $table_sub_accounts;

    public function __construct() {
        global $wpdb;
        $this->table_items = $wpdb->prefix . 'nativa_order_items';
        $this->table_sub_accounts = $wpdb->prefix . 'nativa_sub_accounts';
    }

    /**
     * Adiciona um item à Sessão
     */
    public function add_item( $session_id, $product_id, $qty = 1, $modifiers = null, $sub_account_name = 'Principal' ) {
        global $wpdb;

        // 1. Garante que existe uma Sub-conta (Comanda)
        $sub_account_id = $this->ensure_sub_account( $session_id, $sub_account_name );
        if ( is_wp_error( $sub_account_id ) ) {
            return $sub_account_id;
        }

        // 2. Busca dados do Produto
        $product = get_post( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'product_not_found', 'Produto não encontrado.' );
        }

        // --- CORREÇÃO AQUI: Prioridade para os campos do Nativa V1 ---
        $price = get_field( 'produto_preco', $product_id ); // Campo principal
        
        // Fallbacks (caso o campo principal falhe)
        if ( ! $price ) $price = get_field( 'price', $product_id );
        if ( ! $price ) $price = get_post_meta( $product_id, 'preco', true );
        if ( ! $price ) $price = get_post_meta( $product_id, '_price', true ); // WooCommerce
        
        // Garante formato decimal
        $price = floatval( $price ); 

        // 3. Modificadores JSON
        $modifiers_json = null;
        if ( ! empty( $modifiers ) ) {
            $modifiers_json = json_encode( $modifiers, JSON_UNESCAPED_UNICODE );
        }

        // 4. Insere
        $inserted = $wpdb->insert(
            $this->table_items,
            array(
                'session_id'     => $session_id,
                'sub_account_id' => $sub_account_id,
                'product_id'     => $product_id,
                'product_name'   => $product->post_title,
                'unit_price'     => $price,
                'quantity'       => $qty,
                'modifiers_json' => $modifiers_json,
                'status'         => 'pending',
                'created_at'     => current_time( 'mysql' )
            )
        );

        if ( ! $inserted ) {
            return new WP_Error( 'db_error', 'Erro ao inserir item: ' . $wpdb->last_error );
        }

        return $wpdb->insert_id;
    }

    /**
     * Busca todos os itens de uma sessão
     */
    public function get_items_by_session( $session_id ) {
        global $wpdb;
        
        $query = $wpdb->prepare( 
            "SELECT i.*, s.name as sub_account_name 
             FROM {$this->table_items} i
             LEFT JOIN {$this->table_sub_accounts} s ON i.sub_account_id = s.id
             WHERE i.session_id = %d 
             ORDER BY i.created_at DESC", 
            $session_id 
        );

        return $wpdb->get_results( $query );
    }

    /**
     * Verifica/Cria Sub-conta
     */
    private function ensure_sub_account( $session_id, $name ) {
        global $wpdb;

        $query = $wpdb->prepare( 
            "SELECT id FROM {$this->table_sub_accounts} WHERE session_id = %d AND name = %s LIMIT 1", 
            $session_id, $name 
        );
        $exists = $wpdb->get_var( $query );

        if ( $exists ) return $exists;

        $inserted = $wpdb->insert(
            $this->table_sub_accounts,
            array( 'session_id' => $session_id, 'name' => $name )
        );

        return $inserted ? $wpdb->insert_id : new WP_Error( 'sub_account_error', 'Erro ao criar sub-conta.' );
    }
}