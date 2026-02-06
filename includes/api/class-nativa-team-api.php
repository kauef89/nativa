<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Nativa_Team_API {

    public function register_routes() {
        // 1. Gestão de Membros (Listar e Salvar)
        register_rest_route( 'nativa/v2', '/team', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_team' ),
            'permission_callback' => function() { return current_user_can('nativa_access_team') || current_user_can('administrator'); }
        ));

        register_rest_route( 'nativa/v2', '/team', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'create_update_member' ),
            'permission_callback' => function() { return current_user_can('nativa_access_team') || current_user_can('administrator'); }
        ));

        // 2. Matriz de Permissões (Roles x Capabilities)
        register_rest_route( 'nativa/v2', '/roles-matrix', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_roles_matrix' ),
            'permission_callback' => function() { return current_user_can('nativa_access_team') || current_user_can('administrator'); }
        ));

        register_rest_route( 'nativa/v2', '/roles-matrix', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'update_roles_matrix' ),
            'permission_callback' => function() { return current_user_can('nativa_access_team') || current_user_can('administrator'); }
        ));
    }

    public function get_team() {
        $staff_roles = ['nativa_manager', 'nativa_waiter', 'nativa_kitchen', 'nativa_driver', 'administrator'];
        $users = get_users( array( 'role__in' => $staff_roles ) );
        $data = [];

        foreach ( $users as $u ) {
            $user_roles = $u->roles; 
            $primary_role = !empty($user_roles) ? $user_roles[0] : 'customer';
            
            // Recupera a cozinha principal salva
            $main_kitchen = get_user_meta($u->ID, 'nativa_main_kitchen', true);

            $data[] = array(
                'id' => $u->ID,
                'name' => $u->display_name,
                'email' => $u->user_email,
                'roles' => $user_roles, 
                'role_label' => $this->get_role_label($primary_role) . (count($user_roles) > 1 ? ' (+)' : ''),
                'avatar' => get_avatar_url($u->ID),
                'pin' => get_user_meta($u->ID, 'nativa_access_pin', true),
                'main_kitchen' => $main_kitchen ? (int)$main_kitchen : null // <--- NOVO
            );
        }
        return new WP_REST_Response(['success' => true, 'team' => $data], 200);
    }

    public function create_update_member( $request ) {
        $params = $request->get_params();
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        $email = sanitize_email($params['email']);
        $name = sanitize_text_field($params['name']);
        
        $roles = isset($params['roles']) ? $params['roles'] : [];
        if ( is_string($roles) ) $roles = [ $roles ]; 

        if ( empty($email) ) return new WP_REST_Response(['success'=>false, 'message'=>'E-mail obrigatório'], 400);
        if ( empty($roles) ) return new WP_REST_Response(['success'=>false, 'message'=>'Selecione ao menos uma função'], 400);

        $existing_user = get_user_by( 'email', $email );
        $user_id = $existing_user ? $existing_user->ID : 0;

        if ( $user_id ) {
            if ( !empty($name) ) wp_update_user([ 'ID' => $user_id, 'display_name' => $name ]);
        } else {
            $password = wp_generate_password(); 
            $user_id = wp_create_user( $email, $password, $email );
            if ( is_wp_error( $user_id ) ) return new WP_REST_Response(['success'=>false, 'message'=>$user_id->get_error_message()], 400);
            if ( !empty($name) ) wp_update_user([ 'ID' => $user_id, 'display_name' => $name ]);
        }

        $u = new WP_User( $user_id );
        $u->set_role( $roles[0] );
        for ( $i = 1; $i < count($roles); $i++ ) {
            $u->add_role( $roles[$i] );
        }
        
        if ( isset($params['pin']) ) {
            update_user_meta($user_id, 'nativa_access_pin', sanitize_text_field($params['pin']));
        }

        // --- SALVA COZINHA PRINCIPAL ---
        if ( isset($params['main_kitchen']) ) {
            update_user_meta($user_id, 'nativa_main_kitchen', (int)$params['main_kitchen']);
        }
        // -------------------------------

        return new WP_REST_Response(['success'=>true, 'message'=>'Membro atualizado.'], 200);
    }

    /**
     * Retorna a Matriz de Permissões (Roles x Capabilities)
     */
    public function get_roles_matrix() {
        global $wp_roles;
        
        // 1. Pega as definições do nosso dicionário
        if ( ! class_exists('Nativa_Roles') ) return new WP_REST_Response(['success'=>false, 'message'=>'Classe de Roles não carregada'], 500);
        
        $schema = Nativa_Roles::get_capabilities_schema();
        
        // 2. Pega os papéis registrados no WP
        $roles_data = [];
        $editable_roles = ['nativa_manager', 'nativa_waiter', 'nativa_driver', 'nativa_kitchen']; 
        
        foreach ($editable_roles as $slug) {
            $role_obj = get_role($slug);
            if (!$role_obj) continue;
            
            $caps = [];
            foreach ($schema as $key => $def) {
                // Verifica se o papel tem a capacidade
                $caps[$def['cap']] = $role_obj->has_cap($def['cap']);
            }
            
            $roles_data[] = [
                'slug' => $slug,
                'name' => isset($wp_roles->roles[$slug]['name']) ? translate_user_role($wp_roles->roles[$slug]['name']) : ucfirst($slug),
                'caps' => $caps
            ];
        }

        return new WP_REST_Response([
            'success' => true,
            'schema' => array_values($schema), // Lista para o cabeçalho da tabela
            'matrix' => $roles_data
        ], 200);
    }

    /**
     * Salva as alterações na Matriz de Permissões
     */
    public function update_roles_matrix( $request ) {
        $matrix = $request->get_param('matrix'); // Array de roles com suas caps alteradas
        
        if ( empty($matrix) || !is_array($matrix) ) {
            return new WP_REST_Response(['success'=>false, 'message'=>'Dados inválidos'], 400);
        }

        foreach ($matrix as $role_data) {
            $role_obj = get_role($role_data['slug']);
            if (!$role_obj) continue;

            // Itera sobre as caps enviadas (true/false) e aplica
            foreach ($role_data['caps'] as $cap => $has_access) {
                if ( $has_access ) {
                    $role_obj->add_cap($cap);
                } else {
                    $role_obj->remove_cap($cap);
                }
            }
        }

        return new WP_REST_Response(['success'=>true, 'message'=>'Permissões atualizadas com sucesso!'], 200);
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