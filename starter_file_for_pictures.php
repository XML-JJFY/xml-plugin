<?php
/**
 * Pictures would have to be either saved or moved to wp-content/uploads/job-manager-uploads/company_logo/2023/11. This might be doable with code. Should be doable if making a a custom page for the plugin
 */
function assignPictureToPost($publisherID)
{
    global $wpdb;
    $wpdb->show_errors();
    $url = null;
    // $publisherID = 1;
    $filePath = "/wp-content/uploads/forminator";
    /**
     * gets entry id from table above, then checks if user has added in a picture.
     */
    $table_name = $wpdb->prefix . "posts";
    $isRowPresent = $wpdb->get_var("SELECT ID FROM $table_name WHERE post_title ='User $publisherID Job Picture'");
    if ($isRowPresent) {
        return $isRowPresent;
    }
    $table_name = $wpdb->prefix . "frmt_form_entry_meta";
    $entryID = $wpdb->get_var("SELECT entry_id FROM $table_name WHERE meta_key = 'hidden-1' AND meta_value=$publisherID");
    $isPresent = $wpdb->get_var("SELECT meta_value FROM $table_name WHERE meta_key = 'upload-1' and entry_id = $entryID");
    /**
     * Checks is $isPresent is there. If it is splits the data down to a usable url
     */
    if($isPresent){
        $pattern = '/;s:\d+:"message";s:\d+:"";s:\d+:"file_path";s:\d+:"/';
        /**
         * Splits the string by the 1st pattern.
         */
        $temp = preg_split($pattern, $isPresent, 2);
        /**
         * saves the 1st array of temp to temp then splits again. Setting temp equal to the file path + the file. 
         */
        $temp = $temp[1];
        $pattern = '/wp-content\/uploads\/forminator\//';
        $temp = preg_split($pattern, $temp, 2);
        $filePath = "$filePath/$temp[1]";
        /**
         * Geting site url and adding it to the completed file path.
         */
        $tempurl = get_site_url(null, null, 'http');
        $url = "$tempurl$filePath";
        $url = substr($url, 0, -1 * 4);
    }
    if (!$isRowPresent && $isPresent) {
        $url = "http://localhost/wordpress/wp-content/uploads/job-manager-uploads/company_logo/2023/11/logotest-3.png";
        $table_name = $wpdb->prefix . "posts";
        $wpdb->insert($table_name, array('post_author' => $publisherID, 'post_title' => "User $publisherID Job Picture", 'post_status' => 'inherit', 'comment_status' => 'open', 'ping_status' => 'closed', 'guid' => $url, "post_type" => 'attachment', 'post_mime_type' => 'image/png'));
        return $picPostID = $wpdb->get_var("SELECT ID FROM $table_name WHERE post_title ='User $publisherID Job Picture'");
    }
}
