<?php
/*
Plugin Name: Analytics Control Plus
Plugin URI: http://www.aykira.com.au/programming/wordpress-plugins/
Description: Adds Google Analytics tracking code to WordPress with options to: set bounce timeout; enhanced inpage link tracking; demographics controls. Hides code depending on role.
Version: 1.4
Author: Aykira Internet Solutions
Author URI: http://www.aykira.com.au/
License: GPL2
*/

if ( !defined('ABSPATH') ) { die('-1'); }

class acp_plugin {
  const PLUGIN_OPTIONS = 'analytics_control_plus';
  const META_KEY = 'acp_dont_track';

  protected static $instance;
  public $analytics_id='';
  public $analytics_js=false;
  public $inpage_tracking=false;
  public $bounce_timeout=30;
  public $demographics=false;
  public $excluded_ips=array();
  public $roles_off=array();
  public $in_footer=false;

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
		       'analytics_js'=>'N',
		       'inpage_tracking'=>'N',
		       'demographics'=>'N',
		       'excluded_ips'=>'',
		       'roles_off'=>'administrator',
		       'in_footer'=>'N')); }

    $opts = get_option(PLUGIN_OPTIONS);
    if(isset($opts)) {
      if(isset($opts['analytics_id'])) $this->analytics_id=$opts['analytics_id'];
      if(isset($opts['analytics_js'])) $this->analytics_js=($opts['analytics_js']=='Y');
      if(isset($opts['inpage_tracking'])) $this->inpage_tracking=($opts['inpage_tracking']=='Y');
      if(isset($opts['demographics'])) $this->demographics=($opts['demographics']=='Y');
      if(isset($opts['bounce_timeout'])) $this->bounce_timeout=$opts['bounce_timeout'];
      if(isset($opts['in_footer'])) $this->in_footer=$opts['in_footer'];
      if(isset($opts['excluded_ips'])) {
	$out=array();
	foreach(explode(',',str_replace(' ','',$opts['excluded_ips'])) as $ip) {
	  $out[]='#^'.str_replace('.','\.',$ip).'#';
	}
	$this->excluded_ips=$out;
      }
      if(isset($opts['roles_off'])) {
	$this->roles_off=explode('|',$opts['roles_off']);
      }
      else {
	$this->roles_off=array("administrator");
      }
    }

    add_action('add_meta_boxes', array($this,'meta_box_dont_track') );
    add_action( 'save_post', array($this,'save_postdata') );
    add_action( $this->in_footer ? 'wp_footer' : 'wp_head', array($this,'tracking_code')); 
    add_action('admin_menu', array($this,'admin_menu'));
    add_action('admin_init',array($this,'register_Settings'));
  }



  function meta_box_dont_track() {
    add_meta_box( 'acp-dont-track-box-id',          // ID attribute of metabox
                  "Analytics Control Plus",            // Title of metabox visible to user
                  array($this,'meta_box_callback'), // Function that prints box in wp-admin
                  'page',            // Show box for posts, pages, custom, etc.
                  'side',            // Where on the page to show the box
                  'default' );       // Priority of box in display order
  }


  function meta_box_callback($post) {
    wp_nonce_field( 'acp_inner_custom_box', 'acp_inner_custom_box_nonce' );

    /*
     * Use get_post_meta() to retrieve an existing value
     * from the database and use the value for the form.
     */
    $value = get_post_meta( $post->ID, self::META_KEY, true );

    echo '<label for="acp_dont_track">Disable Google Analytics</label> &nbsp;';
    echo '<input type="checkbox" id="acp_dont_track" name="acp_dont_track" value="Y"';
    if( $value=='Y' ) echo ' checked';
    echo '/>';
  }




  function save_postdata( $post_id ) {
    if ( ! isset( $_POST['acp_inner_custom_box_nonce'] ) )
      return $post_id;

    $nonce = $_POST['acp_inner_custom_box_nonce'];

    // Verify that the nonce is valid.
    if ( ! wp_verify_nonce( $nonce, 'acp_inner_custom_box' ) )
      return $post_id;

    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
      return $post_id;

    // Check the user's permissions.
    if ( 'page' == $_POST['post_type'] ) {

      if ( ! current_user_can( 'edit_page', $post_id ) )
        return $post_id;
  
    } else {

      if ( ! current_user_can( 'edit_post', $post_id ) )
        return $post_id;
    }

    /* OK, its safe for us to save the data now. */

    // Sanitize user input.
    $mydata = ($_POST['acp_dont_track']=='Y') ? 'Y' : 'N';

    // Update the meta field in the database.
    update_post_meta( $post_id, self::META_KEY, $mydata );
  }


  private function get_ip() {
    foreach(array('HTTP_X_FORWARDED_FOR','HTTP_X_FORWARDED','HTTP_FORWARDED_FOR','HTTP_VIA',
		  'HTTP_CLIENT_IP','REMOTE_ADDR') as $f) {
      if(isset($_SERVER[$f])) return $_SERVER[$f];
    }

    return 'NA';
  }


  private function blocked_ip($ip) {
    foreach($this->excluded_ips as $bl) {
      if(preg_match($bl,$ip)) return true;
    }
    return false;
  }

  private function siteDomain() {
    $site=get_site_url();
    if(preg_match('#^https?://(.*)$#si',$site,$matches)) {
      $site=$matches[1];
    }
    if(preg_match('#^www\.(.*)$#si',$site,$matches)) {
      $site=$matches[1];
    }

    return $site;
  }

  function tracking_code() {
    $code_id=$this->analytics_id;
    if(empty($code_id)) return;

    if($this->blocked_ip($this->get_ip())) return;

    if(is_page()) {
      $page_id = get_queried_object_id();
      $value=get_post_meta($page_id,self::META_KEY, true);
      if($value=='Y') {
	echo "<!--- GA disabled for this page -->";
	return;  // no GA here!!
      }
    }

    $bounce_timeout=$this->bounce_timeout*1000;
    $tracking_script='';
    if($this->analytics_js) {
      $site=$this->siteDomain();
      $tracking_script = <<<_TRACKING_CODE_
<script>
      (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
     m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
    })(window,document,'script','//www.google-analytics.com/analytics.js','ga');
    ga('create', '$code_id', '$site');

_TRACKING_CODE_;
      if($this->inpage_tracking=='Y') {
        $tracking_script.="    ga('require', 'linkid', 'linkid.js');\n";
      }
      if($this->demographics) {
        $tracking_script.="    ga('require', 'displayfeatures');\n";
      }
      $tracking_script.=<<<_TRACKING_CODE_
    ga('send', 'pageview');
    setTimeout(function() {
         window.onscroll = function() {
           window.onscroll = null; // Only track the event once
           ga('send','event', 'scroll', 'read');
         }
       }, $bounce_timeout);
_TRACKING_CODE_;
      $tracking_script.="</script>\n";
    }
    else {
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
    }
    $doit=true;
    $role='';
    $current_user = wp_get_current_user();
    if(isset($current_user)) {
      $roles = $current_user->roles;
      $role = array_shift($roles);
      if(in_array($role,$this->roles_off)) $doit=false;
    }

    if($doit) {
      echo $tracking_script;
    }
    else {
      echo "<!--- Analytics Control Plus is working but not showing code due to you being logged in as $role -->";
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
   <small>Every little bit helps &amp; encourages more development, please contribute.</small></br>
<small><b>Have an idea or suggestion?<br/>Please <a href="http://www.aykira.com.au/contact/" target="_blank">Contact Us</a>.</b></small>
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
    add_settings_field('analytics_js', 'Enable Universal Analytics', array($this,'settings_analytics_js'), 'acp_plugin', 'plugin_main');
    add_settings_field('inpage_tracking', 'Enable Enhanced Link Attribution', array($this,'settings_inPage_Tracking'), 'acp_plugin', 'plugin_main');
    add_settings_field('demographics', 'Enable Demographics and Interest Reports', array($this,'settings_demographics'), 'acp_plugin', 'plugin_main');
    add_settings_field('bounce_timeout', 'Debounce Timeout', array($this,'settings_bounce_timeout'), 'acp_plugin', 'plugin_main');
    add_settings_field('in_footer', 'Put Code in Footer', array($this,'settings_in_footer'), 'acp_plugin', 'plugin_main');
    add_settings_field('excluded_ips', 'Excluded IPs', array($this,'settings_excluded_ips'), 'acp_plugin', 'plugin_main');
    add_settings_field('roles_off', 'Excluded Roles from Tracking', array($this,'settings_roles_off'), 'acp_plugin', 'plugin_main');
  }


  public function options_validate($input) {
    $input['analytics_id']=trim($input['analytics_id']);
    $input['bounce_timeout']=$input['bounce_timeout']*1;
    if($input['bounce_timeout']<5) $input['bounce_timeout']=5;
    global $wp_roles;
    $roles=$wp_roles->get_names();
    $off_roles=array();
    foreach($roles as $role) {
      if(isset($input["role_$role"])) {
	$rolel=strtolower($role);
	$off_roles[]=$rolel;
	unset($input["role_$role"]);
      }
    }
    $input['roles_off']=implode('|',$off_roles);
    return $input;
  }


  public function ga_section() {
    echo "<p><b>Note:</b> If you turn on the Enhanced Link Attribution or Demographics &amp; Interest Reports you need to configure Google Analytics to use the information; this is done under <i>Admin > Property > Property Settings > Advanced Settings</i> - just flick the toggles to ON and save the changes. Log on to Google Analytics <a href=\"http://www.google.com/analytics/\" target=\"_blank\">here</a>.</p>";
  }

  public function settings_analytics_id() {
    $options = get_option(PLUGIN_OPTIONS);
    echo "<input id='analytics_id' name='".PLUGIN_OPTIONS."[analytics_id]' type='text' value='".$options['analytics_id']."' size='20'/>";
  }

  public function settings_analytics_js() {
    $options = get_option(PLUGIN_OPTIONS);
    echo "<input id='analytics_js' name='".PLUGIN_OPTIONS."[analytics_js]' type='checkbox' value='Y'";
    if($options['analytics_js']=='Y') echo " checked='yes'";
    echo " /> <small><a target='_blank' href='https://developers.google.com/analytics/devguides/collection/upgrade/guide'>Google Help details</a>. (site domain = ".$this->siteDomain()."). Migrate to Universal Analytics first in GA before turning on!</small>";
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


  public function settings_in_footer() {
    $options = get_option(PLUGIN_OPTIONS);
    echo "<input id='in_footer' name='".PLUGIN_OPTIONS."[in_footer]' type='checkbox' value='Y'";
    if($options['in_footer']=='Y') echo " checked='yes'";
    echo " /> <small>Put in the footer if you have JavaScript clashes.</small>";
  }


  public function settings_excluded_ips() {
    $options = get_option(PLUGIN_OPTIONS);
    echo "<input id='excluded_ips' name='".PLUGIN_OPTIONS."[excluded_ips]' type='text' value='".$options['excluded_ips']."' size='30'/><br/><small>Comma separated list of IP's excluded (or subnets)<br/>&nbsp;Current IP = ".$this->get_ip()."</small>";
  }


  public function settings_roles_off() {
    $options = get_option(PLUGIN_OPTIONS);

    global $wp_roles;
    $roles=$wp_roles->get_names();
    foreach($roles as $role) {
      echo "&nbsp;&nbsp;<input type='checkbox' name='".PLUGIN_OPTIONS."[role_$role]' value='Y'";
      $rolel=strtolower($role);
      if(in_array($rolel,$this->roles_off)) echo " checked";
      echo "> $role<br/>";
    }
    echo "<small>Select those roles who you do <u>not</u> want tracked on site.</small>";
  }

}


$acp = acp_plugin::instance();




