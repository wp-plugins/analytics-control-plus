<?php
/*
Plugin Name: Analytics Control Plus
Plugin URI: http://www.aykira.com.au/programming/wordpress-plugins/
Description: Adds Google Analytics tracking code to WordPress with options to: set bounce timeout; enhanced inpage link tracking; demographics controls. Hides code when logged in as Admin.
Version: 1.0
Author: Aykira Internet Solutions
Author URI: http://www.aykira.com.au/
License: GPL2
*/

if ( !defined('ABSPATH') ) { die('-1'); }

class acp_plugin {
  const PLUGIN_OPTIONS = 'analytics_control_plus';
 
  protected static $instance;
  public $analytics_id='';
  public $inpage_tracking=false;
  public $bounce_timeout=30;
  public $demographics=false;


  public static function instance() {
    if (!isset(self::$instance)) {
      $className = __CLASS__;
      self::$instance = new $className;
    }
    return self::$instance;
  }

  protected function __construct( ) {
    if (!get_option(PLUGIN_OPTIONS)) { 
      add_option(PLUGIN_OPTIONS,
		 array('bounce_timeout'=>30,
		       'inpage_tracking'=>'N',
		       'demographics'=>'N')); }

    $opts = get_option(PLUGIN_OPTIONS);
    if(isset($opts)) {
      if(isset($opts['analytics_id'])) $this->analytics_id=$opts['analytics_id'];
      if(isset($opts['inpage_tracking'])) $this->inpage_tracking=($opts['inpage_tracking']=='Y');
      if(isset($opts['demographics'])) $this->demographics=($opts['demographics']=='Y');
      if(isset($opts['bounce_timeout'])) $this->bounce_timeout=$opts['bounce_timeout'];
    }

    add_action('wp_footer', array($this,'tracking_code')); 
    add_action('admin_menu', array($this,'admin_menu'));
    add_action('admin_init',array($this,'register_Settings'));
  }


