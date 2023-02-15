<?php
include_once(dirname(__FILE__).'/utils.inc.php');

// Get OS and other system data
$system = rcagent_configwizard_get_api_data("system", $status_url, $rp_token, $ssl_verify);
$users = rcagent_configwizard_get_api_data("system/users", $status_url, $rp_token, $ssl_verify);
$plugins = rcagent_configwizard_get_api_data("plugins", $status_url, $rp_token, $ssl_verify);

// Get disk info
$disks = rcagent_configwizard_get_api_data("disk", $status_url, $rp_token, $ssl_verify);
$inodes = rcagent_configwizard_get_api_data("disk/inodes", $status_url, $rp_token, $ssl_verify);

// Set defaults for disk/inodes
if (!array_key_exists('disk', $services)) {
    foreach ($disks as $disk) {
        $services['disk'][$disk['path']] = array(
            'warning' => 70,
            'critical' => 90
        );
        $services['inodes'][$disk['path']] = array(
            'warning' => 70,
            'critical' => 90
        );
        if ($disk['path'] == '/' || $disk['path'] == 'C:') {
            $services['disk'][$disk['path']]['check'] = 1;
            if ($system['os'] != "windows") {
                $services['inodes'][$disk['path']]['check'] = 1;
            }
        }
    }
}

// Get network info
$network = rcagent_configwizard_get_api_data("network", $status_url, $rp_token, $ssl_verify);
if (!array_key_exists('network', $services)) {
    foreach ($network as $net) {
        $services['network'][$net['name']] = array(
            'warning' => 50,
            'critical' => 100
        );
        if (!empty($net['bytesSent']) && !empty($net['bytesRecv']) && $net['name'] != "lo") {
            $services['network'][$net['name']]['check'] = 1;
        }
    }
}

// Get services
$servicesAvailable = rcagent_configwizard_get_api_data("services", $status_url, $rp_token, $ssl_verify);

// Default services
if (!array_key_exists('services', $services)) {
    $services['services'] = array(
        array(
            'check' => 0,
            'desc' => "",
            'name' => "",
            'expected' => ""
        )
    );
}

// Get processes and process them
$processes = array();
$procs = rcagent_configwizard_get_api_data("processes", $status_url, $rp_token, $ssl_verify); 
foreach ($procs['processes'] as $p) {
    @$processes[$p['name']]++;
}

// Default processes
if (!array_key_exists('processes', $services)) {
    $services['processes'] = array(
        array(
            'check' => 0,
            'desc' => "",
            'name' => "",
            'warning' => "",
            'critical' => ""
        )
    );
}

// Default plugins
if (!array_key_exists('plugins', $services)) {
    $services['plugins'] = array(
        array(
            'check' => 0,
            'desc' => "",
            'plugin' => "",
            'args' => ""
        )
    );
}

?>
<?php if (!is_dev_mode()) { ?>
<script src="https://unpkg.com/vue@3.2.47/dist/vue.global.js"></script>
<?php } else { ?>
<script src="<?php echo get_base_url(true); ?>includes/configwizards/rcagent/js/vue.3.2.47.prod.js"></script>
<?php } ?>

<style type="text/css">
.thresholds .input-group { margin-right: 10px; }
.thresholds input { height: 30px; width: 60px; }
.well.info { padding: 10px; margin: 0; }
.well.info span { margin-right: 20px; }
input[type=checkbox] { margin: 0; }
.modal-mask {
  position: fixed;
  z-index: 9998;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, .5);
  display: table;
  transition: opacity .3s ease;
}
.modal-wrapper {
  display: table-cell;
  vertical-align: middle;
}
</style>

