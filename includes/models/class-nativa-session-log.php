<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Session_Log {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'nativa_session_logs';
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
            type varchar(50) NOT NULL, /* order, cancel, swap, payment */
            description text NOT NULL,
            author_id bigint(20) NOT NULL,
            author_name varchar(100) DEFAULT NULL,
            meta_json longtext DEFAULT NULL, /* Para guardar detalhes da troca {old, new} */
            status varchar(50) DEFAULT 'completed', /* pending (para aprovação), completed, rejected */
            approver_name varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY session_id (session_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function log( $session_id, $type, $description, $meta = [], $status = 'completed' ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $user = get_userdata( $user_id );
        $author_name = $user ? ($user->first_name ?: $user->display_name) : 'Sistema';

        return $wpdb->insert( $this->table_name, array(
            'session_id'  => $session_id,
            'type'        => $type,
            'description' => $description,
            'author_id'   => $user_id,
            'author_name' => $author_name,
            'meta_json'   => !empty($meta) ? json_encode($meta) : null,
            'status'      => $status,
            'created_at'  => current_time('mysql')
        ));
    }

    public function get_logs( $session_id ) {
        global $wpdb;
        $results = $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM $this->table_name WHERE session_id = %d ORDER BY created_at DESC", 
            $session_id 
        ));
        
        // Decodifica o JSON para o Frontend
        foreach ($results as $row) {
            $row->meta = $row->meta_json ? json_decode($row->meta_json, true) : [];
        }
        return $results;
    }
}