<?php
class xml_link_tables extends WP_List_Table {
    public function __construct() {
        parent::__construct(array(
            'singular' => 'link',
            'plural' => 'links',
            'ajax' => false
        ));
    }

    // Fetch the initial table data from the database.
    public function fetch_table_data() {
        global $wpdb;
        $query = "SELECT form.entry_id, users.user_nicename, users.ID FROM `wp_frmt_form_entry_meta` AS `form` INNER JOIN `wp_users` as `users` WHERE form.meta_value = users.ID";
        $query_results = $wpdb->get_results($query, ARRAY_A);

        $index = 0;
        while ($index < count($query_results)) {
            $id = $query_results[$index]['entry_id'];
            $query = "SELECT meta_value, entry_id FROM `wp_frmt_form_entry_meta` WHERE meta_key = 'url-1' and entry_id = $id";
            $results = $wpdb->get_results($query, ARRAY_N);
            $query_results[$index]['xml_link'] = $results[0][0];
            $query_results[$index]['form-id'] = $results[0][1];
            $index++;
        }
        return $query_results;
    }

    // Prepare the items for the table.
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden_columns = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $this->process_bulk_action($this->item);
        $this->_column_headers = array($columns, $hidden_columns, $sortable);
        $this->_column_headers = $this->get_column_info();
        $table_data = $this->fetch_table_data();
        
        // Get the search key from the request.
        $xml_link_search_key = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        
        // Display the search box.
        $this->display_search_box();

        // Filter the data based on the search key.
        if (!empty($xml_link_search_key)) {
            $filtered_results = array_filter($table_data, function ($item) use ($xml_link_search_key) {
                return stripos($item['user_nicename'], $xml_link_search_key) !== false ||
                    stripos($item['ID'], $xml_link_search_key) !== false ||
                    stripos($item['xml_link'], $xml_link_search_key) !== false;
            });
        } else {
            $filtered_results = $table_data;
        }

        $links_per_page = $this->get_items_per_page('links_per_page');
        $table_page = $this->get_pagenum();
        $total_links = count($filtered_results);

        $this->set_pagination_args(array(
            'total_items' => $total_links,
            'per_page'    => $links_per_page,
            'total_pages' => ceil($total_links / $links_per_page)
        ));

        usort($filtered_results, array($this, 'usort_reorder'));
        $this->items = array_slice($filtered_results, (($table_page - 1) * $links_per_page), $links_per_page);
    }

    protected function extra_tablenav($which) {
        if ($which == "top") {
            // Additional top navigation if needed.
        }
        if ($which == 'bottom') {
            // Additional bottom navigation if needed.
        }
    }

    public function get_columns() {
        // Define the table columns.
        $columns = array(
            'cb' => '<input type="checkbox">',
            'col_user_name' => 'User Name',
            'col_user_id' => 'User ID',
            'col_xml_link' => 'Xml Link'
        );

        return $columns;
    }

    protected function get_sortable_columns() {
        // Define sortable columns.
        $sortable_columns = array(
            'col_user_name' => array('user_nicename', false),
            'col_user_id' => array('ID', false),
            'col_xml_link' => array('xml_link', false)
        );

        return $sortable_columns;
    }

    protected function get_hidden_columns() {
        // Define hidden columns.
        $hidden_columns = array(
            'hidden-id' => ''
        );

        return $hidden_columns;
    }

    public function no_items() {
        _e('No links available.');
    }

    function column_cb($item) {
        // Render the checkbox column.
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            $this->_args['singular'],
            $item['form-id'],
        );
    }

    protected function column_default($item, $column_name) {
        // Render the default column content.
        switch ($column_name) {
            case 'cb':
                return '<input type="checkbox">';
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

    protected function column_col_user_name($item) {
        $page = wp_unslash($_REQUEST['page']);
        
        // Build delete row action.
        $delete_query_args = array(
            'page'   => $page,
            'action' => 'delete',
            'link'  => $item['ID'],
            'form-id' => $item['form-id']
        );

        $actions['delete'] = sprintf(
            '<a href="%1$s">%2$s</a>',
            esc_url(wp_nonce_url(add_query_arg($delete_query_args, 'admin.php'), 'deletelink_' . $item['ID'] . 'test')),
            _x('Delete', 'List table row action', 'wp-wp-xml-link-tables')
        );
        
        return sprintf('%1$s %2$s', $item['user_nicename'], $this->row_actions($actions));
    }

    protected function usort_reorder($a, $b) {
        // Sort items based on user-selected columns.
        $orderby = !empty($_REQUEST['orderby']) ? wp_unslash($_REQUEST['orderby']) : 'user_nicename';
        $order = !empty($_REQUEST['order']) ? wp_unslash($_REQUEST['order']) : 'asc';
        $result = strcmp($a[$orderby], $b[$orderby]);
        return ('asc' === $order) ? $result : -$result;
    }

    // Define bulk actions.
    protected function get_bulk_actions() {
        $actions = array(
            'delete' => _x('Delete', 'List table bulk action', 'wp-xml-link-tables'),
        );

        return $actions;
    }

    // Process bulk actions.
    protected function process_bulk_action() {
        global $wpdb;
        $wpdb->show_errors();

        // Detect when a bulk action is being triggered.
        if ('delete' === $this->current_action()) {
            $table_name = $wpdb->prefix . 'frmt_form_entry_meta';
            $selected_items = isset($_POST[$this->_args['singular']]) ? $_POST[$this->_args['singular']] : array();
            if (!empty($selected_items)) {
                foreach ($selected_items as $item_id) {
                    $results = $wpdb->delete($table_name, array('entry_id' => $item_id), array('%d'));
                    if ($results === false) {
                        $error_message = $wpdb->last_error;
                        echo $error_message;
                    }
                }
            } else {
                $form_id = isset($_REQUEST['form-id']) ? $_REQUEST['form-id'] : 0;
                $results = $wpdb->delete($table_name, array('entry_id' => $form_id), array('%d'));
                var_dump($results);
                if ($results === false) {
                    $error_message = $wpdb->last_error;
                    echo $error_message;
                }
            }
        }
    }

    protected function display_search_box() {
        $search_value = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="link-search-input">Search Links:</label>
            <input type="search" id="link-search-input" name="s" value="<?php echo $search_value; ?>">
            <input type="submit" id="search-submit" class="button" value="Search Links">
        </p>
        <?php
    }
}
