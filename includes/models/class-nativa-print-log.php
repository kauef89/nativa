<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Print_Log {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'nativa_print_logs';
    }

    public function init() {
        $this->create_table();
    }

    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) DEFAULT NULL,
            user_name varchar(100) NOT NULL,
            type varchar(50) NOT NULL,
            service_type varchar(50) DEFAULT NULL,
            identifier varchar(100) DEFAULT NULL,
            station varchar(50) DEFAULT 'frente',
            status varchar(50) DEFAULT 'enviando',
            payload longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql ); // Esta função do WP cria a tabela se ela não existir ou atualiza se mudar algo
    }

    public function add_log($data) {
        global $wpdb;
        $wpdb->insert($this->table_name, $data);
        return $wpdb->insert_id;
    }

    public function update_status($log_id, $status) {
        global $wpdb;
        return $wpdb->update($this->table_name, ['status' => $status], ['id' => $log_id]);
    }
}