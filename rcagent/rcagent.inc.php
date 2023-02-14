<?php
//
// ReChecked Agent Config Wizard
// Copyright (c) 2023 ReChecked. All rights reserved.
//

include_once(dirname(__FILE__).'/utils.inc.php');
try {
    @include_once(dirname(__FILE__).'/../configwizardhelper.inc.php');
} catch (Exception $ex) {
    include_once('/usr/local/nagiosxi/includes/configwizards/configwizardhelper.inc.php');
}


rcagent_configwizard_init();


function rcagent_configwizard_init()
{
    $name = "rcagent";
    $args = array(
        CONFIGWIZARD_NAME => $name,
        CONFIGWIZARD_VERSION => "VERSION_ID",
        CONFIGWIZARD_TYPE => CONFIGWIZARD_TYPE_MONITORING,
        CONFIGWIZARD_DESCRIPTION => _("Monitor a host (Windows, Linux, OS X, Solaris, or AIX) using the ReChecked Agent.") . ' <div class="hide">centos rhel sles suse opensuse oracle cloudlinux ubuntu debian redhat mac</div>',
        CONFIGWIZARD_DISPLAYTITLE => _("ReChecked Agent"),
        CONFIGWIZARD_FUNCTION => "rcagent_configwizard_func",
        CONFIGWIZARD_PREVIEWIMAGE => "rcagent.png",
        CONFIGWIZARD_FILTER_GROUPS => array('nagios','windows','linux','otheros'),
        CONFIGWIZARD_REQUIRES_VERSION => 5700
    );
    register_configwizard($name, $args);
}


