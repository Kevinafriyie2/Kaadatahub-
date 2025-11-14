<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WASSCE_Checker_PRO_Codes_List_Table extends WP_List_Table {

    public function get_columns() {
        $columns = array(
            'cb'          => '<input type="checkbox" />',
            'serial'      => __( 'Serial', 'wassce-checker-pro' ),
            'pin'         => __( 'PIN', 'wassce-checker-pro' ),
            'status'      => __( 'Status', 'wassce-checker-pro' ),
            'order_id'    => __( 'Order ID', 'wassce-checker-pro' ),
            'assigned_at' => __( 'Date Assigned', 'wassce-checker-pro' ),
        );
        return $columns;
    }

    function get_bulk_actions() {
        $actions = array(
            'delete' => 'Delete',
            'export' => 'Export Unused'
        );
        return $actions;
    }

    function extra_tablenav( $which ) {
        if ( $which == "top" ) {
            ?>
            <div class="alignleft actions">
                <select name="status_filter">
                    <option value="">All Statuses</option>
                    <option value="unused">Unused</option>
                    <option value="used">Used</option>
                </select>
                <input type="submit" name="filter_action" class="button" value="Filter">
            </div>
            <?php
        }
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wassce_checker_codes';

        $per_page = 20;
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $this->_column_headers = array( $columns, $hidden, $sortable );

        $this->process_bulk_action();

        $query = "SELECT * FROM $table_name";
        if ( isset( $_POST['status_filter'] ) && ! empty( $_POST['status_filter'] ) ) {
            $status_filter = sanitize_text_field( $_POST['status_filter'] );
            $query .= $wpdb->prepare( " WHERE status = %s", $status_filter );
        }

        $data = $wpdb->get_results( $query, ARRAY_A );
        $this->items = $data;

        $total_items = count( $data );
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ) );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'pin':
            case 'status':
            case 'order_id':
            case 'assigned_at':
                return $item[ $column_name ];
            default:
                return print_r( $item, true );
        }
    }

    function column_serial( $item ) {
        $actions = array(
            'delete' => sprintf( '<a href="?page=%s&action=%s&id=%s">Delete</a>', $_REQUEST['page'], 'delete', $item['id'] ),
        );
        return sprintf( '%1$s %2$s', $item['serial'], $this->row_actions( $actions ) );
    }

    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%s" />', $item['id']
        );
    }

    public function process_bulk_action() {
        if ( 'delete' === $this->current_action() ) {
            $ids = isset( $_REQUEST['id'] ) ? $_REQUEST['id'] : array();
            if ( ! is_array( $ids ) ) {
                $ids = array( $ids );
            }
            $ids = implode( ',', array_map( 'absint', $ids ) );

            if ( ! empty( $ids ) ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'wassce_checker_codes';
                $wpdb->query( "DELETE FROM $table_name WHERE id IN( $ids )" );
            }
        }

        if ( 'export' === $this->current_action() ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wassce_checker_codes';
            $data = $wpdb->get_results( "SELECT serial, pin FROM $table_name WHERE status = 'unused'", ARRAY_A );

            $filename = 'unused-codes-' . date( 'Y-m-d' ) . '.csv';
            header( 'Content-Type: text/csv' );
            header( 'Content-Disposition: attachment; filename=' . $filename );
            $output = fopen( 'php://output', 'w' );
            fputcsv( $output, array( 'serial', 'pin' ) );
            foreach ( $data as $row ) {
                fputcsv( $output, $row );
            }
            fclose( $output );
            exit;
        }
    }
}
