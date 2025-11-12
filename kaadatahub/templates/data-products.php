<?php
// templates/data-products.php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

global $products;

if ( $products->have_posts() ) : ?>

    <div class="kdh-data-products-container">

        <?php while ( $products->have_posts() ) : $products->the_post(); ?>

            <?php
            $product = wc_get_product( get_the_ID() );
            $network = get_post_meta( get_the_ID(), '_network', true );
            $data_size = get_post_meta( get_the_ID(), '_data_size', true );
            $validity = get_post_meta( get_the_ID(), '_validity', true );
            ?>

            <div class="kdh-card data-product-card">
                <h3><?php the_title(); ?></h3>
                <p><strong>Network:</strong> <?php echo esc_html( ucfirst( $network ) ); ?></p>
                <p><strong>Data Size:</strong> <?php echo esc_html( $data_size ); ?></p>
                <p><strong>Validity:</strong> <?php echo esc_html( $validity ); ?></p>
                <p><strong>Price:</strong> <?php echo $product->get_price_html(); ?></p>
                <a href="?add-to-cart=<?php echo esc_attr( get_the_ID() ); ?>" class="kdh-btn">Buy Now</a>
            </div>

        <?php endwhile; ?>

    </div>

<?php else : ?>

    <p><?php esc_html_e( 'No data bundles found.', 'kaadatahub' ); ?></p>

<?php endif; ?>
