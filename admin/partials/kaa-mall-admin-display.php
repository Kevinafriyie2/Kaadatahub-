<div class="wrap">
    <h1>KAA Mall Settings</h1>
    <form method="post" action="options.php">
        <?php
            settings_fields('kaa_mall_options_group');
            do_settings_sections('kaa-mall');
            submit_button();
        ?>
    </form>
</div>
