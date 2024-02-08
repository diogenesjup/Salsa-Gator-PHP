<?php 
/*
Plugin Name: Salsa Comunicação
Plugin URI: https://fabricadeplugins.com.br/
Description: Plugin para comunicação geral restrita Salsa
Author: Diogenes Junior
Version: 1.0.0
Author URI: https://fabricadeplugins.com.br/
*/

/**
*  ------------------------------------------------------------------------------------------------
*
*
*   NOTIF API
*
*
*  ------------------------------------------------------------------------------------------------
*/
add_action('rest_api_init', function () {
    register_rest_route('salsa/v1', '/notif', array(
        'methods' => 'POST',
        'callback' => 'handle_salsa_notification',
    ));
});

function handle_salsa_notification($request) {
    $data_recebido = $request->get_body(); // Pega os dados recebidos
    $log = new WC_Logger();
    $log_entry = "Webhook Recebido: " . print_r($data_recebido, true);
    $log->add('salsa-webhook', $log_entry); // Salva no log do WooCommerce

     // Obter a URL do referer
    $referer = $request->get_header('referer');

    $log_entry = "Webhook Recebido Referer: " . $referer;
    $log->add('salsa-webhook', $log_entry); // Salva no log do WooCommerce

    $xmlstring = $data_recebido;

    $xml   = simplexml_load_string($xmlstring, "SimpleXMLElement", LIBXML_NOCDATA);
    $json  = json_encode($xml);
    $array = json_decode($json, true); 

    $method = $array['Method']['@attributes']['Name'];
    $params = $array['Method']['Params'];

    $token = $params['Token']['@attributes']['Value'];
    $data  = json_decode(base64_decode($token), true);

	if($params['Token']['@attributes']['Value'] != "AQUI-O-SEU-TOKEN"):
		return salsaTokenInvalido($params);
	endif;

	if($params['Hash']['@attributes']['Value']==":hash"):
		return salsaHashInvalido($method,$params);
	endif;

    switch ($method):

            case 'GetAccountDetails':
                
                $log_entry = "Match GetAccountDetails: " . print_r($params, true);
    			$log->add('salsa-webhook-GetAccountDetails', $log_entry);
                return salsaGetAccountDetails($params);
                 
                break;

            case 'GetBalance':
                
                $log_entry = "Match GetBalance: " . print_r($params, true);
    			$log->add('salsa-webhook-GetBalance', $log_entry);
                return salsaGetBalance($params);
                 
                break;

            case 'PlaceBet':
                
                $log_entry = "Match PlaceBet: " . print_r($params, true);
    			$log->add('salsa-webhook-PlaceBet', $log_entry);
                return salsaPlaceBet($params);
                 
                break;

            case 'AwardWinnings':
                
                $log_entry = "Match AwardWinnings: " . print_r($params, true);
    			$log->add('salsa-webhook-AwardWinnings', $log_entry);
                return salsaAwardWinnings($params);
                 
                break;

            case 'RefundBet':
                
                $log_entry = "Match RefundBet: " . print_r($params, true);
    			$log->add('salsa-webhook-RefundBet', $log_entry);
                return salsaRefundBet($params);
                 
                break;

            case 'ChangeGameToken':

                $log_entry = "Match ChangeGameToken: " . print_r($params, true);
    			$log->add('salsa-webhook-ChangeGameToken', $log_entry);
                return salsaChangeGameToken($params);
                 
                break;

            default:
                return new WP_REST_Response('Método não encontrado', 403);

    endswitch;

    return new WP_REST_Response('Recebido', 200);


}

/**
*  ------------------------------------------------------------------------------------------------
*
*
*   MÉTODOS
*
*
*  ------------------------------------------------------------------------------------------------
*/
function salsaGetAccountDetails($params){

	$xmlRequest ='
	<PKT>
		<Result Name="GetAccountDetails" Success="1">
			<Returnset>
				<Token Type="string" Value="'.$params['Token']['@attributes']['Value'].'" />
				<LoginName Type="string" Value="userteste1" />
				<Currency Type="string" Value="BRL" />
				<Country Type="string" Value="BR" />
				<Birthdate Type="date" Value="1990-07-24" />
				<Registration Type="date" Value="2023-12-01" />
				<Gender Type="string" Value="m" />
			</Returnset>
		</Result>
	</PKT>';

	$log = new WC_Logger();
    $log_entry = "Webhook Recebido salsaGetAccountDetails: " . print_r($params, true);
    $log->add('salsa-webhook-GetAccountDetails', $log_entry); // Salva no log do WooCommerce

	echo $xmlRequest;

}

