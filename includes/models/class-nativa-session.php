<?php
/**
 * Model: Nativa Session
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Session {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'nativa_sessions';
    }

    public function init() {
        $this->create_table();
    }

    public function create_table() {
        global $wpdb;
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") === $this->table_name;
        $charset_collate = $wpdb->get_charset_collate();

        if ( ! $table_exists ) {
            $sql = "CREATE TABLE $this->table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                type varchar(50) NOT NULL,
                table_number int(11) DEFAULT NULL,
                client_name varchar(255) DEFAULT NULL,
                client_id bigint(20) DEFAULT NULL,
                status varchar(50) DEFAULT 'open',
                delivery_address_json text DEFAULT NULL,
                payment_info_json text DEFAULT NULL,
                payment_status varchar(50) DEFAULT 'pending',
                delivery_fee decimal(10,2) DEFAULT 0.00,
                accounts_json text DEFAULT NULL,
                pix_txid varchar(100) DEFAULT NULL,
                pix_string text DEFAULT NULL,
                fiscal_status varchar(20) DEFAULT NULL,
                fiscal_url text DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                closed_at datetime DEFAULT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        } else {
            $this->check_column('fiscal_status', 'varchar(20) DEFAULT NULL');
            $this->check_column('fiscal_url', 'text DEFAULT NULL');
            $this->check_column('client_name', 'varchar(255) DEFAULT NULL');
            $this->check_column('payment_info_json', 'text DEFAULT NULL');
        }
    }

    private function check_column($col, $def) {
        global $wpdb;
        $check = $wpdb->get_results( "SHOW COLUMNS FROM $this->table_name LIKE '$col'" );
        if ( empty( $check ) ) $wpdb->query( "ALTER TABLE $this->table_name ADD COLUMN $col $def" );
    }

    public function open( $type, $table_number = null, $data = [] ) {
        global $wpdb;
        $client_name = is_array($data) ? ($data['name'] ?? null) : $data;
        
        $insert_data = array(
            'type' => $type,
            'table_number' => $table_number,
            'status' => is_array($data) ? ($data['status'] ?? 'open') : 'open',
            'client_name' => $client_name,
            'client_id' => is_array($data) ? ($data['client_id'] ?? null) : null,
            'delivery_address_json' => (is_array($data) && isset($data['address'])) ? json_encode($data['address']) : null,
            'payment_info_json' => (is_array($data) && isset($data['payments'])) ? json_encode($data['payments']) : null,
            'delivery_fee' => (is_array($data) && isset($data['delivery_fee'])) ? $data['delivery_fee'] : 0.00,
            'payment_status' => (is_array($data) && isset($data['payment_status'])) ? $data['payment_status'] : 'pending',
            'accounts_json' => json_encode(['Principal']) 
        );

        $wpdb->insert( $this->table_name, $insert_data );
        return $wpdb->insert_id;
    }

    public function close( $session_id ) {
        global $wpdb;
        return $wpdb->update( 
            $this->table_name, 
            array( 'status' => 'closed', 'closed_at' => current_time('mysql') ), 
            array( 'id' => $session_id ) 
        );
    }

    public function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE id = %d", $id ) );
    }

    public function add_account( $session_id, $account_name ) {
        global $wpdb;
        $session = $this->get( $session_id );
        if ( ! $session ) return false;

        $json = !empty($session->accounts_json) ? $session->accounts_json : '[]';
        $accounts = json_decode( $json, true );
        
        if ( ! is_array( $accounts ) ) $accounts = ['Principal'];

        if ( ! in_array( $account_name, $accounts ) ) {
            $accounts[] = $account_name;
            $wpdb->update( 
                $this->table_name, 
                array( 'accounts_json' => json_encode( $accounts ) ), 
                array( 'id' => $session_id ) 
            );
        }
        return $accounts;
    }

    public function is_command_in_use( $command_number, $ignore_session_id = null ) {
        global $wpdb;
        $sessions = $wpdb->get_results( "SELECT id, accounts_json FROM $this->table_name WHERE status = 'open'" );
        
        foreach ( $sessions as $sess ) {
            if ( $ignore_session_id && $sess->id == $ignore_session_id ) continue;
            
            $json = !empty($sess->accounts_json) ? $sess->accounts_json : '[]';
            $accounts = json_decode( $json, true );
            
            if ( is_array( $accounts ) && in_array( (string)$command_number, $accounts ) ) {
                return $sess->id;
            }
        }
        return false;
    }
}