<?php
/**
* Plugin Name: JJFY XML Parser
* Description: XML Parser for JJFY XML Feed
* Author: Albert Friend, Sneha Banda, Eugene Jung, Israel Rivera
* Author URI:
* Version: 1.0.0
* Text Domain: JFY-XML-Plugin
*
*/

if(!defined('ABSPATH'))
{
    exit;
}
// Plugin Constants
define('xmlParsePath', trailingslashit(plugin_dir_path(__FILE__)));
//Var that will come from settings page
$formID = get_option('xml_form_id');

/**
 * function calls for activating and deactivating the plugin
 */
function activate_XMLParse(){
    require_once(xmlParsePath. 'includes/class-xmlParser-activator.php');
    (new xmlParser_Activator)->activate();
}

function deactivate_XMLParse(){
    require_once(xmlParsePath. 'includes/class-xmlParser-deactivation.php');
    (new xmlParser_deactivation) ->deactivation();
}

// wp hooks for plugin activation and de-activation
register_activation_hook(__FILE__ ,"activate_XMLParse");
register_deactivation_hook(__FILE__, "deactivate_XMLParse");

function xmlCrons(){
    require_once(xmlParsePath .'includes/class-xmlParser-parser.php');
    (new JJFYXMLParser) -> xmlParser();
}
add_action('xmlParser', 'xmlCrons');
function setting_page(){
    require_once xmlParsePath . 'settings/class-settings.php';
    new Settings;
}
setting_page();
function test(){
    global $wpdb;
    $query = "SELECT form.entry_id, users.user_nicename, users.ID FROM `wp_frmt_form_entry_meta` AS `form` INNER JOIN `wp_users` as `users` WHERE form.meta_value = users.ID";
    $query_results = $wpdb->get_results($query, ARRAY_A);
    $index = 0;
    while($index < count($query_results)){
        $id = $query_results[$index]['entry_id'];
        $query = "SELECT meta_value, entry_id FROM `wp_frmt_form_entry_meta` WHERE meta_key = 'url-1' and entry_id = $id";
        $results = $wpdb->get_results($query, ARRAY_N);
        // var_dump($results);
        $query_results[$index]['xml_link'] = $results[0][0];
        $query_results[$index]['form-id'] = $results[0][1];
        // array_push($query_results[$index], "ID" => $results);
        $index ++; 
    }
}
// test();
// function run(){
//     require_once(xmlParsePath .'includes/class-xmlParser-parser.php');
//     (new JJFYXMLParser) -> xmlParser();
//     $test = plugins_url('assets/XML-Plugin-icon.png', __FILE__);
//     echo $test;
// }
// run();