function salsaGetBalance($params){

	$saldo_teste = get_option("saldo_teste");

	if($saldo_teste == "" || $saldo_teste==FALSE){
		$saldo_teste = 10000;
	}

	if($saldo_teste < 0){
		$saldo_teste = 10000;
	}

	update_option( "saldo_teste", $saldo_teste);

	$xmlRequest ='
	<PKT>
		<Result Name="GetBalance" Success="1">
		<Returnset>
		<Token Type="string" Value="'.$params['Token']['@attributes']['Value'].'" />
		<Balance Type="int" Value="'.$saldo_teste.'" />
		<Currency Type="string" Value="BRL" />
		</Returnset>
		</Result>
	</PKT>';

	$log = new WC_Logger();
    $log_entry = "Webhook Recebido salsaGetBalance: " . print_r($params, true);
    $log->add('salsa-webhook-salsaGetBalance', $log_entry); // Salva no log do WooCommerce

	echo $xmlRequest;

}

function salsaPlaceBet($params){

	$saldo = get_option("saldo_teste");

	if( $params['BetAmount']['@attributes']['Value']!="" && $params['BetAmount']['@attributes']['Value']!= 0 && $params['BetAmount']['@attributes']['Value'] <= $saldo):

				$bets_anteriores = get_post_meta( 1, "bet_ref_id", false );

				if (in_array($params['BetReferenceNum']['@attributes']['Value'], $bets_anteriores)):

					$xmlRequest ='
					<PKT>
						<Result Name="PlaceBet" Success="1">
						<Returnset>
						<Token Value="'.$params['Token']['@attributes']['Value'].'" />
						<Balance Type="int" Value="'.$saldo.'" />
						<Currency Type="string" Value="BRL" />
						<ExtTransactionID Type="long" Value="'.$bet_id.'" />
						<AlreadyProcessed Type="bool" Value="true" />
						</Returnset>
						</Result>
					</PKT>';

				else:

					$saldo = $saldo - $params['BetAmount']['@attributes']['Value'];

					update_option( "saldo_teste", $saldo);

					$bet_id = "1" .date("ddmmYYYYhhmmss");


					$xmlRequest ='
					<PKT>
						<Result Name="PlaceBet" Success="1">
						<Returnset>
						<Token Value="'.$params['Token']['@attributes']['Value'].'" />
						<Balance Type="int" Value="'.$saldo.'" />
						<Currency Type="string" Value="BRL" />
						<ExtTransactionID Type="long" Value="'.$bet_id.'" />
						<AlreadyProcessed Type="bool" Value="false" />
						</Returnset>
						</Result>
					</PKT>';

					add_post_meta( 1, "bet_id", $bet_id, false );
					add_post_meta( 1, "bet_ref_id", $params['BetReferenceNum']['@attributes']['Value'], false );

				endif;

				

	else:	

				$sucesso = 0;
				if($params['BetAmount']['@attributes']['Value']==0) $sucesso = 1;

				$xmlRequest ='
				<PKT>
					<Result Name="PlaceBet" Success="'.$sucesso.'">
					<Returnset>
					<Token Value="'.$params['Token']['@attributes']['Value'].'" />
					<Balance Type="int" Value="'.$saldo.'" />
					<Currency Type="string" Value="BRL" />
					<Error Type="string" Value="Not enoght credits|Insufficient funds" />
					<ErrorCode Type="string" Value="6" />
					</Returnset>
					</Result>
				</PKT>';

	endif;

	$log = new WC_Logger();
    $log_entry = "Webhook Recebido salsaPlaceBet: " . print_r($params, true);
    $log->add('salsa-webhook-salsaPlaceBet', $log_entry); // Salva no log do WooCommerce

	echo $xmlRequest;

}

function salsaAwardWinnings($params){

	$saldo = get_option("saldo_teste");

	$wins_anteriores = get_post_meta( 1, "id_winnings", false );

	if (in_array($params['WinReferenceNum']['@attributes']['Value'], $wins_anteriores)):

			$xmlRequest ='
			<PKT>
				<Result Name="AwardWinnings" Success="1">
				<Returnset>
				<Token Type="string" Value="'.$params['Token']['@attributes']['Value'].'" />
				<Balance Type="int" Value="'.$saldo.'" />
				<Currency Type="string" Value="BRL" />
				<ExtTransactionID Type="long" Value="22334" />
				<AlreadyProcessed Type="bool" Value="true" />
				</Returnset>
				</Result>
			</PKT>';

	else:

		add_post_meta( 1, "id_winnings", $params['WinReferenceNum']['@attributes']['Value'], false );

		$saldo = $saldo + $params['WinAmount']['@attributes']['Value'];

		update_option( "saldo_teste", $saldo);

		$xmlRequest ='
		<PKT>
			<Result Name="AwardWinnings" Success="1">
			<Returnset>
			<Token Type="string" Value="'.$params['Token']['@attributes']['Value'].'" />
			<Balance Type="int" Value="'.$saldo.'" />
			<Currency Type="string" Value="BRL" />
			<ExtTransactionID Type="long" Value="22334" />
			<AlreadyProcessed Type="bool" Value="false" />
			</Returnset>
			</Result>
		</PKT>';

	endif;

	

	$log = new WC_Logger();
    $log_entry = "Webhook Recebido salsaAwardWinnings: " . print_r($params, true);
    $log->add('salsa-webhook-AwardWinnings', $log_entry); // Salva no log do WooCommerce

	echo $xmlRequest;

}

