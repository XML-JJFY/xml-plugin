<?php

/**
 * main logic of the plugin
 */
class JJFYXMLParser
{
    // Function for pulling company urls from db
    private function retrieveCompanyURLS()
    {
        global $wpdb, $formID;
        $wpdb -> show_errors();
        $companyURLS = [];
        $index = 0;
        $tableentry = $wpdb->prefix . 'frmt_form_entry';
        $table = $wpdb->prefix . 'frmt_form_entry_meta';
        //Getting userID and postID from the Table that hold them
        if (!empty($formID)) {
            $formData = $wpdb->get_results($wpdb->prepare("SELECT meta_value, $table.entry_id FROM $table JOIN $tableentry ON $table.entry_id = $tableentry.entry_id WHERE meta_key = 'hidden-1' AND $tableentry.form_id = $formID"));
            //splitting them into their own arrays
            $postID = array_column($formData, 'entry_id');
            $userID = array_column($formData, 'meta_value');
            foreach ($postID as $ID) {
                //checking if the postID is from the URL form. If it is pulling the url in and adding it to the array of urls.
                $results = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tableentry WHERE form_id = $formID AND entry_id = $ID"));
                if ($results == 1) {
                    $results = $wpdb->get_results($wpdb->prepare("SELECT meta_value, date_created FROM $table WHERE meta_key = 'url-1' AND entry_id = $ID"));
                    $date = explode(' ', ($results[0]->date_created));
                    unset($date[1]);
                    $date = implode(' ', $date);
                    array_push($companyURLS, [$userID[$index], $results[0]->meta_value, $date]);
                    $index++;
                } else {
                    $index++;
                }
            }
            return $companyURLS;
        }
    }


    //Parses XML and calls functions relating to job postings
    private function parse_xml(&$companyURLS)
    {
        $job_array = [];
        $jobInfo = [];
        $arrayindex = 0;
        $cutoffDate = strtotime(date("Y-m-d", strtotime("yesterday")));
        foreach ($companyURLS as $company) {
            // getting userID who linked the xml feed
            $publisherID = $company[0];
            array_push($job_array, [$publisherID]);
            // checks if link is vaild xml link if so parses the link
            if (str_contains($company[1], '.xml')) {
                $xml = simplexml_load_file($company[1]) or die("Cannot load URL");
                $isCurrent = strtotime($company[2]);
                $lastbuildDate = strtotime($xml->lastBuildDate);
                // checks if xml loaded is the correct format if so loops over the current ones or if it is a new xml feed
                //comment this line back in after checking
                if ($lastbuildDate >= $cutoffDate || $isCurrent >= $cutoffDate) {
                    if (!(count($xml->job) == 0)) {
                        foreach ($xml as $jobs) {
                            //saving the needed job info into an array
                            if (count($jobs) != 0) {
                                $jobInfo = ["publisherName" => $xml->publisher, "jobID" => $jobs->partnerJobId, "jobTitle" => $jobs->title, "jobDescription" => $jobs->description, "skills" => $jobs->skills->skill, "experienceLvl" => $jobs->experienceLevel, "jobFunction" => $jobs->jobFunctions->jobFunction, "jobLocation" => $jobs->location, 'jobWorkPlace' => $jobs->workplaceTypes, "jobType" => $jobs->jobtype, 'salaryHighEnd' => $jobs->salaries->salary->highEnd->amount, 'salaryLowEnd' => $jobs->salaries->salary->lowEnd->amount, "currencyCode" => $jobs->salaries->salary->lowEnd->currencyCode, "expirationDate" => $jobs->expirationDate, "applyUrl" => $jobs->applyUrl];
                                if ($jobs->company) {
                                    $jobInfo['companyName'] = $jobs->company;
                                } else {
                                    $jobInfo['companyName'] = $xml->publisher;
                                }
                            }
                            if (count($jobInfo) != 0) {
                                array_push($job_array[$arrayindex], [$jobInfo['jobID']]);
                                $pay = $this->salaryToString($jobInfo);
                                $skill = $this->skillsToString($jobInfo);
                                $this->addPost($jobInfo, $publisherID, $pay, $skill);
                                $this->updatePost($jobInfo, $publisherID, $pay, $skill);
                            }
                        }
                    }
                }
            }
            $arrayindex++;
        }
        $this->deleteOldPost($job_array);
    }

    private function salaryToString(&$jobInfo)
    {
        $payHigh = $jobInfo['salaryHighEnd'];
        $payLow = $jobInfo['salaryLowEnd'];
        $currencyCode = $jobInfo['currencyCode'];
        $pay = "$payLow - $payHigh $currencyCode";
        return $pay;
    }

