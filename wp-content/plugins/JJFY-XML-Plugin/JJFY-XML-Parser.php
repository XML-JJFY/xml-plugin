<?php
/**
* Plugin Name: JJFY XML Parser
* Description: XML Parser for JJFY XML Feed
* Author: Albert Friend, Sneha Banda, Eugene Jung, Israel Rivera
* Author URI:
* Version: 1.0.0
* Text Domain: test-xml
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
// wp hooks for plugin activation and de-activation
register_activation_hook(__FILE__,"activate_XMLParse");
register_deactivation_hook(__FILE__, "deactivate_XMLParse");


function activate_XMLParse(){
    //Inserts DB Table
    init_db_XMLParse();
    //adds option for form id
    RegisterSettings();
}

function RegisterSettings(){
    add_option('xml_form_id');
}
function init_db_XMLParse(){
    global $wpdb, $table_prefix;
    //Sets table var for wp-job-postings
    // $jobTable = $wpdb -> prefix . 'wp_job_postings';
    $jobTable = $table_prefix.'job_postings';
    $charset_collate = $wpdb->get_charset_collate();
    if ($wpdb -> get_var("show tables like '$jobTable'") != $jobTable){
        //Create table query
        $sql = "CREATE TABLE " .$jobTable. "(
        PublisherID int(11) NOT NULL,
        JobID varchar(40) NOT NULL)
        $charset_collate;";
        require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
        dbDelta($sql);
    }
}


function deactivate_XMLParse(){
    delete_option('xml_form_id');
}
/**
* adding js to pluging for setting page button
*/

require_once xmlParsePath . '/settings/settings.php';


class JJFYXMLParser{
    public function __construct()
    {
        add_action('init', array($this,'xmlParser'));
    }


    // Function for pulling company urls from db
    public function retrieveCompanyURLS(){
        global $wpdb, $formID;
        $companyURLS = [];
        $index = 0;
        //Getting userID and postID from the Table that hold them
        if(!empty($formID)){
            $formData = $wpdb -> get_results("SELECT meta_value, entry_id FROM wp_frmt_form_entry_meta WHERE meta_key = 'hidden-1'");
            //splitting them into their own arrays
            $postID = array_column($formData, 'entry_id');
            $userID = array_column($formData, 'meta_value');
            foreach ($postID as $ID){
                //checking if the postID is from the URL form. If it is pulling the url in and adding it to the array of urls.
                $results = $wpdb -> get_var("SELECT COUNT(*) FROM wp_frmt_form_entry WHERE form_id = $formID AND entry_id = $ID");
                if($results == 1){
                $url = $wpdb -> get_col($wpdb -> prepare("SELECT meta_value FROM wp_frmt_form_entry_meta WHERE meta_key = 'url-1' AND entry_id = $ID"));
                array_push($companyURLS, [$userID[$index], $url]);
                $index ++;
                }else{
                    $index ++;
                }
            }
            return $companyURLS;
        }
    }




    //Parses XML and calls functions relating to job postings
    public function parse_xml(&$companyURLS){
        $job_array = [];
        $jobInfo = [];
        $arrayindex = 0;
        foreach($companyURLS as $company){
            // getting userID who linked the xml feed
            $publisherID = $company[0];
            array_push($job_array, [$publisherID]);
            // checks if link is vaild xml link if so parses the link
            if (str_contains($company[1][0], '.xml')){
                $xml = simplexml_load_file($company[1][0]) or die("Cannot load URL");
                // loops over the job postings
                if(!(count($xml -> job) == 0)){
                    foreach ($xml as $jobs){
                    //saving the needed job info into an array
                        if(count($jobs) != 0){
                            $jobInfo = ["companyName" => $jobs -> company, "publisherName" => $xml -> publisher, "jobID" => $jobs -> partnerJobId, "jobTitle" => $jobs -> title, "jobDescription" => $jobs -> description, "skills" => $jobs -> skills -> skill, "experienceLvl" => $jobs -> experienceLevel, "jobFunction" => $jobs ->  jobFunctions -> jobFunction, "jobLocation" => $jobs -> location, 'jobWorkPlace'=> $jobs -> workplaceTypes, "jobType" => $jobs -> jobtype, 'salaryHighEnd'=> $jobs -> salaries -> salary -> highEnd->amount, 'salaryLowEnd'=> $jobs -> salaries -> salary -> lowEnd-> amount, "currencyCode"=> $jobs -> salaries -> salary -> lowEnd-> currencyCode, "expirationDate"=> $jobs -> expirationDate, "applyUrl" => $jobs -> applyUrl];
                        }
                        if (count($jobInfo) != 0){
                            array_push($job_array[$arrayindex], [$jobInfo['jobID']]);
                            $this-> addPost($jobInfo, $publisherID);
                            // $this-> updatePost($jobInfo, $publisherID);
                        }
                    }
                }
            }
            $arrayindex ++;
        }
        $this-> deleteOldPost($job_array);
    }




