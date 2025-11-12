<div class="wrap kdh-admin-wrap">
    <h1><?php esc_html_e( 'Kaadatahub Settings', 'kaadatahub' ); ?></h1>

    <form method="post" action="options.php">
        <?php
        settings_fields( 'kdh_settings_group' );
        do_settings_sections( 'kaadatahub-settings' );
        submit_button();
        ?>
    </form>
</div>
