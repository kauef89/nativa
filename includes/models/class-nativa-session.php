<?php
/**
 * Model: Nativa Session
 * Gerencia as sessões (Mesas, Comandas, Delivery) e suas sub-contas.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
        
        // Verifica se a tabela já existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") === $this->table_name;

        if ( ! $table_exists ) {
            // Se NÃO existe, cria do zero usando dbDelta
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $this->table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                type varchar(50) NOT NULL,
                table_number int(11) DEFAULT NULL,
                client_name varchar(255) DEFAULT NULL,
                status varchar(50) DEFAULT 'open',
                delivery_address_json text DEFAULT NULL,
                accounts_json text DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        } else {
            // Se JÁ existe, fazemos apenas ALTER TABLE manuais para evitar erro de Foreign Key do dbDelta
            
            // 1. Adiciona accounts_json
            $col_accounts = $wpdb->get_results( "SHOW COLUMNS FROM $this->table_name LIKE 'accounts_json'" );
            if ( empty( $col_accounts ) ) {
                $wpdb->query( "ALTER TABLE $this->table_name ADD COLUMN accounts_json text DEFAULT NULL" );
            }
            
            // 2. Adiciona client_name
            $col_client = $wpdb->get_results( "SHOW COLUMNS FROM $this->table_name LIKE 'client_name'" );
            if ( empty( $col_client ) ) {
                $wpdb->query( "ALTER TABLE $this->table_name ADD COLUMN client_name varchar(255) DEFAULT NULL" );
            }
        }
    }

    public function open( $type, $table_number = null, $delivery_data = null ) {
        global $wpdb;
        
        $data = array(
            'type' => $type,
            'table_number' => $table_number,
            'client_name' => is_array($delivery_data) ? ($delivery_data['name'] ?? null) : $delivery_data,
            'status' => 'open',
            'delivery_address_json' => is_array($delivery_data) ? json_encode($delivery_data) : null,
            'accounts_json' => json_encode(['Principal']) 
        );

        $wpdb->insert( $this->table_name, $data );
        return $wpdb->insert_id;
    }

    public function close( $session_id ) {
        global $wpdb;
        return $wpdb->update( 
            $this->table_name, 
            array( 'status' => 'closed' ), 
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

        // CORREÇÃO: Tratamento para valor nulo
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