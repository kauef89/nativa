<?php
/**
 * Tradutor de Sessão (Model)
 * Responsável por conversar com a tabela 'wp_nativa_sessions'
 */

// Segurança: Se alguém tentar abrir o arquivo direto pelo navegador, o sistema bloqueia.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nativa_Session {

    private $table_name;

    public function __construct() {
        global $wpdb;
        // Define o nome da tabela. Se seu WP usa prefixo 'wp_', vira 'wp_nativa_sessions'
        $this->table_name = $wpdb->prefix . 'nativa_sessions';
    }

    /**
     * FUNÇÃO: Criar Nova Sessão
     * Usada quando: Cliente senta na mesa ou abre o app de delivery.
     */
    public function create( $data ) {
        global $wpdb;

        // Regra de Negócio: Não pode criar sem dizer o tipo (mesa, delivery, etc)
        if ( empty( $data['type'] ) ) {
            return new WP_Error( 'erro_sistema', 'O tipo de sessão é obrigatório.' );
        }

        // Prepara os dados padrão
        $defaults = array(
            'status'           => 'open', // Começa aberta
            'created_at'       => current_time( 'mysql' ), // Hora de agora
            'delivery_address_json' => null,
            'table_number'     => null,
            'customer_user_id' => null
        );

        // Junta o que veio do sistema com os padrões
        $args = wp_parse_args( $data, $defaults );

        // Se tiver endereço (delivery), transforma em texto JSON para o banco entender
        if ( ! empty( $args['delivery_address_json'] ) && is_array( $args['delivery_address_json'] ) ) {
            $args['delivery_address_json'] = json_encode( $args['delivery_address_json'], JSON_UNESCAPED_UNICODE );
        }

        // Insere no Banco de Dados
        $inseriu = $wpdb->insert(
            $this->table_name,
            $args
        );

        if ( ! $inseriu ) {
            return new WP_Error( 'erro_banco', 'Erro ao salvar sessão: ' . $wpdb->last_error );
        }

        // Retorna o ID da sessão criada (Ex: Sessão #105)
        return $wpdb->insert_id;
    }

    /**
     * FUNÇÃO: Buscar Sessão
     * Usada para ler os dados de uma mesa ou pedido
     */
    public function get( $id ) {
        global $wpdb;
        
        // Busca a linha pelo ID
        $query = $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id );
        $resultado = $wpdb->get_row( $query );

        // Se achou, destransforma o endereço JSON de volta para Array pro PHP usar
        if ( $resultado && $resultado->delivery_address_json ) {
            $resultado->delivery_address_json = json_decode( $resultado->delivery_address_json, true );
        }

        return $resultado;
    }

    /**
     * FUNÇÃO: Atualizar Sessão
     * Usada para fechar a conta ou mudar status
     */
    public function update( $id, $dados ) {
        global $wpdb;
        
        // Se estiver mandando endereço, codifica pra JSON
        if ( isset( $dados['delivery_address_json'] ) && is_array( $dados['delivery_address_json'] ) ) {
            $dados['delivery_address_json'] = json_encode( $dados['delivery_address_json'], JSON_UNESCAPED_UNICODE );
        }
        
        // Regra de Negócio: Se o status for "Pago" ou "Cancelado", marca a hora do fechamento
        if ( isset( $dados['status'] ) && ( $dados['status'] == 'paid' || $dados['status'] == 'cancelled' ) ) {
            if ( ! isset( $dados['closed_at'] ) ) {
                $dados['closed_at'] = current_time( 'mysql' );
            }
        }

        // Executa a atualização
        return $wpdb->update( $this->table_name, $dados, array( 'id' => $id ) );
    }
}