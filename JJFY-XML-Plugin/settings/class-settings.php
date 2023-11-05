<?php

class Settings{
    public function __construct() {
        add_action('admin_menu', [$this, 'xmlparse_setting_menu']);
        add_action('admin_enqueue_scripts', [$this, 'addStyle']);
        add_action('admin_init', [$this, 'xml_parser_data_settings_init']);
        add_action('admin_init', [$this, 'custom_trigger_plugin_action']);
    }
    public function xmlparse_setting_menu (){
        $logourl = get_site_url(null, null, 'https');
        add_menu_page(
            __('XML Parser Settings', 'JJFYXMLParser' ),
            __('XML Parser Settings', 'JJFYXMLParser' ),
            'manage_options',
            'xml-parser-settings',
            [$this, 'xml_parser_settings_template_callback'],
            "$logourl/wp-content/plugins/JJFY-XML-Plugin/assets/xml.png",
            null
        );
    }

    public function addStyle(){
        define('MY_PLUGIN_URL', plugin_dir_url( __FILE__ ));
        wp_register_style('settingsPage',(MY_PLUGIN_URL. 'css/setting-page.css'));
        wp_enqueue_style('settingsPage',(MY_PLUGIN_URL. 'css/setting-page.css'));
    }

    public function xml_parser_settings_template_callback(){
        require_once(xmlParsePath. 'settings/settings-table.php');
        $xml_links_table = new xml_link_tables;
        $page = wp_unslash($_REQUEST['page']);
        ?>
        <div class="xml-setting-page-wrap">
            <div class="xml-settings-wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <form class="xml-setting-form" method="post" action="options.php">
                    <?php
                        settings_fields('xml-parser-settings');
                        do_settings_sections('xml-parser-settings');
                        submit_button('Save Settings');
                    ?>
                </form>
            </div>
            <div class="xml-settings-wrap">
                <h1>User links</h1>
                <form class="xml-table-wrap" method="post" action="<?php echo admin_url("admin.php?page=$page"); ?>">
                    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
                    <?php $xml_links_table -> prepare_items(); ?>
                    <?php $xml_links_table ->display(); ?>
                </form>
            </div>
            <div class="xml-settings-wrap">
                <h1>Trigger Plugin</h1>
                <div class="inner-button-wrapper">
                    <p>The button below can be used to manually trigger the plugin. Use this if there are new XML links or the CRONS job is not working correctly.</p>
                    <form method="post" action="<?php echo admin_url("admin.php?page=$page");?>">
                        <button class = "wp-core-ui button, wp-core-ui button-primary" type="submit" name="trigger_Plugin">Trigger Plugin</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function xml_parser_data_settings_init(){
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
            [$this, 'xml_parser_settings_input_field_callback'],
            'xml-parser-settings',
            'XML-plugin_settings_section'
        );
    }

    public function xml_parser_settings_input_field_callback() {
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

    public function trigger_Plugin(){
        require_once(xmlParsePath .'includes/class-xmlParser-logic.php');
        (new JJFYXMLParser) -> xmlParser();
    }
    function custom_trigger_plugin_action() {
        if (isset($_POST['trigger_Plugin'])) {
            $this -> trigger_Plugin();
        }
    }
}
