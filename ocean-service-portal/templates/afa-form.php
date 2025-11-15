<?php
// AFA registration form template
?>

<form method="post">
    <p>
        <label for="kaa_afa_name"><?php echo esc_html__( 'Name', 'ocean-service-portal' ); ?></label>
        <input type="text" id="kaa_afa_name" name="kaa_afa_name" required>
    </p>
    <p>
        <label for="kaa_afa_phone"><?php echo esc_html__( 'Phone', 'ocean-service-portal' ); ?></label>
        <input type="text" id="kaa_afa_phone" name="kaa_afa_phone" required>
    </p>
    <p>
        <label for="kaa_afa_ghana_card_id"><?php echo esc_html__( 'Ghana Card ID', 'ocean-service-portal' ); ?></label>
        <input type="text" id="kaa_afa_ghana_card_id" name="kaa_afa_ghana_card_id" required>
    </p>
    <p>
        <input type="submit" name="kaa_afa_submit" value="<?php echo esc_attr__( 'Submit', 'ocean-service-portal' ); ?>">
    </p>
</form>
