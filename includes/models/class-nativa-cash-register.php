<?php
/**
 * Model: Nativa Cash Register
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

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
        // ... (Mantido igual) ...
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

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

    // ... (Métodos open_register, close_register, get_current_open_register, etc mantidos) ...
    public function get_current_open_register() {
        global $wpdb;
        return $wpdb->get_row( "SELECT * FROM $this->table_name WHERE status = 'open' ORDER BY id DESC LIMIT 1" );
    }
    public function get_last_closed_register() {
        global $wpdb;
        return $wpdb->get_row( "SELECT * FROM $this->table_name WHERE status = 'closed' ORDER BY id DESC LIMIT 1" );
    }
    public function open_register( $amount, $user_id ) {
        global $wpdb;
        if ( $this->get_current_open_register() ) return new WP_Error( 'already_open', 'Já existe um caixa aberto.' );
        $wpdb->insert( $this->table_name, array( 'user_id' => $user_id, 'opening_balance' => $amount, 'status' => 'open', 'opened_at' => current_time('mysql') ));
        $register_id = $wpdb->insert_id;
        $this->add_transaction( $register_id, 'supply', 'money', $amount, 'Saldo Inicial (Abertura)' );
        return $register_id;
    }
    public function close_register( $register_id, $closing_balance, $notes = '', $breakdown = null ) {
        global $wpdb;
        $data = array( 'status' => 'closed', 'closing_balance' => $closing_balance, 'closed_at' => current_time('mysql'), 'notes' => $notes );
        if ( $breakdown ) { $data['cash_count_json'] = json_encode( $breakdown ); }
        return $wpdb->update( $this->table_name, $data, array( 'id' => $register_id ) );
    }
    public function add_transaction( $register_id, $type, $method, $amount, $desc = '', $session_id = null ) {
        global $wpdb;
        return $wpdb->insert( $this->trans_table, array( 'register_id' => $register_id, 'session_id' => $session_id, 'type' => $type, 'method' => $method, 'amount' => $amount, 'description' => $desc, 'created_at' => current_time('mysql') ));
    }
    public function get_balance_summary( $register_id ) {
        global $wpdb;
        $sql = "SELECT type, method, SUM(amount) as total FROM $this->trans_table WHERE register_id = %d GROUP BY type, method";
        $rows = $wpdb->get_results( $wpdb->prepare($sql, $register_id) );
        $summary = [ 'total_in' => 0, 'total_out' => 0, 'total_loss' => 0, 'cash_balance' => 0, 'methods' => [] ];
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
            } elseif ($r->type === 'loss') {
                $summary['total_loss'] += $val;
            }
        }
        return $summary;
    }
    
// --- CORREÇÃO AQUI: JOIN PARA PEGAR STATUS FISCAL E DADOS DE DESCRIÇÃO ---
    public function get_transactions( $register_id ) {
        global $wpdb;
        $session_table = $wpdb->prefix . 'nativa_sessions';
        
        // Adicionamos s.type, s.table_number e s.client_name ao SELECT
        $sql = "SELECT t.*, 
                       s.fiscal_status, 
                       s.fiscal_url,
                       s.type as session_type,
                       s.table_number,
                       s.client_name
                FROM $this->trans_table t 
                LEFT JOIN $session_table s ON t.session_id = s.id 
                WHERE t.register_id = %d 
                ORDER BY t.id DESC";
                
        return $wpdb->get_results( $wpdb->prepare( $sql, $register_id ) );
    }
}