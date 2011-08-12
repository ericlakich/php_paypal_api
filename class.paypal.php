<?php

/**
 * PayPal Class
 * 
 * Class to handle express checkout
 * using PayPal NVP 
 * 
 * @author Miguel Perez
 *
 * $paypal = Paypal();
 *
 * Checkout:
 * $paypal->setItems($name, $quantity, $cost);
 * $paypal->CheckOut();
 *
 * Process:
 * Handler.php
 * $token = $_GET[ 'token' ]
 * 
 * $paypal->Process($token);
 */
class PayPal
{
	/**
	 * NVP variables
	 * @var array
	 */
	private $nvp = array();
	/**
	 * NVP API URL
	 * @var string
	 */
	private $nvpURL;

	/**
	 * webscr URL
	 * @var string
	 */
	private $webscr;

	/**
	 * Items
	 * @var array
	 */
	private $items = array();


	public function __construct($CompanyName, $handlerURL, $cancelURL, $APIPassword, $APIUser, $APISignature, $sandbox = false)
	{
		$total = 10;
		$this->setRequest('BRANDNAME', $CompanyName);
		$this->setRequest('PAYMENTREQUEST_0_AMT', $total);
		$this->setRequest('PAYMENTREQUEST_0_CURRENCYCODE', 'USD');
		$this->setRequest('PAYMENTREQUEST_0_PAYMENTACTION', 'Sale' );
		$this->setRequest('RETURNURL', $handlerURL);
		$this->setRequest('CANCELURL', $cancelURL);


		if($sandbox){

			$this->nvpURL = 'https://api-3t.sandbox.paypal.com/nvp';
			$this->webscr = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

		}else{

			$this->nvpURL = 'https://api-3t.paypal.com/nvp';
			$this->webscr = 'https://www.paypal.com/cgi-bin/webscr';
		}

		$this->setRequest('VERSION', '64');
		$this->setRequest('PWD', $APIPassword);
		$this->setRequest('USER', $APIUser);
		$this->setRequest('SIGNATURE', $APISignature);
	}

	/**
	 * Process order after check out
	 * @param string $token
         * @return boolean
	 */
	public function Process($token)
	{
		$this->setRequest('TOKEN', $token);
		$this->setMethod('GetExpressCheckoutDetails');

		$response = $this->Sent();
		if($response['ACK'] == 'Success' && $response['TOKEN'] == $token ){

			$this->setRequest('PAYERID', $response['PAYERID']);
			$this->setRequest('PAYMENTREQUEST_0_AMT', $response['PAYMENTREQUEST_0_AMT']);

			$this->setMethod('DoExpressCheckoutPayment');
			$paymant = $this->Sent();
				
			if($paymant['PAYMENTINFO_0_PAYMENTSTATUS'] == 'Completed'){
				
				return true;
			}

		}
		return false;
	}

	/**
	 *Checkout items after setting them
	 *@return redirect
	 */
	public function CheckOut()
	{
		$total = 0;
		foreach ($this->items as $key => $value) {
			$this->setRequest('L_PAYMENTREQUEST_0_NAME'.$key, $value['name']);
			$this->setRequest('L_PAYMENTREQUEST_0_QTY'.$key, $value['quantity']);
			$this->setRequest('L_PAYMENTREQUEST_0_AMT'.$key, $value['cost']);

			$total += ($value['cost'] * $value['quantity']);

		}
		$this->setRequest('PAYMENTREQUEST_0_ITEMAMT', number_format($total, 2));
		$this->setRequest('PAYMENTREQUEST_0_AMT', number_format($total, 2));
		$this->setMethod('SetExpressCheckout');

		$response = $this->Sent();

		$query = array(
		'cmd'	=> '_express-checkout',
		'token'	=> $response[ 'TOKEN' ]
		);

		return header( 'Location: ' . $this->webscr . '?' . http_build_query( $query ) );
	}

	/**
	 * Set items for check out
	 * @param string $name
	 * @param integer $quantity
	 * @param double $cost
	 */
	public function setItems($name, $quantity, $cost)
	{
           $this->items[]= array('name' => $name,
           'quantity' => $quantity,
	   'cost' =>  number_format($cost, 2));
	}

	/**
	 * Set API requests
	 * @param string $name
	 * @param string $value
	 */
	private function setRequest($name, $value)
	{
		$this->nvp[$name] = $value;
	}

	/**
	 * Set API methods
	 * @param unknown_type $method
	 */
	private function setMethod($method)
	{
		$this->nvp['METHOD'] = $method;
	}

	/**
	 * Sent call to API
	 * @return array
	 */
	private function Sent()
	{
		$curl = curl_init();

		curl_setopt( $curl , CURLOPT_URL , $this->nvpURL);
		curl_setopt( $curl , CURLOPT_SSL_VERIFYPEER , false );
		curl_setopt( $curl , CURLOPT_RETURNTRANSFER , 1 );
		curl_setopt( $curl , CURLOPT_POST , 1 );
		curl_setopt( $curl , CURLOPT_POSTFIELDS , http_build_query( $this->nvp ) );

		$response = urldecode( curl_exec( $curl ) );

		curl_close( $curl );

		parse_str($response, $result);


		return $result;
	}
}