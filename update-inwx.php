<?php
/*
 * update-inwx.php - Update INWX Nameserver-Record
 * 
 * Mit diesem Script kann man einen Nameserver-Record beim Provider inwx.de updaten.
 *   
 * by Thomas klumpp
 */

header('Content-type: text/plain; charset=utf-8');
ini_set('display_errors',1);
error_reporting(E_ALL);
require "domrobot.class.php";
require "config.inc.php";

// globals
$domrobot = new domrobot(APIURL); 

// GET variables from URL
$domain = $_GET['domain'];
if (isset($_GET['ip4addr'])) { // TODO check for valid ipv4 address
	$ip4addr = $_GET['ip4addr'];
}
if (isset($_GET['ip6addr'])) { // TODO check for valid ipv6 address
	$ip6addr = $_GET['ip6addr'];
}

//main
try {
	// login
	$res = connect($inwxUser, $inwxPassword);
	
	// update ipv4 if requested
	if (isset($ip4addr)) {
		$recordId = requestRecordId($res, $domain, "ipv4");
		updateRecord($res, $recordId, $ip4addr);
	}
	
	// update ipv6 if requeste
	if (isset($ip6addr)) {
		$recordId = requestRecordId($res, $domain, "ipv6");
		updateRecord($res, $recordId, $ip6addr);
	}
	
	// done, logout
	$domrobot->logout();
} catch (Exception $e) {
	print $e->getMessage();
}

/**
 * Fragt die eindeutige Nameserver-Record ID ab
 *
 * @param array $res Response from login
 * @param String $domain enthält den abzufragenden Domainnamen
 * @param String $type which IP type to query, either ipv4 or ipv6
 * @return int ID liefert die unique ID des Nameserver-Records
 */
function requestRecordId($res, $domain, $type) {
	global $domrobot;
	
	//domain zerlegen
	$domain_exploded = explode(".", $domain);
	$domain_exploded_length = count($domain_exploded);
	$domain = $domain_exploded[$domain_exploded_length - 2] . "." . $domain_exploded[$domain_exploded_length - 1];
	unset($domain_exploded[$domain_exploded_length - 1]);
	unset($domain_exploded[$domain_exploded_length - 2]);
	$name= implode(".", $domain_exploded);

	//do request
	if ($res['code']==1000) {
		$obj = "nameserver";
		$meth = "info";
		$params = array();
		$params['domain'] = $domain;
		$params['name'] = $name;
		$res = $domrobot->call($obj,$meth,$params);
		
		if ($type == "ipv4"){
			foreach ($res['resData']['record'] as $record) {
				if ($record['type'] == 'A') {
					$recordId = $record['id'];
				}
			}
		} else if ($type == "ipv6") {
			foreach ($res['resData']['record'] as $record) {
				if ($record['type'] == 'AAAA') {
					$recordId = $record['id'];
				}
			}
		} else 
			throw new Exception('unknown IP type');
		
		if ($recordId != "")
			return $recordId;
		else
			throw new Exception('domain or name not found');
	} else {
		throw new Exception('connection error occured');
	}
}

/**
 * Setzt die IP-Adresse in den entsprechenen Nameserver-Record
 *
 * @param array $res Response from login
 * @param int $recordId enthält die unique ID des Nameserver-Records
 * @param String $ip4addr enthält die zu setzende IP-Adresse
 */
function updateRecord($res, $recordId, $ipAddr) {
	global $domrobot;
	
	// do update
	if ($res['code']==1000) {
		$obj = "nameserver";
		$meth = "updateRecord";
		$params = array();
		$params['id'] = $recordId;
		$params['content'] = $ipAddr;
		$res = $domrobot->call($obj,$meth,$params);
	} else {
		throw new Exception('connection error occured');
	}
}

/**
* Log into inwx API
*/
function connect($user, $password) {	
	global $domrobot;
	$domrobot->setDebug(false);
	$domrobot->setLanguage('en');
	return $domrobot->login($user,$password);
}
?>