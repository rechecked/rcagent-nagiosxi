<?php

function rcagent_configwizard_get_api_data($endpoint, $status_url, $token, $ssl_verify, $params = array(), $json = false)
{
    // Remove SSL verification or not
    $context = array("ssl" => array("verify_peer" => false, "verify_peer_name" => false));
    if ($ssl_verify) {
        $context['ssl']['verify_peer'] = true;
        $context['ssl']['verify_peer_name'] = true;
    }

    // Setup API url
    $params['token'] = $token;
    $full_status_url = $status_url."/".$endpoint."?".http_build_query($params);

    // Do connection and get data
    $data = file_get_contents($full_status_url, false, stream_context_create($context));

    if ($json) {
        return $data;
    }
    $data = json_decode($data, true);

    return $data;
}

function rcagent_configwizard_get_os_icon($os, $platform)
{
    $icon = "rcagent.png";

    if ($os == "windows") {
        $icon = "rc-windows.png";
    } else if ($os == "linux") {
        switch ($platform) {
            case "centos":
                $icon = "rc-centos.png";
                break;
            case "ubuntu":
                $icon = "rc-ubuntu.png";
                break;
            case "debian":
                $icon = "rc-debian.png";
                break;
            case "macos":
                $icon = "rc-macos.png";
                break;
        }
    }

    return $icon;
}
