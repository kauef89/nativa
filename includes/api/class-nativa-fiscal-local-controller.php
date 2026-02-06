<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use NFePHP\DA\NFe\DaNfce; 

class Nativa_Fiscal_Local_Controller {

    public function register_routes() {
        register_rest_route( 'nativa/v2', '/fiscal/emit-local', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'emit_nfce_local' ),
            'permission_callback' => '__return_true',
        ));
    }

    public function emit_nfce_local( $request ) {
        global $wpdb;
        $params = $request->get_json_params();
        $session_id = isset($params['session_id']) ? (int)$params['session_id'] : 0;
        
        // 1. CARREGAMENTO HÍBRIDO (PRIORIDADE: WP-CONFIG > BANCO)
        // Busca configurações do banco apenas para fallback
        $db_config = get_option('nativa_fiscal_settings', []);
        
        // Define as variáveis finais verificando primeiro se a constante existe
        $tpAmb = defined('NATIVA_FISCAL_ENV') ? NATIVA_FISCAL_ENV : ($db_config['environment'] === 'production' ? 1 : 2);
        $razao = defined('NATIVA_FISCAL_RAZAO') ? NATIVA_FISCAL_RAZAO : ($db_config['razao_social'] ?? '');
        $cnpj  = defined('NATIVA_FISCAL_CNPJ') ? NATIVA_FISCAL_CNPJ : ($db_config['cnpj'] ?? '');
        $ie    = defined('NATIVA_FISCAL_IE') ? NATIVA_FISCAL_IE : ($db_config['ie'] ?? '');
        
        // CSC (Token e ID)
        $csc   = defined('NATIVA_FISCAL_CSC') ? NATIVA_FISCAL_CSC : ($db_config['csc_token'] ?? '');
        $cscId = defined('NATIVA_FISCAL_CSC_ID') ? NATIVA_FISCAL_CSC_ID : ($db_config['csc_id'] ?? '');
        
        // Certificado: Prioridade para caminho físico (NATIVA_CERT_PATH)
        $cert_pass = defined('NATIVA_CERT_PASS') ? NATIVA_CERT_PASS : ($db_config['cert_password'] ?? '');
        $pfx_content = null;

        if (defined('NATIVA_CERT_PATH') && file_exists(NATIVA_CERT_PATH)) {
            // Lê do caminho definido no wp-config (ex: /home/runcloud/...)
            $pfx_content = file_get_contents(NATIVA_CERT_PATH);
        } elseif (!empty($db_config['cert_id'])) {
            // Fallback: Lê do Media Library do WordPress
            $path = get_attached_file($db_config['cert_id']);
            if ($path && file_exists($path)) {
                $pfx_content = file_get_contents($path);
            }
        }

        // Validações de Segurança
        if (empty($pfx_content)) {
            return new WP_REST_Response(['success'=>false, 'message'=>'Certificado Digital não encontrado (Verifique NATIVA_CERT_PATH).'], 500);
        }
        if (empty($cert_pass)) {
            return new WP_REST_Response(['success'=>false, 'message'=>'Senha do certificado não configurada.'], 500);
        }
        if (empty($csc) || empty($cscId)) {
            return new WP_REST_Response(['success'=>false, 'message'=>'Token CSC não configurado.'], 500);
        }

        // 2. Busca Dados da Sessão
        $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}nativa_sessions WHERE id = %d", $session_id));
        
        if (!$session) {
            return new WP_REST_Response(['success'=>false, 'message'=>'Sessão não encontrada.'], 404);
        }

        // 3. Verifica duplicidade
        if ($session->fiscal_status === 'emitido' && !empty($session->fiscal_url)) {
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Nota já emitida anteriormente.',
                'danfe_url' => $session->fiscal_url
            ], 200);
        }

        try {
            $certificate = Certificate::readPfx($pfx_content, $cert_pass);
            
            // Configuração da Lib NFePHP com as variáveis resolvidas acima
            $nfe_config = [
                "atualizacao" => date('Y-m-d h:i:s'),
                "tpAmb"       => (int)$tpAmb,
                "razaosocial" => $razao,
                "cnpj"        => preg_replace('/\D/', '', $cnpj),
                "siglaUF"     => "SC",
                "schemes"     => "PL_009_V4",
                "versao"      => '4.00',
                "tokenIBPT"   => "AAAAAAA",
                "CSC"         => $csc,
                "CSCid"       => $cscId
            ];
            
            $configJson = json_encode($nfe_config);
            $tools = new Tools($configJson, $certificate);
            $tools->model('65'); // 65 = NFC-e

            // --- INÍCIO DA MONTAGEM DO XML ---
            $nfe = new Make();
            
            // Tag Infra
            $std = new \stdClass();
            $std->versao = '4.00';
            $std->Id = '';
            $std->pk_nItem = null;
            $nfe->taginfNFe($std);

            // Tag Identificação
            $std = new \stdClass();
            $std->cUF = 42; // Código IBGE SC
            $std->cNF = rand(10000000, 99999999);
            $std->natOp = 'VENDA CONSUMIDOR';
            $std->mod = 65;
            $std->serie = 1;
            $std->nNF = $this->get_next_number();
            $std->dhEmi = date("Y-m-d\TH:i:sP");
            $std->tpNF = 1; // Saída
            $std->idDest = 1; // Operação interna
            $std->cMunFG = 4202404; // Blumenau (Ideal: tornar configurável)
            $std->tpImp = 4; // DANFE NFC-e
            $std->tpEmis = 1; // Normal
            $std->tpAmb = $nfe_config['tpAmb'];
            $std->finNFe = 1; // Normal
            $std->indFinal = 1; // Consumidor final
            $std->indPres = 1; // Presencial
            $std->procEmi = 0;
            $std->verProc = 'NativaPOS 2.1';
            $nfe->tagide($std);

            // Tag Emitente
            $std = new \stdClass();
            $std->xNome = $nfe_config['razaosocial']; // Usa a variável resolvida
            $std->CNPJ = $nfe_config['cnpj'];         // Usa a variável resolvida
            $std->IE = preg_replace('/\D/', '', $ie);
            $std->CRT = 1; // 1 = Simples Nacional
            $nfe->tagemit($std);

            // Endereço Emitente
            $std = new \stdClass();
            $std->xLgr = "Rua Principal"; // Em produção, ideal vir das configs
            $std->nro = "100";
            $std->xBairro = "Centro";
            $std->cMun = 4202404; 
            $std->xMun = "Blumenau";
            $std->UF = "SC";
            $std->CEP = "89000000";
            $std->cPais = "1058";
            $std->xPais = "BRASIL";
            $nfe->tagenderEmit($std);

            // Tag Destinatário
            if ( !empty($params['cpf']) ) {
                $std = new \stdClass();
                $std->CPF = preg_replace('/\D/', '', $params['cpf']);
                $std->indIEDest = 9; // Não Contribuinte
                $nfe->tagdest($std);
            }

            // --- ITENS DO PEDIDO ---
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nativa_order_items WHERE session_id = %d AND status != 'cancelled'", 
                $session_id
            ));

            $i = 1;
            $total_produtos = 0;

            // Expande combos com lógica de rateio fiscal
            require_once NATIVA_PLUGIN_DIR . 'includes/classes/class-nativa-combo-helper.php';
            $items = Nativa_Combo_Helper::expand_items($items, 'fiscal');

            foreach ($items as $item) {
                // Recupera NCM e outros dados fiscais do produto
                $ncm = get_post_meta($item->product_id, 'nativa_prod_ncm', true) ?: '21069090'; // Fallback genérico
                $cfop = get_post_meta($item->product_id, 'nativa_prod_cfop', true) ?: '5102';
                $csosn = get_post_meta($item->product_id, 'nativa_prod_csosn', true) ?: '102';
                $origem = get_post_meta($item->product_id, 'nativa_prod_origem', true) ?: '0';
                $cest = get_post_meta($item->product_id, 'nativa_prod_cest', true); 

                $qtd = (float)$item->quantity;
                $vTotalItem = (float)$item->line_total;
                $vUnitReal = $qtd > 0 ? ($vTotalItem / $qtd) : 0;
                
                $total_produtos += $vTotalItem;

                // Produto
                $std = new \stdClass();
                $std->item = $i;
                $std->cProd = str_pad($item->product_id, 4, '0', STR_PAD_LEFT);
                $std->cEAN = "SEM GTIN";
                $std->xProd = mb_substr($item->product_name, 0, 120);
                $std->NCM = preg_replace('/\D/', '', $ncm);
                if($cest) $std->CEST = preg_replace('/\D/', '', $cest);
                $std->CFOP = $cfop;
                $std->uCom = "UN";
                $std->qCom = number_format($qtd, 4, '.', '');
                $std->vUnCom = number_format($vUnitReal, 10, '.', '');
                $std->vProd = number_format($vTotalItem, 2, '.', '');
                $std->cEANTrib = "SEM GTIN";
                $std->uTrib = "UN";
                $std->qTrib = number_format($qtd, 4, '.', '');
                $std->vUnTrib = number_format($vUnitReal, 10, '.', '');
                $std->indTot = 1;
                $nfe->tagprod($std);

                // Impostos (Simples Nacional)
                $std = new \stdClass();
                $std->item = $i;
                $std->orig = $origem;
                $std->CSOSN = $csosn; 
                
                if (in_array($csosn, ['102', '103', '300', '400'])) {
                    $nfe->tagICMSSN102($std);
                } else if ($csosn == '500') {
                    $std->vBCSTRet = 0.00;
                    $std->pST = 0.00;
                    $std->vICMSSTRet = 0.00;
                    $nfe->tagICMSSN500($std);
                }

                // PIS e COFINS
                $std = new \stdClass(); $std->item = $i; $std->CST = '49'; $std->vBC = 0.00; $std->pPIS = 0.00; $std->vPIS = 0.00; $nfe->tagPISOutr($std);
                $std = new \stdClass(); $std->item = $i; $std->CST = '49'; $std->vBC = 0.00; $std->pCOFINS = 0.00; $std->vCOFINS = 0.00; $nfe->tagCOFINSOutr($std);

                $i++;
            }

            // Totais
            $nfe->tagICMSTot(new \stdClass());

            // Pagamentos
            $payments = json_decode($session->payment_info_json, true);
            $total_pagamentos = 0;

            if (empty($payments)) {
                $std = new \stdClass();
                $std->tPag = '01'; // Dinheiro
                $std->vPag = number_format($total_produtos, 2, '.', '');
                $nfe->tagdetPag($std);
                $total_pagamentos = $total_produtos;
            } else {
                foreach($payments as $pay) {
                    $std = new \stdClass();
                    $std->tPag = $this->map_payment_method($pay['method']); 
                    $std->vPag = number_format((float)$pay['amount'], 2, '.', '');
                    $nfe->tagdetPag($std);
                    $total_pagamentos += (float)$pay['amount'];
                }
            }

            // Troco
            $std = new \stdClass();
            if ($total_pagamentos > $total_produtos) {
                $std->vTroco = number_format($total_pagamentos - $total_produtos, 2, '.', '');
            }
            $nfe->tagpag($std);

            // --- TRANSMISSÃO ---
            $xml = $nfe->getXML();
            $xmlSigned = $tools->signNFe($xml);
            $idLote = substr(preg_replace('/\D/', '', $session_id . time()), 0, 15);
            
            $resp = $tools->sefazEnviaLote($xmlSigned, $idLote, 1);
            
            $st = new Standardize();
            $stdResp = $st->toStd($resp);

            if (isset($stdResp->protNFe->infProt->cStat)) {
                $cStat = $stdResp->protNFe->infProt->cStat;
                $xMotivo = $stdResp->protNFe->infProt->xMotivo;
                
                if ($cStat == 100) { // Autorizado
                    $protocolo = $stdResp->protNFe->infProt->nProt;
                    $xml_autorizado = $tools->addProtocol($xmlSigned, $resp);
                    
                    // Gera PDF
                    $danfce = new DaNfce($xml_autorizado, $configJson);
                    $danfce->debugMode(false);
                    $danfce->creditsIntegratorFooter('Nativa PDV');
                    $pdfContent = $danfce->render();
                    
                    $upload_dir = wp_upload_dir();
                    $filename = 'nfce_' . $session_id . '_' . time() . '.pdf';
                    file_put_contents($upload_dir['path'] . '/' . $filename, $pdfContent);
                    
                    $wpdb->update($wpdb->prefix . 'nativa_sessions', [
                        'fiscal_status' => 'emitido',
                        'fiscal_url' => $upload_dir['url'] . '/' . $filename
                    ], ['id' => $session_id]);

                    return new WP_REST_Response([
                        'success' => true,
                        'message' => 'NFC-e Autorizada com Sucesso!',
                        'danfe_url' => $upload_dir['url'] . '/' . $filename,
                        'protocolo' => $protocolo
                    ], 200);
                } else {
                    // Rejeição
                    $wpdb->update($wpdb->prefix . 'nativa_sessions', ['fiscal_status' => 'erro'], ['id' => $session_id]);
                    return new WP_REST_Response(['success'=>false, 'message'=>"Rejeição SEFAZ ($cStat): $xMotivo"], 400);
                }
            } else {
                return new WP_REST_Response(['success'=>false, 'message'=>'Erro comunicação SEFAZ.'], 500);
            }

        } catch (\Exception $e) {
            return new WP_REST_Response(['success'=>false, 'message'=>'Erro Interno: ' . $e->getMessage()], 500);
        }
    }
    
    private function map_payment_method($nativa_method) {
        switch ($nativa_method) {
            case 'money': return '01';
            case 'credit_card': return '03';
            case 'debit_card': return '04';
            case 'pix': 
            case 'pix_sicredi': return '17';
            case 'loyalty': return '99'; 
            default: return '99';
        }
    }
    
    private function get_next_number() {
        $num = get_option('nativa_nfce_sequence', 1);
        update_option('nativa_nfce_sequence', $num + 1);
        return $num;
    }
}