<div id="wizard" style="margin-bottom: 50px;">

    <h5 class="ul"><?php echo _("Host Information"); ?></h5>
    <table class="table table-condensed table-no-border table-auto-width table-padded">
        <tr>
            <td class="vt"><label><?php echo _("Connection"); ?>:</label></td>
            <td>
                <input type="text" size="20" :value="ipAddress" class="textfield form-control" disabled> :
                <input type="text" size="5" :value="port" class="textfield form-control" disabled>
            </td>
        </tr>
        <tr>
            <td class="vt"><label><?php echo _("Host Name"); ?>:</label></td>
            <td>
                <input type="text" size="20" name="hostname" id="hostname" :value="hostname" class="textfield form-control">
                <div class="subtext"><?php echo _("The hostname you'd like to have associated with this host"); ?></div>
            </td>
        </tr>
        <tr>
            <td class="vt"><label><?php echo _("System"); ?>:</label></td>
            <td>
                <div style="margin-bottom: 5px;"><?php echo ucfirst($system['os']); ?> (<?php echo $system['platform']; ?>)</div>
                <img src="<?php echo nagioscore_get_ui_url() . "images/logos/" . rcagent_configwizard_get_os_icon($system['os'], $system['platform']); ?>" />
                <input type="hidden" name="os" value="<?php echo encode_form_val($system['os']); ?>" />
                <input type="hidden" name="platform" value="<?php echo encode_form_val($system['platform']); ?>" />
            </td>
        </tr>
    </table>

    <h5 class="ul"><?php echo _("System Checks"); ?></h5>
    <table class="table table-no-border table-auto-width table-padded">
        <tr>
            <td>
                <input type="checkbox" class="checkbox" name="services[cpu][check]" value="1" id="checkCpu" <?php echo is_checked(grab_array_var($services['cpu'], "check"), 1); ?> />
            </td>
            <td>
                <label for="checkCpu" style="font-weight: normal;">
                    <b><?php echo _('CPU Usage'); ?></b>
                    <div><?php echo _('Check the average usage on all CPUs'); ?>.</div>
                </label>
            </td>
            <td>
                <div class="well info" style="width: 250px;">
                    <?php echo _('Current Usage'); ?>: <b v-if="cpuUsage !== null">{{ cpuUsage.toFixed(2) }}%</b><b v-else>-</b>
                </div>
            </td>
            <td class="form-inline thresholds">
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('error.png'); ?>" class="tt-bind" title="<?php echo _('Warning Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[cpu][warning]" value="<?php echo encode_form_val($services['cpu']['warning']); ?>" class="form-control" />
                    <div class="input-group-addon">
                        %
                    </div>
                </div>
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('critical_small.png'); ?>" class="tt-bind" title="<?php echo _('Critical Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[cpu][critical]" value="<?php echo encode_form_val($services['cpu']['critical']); ?>" class="form-control" />
                    <div class="input-group-addon">
                        %
                    </div>
                </div>
            </td>
        </tr>
        <tr v-if="os !== 'windows'">
            <td>
                <input type="checkbox" class="checkbox" name="services[load][check]" value="1" id="load" <?php echo is_checked(grab_array_var($services['load'], "check"), 1); ?> />
            </td>
            <td>
                <label for="load" style="font-weight: normal;">
                    <b><?php echo _('Load'); ?></b>
                    <div><?php echo _('Check the load on the system'); ?>.</div>
                </label>
            </td>
            <td>
                <div class="well info" style="width: 250px;">
                    <?php echo _('Current Load'); ?>: <b v-if="load">{{ getLoad() }}</b><b v-else>-</b>
                </div>
            </td>
            <td class="form-inline thresholds">
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('error.png'); ?>" class="tt-bind" title="<?php echo _('Warning Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[load][warning]" value="<?php echo encode_form_val($services['load']['warning']); ?>" class="form-control" />
                </div>
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('critical_small.png'); ?>" class="tt-bind" title="<?php echo _('Critical Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[load][critical]" value="<?php echo encode_form_val($services['load']['critical']); ?>" class="form-control" />
                </div>
                <div class="input-group">
                    <div class="input-group-addon">
                        <?php echo _('Check Against'); ?>
                    </div>
                    <select name="services[load][against]" class="form-control">
                        <option selected>load1</option>
                        <option>load5</option>
                        <option>load15</option>
                    </select>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <input type="checkbox" class="checkbox" name="services[mem_virtual][check]" value="1" id="mem_virtual" <?php echo is_checked(grab_array_var($services['mem_virtual'], "check"), 1); ?> />
            </td>
            <td>
                <label for="mem_virtual" style="font-weight: normal;">
                    <b><?php echo _('Memory Usage'); ?></b>
                    <div><?php echo _('Check the memory usage on the system'); ?>.</div>
                </label>
            </td>
            <td>
                <div class="well info" style="width: 250px;">
                    <?php echo _('Current Used'); ?>: <b v-if="memVirtual">{{ memVirtual.usedPercent.toFixed(2) }}%</b><b v-else>-</b>
                </div>
            </td>
            <td class="form-inline thresholds">
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('error.png'); ?>" class="tt-bind" title="<?php echo _('Warning Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[mem_virtual][warning]" value="<?php echo encode_form_val($services['mem_virtual']['warning']); ?>" class="form-control" />
                    <div class="input-group-addon">
                        %
                    </div>
                </div>
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('critical_small.png'); ?>" class="tt-bind" title="<?php echo _('Critical Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[mem_virtual][critical]" value="<?php echo encode_form_val($services['mem_virtual']['critical']); ?>" class="form-control" />
                    <div class="input-group-addon">
                        %
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <input type="checkbox" class="checkbox" name="services[mem_swap][check]" value="1" id="mem_swap" <?php echo is_checked(grab_array_var($services['mem_swap'], "check"), 1); ?> />
            </td>
            <td>
                <label for="mem_swap" style="font-weight: normal;">
                    <b><?php echo _('Swap Usage'); ?></b>
                    <div><?php echo _('Check the swap usage on the system'); ?>.</div>
                </label>
            </td>
            <td>
                <div class="well info" style="width: 250px;">
                    <?php echo _('Current Used'); ?>: <b v-if="memSwap">{{ memSwap.usedPercent.toFixed(2) }}%</b><b v-else>-</b>
                </div>
            </td>
            <td class="form-inline thresholds">
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('error.png'); ?>" class="tt-bind" title="<?php echo _('Warning Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[mem_swap][warning]" value="<?php echo encode_form_val($services['mem_swap']['warning']); ?>" class="form-control" />
                    <div class="input-group-addon">
                        %
                    </div>
                </div>
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('critical_small.png'); ?>" class="tt-bind" title="<?php echo _('Critical Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[mem_swap][critical]" value="<?php echo encode_form_val($services['mem_swap']['critical']); ?>" class="form-control" />
                    <div class="input-group-addon">
                        %
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <input type="checkbox" class="checkbox" name="services[users][check]" value="1" id="users" <?php echo is_checked(grab_array_var($services['users'], "check"), 1); ?> />
            </td>
            <td>
                <label for="users" style="font-weight: normal;">
                    <b><?php echo _('User Count'); ?></b>
                    <div><?php echo _('Check the current users on the system'); ?>.</div>
                </label>
            </td>
            <td>
                <div class="well info" style="width: 250px;">
                    <?php echo _('Current Users'); ?>: <b><?php echo count($users); ?></b>
                </div>
            </td>
            <td class="form-inline thresholds">
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('error.png'); ?>" class="tt-bind" title="<?php echo _('Warning Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[users][warning]" value="<?php echo encode_form_val($services['users']['warning']); ?>" class="form-control" />
                </div>
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('critical_small.png'); ?>" class="tt-bind" title="<?php echo _('Critical Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[users][critical]" value="<?php echo encode_form_val($services['users']['critical']); ?>" class="form-control" />
                </div>
            </td>
        </tr>
    </table>
    
    <h5 class="ul"><?php echo _("Disk Checks"); ?></h5>
    <table class="table table-no-border table-auto-width table-padded">
    <?php foreach ($disks as $disk) { ?>
        <tr>
            <td>
                <input type="checkbox" class="checkbox" name="services[disk][<?php echo encode_form_val($disk['path']); ?>][check]" value="1" id="disk-path-<?php echo encode_form_val($disk['path']); ?>" <?php echo is_checked(grab_array_var($services['disk'][$disk['path']], "check"), 1); ?> />
            </td>
            <td>
                <label for="disk-path-<?php echo encode_form_val($disk['path']); ?>">
                    Disk: <?php echo $disk['path']; ?>
                </label>
                <input type="hidden" name="services[disk][<?php echo encode_form_val($disk['path']); ?>][path]" value="<?php echo encode_form_val($disk['path']); ?>" />
            </td>
            <td>
                <div class="well info">
                    <span>
                        Usage: <b><?php echo round($disk['usedPercent'], 2); ?>%</b>
                    </span>
                    <?php
                    if ($system['os'] == "linux") {
                        $i = array();
                        foreach ($inodes as $a) {
                            if ($a['path'] == $disk['path']) {
                                $i = $a;
                            }
                        }
                    ?>
                    <span>
                        Inode Usage: <b><?php echo round($i['usedPercent'], 2); ?>%</b>
                    </span>
                    <?php } ?>
                </div>
            </td>
            <td class="form-inline thresholds">
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('error.png'); ?>" class="tt-bind" title="<?php echo _('Warning Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[disk][<?php echo encode_form_val($disk['path']); ?>][warning]" value="<?php echo encode_form_val($services['disk'][$disk['path']]['warning']); ?>" class="form-control" />
                    <div class="input-group-addon">
                        %
                    </div>
                </div>
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('critical_small.png'); ?>" class="tt-bind" title="<?php echo _('Critical Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[disk][<?php echo encode_form_val($disk['path']); ?>][critical]" value="<?php echo encode_form_val($services['disk'][$disk['path']]['critical']); ?>" class="form-control" />
                    <div class="input-group-addon">
                        %
                    </div>
                </div>
            </td>
            <?php if ($system['os'] == "linux") { ?>
            <td>
                <input type="checkbox" class="checkbox" name="services[inodes][<?php echo $disk['path']; ?>][check]" value="1" id="inodes-path-<?php echo encode_form_val($disk['path']); ?>" <?php echo is_checked(grab_array_var($services['disk'][$disk['path']], "check"), 1); ?> />
            </td>
            <td>
                <label style="font-weight: normal;" for="inodes-path-<?php echo encode_form_val($disk['path']); ?>">
                    Inode Check
                </label>
                <input type="hidden" name="services[inodes][<?php echo encode_form_val($disk['path']); ?>][path]" value="<?php echo encode_form_val($disk['path']); ?>" />
            </td>
            <td class="form-inline thresholds">
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('error.png'); ?>" class="tt-bind" title="<?php echo _('Warning Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[inodes][<?php echo encode_form_val($disk['path']); ?>][warning]" value="<?php echo encode_form_val($services['inodes'][$disk['path']]['warning']); ?>" class="form-control" />
                    <div class="input-group-addon">
                        %
                    </div>
                </div>
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('critical_small.png'); ?>" class="tt-bind" title="<?php echo _('Critical Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[inodes][<?php echo encode_form_val($disk['path']); ?>][critical]" value="<?php echo encode_form_val($services['inodes'][$disk['path']]['critical']); ?>" class="form-control" />
                    <div class="input-group-addon">
                        %
                    </div>
                </div>
            </td>
            <?php } ?>
        </tr>
    <?php } ?>
    </table>

    <h5 class="ul"><?php echo _("Network Checks"); ?></h5>
    <p><?php echo _('Network checks warning/critical values check against total traffic in and out. This can be changed by selecting check against value.'); ?></p>
    <table class="table table-no-border table-auto-width table-padded">
        <?php foreach ($network as $net) { ?>
        <tr>
            <td>
                <input type="checkbox" class="checkbox" name="services[network][<?php echo encode_form_val($net['name']); ?>][check]" value="1" id="net-<?php echo encode_form_val($net['name']); ?>" <?php echo is_checked(grab_array_var($services['network'][$net['name']], "check"), 1); ?> />
            </td>
            <td>
                <label style="font-weight: normal;" for="net-<?php echo encode_form_val($net['name']); ?>">
                    <b>Interface: <?php echo $net['name']; ?></b>
                    <div><?php foreach ($net['addrs'] as $addr) { echo $addr['addr']." "; } ?></div>
                </label>
                <input type="hidden" name="services[network][<?php echo encode_form_val($net['name']); ?>][name]" value="<?php echo encode_form_val($net['name']); ?>" />
            </td>
            <td class="form-inline thresholds">
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('error.png'); ?>" class="tt-bind" title="<?php echo _('Warning Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[network][<?php echo encode_form_val($net['name']); ?>][warning]" value="<?php echo encode_form_val($services['network'][$net['name']]['warning']); ?>" style="width: 60px;" class="form-control" />
                    <div class="input-group-addon">
                        MB/s
                    </div>
                </div>
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('critical_small.png'); ?>" class="tt-bind" title="<?php echo _('Critical Threshold'); ?>">
                    </div>
                    <input type="text" size="1" name="services[network][<?php echo encode_form_val($net['name']); ?>][critical]" value="<?php echo encode_form_val($services['network'][$net['name']]['critical']); ?>" style="width: 60px;" class="form-control" />
                    <div class="input-group-addon">
                        MB/s
                    </div>
                </div>
                <div class="input-group">
                    <div class="input-group-addon">
                        <?php echo _('Check Against'); ?>
                    </div>
                    <select name="services[network][<?php echo encode_form_val($net['name']); ?>][against]" style="width: 80px;" class="form-control">
                        <option value="" selected>total</option>
                        <option value="in">in</option>
                        <option value="out">out</option>
                    </select>
                </div>
            </td>
        </tr>
        <?php } ?>
    </table>

    <h5 class="ul"><?php echo _("Service Checks"); ?></h5>
    <table class="table table-no-border table-auto-width table-padded">
        <tr>
            <th></th>
            <th>Service Description</th>
            <th>Service Name</th>
            <th>Expected Status</th>
        </tr>
        <tr v-for="(service, i) in services">
            <td>
                <input type="checkbox" class="checkbox" :name="'services[services]['+i+'][check]'" :checked="service.check" value="1" />
            </td>
            <td>
                <input style="width: 240px;" class="form-control" :name="'services[services]['+i+'][desc]'" v-model="service.desc" />
            </td>
            <td>
                <div class="input-group">
                    <input class="form-control" :name="'services[services]['+i+'][name]'" v-model="service.name" />
                    <div class="input-group-addon">
                        <a @click="showSelectService(i)"><?php echo _('Select Service'); ?></a>
                    </div>
                </div>
            </td>
            <td>
                <input style="width: 120px;" class="form-control" :name="'services[services]['+i+'][expected]'" v-model="service.expected" />
            </td>
        </tr>
    </table>
    <div style="margin-bottom: 20px;">
        <a @click="addService"><?php echo _('Add Service Check'); ?></a>
    </div>

    <h5 class="ul"><?php echo _("Process Checks"); ?></h5>
    <table class="table table-no-border table-auto-width table-padded">
        <tr>
            <th></th>
            <th>Service Description</th>
            <th>Process Name</th>
            <th>Process Counts</th>
        </tr>
        <tr v-for="(process, i) in processes">
            <td>
                <input type="checkbox" class="checkbox" :name="'services[processes]['+i+'][check]'" :checked="process.check" value="1" />
            </td>
            <td>
                <input style="width: 240px;" class="form-control" :name="'services[processes]['+i+'][desc]'" v-model="process.desc" />
            </td>
            <td>
                <div class="input-group">
                    <input class="form-control" :name="'services[processes]['+i+'][name]'" v-model="process.name" />
                    <div class="input-group-addon">
                        <a @click="showSelectProcess(i)"><?php echo _('Select Process'); ?></a>
                    </div>
                </div>
            </td>
            <td class="form-inline thresholds">
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('error.png'); ?>" class="tt-bind" title="<?php echo _('Warning Threshold'); ?>">
                    </div>
                    <input type="text" size="1" :name="'services[processes]['+i+'][warning]'" v-model="process.warning" style="width: 50px;" class="form-control" />
                </div>
                <div class="input-group">
                    <div class="input-group-addon">
                        <img src="<?php echo theme_image('critical_small.png'); ?>" class="tt-bind" title="<?php echo _('Critical Threshold'); ?>">
                    </div>
                    <input type="text" size="1" :name="'services[processes]['+i+'][critical]'" v-model="process.critical" style="width: 50px;" class="form-control" />
                </div>
            </td>
        </tr>
    </table>
    <div style="margin-bottom: 20px;">
        <a @click="addProcess"><?php echo _('Add Process Check'); ?></a>
    </div>

    <h5 class="ul"><?php echo _("Plugins"); ?></h5>
    <table class="table table-no-border table-auto-width table-padded">
        <tr>
            <th></th>
            <th>Service Description</th>
            <th>Plugin</th>
            <th>Plugin Arguments</th>
        </tr>
        <tr v-for="(plugin, i) in plugins">
            <td>
                <input type="checkbox" class="checkbox" :name="'services[plugins]['+i+'][check]'" :checked="plugin.check" value="1" />
            </td>
            <td>
                <input style="width: 240px;" class="form-control" :name="'services[plugins]['+i+'][desc]'" v-model="plugin.desc" />
            </td>
            <td>
                <div class="input-group">
                    <input class="form-control" :name="'services[plugins]['+i+'][plugin]'" v-model="plugin.plugin" />
                    <div class="input-group-addon">
                        <a @click="showSelectPlugin(i)"><?php echo _('Select Plugin'); ?></a>
                    </div>
                </div>
            </td>
            <td>
                <input style="width: 440px;" class="form-control" :name="'services[plugins]['+i+'][args]'" v-model="plugin.args" />
            </td>
        </tr>
    </table>
    <a @click="addPlugin"><?php echo _('Add Plugin'); ?></a>

    <div v-if="showPluginsModal">
        <transition name="modal">
            <div class="modal-mask">
                <div class="modal-wrapper">
                    <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <button type="button" class="close" @click="showPluginsModal=false">
                              <span aria-hidden="true">&times;</span>
                            </button>
                            <h4 class="modal-title">Select Plugin</h4>
                          </div>
                          <div class="modal-body">
                            <div v-if="pluginsAvailable.length > 0 ">
                                <select style="width: 100%;" v-model="selectedPlugin" class="form-control">
                                    <option v-for="p in pluginsAvailable" :value="p">{{ p }}</option>
                                </select>
                            </div>
                            <div v-else>
                                Could not find any plugins on rcagent. Check to see if there are plugins in the plugins folder.
                            </div>
                          </div>
                            <div class="modal-footer">
                                <button type="button" @click="addSelectedPlugin" class="btn btn-sm btn-primary">Select Plugin</button>
                                <button type="button" class="btn btn-sm btn-default" @click="showPluginsModal=false">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>

    <div v-if="showServiceModal">
        <transition name="modal">
            <div class="modal-mask">
                <div class="modal-wrapper">
                    <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <button type="button" class="close" @click="showServiceModal=false">
                              <span aria-hidden="true">&times;</span>
                            </button>
                            <h4 class="modal-title">Select Service Name</h4>
                          </div>
                          <div class="modal-body">
                            <select style="width: 100%;" v-model="selectedService" class="form-control">
                                <option v-for="s in servicesAvailable" :value="s.name">{{ s.name }} ({{ s.status }})</option>
                            </select>
                          </div>
                            <div class="modal-footer">
                                <button type="button" @click="addSelectedService" class="btn btn-sm btn-primary">Select Service</button>
                                <button type="button" class="btn btn-sm btn-default" @click="showServiceModal=false">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>

    <div v-if="showProcessModal">
        <transition name="modal">
            <div class="modal-mask">
                <div class="modal-wrapper">
                    <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <button type="button" class="close" @click="showProcessModal=false">
                              <span aria-hidden="true">&times;</span>
                            </button>
                            <h4 class="modal-title">Select Process</h4>
                          </div>
                          <div class="modal-body">
                            <select style="width: 100%;" v-model="selectedProcess" class="form-control">
                                <option v-for="(num, name) in processesAvailable" :value="name">{{ name }} ({{ num }} running)</option>
                            </select>
                          </div>
                            <div class="modal-footer">
                                <button type="button" @click="addSelectedProcess" class="btn btn-sm btn-primary">Select Process</button>
                                <button type="button" class="btn btn-sm btn-default" @click="showProcessModal=false">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>

</div>

<script type="text/javascript">
const API_URL = "<?php echo $COMPONENT_API_URL; ?>";
const { createApp } = Vue;

createApp({
    data() {
        return {
            ipAddress: "<?php echo encode_form_val($ip_address); ?>",
            port: "<?php echo encode_form_val($port); ?>",
            hostname: "<?php echo encode_form_val($hostname ? $hostname : $system['hostname']); ?>",
            os: "<?php echo encode_form_val($system['os']); ?>",
            cpuUsage: null,
            load: null,
            memVirtual: null,
            memSwap: null,
            services: [],
            processes: [],
            showPluginsModal: false,
            showServiceModal: false,
            showProcessModal: false,
            services: <?php echo json_encode($services['services']); ?>,
            servicesAvailable: <?php echo json_encode($servicesAvailable); ?>,
            processes: <?php echo json_encode($services['processes']); ?>,
            processesAvailable: <?php echo json_encode($processes); ?>,
            plugins: <?php echo json_encode($services['plugins']); ?>,
            pluginsAvailable: <?php echo json_encode($plugins['plugins']); ?>,
            spi: 0,
            selectedProcess: "<?php $x = array_keys($processes); echo $x[0]; ?>",
            selectedPlugin: "<?php if (count($plugins['plugins']) > 0) { echo $plugins['plugins'][0]; } ?>",
            selectedService: "<?php if (count($servicesAvailable) > 0) { echo $servicesAvailable[0]['name']; } ?>"
        };
    },
    methods: {
        fetchData () {
            const params = new FormData();
            params.append("status_url", "<?php echo encode_form_val($status_url); ?>");
            params.append("token", "<?php echo encode_form_val($rp_token); ?>");
            params.append("ssl_verify", "<?php echo encode_form_val($ssl_verify); ?>");
            params.append("os", "<?php echo encode_form_val($system['os']); ?>");
            fetch(API_URL, {
                method: "POST",
                body: params
            })
            .then((response) => response.json())
            .then(data => {
                if (!data.error) {
                    this.cpuUsage = data.cpuUsage;
                    this.memVirtual = data.memVirtual;
                    this.memSwap = data.memSwap;

                    // linux only
                    if (data.load) {
                        this.load = data.load;
                    }
                }
            });
        },
        refershData() {
            setInterval(() => {
                this.fetchData();
            }, 10000);
        },
        getLoad() {
            return this.load.load1.toFixed(2) + ", " + this.load.load5.toFixed(2) + ", " + this.load.load15.toFixed(2)
        },
        addService() {
            this.services.push({
                check: 0,
                desc: "",
                name: "",
                expected: ""
            });
        },
        addProcess() {
            this.processes.push({
                check: 0,
                desc: "",
                name: "",
                warning: "",
                critical: ""
            });
        },
        addPlugin() {
            this.plugins.push({
                check: 0,
                desc: "",
                plugin: "",
                args: ""
            });
        },
        showSelectPlugin(i) {
            this.showPluginsModal = true;
            this.spi = i;
        },
        addSelectedPlugin() {
            this.plugins[this.spi].check = 1;
            this.plugins[this.spi].plugin = this.selectedPlugin;
            if (this.plugins[this.spi].desc == "") {
                this.plugins[this.spi].desc = this.selectedPlugin.replace(/\.[^/.]+$/, "").replace("_", " ").ucfirst();
            }
            this.showPluginsModal = false;
        },
        showSelectService(i) {
            this.showServiceModal = true;
            this.spi = i;
        },
        addSelectedService() {
            this.services[this.spi].check = 1;
            this.services[this.spi].name = this.selectedService;
            if (this.services[this.spi].desc == "") {
                this.services[this.spi].desc = "Service Status - " + this.selectedService;
            }
            var s = this.servicesAvailable.find(o => o.name == this.selectedService);
            this.services[this.spi].expected = s.status;
            this.showServiceModal = false;
        },
        showSelectProcess(i) {
            this.showProcessModal = true;
            this.spi = i;
        },
        addSelectedProcess() {
            this.processes[this.spi].check = 1;
            this.processes[this.spi].name = this.selectedProcess;
            this.processes[this.spi].warning = this.processesAvailable[this.selectedProcess]*2;
            this.processes[this.spi].critical = this.processesAvailable[this.selectedProcess]*3;
            if (this.processes[this.spi].desc == "") {
                this.processes[this.spi].desc = "Process Count - " + this.selectedProcess.ucfirst();
            }
            this.showProcessModal = false;
        }
    },
    mounted() {
        this.fetchData();
        this.refershData();
    }
}).mount("#wizard")

String.prototype.ucfirst = function() {
    return this.charAt(0).toUpperCase() + this.slice(1);
}
</script>