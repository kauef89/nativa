<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Nativa_Roles {

    public static function init() {
        add_action( 'init', array( __CLASS__, 'add_custom_roles' ) );
    }

    public static function add_custom_roles() {
        // Removemos roles antigos se existirem para limpar
        // remove_role('nativa_manager'); 

        // 1. Gestão (Acesso Total ao PDV e Configs)
        add_role( 'nativa_manager', 'Gestão', array(
            'read' => true,
            'edit_posts' => true,
            'manage_options' => true, 
            'nativa_access_cash' => true,
            'nativa_access_reports' => true,
            'nativa_access_team' => true
        ));

        // 2. Atendimento (Garçom - Mesas e Pedidos)
        add_role( 'nativa_waiter', 'Atendimento', array(
            'read' => true,
            'edit_posts' => true, // Precisa criar pedidos
            'nativa_access_tables' => true,
            'nativa_access_pos' => true,
            'nativa_access_cash' => false, 
        ));

        // 3. Cozinha (KDS - Apenas Visualização de Produção)
        add_role( 'nativa_kitchen', 'Cozinha', array(
            'read' => true,
            'nativa_view_kds' => true,
            'edit_posts' => true // Para mudar status do pedido para "Pronto"
        ));
        
        // 4. Entrega (App do Entregador)
        add_role( 'nativa_driver', 'Entrega', array(
            'read' => true,
            'nativa_view_delivery' => true,
            'edit_posts' => true // Para marcar como "Entregue"
        ));
    }
}