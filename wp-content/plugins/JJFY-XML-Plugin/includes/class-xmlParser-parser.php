<?php
/**
 * main logic of the plugin
 */
class JJFYXMLParser{
    //
    // Function for pulling company urls from db
    private function retrieveCompanyURLS(){
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
                    $results = $wpdb -> get_results($wpdb -> prepare("SELECT meta_value, date_created FROM wp_frmt_form_entry_meta WHERE meta_key = 'url-1' AND entry_id = $ID"));
                    $date = explode(' ', ($results[0] -> date_created));
                    unset($date[1]);
                    $date = implode(' ',$date);
                    array_push($companyURLS, [$userID[$index], $results[0] -> meta_value, $date]);
                    $index ++;
            }else{
                $index ++;
            }
            }
            return $companyURLS;
        }
    }


    //Parses XML and calls functions relating to job postings
    private function parse_xml(&$companyURLS){
        $job_array = [];
        $jobInfo = [];
        $arrayindex = 0;
        $todaysDate= (strtotime(date('Y-m-d')));
        // print_r($companyURLS[0][2]);
        foreach($companyURLS as $company){
            // getting userID who linked the xml feed
            $publisherID = $company[0];
            array_push($job_array, [$publisherID]);
            // checks if link is vaild xml link if so parses the link
            if (str_contains($company[1], '.xml')){
                $xml = simplexml_load_file($company[1]) or die("Cannot load URL");
                $isCurrent = strtotime($company[2]);
                $lastbuildDate = strtotime($xml-> lastBuildDate);
                // checks if xml loaded is the correct format if so loops over the current ones or if it is a new xml feed
                if($lastbuildDate >= $todaysDate || $isCurrent == $todaysDate){
                    if(!(count($xml -> job) == 0)){
                        foreach ($xml as $jobs){
                        //saving the needed job info into an array
                            if(count($jobs) != 0){
                                $jobInfo = ["companyName" => $jobs -> company, "publisherName" => $xml -> publisher, "jobID" => $jobs -> partnerJobId, "jobTitle" => $jobs -> title, "jobDescription" => $jobs -> description, "skills" => $jobs -> skills -> skill, "experienceLvl" => $jobs -> experienceLevel, "jobFunction" => $jobs ->  jobFunctions -> jobFunction, "jobLocation" => $jobs -> location, 'jobWorkPlace'=> $jobs -> workplaceTypes, "jobType" => $jobs -> jobtype, 'salaryHighEnd'=> $jobs -> salaries -> salary -> highEnd->amount, 'salaryLowEnd'=> $jobs -> salaries -> salary -> lowEnd-> amount, "currencyCode"=> $jobs -> salaries -> salary -> lowEnd-> currencyCode, "expirationDate"=> $jobs -> expirationDate, "applyUrl" => $jobs -> applyUrl];
                            }
                            if (count($jobInfo) != 0){
                                array_push($job_array[$arrayindex], [$jobInfo['jobID']]);
                                $pay= $this -> salaryToString($jobInfo);
                                $skill = $this ->skillsToString($jobInfo);
                                $this-> addPost($jobInfo, $publisherID, $pay, $skill);
                                $this-> updatePost($jobInfo, $publisherID, $pay, $skill);
                            }
                        }
                    }
                }
            }
            $arrayindex ++;
        }
        $this-> deleteOldPost($job_array);
    }

    private function salaryToString(&$jobInfo){
        $payHigh = $jobInfo['salaryHighEnd'];
        $payLow = $jobInfo['salaryLowEnd'];
        $currencyCode = $jobInfo['currencyCode'];
        $pay = "$payLow - $payHigh $currencyCode";
        return $pay;
    }

    private function skillsToString(&$jobInfo){
        $count = 0;
        $skill = [];
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
        return $skill;
    }

    private function getPostID(&$jobInfo, $publisherID){
        global $wpdb;
        //gets post_id of posting above for meta data
        $post_id = $wpdb -> get_var($wpdb -> prepare ("SELECT ID FROM wp_posts WHERE job_posting_id = $jobInfo[jobID] AND post_author = $publisherID"));
        return $post_id;
    }

    private function jobTypeRelationship(&$jobInfo, $post_id){
        global $wpdb;
        $table_name = $wpdb->prefix . 'term_relationships';
        $isPresent = $wpdb -> get_var("SELECT COUNT(*) FROM $table_name WHERE object_id = $post_id");
        if(!$isPresent){
            switch (strtolower($jobInfo['jobType'])){
                case 'full-time':
                    $wpdb -> insert($table_name, array('object_id' => $post_id, 'term_taxonomy_id'=>3));
                    break;
                case 'part-time':
                    $wpdb -> insert($table_name, array('object_id' => $post_id, 'term_taxonomy_id'=>4));
                    break;
                case 'temporary':
                    $wpdb -> insert($table_name, array('object_id' => $post_id, 'term_taxonomy_id'=>5));
                    break;
                case 'freelance':
                    $wpdb -> insert($table_name, array('object_id' => $post_id, 'term_taxonomy_id'=>6));
                    break;
                case 'internship':
                    $wpdb -> insert($table_name, array('object_id' => $post_id, 'term_taxonomy_id'=>7));
                    break;
            }
        }else{
            switch (strtolower($jobInfo['jobType'])){
                case 'full-time':
                    $wpdb -> update($table_name, array('term_taxonomy_id'=>3), array('object_id' => $post_id));
                    break;
                case 'part-time':
                    $wpdb -> update($table_name, array('term_taxonomy_id'=>4), array('object_id' => $post_id));
                    break;
                case 'temporary':
                    $wpdb -> update($table_name, array('term_taxonomy_id'=>5), array('object_id' => $post_id));
                    break;
                case 'freelance':
                    $wpdb -> update($table_name, array('term_taxonomy_id'=>6), array('object_id' => $post_id));
                    break;
                case 'internship':
                    $wpdb -> update($table_name, array('term_taxonomy_id'=>7), array('object_id' => $post_id));
                    break;
            }
        }
    }

    private function setRemoteType(&$jobInfo, $post_id){
        global $wpdb;
        $isPresent = $wpdb -> get_var("SELECT COUNT(*) FROM wp_postmeta WHERE post_id = $post_id AND meta_key = '_remote_position'");
        if(!$isPresent){
            if(strtolower($jobInfo['jobWorkPlace']) === 'remote'){
                add_post_meta($post_id, '_remote_position', '1');
            }else{
                add_post_meta($post_id, '_remote_position', '0');
                add_post_meta($post_id, '_job_location', "$jobInfo[jobLocation]");
            }
        }else{
            if(strtolower($jobInfo['jobWorkPlace']) === 'remote'){
                update_metadata('post', $post_id, '_remote_position', '1');
            }else{
                update_metadata('post',$post_id, '_remote_position', '0');
                update_metadata('post', $post_id, '_job_location', "$jobInfo[jobLocation]");
            }
        }
    }
    private function addPost(&$jobInfo, $publisherID, $pay, &$skill){
        //db global call
        global $wpdb;
        $wpdb ->show_errors();
        //checking if job is present, if so it skips, if it is a new job, it adds to the db. (Might want to update this to check publisher id as well)
        $isPosted = $wpdb -> get_results("SELECT ID FROM wp_posts WHERE post_author = $publisherID AND job_posting_id = $jobInfo[jobID]");
        if (count($isPosted) == 0){
            $wpdb -> query("INSERT INTO wp_job_postings (PublisherID, JobID) VALUES ($publisherID, $jobInfo[jobID])");
            $this->postJob($jobInfo, $publisherID);
            $this->addMetaData($jobInfo, $publisherID, $pay, $skill);
        }
    }

    private function updatePost(&$jobInfo, $publisherID, $pay, &$skill){
        /**
         * checks post information and updates any needed information that has changed. 
         */
        global $wpdb;
        $wpdb -> show_errors();
        /**
         * Pulls post id for the job in question. 
         */
        $postID = $this -> getPostID($jobInfo, $publisherID);
        /**
         * array to hold all the meta data keys, the commented out array is the full array if more meta data is needed down the line.
         * $meta_data = ['_company_name', '_company_website', '_job_expires', '_job_description', '_job_title', '_job_salary', '_job_important_info', '_thumbnail_id', '_company_twitter', '_company_video', '_company_tagline', '_filled', '_featured'];
         */
        $meta_data = ['_company_name', '_company_website', '_job_expires', '_job_description', '_job_title', '_job_salary', '_job_important_info'];
        /**
         * Loops over meta data aray to get keys, checking if any have been updated.
         */
        foreach($meta_data as $keys){
            switch ($keys){
                case '_company_name':
                    update_metadata('post', $postID, $keys, "$jobInfo[companyName]");
                    break;
                case '_company_website':
                    update_metadata('post', $postID, $keys, "$jobInfo[applyUrl]");
                    break;
                case '_job_expires':
                    update_metadata('post', $postID, $keys, "$jobInfo[expirationDate]");
                    break;
                case '_job_description':
                    update_metadata('post', $postID, $keys, "$jobInfo[jobDescription]");
                    break;
                case '_job_title':
                    update_metadata('post', $postID, $keys, "$jobInfo[jobTitle]");
                    break;
                case '_job_salary':
                    update_metadata('post', $postID, $keys, $pay);
                    break;
                case '_job_important_info':
                    update_metadata('post', $postID, $keys, $skill);
                    break;
            }
        }
        
        /**
         * checking if the job type has changed
         */
        $this -> jobTypeRelationship($jobInfo, $postID);

        /**
         * checking if the job has changed locations
         */
        $this -> setRemoteType($jobInfo, $postID);

        /**
         * Checking if any of the main job posting has changed. 
         */
        $this ->postJob($jobInfo, $publisherID);
    }

    private function deleteOldPost(&$job_array){
        global $wpdb;
        $wpdb ->show_errors();
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

        // var_dump($jobIDS[0]);
        unset($jobIDS[0][2]);
        unset($jobIDS[0][1]);
        // unset($jobIDS[0][2]);
        // var_dump($jobIDS[0]);
        // var_dump($jobIDS);
        $index = 0;
        // print_r($jobIDS[0]);
        foreach($jobIDS as $id){
            // print_r($id);
            $test = implode(', ', $id);
            // print_r($test);
            $sql = "SELECT ID FROM `wp_posts` WHERE `post_author`= $publisherID[$index] and `job_posting_id` NOT IN ($test)";
            $sql1 =$wpdb -> get_results($sql);
            // print_r($sql1);
            // print_r($sql1);
            if(!empty($sql1)){
                $jobid = null; 
                foreach($sql1 as $ids){
                    if(!$jobid){
                        $jobid = (string) $ids -> ID;
                    }else{
                        $tempID = (string) $ids -> ID;
                        $jobid.=", $tempID";
                    }
                }
            }
            // print_r($sql1[0]);
            // $test = (string) $sql1 -> ID;
            //take array of stdClass Objects and get id to string
            // $wpdb->delete($table_name, array('post_id' => $sql1), array('%d'));
            // print_r($sql1);
            $index ++;
        }
        // $sql = "SELECT ID FROM `wp_posts` WHERE `post_author`= 2 and `job_posting_id` NOT IN (54321)";
        // $sql = "SELECT ID FROM `wp_posts` WHERE `post_author`= 2 and `job_posting_id` NOT IN (54321)";
        // $sql1 =$wpdb -> get_results($sql);
        // print_r($sql1);
        // print_r($sql1);
        // $test = implode(', ', $sql1);
        // print_r($test);
        // foreach($sql1 as $jobID){
        //     $test = null;
        //     if($test == null){
        //         $test = $jobID[0];
        //     }else{
        //         $test.=", $jobID";
        //     }
        //     print_r($test);
        // }
        // $table_name = $wpdb->prefix . 'posts';
        // print_r($table_name);
        // $results = $wpdb->delete($table_name, array('ID' => $sql1), array('%d'));
        // $table_name = $wpdb->prefix . 'postmeta';
        // $wpdb->delete($table_name, array('post_id' => $sql1), array('%d'));
        // $sql = "DELETE * FROM wp_post WHERE 'ID' = $sql1[0]";
        // $wpdb -> query($sql);
        // print_r($results);

    /**
     *  loop over the arrays by index.
     *  select publisherID and JobID $wpdb -> get_results("SELECT PublisherID, JobID FROM `wp_job_postings` WHERE `PublisherID` = publisherID[index] AND `JobID` NOT IN (jobIDS[index])");
     *  get post id from post table.
     *  delete meta data and post
    */    
    }
    private function addMetaData(&$jobInfo, $publisherID, $pay, &$skill){
        global $wpdb;
        //gets post_id of posting above for meta data
        $post_id = $this -> getPostID($jobInfo, $publisherID);
        //gets user email via publisherID
        $userEmail = $wpdb ->get_var($wpdb ->prepare("SELECT user_email FROM wp_users WHERE ID = $publisherID"));
        //Adding reletive post meta.
        add_post_meta($post_id, '_company_name', "$jobInfo[companyName]");
        add_post_meta($post_id, '_company_website', "$jobInfo[applyUrl]");
        add_post_meta($post_id, '_job_expires', "$jobInfo[expirationDate]");
        add_post_meta($post_id, '_job_description', "$jobInfo[jobDescription]");
        add_post_meta($post_id, '_job_title', "$jobInfo[jobTitle]");
        add_post_meta($post_id, '_job_salary', $pay);
        add_post_meta($post_id, '_job_important_info', $skill);
        add_post_meta($post_id, '_application', $userEmail);
        // adding defaults for post meta
        add_post_meta($post_id, '_thumbnail_id', '');
        add_post_meta($post_id, '_company_twitter', '');
        add_post_meta($post_id, '_company_video', '');
        add_post_meta($post_id, '_company_tagline', '');
        add_post_meta($post_id, '_filled', '');
        add_post_meta($post_id, '_featured', '');
        //Checks if job is remote
        $this -> setRemoteType($jobInfo, $post_id);
        // Sets job type in wp_term_relationships
        $this -> jobTypeRelationship($jobInfo, $post_id);
        // adding defaults for post meta
        add_post_meta($post_id, '_thumbnail_id', '');
        add_post_meta($post_id, '_company_twitter', '');
        add_post_meta($post_id, '_company_video', '');
        add_post_meta($post_id, '_company_tagline', '');
        add_post_meta($post_id, '_filled', '');
        add_post_meta($post_id, '_featured', '');
    }

    private function postJob(&$jobInfo, $publisherID){
    global $wpdb;
    /** checking if job has been posted, if so, check if any information has changed, if not post the job. */
    $postID = $wpdb -> get_var("SELECT ID FROM wp_posts WHERE post_author = $publisherID AND job_posting_id = $jobInfo[jobID]");
    //reg expression to get post name close to the live site
    $companyName = strtolower(preg_replace('/\s+/', '-', $jobInfo['companyName']));
    $jobTitle = strtoLower(preg_replace('/\s+/', '-', $jobInfo['jobTitle']));
    $postName = "$companyName-$jobTitle";
    $table_name = $wpdb->prefix . 'posts';
    if(!$postID){
        //inseting into the post table
        $wpdb -> insert($table_name, array('post_author' => $publisherID, 'job_posting_id' => "$jobInfo[jobID]", 'post_content' => "$jobInfo[jobDescription]", 'post_title' => "$jobInfo[jobTitle]", 'post_status' => 'publish', 'ping_status' => 'closed', 'comment_status' => 'closed', 'post_name' => $postName, 'post_type' => 'job_listing'), array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));
    }else{
        $wpdb -> update($table_name, array('post_author' => $publisherID, 'job_posting_id' => "$jobInfo[jobID]", 'post_content' => "$jobInfo[jobDescription]", 'post_title' => "$jobInfo[jobTitle]", 'post_status' => 'publish', 'ping_status' => 'closed', 'comment_status' => 'closed', 'post_name' => $postName, 'post_type' => 'job_listing'), array('post_author' => $publisherID, 'ID' => $postID) , array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));
    }
    }

    public function xmlParser(){
        $companyURLS = $this -> retrieveCompanyURLS();
        if($companyURLS){
            $this -> parse_XML($companyURLS);
        }
    }
}