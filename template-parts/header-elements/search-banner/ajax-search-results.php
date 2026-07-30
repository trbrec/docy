<div id="docy-search-result" data-noresult="<?php esc_attr_e('No Results Found', 'docy'); ?>">
    <?php
    if ( docy_opt('is_ajax_search_tab') )  :
        ?>       
        <div class="searchbar-tabs" id="search-tabs"></div> <!-- Move tabs out here -->
        <?php
    endif;
    ?>
    <div id="search-results" class="search-results-tab">
        <div id="search-preloader" style="display:none;"></div>
    </div>
</div>