function rcagent_configwizard_func($mode = "", $inargs = null, &$outargs, &$result, $extra = array())
{
    $wizard_name = "rcagent";

    // Initialize return code and output
    $result = 0;
    $output = "";

    // Initialize output args
    $outargs[CONFIGWIZARD_PASSBACK_DATA] = $inargs;

    switch ($mode) {

        case CONFIGWIZARD_MODE_GETSTAGE1HTML:
            $ip_address = grab_array_var($inargs, "ip_address", "");
            $port = grab_array_var($inargs, "port", "5995");
            $token = grab_array_var($inargs, "token", "");
            $no_https = grab_array_var($inargs, "no_https", 0);
            $ssl_verify = grab_array_var($inargs, "ssl_verify", 0);

            $output = '
            <h5 class="ul">' . _('Connection Info') . '</h5>

            <p>'._('This wizard requires a running rcagent, if you don\'t have ReChecked Agent isntalled yet follow the').' <a href="https://rechecked.io/quick-start-guide/" target="_blank" rel="noreferrer noopener">'._('quick start guide').'</a> '._('to get started.').'</p>

            <table class="table table-condensed table-no-border table-auto-width table-padded">
                <tr>
                    <td class="vt"><label>' . _('Address') . ':</label></td>
                    <td>
                        <input type="text" size="40" name="ip_address" value="' . encode_form_val($ip_address) . '" class="textfield usermacro-detection form-control" autocomplete="off">
                        <div class="subtext">' . _('The IP address or FQDNS name used to connect') . '.</div>
                    </td>
                </tr>
                <tr>
                    <td class="vt"><label>' . _('Port') . ':</label></td>
                    <td>
                        <input type="text" size="5" name="port" value="' . encode_form_val($port) . '" class="textfield usermacro-detection form-control">
                        <div class="subtext">' . _('Defaults to port 5995') . '.</div>
                    </td>
                </tr>
                <tr>
                    <td class="vt"><label>' . _('Token') . ':</label></td>
                    <td>
                        <input type="password" size="15" name="token" id="token" value="' . encode_form_val($token) . '" class="textfield usermacro-detection form-control" autocomplete="off">
                        <button type="button" style="vertical-align: top;" class="btn btn-sm btn-default tt-bind btn-show-password" title="'._("Show password").'"><i class="fa fa-eye"></i></button>
                        <div class="subtext">' . _('Authentication token set in config.yml') . '.</div>
                    </td>
                </tr>
                <tr>
                    <td class="vt"></td>
                    <td class="checkbox">
                        <label>
                            <input type="checkbox" name="no_https" value="1" ' . is_checked($no_https, 1) . '>
                            ' . _("Do not use HTTPS") . '
                        </label>
                    </td>
                </tr>
                <tr>
                    <td class="vt"></td>
                    <td class="checkbox">
                        <label>
                            <input type="checkbox" name="ssl_verify" value="1" ' . is_checked($ssl_verify, 1) . '>
                            ' . _("Verify SSL certificate") . '
                        </label>
                    </td>
                </tr>
            </table>';
            break;

        case CONFIGWIZARD_MODE_VALIDATESTAGE1DATA:
            // Get variables that were passed to us
            $ip_address = grab_array_var($inargs, "ip_address", "");
            $port = grab_array_var($inargs, "port", "5995");
            $token = grab_array_var($inargs, "token", "");
            $no_https = grab_array_var($inargs, "no_https", 0);
            $ssl_verify = grab_array_var($inargs, "ssl_verify", 0);

            // Check for errors
            $errors = 0;
            $errmsg = array();

            if (have_value($ip_address) == false) {
                $errmsg[$errors++] = _("No address specified.");
            }
            if (have_value($port) == false) {
                $errmsg[$errors++] = _("No port number specified.");
            }

            // Test the connection if no errors
            if (empty($errors) && empty($hosts)) {

                $ip_address_replaced = nagiosccm_replace_user_macros($ip_address);
                $port_replaced = nagiosccm_replace_user_macros($port);
                $token_replaced = nagiosccm_replace_user_macros($token);
                $proto = $no_https ? "http" : "https";

                // Test connection
                $query_url = "{$proto}://{$ip_address}:{$port}/status/system?token=".urlencode($token);
                $query_url_replaced = "{$proto}://{$ip_address_replaced}:{$port_replaced}/status/system?token=".urlencode($token_replaced);

                // Remove SSL verification or not
                $context = array("ssl" => array("verify_peer" => false, "verify_peer_name" => false));
                if ($ssl_verify) {
                    $context['ssl']['verify_peer'] = true;
                    $context['ssl']['verify_peer_name'] = true;
                }

                // All we want to do is test if we can hit this URL.
                $raw_json = file_get_contents($query_url_replaced, false, stream_context_create($context));
                //print_r($raw_json);
                if (empty($raw_json)) {
                    $errmsg[$errors++] = _("Unable to contact server at") . " {$query_url}.";
                } else {
                    $json = json_decode($raw_json, true);
                    if (array_key_exists('status', $json) && $json['status'] == "error") {
                        $errmsg[$errors++] = $json['message'];
                    }
                }

            }

            if ($errors > 0) {
                $outargs[CONFIGWIZARD_ERROR_MESSAGES] = $errmsg;
                $result = 1;
            }
            break;

        case CONFIGWIZARD_MODE_GETSTAGE2HTML:
            // Get variables that were passed to us
            $ip_address = grab_array_var($inargs, "ip_address", "");
            $port = grab_array_var($inargs, "port", "5693");
            $token = grab_array_var($inargs, "token", "");
            $no_https = grab_array_var($inargs, "no_https", 0);
            $ssl_verify = grab_array_var($inargs, "ssl_verify", 0);

            // Get hostname from data
            $hostname = grab_array_var($inargs, 'hostname', @gethostbyaddr($ip_address));

            $rp_address = nagiosccm_replace_user_macros($ip_address);
            $rp_port = nagiosccm_replace_user_macros($port);
            $rp_token = nagiosccm_replace_user_macros($token);

            $services = grab_array_var($inargs, "services", array());
            $services_serial = grab_array_var($inargs, "services_serial", "");
            if ($services_serial) {
                $services = json_decode(base64_decode($services_serial), true);
            }

            $COMPONENT_API_URL = get_base_url(true) . "includes/configwizards/rcagent/api.php";
            $status_url = "https://{$rp_address}:{$rp_port}/status";

            $output = '<input type="hidden" name="ip_address" value="' . encode_form_val($ip_address) . '">
                       <input type="hidden" name="port" value="' . encode_form_val($port) . '">
                       <input type="hidden" name="token" value="' . encode_form_val($token) . '">
                       <input type="hidden" name="no_https" value="' . intval($no_https) . '">
                       <input type="hidden" name="ssl_verify" value="' . intval($ssl_verify) . '">';

            // Defaults
            if (empty($services)) {
                $services['cpu'] = array(
                    'check' => 1,
                    'warning' => 70,
                    'critical' => 90
                );
                $services['load'] = array(
                    'check' => 1,
                    'warning' => 10,
                    'critical' => 20
                );
                $services['mem_virtual'] = array(
                    'check' => 1,
                    'warning' => 60,
                    'critical' => 80
                );
                $services['mem_swap'] = array(
                    'check' => 1,
                    'warning' => 40,
                    'critical' => 60
                );
                $services['users'] = array(
                    'check' => 1,
                    'warning' => 6,
                    'critical' => 10
                );
            }

            // Include step 2 template code
            ob_start();
            require_once(dirname(__FILE__)."/step2.inc.php");
            $output .= ob_get_clean();

            break;

        case CONFIGWIZARD_MODE_VALIDATESTAGE2DATA:
            // Get variables that were passed to us
            $ip_address = grab_array_var($inargs, 'ip_address');
            $hostname = grab_array_var($inargs, 'hostname');
            $no_https = grab_array_var($inargs, 'no_https', 0);
            $ssl_verify = grab_array_var($inargs, 'ssl_verify', 0);
            $port = grab_array_var($inargs, 'port');
            $token = grab_array_var($inargs, 'token');
            $os = grab_array_var($inargs, 'os');
            $platform = grab_array_var($inargs, 'platform');

            // Check for errors
            $errors = 0;
            $errmsg = array();
            if (empty($hosts) && is_valid_host_name($hostname) == false) {
                $errmsg[$errors++] = "Invalid host name.";
            }

            if ($errors > 0) {
                $outargs[CONFIGWIZARD_ERROR_MESSAGES] = $errmsg;
                $result = 1;
            }
            break;

        case CONFIGWIZARD_MODE_GETSTAGE3HTML:
            // Get variables that were passed to us
            $ip_address = grab_array_var($inargs, 'ip_address');
            $hostname = grab_array_var($inargs, 'hostname');
            $port = grab_array_var($inargs, 'port');
            $token = grab_array_var($inargs, 'token');
            $no_https = grab_array_var($inargs, 'no_https', 0);
            $ssl_verify = grab_array_var($inargs, 'ssl_verify', 0);
            $os = grab_array_var($inargs, 'os');
            $platform = grab_array_var($inargs, 'platform');

            $services = grab_array_var($inargs, 'services', array());
            if (empty($services)) {
                $services_serial = grab_array_var($inargs, "services_serial", "");
                $services = json_decode(base64_decode($services_serial), true);
            }

            $output = '
            <input type="hidden" name="ip_address" value="' . encode_form_val($ip_address) . '" />
            <input type="hidden" name="hostname" value="' . encode_form_val($hostname) . '" />
            <input type="hidden" name="port" value="' . encode_form_val($port) . '" />
            <input type="hidden" name="token" value="' . encode_form_val($token) . '" />
            <input type="hidden" name="os" value="' . encode_form_val($os) . '" />
            <input type="hidden" name="platform" value="' . encode_form_val($platform) . '" />
            <input type="hidden" name="no_https" value="' . intval($no_https) . '">
            <input type="hidden" name="ssl_verify" value="' . intval($ssl_verify) . '">
            <input type="hidden" name="services_serial" value="' . base64_encode(json_encode($services)) . '" />';

            break;

        case CONFIGWIZARD_MODE_VALIDATESTAGE3DATA:
            break;

        case CONFIGWIZARD_MODE_GETFINALSTAGEHTML:
            // Get variables that were passed to us
            $ip_address = grab_array_var($inargs, 'ip_address');
            $hostname = grab_array_var($inargs, 'hostname');
            $port = grab_array_var($inargs, 'port');
            $token = grab_array_var($inargs, 'token');
            $no_https = grab_array_var($inargs, 'no_https', 0);
            $ssl_verify = grab_array_var($inargs, 'ssl_verify', 0);
            $platform = grab_array_var($inargs, 'platform');

            $services = grab_array_var($inargs, 'services', array());
            if (empty($services)) {
                $services_serial = grab_array_var($inargs, "services_serial", "");
                $services = json_decode(base64_decode($services_serial), true);
            }

            $output = '
            <input type="hidden" name="ip_address" value="' . encode_form_val($ip_address) . '" />
            <input type="hidden" name="hostname" value="' . encode_form_val($hostname) . '" />
            <input type="hidden" name="port" value="' . encode_form_val($port) . '" />
            <input type="hidden" name="token" value="' . encode_form_val($token) . '" />
            <input type="hidden" name="os" value="' . encode_form_val($os) . '" />
            <input type="hidden" name="platform" value="' . encode_form_val($platform) . '" />
            <input type="hidden" name="no_https" value="' . intval($no_https) . '">
            <input type="hidden" name="ssl_verify" value="' . intval($ssl_verify) . '">
            <input type="hidden" name="services_serial" value="' . base64_encode(json_encode($services)) . '" />';

            break;

        case CONFIGWIZARD_MODE_GETOBJECTS:
            $hostname = grab_array_var($inargs, "hostname", "");
            $ip_address = grab_array_var($inargs, "ip_address", "");
            $no_https = grab_array_var($inargs, 'no_https', 0);
            $ssl_verify = grab_array_var($inargs, 'ssl_verify', 0);
            $port = grab_array_var($inargs, "port", "");
            $token = grab_array_var($inargs, "token", "");
            $os = grab_array_var($inargs, 'os');
            $platform = grab_array_var($inargs, 'platform');
            $services_serial = grab_array_var($inargs, "services_serial", "");
            $services = json_decode(base64_decode($services_serial), true);

            // Save data for later use in re-entrance
            $meta_arr = array();
            $meta_arr["hostname"] = $hostname;
            $meta_arr["ip_address"] = $ip_address;
            $meta_arr["port"] = $port;
            $meta_arr["token"] = $token;
            $meta_arr["services"] = $services;
            save_configwizard_object_meta($wizard_name, $hostname, "", $meta_arr);

            // Escape values for check_command line
            if (function_exists('nagiosccm_replace_command_line')) {
                $token = nagiosccm_replace_command_line($token, '$');
            } else {
                $token = str_replace('!', '\!', $token);
            }

            $objs = array();

            if (!host_exists($hostname)) {
                $objs[] = array(
                    "type" => OBJECTTYPE_HOST,
                    "use" => "xiwizard_rcagent_host",
                    "host_name" => $hostname,
                    "address" => $ip_address,
                    "icon_image" => rcagent_configwizard_get_os_icon($os, $platform),
                    "statusmap_image" => rcagent_configwizard_get_os_icon($os, $platform),
                    "_xiwizard" => $wizard_name);
            }

            // Common plugin opts
            $commonopts = "-t ".escapeshellarg($token)." ";
            if ($port) {
                $commonopts .= "-P ".intval($port)." ";
            }

            // If we don't already have an array of hosts, make it
            $hostnames = array($hostname);

            foreach ($hostnames as $hostname) {
                foreach ($services as $type => $args) {
                    $pluginopts = "";
                    $pluginopts .= $commonopts;

                    $wcopts = "";
                    if (!empty($args['warning'])) {
                        $wcopts .= " -w " . escapeshellarg($args["warning"]);
                    }
                    if (!empty($args['critical'])) {
                        $wcopts .= " -c " . escapeshellarg($args["critical"]);
                    }

                    switch ($type) {

                        case "cpu":
                            if (!array_key_exists('check', $args)) { break; }

                            $pluginopts .= "-e cpu/percent $wcopts";

                            $objs[] = array(
                                "type" => OBJECTTYPE_SERVICE,
                                "host_name" => $hostname,
                                "service_description" => "CPU Usage",
                                "use" => "xiwizard_rcagent_service",
                                "check_command" => "check_xi_rcagent!" . $pluginopts,
                                "_xiwizard" => $wizard_name);
                            break;

                        case "load":
                            if ($os == "windows") { break; }
                            if (!array_key_exists('check', $args)) { break; }
                            
                            $pluginopts .= "-e load $wcopts";

                            if ($args['against'] != 'load1') {
                                $pluginopts .= "-q " . escapeshellarg("against=" . $args['against']);
                            }

                            $objs[] = array(
                                "type" => OBJECTTYPE_SERVICE,
                                "host_name" => $hostname,
                                "service_description" => "Current Load",
                                "use" => "xiwizard_rcagent_service",
                                "check_command" => "check_xi_rcagent!" . $pluginopts,
                                "_xiwizard" => $wizard_name);
                            break;

                        case "users":
                            if (!array_key_exists('check', $args)) { break; }

                            $pluginopts .= "-e system/users $wcopts";

                            $objs[] = array(
                                "type" => OBJECTTYPE_SERVICE,
                                "host_name" => $hostname,
                                "service_description" => "Current Users",
                                "use" => "xiwizard_rcagent_service",
                                "check_command" => "check_xi_rcagent!" . $pluginopts,
                                "_xiwizard" => $wizard_name);
                            break;

                        case "mem_virtual":
                            if (!array_key_exists('check', $args)) { break; }

                            $pluginopts .= "-e memory/virtual $wcopts";

                            $objs[] = array(
                                "type" => OBJECTTYPE_SERVICE,
                                "host_name" => $hostname,
                                "service_description" => "Memory Usage",
                                "use" => "xiwizard_rcagent_service",
                                "check_command" => "check_xi_rcagent!" . $pluginopts,
                                "_xiwizard" => $wizard_name);
                            break;

                        case "mem_swap":
                            if (!array_key_exists('check', $args)) { break; }

                            $pluginopts .= "-e memory/swap $wcopts";

                            $objs[] = array(
                                "type" => OBJECTTYPE_SERVICE,
                                "host_name" => $hostname,
                                "service_description" => "Swap Usage",
                                "use" => "xiwizard_rcagent_service",
                                "check_command" => "check_xi_rcagent!" . $pluginopts,
                                "_xiwizard" => $wizard_name);
                            break;

                        case "disk":
                            foreach ($args as $i => $disk) {
                                if (!array_key_exists('check', $disk)) { continue; }

                                $opts = "-q ".escapeshellarg("path=".$disk['path']);

                                $pluginopts .= "-e disk $opts $wcopts";

                                $objs[] = array(
                                    "type" => OBJECTTYPE_SERVICE,
                                    "host_name" => $hostname,
                                    "service_description" => "Disk Usage - ".$disk['path'],
                                    "use" => "xiwizard_rcagent_service",
                                    "check_command" => "check_xi_rcagent!" . $pluginopts,
                                    "_xiwizard" => $wizard_name);
                            }
                            break;

                        case "inodes":
                            foreach ($args as $i => $inodes) {
                                if (!array_key_exists('check', $inodes)) { continue; }

                                $opts = "-q ".escapeshellarg("path=".$inodes['path']);

                                $pluginopts .= "-e disk/inodes $opts $wcopts";

                                $objs[] = array(
                                    "type" => OBJECTTYPE_SERVICE,
                                    "host_name" => $hostname,
                                    "service_description" => "Inode Usage - ".$inodes['path'],
                                    "use" => "xiwizard_rcagent_service",
                                    "check_command" => "check_xi_rcagent!" . $pluginopts,
                                    "_xiwizard" => $wizard_name);
                            }
                            break;

                        case "network":
                            foreach ($args as $i => $net) {
                                if (!array_key_exists('check', $net)) { continue; }

                                $opts = "-u MB -q delta=1 -q ".escapeshellarg("name=".$net['name']);
                                if (!empty($net['against'])) {
                                    $opts .= " -q ".escapeshellarg("against=".$net['against']);
                                }

                                $pluginopts .= "-e network $opts $wcopts";

                                $objs[] = array(
                                    "type" => OBJECTTYPE_SERVICE,
                                    "host_name" => $hostname,
                                    "service_description" => "Network Interface - ".$net['name'],
                                    "use" => "xiwizard_rcagent_service",
                                    "check_command" => "check_xi_rcagent!" . $pluginopts,
                                    "_xiwizard" => $wizard_name);
                            }
                            break;

                        case "services":
                            foreach ($args as $i => $service) {
                                if (!array_key_exists('check', $service) || empty($service['desc'])) {
                                    continue;
                                }
                                $opts = "{$pluginopts} -e services -q " . escapeshellarg("name=".$service['name']);
                                $opts .= " -q " . escapeshellarg("expected=".$service['expected']);

                                $description = str_replace(array('\\', ','), array('/', ' '), $service['desc']);

                                $objs[] = array(
                                    "type" => OBJECTTYPE_SERVICE,
                                    "host_name" => $hostname,
                                    "service_description" => $description,
                                    "use" => "xiwizard_rcagent_service",
                                    "check_command" => "check_xi_rcagent!" . $opts,
                                    "_xiwizard" => $wizard_name);
                            }
                            break;

                        case "processes":
                            foreach ($args as $i => $proc) {
                                if (!array_key_exists('check', $proc) || empty($proc['desc'])) {
                                    continue;
                                }
                                
                                $wcopts = "";
                                if (!empty($args['warning'])) {
                                    $wcopts .= " -w " . escapeshellarg($proc["warning"]);
                                }
                                if (!empty($args['critical'])) {
                                    $wcopts .= " -c " . escapeshellarg($proc["critical"]);
                                }

                                $opts = "{$pluginopts} -e processes -q " . escapeshellarg("name=".$proc['name']);
                                $opts .= " {$wcopts}";

                                $description = str_replace(array('\\', ','), array('/', ' '), $proc['desc']);

                                $objs[] = array(
                                    "type" => OBJECTTYPE_SERVICE,
                                    "host_name" => $hostname,
                                    "service_description" => $description,
                                    "use" => "xiwizard_rcagent_service",
                                    "check_command" => "check_xi_rcagent!" . $opts,
                                    "_xiwizard" => $wizard_name);
                            }
                            break;

                        case "plugins":
                            foreach ($args as $i => $plugin) {
                                if (!array_key_exists('check', $plugin) || empty($plugin['desc'])) {
                                    continue;
                                }
                                $opts = "{$pluginopts} -p " . escapeshellarg($plugin['plugin']);

                                if (!empty($plugin['args'])) {
                                    $opts .= " --arg=" . escapeshellarg($plugin['args']);
                                }

                                $description = str_replace(array('\\', ','), array('/', ' '), $plugin['desc']);

                                $objs[] = array(
                                    "type" => OBJECTTYPE_SERVICE,
                                    "host_name" => $hostname,
                                    "service_description" => $description,
                                    "use" => "xiwizard_rcagent_service",
                                    "check_command" => "check_xi_rcagent!" . $opts,
                                    "_xiwizard" => $wizard_name);
                            }
                            break;

                        default:
                            break;
                    }
                }
            }

            // Return the object definitions to the wizard
            $outargs[CONFIGWIZARD_NAGIOS_OBJECTS] = $objs;
            break;

        default:
            break;
    }

    return $output;
}
