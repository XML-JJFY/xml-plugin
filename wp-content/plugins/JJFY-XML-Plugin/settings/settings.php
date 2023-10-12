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
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    </div>
    <?php
}