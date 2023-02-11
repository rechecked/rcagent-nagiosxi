<?php
include_once(dirname(__FILE__).'/utils.inc.php');

// Get OS and other system data
$system = rcagent_configwizard_get_api_data("system", $status_url, $rp_token, $ssl_verify);

?>
<?php if (is_dev_mode()) { ?>
<script src="https://unpkg.com/vue@3.2.47/dist/vue.global.js"></script>
<?php } else { ?>
<script src="js/vue.global.js"></script>
<?php } ?>

<style type="text/css">
.thresholds .input-group { margin-right: 10px; }
.thresholds input { height: 30px; width: 60px; }
</style>

<div id="wizard">

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
                <div class="well" style="padding: 10px; margin: 0; width: 250px;">
                    <?php echo _('Current Usage'); ?>: <b v-if="cpuUsage">{{cpuUsage.toFixed(2)}}%</b><b v-else>-</b>
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
                <div class="well" style="padding: 10px; margin: 0; width: 250px;">
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
                <div class="well" style="padding: 10px; margin: 0; width: 250px;">
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
                <div class="well" style="padding: 10px; margin: 0; width: 250px;">
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
    </table>
    
    <h5 class="ul"><?php echo _("Disk Checks"); ?></h5>
    

    <h5 class="ul"><?php echo _("Network Checks"); ?></h5>

    <h5 class="ul"><?php echo _("Service Checks"); ?></h5>

    <h5 class="ul"><?php echo _("Process Checks"); ?></h5>

    <h5 class="ul"><?php echo _("Plugins"); ?></h5>


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
            memSwap: null
        }
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

                    console.log(data.memVirtual)
                    
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
            return this.load.load1 + ", " + this.load.load5 + ", " + this.load.load15
        },
        addService() {

        },
        addProcess() {

        },
        addPlugin() {

        }
    },
    mounted() {
        this.fetchData();
        this.refershData();
    }
}).mount("#wizard")
</script>