<?php
/** 
 * core of the plugin
 */

define('xmlParsePath', trailingslashit(plugin_dir_path(__FILE__)));
 class xmlParser{
    protected $loader;
    public function __construct()
    {
        add_action('init', array($this,'xmlParser'));
        $this->load_dependencies();
        define('MY_PLUGIN_URL', plugin_dir_url( __FILE__ ));
        $this->define_hooks();
    }
    private function load_dependencies(){
        require_once(xmlParsePath. 'includes/class-Parser.php');
        require_once(xmlParsePath. 'includes/class-xmlParser-loader.php');

        $this -> loader = new xmlParser_Loader;
    }
    private function define_hooks(){
        // $plugin_hooks = new Parser;
        // $this->loader->add_action('xmlParser',$plugin_hooks, 'xmlParser');
    }

    public function run(){
        $this->loader->run();
    }
}