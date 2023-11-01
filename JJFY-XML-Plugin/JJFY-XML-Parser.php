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
$fullTime = get_option('xml_parser_Full_Time');
$partTime = get_option('xml_parser_Part_Time');
$contractor = get_option('xml_parser_Contractor');
$temporary = get_option('xml_parser_Temporary');
$intern = get_option('xml_parser_Intern');
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
    require_once(xmlParsePath .'includes/class-xmlParser-logic.php');
    (new JJFYXMLParser) -> xmlParser();
}
add_action('xmlParser', 'xmlCrons');
function setting_page(){
    require_once xmlParsePath . 'settings/class-settings.php';
    new Settings;
}
setting_page();