    private function skillsToString(&$jobInfo)
    {
        $count = 0;
        $skill = [];
        $skills = $jobInfo["skills"];
        $jobFunction = $jobInfo["jobFunction"];
        while ($count < count($skills)) {
            array_push($skill, $skills[$count]);
            $count++;
        }
        $count = 0;
        while ($count < count($jobFunction)) {
            array_push($skill, $jobFunction[$count]);
            $count++;
        }
        array_push($skill, $jobInfo["experienceLvl"]);
        $skill = implode(', ', $skill);
        return $skill;
    }

    private function getPostID(&$jobInfo, $publisherID)
    {
        /**
         * Gets post id from job_posting and checks if id is vaild if it is returns the id.
         */
        global $wpdb;
        $table = $wpdb->prefix . 'job_postings';
        $postTable = $wpdb->prefix . 'posts';
        $post_id = $wpdb->get_var($wpdb->prepare("SELECT PostID FROM $table WHERE PublisherID = $publisherID AND JobID = $jobInfo[jobID]"));
        if ($post_id) {
            $post_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $postTable WHERE ID = $post_id"));
        }
        if ($post_id == null) {
            return $post_id = 0;
        } else {
            return $post_id;
        }
    }

    private function jobTypeRelationship(&$jobInfo, $post_id)
    {
        /**
         * matches the job type with the taxonomy info
         */
        global $wpdb, $fullTime, $partTime, $contractor, $temporary, $intern;
        $table_name = $wpdb->prefix . 'term_relationships';
        $isPresent = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE object_id = $post_id"));
        if (!$isPresent) {
            switch (strtolower($jobInfo['jobType'])) {
                case 'full-time':
                    $wpdb->insert($table_name, array('object_id' => $post_id, 'term_taxonomy_id' => $fullTime));
                    break;
                case 'part-time':
                    $wpdb->insert($table_name, array('object_id' => $post_id, 'term_taxonomy_id' => $partTime));
                    break;
                case 'temporary':
                    $wpdb->insert($table_name, array('object_id' => $post_id, 'term_taxonomy_id' => $contractor));
                    break;
                case 'freelance':
                    $wpdb->insert($table_name, array('object_id' => $post_id, 'term_taxonomy_id' => $temporary));
                    break;
                case 'internship':
                    $wpdb->insert($table_name, array('object_id' => $post_id, 'term_taxonomy_id' => $intern));
                    break;
            }
        } else {
            switch (strtolower($jobInfo['jobType'])) {
                case 'full-time':
                    $wpdb->update($table_name, array('term_taxonomy_id' => $fullTime), array('object_id' => $post_id));
                    break;
                case 'part-time':
                    $wpdb->update($table_name, array('term_taxonomy_id' => $partTime), array('object_id' => $post_id));
                    break;
                case 'temporary':
                    $wpdb->update($table_name, array('term_taxonomy_id' => $contractor), array('object_id' => $post_id));
                    break;
                case 'freelance':
                    $wpdb->update($table_name, array('term_taxonomy_id' => $temporary), array('object_id' => $post_id));
                    break;
                case 'internship':
                    $wpdb->update($table_name, array('term_taxonomy_id' => $intern), array('object_id' => $post_id));
                    break;
            }
        }
    }

    private function setRemoteType(&$jobInfo, $post_id)
    {
        global $wpdb;
        $isPresent = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM wp_postmeta WHERE post_id = $post_id AND meta_key = '_remote_position'"));
        if (!$isPresent) {
            if (strtolower($jobInfo['jobWorkPlace']) === 'remote') {
                add_post_meta($post_id, '_remote_position', '1');
            } else {
                add_post_meta($post_id, '_remote_position', '0');
                add_post_meta($post_id, '_job_location', "$jobInfo[jobLocation]");
            }
        } else {
            if (strtolower($jobInfo['jobWorkPlace']) === 'remote') {
                update_metadata('post', $post_id, '_remote_position', '1');
            } else {
                update_metadata('post', $post_id, '_remote_position', '0');
                update_metadata('post', $post_id, '_job_location', "$jobInfo[jobLocation]");
            }
        }
    }
    private function addPost(&$jobInfo, $publisherID, $pay, &$skill)
    {
        //db global call
        global $wpdb;
        $jobPostingTable = $wpdb->prefix . 'job_postings';
        //checking if job is present, if so it skips, if it is a new job, it adds to the db. (Might want to update this to check publisher id as well)
        $ID = $this->getPostID($jobInfo, $publisherID);
        if (!$ID) {
            $results = $wpdb->get_var($wpdb->prepare("SELECT PostID FROM $jobPostingTable WHERE PublisherID = $publisherID AND JobID =  $jobInfo[jobID]"));
            if ($results) {
                $wpdb->update($jobPostingTable, array('PostID' => NULL), array('PublisherID' => $publisherID, 'JobID' => "$jobInfo[jobID]", 'PostID' => $results));
            } else {
                $wpdb->insert($jobPostingTable, array('PublisherID' => $publisherID, 'JobID' => "$jobInfo[jobID]"));
            }
            $this->postJob($jobInfo, $publisherID);
            $this->addMetaData($jobInfo, $publisherID, $pay, $skill);
        }
    }

