<?php

class xmlParser_Activator{

    private function RegisterSettings(){
        add_option('xml_form_id');
        add_option('xml_parser_Full_Time');
        add_option('xml_parser_Part_Time');
        add_option('xml_parser_Temporary');
        add_option('xml_parser_Contractor');
        add_option('xml_parser_Intern');
    }
    private function init_db_XMLParse(){
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
    private function init_employment_term_meta(){
        global $wpdb;
        $table_name = $wpdb -> prefix. 'termmeta';
        $query = "SELECT term_id FROM $table_name WHERE meta_key ='employment_type'";
        $metaArray = $wpdb -> get_col($query);
        update_option('xml_parser_Full_Time', $metaArray[0]);
        update_option('xml_parser_Part_Time', $metaArray[1]);
        update_option('xml_parser_Temporary', $metaArray[2]);
        update_option('xml_parser_Contractor', $metaArray[3]);
        update_option('xml_parser_Intern', $metaArray[4]);
    }
    public function activate(){
        //Inserts DB Table
        $this -> init_db_XMLParse();
        //Add in settings 
        $this -> RegisterSettings();
        //set term metas
        $this -> init_employment_term_meta();
        //create a new cronjob
        if (!wp_next_scheduled('xmlParse')){
            wp_schedule_event(time(), 'daily', 'xmlParser');
        }
    }
}