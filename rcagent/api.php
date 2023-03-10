<?php

include_once(dirname(__FILE__).'/utils.inc.php');
header('Content-type: application/json; charset=utf-8');

$status_url = array_key_exists("status_url", $_POST) ? $_POST['status_url'] : "";
$token = array_key_exists("token", $_POST) ? $_POST['token'] : "";
$ssl_verify = array_key_exists("ssl_verify", $_POST) ? $_POST['ssl_verify'] : 0;
$os = array_key_exists("os", $_POST) ? $_POST['os'] : "";

// Validate data
if (empty($status_url) || empty($token) || empty($os)) {
	$data = array(
		"error" => "Must enter all values."
	);
	echo json_encode($data);
	exit;
}

// Do API calls
$data = array();

// CPU
$cpu = rcagent_configwizard_get_api_data("cpu/percent", $status_url, $token, $ssl_verify);
$data['cpuUsage'] = $cpu['percent'][0];

// Memory
$virt = rcagent_configwizard_get_api_data("memory/virtual", $status_url, $token, $ssl_verify);
$data['memVirtual'] = $virt;
$swap = rcagent_configwizard_get_api_data("memory/swap", $status_url, $token, $ssl_verify);
$data['memSwap'] = $swap;

// Load
if ($os != "windows") {
    $data['load'] = rcagent_configwizard_get_api_data("load", $status_url, $token, $ssl_verify);
}

echo json_encode($data);