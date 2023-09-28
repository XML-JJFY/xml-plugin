<?php
/**
 * Plugin Name: JJFY XML Parser
 * Description: XML Parser for JJFY XML Feed
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
 * This might not be needed, I am not sure if we are just gonna hard code everything.
 * would allow changes easier I think
 */

// just to report errors, delete before publishing 
$wpdb -> show_errors();
class  JJFYXMLParser{
    public function __construct()
    {
        add_action('init', array($this,'xmlParser'));
    }

    //Function for pulling company urls from db
    public function retrieveCompanyURLS(){
        global $wpdb;
        $companyURLS = [];
        // Will pull table and col name from settings so user can change it or hardcode if we dont have time
        //SQL Query to get company XML URLS from DP
        $URLS = $wpdb -> get_results("SELECT PublisherURL FROM wp_xml_links");
        //loops over URLS and saves them to array
        foreach($URLS as $URL){
            $url = trim($URL -> PublisherURL);
            array_push($companyURLS, $url);
        }
        //returns the array
        return $companyURLS;
    }

    //Parses XML and calls functions relating to job postings
    public function parse_xml(&$companyURLS){
        foreach($companyURLS as $URLS){
            //pulls xml data from the url array passed to it
            $xml = simplexml_load_file($URLS) or die("Cannot load URL");
            //loops over the job postings
            foreach ($xml->job as $jobs){
                //saving the needed job info into an array
                $jobInfo = [$jobs -> company, $jobs -> partnerJobId, $jobs -> title, $jobs -> description, $xml -> publisher];
                $this-> addPost($jobInfo);
                $this-> updatePost(($jobInfo));
            }
        }
    }

    public function addPost(&$jobInfo){
        //db global call
        global $wpdb;
        $wpdb ->show_errors();
        //checking if job is present, if so it skips, if it is a new job, it adds to the db. (Might want to update this to check publisher id as well)
        $publisherID = $wpdb -> get_col( $wpdb -> prepare ("SELECT PublisherID FROM wp_xml_links WHERE PublisherName = '$jobInfo[4]'"));
        $isPresent = $wpdb -> get_results("SELECT PublisherID, JobID FROM wp_job_postings WHERE JobID = '$jobInfo[1]' AND PublisherID = $publisherID[0]");
        if (count($isPresent) == 0){
            $wpdb -> query("INSERT INTO wp_job_postings (PublisherID, CompanyName, JobID, JobTitle, JobDesc) VALUES ((SELECT PublisherID FROM wp_xml_links WHERE PublisherName = '$jobInfo[4]'), '$jobInfo[0]', $jobInfo[1], '$jobInfo[2]', '$jobInfo[3]')");
        }
    }
    
    public function updatePost(&$jobInfo){
        /**
         * I think we can use wpdb -> update for this 
         * https://developer.wordpress.org/reference/classes/wpdb/update/
         * pass in the job info array from the parseing function 
         * update post if needed
         */
        global $wpdb;
        $wpdb -> show_errors();
        //gets the publisher id from the links db 
        $publisherID = $wpdb -> get_col( $wpdb -> prepare ("SELECT PublisherID FROM wp_xml_links WHERE PublisherName = '$jobInfo[4]'"));
        //checks if data needs updated if it does, updates the data (this can be changed to the update class from wpdb)
        $wpdb -> query($wpdb -> prepare("UPDATE wp_job_postings SET CompanyName = '$jobInfo[0]', JobID = $jobInfo[1], JobTitle = '$jobInfo[2]', JobDesc = '$jobInfo[3]' WHERE PublisherID = $publisherID[0] AND JobID = $jobInfo[1]"));
    }
    public function deleteOldPost(){
        /**
         * I am not 100% sure if there is gonna be a easy way to to this. Might have to do a custom query here.
         * Could make an array of all job posting id/publisher id  in the parsing function, pass that down then compare the db to that and see if there is any that is not in the array. If so, delete them. 
         */
        
    }

    public function xmlParser(){
        $companyURLS = $this -> retrieveCompanyURLS();
        $this -> parse_XML($companyURLS);
    }
    
}

new  JJFYXMLParser;