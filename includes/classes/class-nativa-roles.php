<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Nativa_Roles {

    public static function init() {
        add_action( 'init', array( __CLASS__, 'add_custom_roles' ) );
    }

    /**
     * DICIONÁRIO DE FUNCIONALIDADES (SCHEMA)
     * Define o que aparece na tela de Gestão de Equipe > Papéis e Acessos
     */
    public static function get_capabilities_schema() {
        return [
            // OPERACIONAL
            'tables' => [
                'cap' => 'nativa_access_tables',
                'label' => 'Mapa de Mesas',
                'desc' => 'Visualizar ocupação e abrir mesas.'
            ],
            'comanda_digital' => [ // <--- AQUI ESTAVA A CONFUSÃO, AGORA CLARO
                'cap' => 'nativa_access_pos', 
                'label' => 'Comanda Digital (Mobile/Lançamento)',
                'desc' => 'Permite lançar pedidos e usar o modo Garçom (MobileMenu). Não dá acesso ao fechamento de caixa.'
            ],
            'delivery' => [
                'cap' => 'nativa_view_delivery',
                'label' => 'Gestão de Delivery',
                'desc' => 'Acesso ao Kanban de pedidos e despacho.'
            ],
            
            // GESTÃO E FINANCEIRO
            'pdv_financeiro' => [
                'cap' => 'nativa_access_cash',
                'label' => 'PDV Desktop / Caixa',
                'desc' => 'Acesso total ao POSInterface (Pagamentos, Sangria, Fechamento).'
            ],
            'products' => [
                'cap' => 'nativa_manage_products',
                'label' => 'Estoque e Cardápio',
                'desc' => 'Editar produtos, preços e disponibilidade.'
            ],
            'customers' => [
                'cap' => 'nativa_access_customers',
                'label' => 'CRM / Clientes',
                'desc' => 'Visualizar histórico de clientes e fidelidade.'
            ],
            'team' => [
                'cap' => 'nativa_access_team',
                'label' => 'Admin de Equipe',
                'desc' => 'Criar usuários, senhas e definir estes papéis.'
            ],
            'settings' => [
                'cap' => 'nativa_access_settings',
                'label' => 'Configurações da Loja',
                'desc' => 'Horários, impressoras e dados fiscais.'
            ]
        ];
    }

    public static function add_custom_roles() {
        $caps_def = self::get_capabilities_schema();
        
        // --- 1. GARÇOM (Atendimento) ---
        // Acesso: Mesas + Comanda Digital. (Sem acesso ao Caixa/PDV Financeiro)
        $waiter_caps = [
            'read' => true, 
            'edit_posts' => true,
            $caps_def['tables']['cap'] => true,
            $caps_def['comanda_digital']['cap'] => true, // nativa_access_pos
            $caps_def['delivery']['cap'] => true // Conforme solicitado (Garçom vê delivery)
        ];

        // --- 2. COZINHA (KDS) ---
        $kitchen_caps = [
            'read' => true,
            'edit_posts' => true,
            $caps_def['delivery']['cap'] => true // KDS usa a mesma permissão de visualização por enquanto
        ];

        // --- 3. ENTREGADOR ---
        $driver_caps = [
            'read' => true,
            'edit_posts' => true,
            $caps_def['delivery']['cap'] => true
        ];

        // --- 4. GERENTE (Gestão) ---
        // Ganha TUDO automaticamente
        $manager_caps = [
            'read' => true,
            'edit_posts' => true,
            'manage_options' => true, 
        ];
        foreach ($caps_def as $feat) {
            $manager_caps[$feat['cap']] = true;
        }

        // Registra/Atualiza os Papéis no WordPress
        add_role( 'nativa_waiter', 'Atendimento', $waiter_caps );
        add_role( 'nativa_kitchen', 'Cozinha', $kitchen_caps );
        add_role( 'nativa_driver', 'Entrega', $driver_caps );
        add_role( 'nativa_manager', 'Gestão', $manager_caps );

        // Atualiza o Admin também
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( $caps_def as $feat ) {
                if ( !$admin->has_cap($feat['cap']) ) {
                    $admin->add_cap( $feat['cap'] );
                }
            }
        }
    }
}