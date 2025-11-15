<?php
// Agent dashboard template
?>
<div class="wrap">
    <h1><?php echo esc_html__( 'Agent Dashboard', 'ocean-service-portal' ); ?></h1>
    <div id="dashboard-widgets-wrap">
        <div id="dashboard-widgets" class="metabox-holder">
            <div id="postbox-container-1" class="postbox-container">
                <div class="meta-box-sortables">
                    <div class="postbox">
                        <h2><span><?php echo esc_html__( 'Wallet Balance', 'ocean-service-portal' ); ?></span></h2>
                        <div class="inside">
                            <p><?php
                                $wallet = new Ocean_Service_Portal_Wallet();
                                $balance = $wallet->get_balance( get_current_user_id() );
                                echo wc_price( $balance );
                            ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="postbox-container-2" class="postbox-container">
                <div class="meta-box-sortables">
                    <div class="postbox">
                        <h2><span><?php echo esc_html__( 'Quick Buy', 'ocean-service-portal' ); ?></span></h2>
                        <div class="inside">
                            <p><?php echo esc_html__( 'Quick buy form will be here.', 'ocean-service-portal' ); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="postbox-container-3" class="postbox-container">
                <div class="meta-box-sortables">
                    <div class="postbox">
                        <h2><span><?php echo esc_html__( 'Bulk Upload', 'ocean-service-portal' ); ?></span></h2>
                        <div class="inside">
                            <p><?php echo esc_html__( 'Bulk upload form will be here.', 'ocean-service-portal' ); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="postbox-container-4" class="postbox-container">
                <div class="meta-box-sortables">
                    <div class="postbox">
                        <h2><span><?php echo esc_html__( 'Order History', 'ocean-service-portal' ); ?></span></h2>
                        <div class="inside">
                            <?php echo do_shortcode( '[kaa_orders_history]' ); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
