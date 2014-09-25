<?php
/*
 * update-inwx.php - Update INWX Nameserver-Record
 * 
 * Mit diesem Script kann man einen Nameserver-Record beim Provider inwx.de updaten.
 *   
 * by Thomas klumpp
 */

header('Content-type: text/plain; charset=utf-8');
if ($debug) {
    ini_set("log_errors", 1);
    ini_set("error_log", "php-error.log");
    error_reporting(E_ALL);
}
require "domrobot.class.php";
require "config.inc.php";

// globals
$domrobot = new domrobot(APIURL); 

// GET variables from URL
if (isset($_GET['domain'])) {
    $domain = filter_input(INPUT_GET, 'domain', FILTER_SANITIZE_STRING);
} else {
    abortOnError(400, 'No target domain specified');
}
if (isset($_GET['ip4addr'])) {
	$ip4addr = filter_input(INPUT_GET, 'ip4addr', FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    if (!$ip4addr) abortOnError(400, 'Invalid IPv4');
}
if (isset($_GET['ip6addr'])) {
	$ip6addr = filter_input(INPUT_GET, 'ip6addr', FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    if (!$ip6addr) abortOnError(400, 'Invalid IPv6');
}

// get username and password from $_SERVER
if (isset($_SERVER['PHP_AUTH_USER'])) {
    $dynDomainUser = filter_var($_SERVER['PHP_AUTH_USER'], FILTER_SANITIZE_STRING);
} else {
    abortOnError(400, 'No Username provided');
}
if (isset($_SERVER['PHP_AUTH_PW'])) {
    $dynDomainPass = filter_var($_SERVER['PHP_AUTH_PW'], FILTER_SANITIZE_STRING);
} else {
    abortOnError(400, 'No password provided');
}

// Main
try {
    if (array_key_exists($domain, $domains) && $domains[$domain]['active']) {
        if ($dynDomainUser === $domains[$domain]['usr'] && $dynDomainPass === $domains[$domain]['pass']) {   	
            // login
        	$res = connect($inwxUser, $inwxPassword);
        	
        	// update ipv4 if requested
        	if (isset($ip4addr)) {
        		$recordId = requestRecordId($res, $domain, 'ipv4');
        		updateRecord($res, $recordId, $ip4addr);
        	}
        	
        	// update ipv6 if requested
        	if (isset($ip6addr)) {
        		$recordId = requestRecordId($res, $domain, 'ipv6');
        		updateRecord($res, $recordId, $ip6addr);
        	}
        	
        	// done, logout
        	$domrobot->logout();  
        } else {
            abortOnError(403, 'wrong username or password.');      
        }   
    }  else {
        abortOnError(400, 'missing domain or inactive domain'); // missing domain or inactive domain
    }
  
} catch (Exception $e) {
	error_log($e->getMessage(), 0, 'php-error.log');
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

/**
* Send Header to indicate some error so Fritzbox can detect failure
*/
function abortOnError($httpResponse, $message) {
    // backwards compatibilty for php<5.4  
    if (!function_exists('http_response_code')) {
        function http_response_code($response) {
            header('none', false, $response);
        }
    }    

    http_response_code($httpResponse);
    error_log($message, 0, 'php-error.log');
    die();
}
?>