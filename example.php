<?php

	include 'Firebase/JWT/ExpiredException.php';
	include 'Firebase/JWT/BeforeValidException.php';
	include 'Firebase/JWT/SignatureInvalidException.php';
	include 'Firebase/JWT/JWT.php';
	
	use Firebase\JWT\JWT;
	
	header( "Content-type: text/plain", TRUE );

	$user = '<your username>';
	$secret = '<your secret>';
	
	echo "Trying to login user $user... ";
	
	if( $jwt = login( $user, $secret )) {
		echo "success.\n";
	} else {
		exit( "Authentication failed" );
	}

	// List the services assigned to this $user
	if( $services = listServices( $jwt ))
	{
		echo "User $user has " . count( $services ) . " active services assigned.\n";
		echo "Listing all active Icecast-Live services and their actual live CCU at this second:\n\n";
		
		// Filter for Icecast-Live services
		$service_ids = array();	// The service ID
		$service_pth = array();	// The service path
		foreach( $services as $service ) {
			// Grab any Icecast/Live service ('icscxl')
			// A complete list of all possible service identifiers
			// is provided by /api/v1/services/definitions
			if( $service['service'] === 'icscxl' ) {
				$service_ids[] = $service['id'];
				// Save Id => Path association for output
				$service_pth[ $service['id'] ] = $service['path'];
			}
		}
		
		$allccu = getCCU( $jwt, $service_ids );
		foreach( $allccu as $sid => $ccu ) {
			echo "    " . $service_pth[ $sid ] . " => " . $ccu . "\n";
		}
		
		echo "\n";
		
		$maxday = getMaxDay( $jwt, $service_ids );
		echo "Maximum number of CCU today: " . $maxday['max_ccu'] . " at ";
		echo date( "d-m-Y H:m:i", $maxday['timestamp'] ) . "\n";
		
		$maxmonth = getMaxMonth( $jwt, $service_ids );
		echo "Maximum number of CCU this month: " . $maxmonth['max_ccu'] . " at ";
		echo date( "d-m-Y", $maxmonth['timestamp'] ) . "\n";
		
		echo "\n--------------------------------\n";
	
	} else {
		echo "Error retrieving services for user $user\n";
	}
	
// --------- Helper functions ---------

function login( $user, $secret )
{
	$opts = [];
	$opts['http']['method'] = 'GET';
	$context = stream_context_create( $opts );
	
	// Retrieve a challenge from the API to validate our credentials
	if( $jwt = @file_get_contents( 'https://api.addradio.de/api/login?name=' . $user, FALSE, $context ))
	{
		$jwt = JWT::decode( $jwt, $secret, ['HS256'] );
	   
		// Just copy the challenge into the response. The API only wants
		// to know if we can encrypt its challenge using the right secret
		$jwt->response = $jwt->challenge;
	   
		// Construct a new authentication header
		$jwt = JWT::encode( $jwt, $secret );
		$opts['http']['header'] = 'Authorization: Bearer '.$jwt;
		$context = stream_context_create( $opts );
		
		// This function returns the JWT immediately, so no need to extract it from the headers
		$jwt = @file_get_contents( 'https://api.addradio.de/api/auth', FALSE, $context );
		
		// We now have a server-signed token to work with
		return $jwt;
	}
	
	return NULL;
}

function listServices( &$jwt )
{
	$services = readjson( 'https://api.addradio.de/api/v1/services/list?type=live', $jwt );
	return $services;
}

function getCCU( &$jwt, $services )
{
	$args = 'service=' . json_encode( $services );
	$ccu = readjson( 'https://api.addradio.de/api/v1/stats/live?' . $args, $jwt );
	return $ccu;
}

function getMaxDay( &$jwt, $services )
{
	$args = 'service=' . json_encode( $services );
	$ccu = readjson( 'https://api.addradio.de/api/v1/stats/max_ccu_today?' . $args, $jwt );
	return $ccu;
}

function getMaxMonth( &$jwt, $services )
{
	$args = 'service=' . json_encode( $services );
	$ccu = readjson( 'https://api.addradio.de/api/v1/stats/max_ccu_month?' . $args, $jwt );
	return $ccu;
}

function updateJWT( &$jwt, $headers )
{
	$new_jwt = NULL;
		
	if( is_array( $headers ))
	{
		// Search response headers for updated JWT.
		foreach( $headers as $header ) {
			$match = NULL;
			if( preg_match( '/Authorization: Bearer ([^,]+).*$/', $header, $match ) && isset( $match[1] )) {
				 $new_jwt = $match[1];
				 break;
			}
		}
		
		$jwt = $new_jwt;
	}
	
	return $new_jwt;
}

function readJson( $url, &$jwt=FALSE )
{
	// Is it a URL starting with http(s):// ?
	if( stripos( $url, 'http://' ) === 0 || stripos( $url, 'https://' ) === 0 )
	{
		// Is it a valid URL in any other regard?
		if( filter_var( $url, FILTER_VALIDATE_URL ) !== FALSE )
		{
			$json = FALSE;
			$headers = '';

			// Do we have a token we want to include in the header?
			if( $jwt ) {
				$headers .= 'Authorization: Bearer ' . $jwt . "\r\n";
			}
			
			$opts = [];
			$opts['http']['method'] = 'GET';
			$opts['http']['header'] = $headers;
			$opts['http']['timeout'] = '10.0';
			$opts['http']['request_fulluri'] = TRUE;
			$opts['http']['ignore_errors'] = TRUE;

			$context = stream_context_create( $opts );
			
			// Request JSON data, supressing errors
			$json = @file_get_contents( $url, FALSE, $context );
			
			// If there is some data that claims to be JSON...
			if( $json !== FALSE )
			{
				// ...try to decode it
				$data = json_decode( $json, TRUE );
				
				// Return decoded data, if successful.
				if( $data !== NULL ) {
					if( $jwt !== NULL ) {
						// JWT might have been updated by the server, so
						// go and grab for a new JWT with refreshed TTL
						updateJWT( $jwt, $http_response_header );
					}
					return $data;
				} else {
					// Unable to decode the result as JSON
				}
			}  else {
				// Unable to successfully read from $url
			}

		} else {
			// $url seems not to be valid
		}
	} else {
		// $url does not specify a protocol
	}
	
	// If something went wrong, return NULL
	return NULL;
}
