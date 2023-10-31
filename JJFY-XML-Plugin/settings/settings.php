<?php
// Settings Menu
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'settings/settings-table.php';
}

function xmlparse_setting_menu (){
    add_menu_page(
        __('XML Parser Settings', 'JJFYXMLParser' ),
        __('XML Parser Settings', 'JJFYXMLParser' ),
        'manage_options',
        'xml-parser-settings',
        'xml_parser_settings_template_callback',
        plugins_url('/assets/XML-Plugin-icon.png', __FILE__),
        null
    );

}
add_action('admin_menu', 'xmlparse_setting_menu');
define('MY_PLUGIN_URL', plugin_dir_url( __FILE__ ));
function addStyle(){
    wp_register_style('settingsPage',(MY_PLUGIN_URL. 'css/setting-page.css'));
    wp_enqueue_style('settingsPage',(MY_PLUGIN_URL. 'css/setting-page.css'));
}
add_action('admin_enqueue_scripts', 'addStyle');
//settings template
function xml_parser_settings_template_callback(){
    require_once(xmlParsePath. 'settings/settings-table.php');
    $xml_links_table = new xml_link_tables;
    ?>
    <div class="xml-settings-wrap" onload="test()">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form class="xml-setting-form" method="post" action="options.php">
            <?php
                settings_fields('xml-parser-settings');
                do_settings_sections('xml-parser-settings');
                submit_button('Save Settings');
            ?>
        </form>
    </div>
    <div class="xml-table-wrap">
        <h1>User links</h1>
        <form method="get">
            <?php $xml_links_table ->display(); ?>
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
