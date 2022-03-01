<?php

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\MIME\Email;

$PEPPOL_NS = [
	'S12' => 'http://www.w3.org/2003/05/soap-envelope',
	'wsu' => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd',
	'eb' => 'http://docs.oasis-open.org/ebxml-msg/ebms/v3.0/nx/core/200704/',
	'wsse' => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-sectext-1.0.xsd',
	'xenc' => 'http://www.w3.org/2001/04/xmlenc#',
	'ds' => 'http://www.w3.org/2000/09/xmlsig#',
	'wsse11' => 'http://docs.oasis-open.org/wss/oasis-wss-security-sectext-1.1.xsd',
	'xenc11' => 'http://www.w3.org/2009/xmlenc11#',
	'ec' => 'http://www.w3.org/2001/10/xml-exc-c14n#',
];

if(!function_exists('str_starts_with')){
	function str_starts_with($haystack, $needle){
		$length = strlen($needle);
		return substr($haystack, 0, $length) === $needle;
	}
}

function getMimeBoundary($contentType){
	return explode('"',explode('boundary=',$contentType)[1])[1];
}

function getMimeParts($boundary, $message){
	return array_filter(explode($boundary, $message), function($m) {
		return trim($m) != '--';
	});
}

function getInput(){
	return file_get_contents('php://input');
}

function getHeader($headerField){
	return($_SERVER[$headerField]);
}

function parseMimePart($mimePart){
	$messagePart = [];
	$messagePart['RAW'] = $mimePart;
	$messagePart['HEADERS'] = [];
	$messagePart['CONTENT'] = '';	
	$parted = explode("\n",$mimePart);
	foreach($parted as $part){
		if(isKnownHeader($part)){
			$pos = strpos($part,':');
			$key = substr($part, 0, $pos);
			$value = substr($part, $pos+1);
			if(!$messagePart['HEADERS'][$key]){
				$messagePart['HEADERS'][$key] = trim($value);
			} else {
				error_log($key . ' already present in headers. redeclaration is ignored');
			}
		} else {
			if($messagePart['HEADERS']['Content-Type'] && str_starts_with($messagePart['HEADERS']['Content-Type'], "application/soap+xml") || str_starts_with($messagePart['HEADERS']['Content-Type'], "application/xml")){
				$dom = DOMDocument::loadXML($part);
				if($dom){
					$xpath = new DOMXPath($dom);
					loadNamespaces($xpath);
					$soapHeaderQuery = "//S12:Header";
					$soapHeader = $xpath->evaluate($soapHeaderQuery);
					$soapBodyQuery = "//S12:Body";
					$soapBody = $xpath->evaluate($soapBodyQuery);
					$messagePart['Content'] = ['Header' => $soapHeader, 'Body' => $soapBody];
				} else {
					error_log('failed to parse xml');
				}
			} else {
				if($messagePart['HEADERS']['Content-Type'] && str_starts_with($messagePart['HEADERS']['Content-Type'], "application/octet-stream")){
					$messagePart['Content'] = $part;
					if($messagePart['Content']) {
						print('managed to unzip the content' . json_encode($part) . '<br>');
					} else {
						print('failed to unzip the content' . '<br>');
					}
				}
			}
		}
	}
}

function loadNamespaces($xpath){
	foreach($PEPPOL_NS as $prefix => $namespace){
		$xpath->registerNamespace($prefix, $namespace);
	}
}

function isKnownHeader($header){
	$knownHeaders = [
		'Content-Type',
		'Content-Transfer-Encoding',
		'Content-Description',
		'Content-ID'
	];
	foreach($knownHeaders as $h){
		if(str_starts_with(trim($header), $h)){
			return true;
		}
	}
	return false;
}

$input = getInput();
$mimeBoundary = getMimeBoundary(getHeader('CONTENT_TYPE'));
$mimeParts = getMimeParts($mimeBoundary,$input);
foreach($mimeParts as $part){
	parseMimePart($part);
}