  function tracking_code() {
    $code_id=$this->analytics_id;
    if(empty($code_id)) return;
    $bounce_timeout=$this->bounce_timeout*1000;

    $tracking_script = <<<_TRACKING_CODE_
<script type="text/javascript">
var _gaq = _gaq || [];
var pluginUrl = '//www.google-analytics.com/plugins/ga/inpage_linkid.js';
_TRACKING_CODE_;
if($this->inpage_tracking=='Y') {
  $tracking_script.="\n_gaq.push(['_require', 'inpage_linkid', pluginUrl]);\n";
}
$tracking_script.= <<<_TRACKING_CODE_
_gaq.push(['_setAccount', '$code_id']);
_gaq.push(['_trackPageview']);

setTimeout(function() {
    window.onscroll = function() {
      window.onscroll = null; // Only track the event once
      _gaq.push(['_trackEvent', 'scroll', 'read']);
    }
  }, $bounce_timeout);

(function() {
  var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
_TRACKING_CODE_;
if($this->demographics) {
  $tracking_script.="  ga.src = ('https:' == document.location.protocol ? 'https://' : 'http://') + 'stats.g.doubleclick.net/dc.js';\n";
}
else {
  $tracking_script.="  ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';\n";
}
$tracking_script.= <<<_TRACKING_CODE_

  var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();
</script>
_TRACKING_CODE_;

    if (!current_user_can('edit_published_posts')) {
      echo $tracking_script;
    }
    else {
      echo "<!--- Analytics Control Plus is working but not showing code due to you being logged in as admin -->";
    }
  }



  function admin_menu() {
    add_options_page( 'Analytics Control+', 'Analytics Control+', 'manage_options', 'acp', array($this,'options'));
  }


  function options() {
    echo "<h2>Analytics Control Plus Configuration</h2>";
?>
<div>
<div style='float:right;width:220px;background:#ccf;padding:10px;text-align:center;box-shadow:3px 3px 3px rgba(0,0,0,0.35);border-radius:10px;margin-right:50px;margin-top:50px;'>
<h3>Please contribute $5 to the ongoing development of this and other plugins</h3>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="VX3T2AFBDBYV8">
<input type="image" src="https://www.paypalobjects.com/en_AU/i/btn/btn_buynowCC_LG.gif" border="0" name="submit" alt="PayPal -- The safer, easier way to pay online.">
<img alt="" border="0" src="https://www.paypalobjects.com/en_AU/i/scr/pixel.gif" width="1" height="1">
</form><br/>
   <small>Every little bit helps &amp; encourages more development, please contribute if you can.</small>
</div>
<form method='post' action="options.php" style="width:58%;float:left;">
<?php settings_fields('acp_settings_group'); ?>
<?php do_settings_sections('acp_plugin'); ?>
<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />    
</form>

</div><br clear=all>
<hr>
<small><b>Plugin by <a target="_blank" href="http://www.aykira.com.au/">Aykira Internet Solutions</a></b></small>
<?php
 }



  public function register_Settings() {
    register_setting('acp_settings_group',PLUGIN_OPTIONS,array($this,'options_validate'));
    add_settings_section('plugin_main', 'Google Analytics Code', array($this,'ga_section'), 'acp_plugin');
    add_settings_field('analytics_id', 'Analytics ID', array($this,'settings_analytics_id'), 'acp_plugin', 'plugin_main');
    add_settings_field('inpage_tracking', 'Enable Enhanced Link Attribution', array($this,'settings_inPage_Tracking'), 'acp_plugin', 'plugin_main');
    add_settings_field('demographics', 'Enable Demographics and Interest Reports', array($this,'settings_demographics'), 'acp_plugin', 'plugin_main');
    add_settings_field('bounce_timeout', 'Debounce Timeout', array($this,'settings_bounce_timeout'), 'acp_plugin', 'plugin_main');
  }


  public function options_validate($input) {
    $input['analytics_id']=trim($input['analytics_id']);
    $input['bounce_timeout']=$input['bounce_timeout']*1;
    if($input['bounce_timeout']<5) $input['bounce_timeout']=5;

    return $input;
  }


  public function ga_section() {
    echo "<p><b>Note:</b> If you turn on the Enhanced Link Attribution or Demographics &amp; Interest Reports you need to configure Google Analytics to use the information; this is done under <i>Admin > Property > Property Settings > Advanced Settings</i> - just flick the toggles to ON and save the changes. Log on to Google Analytics <a href=\"http://www.google.com/analytics/\" target=\"_blank\">here</a>.</p>";
  }

  public function settings_analytics_id() {
    $options = get_option(PLUGIN_OPTIONS);
    echo "<input id='analytics_id' name='".PLUGIN_OPTIONS."[analytics_id]' type='text' value='".$options['analytics_id']."' size='20'/>";
  }

  public function settings_inPage_Tracking() {
    $options = get_option(PLUGIN_OPTIONS);
    echo "<input id='inpage_tracking' name='".PLUGIN_OPTIONS."[inpage_tracking]' type='checkbox' value='Y'";
    if($options['inpage_tracking']=='Y') echo " checked='yes'";
    echo " /> <small><a target='_blank' href='https://support.google.com/analytics/answer/2558867?hl=en&utm_id=ad'>Google Help details</a>. Turn on in GA as well!</small>";
  }

  public function settings_demographics() {
    $options = get_option(PLUGIN_OPTIONS);
    echo "<input id='demographics' name='".PLUGIN_OPTIONS."[demographics]' type='checkbox' value='Y'";
    if($options['demographics']=='Y') echo " checked='yes'";
    echo " /> <small><a target='_blank' href='https://support.google.com/analytics/answer/2444872?hl=en&utm_id=ad'>Google Help details</a>. Turn on in GA as well!</small>";
  }

  public function settings_bounce_timeout() {
    $options = get_option(PLUGIN_OPTIONS);
    echo "<input id='bounce_timeout' name='".PLUGIN_OPTIONS."[bounce_timeout]' type='text' value='".$options['bounce_timeout']."' size='3'/> (seconds) <small>Minimum 5 seconds.</small>";
  }

}


$acp = acp_plugin::instance();



