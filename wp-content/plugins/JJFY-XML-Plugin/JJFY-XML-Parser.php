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
class JJFYXMLParser{
public function __construct()
{
add_action('init', array($this,'xmlParser'));
}


// Function for pulling company urls from db
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
// public function retrieveCompanyURLS(){
// //This will be for when we deploy if we use forminator it will replace the current url function
// global $wpdb;
// $companyURLS = [];
// $index = 0;
// //Getting userID and postID from the Table that hold them
// $formData = $wpdb -> get_results("SELECT meta_value, entry_id FROM wp_frmt_form_entry_meta WHERE meta_key = 'hidden-1'");
// //splitting them into their own arrays
// $postID = array_column($formData, 'entry_id');
// $userID = array_column($formData, 'meta_value');
// foreach ($postID as $ID){
// //checking if the postID is from the URL form. If it is pulling the url in and adding it to the array of urls.
// $results = $wpdb -> get_var("SELECT COUNT(*) FROM wp_frmt_form_entry WHERE form_id = 228 AND entry_id = $ID");
// if($results == 1){
// $url = $wpdb -> get_col($wpdb -> prepare("SELECT meta_value FROM wp_frmt_form_entry_meta WHERE meta_key = 'url-1' AND entry_id = $ID"));
// array_push($companyURLS, [$userID[$index], $url]);
// $index ++;
// }else{
// $index ++;
// }
// }
// return $companyURLS;
// }


//Parses XML and calls functions relating to job postings
public function parse_xml(&$companyURLS){
$job_array = [];
$jobInfo = [];
$publisherID = [];
foreach($companyURLS as $company){
//adding info for prod --delete this line before we add it to live --
//getting userID who linked the xml feed
// array_push($publisherID, $company[0]);
// pulls xml data from the url array passed to it
// $xml = simplexml_load_file($company[1][0]) or die("Cannot load URL");
// pulls xml data from the url array passed to it
$xml = simplexml_load_file($company) or die("Cannot load URL");
// loops over the job postings
foreach ($xml as $jobs){
//saving the needed job info into an array
if(count($jobs) != 0){
$jobInfo = ["companyName" => $jobs -> company, "publisherName" => $xml -> publisher, "jobID" => $jobs -> partnerJobId, "jobTitle" => $jobs -> title, "jobDescription" => $jobs -> description, "jobLocation" => $jobs -> location, 'jobWorkPlace'=> $jobs -> workplaceTypes, "jobType" => $jobs -> jobtype, 'salaryHighEnd'=> $jobs -> salaries -> salary -> highEnd->amount, 'salaryLowEnd'=> $jobs -> salaries -> salary -> lowEnd-> amount, "currencyCode"=> $jobs -> salaries -> salary -> lowEnd-> currencyCode, "expirationDate"=> $jobs -> expirationDate, "applyUrl" => $jobs -> applyUrl];
array_push($job_array,[$xml -> publisher, $jobs-> partnerJobId]);


}
if (count($jobInfo) != 0){
$this-> addPost($jobInfo);
// $this-> updatePost(($jobInfo));
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
$publisherID = $wpdb -> get_col( $wpdb -> prepare ("SELECT PublisherID FROM wp_xml_links WHERE PublisherName = '$jobInfo[publisherName]'"));
// this will be the sql call for prod
$isPresent = $wpdb -> get_results("SELECT PublisherID, JobID FROM wp_job_postings WHERE JobID = '$jobInfo[jobID]' AND PublisherID = $publisherID[0]");
if (count($isPresent) == 0){
$wpdb -> query("INSERT INTO wp_job_postings (PublisherID, CompanyName, JobID, JobTitle, JobDesc) VALUES ($publisherID[0], '$jobInfo[companyName]', $jobInfo[jobID], '$jobInfo[jobTitle]', '$jobInfo[jobDescription]')");
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
$publisherID = $wpdb -> get_col( $wpdb -> prepare ("SELECT PublisherID FROM wp_xml_links WHERE PublisherName = '$jobInfo[publisherName]'"));
//checks if data needs updated if it does, updates the data (this can be changed to the update class from wpdb)
$wpdb -> query($wpdb -> prepare("UPDATE wp_job_postings SET CompanyName = '$jobInfo[companyName]', JobID = $jobInfo[jobID], JobTitle = '$jobInfo[jobTitle]', JobDesc = '$jobInfo[jobDescription]' WHERE PublisherID = $publisherID[0] AND JobID = $jobInfo[jobID]"));
}
public function deleteOldPost(&$job_array){
/**
* I am not 100% sure if there is gonna be a easy way to to this. Might have to do a custom query here.
* Could make an array of all job posting id/publisher id in the parsing function, pass that down then compare the db to that and see if there is any that is not in the array. If so, delete them.
* will also have to delete/trash the post/post meta info
*/
// global $wpdb;
// $publisherID = [];
// $jobIDS = [];
// foreach($job_array as $job){
// array_push($publisherID, $wpdb -> get_var( $wpdb -> prepare ("SELECT PublisherID FROM wp_xml_links WHERE PublisherName = '$job[0]'")));
// foreach($job as $jobID){
// array_push($jobIDS, $job[1]);
// }
// }
// $publisherID = array_unique($publisherID);
// $jobIDS= array_unique($jobIDS);


//checking if any jobs have been deleted if so, delete them
// DELETE FROM `wp_job_postings` WHERE JobID AND PublisherID NOT IN (1114, 4)
// $wpdb -> query($wpdb -> prepare("DELETE * FROM wp_job_postings WHERE "));
}
public function addMetaData(&$jobInfo){
//if anyone can get the built in inserts to work it will be cleaner leaving them there for now.
global $wpdb;
//setting var for salary
$payHigh = $jobInfo['salaryHighEnd'];
$payLow = $jobInfo['salaryLowEnd'];
$currencyCode = $jobInfo['currencyCode'];
$pay = "$payLow - $payHigh $currencyCode";
//gets post_id of posting above for meta data
$post_id = $wpdb -> get_col($wpdb -> prepare ("SELECT ID FROM wp_posts WHERE job_posting_id = $jobInfo[jobID] AND post_author = 1"));
if ($post_id != null || 0){
//Adding reletive post meta.
// //add post meta
// add_post_meta($post_id, '_company_name', $jobInfo[0]);
// add_post_meta($post_id, '_company_website', $jobInfo[6]);
// add_post_meta($post_id, '_job_expires', $jobInfo[8]);
// add_post_meta($post_id, '_job_description', $jobInfo[3]);
// add_post_meta($post_id, '_job_title', $jobInfo[2]);
// add_post_meta($post_id, '_job_salary', $pay);


//normal insert
$wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_company_name' ,$post_id[0], '$jobInfo[companyName]')"));
$wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_company_website' ,$post_id[0], '$jobInfo[applyUrl]')"));
$wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_expires' ,$post_id[0], '$jobInfo[expirationDate]')"));
$wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_description' ,$post_id[0], '$jobInfo[jobDescription]')"));
$wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_title' ,$post_id[0], '$jobInfo[jobTitle]')"));
$wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_salary' ,$post_id[0], '$pay')"));


// wp built in insert
// $wpdb -> insert('wp_postmeta', array('meta_key' => '_company_name', 'post_id' => $post_id[0], 'meta_value' => $jobInfo[0]));
// $wpdb -> insert('wp_postmeta', array('meta_key' => '_company_webstie', 'post_id' => $post_id[0], 'meta_value' => $jobInfo[6]));
// $wpdb -> insert('wp_postmeta', array('meta_key' => '_job_expires', 'post_id' => $post_id[0], 'meta_value' => $jobInfo[8]));
// $wpdb -> insert('wp_postmeta', array('meta_key' => '_job_description', 'post_id' => $post_id[0], 'meta_value' => $jobInfo[3]));
// $wpdb -> insert('wp_postmeta', array('meta_key' => '_job_title', 'post_id' => $post_id[0], 'meta_value' => $jobInfo[2]));




//extra statments
// $wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_title' ,$post_id[0], '$jobInfo[2]')"));
// $wpdb -> insert('wp_postmeta', array('meta_key' => '_company_webstie', 'post_id' => $post_id, 'meta_value' => $jobInfo[6]));


//User who posted (this will be updated once we figure out the front end part)
$wpdb -> insert('wp_postmeta', array('meta_key' => '_application', 'post_id' => $post_id, 'meta_value' => 'awfriend77@gmail.com'));


//Checks if job is remote if so sets remote to 1
if(strtolower($jobInfo['jobWorkPlace']) === 'remote'){
$wpdb -> insert('wp_postmeta', array('meta_key' => '_remote_position', 'post_id' => $post_id, 'meta_value' => '1'));
}else{
$wpdb -> insert('wp_postmeta', array('meta_key' => '_remote_position', 'post_id' => $post_id, 'meta_value' => '0'));
$wpdb -> query($wpdb -> prepare("INSERT INTO wp_postmeta (meta_key, post_id, meta_value) VALUES ('_job_location' ,$post_id[0], '$jobInfo[jobLocation]')"));
// $wpdb -> insert('wp_postmeta', array('meta_key' => '_job_location', 'post_id' => $post_id, 'meta_value' => $jobInfo[9]));
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
$wpdb -> insert('wp_postmeta', array('meta_key' => '_company_video', 'post_id' => $post_id[0], 'meta_value' => ''));
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
$companyName = strtolower(preg_replace('/\s+/', '-', $jobInfo['companyName']));
$jobTitle = strtoLower(preg_replace('/\s+/', '-', $jobInfo['jobTitle']));
$postName = "$companyName-$jobTitle";
print_r($publisherID[0]);
//inseting into the post table
$wpdb -> query($wpdb -> prepare("INSERT INTO wp_posts (post_author, job_posting_id, post_content, post_title, post_status, comment_status, ping_status, post_name, post_type) VALUES (1, $jobInfo[jobID], '$jobInfo[jobDescription]', '$jobInfo[jobTitle]', 'publish', 'closed', 'closed', '$postName', 'job_listing')"));
}


public function xmlParser(){
$companyURLS = $this -> retrieveCompanyURLS();
$this -> parse_XML($companyURLS);
}
}


new JJFYXMLParser;

