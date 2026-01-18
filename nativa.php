<?php
/**
 * Plugin Name:       Nativa
 * Plugin URI:        https://pastelarianativa.com.br
 * Description:       Núcleo do Sistema Comercial e PDV (V2). Gerencia Pedidos, Mesas, Delivery e Integrações de Hardware.
 * Version:           2.0.0
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

define( 'NATIVA_VERSION', '2.0.0' );
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
		// Carrega os Models
		require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-session.php';
		require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-order-item.php';
		require_once NATIVA_PLUGIN_DIR . 'includes/admin/class-nativa-admin-page.php';
		require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-payment-method.php';
		require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-product-cpt.php';
		require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-acf-fields.php';
		require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-combo-cpt.php';
		require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-combo-fields.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-rua-cpt.php'; // NOVO
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-bairro-cpt.php'; // NOVO
        require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-legacy-cpt.php';

		// Carrega as APIs Oficiais
		require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-menu-api.php';
		require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-combo-api.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-orders-api.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-payment-api.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-tables-api.php';
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-delivery-api.php'; // NOVO
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-profile-api.php'; // NOVO
        require_once NATIVA_PLUGIN_DIR . 'includes/api/class-nativa-debug-api.php'; // <--- ADICIONE

        // Carrega Frontend Loader
        require_once NATIVA_PLUGIN_DIR . 'includes/frontend/class-nativa-frontend-loader.php';
    }

	private function define_hooks() {
        // 0. Inicializa Pedidos da V1 para histórico
        $legacy_cpt = new Nativa_Legacy_CPT();
        $legacy_cpt->init();

        // 1. Inicializa CPTs e Campos
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

        $bairro_cpt = new Nativa_Bairro_CPT(); // NOVO
        $bairro_cpt->init();

        $rua_cpt = new Nativa_Rua_CPT(); // NOVO
        $rua_cpt->init();

        // 2. Inicializa APIs (REST API)
        add_action( 'rest_api_init', function() {
            
            // API Menu (Cardápio)
            $menu_api = new Nativa_Menu_API();
            $menu_api->register_routes();

            // API Combos
            $combo_api = new Nativa_Combo_API();
            $combo_api->register_routes();

            // API de Pedidos (CORRIGIDO PARA O NOME NOVO)
            $orders_api = new Nativa_Orders_API();
            $orders_api->register_routes();

            // API de Pagamentos (CORRIGIDO PARA O NOME NOVO)
            $payment_api = new Nativa_Payment_API();
            $payment_api->register_routes();

            // API de Mesas
            $tables_api = new Nativa_Tables_API(); 
            $tables_api->register_routes();

            // API de Bairros
            $delivery_api = new Nativa_Delivery_API(); // NOVO
            $delivery_api->register_routes();

            // API de Clientes
            $profile_api = new Nativa_Profile_API(); // NOVO
            $profile_api->register_routes();

            $debug = new Nativa_Debug_API(); // <--- ADICIONE
            $debug->register_routes();       // <--- ADICIONE
        });

        // 3. Admin Page
        $admin_page = new Nativa_Admin_Page();
        $admin_page->init();

        // 4. Frontend App (/pdv)
        $frontend = new Nativa_Frontend_Loader();
        $frontend->init();
    }
    
    public static function activate() {
        // Ativação (criação de tabelas, etc)
    }
}

register_activation_hook( __FILE__, array( 'Nativa_Core', 'activate' ) );

function run_nativa() {
	$plugin = Nativa_Core::get_instance();
}
run_nativa();