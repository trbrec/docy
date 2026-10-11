<?php
/** WPCode PHP snippet; destination-only and reversible from the snippet list. */
if (strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) !== 'new1.trbrec.com') return;

add_action('wp', static function () {
    if (is_page() && get_post_meta(get_queried_object_id(), '_trb_studio_artist_id', true)) {
        // Site Studio already supplies the artist description and social metadata.
        remove_action('wp_head', ['TRB_Promo_Ecosystem', 'seo'], 3);
    }
});

add_filter('document_title_parts', static function ($parts) {
    if (!is_singular('trb_release') || !class_exists('TRB_Promo_Ecosystem')) return $parts;
    $r = TRB_Promo_Ecosystem::data(get_queried_object_id());
    if (empty($r['artist']) || empty($r['title'])) return $parts;
    $name = $r['artist'] . ' — ' . $r['title'];
    $version = trim((string) ($r['version'] ?? ''));
    if ($version !== '' && mb_stripos($r['title'], $version) === false) $name .= ' (' . $version . ')';
    if (preg_match('/^([12][0-9]{3})-[0-9]{2}-[0-9]{2}$/D', (string) ($r['date'] ?? ''), $date)) $name .= ' · ' . $date[1];
    $view = get_query_var('trb_promo_view');
    $parts['title'] = $name . ($view === 'press-kit' ? ' — Press kit' : ($view === 'smartlink' ? ' — Ascolta' : ''));
    return $parts;
}, 20);

// Serve smaller covers in the compact home grid, retaining the full-size option.
add_filter('do_shortcode_tag', static function ($html, $tag) {
    if ($tag !== 'trb_latest_releases' || !is_front_page() || !class_exists('WP_HTML_Tag_Processor')) return $html;
    $processor = new WP_HTML_Tag_Processor($html);
    while ($processor->next_tag('IMG')) {
        $src = (string) $processor->get_attribute('src');
        $small = '';
        if (preg_match('~^https://i\.scdn\.co/image/ab67616d0000b273[a-f0-9]{24}$~D', $src)) {
            $small = str_replace('ab67616d0000b273', 'ab67616d00001e02', $src);
            $processor->set_attribute('srcset', $small . ' 300w, ' . $src . ' 640w');
        } elseif (preg_match('~^https://is[0-9]+-ssl\.mzstatic\.com/image/thumb/.+/600x600bb\.jpg$~D', $src)) {
            $small = preg_replace('~/600x600bb\.jpg$~D', '/300x300bb.jpg', $src);
            $processor->set_attribute('srcset', $small . ' 300w, ' . $src . ' 600w');
        }
        if ($small !== '') $processor->set_attribute('src', $small);
        $processor->set_attribute('sizes', '(max-width: 760px) 45vw, (max-width: 1050px) 30vw, 200px');
    }
    return $processor->get_updated_html();
}, 20, 2);
