<?php
/**
 * Plugin Name:       Nativa
 * Plugin URI:        https://pastelarianativa.com.br
 * Description:       Núcleo do Sistema Comercial e PDV (V2). Gerencia Pedidos, Mesas, Delivery e Integrações de Hardware.
 * Version:           2.0.2
 * Author:            Kauê Friedrich
 * Author URI:        https://pastelarianativa.com.br
 * Text Domain:       nativa
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NATIVA_VERSION', '2.0.2' );
define( 'NATIVA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NATIVA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NATIVA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

class Nativa_Core {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->define_hooks();
    }

    private function load_dependencies() {
        // --- Models (Acesso a Dados) ---
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-session.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-order-item.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-cash-register.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-stock.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-payment-method.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-product-cpt.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-acf-fields.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-combo-cpt.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-combo-fields.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-rua-cpt.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-bairro-cpt.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-legacy-cpt.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-mesa-cpt.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-mesa-fields.php';
        
        // NOVO: Model de Auditoria e Logs (Timeline)
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-session-log.php';
        
        // --- Classes Utilitárias e Configuração ---
        require_once NATIVA_PLUGIN_DIR . 'includes/classes/class-nativa-onesignal.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/classes/class-nativa-roles.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/admin/class-nativa-admin-page.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/frontend/class-nativa-frontend-loader.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/classes/class-nativa-gov-api.php';

        // --- APIs REST (Endpoints) ---
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-menu-api.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-combo-api.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-pos-controller.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-web-order-controller.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-dashboard-controller.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-payment-api.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-tables-api.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-delivery-api.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-profile-api.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-debug-api.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-cash-api.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-products-api.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-team-api.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-settings-api.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-auth-controller.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-onboarding-api.php';
    }

    private function define_hooks() {
        // 1. Inicializa CPTs, Campos e Roles
        $legacy_cpt = new Nativa_Legacy_CPT();
        $legacy_cpt->init();

        $product_cpt = new Nativa_Product_CPT();
        $product_cpt->init();

        $combo_cpt = new Nativa_Combo_CPT();
        $combo_cpt->init();

        $payment_methods = new Nativa_Payment_Method();
        $payment_methods->init();

        $acf_fields = new Nativa_ACF_Fields();
        $acf_fields->init();
        
        $combo_fields = new Nativa_Combo_Fields();
        $combo_fields->init();

        $bairro_cpt = new Nativa_Bairro_CPT();
        $bairro_cpt->init();

        $rua_cpt = new Nativa_Rua_CPT();
        $rua_cpt->init();

        $mesa_cpt = new Nativa_Mesa_CPT();
        $mesa_cpt->init();

        $mesa_fields = new Nativa_Mesa_Fields();
        $mesa_fields->init();

        // Inicializa a tabela de logs
        if ( class_exists('Nativa_Session_Log') ) {
            $log_model = new Nativa_Session_Log();
            // A criação da tabela idealmente ocorre no activate, mas mantemos o init da classe se houver hooks
        }

        Nativa_Roles::init();

        // 2. Inicializa APIs (REST API)
        add_action( 'rest_api_init', function() {
            
            $apis = [
                new Nativa_Menu_API(),
                new Nativa_Combo_API(),
                new Nativa_POS_Controller(),
                new Nativa_Web_Order_Controller(),
                new Nativa_Dashboard_Controller(),
                new Nativa_Payment_API(),
                new Nativa_Tables_API(),
                new Nativa_Delivery_API(),
                new Nativa_Profile_API(),
                new Nativa_Debug_API(),
                new Nativa_Cash_API(),
                new Nativa_Products_API(),
                new Nativa_Team_API(),
                new Nativa_Settings_API(),
                new Nativa_Auth_Controller(),
                new Nativa_Onboarding_API()
            ];

            foreach ($apis as $api) {
                $api->register_routes();
            }
        });

        // 3. Injeta Configuração do OneSignal no Frontend
        add_action( 'wp_enqueue_scripts', function() {
            $app_id = defined('NATIVA_ONESIGNAL_APP_ID') ? NATIVA_ONESIGNAL_APP_ID : '';
            
            wp_localize_script( 'nativa-pdv-app', 'nativaOneSignal', array(
                'app_id' => $app_id
            ));
        }, 20 );

        // 4. Páginas e Frontend
        $admin_page = new Nativa_Admin_Page();
        $admin_page->init();

        $frontend = new Nativa_Frontend_Loader();
        $frontend->init();
    }
    
    /**
     * Roda apenas ao ATIVAR o plugin no painel.
     */
    public static function activate() {
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-session.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-order-item.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-cash-register.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-stock.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/classes/class-nativa-roles.php';
        
        // Carrega o novo model para criar a tabela no activate
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-session-log.php'; 
        
        $session = new Nativa_Session();
        $session->create_table();
        
        $items = new Nativa_Order_Item();
        $items->create_table();

        $cash = new Nativa_Cash_Register();
        $cash->create_tables();

        $stock = new Nativa_Stock();
        $stock->create_table();
        
        $logs = new Nativa_Session_Log(); // Cria a tabela de logs
        $logs->create_table();
        
        Nativa_Roles::add_custom_roles();
        flush_rewrite_rules();
    }
}

register_activation_hook( __FILE__, array( 'Nativa_Core', 'activate' ) );

function run_nativa() {
    $plugin = Nativa_Core::get_instance();
}
run_nativa();