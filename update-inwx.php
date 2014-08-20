<?php
/*
 * update-inwx.php - Update INWX Nameserver-Record
 * 
 * Mit diesem Script kann man einen Nameserver-Record beim Provider inwx.de updaten.
 *   
 * by Thomas klumpp
 */

header('Content-type: text/plain; charset=utf-8');
error_reporting(E_ALL);
require "domrobot.class.php";

define("APIURL", "https://api.domrobot.com/xmlrpc/");

//GET variablen aus url holen
$usr = $_GET['user'];
$pwd = $_GET['password'];
$domain = $_GET['domain'];
$ip4addr = $_GET['ip4addr'];

//main
try {
	$recordId = requestRecordId($domain);
	updateRecord($recordId, $ip4addr);
} catch (Exception $e) {
	print $e->getMessage();
}

/**
 * Fragt die eindeutige Nameserver-Record ID ab
 *
 * @param String $domain enthält den abzufragenden Domainnamen
 * @return int ID liefert die unique ID des Nameserver-Records
 */
function requestRecordId($domain) {
	//globale variablen abrufen
	global $usr;
	global $pwd;

	//domrobot object instanziieren und einloggen
	$domrobot = new domrobot(APIURL);
	$domrobot->setDebug(false);
	$domrobot->setLanguage('de');
	$res = $domrobot->login($usr,$pwd);

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
		$recordId = $res['resData']['record'][0]['id'];
		if ($recordId != "")
			return $recordId;
		else
			throw new Exception('domain or name not found');
	} else {
		throw new Exception('connection error occured');
	}

	$res = $domrobot->logout();
}

/**
 * Setzt die IP-Adresse in den entsprechenen Nameserver-Record
 *
 * @param int $recordId enthält die unique ID des Nameserver-Records
 * @param String $ip4addr enthält die zu setzende IP-Adresse
 */
function updateRecord($recordId, $ip4addr) {
	//globale variablen abrufen
	global $usr;
	global $pwd;

	//domrobot object instanziieren und einloggen
	$domrobot = new domrobot(APIURL);
	$domrobot->setDebug(false);
	$domrobot->setLanguage('de');
	$res = $domrobot->login($usr,$pwd);

	//do update
	if ($res['code']==1000) {
		$obj = "nameserver";
		$meth = "updateRecord";
		$params = array();
		$params['id'] = $recordId;
		$params['content'] = $ip4addr;
		$res = $domrobot->call($obj,$meth,$params);
	} else {
		throw new Exception('connection error occured');
	}

	$res = $domrobot->logout();
}
?>