<?php
/**
 * Model: Nativa Order Item (V2.2 - Suporte a Preço Promocional e Override de Preço)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

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
        $charset_collate = $wpdb->get_charset_collate();
        
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
    }

    /**
     * Adiciona um item ao pedido.
     * * @param int $session_id ID da Sessão
     * @param int $product_id ID do Produto
     * @param int $qty Quantidade
     * @param array|null $modifiers Modificadores
     * @param string $sub_account Sub-conta (para mesas)
     * @param float|null $override_price Preço forçado (ex: 0.00 para Fidelidade). Se null, calcula do banco.
     */
    public function add_item( $session_id, $product_id, $qty = 1, $modifiers = null, $sub_account = 'Principal', $override_price = null ) {
        global $wpdb;

        $product = get_post( $product_id );
        if ( ! $product ) return new WP_Error( 'invalid_product', 'Produto não encontrado.' );

        // 1. Definição do Preço Base
        if ( ! is_null( $override_price ) ) {
            // Se foi passado um preço forçado (ex: resgate de pontos), usa ele.
            $unit_price = floatval( $override_price );
        } else {
            // Lógica Padrão: Preço Base vs Promocional
            $price_base = (float) (get_post_meta( $product_id, 'produto_preco', true ) ?: get_post_meta( $product_id, 'price', true ));
            $price_promo = (float) get_post_meta( $product_id, 'produto_preco_promocional', true );
            
            // Se existe promoção válida e menor que o preço base, usa ela
            $unit_price = ($price_promo > 0 && $price_promo < $price_base) ? $price_promo : $price_base;
        }

        // 2. Calcula Adicionais
        $modifiers_total = 0;
        if ( !empty($modifiers) && is_array($modifiers) ) {
            foreach ( $modifiers as $mod ) {
                $m_price = is_array($mod) ? ($mod['price'] ?? 0) : ($mod->price ?? 0);
                $modifiers_total += (float) $m_price;
            }
        }

        // 3. Calcula Total da Linha
        // Nota: Se for override_price = 0 (fidelidade), os modificadores devem ser cobrados?
        // Geralmente sim, mas se quiser zerar tudo, o controller deve passar modifiers zerados ou tratamos aqui.
        // Assumindo aqui que modificadores sempre somam. Se o unit_price for 0, cobra só os adicionais.
        $unit_final = $unit_price + $modifiers_total;
        $line_total = $unit_final * $qty;
        
        $data = array(
            'session_id'   => $session_id,
            'product_id'   => $product_id,
            'product_name' => $product->post_title,
            'quantity'     => $qty,
            'unit_price'   => $unit_price, // Salva o preço unitário efetivo
            'line_total'   => $line_total,
            'modifiers_json' => $modifiers ? json_encode( $modifiers ) : null,
            'status'       => 'pending',
            'sub_account'  => $sub_account 
        );

        $wpdb->insert( $this->table_name, $data );
        return $wpdb->insert_id;
    }

    public function get_items_by_session( $session_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE session_id = %d AND status != 'cancelled'", $session_id ) );
    }

    public function transfer_items( $item_ids, $target_session_id, $target_account ) {
        global $wpdb;
        if ( empty($item_ids) || !is_array($item_ids) ) return false;
        
        $data = array( 'sub_account' => $target_account );
        if ( $target_session_id ) $data['session_id'] = $target_session_id;
        
        $ids_sanitized = implode( ',', array_map( 'intval', $item_ids ) );
        
        // Monta SET clause manualmente para evitar problemas com prepared statements em array
        $set_sql = [];
        foreach($data as $col => $val) { 
            $set_sql[] = "$col = '" . esc_sql($val) . "'"; 
        }
        $set_string = implode(', ', $set_sql);
        
        return $wpdb->query( "UPDATE $this->table_name SET $set_string WHERE id IN ($ids_sanitized)" );
    }
}