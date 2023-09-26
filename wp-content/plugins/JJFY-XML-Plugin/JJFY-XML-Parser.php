<?php
/**
 * Plugin Name: JJFY XML Parser
 * Description: xml test
 * Author: Albert Friend
 * Author URI: https://albertfriend.dev,
 * Version: 1.0.0
 * Text Domain: test-xml
 * 
 */
//Add your name and contact info above
 if(!defined('ABSPATH'))
{
    exit;
}

/**
 * custom option and settings
 */

class  JJFYXMLParser{
    public function __construct()
    {
        add_action('init', array($this,'xmlParser'));
    }

    //Function for pulling company urls from db
    public function retrieveCompanyURLS(){
        global $wpdb;
        $companyURLS = [];
        /**
         *  Will pull table and col name from settings so user can change it
         */
        //SQL Query to get company XML URLS from DP
        $URLS = $wpdb -> get_results("SELECT CompanyURL FROM wp_xml_links");

        //loops over URLS and saves them to array
        foreach($URLS as $URL){
            $url = trim($URL -> CompanyURL);
            array_push($companyURLS, $url);
        }
        //returns the array
        return $companyURLS;
    }
    public function parse_xml(&$companyURLS){
        global $wpdb;
        $wpdb -> show_errors();
        foreach($companyURLS as $URLS){
            
        }
    }
    public function xmlParser(){
        $companyURLS = $this -> retrieveCompanyURLS();
        // var_dump($companyURLS);
        $this -> parse_XML($companyURLS);
    }
    
}

new  JJFYXMLParser;