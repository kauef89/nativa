<?php
/**
 * API de Ofertas (Upsell)
 * Verifica regras de carrinho e retorna ofertas elegíveis.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Offers_API {

    public function register_routes() {
        // Endpoint chamado pelo CartStore.js para verificar upsell
        register_rest_route( 'nativa/v2', '/check-offer', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'check_upsell' ),
            'permission_callback' => '__return_true',
        ));
    }

    public function check_upsell( $request ) {
        $params = $request->get_json_params();
        $cart_items = isset($params['cart']) ? $params['cart'] : [];
        
        if ( empty($cart_items) ) {
            return new WP_REST_Response(['has_offer' => false], 200);
        }

        // Calcula totais do carrinho para validação
        $cart_subtotal = 0;
        $cart_product_ids = [];
        $cart_categories = [];

        foreach ($cart_items as $item) {
            $cart_subtotal += (float) ($item['price'] * $item['qty']);
            $cart_product_ids[] = (int) $item['id'];
            
            // Busca categorias do produto
            $cats = wp_get_post_terms( $item['id'], 'category', ['fields' => 'ids'] );
            if ( ! is_wp_error($cats) ) {
                $cart_categories = array_merge($cart_categories, $cats);
            }
        }
        $cart_categories = array_unique($cart_categories);

        // Busca todas as ofertas ativas
        $args = [
            'post_type' => 'nativa_oferta',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [ 'key' => 'oferta_status', 'value' => '1', 'compare' => '=' ] // Apenas ativas
            ]
        ];
        
        $offers = get_posts($args);

        foreach ( $offers as $offer ) {
            // Se já foi usada muitas vezes (limite global), pula (opcional, requer contagem)
            
            // Lógica de Validação (Grupos e Regras)
            // Lemos os campos ACF
            $grupos = get_field('grupos_de_regras', $offer->ID);
            
            // Se não tiver regras, oferta é válida para todos (Cuidado!)
            $is_valid = empty($grupos); 

            if ( ! empty($grupos) && is_array($grupos) ) {
                // Lógica OU entre grupos: Basta um grupo ser verdadeiro
                foreach ( $grupos as $grupo ) {
                    $regras = $grupo['regras'];
                    if ( empty($regras) ) {
                        $is_valid = true; // Grupo vazio = sempre verdade
                        break;
                    }

                    // Lógica E dentro do grupo: Todas as regras devem bater
                    $group_valid = true;
                    foreach ( $regras as $regra ) {
                        if ( ! $this->validate_rule($regra, $cart_subtotal, $cart_product_ids, $cart_categories) ) {
                            $group_valid = false;
                            break;
                        }
                    }

                    if ( $group_valid ) {
                        $is_valid = true;
                        break; // Achou um grupo válido, a oferta é válida
                    }
                }
            }

            if ( $is_valid ) {
                // Monta o objeto de resposta
                $product_id = get_field('produto_ofertado', $offer->ID);
                $promo_price = (float) get_field('preco_promocional', $offer->ID);
                $call_to_action = get_field('texto_da_oferta', $offer->ID);
                
                // Valida se o produto existe e tem imagem
                $product = get_post($product_id);
                if ( ! $product ) continue;

                $image_url = get_the_post_thumbnail_url($product_id, 'medium');

                return new WP_REST_Response([
                    'has_offer' => true,
                    'offer' => [
                        'offer_id' => $offer->ID,
                        'product_id' => $product_id,
                        'name' => $product->post_title,
                        'image' => $image_url,
                        'original_price' => (float) get_post_meta($product_id, 'produto_preco', true),
                        'promo_price' => $promo_price,
                        'call_to_action' => $call_to_action
                    ]
                ], 200);
            }
        }

        return new WP_REST_Response(['has_offer' => false], 200);
    }

    /**
     * Valida uma única regra
     */
    private function validate_rule( $regra, $subtotal, $cart_ids, $cart_cats ) {
        $tipo = $regra['tipo_regra'];
        $operador = $regra['operador'];
        $valor = $regra['valor']; // Numérico para subtotal
        
        switch ( $tipo ) {
            case 'subtotal_carrinho':
                $val = (float) $valor;
                if ($operador === 'maior_igual') return $subtotal >= $val;
                if ($operador === 'menor_igual') return $subtotal <= $val;
                if ($operador === 'maior') return $subtotal > $val;
                if ($operador === 'menor') return $subtotal < $val;
                break;

            case 'categoria_no_carrinho':
                $cat_id = $regra['valor_categoria'];
                $has_cat = in_array($cat_id, $cart_cats);
                if ($operador === 'contem') return $has_cat;
                if ($operador === 'nao_contem') return !$has_cat;
                break;

            case 'produto_no_carrinho':
                $prod_id = $regra['valor_produto'];
                $has_prod = in_array($prod_id, $cart_ids);
                if ($operador === 'contem') return $has_prod;
                if ($operador === 'nao_contem') return !$has_prod;
                break;
        }
        
        return false;
    }
}