<?php
$adjacent_links = [
    'next' => '',
    'previous' => ''
];
$adjacent_links = tainacan_get_adjacent_item_links();
$previous = $adjacent_links['previous'];
$next = $adjacent_links['next'];
?>
<?php if ($previous !== '' || $next !== '') : ?>
    <hr class="alignfull" style="height: 3px; background: #0C326F;" />
    <div class="site-container tainacan-single-item-navigation">
        <?php if (get_theme_mod('tainacan_single_item_navigation_section_label', __('Continue explorando', 'tainacan_inventarios')) != '') : ?>
            <div class="is-style-title-tainacan-inventarios-underscore title-page">
                <h1 id="single-item-navigation-label">
                    <?php echo esc_html(get_theme_mod('tainacan_single_item_navigation_section_label', __('Continue explorando', 'tainacan_inventarios'))); ?>
                </h1>
            </div>
        <?php endif; ?>
        <div id="item-single-navigation" class="related-posts">
            <div class="related-post">
                <?php echo $previous; ?>
            </div>
            <div class="related-post">
                <a style="background-image: url(<?php echo 'alguma_url' ?>)" rel="next" href="<?php echo tainacan_get_source_item_list_url() ?>">
                    <div class="post-box"><img src="' . $next_thumb . '" alt=""' . $next_title . '">
                        <span class=" post-type"><?php echo __('Coleção do Item', 'tainacan_inventarios') ?></span>
                        <span class="post-title"><?php echo tainacan_the_collection_name(); ?><span>
                    </div>
                </a>
            </div>
            <div class="related-post">
                <?php echo $next; ?>
            </div>
        </div>
    </div>
<?php endif; ?>