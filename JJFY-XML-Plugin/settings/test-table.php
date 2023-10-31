<?php

class xml_link_tables extends WP_List_Table{
    public function __construct(){
        parent::__construct( array(
            'singular' => 'xml_link',
            'plural' => 'xml_links',
            'ajax' => false
        ));
    }
    public function fetch_table_data(){
        global $wpdb;
        /**
         * Gets information needed by the table.
         */
        $query = "SELECT form.entry_id, users.user_nicename, users.ID FROM `wp_frmt_form_entry_meta` AS `form` INNER JOIN `wp_users` as `users` WHERE form.meta_value = users.ID";
        $query_results = $wpdb->get_results($query, ARRAY_A);
        $index = 0;
        while($index < count($query_results)){
            $id = $query_results[$index]['entry_id'];
            $query = "SELECT meta_value, entry_id FROM `wp_frmt_form_entry_meta` WHERE meta_key = 'url-1' and entry_id = $id";
            $results = $wpdb->get_results($query, ARRAY_N);
            $query_results[$index]['xml_link'] = $results[0][0];
            $query_results[$index]['form-id'] = $results[0][1];
            $index ++; 
        }
        return $query_results;
    }

    public function prepare_items(){
        $columns = $this->get_columns();
        $hidden_columns = $this->get_hidden_columns();
        $this->process_bulk_action();
        $this -> _column_headers = array($columns, $hidden_columns);
        $xml_link_search_key = isset($_REQUEST['s']) ? wp_unslash(trim($_REQUEST['s'])) : '';
        $this -> _column_headers = $this-> get_column_info();
        $table_data = $this -> fetch_table_data();
        if($xml_link_search_key){
            $table_data = $this -> filter_table_data($table_data, $xml_link_search_key);
        }
        $links_per_page = $this -> get_items_per_page('links_per_page');
        $table_page = $this->get_pagenum();
        $this -> items = array_slice( $table_data, (($table_page - 1) * $links_per_page), $links_per_page);
        $total_links = count ($table_data);
        $this -> set_pagination_args(array(
            'total_items' => $total_links,
            'per_page' => $links_per_page,
            'total_pages' => ceil($total_links/$links_per_page)
        ) );
    }
   
    public function extra_tablenav($which)
    {
        if($which == "top"){
        }
        if($which == 'bottom'){
        }
    }

    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox">',
            'col_user_name' => 'User Name',
            'col_user_id' => 'User ID',
            'col_xml_link' => 'Xml Link'
        );

        return $columns;
    }
    public function get_hidden_columns(){
        $hidden_columns = array(
            'hidden-id' => ''
        );
        return $hidden_columns;
    }
    
    public function no_items(){
        _e('No links avaliable.');
    }
    function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="item[]" value="%s" />',
            $item['ID']
        );
    }
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'cb':
                return '<input type="checkbox">';
            case 'col_user_name':
                return $item['user_nicename'];
            case 'col_user_id':
                return $item['ID'];
            case 'col_xml_link':
                return $item['xml_link'];
            case 'hidden-id':
                return $item['form-id'];
            default:
                return 'N/A';
        }
    }
    /**
     * bulk action set up
     */
    protected function get_bulk_actions() {
		$actions = array(
			'delete' => _x( 'Delete', 'List table bulk action', 'wp-list-table-example' ),
		);

		return $actions;
	}
    protected function process_bulk_action() {
		// Detect when a bulk action is being triggered.
		if ( 'delete' === $this->current_action() ) {
			wp_die( 'Items deleted (or they would be if we had items to delete)!' );
		}
	}
}
