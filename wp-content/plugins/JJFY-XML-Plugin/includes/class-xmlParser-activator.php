<?php

class xmlParser_Activator{

    public function RegisterSettings(){
        add_option('xml_form_id');
    }
    public function init_db_XMLParse(){
        global $wpdb, $table_prefix;
        //Sets table var for wp-job-postings
        $jobTable = $table_prefix.'job_postings';
        $charset_collate = $wpdb->get_charset_collate();
        if ($wpdb -> get_var("show tables like '$jobTable'") !=  $jobTable){
            //Create table query
            $sql = "CREATE TABLE " .$jobTable. "(
                PublisherID int(11) NOT NULL,
                JobID varchar(40) NOT NULL)
                $charset_collate;";
            require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
            dbDelta($sql);
        }
    }
    public function activate(){
        //Inserts DB Table
        $this -> init_db_XMLParse();
        //Add in settings 
        $this -> RegisterSettings();
        //create a new cronjob
        if (!wp_next_scheduled('xmlParse')){
            wp_schedule_event(time(), 'daily', 'xmlParser');
        }
    }
}