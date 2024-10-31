<?php
/*
Plugin Name: Post Reaction
Description: Count post reactions (likes, loves, cares) and enforce one-time reaction per user.
Version: 1.0.0
Author: bPlugins
Author URI: https://bplugins.com
License: GPLv3
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// prefix - bppr

/*Some Set-up*/
define('BPPR_PLUGIN_DIR', plugin_dir_url(__FILE__));
define('BPPR_PLUGIN_FILE_BASENAME', plugin_basename( __FILE__ ));
define('BPPR_PLUGIN_DIR_BASENAME', plugin_basename( __DIR__ ));
define('BPPR_VER', '1.0.0');

// auto loader configuration
if(file_exists(dirname(__FILE__).'/vendor/autoload.php')){
    require_once(dirname(__FILE__).'/vendor/autoload.php');
}

// Add custom meta fields for post reactions
function post_reactions_counter_setup() {
    if(class_exists('BPPR\\Init')){
        BPPR\Init::register_services();
    }
}
add_action('plugins_loaded', 'post_reactions_counter_setup');