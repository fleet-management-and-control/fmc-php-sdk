<?php
namespace Mapon;

use stdClass;

class MaponApi{

	public $apiKey;

	public $apiUrl = 'https://mapon.com/api/v1/';

	public $libVersion = 1.0;

	public $debug = false;

	public function __construct($apiKey){
		$this->apiKey = $apiKey;
	}

	/**
	 * @param string $type
	 * @param string $method
	 * @param array $getData
	 * @param array $postData
	 * @return stdClass
	 * @throws ApiException
	 */
	public function callMethod($type, $method, $getData = array(), $postData = array()){

		if(!function_exists('json_decode')){
			throw new ApiException('Please enable php json extension');
		}

		$getData['key'] = $this->apiKey;
		$getData['_phplibv'] = $this->libVersion;

		$to_call = $this->apiUrl . $method . '.json' . '?' . http_build_query($getData, '', '&');

		$context_opts = array(
			'http' => array(
				'method' => strtoupper($type)
			)
		);

		if($type == 'post'){
			$postData = http_build_query($postData, '', '&');

			$context_opts['http']['header'] = "Content-Type: application/x-www-form-urlencoded\r\n";
			$context_opts['http']['header'] .= "Content-Length: " . strlen($postData) . "\r\n";
			$context_opts['http']['content'] = $postData;
		}

		$context = stream_context_create($context_opts);

		$res = file_get_contents($to_call, false, $context);

		if($res === false){
			throw new ApiException('Error while requesting API');
		}

		$json = json_decode($res);

		if(is_null($json) || (!isset($json->data) && !isset($json->error))){
			if($this->debug){
				echo "Response from API:\n" . $res . "\n";
			}
			throw new ApiException('Error while parsing API response');
		}

		if(isset($json->error)){
			throw new ApiException($json->error->msg, $json->error->code);
		}

		return $json;
	}

	/**
	 * Call GET API request
	 * @param $action
	 * @param array $get_data
	 * @return stdClass
	 * @throws ApiException
	 */
	public function get($action, $get_data = array()){
		return $this->callMethod('get', $action, $get_data);
	}

	/**
	 * Call POST API request
	 * @param $action
	 * @param array $post_data
	 * @return stdClass
	 * @throws ApiException
	 */
	public function post($action, $post_data = array()){
		return $this->callMethod('post', $action, array(), $post_data);
	}

	/**
	 * Based on:
	 * http://facstaff.unca.edu/mcmcclur/GoogleMaps/EncodePolyline/decode.js
	 * http://code.google.com/apis/maps/documentation/polylinealgorithm.html
	 */
	public function decodePolyline($encoded, $speed = null, $startTime = null){
		if(!is_null($speed)){
			$speed = $this->decodeSpeed($speed);
		}

		if(!is_null($startTime)){
			$startTime = strtotime($startTime);
		}

		$length = strlen($encoded);
		$index = 0;
		$points = array();
		$lat = 0;
		$lng = 0;

		while($index < $length){

			// Temporary variable to hold each ASCII byte.
			$b = 0;

			// The encoded polyline consists of a latitude value followed by a
			// longitude value.  They should always come in pairs.  Read the
			// latitude value first.
			$shift = 0;
			$result = 0;
			do{
				// The `ord(substr($encoded, $index++))` statement returns the ASCII
				//  code for the character at $index.  Subtract 63 to get the original
				// value. (63 was added to ensure proper ASCII characters are displayed
				// in the encoded polyline string, which is `human` readable)
				$b = ord(substr($encoded, $index++)) - 63;

				// AND the bits of the byte with 0x1f to get the original 5-bit `chunk.
				// Then left shift the bits by the required amount, which increases
				// by 5 bits each time.
				// OR the value into $results, which sums up the individual 5-bit chunks
				// into the original value.  Since the 5-bit chunks were reversed in
				// order during encoding, reading them in this way ensures proper
				// summation.
				$result |= ($b & 0x1f) << $shift;
				$shift += 5;
			}
				// Continue while the read byte is >= 0x20 since the last `chunk`
				// was not OR'd with 0x20 during the conversion process. (Signals the end)
			while($b >= 0x20);

			// Check if negative, and convert. (All negative values have the last bit
			// set)
			$dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));

			// Compute actual latitude since value is offset from previous value.
			$lat += $dlat;

			// The next values will correspond to the longitude for this point.
			$shift = 0;
			$result = 0;
			do{
				$b = ord(substr($encoded, $index++)) - 63;
				$result |= ($b & 0x1f) << $shift;
				$shift += 5;
			}while($b >= 0x20);

			$dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
			$lng += $dlng;

			// The actual latitude and longitude values were multiplied by
			// 1e5 before encoding so that they could be converted to a 32-bit
			// integer representation. (With a decimal accuracy of 5 places)
			// Convert back to original values.

			$points[] = array(
				'lat' => $lat * 1e-5,
				'lng' => $lng * 1e-5
			);
		}

		if(!is_null($speed)){
			foreach($speed as $k => $v){
				$points[$k]['speed'] = $v[1];
				if(!is_null($startTime)){
					$startTime += $v[0];
					$points[$k]['time'] = $startTime;
				}
			}
		}

		return $points;
	}

	/**
	 * decodes time offsets and speeds
	 * @param string $str
	 * @return array
	 */
	public function decodeSpeed($str){
		$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-.';

		$points = strlen($str) / 4;

		$data = array();

		for($i = 0; $i < $points; $i++){

			$pos = $i * 4;

			$offset = strpos($chars, $str[$pos]) * 64;
			$offset += strpos($chars, $str[$pos + 1]);

			$speed = strpos($chars, $str[$pos + 2]) * 64;
			$speed += strpos($chars, $str[$pos + 3]);

			$data[] = array($offset, $speed);
		}

		return $data;
	}
}