<?php
/**
 * Model: Nativa Cash Register (Versão Definitiva com Mapa de Caixa)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Cash_Register {

    private $table_name;
    private $trans_table;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'nativa_cash_registers';
        $this->trans_table = $wpdb->prefix . 'nativa_transactions';
    }

    public function init() {
        $this->create_tables();
    }

    public function create_tables() {
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // ADICIONADO: cash_count_json para salvar o mapa de notas/moedas
        $sql_registers = "CREATE TABLE $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            opening_balance decimal(10,2) NOT NULL DEFAULT 0.00,
            closing_balance decimal(10,2) DEFAULT NULL,
            status varchar(20) DEFAULT 'open',
            opened_at datetime DEFAULT CURRENT_TIMESTAMP,
            closed_at datetime DEFAULT NULL,
            notes text DEFAULT NULL,
            cash_count_json longtext DEFAULT NULL, 
            PRIMARY KEY  (id)
        ) " . $wpdb->get_charset_collate() . ";";

        dbDelta( $sql_registers );

        // Tabela de Transações (Mantida)
        $sql_trans = "CREATE TABLE $this->trans_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            register_id bigint(20) NOT NULL,
            session_id bigint(20) DEFAULT NULL,
            type varchar(50) NOT NULL,
            method varchar(50) DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            description varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY register_id (register_id)
        ) " . $wpdb->get_charset_collate() . ";";

        dbDelta( $sql_trans );
    }

    public function get_current_open_register() {
        global $wpdb;
        return $wpdb->get_row( "SELECT * FROM $this->table_name WHERE status = 'open' ORDER BY id DESC LIMIT 1" );
    }

    // NOVO: Busca o último caixa fechado para recuperar as moedas
    public function get_last_closed_register() {
        global $wpdb;
        return $wpdb->get_row( "SELECT * FROM $this->table_name WHERE status = 'closed' ORDER BY id DESC LIMIT 1" );
    }

    public function open_register( $amount, $user_id ) {
        global $wpdb;
        if ( $this->get_current_open_register() ) return new WP_Error( 'already_open', 'Já existe um caixa aberto.' );

        $wpdb->insert( $this->table_name, array(
            'user_id' => $user_id,
            'opening_balance' => $amount,
            'status' => 'open',
            'opened_at' => current_time('mysql')
        ));
        
        $register_id = $wpdb->insert_id;
        $this->add_transaction( $register_id, 'supply', 'money', $amount, 'Saldo Inicial (Abertura)' );

        return $register_id;
    }

    // ATUALIZADO: Agora aceita $breakdown (JSON do mapa de caixa)
    public function close_register( $register_id, $closing_balance, $notes = '', $breakdown = null ) {
        global $wpdb;
        
        $data = array(
            'status' => 'closed',
            'closing_balance' => $closing_balance,
            'closed_at' => current_time('mysql'),
            'notes' => $notes
        );

        if ( $breakdown ) {
            $data['cash_count_json'] = json_encode( $breakdown ); // Salva no banco!
        }

        return $wpdb->update( $this->table_name, $data, array( 'id' => $register_id ) );
    }

    public function add_transaction( $register_id, $type, $method, $amount, $desc = '', $session_id = null ) {
        global $wpdb;
        return $wpdb->insert( $this->trans_table, array(
            'register_id' => $register_id,
            'session_id' => $session_id,
            'type' => $type,
            'method' => $method,
            'amount' => $amount,
            'description' => $desc,
            'created_at' => current_time('mysql')
        ));
    }

    public function get_balance_summary( $register_id ) {
        global $wpdb;
        $sql = "SELECT type, method, SUM(amount) as total FROM $this->trans_table WHERE register_id = %d GROUP BY type, method";
        $rows = $wpdb->get_results( $wpdb->prepare($sql, $register_id) );

        $summary = [ 'total_in' => 0, 'total_out' => 0, 'cash_balance' => 0, 'methods' => [] ];

        foreach($rows as $r) {
            $val = (float)$r->total;
            if ($r->type === 'sale' || $r->type === 'supply') {
                $summary['total_in'] += $val;
                if ($r->method === 'money') $summary['cash_balance'] += $val;
                if (!isset($summary['methods'][$r->method])) $summary['methods'][$r->method] = 0;
                $summary['methods'][$r->method] += $val;
            } elseif ($r->type === 'bleed') {
                $summary['total_out'] += $val;
                if ($r->method === 'money') $summary['cash_balance'] -= $val;
            }
        }
        return $summary;
    }
    
    public function get_transactions( $register_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $this->trans_table WHERE register_id = %d ORDER BY id DESC", $register_id ) );
    }
}