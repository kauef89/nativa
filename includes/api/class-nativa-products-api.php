<?php
/**
 * API de Gestão de Produtos (Dashboard)
 * ATUALIZADO: Suporte a Grab&Go, Estoque Controlado e Gestão de Grupos de Adicionais
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Products_API {

    public function register_routes() {
        // Rotas de Produtos
        register_rest_route( 'nativa/v2', '/products', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_products' ),
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ));

        register_rest_route( 'nativa/v2', '/products/(?P<id>\d+)/quick-update', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'quick_update' ),
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ));

        register_rest_route( 'nativa/v2', '/products/bulk-update', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'bulk_update' ),
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ));

        register_rest_route( 'nativa/v2', '/products/export', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'export_products_csv' ),
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ));

        register_rest_route( 'nativa/v2', '/products/import', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'import_products_csv' ),
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ));

        // --- NOVAS ROTAS: Grupos de Adicionais ---
        register_rest_route( 'nativa/v2', '/products/modifier-groups', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_modifier_groups' ),
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ));

        register_rest_route( 'nativa/v2', '/products/modifier-groups/(?P<id>\d+)', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'update_modifier_group' ),
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ));
    }

    public function get_products( $request ) { 
        global $wpdb; 
        $args = array('post_type' => 'nativa_produto', 'posts_per_page' => -1, 'post_status' => array('publish', 'private'), 'orderby' => 'title', 'order' => 'ASC'); 
        $posts = get_posts( $args ); 
        $data = array(); 
        
        foreach ( $posts as $p ) { 
            $price_base = (float) get_post_meta( $p->ID, 'produto_preco', true ); 
            
            // Novos Campos de Estoque
            $is_controlled = get_post_meta( $p->ID, 'nativa_stock_controlled', true );
            if ($is_controlled === '') $is_controlled = get_post_meta( $p->ID, 'nativa_estoque_ativo', true );
            $is_controlled = (bool)$is_controlled;

            $stock_qty = (int) get_post_meta( $p->ID, 'nativa_estoque_qtd', true ); 
            $stock_min = (int) get_post_meta( $p->ID, 'nativa_stock_min', true ); 

            // Campo Grab & Go
            $prep_meta = get_post_meta( $p->ID, 'nativa_requires_preparation', true );
            $requires_prep = ($prep_meta === '' || $prep_meta === '1'); 

            $availability = get_post_meta( $p->ID, 'produto_disponibilidade', true ) ?: 'disponivel'; 
            $meta_del = get_post_meta( $p->ID, 'nativa_exibir_delivery', true ); 
            $show_delivery = ($meta_del === '' || $meta_del === '1'); 
            $meta_mesa = get_post_meta( $p->ID, 'nativa_exibir_mesa', true ); 
            $show_table = ($meta_mesa === '' || $meta_mesa === '1'); 
            $is_18 = (bool) get_post_meta( $p->ID, 'nativa_apenas_maiores', true ); 
            $is_loyalty = (bool) get_post_meta( $p->ID, 'nativa_is_loyalty_reward', true ); 
            
            // Tenta pegar o ID da cozinha (post_object)
            $station = get_field('nativa_estacao_impressao', $p->ID); 
            // Fallback para meta antigo se não achar (mas o ideal é que seja o ID agora)
            if (empty($station)) { $station = get_post_meta($p->ID, 'nativa_estacao_impressao', true); } 
            
            $img = get_the_post_thumbnail_url( $p->ID, 'thumbnail' ); 
            $sku = str_pad($p->ID, 4, '0', STR_PAD_LEFT); 
            
            $terms = get_the_terms( $p->ID, 'category' ); 
            $cat_name = 'Geral'; 
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) { 
                $term = $terms[0]; 
                $cat_name = $term->name; 
                if ( $term->parent ) { 
                    $parent = get_term( $term->parent, 'category' ); 
                    if ( ! is_wp_error( $parent ) ) { $cat_name = $parent->name . ' > ' . $term->name; } 
                } 
            } 
            
            $data[] = array( 
                'id' => $p->ID, 
                'name' => $p->post_title, 
                'sku' => $sku, 
                'price' => $price_base, 
                'image' => $img, 
                'manage_stock' => $is_controlled, 
                'stock_quantity' => $stock_qty, 
                'stock_min' => $stock_min, 
                'requires_prep' => $requires_prep, 
                'availability' => $availability, 
                'category_name' => $cat_name, 
                'show_delivery' => $show_delivery, 
                'show_table' => $show_table, 
                'is_18_plus' => $is_18, 
                'is_loyalty' => $is_loyalty, 
                'station' => $station // Retorna o ID da cozinha
            ); 
        } 
        return new WP_REST_Response( array( 'success' => true, 'products' => $data ), 200 ); 
    }

    public function quick_update( $request ) { 
        $id = (int) $request->get_param('id'); 
        $params = $request->get_params(); 
        $user_id = get_current_user_id(); 
        
        if ( ! $id ) return new WP_REST_Response( ['success'=>false, 'message'=>'ID inválido'], 400 ); 
        
        if ( isset( $params['availability'] ) ) update_post_meta( $id, 'produto_disponibilidade', sanitize_text_field( $params['availability'] ) ); 
        if ( isset( $params['show_delivery'] ) ) update_post_meta( $id, 'nativa_exibir_delivery', $params['show_delivery'] ? 1 : 0 ); 
        if ( isset( $params['show_table'] ) )    update_post_meta( $id, 'nativa_exibir_mesa', $params['show_table'] ? 1 : 0 ); 
        if ( isset( $params['is_18_plus'] ) )    update_post_meta( $id, 'nativa_apenas_maiores', $params['is_18_plus'] ? 1 : 0 ); 
        if ( isset( $params['is_loyalty'] ) )    update_post_meta( $id, 'nativa_is_loyalty_reward', $params['is_loyalty'] ? 1 : 0 ); 
        
        // CORREÇÃO CRÍTICA: Salva o ID da estação como INTEIRO para funcionar com post_object do ACF
        if ( isset( $params['station'] ) )       update_post_meta( $id, 'nativa_estacao_impressao', (int)$params['station'] ); 
        
        if ( isset( $params['requires_prep'] ) ) update_post_meta( $id, 'nativa_requires_preparation', $params['requires_prep'] ? 1 : 0 );
        if ( isset( $params['manage_stock'] ) )  update_post_meta( $id, 'nativa_stock_controlled', $params['manage_stock'] ? 1 : 0 );
        if ( isset( $params['stock_min'] ) )     update_post_meta( $id, 'nativa_stock_min', (int)$params['stock_min'] );

        if ( isset( $params['stock_add'] ) ) {
            $add_qty = (float) $params['stock_add'];
            if(class_exists('Nativa_Stock')) { 
                $stock_model = new Nativa_Stock(); 
                $stock_model->adjust_stock( $id, $add_qty, 'supply', $user_id, 'Entrada via Gestor' ); 
            }
        } 
        else if ( isset( $params['stock_quantity'] ) ) { 
            $new_total = (float) $params['stock_quantity']; 
            if(class_exists('Nativa_Stock')) { 
                $stock_model = new Nativa_Stock(); 
                $current = (float) get_post_meta( $id, 'nativa_estoque_qtd', true );
                $diff = $new_total - $current;
                $stock_model->adjust_stock( $id, $diff, 'adjustment', $user_id, 'Ajuste Rápido via Gestor' ); 
            } 
        } 
        
        return new WP_REST_Response( ['success' => true], 200 ); 
    }

    public function bulk_update( $request ) { 
        $params = $request->get_params(); 
        $ids = isset($params['ids']) ? $params['ids'] : []; 
        $data = isset($params['data']) ? $params['data'] : []; 
        $user_id = get_current_user_id(); 
        
        if ( empty($ids) || !is_array($ids) ) return new WP_REST_Response(['success'=>false, 'message'=>'Nenhum produto selecionado.'], 400); 
        
        $count = 0; 
        $stock_model = class_exists('Nativa_Stock') ? new Nativa_Stock() : null; 
        
        foreach ( $ids as $id ) { 
            $id = (int)$id; 
            if ( isset( $data['availability'] ) ) update_post_meta( $id, 'produto_disponibilidade', sanitize_text_field( $data['availability'] ) ); 
            if ( isset( $data['manage_stock'] ) ) update_post_meta( $id, 'nativa_stock_controlled', $data['manage_stock'] ? 1 : 0 ); 
            if ( isset( $data['show_delivery'] ) ) update_post_meta( $id, 'nativa_exibir_delivery', $data['show_delivery'] ? 1 : 0 ); 
            if ( isset( $data['show_table'] ) )    update_post_meta( $id, 'nativa_exibir_mesa', $data['show_table'] ? 1 : 0 ); 
            if ( isset( $data['is_loyalty'] ) )    update_post_meta( $id, 'nativa_is_loyalty_reward', $data['is_loyalty'] ? 1 : 0 ); 
            
            // CORREÇÃO: Salva station como inteiro
            if ( isset( $data['station'] ) )       update_post_meta( $id, 'nativa_estacao_impressao', (int)$data['station'] ); 
            
            if ( isset( $data['stock_quantity'] ) && $stock_model ) { 
                $new_qty = (float)$data['stock_quantity']; 
                update_post_meta( $id, 'nativa_stock_controlled', 1 ); 
                $current = (float) get_post_meta( $id, 'nativa_estoque_qtd', true );
                $diff = $new_qty - $current;
                $stock_model->adjust_stock( $id, $diff, 'adjustment', $user_id, 'Bulk Update' ); 
            } 
            $count++; 
        } 
        return new WP_REST_Response(['success'=>true, 'updated_count'=>$count], 200); 
    }

    /**
     * Exporta CSV com TODAS as novas colunas
     */
    public function export_products_csv() {
        $products = get_posts(['post_type' => 'nativa_produto', 'posts_per_page' => -1, 'post_status' => ['publish', 'private']]);
        
        $csv_data = [];
        // Header Expandido (Incluindo Grab&Go e Estoque Min)
        $csv_data[] = [
            'ID', 'Nome', 'Descrição', 'Categoria', 'Preço', 'Preço Promo', 
            'Status (disponivel/indisponivel/oculto)', 
            'Estoque Controlado (0/1)', 'Qtd Estoque', 
            'Exibir Delivery (0/1)', 'Exibir Mesa (0/1)', 'Maior de 18 (0/1)', 
            'Fidelidade (0/1)', 'Estação (ID da Cozinha)', // Alterado label
            'Grupos Adicionais (IDs)', 'NCM', 'CEST', 'CFOP', 'Origem', 'CSOSN',
            'Requer Preparo (0/1)', 'Estoque Mínimo' 
        ];

        foreach ($products as $p) {
            $cat_name = '';
            $terms = get_the_terms($p->ID, 'category');
            if ($terms && !is_wp_error($terms)) $cat_name = $terms[0]->name;
            
            $modifiers_raw = get_post_meta($p->ID, 'produto_grupos_adicionais', true);
            $modifiers_ids = maybe_unserialize($modifiers_raw);
            if (!is_array($modifiers_ids)) $modifiers_ids = [];
            $modifiers_str = implode(',', $modifiers_ids);

            // Preparo (Default 1 se vazio)
            $prep_meta = get_post_meta($p->ID, 'nativa_requires_preparation', true);
            $prep_val = ($prep_meta === '' || $prep_meta === '1') ? 1 : 0;

            // Stock Controlled (Default 0 se vazio)
            $stock_ctrl = get_post_meta($p->ID, 'nativa_stock_controlled', true) ? 1 : 0;

            $csv_data[] = [
                $p->ID, $p->post_title, $p->post_excerpt, $cat_name,
                get_post_meta($p->ID, 'produto_preco', true),
                get_post_meta($p->ID, 'produto_preco_promocional', true),
                get_post_meta($p->ID, 'produto_disponibilidade', true) ?: 'disponivel',
                $stock_ctrl, 
                get_post_meta($p->ID, 'nativa_estoque_qtd', true) ?: 0,
                get_post_meta($p->ID, 'nativa_exibir_delivery', true) !== '0' ? 1 : 0,
                get_post_meta($p->ID, 'nativa_exibir_mesa', true) !== '0' ? 1 : 0,
                get_post_meta($p->ID, 'nativa_apenas_maiores', true) ? 1 : 0,
                get_post_meta($p->ID, 'nativa_is_loyalty_reward', true) ? 1 : 0,
                get_post_meta($p->ID, 'nativa_estacao_impressao', true), // Agora exporta o ID
                $modifiers_str,
                get_post_meta($p->ID, 'nativa_prod_ncm', true),
                get_post_meta($p->ID, 'nativa_prod_cest', true),
                get_post_meta($p->ID, 'nativa_prod_cfop', true),
                get_post_meta($p->ID, 'nativa_prod_origem', true),
                get_post_meta($p->ID, 'nativa_prod_csosn', true),
                $prep_val, 
                get_post_meta($p->ID, 'nativa_stock_min', true) ?: 5 
            ];
        }

        $output = fopen('php://temp', 'r+');
        fputs($output, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
        foreach ($csv_data as $row) fputcsv($output, $row, ';');
        
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        return new WP_REST_Response(['success' => true, 'csv_content' => base64_encode($csv_content), 'filename' => 'produtos_nativa_' . date('Y-m-d') . '.csv'], 200);
    }

    /**
     * Importa CSV com detecção automática de separador
     */
    public function import_products_csv( $request ) {
        $files = $request->get_file_params();
        if ( empty($files['file']) ) return new WP_REST_Response(['success' => false, 'message' => 'Nenhum arquivo enviado.'], 400);
        
        $file = $files['file'];
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) return new WP_REST_Response(['success' => false, 'message' => 'Erro ao ler arquivo.'], 500);
        
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") { rewind($handle); }

        $sample_line = fgets($handle);
        $comma_count = substr_count($sample_line, ',');
        $semicolon_count = substr_count($sample_line, ';');
        $delimiter = ($semicolon_count > $comma_count) ? ';' : ',';
        
        rewind($handle);
        if ($bom === "\xEF\xBB\xBF") fread($handle, 3); 

        $header = fgetcsv($handle, 0, $delimiter); 
        
        $count_updated = 0; 
        $count_created = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            if (count($row) < 2) continue;

            $id = intval($row[0]); 
            $name = sanitize_text_field($row[1]);
            
            if (empty($name)) continue;

            $post_data = [
                'post_title' => $name, 
                'post_excerpt' => isset($row[2]) ? sanitize_textarea_field($row[2]) : '', 
                'post_type' => 'nativa_produto', 
                'post_status' => 'publish'
            ];
            
            if ($id > 0 && get_post($id)) { 
                $post_data['ID'] = $id; 
                wp_update_post($post_data); 
                $pid = $id; 
                $count_updated++; 
            } else { 
                $pid = wp_insert_post($post_data); 
                $count_created++; 
            }

            // Metadados Padrão
            update_post_meta($pid, 'produto_preco', $this->parse_money($row[4] ?? 0));
            update_post_meta($pid, 'produto_preco_promocional', $this->parse_money($row[5] ?? 0));
            update_post_meta($pid, 'produto_disponibilidade', sanitize_text_field($row[6] ?? 'disponivel'));
            
            // Estoque Controlado e Qtd
            update_post_meta($pid, 'nativa_stock_controlled', intval($row[7] ?? 0));
            update_post_meta($pid, 'nativa_estoque_qtd', intval($row[8] ?? 0));
            
            // Flags de Visibilidade
            update_post_meta($pid, 'nativa_exibir_delivery', intval($row[9] ?? 1));
            update_post_meta($pid, 'nativa_exibir_mesa', intval($row[10] ?? 1));
            update_post_meta($pid, 'nativa_apenas_maiores', intval($row[11] ?? 0));
            update_post_meta($pid, 'nativa_is_loyalty_reward', intval($row[12] ?? 0));
            
            // Estação (Importa como inteiro agora)
            update_post_meta($pid, 'nativa_estacao_impressao', (int)($row[13] ?? 0));
            
            // Adicionais
            $mod_ids = []; 
            if (!empty($row[14])) { 
                $mod_ids = array_map('intval', explode(',', $row[14])); 
            }
            update_post_meta($pid, 'produto_grupos_adicionais', $mod_ids);
            
            // Fiscal
            update_post_meta($pid, 'nativa_prod_ncm', sanitize_text_field($row[15] ?? ''));
            update_post_meta($pid, 'nativa_prod_cest', sanitize_text_field($row[16] ?? ''));
            update_post_meta($pid, 'nativa_prod_cfop', sanitize_text_field($row[17] ?? ''));
            update_post_meta($pid, 'nativa_prod_origem', sanitize_text_field($row[18] ?? ''));
            update_post_meta($pid, 'nativa_prod_csosn', sanitize_text_field($row[19] ?? ''));

            // --- NOVAS COLUNAS ---
            $req_prep = (isset($row[20]) && $row[20] !== '') ? intval($row[20]) : 1;
            update_post_meta($pid, 'nativa_requires_preparation', $req_prep);

            $stk_min = (isset($row[21]) && $row[21] !== '') ? intval($row[21]) : 5;
            update_post_meta($pid, 'nativa_stock_min', $stk_min);
            
            // Categoria
            $cat_name = sanitize_text_field($row[3] ?? '');
            if ($cat_name) {
                $term = term_exists($cat_name, 'category');
                if (!$term) $term = wp_insert_term($cat_name, 'category');
                if (!is_wp_error($term)) { 
                    $term_id = is_array($term) ? $term['term_id'] : $term; 
                    wp_set_object_terms($pid, (int)$term_id, 'category'); 
                }
            }
        }
        
        fclose($handle);
        return new WP_REST_Response(['success' => true, 'message' => "Importação concluída: $count_created criados, $count_updated atualizados."], 200);
    }

    // --- MÉTODOS PARA GRUPOS DE ADICIONAIS ---

    public function get_modifier_groups() {
        $args = array('post_type' => 'nativa_adic_grupo', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC');
        $posts = get_posts($args);
        
        $groups = [];
        foreach ($posts as $post) {
            $is_active = get_post_meta($post->ID, 'nativa_grupo_ativo', true);
            $show_delivery = get_post_meta($post->ID, 'nativa_grupo_show_delivery', true);
            $show_table = get_post_meta($post->ID, 'nativa_grupo_show_table', true);
            
            // --- NOVO: Campo enable_qty ---
            $enable_qty = get_post_meta($post->ID, 'nativa_grupo_enable_qty', true);

            // Padrões
            if ($is_active === '')     $is_active = '1';
            if ($show_delivery === '') $show_delivery = '1';
            if ($show_table === '')    $show_table = '1';
            if ($enable_qty === '')    $enable_qty = '0'; // Padrão Desligado

            $groups[] = [
                'id' => $post->ID,
                'name' => $post->post_title,
                'is_active' => $is_active === '1',
                'show_delivery' => $show_delivery === '1',
                'show_table' => $show_table === '1',
                'enable_qty' => $enable_qty === '1'
            ];
        }

        return new WP_REST_Response(['success' => true, 'groups' => $groups], 200);
    }

    public function update_modifier_group( $request ) {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        if ( !$id || !get_post($id) ) return new WP_REST_Response(['success' => false, 'message' => 'Grupo não encontrado.'], 404);

        if ( isset($params['is_active']) )     update_post_meta($id, 'nativa_grupo_ativo', $params['is_active'] ? '1' : '0');
        if ( isset($params['show_delivery']) ) update_post_meta($id, 'nativa_grupo_show_delivery', $params['show_delivery'] ? '1' : '0');
        if ( isset($params['show_table']) )    update_post_meta($id, 'nativa_grupo_show_table', $params['show_table'] ? '1' : '0');
        
        // --- NOVO: Salva Qtd Habilitada ---
        if ( isset($params['enable_qty']) )    update_post_meta($id, 'nativa_grupo_enable_qty', $params['enable_qty'] ? '1' : '0');

        return new WP_REST_Response(['success' => true, 'message' => 'Grupo atualizado.'], 200);
    }

    private function parse_money($val) {
        if (is_numeric($val)) return $val;
        return floatval(str_replace(',', '.', str_replace('.', '', $val)));
    }
}