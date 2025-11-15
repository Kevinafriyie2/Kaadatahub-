<?php

class Ocean_Service_Portal_AFA {

    public function __construct() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_shortcode( 'kaa_afa_form', array( $this, 'render_form' ) );
    }

    public function register_cpt() {
        $labels = array(
            'name'               => _x( 'AFA Registrations', 'post type general name', 'ocean-service-portal' ),
            'singular_name'      => _x( 'AFA Registration', 'post type singular name', 'ocean-service-portal' ),
            'menu_name'          => _x( 'AFA Registrations', 'admin menu', 'ocean-service-portal' ),
            'name_admin_bar'     => _x( 'AFA Registration', 'add new on admin bar', 'ocean-service-portal' ),
            'add_new'            => _x( 'Add New', 'afa', 'ocean-service-portal' ),
            'add_new_item'       => __( 'Add New AFA Registration', 'ocean-service-portal' ),
            'new_item'           => __( 'New AFA Registration', 'ocean-service-portal' ),
            'edit_item'          => __( 'Edit AFA Registration', 'ocean-service-portal' ),
            'view_item'          => __( 'View AFA Registration', 'ocean-service-portal' ),
            'all_items'          => __( 'All AFA Registrations', 'ocean-service-portal' ),
            'search_items'       => __( 'Search AFA Registrations', 'ocean-service-portal' ),
            'parent_item_colon'  => __( 'Parent AFA Registrations:', 'ocean-service-portal' ),
            'not_found'          => __( 'No AFA registrations found.', 'ocean-service-portal' ),
            'not_found_in_trash' => __( 'No AFA registrations found in Trash.', 'ocean-service-portal' )
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'afa-registration' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' )
        );

        register_post_type( 'kaa_afa', $args );
    }

    public function render_form() {
        if ( isset( $_POST['kaa_afa_submit'] ) ) {
            $name = sanitize_text_field( $_POST['kaa_afa_name'] );
            $phone = sanitize_text_field( $_POST['kaa_afa_phone'] );
            $ghana_card_id = sanitize_text_field( $_POST['kaa_afa_ghana_card_id'] );

            $post_id = wp_insert_post( array(
                'post_title'   => $name,
                'post_content' => "Phone: $phone\nGhana Card ID: $ghana_card_id",
                'post_status'  => 'pending',
                'post_type'    => 'kaa_afa',
            ) );

            if ( $post_id ) {
                return '<p>' . esc_html__( 'Thank you for your submission. It is currently under review.', 'ocean-service-portal' ) . '</p>';
            }
        }

        ob_start();
        include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/afa-form.php';
        return ob_get_clean();
    }
}