    public function addPost(&$jobInfo, $publisherID){
        //db global call
        global $wpdb;
        // $wpdb ->show_errors();
        //checking if job is present, if so it skips, if it is a new job, it adds to the db. (Might want to update this to check publisher id as well)
        // $isPresent = $wpdb -> get_results("SELECT PublisherID, JobID FROM wp_job_postings WHERE JobID = '$jobInfo[jobID]' AND PublisherID = $publisherID");
        $isPosted = $wpdb -> get_results("SELECT ID FROM wp_posts WHERE post_author = $publisherID AND job_posting_id = $jobInfo[jobID]");
        if (count($isPosted) == 0){
            $wpdb -> query("INSERT INTO wp_job_postings (PublisherID, JobID) VALUES ($publisherID, $jobInfo[jobID])");
            $this->postJob($jobInfo, $publisherID);
            $this->addMetaData($jobInfo, $publisherID);
        }
    }


    public function updatePost(&$jobInfo, $publisherID){
        /**
        * I think we can use wpdb -> update for this
        * https://developer.wordpress.org/reference/classes/wpdb/update/
        * pass in the job info array from the parseing function
        * update post if needed
        */
        // global $wpdb;
        // $wpdb -> show_errors();
        // //gets the publisher id from the links db
        // //checks if data needs updated if it does, updates the data (this can be changed to the update class from wpdb)
        // $wpdb -> query($wpdb -> prepare("UPDATE wp_job_postings SET CompanyName = $jobInfo[companyName], JobID = $jobInfo[jobID], JobTitle = '$jobInfo[jobTitle]', JobDesc = '$jobInfo[jobDescription]' WHERE PublisherID = $publisherID AND JobID = $jobInfo[jobID]"));
    }
    public function deleteOldPost(&$job_array){
        global $wpdb;
        $publisherID = [];
        $jobIDS = [];
        $arrayIndex = 0;
        //pulls information needed to check for old post
        while($arrayIndex < count($job_array)){
            array_push($publisherID, $job_array[$arrayIndex][0]);
            unset($job_array[$arrayIndex][0]);
            foreach($job_array[$arrayIndex] as $job){
                if(!array_key_exists($arrayIndex, $jobIDS)){
                    array_push($jobIDS, [implode('', $job)]);
                }else{
                    array_push($jobIDS[$arrayIndex], implode('', $job));
                }
            }
            $arrayIndex ++;
        }
        /**
        * loop over the arrays by index.
        * select publisherID and JobID $wpdb -> get_results("SELECT PublisherID, JobID FROM `wp_job_postings` WHERE `PublisherID` = publisherID[index] AND `JobID` NOT IN (jobIDS[index])");
        * get post id from post table.
        * delete meta data and post
        */
    }
    public function addMetaData(&$jobInfo, $publisherID){
        //if anyone can get the built in inserts to work it will be cleaner leaving them there for now.
        global $wpdb;
        $count = 0;
        $skill = [];
        //setting var for salary
        $payHigh = $jobInfo['salaryHighEnd'];
        $payLow = $jobInfo['salaryLowEnd'];
        $currencyCode = $jobInfo['currencyCode'];
        $pay = "$payLow - $payHigh $currencyCode";
        //setting up var for skills and to mimic tags
        $skills = $jobInfo["skills"];
        $jobFunction = $jobInfo["jobFunction"];
        while ($count < count($skills)){
            array_push($skill ,$skills[$count]);
            $count ++;
        }
        $count = 0;
        while ($count < count($jobFunction)){
            array_push($skill ,$jobFunction[$count]);
            $count ++;
        }
        array_push($skill, $jobInfo["experienceLvl"]);
        $skill = implode(', ', $skill);
        //gets post_id of posting above for meta data
        $post_id = $wpdb -> get_col($wpdb -> prepare ("SELECT ID FROM wp_posts WHERE job_posting_id = $jobInfo[jobID] AND post_author = $publisherID"));
        //Adding reletive post meta.
        // //add post meta
        // update_post_meta($post_id, '_company_name', '$jobInfo[companyName]');
        // add_post_meta($post_id, '_company_website', $jobInfo[6]);
        // add_post_meta($post_id, '_job_expires', $jobInfo[8]);
        // add_post_meta($post_id, '_job_description', $jobInfo[3]);
        // add_post_meta($post_id, '_job_title', $jobInfo[2]);
        // add_post_meta($post_id, '_job_salary', $pay);
        // add_post_meta($post_id, '_job_important_info', $skill);


        //normal insert
        $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_company_name' ,$post_id[0], '$jobInfo[companyName]')"));
        $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_company_website' ,$post_id[0], '$jobInfo[applyUrl]')"));
        $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_expires' ,$post_id[0], '$jobInfo[expirationDate]')"));
        $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_description' ,$post_id[0], '$jobInfo[jobDescription]')"));
        $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_title' ,$post_id[0], '$jobInfo[jobTitle]')"));
        $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_salary' ,$post_id[0], '$pay')"));
        $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_important_info' ,$post_id[0], '$skill')"));




        // wp built in insert
        // $wpdb -> insert('wp_postmeta', array('meta_key' => '_company_name', 'post_id' => $post_id[0], 'meta_value' => $jobInfo[0]));
        // $wpdb -> insert('wp_postmeta', array('meta_key' => '_company_webstie', 'post_id' => $post_id[0], 'meta_value' => $jobInfo[6]));
        // $wpdb -> insert('wp_postmeta', array('meta_key' => '_job_expires', 'post_id' => $post_id[0], 'meta_value' => $jobInfo[8]));
        // $wpdb -> insert('wp_postmeta', array('meta_key' => '_job_description', 'post_id' => $post_id[0], 'meta_value' => $jobInfo[3]));
        // $wpdb -> insert('wp_postmeta', array('meta_key' => '_job_title', 'post_id' => $post_id[0], 'meta_value' => $jobInfo[2]));








        //extra statments
        // $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_title' ,$post_id[0], '$jobInfo[2]')"));
        // $wpdb -> insert('wp_postmeta', array('meta_key' => '_company_webstie', 'post_id' => $post_id, 'meta_value' => $jobInfo[6]));


        //gets user email via publisherID
        $userEmail = $wpdb ->get_col($wpdb ->prepare("SELECT user_email FROM wp_users WHERE ID = $publisherID"));
        //User who posted (this will be updated once we figure out the front end part)
        $wpdb -> insert('wp_postmeta', array('meta_key' => '_application', 'post_id' => $post_id, 'meta_value' => $userEmail));




        //Checks if job is remote if so sets remote to 1
        if(strtolower($jobInfo['jobWorkPlace']) === 'remote'){
                $wpdb -> insert('wp_postmeta', array('meta_key' => '_remote_position', 'post_id' => $post_id, 'meta_value' => '1'));
            }else{
                $wpdb -> insert('wp_postmeta', array('meta_key' => '_remote_position', 'post_id' => $post_id, 'meta_value' => '0'));
                $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_location' ,$post_id[0], '$jobInfo[jobLocation]')"));
        }




        // Sets job type in wp_term_relationships
        switch (strtolower($jobInfo['jobType'])){
            case 'full-time':
            $wpdb -> insert('wp_term_relationships', array('object_id' => $post_id[0], 'term_taxonomy_id'=>3));
            break;
            case 'part-time':
            $wpdb -> insert('wp_term_relationships', array('object_id' => $post_id[0], 'term_taxonomy_id'=>4));
            break;
            case 'temporary':
            $wpdb -> insert('wp_term_relationships', array('object_id' => $post_id[0], 'term_taxonomy_id'=>5));
            break;
            case 'freelance':
            $wpdb -> insert('wp_term_relationships', array('object_id' => $post_id[0], 'term_taxonomy_id'=>6));
            break;
            case 'internship':
            $wpdb -> insert('wp_term_relationships', array('object_id' => $post_id[0], 'term_taxonomy_id'=>7));
            break;
        }
        // adding defaults for post meta
        $wpdb -> insert('wp_postmeta', array('meta_key' => '_thumbnail_id', 'post_id' => $post_id[0], 'meta_value' => ''));
        $wpdb -> insert('wp_postmeta', array('meta_key' => '_company_twitter', 'post_id' => $post_id[0], 'meta_value' => ''));
        $wpdb -> insert('wp_postmeta', array('meta_key' => '_company_video', 'post_id' => $post_id[0], 'meta_value' => ''));
        $wpdb -> insert('wp_postmeta', array('meta_key' => '_company_tagline', 'post_id' => $post_id[0], 'meta_value' => ''));
        $wpdb -> insert('wp_postmeta', array('meta_key' => '_filled', 'post_id' => $post_id[0], 'meta_value' => 0));
        $wpdb -> insert('wp_postmeta', array('meta_key' => '_featured', 'post_id' => $post_id[0], 'meta_value' => 0));
    }


    public function postJob(&$jobInfo, $publisherID){
        global $wpdb;
        //reg expression to get post name close to the live site
        $companyName = strtolower(preg_replace('/\s+/', '-', $jobInfo['companyName']));
        $jobTitle = strtoLower(preg_replace('/\s+/', '-', $jobInfo['jobTitle']));
        $postName = "$companyName-$jobTitle";
        //inseting into the post table
        $wpdb -> query($wpdb -> prepare("INSERT INTO wp_posts (post_author, job_posting_id, post_content, post_title, post_status, comment_status, ping_status, post_name, post_type) VALUES ($publisherID, $jobInfo[jobID], '$jobInfo[jobDescription]', '$jobInfo[jobTitle]', 'publish', 'closed', 'closed', '$postName', 'job_listing')"));
    }




    public function xmlParser(){
        $companyURLS = $this -> retrieveCompanyURLS();
        if($companyURLS){
            $this -> parse_XML($companyURLS);
        }
    }
}




new JJFYXMLParser;
