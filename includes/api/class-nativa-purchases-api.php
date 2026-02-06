<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Purchases_API {

    public function register_routes() {
        // GET: Listar dados gerais (Locais + Itens Pendentes + Catálogo)
        register_rest_route( 'nativa/v2', '/purchases/list', array( 
            'methods' => 'GET', 
            'callback' => array( $this, 'get_shopping_list' ), 
            'permission_callback' => function() { return current_user_can('edit_posts'); } 
        ));

        // POST: Adicionar item à lista
        register_rest_route( 'nativa/v2', '/purchases/item', array( 
            'methods' => 'POST', 
            'callback' => array( $this, 'add_item' ), 
            'permission_callback' => function() { return current_user_can('edit_posts'); } 
        ));

        // DELETE: Remover item da lista
        register_rest_route( 'nativa/v2', '/purchases/item', array( 
            'methods' => 'DELETE', 
            'callback' => array( $this, 'remove_item' ), 
            'permission_callback' => function() { return current_user_can('edit_posts'); } 
        ));

        // POST: Marcar como comprado
        register_rest_route( 'nativa/v2', '/purchases/buy', array( 
            'methods' => 'POST', 
            'callback' => array( $this, 'mark_purchased' ), 
            'permission_callback' => function() { return current_user_can('edit_posts'); } 
        ));

        // GET: Busca para autocomplete
        register_rest_route( 'nativa/v2', '/purchases/insumos', array( 
            'methods' => 'GET', 
            'callback' => array( $this, 'search_insumos' ), 
            'permission_callback' => function() { return current_user_can('edit_posts'); } 
        ));
        
        // POST: Criar novo local de compra
        register_rest_route( 'nativa/v2', '/purchases/location', array( 
            'methods' => 'POST', 
            'callback' => array( $this, 'create_location' ), 
            'permission_callback' => function() { return current_user_can('edit_posts'); } 
        ));
    }

    public function get_shopping_list() {
        // 1. Busca Locais
        $locations = get_terms( array( 'taxonomy' => 'nativa_local_compra', 'hide_empty' => false ));
        $loc_map = [];
        if (!is_wp_error($locations)) {
            foreach($locations as $loc) {
                $type = get_term_meta($loc->term_id, 'nativa_location_type', true) ?: 'physical';
                $loc_map[] = ['id' => $loc->term_id, 'name' => $loc->name, 'type' => $type];
            }
        }

        // 2. Busca Itens Pendentes
        $model = new Nativa_Shopping_List();
        $raw_items = $model->get_pending_items();
        $pending_map = []; 
        $items = [];

        foreach($raw_items as $row) {
            $insumo_id = (int)$row->insumo_id; 
            $list_id   = (int)$row->id;
            
            $terms = wp_get_post_terms($insumo_id, 'nativa_local_compra', array('fields' => 'ids'));
            $valid_locations = !is_wp_error($terms) ? $terms : [];
            
            $pending_map[$insumo_id] = $list_id; 

            $items[] = [
                'id' => $list_id, 
                'insumo_id' => $insumo_id,
                'name' => get_the_title($insumo_id),
                'qty' => (float)$row->quantity,
                'unit' => $row->unit,
                'url' => $row->purchase_url,
                'valid_locations' => $valid_locations
            ];
        }

        // 3. Monta Catálogo (Insumos + Produtos Grab&Go)
        $library = [];
        $existing_names = []; 

        // A) Insumos Cadastrados
        $insumos = get_posts([ 'post_type' => 'nativa_insumo', 'posts_per_page' => -1, 'post_status' => 'publish' ]);
        
        foreach($insumos as $p) {
            $normalized_name = mb_strtolower($p->post_title, 'UTF-8');
            
            if (isset($existing_names[$normalized_name])) continue;

            $locs = wp_get_post_terms($p->ID, 'nativa_local_compra', ['fields' => 'ids']);
            $is_pending = isset($pending_map[$p->ID]);
            
            $library[] = [
                'id' => $p->ID,
                'name' => $p->post_title,
                'type' => 'insumo',
                'valid_locations' => !is_wp_error($locs) ? $locs : [],
                'is_pending' => $is_pending,
                'list_id' => $is_pending ? $pending_map[$p->ID] : null
            ];
            $existing_names[$normalized_name] = $p->ID;
        }

        // B) Produtos Grab & Go
        $products = get_posts([
            'post_type' => 'nativa_produto', 'posts_per_page' => -1, 'post_status' => 'publish',
            'meta_query' => [[ 'key' => 'nativa_requires_preparation', 'value' => '0', 'compare' => '=' ]]
        ]);

        foreach($products as $prod) {
            $pName = $prod->post_title;
            $normalized_pName = mb_strtolower($pName, 'UTF-8');
            
            if (isset($existing_names[$normalized_pName])) continue;

            $library[] = [
                'id' => $prod->ID,
                'name' => $pName,
                'type' => 'product', 
                'valid_locations' => [], 
                'is_pending' => false, 
                'list_id' => null
            ];
        }

        usort($library, function($a, $b) { return strcasecmp($a['name'], $b['name']); });

        return new WP_REST_Response([ 'success' => true, 'locations' => $loc_map, 'items' => $items, 'library' => $library ], 200);
    }

    public function add_item( $request ) {
        $params = $request->get_json_params();
        $name = sanitize_text_field($params['name']);
        $qty = (float) ($params['qty'] ?? 1);
        $locations = isset($params['locations']) ? $params['locations'] : [];
        $url = isset($params['url']) ? esc_url_raw($params['url']) : '';

        if (empty($name)) return new WP_REST_Response(['success'=>false, 'message'=>'Nome obrigatório'], 400);

        // Busca Inteligente para evitar duplicatas (Case Insensitive)
        global $wpdb;
        $existing_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'nativa_insumo' AND post_status = 'publish' LIMIT 1",
            $name
        ));

        if (!$existing_id) {
            // Fallback com LIKE se a busca exata falhar
            $existing_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE post_title LIKE %s AND post_type = 'nativa_insumo' AND post_status = 'publish' LIMIT 1",
                $name
            ));
        }

        if ($existing_id) {
            $insumo_id = $existing_id;
        } else {
            $insumo_id = wp_insert_post(['post_type' => 'nativa_insumo', 'post_title' => $name, 'post_status' => 'publish']);
        }

        if (!empty($locations)) {
            wp_set_object_terms($insumo_id, array_map('intval', $locations), 'nativa_local_compra');
        }

        $model = new Nativa_Shopping_List();
        $list_id = $model->add_item($insumo_id, $qty, 'un', $url);

        return new WP_REST_Response(['success'=>true, 'list_id'=>$list_id], 200);
    }

    public function create_location( $request ) {
        $params = $request->get_json_params();
        $name = sanitize_text_field($params['name']);
        $type = sanitize_text_field($params['type']) ?: 'physical';
        
        if (empty($name)) return new WP_REST_Response(['success'=>false, 'message'=>'Nome obrigatório'], 400);
        
        $term = wp_insert_term($name, 'nativa_local_compra');
        
        if (is_wp_error($term)) {
            if (isset($term->error_data['term_exists'])) {
                $term_id = $term->error_data['term_exists'];
                update_term_meta($term_id, 'nativa_location_type', $type);
                return new WP_REST_Response(['success'=>true, 'location_id'=>$term_id, 'message'=>'Local atualizado.'], 200);
            }
            return new WP_REST_Response(['success'=>false, 'message'=>$term->get_error_message()], 400);
        }
        
        $term_id = $term['term_id'];
        add_term_meta($term_id, 'nativa_location_type', $type, true);
        return new WP_REST_Response(['success'=>true, 'location_id'=>$term_id], 200);
    }

    public function remove_item( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        $id = (int) ($params['id'] ?? 0);
        
        if (!$id) return new WP_REST_Response(['success'=>false], 400);
        
        $wpdb->delete($wpdb->prefix . 'nativa_shopping_list', ['id' => $id]);
        return new WP_REST_Response(['success'=>true], 200);
    }

    public function mark_purchased( $request ) {
        $params = $request->get_json_params();
        $id = (int) $params['id'];
        $cost = (float) ($params['cost'] ?? 0);
        $location_id = (int) $params['location_id'];

        if (!$id || !$location_id) return new WP_REST_Response(['success'=>false], 400);
        
        $model = new Nativa_Shopping_List();
        $model->mark_purchased($id, $location_id, $cost);
        return new WP_REST_Response(['success'=>true], 200);
    }

    public function search_insumos( $request ) {
        $term = $request->get_param('q');
        $args = [ 'post_type' => 'nativa_insumo', 's' => $term, 'posts_per_page' => 10 ];
        $query = new WP_Query($args);
        
        $results = [];
        foreach($query->posts as $p) {
            $locs = wp_get_post_terms($p->ID, 'nativa_local_compra', ['fields' => 'ids']);
            $results[] = ['id' => $p->ID, 'name' => $p->post_title, 'locations' => $locs];
        }
        return new WP_REST_Response(['success'=>true, 'results'=>$results], 200);
    }
}