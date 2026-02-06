<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Session_Log {

    /**
     * Cria um registro de log/atividade
     */
    public function log( $session_id, $type, $description, $meta = [], $status = 'completed' ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nativa_session_logs';

        $user_id = get_current_user_id();
        $user_info = get_userdata($user_id);
        
        // Se for usuário logado, pega o nome, senão 'Sistema'
        $created_by = $user_info ? ($user_info->first_name ?: $user_info->display_name) : 'Sistema/Cliente';

        $data = [
            'session_id'  => $session_id,
            'type'        => $type,
            'description' => $description,
            'meta_json'   => json_encode($meta),
            'status'      => $status, // 'pending', 'approved', 'rejected', 'completed'
            'created_by'  => $created_by,
            'created_at'  => current_time('mysql')
        ];

        $format = ['%d', '%s', '%s', '%s', '%s', '%s', '%s'];

        $wpdb->insert( $table_name, $data, $format );
        $log_id = $wpdb->insert_id;

        // --- GATILHO DE NOTIFICAÇÃO (NOVO) ---
        // Se for uma solicitação pendente (precisa de aprovação do gerente)
        if ( $status === 'pending' ) {
            if ( class_exists('Nativa_Notification_Service') ) {
                Nativa_Notification_Service::notify_staff_activity( 
                    $log_id, 
                    $type, 
                    $description,
                    $session_id
                );
            }
        }
        // -------------------------------------

        return $log_id;
    }
}