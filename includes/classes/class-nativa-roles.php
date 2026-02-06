<?php
/**
 * Nativa Roles & Capabilities
 * Define os papéis de usuário e a matriz de permissões do sistema.
 */

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
            // --- FRENTE DE LOJA / OPERACIONAL ---
            'tables' => [
                'cap' => 'nativa_access_tables',
                'label' => 'Mapa de Mesas',
                'desc' => 'Visualizar ocupação, abrir mesas e lançar pedidos.'
            ],
            'comanda_digital' => [
                'cap' => 'nativa_access_pos', 
                'label' => 'PDV Balcão / Lançamento',
                'desc' => 'Acesso à tela de lançamento rápido e identificação de cliente.'
            ],
            'delivery' => [
                'cap' => 'nativa_view_delivery',
                'label' => 'Gestão de Delivery',
                'desc' => 'Acesso ao Kanban de pedidos, despacho e motoboys.'
            ],
            'kds' => [
                'cap' => 'nativa_view_kds', // Agora visível na matriz
                'label' => 'Tela KDS (Cozinha)',
                'desc' => 'Visualizar fila de produção e marcar itens como prontos.'
            ],
            'print_monitor' => [
                'cap' => 'nativa_access_prints', // NOVO
                'label' => 'Monitor de Impressão',
                'desc' => 'Ver logs de impressão, status do Bridge e reimprimir tickets.'
            ],

            // --- ESTOQUE E SUPRIMENTOS ---
            'restock' => [
                'cap' => 'nativa_access_restock', // NOVO
                'label' => 'Reposição de Gôndola',
                'desc' => 'Acesso à lista de reposição rápida (Grab & Go).'
            ],
            'purchases' => [
                'cap' => 'nativa_access_purchases', // NOVO
                'label' => 'Gestão de Compras',
                'desc' => 'Gerenciar lista de insumos, fornecedores e dar baixa em compras.'
            ],
            'products' => [
                'cap' => 'nativa_manage_products',
                'label' => 'Cadastro de Produtos',
                'desc' => 'Criar e editar produtos, preços e disponibilidade.'
            ],

            // --- GESTÃO E FINANCEIRO ---
            'pdv_financeiro' => [
                'cap' => 'nativa_access_cash',
                'label' => 'Financeiro / Caixa',
                'desc' => 'Acesso a Fluxo de Caixa, Sangria, Suprimento e Fechamento.'
            ],
            'customers' => [
                'cap' => 'nativa_access_customers',
                'label' => 'CRM / Clientes',
                'desc' => 'Visualizar histórico de clientes e dados de contato.'
            ],
            'loyalty' => [
                'cap' => 'nativa_access_loyalty',
                'label' => 'Programa de Fidelidade',
                'desc' => 'Configurar regras de pontos e catálogo de prêmios.'
            ],
            'offers' => [
                'cap' => 'nativa_manage_offers', // NOVO
                'label' => 'Gestão de Ofertas',
                'desc' => 'Criar e editar regras de upsell e promoções de carrinho.'
            ],
            
            // --- ADMINISTRAÇÃO ---
            'team' => [
                'cap' => 'nativa_access_team',
                'label' => 'Admin de Equipe',
                'desc' => 'Criar usuários, senhas e definir permissões de acesso.'
            ],
            'fiscal' => [
                'cap' => 'nativa_access_fiscal', // NOVO
                'label' => 'Menu Fiscal (PAF)',
                'desc' => 'Acesso aos relatórios fiscais obrigatórios e geração de arquivos.'
            ],
            'settings' => [
                'cap' => 'nativa_access_settings',
                'label' => 'Configurações da Loja',
                'desc' => 'Horários de funcionamento, dados de contato e integrações.'
            ]
        ];
    }

    /**
     * Registra e atualiza os cargos no WordPress
     */
    public static function add_custom_roles() {
        $caps_def = self::get_capabilities_schema();
        
        // Mantém a lógica existente de criação de roles...
        // O importante é que agora a matriz no frontend vai ler o array acima.
        
        // --- 1. GARÇOM (Atendimento) ---
        $waiter_caps = [
            'read' => true, 
            'edit_posts' => true,
            $caps_def['tables']['cap'] => true,
            $caps_def['comanda_digital']['cap'] => true,
            $caps_def['delivery']['cap'] => true,
            $caps_def['print_monitor']['cap'] => true // Garçom pode ver erros de impressão
        ];

        // --- 2. COZINHA (KDS) ---
        $kitchen_caps = [
            'read' => true,
            'edit_posts' => true,
            $caps_def['kds']['cap'] => true,
            $caps_def['restock']['cap'] => true // Cozinha pode ver reposição
        ];

        // --- 3. ENTREGADOR ---
        $driver_caps = [
            'read' => true,
            'edit_posts' => true,
            $caps_def['delivery']['cap'] => true
        ];

        // --- 4. GERENTE (Gestão) ---
        $manager_caps = [
            'read' => true,
            'edit_posts' => true,
            'manage_options' => true, 
        ];
        // Gerente ganha tudo por padrão
        foreach ($caps_def as $feat) {
            $manager_caps[$feat['cap']] = true;
        }

        // --- 5. SUPER ADMIN ---
        $super_admin_caps = $manager_caps;
        $super_admin_caps['nativa_is_super_admin'] = true;

        // Registra/Atualiza
        add_role( 'nativa_waiter', 'Atendimento', $waiter_caps );
        add_role( 'nativa_kitchen', 'Cozinha', $kitchen_caps );
        add_role( 'nativa_driver', 'Entrega', $driver_caps );
        add_role( 'nativa_manager', 'Gestão', $manager_caps );
        add_role( 'nativa_super_admin', 'Super Administrador', $super_admin_caps );

        // Sincroniza Admin WP
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( $caps_def as $feat ) {
                if ( ! $admin->has_cap($feat['cap']) ) {
                    $admin->add_cap( $feat['cap'] );
                }
            }
        }
    }
}