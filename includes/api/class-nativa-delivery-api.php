<?php
/**
 * API de Delivery (Endereços, Ruas e Taxas)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Delivery_API {

    public function register_routes() {
        // Rota 1: Listar todos os bairros
        register_rest_route( 'nativa/v2', '/bairros', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_bairros' ),
            'permission_callback' => '__return_true',
        ) );

        // Rota 2: Autocomplete de Ruas (Com Inteligência Fuzzy)
        register_rest_route( 'nativa/v2', '/search-address', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'search_address' ),
            'permission_callback' => '__return_true',
        ) );

        // Rota 3: Calcular Frete
        register_rest_route( 'nativa/v2', '/calculate-freight', array(
            'methods'             => array('POST'),
            'callback'            => array( $this, 'calculate_freight' ),
            'permission_callback' => '__return_true',
        ) );

        // Rota 4: Criar Rua (Cadastro Rápido)
        register_rest_route( 'nativa/v2', '/create-street', array(
            'methods'             => array('POST'),
            'callback'            => array( $this, 'create_street' ),
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' ); // Segurança
            },
        ) );
    }

    public function get_bairros() {
        $posts = get_posts( array(
            'post_type'      => 'nativa_bairro',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC'
        ) );

        $bairros = array();

        foreach ( $posts as $post ) {
            $taxa = (float) get_field( 'taxa_entrega', $post->ID );
            $min_gratis = (float) get_field( 'valor_minimo_frete_gratis', $post->ID );

            $bairros[] = array(
                'id'         => $post->ID,
                'name'       => $post->post_title,
                'fee'        => $taxa,
                'free_above' => $min_gratis
            );
        }

        return new WP_REST_Response( array( 'success' => true, 'bairros' => $bairros ), 200 );
    }

    /**
     * Busca ruas pelo nome (Com Fuzzy Search / "Você quis dizer")
     */
    public function search_address( $request ) {
        $term = trim( $request->get_param( 'q' ) );
        
        if ( empty( $term ) || strlen( $term ) < 3 ) {
            return new WP_REST_Response( [], 200 );
        }

        // 1. Busca Exata/Padrão (SQL LIKE)
        $query = new WP_Query( array(
            'post_type'      => 'nativa_rua',
            's'              => $term,
            'posts_per_page' => 10,
            'post_status'    => 'publish'
        ) );

        $results = array();
        
        // Adiciona resultados exatos
        foreach ( $query->posts as $post ) {
            $results[] = array(
                'id'   => $post->ID,
                'name' => $post->post_title,
                'type' => 'exact'
            );
        }

        // 2. Fuzzy Search (A Mágica)
        // Só roda se encontrou poucos resultados (menos de 5) para não poluir
        if ( count($results) < 5 ) {
            // Pega TODAS as ruas (só IDs e Títulos para ser leve)
            global $wpdb;
            $all_streets = $wpdb->get_results( "SELECT ID, post_title FROM $wpdb->posts WHERE post_type = 'nativa_rua' AND post_status = 'publish'" );

            // Prepara o termo de busca: remove acentos e deixa minúsculo
            $clean_term = strtolower( remove_accents( $term ) );

            foreach ( $all_streets as $street ) {
                // Prepara o nome da rua do banco
                $clean_street = strtolower( remove_accents( $street->post_title ) );
                
                // Calcula distância
                $dist = levenshtein( $clean_term, $clean_street );

                // REGRA DE OURO:
                // Se a distância for pequena (ex: 1, 2 ou 3 erros) 
                // E a palavra não for totalmente diferente (segurança baseada no tamanho)
                $threshold = 3;
                if ( strlen($clean_term) < 5 ) $threshold = 1; // Palavras curtas exigem mais precisão

                if ( $dist > 0 && $dist <= $threshold ) {
                    // Verifica se já não está na lista de exatos
                    $already_in_list = false;
                    foreach($results as $r) { if($r['id'] === $street->ID) $already_in_list = true; }

                    if ( ! $already_in_list ) {
                        $results[] = array(
                            'id'   => $street->ID,
                            'name' => $street->post_title,
                            'type' => 'suggestion',
                            'msg'  => 'Você quis dizer: ' . $street->post_title . '?'
                        );
                    }
                }
            }
        }

        // 3. Opção de Criar Nova (Se for atendente)
        if ( current_user_can( 'edit_posts' ) ) {
            $results[] = array(
                'id'   => 'new_street',
                'name' => 'Cadastrar nova rua: "' . $term . '"',
                'type' => 'action',
                'original_term' => $term
            );
        }

        return new WP_REST_Response( array( 'success' => true, 'results' => $results ), 200 );
    }

    /**
     * Calcula frete usando nomes de campos da V1
     */
    public function calculate_freight( $request ) {
        $rua_id = $request->get_param( 'rua_id' );
        $numero = (int) $request->get_param( 'numero' ); 

        if ( ! $rua_id ) return new WP_REST_Response( ['error' => 'Rua obrigatória'], 400 );

        // Compatibilidade V1: 'rua_segmentos'
        $mapa = get_field( 'rua_segmentos', $rua_id );
        
        if ( empty( $mapa ) ) {
            return new WP_REST_Response( ['success' => false, 'message' => 'Rua sem bairro vinculado.'], 404 );
        }

        $bairro_id_encontrado = null;

        foreach ( $mapa as $regra ) {
            $b_id = $regra['bairro_associado'];
            $inicio = !empty($regra['numero_inicial']) ? (int) $regra['numero_inicial'] : 0;
            $fim = !empty($regra['numero_final']) ? (int) $regra['numero_final'] : 999999;

            if ( $numero >= $inicio && $numero <= $fim ) {
                $bairro_id_encontrado = $b_id;
                break; 
            }
        }

        if ( ! $bairro_id_encontrado ) {
            $bairro_id_encontrado = $mapa[0]['bairro_associado'];
        }

        $nome_bairro = get_the_title( $bairro_id_encontrado );
        $taxa = (float) get_field( 'taxa_entrega', $bairro_id_encontrado );
        $min_gratis = (float) get_field( 'valor_minimo_frete_gratis', $bairro_id_encontrado );

        return new WP_REST_Response( array(
            'success' => true,
            'bairro'  => $nome_bairro,
            'taxa'    => $taxa,
            'frete_gratis_acima_de' => $min_gratis
        ), 200 );
    }

    /**
     * Cria uma nova rua e vincula a um bairro (Cadastro Simples)
     */
    public function create_street( $request ) {
        $name = sanitize_text_field( $request->get_param( 'name' ) );
        $bairro_id = (int) $request->get_param( 'bairro_id' );

        if ( empty( $name ) || empty( $bairro_id ) ) {
            return new WP_REST_Response( ['success' => false, 'message' => 'Dados inválidos.'], 400 );
        }

        $post_id = wp_insert_post( array(
            'post_title'  => $name,
            'post_type'   => 'nativa_rua',
            'post_status' => 'publish'
        ) );

        if ( is_wp_error( $post_id ) ) {
            return new WP_REST_Response( ['success' => false, 'message' => 'Erro ao criar registro.'], 500 );
        }

        // Salva ACF usando nomes da V1
        $segmento = array(
            'bairro_associado' => $bairro_id,
            'numero_inicial'   => '', 
            'numero_final'     => ''  
        );

        update_field( 'rua_segmentos', array( $segmento ), $post_id );

        return new WP_REST_Response( array(
            'success' => true,
            'rua' => array(
                'id' => $post_id,
                'name' => $name
            )
        ), 200 );
    }
}