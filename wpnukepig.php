<?php
/*
Plugin Name: WP Nuke PiG
Plugin URI: http://www.blogpig.com/nuke
Description: This plugin allows the user to bulk-remove selected content from the site
Author: BlogPiG
Version: 2.1.1
Author URI: http://www.blogpig.com
*/

function wpnukepig_activate() {
  $this_app = 'wp-nuke-pig';

  $installed_apps_string = trim(get_option('blogpig_apps'));
  $installed_apps = array();
  if($installed_apps_string != '') {
    $installed_apps = explode(',', get_option('blogpig_apps'));
  }
  if(array_search($this_app, $installed_apps) == FALSE) {
    array_push($installed_apps, $this_app);
    $installed_apps = array_unique($installed_apps);
    update_option('blogpig_apps', implode(',',  $installed_apps));
  }
  unset($installed_apps);

}
add_action('activate_wp-nuke-pig/wpnukepig.php', 'wpnukepig_activate');

function wpnukepig_deactivate() {
  $this_app = 'wp-nuke-pig';
  $installed_apps = explode(',', get_option('blogpig_apps'));
  if(count($installed_apps) > 0) {
    $output_apps = array();
    foreach($installed_apps as $app) {
      if($app != $this_app) {
        array_push($output_apps, $app);
      }
    }
    update_option('blogpig_apps', implode(',',  $output_apps));
    unset($output_apps);
  }
  unset($installed_apps);
}
add_action('deactivate_wp-nuke-pig/wpnukepig.php', 'wpnukepig_deactivate');

function blogpig_nuke_conf(){
  $pluginpath = dirname(__FILE__);
  include("$pluginpath/config.php");
}

function blogpig_nuke_not_conf(){
  echo "Not Available!";
}

function add_blogpig_nuke_to_submenu() {
  # NukePiG
  if(function_exists('blogpig_nuke_conf')) {
    define('NUKE_CONF_FUNCTION', 'blogpig_nuke_conf', TRUE);
  }
  else {
    define('NUKE_CONF_FUNCTION', 'blogpig_nuke_not_conf', TRUE);
  }
  if(!defined('BLOGPIG_CONF_PARENT')) {
    define('BLOGPIG_CONF_PARENT', __FILE__, TRUE);
    add_menu_page('BlogPiG Page', 'BlogPiG', 8, __FILE__, NUKE_CONF_FUNCTION);
  }
  add_submenu_page(BLOGPIG_CONF_PARENT, 'NukePiG Page', 'NukePiG', 8, __FILE__, NUKE_CONF_FUNCTION);
}
add_action('admin_menu', 'add_blogpig_nuke_to_submenu');

#
# Helper functions
###

if(!function_exists("range_to_value")){
  function range_to_value($range) {
    $result = $range;

    if($range) {
      $tmp_range = split('-', $range);
      $my_count = 0;
      if(count($tmp_range) == 2) {
        $my_count = rand($tmp_range[0], $tmp_range[1]);
      }
      else {
        $my_count = $tmp_range[0];
      }
      if(isset($my_count) && $my_count != '' && $my_count > 0) {
        $result = $my_count;
      }
    }
    #echo "range_to_value:: result = `$result` <BR />";

    return $result;
  }
}

require_once dirname(__FILE__) . "/nukepig_main.php";

?>
