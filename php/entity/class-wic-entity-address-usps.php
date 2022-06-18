<?php
/*
*
* adapted for wordpress from http://joe-riggs.com/blog/2009/10/address-standardization-verification-with-usps-web-tools-and-php/
* original used curl functions for http post
*
*/ 
 
class WIC_Entity_Address_USPS {
 
	public $account = '';
	public $url = 'http://production.shippingapis.com/ShippingAPI.dll';
	public $address1, $address2, $city, $state, $zip;
	public $ship_address1, $ship_address2, $ship_city, $ship_state, $ship_zip;
 
	public function __construct() {
		$this->account = defined ( 'WIC_USER_NAME_FOR_POSTAL_ADDRESS_INTERFACE') ? WIC_USER_NAME_FOR_POSTAL_ADDRESS_INTERFACE : '';
	} 
 
	function toXML() {
		$xml = ' <AddressValidateRequest USERID="' . $this->account . '"><Address ID="1">';
		$xml .= '<Address1>' . $this->address1 . '</Address1>';
		$xml .= '<Address2>' . $this->address2 . '</Address2>';
		$xml .= '<City>' . $this->city . '</City>';
		$xml .= '<State>' . $this->state . '</State>';
		$xml .= '<Zip5>' . $this->zip . '</Zip5>';
		$xml .= '<Zip4></Zip4></Address>';
 
		if ($this->ship_address2 <> ''){
			//shipping address
			$xml .= '<Address ID="2">';
			$xml .= '<Address1>' . $this->ship_address1 . '</Address1>';
			$xml .= '<Address2>' . $this->ship_address2 . '</Address2>';
			$xml .= '<City>' . $this->ship_city . '</City>';
			$xml .= '<State>' . $this->ship_state . '</State>';
			$xml .= '<Zip5>' . $this->ship_zip . '</Zip5>';
			$xml .= '<Zip4></Zip4></Address>';
			}
 
		$xml .= '</AddressValidateRequest>';
 
     return $xml;
     }
 
	function submit_request() {
 
		$response = wp_remote_post( $this->url, array(
			'method' => 'POST',
			'timeout' => 60,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking' => true,
			'headers' => array(),
			'body' => array( 'API' => 'Verify', 'XML' => $this->toXML() ),
			'cookies' => array()
		    )
		);

		if ( is_wp_error( $response ) ) {
		   $error_message = $response->get_error_message();
		   Throw new Exception( sprintf ( 'Connection error in postal interface: %1$s' , $error_message ) );
		} else {
			return ( $response['body'] );
		}

	}	
	
	
	
}
