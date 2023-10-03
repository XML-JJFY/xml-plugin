<?php
/**
 * Plugin Name: JJFY XML Parser
 * Description: XML Parser for JJFY XML Feed
 * Author: Albert Friend, Sneha Banda, Eugene Jung, Israel Rivera
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
        $job_array = [];
        $jobInfo = [];
        foreach($companyURLS as $URLS){
            //pulls xml data from the url array passed to it
            $xml = simplexml_load_file($URLS) or die("Cannot load URL");
            //loops over the job postings
            foreach ($xml as $jobs){
                //saving the needed job info into an array
                if(count($jobs) != 0){
                    $jobInfo = [$jobs -> company, $jobs -> partnerJobId, $jobs -> title, $jobs -> description, $xml -> publisher, $jobs -> location, $jobs -> applyUrl, $jobs -> workplaceTypes, $jobs -> expirationDate, $jobs -> location, $jobs -> jobtype, $jobs -> salaries -> salary -> highEnd->amount, $jobs -> salaries -> salary -> lowEnd-> amount, $jobs -> salaries -> salary -> lowEnd-> currencyCode];
                    array_push($job_array,[$xml -> publisher, $jobs-> partnerJobId]);
                }
                if (count($jobInfo) != 0){
                    $this-> addPost($jobInfo);
                    $this-> updatePost(($jobInfo));
                }
            }
        }
        $this-> deleteOldPost($job_array);

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
            $this->postJob($jobInfo, $publisherID);
            $this->addMetaData($jobInfo);
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
    public function deleteOldPost(&$job_array){
        /**
         * I am not 100% sure if there is gonna be a easy way to to this. Might have to do a custom query here.
         * Could make an array of all job posting id/publisher id  in the parsing function, pass that down then compare the db to that and see if there is any that is not in the array. If so, delete them. 
         * will also have to delete/trash the post/post meta info
         */
        global $wpdb;
        $publisherID = [];
        $jobIDS = [];
        foreach($job_array as $job){
            array_push($publisherID, $wpdb -> get_var( $wpdb -> prepare ("SELECT PublisherID FROM wp_xml_links WHERE PublisherName = '$job[0]'")));
            foreach($job as $jobID){
                array_push($jobIDS, $job[1]);
            }
        }
        $publisherID = array_unique($publisherID);
        $jobIDS= array_unique($jobIDS);

        //checking if any jobs have been deleted if so, delete them
        // DELETE FROM `wp_job_postings` WHERE JobID AND PublisherID NOT IN (1114, 4)
        // $wpdb -> query($wpdb -> prepare("DELETE * FROM wp_job_postings WHERE "));
    }
    public function addMetaData(&$jobInfo){
        //if anyone can get the built in inserts to work it will be cleaner leaving them there for now
        global $wpdb;
        //setting var for salary
        $payHigh = $jobInfo[11];
        $payLow = $jobInfo[12];
        $currencyCode = $jobInfo[13];
        $pay = "$payLow - $payHigh $currencyCode";
        //gets post_id of posting above for meta data
        $post_id = $wpdb -> get_col($wpdb -> prepare ("SELECT ID FROM wp_posts WHERE job_posting_id = $jobInfo[1] AND post_author = 1"));
        if ($post_id != null || 0){
            //Adding reletive post meta.
            $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_company_name' ,$post_id[0], '$jobInfo[0]')"));
            // $wpdb -> insert('wp_postmeta', array('meta_key' => '_company_name', 'post_id' => $post_id[0], 'meta_value' => $jobInfo[0]));
            $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_company_webstie' ,$post_id[0], '$jobInfo[6]')"));
            // $wpdb -> insert('wp_postmeta', array('meta_key' => '_company_webstie', 'post_id' => $post_id[0], 'meta_value' => $jobInfo[6]));
            $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_expires' ,$post_id[0], '$jobInfo[8]')"));
            // $wpdb -> insert('wp_postmeta', array('meta_key' => '_job_expires', 'post_id' => $post_id[0], 'meta_value' => $jobInfo[8]));
            $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_description' ,$post_id[0], '$jobInfo[3]')"));
            // $wpdb -> insert('wp_postmeta', array('meta_key' => '_job_description', 'post_id' => $post_id[0], 'meta_value' => $jobInfo[3]));
            $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_title' ,$post_id[0], '$jobInfo[2]')"));
            // $wpdb -> insert('wp_postmeta', array('meta_key' => '_job_title', 'post_id' => $post_id[0], 'meta_value' => $jobInfo[2]));
            $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_salary' ,$post_id[0], '$pay')"));

            //extra statments
            // $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_title' ,$post_id[0], '$jobInfo[2]')"));
            // $wpdb -> insert('wp_postmeta', array('meta_key' => '_company_webstie', 'post_id' => $post_id, 'meta_value' => $jobInfo[6]));

            //User who posted (this will be updated once we figure out the front end part)
            $wpdb -> insert('wp_postmeta', array('meta_key' => '_application', 'post_id' => $post_id, 'meta_value' => 'awfriend77@gmail.com'));

            //Checks if job is remote if so sets remote to 1
            if(strtolower($jobInfo[7]) === 'remote'){
                $wpdb -> insert('wp_postmeta', array('meta_key' => '_remote_position', 'post_id' => $post_id, 'meta_value' => '1'));
            }else{
                $wpdb -> insert('wp_postmeta', array('meta_key' => '_remote_position', 'post_id' => $post_id, 'meta_value' => '0'));
                $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_location' ,$post_id[0], '$jobInfo[9]')"));
                // $wpdb -> insert('wp_postmeta', array('meta_key' => '_job_location', 'post_id' => $post_id, 'meta_value' => $jobInfo[9]));
            }

            // Sets job type in wp_term_relationships
            print_r(strtolower($jobInfo[10])==='full-time');
            switch (strtolower($jobInfo[10])){
                case 'full-time':
                    $wpdb -> insert('wp_term_relationships', array('object_id' => $post_id[0], 'term_taxonomy_id'=>3));
                    break;
                case 'part-time':
                    $wpdb -> insert('wp_term_relationships', array('object_id' => $post_id[0], 'term_taxonomy_id'=>4));
                    break;
                case 'temporary':
                    $wpdb -> insert('wp_term_relationships', array('object_id' => $post_id[0], 'term_taxonomy_id'=>5));
                    break;
                case 'temporary':
                    $wpdb -> insert('wp_term_relationships', array('object_id' => $post_id[0], 'term_taxonomy_id'=>6));
                    break;
                case 'internship':
                    $wpdb -> insert('wp_term_relationships', array('object_id' => $post_id[0], 'term_taxonomy_id'=>7));
                    break;
            }
            //adding defaults for post meta
            $wpdb -> insert('wp_postmeta', array('meta_key' => '_thumbnail_id', 'post_id' => $post_id[0], 'meta_value' => ''));
            $wpdb -> insert('wp_postmeta', array('meta_key' => '_company_twitter', 'post_id' => $post_id[0], 'meta_value' => ''));
            $wpdb -> insert('wp_postmeta', array('meta_key' => '_company_video',  'post_id' => $post_id[0], 'meta_value' => ''));
            $wpdb -> insert('wp_postmeta', array('meta_key' => '_company_tagline', 'post_id' => $post_id[0], 'meta_value' => ''));
            $wpdb -> insert('wp_postmeta', array('meta_key' => '_filled', 'post_id' => $post_id[0], 'meta_value' => 0));
            $wpdb -> insert('wp_postmeta', array('meta_key' => '_featured', 'post_id' => $post_id[0], 'meta_value' => 0));
        }else{
            print_r('something went wrong');
        }
    }
    public function postJob(&$jobInfo, $publisherID){
        global $wpdb;
        $wpdb -> show_errors();
        //reg expression to get post name close to the live site
        $companyName = strtolower(preg_replace('/\s+/', '-', $jobInfo[0]));
        $jobTitle = strtoLower(preg_replace('/\s+/', '-', $jobInfo[2]));
        $postName = "$companyName-$jobTitle";
        //inseting into the post table
        $wpdb -> query($wpdb -> prepare("INSERT INTO wp_posts (post_author, job_posting_id, post_content, post_title, post_status, comment_status,  ping_status, post_name, post_type) VALUES (1, $jobInfo[1], '$jobInfo[3]', '$jobInfo[2]', 'publish', 'closed', 'closed', '$postName', 'job_listing')"));
    }
    public function xmlParser(){
        $companyURLS = $this -> retrieveCompanyURLS();
        $this -> parse_XML($companyURLS);
    }
    
}

new  JJFYXMLParser;