function salsaRefundBet($params){

	$saldo = get_option("saldo_teste");

	$refunds_anteriores = get_post_meta( 1, "id_refundings", false );

	if (in_array($params['BetReferenceNum']['@attributes']['Value'], $refunds_anteriores)):

		$xmlRequest ='
		<PKT>
			<Result Name="RefundBet" Success="1">
			<Returnset>
			<Token Type="string" Value="'.$params['Token']['@attributes']['Value'].'" />
			<Balance Type="int" Value="'.$saldo.'" />
			<Currency Type="string" Value="BRL" />
			<ExtTransactionID Type="long" Value="1111" />
			<AlreadyProcessed Type="bool" Value="true" />
			</Returnset>
			</Result>
		</PKT>';

	else:

		add_post_meta( 1, "id_refundings", $params['BetReferenceNum']['@attributes']['Value'], false );

		$saldo = $saldo + $params['RefundAmount']['@attributes']['Value'];

		update_option( "saldo_teste", $saldo);

		$xmlRequest ='
		<PKT>
			<Result Name="RefundBet" Success="1">
			<Returnset>
			<Token Type="string" Value="'.$params['Token']['@attributes']['Value'].'" />
			<Balance Type="int" Value="'.$saldo.'" />
			<Currency Type="string" Value="BRL" />
			<ExtTransactionID Type="long" Value="1111" />
			<AlreadyProcessed Type="bool" Value="false" />
			</Returnset>
			</Result>
		</PKT>';

	endif;

	

	$log = new WC_Logger();
    $log_entry = "Webhook Recebido salsaRefundBet: " . print_r($params, true);
    $log->add('salsa-webhook-RefundBet', $log_entry); // Salva no log do WooCommerce

	echo $xmlRequest;

}

function salsaTokenInvalido($params){

	$xmlRequest ='
				<PKT>
					<Result Name="PlaceBet" Success="0">
					<Returnset>
					<Token Value="'.$params['Token']['@attributes']['Value'].'" />
					<Error Type="string" Value="Token Expired|Error retrieving Token|Invalid request" />
					<ErrorCode Type="string" Value="1" />
					<Balance Type="int" Value="'.$saldo.'" />
					<Currency Type="string" Value="BRL" />
					</Returnset>
					</Result>
				</PKT>';

	
	$log = new WC_Logger();
    $log_entry = "Webhook Recebido TOKEN INVALIDO: " . print_r($params, true);
    $log->add('salsa-webhook-token-invalido', $log_entry); // Salva no log do WooCommerce

	echo $xmlRequest;

}

function salsaHashInvalido($methodName,$params){

	$xmlRequest ='
				<PKT>
					<Result Name="'.$methodName.'" Success="0">
					<Returnset>
					<Token Value="'.$params['Token']['@attributes']['Value'].'" />
					<Error Type="string" Value="Invalid Hash" />
					<ErrorCode Type="string" Value="7000" />
					<Balance Type="int" Value="null" />
					<Currency Type="string" Value="BRL" />
					</Returnset>
					</Result>
				</PKT>';

	
	$log = new WC_Logger();
    $log_entry = "Webhook Recebido TOKEN INVALIDO: " . print_r($params, true);
    $log->add('salsa-webhook-token-invalido', $log_entry); // Salva no log do WooCommerce

	echo $xmlRequest;

}

function salsaChangeGameToken($params){

	$xmlRequest ='
	<PKT>
		<Result Name="ChangeGameToken" Success="1">
		<Returnset>
		<NewToken Type="string" Value="'.$params['Token']['@attributes']['Value'].'" />
		</Returnset>
		</Result>
	</PKT>';

	

	$log = new WC_Logger();
    $log_entry = "Webhook Recebido salsaChangeGameToken: " . print_r($params, true);
    $log->add('salsa-webhook-ChangeGameToken', $log_entry); // Salva no log do WooCommerce

	echo $xmlRequest;

}


?>