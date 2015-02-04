<?php
use Mapon\MaponApi;

include __DIR__ . '/../vendor/autoload.php';

$apiKey = 'YOUR-API-KEY';

$api = new MaponApi($apiKey);

$result = null;

try{
	$result = $api->get('route/list', array(
		'from' => '2013-03-08T00:00:00Z',
		'till' => '2013-03-08T23:59:59Z',
		'include' => array('polyline', 'speed')
	));
}catch(\Mapon\ApiException $e){
	echo 'API Error! Code: ' . $e->getCode() . ' Message: ' . $e->getMessage();
}

if($result && $result->data){
	foreach($result->data->units as $unit_id => $unit_data){
		echo "Unit: " . $unit_id . "\n";
		foreach($unit_data->routes as $route){
			if($route->type == 'route'){
				echo "Route starts at: " . $route->start->time . ", " . (isset($route->end) ? " ends at: " . $route->end->time : " not yet finished") . "\n";
				if(isset($route->polyline)){
					$points = $api->decodePolyline($route->polyline, $route->speed, $route->start->time);
					echo "Lat/lng points in route: " . count($points) . "\n";
				}
			}
		}
	}
}