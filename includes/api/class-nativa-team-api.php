<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Nativa_Team_API {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/team', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_team' ),
            'permission_callback' => function() { return current_user_can('edit_users'); }
        ));

        register_rest_route( 'nativa/v2', '/team', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'create_update_member' ),
            'permission_callback' => function() { return current_user_can('edit_users'); }
        ));
    }

    public function get_team() {
        // Busca apenas quem tem cargo de staff
        $users = get_users( array( 'role__in' => ['nativa_manager', 'nativa_waiter', 'nativa_kitchen', 'nativa_driver', 'administrator'] ) );
        $data = [];

        foreach ( $users as $u ) {
            $roles = $u->roles;
            $role = !empty($roles) ? $roles[0] : 'customer';
            
            $data[] = array(
                'id' => $u->ID,
                'name' => $u->display_name,
                'email' => $u->user_email,
                'role' => $role,
                'role_label' => $this->get_role_label($role),
                'avatar' => get_avatar_url($u->ID),
                'pin' => get_user_meta($u->ID, 'nativa_access_pin', true) 
            );
        }
        return new WP_REST_Response(['success' => true, 'team' => $data], 200);
    }

    public function create_update_member( $request ) {
        $params = $request->get_params();
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        $email = sanitize_email($params['email']);
        $role = $params['role'];
        $name = sanitize_text_field($params['name']);
        $password = isset($params['password']) ? $params['password'] : null;

        if ( empty($email) ) return new WP_REST_Response(['success'=>false, 'message'=>'E-mail obrigatório'], 400);

        // LÓGICA INTELIGENTE: Promover Usuário Existente
        $existing_user = get_user_by( 'email', $email );

        if ( $existing_user ) {
            // Se o usuário já existe (login social), usamos o ID dele
            $user_id = $existing_user->ID;
            
            // Atualiza nome se fornecido
            if ( !empty($name) ) {
                wp_update_user([ 'ID' => $user_id, 'display_name' => $name ]);
            }
        } else {
            // Se não existe, cria um novo
            if ( empty($password) ) $password = wp_generate_password(); // Gera senha se não vier (login vai ser por link/social depois)
            
            $user_id = wp_create_user( $email, $password, $email );
            
            if ( is_wp_error( $user_id ) ) {
                return new WP_REST_Response(['success'=>false, 'message'=>$user_id->get_error_message()], 400);
            }
            
            if ( !empty($name) ) {
                wp_update_user([ 'ID' => $user_id, 'display_name' => $name ]);
            }
        }

        // Define o Cargo (Promove ou Rebaixa)
        $u = new WP_User( $user_id );
        $u->set_role( $role );
        
        // Salva PIN opcional (para login rápido no PDV físico)
        if ( isset($params['pin']) ) {
            update_user_meta($user_id, 'nativa_access_pin', sanitize_text_field($params['pin']));
        }

        return new WP_REST_Response(['success'=>true, 'message'=>'Colaborador atualizado com sucesso.'], 200);
    }

    private function get_role_label($slug) {
        $map = [
            'administrator' => 'Admin',
            'nativa_manager' => 'Gestão',
            'nativa_waiter' => 'Atendimento',
            'nativa_kitchen' => 'Cozinha',
            'nativa_driver' => 'Entrega'
        ];
        return $map[$slug] ?? ucfirst($slug);
    }
}