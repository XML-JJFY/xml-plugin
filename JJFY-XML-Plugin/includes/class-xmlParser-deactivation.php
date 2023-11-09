<?php
class xmlParser_deactivation{
    public function deactivation(){
        wp_clear_scheduled_hook('xmlParser');
        delete_option('xml_form_id');
        delete_option('xml_parser_Full_Time');
        delete_option('xml_parser_Part_Time');
        delete_option('xml_parser_Contractor');
        delete_option('xml_parser_Temporary');
        delete_option('xml_parser_Intern');
        delete_option('xml_admin_approval');
    }
}