    private function updatePost(&$jobInfo, $publisherID, $pay, &$skill)
    {
        /**
         * checks post information and updates any needed information that has changed. 
         */
        global $wpdb;
        $todaysDate = strtotime(date("Y-m-d"));
        $postTable = $wpdb->prefix . 'posts';
        /**
         * Pulls post id for the job in question. 
         */
        $postID = $this->getPostID($jobInfo, $publisherID);
        /**
         * array to hold all the meta data keys, the commented out array is the full array if more meta data is needed down the line.
         * $meta_data = ['_company_name', '_company_website', '_job_expires', '_job_description', '_job_title', '_job_salary', '_job_important_info', '_thumbnail_id', '_company_twitter', '_company_video', '_company_tagline', '_filled', '_featured'];
         */
        $meta_data = ['_company_name', '_company_website', '_job_expires', '_job_description', '_job_title', '_job_salary', '_job_important_info'];
        /**
         * Loops over meta data aray to get keys, checking if any have been updated.
         */
        foreach ($meta_data as $keys) {
            switch ($keys) {
                case '_company_name':
                    update_metadata('post', $postID, $keys, "$jobInfo[companyName]");
                    break;
                case '_company_website':
                    update_metadata('post', $postID, $keys, "$jobInfo[applyUrl]");
                    break;
                case '_job_expires':
                    update_metadata('post', $postID, $keys, "$jobInfo[expirationDate]");
                    if($todaysDate > (strtotime($jobInfo['expirationDate']))){
                        $wpdb -> update($postTable, array('post_status' => 'expired'), array('ID' => $postID));
                    }
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
        $this->jobTypeRelationship($jobInfo, $postID);

        /**
         * checking if the job has changed locations
         */
        $this->setRemoteType($jobInfo, $postID);

        /**
         * Checking if any of the main job posting has changed. 
         */
        $this->updateMainJobListing($jobInfo, $publisherID, $postID);
    }

    private function deleteOldPost(&$job_array)
    {
        global $wpdb;
        $wpdb->show_errors();
        $idTable = $wpdb->prefix . "job_postings";
        $postTable = $wpdb->prefix . "posts";
        $metaTable = $wpdb->prefix . "postmeta";
        $publisherID = [];
        $jobIDS = [];
        $arrayIndex = 0;
        //pulls information needed to check for old post
        while ($arrayIndex < count($job_array)) {
            array_push($publisherID, $job_array[$arrayIndex][0]);
            unset($job_array[$arrayIndex][0]);
            foreach ($job_array[$arrayIndex] as $job) {
                if (!array_key_exists($arrayIndex, $jobIDS)) {
                    array_push($jobIDS, [implode('', $job)]);
                } else {
                    array_push($jobIDS[$arrayIndex], implode('', $job));
                }
            }
            $arrayIndex++;
        }
        $index = 0;
        foreach ($jobIDS as $id) {
            $stringID = implode(', ', $id);
            $sql = "SELECT PostID FROM $idTable WHERE `PublisherID`= $publisherID[$index] and `JobID` NOT IN ($stringID)";
            $sql1 = $wpdb->get_results($wpdb->prepare($sql));
            if (!empty($sql1)) {
                $jobid = null;
                foreach ($sql1 as $ids) {
                    if (!$jobid) {
                        $jobid = (string) $ids->PostID;
                    } else {
                        $tempID = (string) $ids->PostID;
                        $jobid .= ", $tempID";
                    }
                }
                $sql = "DELETE FROM $idTable WHERE `PublisherID`= $publisherID[$index] AND `PostID` IN ($jobid)";
                $wpdb->query($wpdb->prepare($sql));
                $sql = "DELETE FROM $postTable WHERE `post_author`= $publisherID[$index] AND `ID` IN ($jobid)";
                $wpdb->query($wpdb->prepare($sql));
                $sql = "DELETE FROM $metaTable WHERE  `post_id` IN ($jobid)";
            }
            $index++;
        }
    }
    private function deleteExpiredPost()
    {
        /**
         * Get post ids of Job listings that are expired. Deleting both the post and the meta data.
         */
        global $wpdb;
        $postTable = $wpdb->prefix . "posts";
        $metaTable = $wpdb->prefix . "postmeta";
        $jobListingTable = $wpdb->prefix . "job_postings";
        $sql = $wpdb->prepare("SELECT ID FROM $postTable WHERE `post_type`= 'job_listing' AND `post_status` = 'expired'");
        $postID = $wpdb->get_results($sql);
        foreach ($postID as $ID) {
            $wpdb->delete($metaTable, array('post_id' => $ID->ID));
            $wpdb->delete($jobListingTable, array('PostID' => $ID->ID));
        }
        $wpdb->delete($postTable, array('post_type' => 'job_listing', 'post_status' => 'expired'));
    }
    private function addMetaData(&$jobInfo, $publisherID, $pay, &$skill)
    {
        global $wpdb;
        //gets post_id of posting above for meta data
        $post_id = $this->getPostID($jobInfo, $publisherID);
        //gets user email via publisherID
        $userEmail = $wpdb->get_var($wpdb->prepare("SELECT user_email FROM wp_users WHERE ID = $publisherID"));
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
        $this->setRemoteType($jobInfo, $post_id);
        // Sets job type in wp_term_relationships
        $this->jobTypeRelationship($jobInfo, $post_id);
        // adding defaults for post meta
        add_post_meta($post_id, '_thumbnail_id', '');
        add_post_meta($post_id, '_company_twitter', '');
        add_post_meta($post_id, '_company_video', '');
        add_post_meta($post_id, '_company_tagline', '');
        add_post_meta($post_id, '_filled', '');
        add_post_meta($post_id, '_featured', '');
    }

    private function postJob(&$jobInfo, $publisherID)
    {
        global $wpdb, $xml_admin_approval;
        /**
         * call to make post name 
         */
        $postName = $this->makePostName($jobInfo, null);
        /** checking if job has been posted, if so, check if any information has changed, if not post the job. */
        $postID = $this->getPostID($jobInfo, $publisherID);
        $table_name = $wpdb->prefix . 'posts';
        $jobsTable = $wpdb->prefix . 'job_postings';
        $guidURL = get_site_url(null, null, 'https');
        $guidURL .= "/job/$postName";
        if (!$postID) {
            //inseting into the post table
            $wpdb->insert($table_name, array('post_author' => $publisherID, 'post_content' => "$jobInfo[jobDescription]", 'post_title' => "$jobInfo[jobTitle]", 'post_status' => $xml_admin_approval, 'ping_status' => 'closed', 'comment_status' => 'closed', 'post_name' => $postName, 'post_type' => 'job_listing', 'guid' => $guidURL), array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));
            $id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $table_name WHERE post_author = $publisherID AND post_name='$postName'"));
            $wpdb->update($jobsTable, array('PostID' => $id), array('PublisherID' => $publisherID, 'JobID' => "$jobInfo[jobID]"));
            $postName .= "$id";
            $wpdb->update($table_name, array('post_name' => $postName), array('ID' => $id));
        }
    }
    private function updateMainJobListing(&$jobInfo, $publisherID, $postID)
    {
        // post_content, post_title, post_name, 
        global $wpdb;
        $postTable = $wpdb->prefix . 'posts';
        $oldData = $wpdb->get_row($wpdb->prepare("SELECT `post_content`, `post_title` FROM $postTable WHERE ID = $postID AND `post_status` != 'expired'"));
        if ($oldData) {
            foreach ($oldData as $keys => $value) {
                switch ($keys) {
                    case 'post_content':
                        if ($value != $jobInfo['jobDescription']) {
                            $wpdb->update($postTable, array('post_content' => "$jobInfo[jobDescription]"), array('ID' => $postID));
                        }
                        break;
                    case 'post_title':
                        if ($value != $jobInfo['jobTitle']) {
                            $wpdb->update($postTable, array('post_title' => "$jobInfo[jobTitle]"), array('ID' => $postID));
                            $newJobName = $this->makePostName($jobInfo, $postID);
                            $wpdb->update($postTable, array('post_name' => $newJobName), array('ID' => $postID));
                        }
                        break;
                }
            }
        }
    }
    private function makePostName(&$jobInfo, $postID)
    {
        /**
         * returns post name, using company name jobTitle and postID if passed in
         */
        /**
         * checks for any trailing punctuation if so delete them
         * then add then name and job title together to get url name
         */

        $jobTitleInfoTemp = [$jobInfo['companyName'], $jobInfo['jobTitle']];
        $jobTitleInfo = [];
        $pattern = '/[[:punct:]]+$/';
        foreach ($jobTitleInfoTemp as $info) {

            if (preg_match($pattern, $info)) {
                $info = preg_replace($pattern, '', $info);
            }
            array_push($jobTitleInfo, (strtolower(preg_replace('/\s+/', '-', $info))));
        }
        $postName = "$jobTitleInfo[0]-$jobTitleInfo[1]";
        if ($postID) {
            $postName .= "-$postID";
            return $postName;
        } else {
            return $postName;
        }
    }
    public function xmlParser()
    {
        $companyURLS = $this->retrieveCompanyURLS();
        if ($companyURLS) {
            $this->parse_XML($companyURLS);
        }
        $this->deleteExpiredPost();
    }
}
