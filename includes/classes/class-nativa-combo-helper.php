<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nativa_Combo_Helper {

    /**
     * Expande os itens de uma sessão para fins Fiscais ou de Produção.
     */
    public static function expand_items( $items, $context = 'production' ) {
        $expanded_list = [];

        foreach ( $items as $item ) {
            // 1. Decodifica os modificadores salvos no banco
            $modifiers = !empty($item->modifiers_json) ? json_decode($item->modifiers_json, true) : [];
            
            // Verifica se é um Combo (pelo Post Type ou se tem estrutura de combo nos modifiers)
            $is_combo_product = (get_post_type($item->product_id) === 'nativa_combo');
            
            // Se não for combo, passa direto
            if ( !$is_combo_product ) {
                $expanded_list[] = $item;
                continue;
            }

            // 2. Dados Financeiros do Combo
            $combo_total_paid = (float) $item->line_total; // R$ 15.00
            $combo_qty = (int) $item->quantity;            // 1
            $unit_paid = $combo_qty > 0 ? ($combo_total_paid / $combo_qty) : 0;

            // 3. Reconstrói a hierarquia (Pai -> Filhos/Adicionais)
            $components = [];
            $last_parent_index = -1;
            $total_base_price_sum = 0;

            foreach ($modifiers as $mod) {
                // Se não tem ID, é apenas texto (ex: "Sem cebola"), ignora na contabilidade
                if ( empty($mod['product_id']) ) continue;

                $mod_id = $mod['product_id'];
                $mod_price = isset($mod['price']) ? (float)$mod['price'] : 0;
                
                // Busca preço base original do cadastro para o rateio
                $original_base_price = (float) get_post_meta($mod_id, 'produto_preco', true);
                
                // Determina se é um Item Principal do Combo ou um Modificador de Item
                // A flag 'type' vem do Frontend (ComboWizardModal)
                $is_main_item = isset($mod['type']) && $mod['type'] === 'combo_item';
                
                // Fallback: Se não tiver flag, assume que itens com preço 0 ou negativos são base do combo
                if (!isset($mod['type'])) {
                    $is_main_item = ($mod['name'][0] === '•'); // Hack visual se faltar flag
                }

                if ( $is_main_item ) {
                    // É um item principal (ex: Pastel, Coca)
                    $components[] = [
                        'post_id' => $mod_id,
                        'name' => str_replace('• ', '', $mod['name']), // Limpa bolinha
                        'base_value_share' => $original_base_price, // Valor de tabela para peso
                        'modifiers_text' => [], // Lista de strings para cozinha
                        'is_kitchen_item' => true
                    ];
                    $last_parent_index++;
                    $total_base_price_sum += $original_base_price;
                } 
                else {
                    // É um adicional (ex: Provolone)
                    // Adiciona o valor dele ao PAI para fins fiscais (R$ 10 do pastel + R$ 3 do queijo)
                    if ($last_parent_index >= 0) {
                        // FISCAL: Soma o valor base do adicional ao valor base do pai
                        $components[$last_parent_index]['base_value_share'] += $original_base_price;
                        $total_base_price_sum += $original_base_price;

                        // COZINHA: Adiciona como texto de instrução
                        $components[$last_parent_index]['modifiers_text'][] = str_replace(['   + ', '+ '], '', $mod['name']);
                    }
                }
            }

            // 4. Gera os itens finais explodidos
            $accumulated_price = 0;
            $total_components = count($components);

            foreach ($components as $index => $comp) {
                $virtual_item = clone $item; // Herda ID da sessão, etc.
                
                $virtual_item->product_id = $comp['post_id'];
                // Prefixo [COMBO] para facilitar identificação visual
                $virtual_item->product_name = "[{$item->product_name}] " . $comp['name'];
                
                // Adiciona os modificadores de texto para a cozinha (ex: + Provolone)
                if ( !empty($comp['modifiers_text']) ) {
                    // Recria estrutura de modifiers para o PrinterService ler
                    $virtual_modifiers = array_map(function($m){ return ['name' => "+ $m"]; }, $comp['modifiers_text']);
                    $virtual_item->modifiers_json = json_encode($virtual_modifiers);
                } else {
                    $virtual_item->modifiers_json = json_encode([]);
                }

                // --- LÓGICA DE PREÇO (RATEIO) ---
                if ( $context === 'fiscal' ) {
                    if ($total_base_price_sum > 0) {
                        $ratio = $comp['base_value_share'] / $total_base_price_sum;
                        $price_share = $unit_paid * $ratio;
                    } else {
                        $price_share = $unit_paid / $total_components;
                    }

                    // Ajuste de arredondamento no último item
                    if ($index === $total_components - 1) {
                        $price_share = $unit_paid - $accumulated_price;
                    }

                    $price_share = round($price_share, 2);
                    $accumulated_price += $price_share;

                    $virtual_item->unit_price = $price_share;
                    $virtual_item->line_total = $price_share * $combo_qty;
                } else {
                    // Para Cozinha/KDS, valor zerado ou informativo
                    $virtual_item->line_total = 0;
                }

                $expanded_list[] = $virtual_item;
            }
        }

        return $expanded_list;
    }
}