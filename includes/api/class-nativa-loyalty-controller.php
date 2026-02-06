<?php
/**
 * Controller: API de Fidelidade (Frontend)
 * CORREÇÃO: Busca dinâmica de produtos marcados como recompensa no inventário.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Loyalty_Controller {

    public function register_routes() {
        // ... (Rotas mantidas iguais) ...
        register_rest_route( 'nativa/v2', '/loyalty/summary', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_summary' ],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route( 'nativa/v2', '/loyalty/rewards', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_rewards_catalog' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'nativa/v2', '/loyalty/history', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_history' ],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }

    public function get_summary( $request ) {
        // ... (Mantido igual) ...
        $user_id = get_current_user_id();
        if ( ! class_exists('Nativa_Loyalty') ) require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-loyalty.php';
        $loyalty = new Nativa_Loyalty();
        $balance = $loyalty->get_balance( $user_id );
        
        return new WP_REST_Response([
            'success' => true,
            'points_balance' => $balance,
            'level' => $this->calculate_level($balance)
        ], 200);
    }

    /**
     * CORREÇÃO: Busca produtos marcados como recompensa (nativa_is_loyalty_reward)
     * e calcula o custo em pontos dinamicamente.
     */
    public function get_rewards_catalog( $request ) {
        $settings = get_option( 'nativa_loyalty_settings', [] );
        
        if ( empty($settings['enabled']) ) {
            return new WP_REST_Response(['success' => false, 'message' => 'Programa de fidelidade inativo.', 'rewards' => []], 200);
        }

        // Taxa de conversão: R$ 1,00 "compra" X pontos em produtos?
        // A lógica do admin diz: "Ex: R$ 1,00 significa que um item de R$ 20,00 custará 20 pontos."
        // Logo: Pontos Necessários = Preço do Produto / Taxa
        $redeem_rate = floatval($settings['points_redeem_rate'] ?? 1.0);
        if ($redeem_rate <= 0) $redeem_rate = 1.0;

        // Busca produtos com a flag ativa
        $args = [
            'post_type' => 'nativa_produto',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'nativa_is_loyalty_reward',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ];

        $products = get_posts($args);
        $catalog = [];

        foreach ( $products as $product ) {
            $product_id = $product->ID;
            
            // Busca Preço Atual
            $price_base = (float) get_post_meta( $product_id, 'produto_preco', true );
            $price_promo = (float) get_post_meta( $product_id, 'produto_preco_promocional', true );
            $current_price = ($price_promo > 0 && $price_promo < $price_base) ? $price_promo : $price_base;

            // Calcula Custo em Pontos
            $points_cost = ceil( $current_price / $redeem_rate );

            if ($points_cost <= 0) continue;

            $image_id = get_post_thumbnail_id($product_id);
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : null;

            $stock_qty = (int) get_post_meta($product_id, 'nativa_estoque_qtd', true);
            $manage_stock = get_post_meta($product_id, 'nativa_estoque_ativo', true);
            $is_available = !($manage_stock && $stock_qty <= 0);

            $catalog[] = [
                'id'           => $product_id,
                'name'         => $product->post_title,
                'points_cost'  => $points_cost,
                'image'        => $image_url,
                'description'  => get_the_excerpt($product_id),
                'is_available' => $is_available
            ];
        }

        // Ordena por custo (menor para maior)
        usort($catalog, function($a, $b) {
            return $a['points_cost'] - $b['points_cost'];
        });

        return new WP_REST_Response([
            'success' => true,
            'rewards' => $catalog,
            'user_balance' => is_user_logged_in() ? (new Nativa_Loyalty())->get_balance(get_current_user_id()) : 0
        ], 200);
    }

    // ... (restante mantido igual) ...
    public function get_history( $request ) {
        // ... (código existente) ...
        $user_id = get_current_user_id();
        $limit = $request->get_param('limit') ? intval($request->get_param('limit')) : 20;
        if ( ! class_exists('Nativa_Loyalty') ) require_once NATIVA_PLUGIN_DIR . 'includes/models/class-nativa-loyalty.php';
        $loyalty = new Nativa_Loyalty();
        $history = $loyalty->get_history( $user_id, $limit );
        $formatted = array_map(function($entry) {
            return [
                'id' => $entry->id,
                'date' => date('d/m/Y H:i', strtotime($entry->created_at)),
                'points' => (int)$entry->points,
                'type' => $entry->type,
                'description' => $entry->description,
                'status' => $entry->status
            ];
        }, $history);
        return new WP_REST_Response(['success' => true, 'history' => $formatted], 200);
    }

    private function calculate_level($points) {
        if ($points > 1000) return 'Ouro';
        if ($points > 500) return 'Prata';
        return 'Bronze';
    }
}