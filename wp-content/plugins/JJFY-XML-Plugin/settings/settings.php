<?php
/**
* https://www.youtube.com/watch?v=xPi-Sr_iWFY
* Button for triggering the plugin
* text box or dropdown for selecting the form id
* some way to delete xml links
*/
// Settings Menu
function xmlparse_setting_menu (){
    add_menu_page(
        __('XML Parser Settings', 'JJFYXMLParser' ),
        __('XML Parser Settings', 'JJFYXMLParser' ),
        'manage_options',
        'xml-parser-settings',
        'xml_parser_settings_template_callback',
        '',
        null
    );
}
add_action('admin_menu', 'xmlparse_setting_menu');


//settings template
function xml_parser_settings_template_callback(){
    ?>
        <div class="wrap" onload="test()">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="options.php">
        <?php
            settings_fields('xml-parser-settings');
            do_settings_sections('xml-parser-settings');
            submit_button('Save Settings');
        ?>
        </form>
        </div>
    <?php
}


function xml_parser_data_settings_init(){
    //Setup setting section
    add_settings_section(
        'XML-plugin_settings_section',
        'Settings For XML Links',
        '',
        'xml-parser-settings'
    );
    //Register input option field
    register_setting(
        'xml-parser-settings',
        'xml_form_id',
        array(
        'type' => 'integer',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
        )
    );
    add_settings_field(
        'xml_form_id',
        __( 'Please enter your Forminator shortcode ID here:', 'JJFYXMLParser' ),
        'xml_parser_settings_input_field_callback',
        'xml-parser-settings',
        'XML-plugin_settings_section'
    );
}

add_action('admin_init', 'xml_parser_data_settings_init');

function xml_parser_settings_input_field_callback() {
    global $wpdb;
    $xml_input_field = get_option('xml_form_id');
    $post_ids = $wpdb -> get_col("SELECT `ID` FROM `wp_posts` WHERE `post_type` = 'forminator_forms'");
    ?>
        <select name= "xml_form_id">
        <?php if(!empty($xml_input_field)){ ?>
            <option value=""><?php echo $xml_input_field ?></option>
        <?php } else{ ?>
            <option value="">Please select You form ID</option>
        <?php } ?>
        <?php foreach($post_ids as $ids){ ?>
            <option value="<?php echo $ids ?>"><?php echo $ids ?></option>
        <?php } ?>
        </select>
    <?php
}


