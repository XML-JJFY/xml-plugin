<?php
class xmlParser_deactivation{
    public function deactivation(){
        wp_clear_scheduled_hook('xmlParser');
    }
}