<?php

class WASSCE_Checker_PRO_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    public function add_admin_menu() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        add_menu_page(
            'WASSCE Checker PRO',
            'WASSCE Checker PRO',
            'manage_options',
            'wassce-checker-pro',
            array( $this, 'manage_codes_page' ),
            'dashicons-admin-generic',
            20
        );

        add_submenu_page(
            'wassce-checker-pro',
            'Manage Codes',
            'Manage Codes',
            'manage_options',
            'wassce-checker-pro',
            array( $this, 'manage_codes_page' )
        );

        add_submenu_page(
            'wassce-checker-pro',
            'Add New Code',
            'Add New Code',
            'manage_options',
            'wassce-checker-pro-add-new',
            array( $this, 'add_new_code_page' )
        );

        add_submenu_page(
            'wassce-checker-pro',
            'Bulk Upload',
            'Bulk Upload',
            'manage_options',
            'wassce-checker-pro-bulk-upload',
            array( $this, 'bulk_upload_page' )
        );
    }

    public function manage_codes_page() {
        require_once plugin_dir_path( __FILE__ ) . 'class-wassce-checker-pro-codes-list-table.php';
        $list_table = new WASSCE_Checker_PRO_Codes_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post">
                <?php
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    public function add_new_code_page() {
        if ( isset( $_POST['submit'] ) ) {
            $this->handle_add_new_code_form();
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'wassce_add_new_code', 'wassce_add_new_code_nonce' ); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="serial_number">Serial Number</label></th>
                            <td><input name="serial_number" type="text" id="serial_number" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="pin">PIN</label></th>
                            <td><input name="pin" type="text" id="pin" class="regular-text"></td>
                        </tr>
                    </tbody>
                </table>
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Code">
                </p>
            </form>
        </div>
        <?php
    }

    public function handle_add_new_code_form() {
        if ( ! isset( $_POST['wassce_add_new_code_nonce'] ) || ! wp_verify_nonce( $_POST['wassce_add_new_code_nonce'], 'wassce_add_new_code' ) ) {
            wp_die( 'Invalid nonce.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wassce_checker_codes';

        $serial = sanitize_text_field( $_POST['serial_number'] );
        $pin = sanitize_text_field( $_POST['pin'] );

        if ( empty( $serial ) || empty( $pin ) ) {
            add_action( 'admin_notices', function() {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php _e( 'Serial number and PIN are required.', 'wassce-checker-pro' ); ?></p>
                </div>
                <?php
            } );
            return;
        }

        $existing_code = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE serial = %s OR pin = %s", $serial, $pin ) );

        if ( $existing_code ) {
            add_action( 'admin_notices', function() {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php _e( 'Serial number or PIN already exists.', 'wassce-checker-pro' ); ?></p>
                </div>
                <?php
            } );
            return;
        }

        $wpdb->insert(
            $table_name,
            array(
                'serial' => $serial,
                'pin'    => $pin,
            )
        );

        add_action( 'admin_notices', function() {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e( 'Code added successfully.', 'wassce-checker-pro' ); ?></p>
            </div>
            <?php
        } );
    }

    public function bulk_upload_page() {
        if ( isset( $_POST['submit'] ) ) {
            $this->handle_bulk_upload_form();
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field( 'wassce_bulk_upload', 'wassce_bulk_upload_nonce' ); ?>
                <p>
                    <label for="csv_file">Upload CSV file</label>
                    <input type="file" name="csv_file" id="csv_file">
                </p>
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Upload">
                </p>
            </form>
        </div>
        <?php
    }

    public function handle_bulk_upload_form() {
        if ( ! isset( $_POST['wassce_bulk_upload_nonce'] ) || ! wp_verify_nonce( $_POST['wassce_bulk_upload_nonce'], 'wassce_bulk_upload' ) ) {
            wp_die( 'Invalid nonce.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        if ( isset( $_FILES['csv_file'] ) ) {
            $file = $_FILES['csv_file'];
            $file_type = wp_check_filetype( $file['name'], array( 'csv' => 'text/csv' ) );
            if ( 'csv' !== $file_type['ext'] ) {
                add_action( 'admin_notices', function() {
                    ?>
                    <div class="notice notice-error is-dismissible">
                        <p><?php _e( 'Please upload a valid CSV file.', 'wassce-checker-pro' ); ?></p>
                    </div>
                    <?php
                } );
                return;
            }

            $csv_data = file_get_contents( $file['tmp_name'] );
            $rows = str_getcsv( $csv_data, "\n" );
            $header = array_shift( $rows );
            $data = array();
            foreach ( $rows as $row ) {
                $data[] = str_getcsv( $row );
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'wassce_checker_codes';

            $added = 0;
            $skipped = 0;

            foreach ( $data as $row ) {
                $serial = sanitize_text_field( $row[0] );
                $pin = sanitize_text_field( $row[1] );

                if ( empty( $serial ) || empty( $pin ) ) {
                    $skipped++;
                    continue;
                }

                $existing_code = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE serial = %s OR pin = %s", $serial, $pin ) );

                if ( $existing_code ) {
                    $skipped++;
                    continue;
                }

                $wpdb->insert(
                    $table_name,
                    array(
                        'serial' => $serial,
                        'pin'    => $pin,
                    )
                );
                $added++;
            }

            add_action( 'admin_notices', function() use ( $added, $skipped ) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php printf( __( 'Bulk upload complete. Added: %d, Skipped: %d', 'wassce-checker-pro' ), $added, $skipped ); ?></p>
                </div>
                <?php
            } );
            do_action( 'wassce_checker_pro_codes_uploaded' );
        }
    }
}

new WASSCE_Checker_PRO_Admin();
