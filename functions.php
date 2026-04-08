<?php

// Подключение модуля защиты контактов от Google
require_once get_template_directory() . '/security-contacts.php'; 
require_once get_template_directory() . '/seo-template-admin.php';
// Если файл положили в папку inc, то: . '/inc/security-contacts.php';

add_action('wp_enqueue_scripts', function () {
    // 1️⃣ Всегда: Tailwind и ваш основной стиль
    wp_enqueue_style(
        'tailwind',
        get_template_directory_uri() . '/assets/css/output.css',
        [],
        null,
        'all'
    );
    wp_enqueue_style(
        'anketa-card',
        get_template_directory_uri() . '/assets/css/anketa-card.css',
        [],
        null,
        'all'
    );
    wp_enqueue_style(
        'cards',
        get_template_directory_uri() . '/assets/css/cards.css',
        [],
        null,
        'all'
    );
    wp_enqueue_style(
        'style',
        get_template_directory_uri() . '/style.css',
        ['tailwind'],
        null,
        'all'
    );

    // Предзагрузка Tailwind
    echo '<link rel="preload" href="'
        . get_template_directory_uri()
        . '/assets/css/output.css" as="style">';

    // Ваш основной скрипт (если нужен везде)
    wp_enqueue_script(
        'smain-js',
        get_template_directory_uri() . '/assets/js/main.js',
        [],
        null,
        true
    );


    // Swiper CSS
    wp_enqueue_style(
        'swiper-css',
        get_template_directory_uri() . '/assets/css/swiper-bundle.min.css',
        [],
        null,
        'all'
    );
    // Swiper JS (defer)
    wp_enqueue_script(
        'swiper-js',
        get_template_directory_uri() . '/assets/js/swiper-bundle.min.js',
        [],
        null,
        true
    );
    wp_script_add_data('swiper-js', 'defer', true);
}, 1);


// 1.1. Подключаем наш фронтенд-скрипт и даём ему параметры

add_action('template_redirect', function () {
    ob_start(function ($buffer) {
        // Удаляем блок <script type="speculationrules">...</script>
        return preg_replace(
            '/<script[^>]*type="speculationrules"[^>]*>.*?<\/script>/is',
            '',
            $buffer
        );
    });
});

add_theme_support('post-thumbnails');
add_theme_support('title-tag');
add_theme_support('custom-logo');

function remove_unused_scripts()
{
    wp_dequeue_script('jquery'); // Отключаем jQuery, если не нужен
}
add_action('wp_enqueue_scripts', 'remove_unused_scripts', 100);


function disable_classic_theme_styles()
{
    remove_action('wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles');
}
add_action('wp_enqueue_scripts', 'disable_classic_theme_styles', 1);

remove_action('wp_head', 'wp_generator');
// Удаление генерации ссылок на API
remove_action('wp_head', 'rest_output_link_wp_head', 10);
remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
remove_action('template_redirect', 'rest_output_link_header', 11, 0);
// Удаление RSD ссылки
remove_action('wp_head', 'rsd_link');
// Удаление shortlink
remove_action('wp_head', 'wp_shortlink_wp_head', 10);
remove_action('template_redirect', 'wp_shortlink_header', 11);
// Удаление oEmbed ссылок
remove_action('wp_head', 'wp_oembed_add_host_js');
// Удаление meta-тегов, связанных с Windows Tiles
remove_action('wp_head', 'wp_site_icon', 99);
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');


function move_jquery_to_footer()
{
    if (!is_admin()) {
        wp_deregister_script('jquery'); // Отключаем стандартное подключение
        wp_register_script(
            'jquery',
            includes_url('/js/jquery/jquery.min.js'),
            array(),
            null,
            true // Подключаем в футере
        );
        wp_enqueue_script('jquery');
    }
}
add_action('wp_enqueue_scripts', 'move_jquery_to_footer');

function disable_global_styles()
{
    wp_dequeue_style('global-styles'); // Отключаем стили
    wp_dequeue_style('wp-block-library'); // Отключаем базовые стили блоков
    wp_dequeue_style('wp-block-library-theme'); // Отключаем стили темы
}
add_action('wp_enqueue_scripts', 'disable_global_styles', 100);

function remove_block_css()
{
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('wc-blocks-style'); // Для WooCommerce
}
add_action('wp_enqueue_scripts', 'remove_block_css', 100);

add_filter('use_block_editor_for_post', '__return_false', 10);
add_action('wp_enqueue_scripts', function () {
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('global-styles');
}, 100);


function custom_contact_settings($wp_customize)
{
    // Раздел "Контактные данные"
    $wp_customize->add_section('contact_section', [
        'title'       => __('Контактные данные', 'textdomain'),
        // Обновили описание
        'description' => __('Здесь вы можете настроить контактные данные. Telegram и WhatsApp поддерживают до 5 вариантов.', 'textdomain'),
        'priority'    => 30,
    ]);

    // Телефон
    $wp_customize->add_setting('contact_number', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control('contact_number', [
        'label'       => __('Телефон', 'textdomain'),
        'section'     => 'contact_section',
        'type'        => 'text',
        'description' => __('Введите основной номер, например: +7 999 123-45-67', 'textdomain'),
    ]);

    $wp_customize->add_setting('contact_telegram_channel', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ));

    $wp_customize->add_control('contact_telegram_channel', array(
        'label'       => __('Telegram-канал', 'textdomain'),
        'section'     => 'contact_section',
        'type'        => 'text',
        'description' => __('Введите username (без @) или полный URL.', 'textdomain'),
    ));

    // === Telegram (5 вариантов с особым заголовком для 5-го) ===
    for ($i = 1; $i <= 5; $i++) {
        // Обычный заголовок или специальный для 5-го элемента
        $label = ($i === 5)
            ? __('Telegram (для Дешевых анкет и страницы)', 'textdomain')
            : __("Telegram #$i", 'textdomain');

        $wp_customize->add_setting("contact_telegram_$i", [
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        $wp_customize->add_control("contact_telegram_$i", [
            'label'       => $label,
            'section'     => 'contact_section',
            'type'        => 'text',
            'description' => __('Введите username без @ или ссылку, например: t.me/username', 'textdomain'),
        ]);
    }

    // === WhatsApp (5 вариантов с особым заголовком для 5-го) ===
    for ($i = 1; $i <= 5; $i++) {
        // Обычный заголовок или специальный для 5-го элемента
        $label = ($i === 5)
            ? __('WhatsApp (для Дешевых анкет и страницы)', 'textdomain')
            : __("WhatsApp #$i", 'textdomain');

        $wp_customize->add_setting("contact_whatsapp_$i", [
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        $wp_customize->add_control("contact_whatsapp_$i", [
            'label'       => $label,
            'section'     => 'contact_section',
            'type'        => 'text',
            'description' => __('Введите номер WhatsApp в формате: +79991234567', 'textdomain'),
        ]);
    }

    // Email
    $wp_customize->add_setting('contact_email', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control('contact_email', [
        'label'       => __('Email', 'textdomain'),
        'section'     => 'contact_section',
        'type'        => 'text',
        'description' => __('Введите вашу почту', 'textdomain'),
    ]);

    // Agency
    $wp_customize->add_setting('contact_agency', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control('contact_agency', [
        'label'       => __('Agency', 'textdomain'),
        'section'     => 'contact_section',
        'type'        => 'text',
        'description' => __('Введите название агентства', 'textdomain'),
    ]);

    // Street
    $wp_customize->add_setting('contact_street', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control('contact_street', [
        'label'       => __('Street', 'textdomain'),
        'section'     => 'contact_section',
        'type'        => 'text',
        'description' => __('Введите адрес агентства', 'textdomain'),
    ]);
}
add_action('customize_register', 'custom_contact_settings');

function custom_model_card_settings($wp_customize)
{
    $wp_customize->add_section('model_card_section', [
        'title'       => __('Анкеты: карточка', 'textdomain'),
        'description' => __('Настройки отображения карточек анкет.', 'textdomain'),
        'priority'    => 31,
    ]);

    $wp_customize->add_setting('model_card_desc_length', [
        'default'           => 220,
        'sanitize_callback' => static function ($value) {
            $val = (int) $value;
            if ($val < 160) $val = 160;
            if ($val > 260) $val = 260;
            return $val;
        },
    ]);

    $wp_customize->add_control('model_card_desc_length', [
        'label'       => __('Длина описания в карточке (160–260)', 'textdomain'),
        'section'     => 'model_card_section',
        'type'        => 'number',
        'input_attrs' => [
            'min'  => 160,
            'max'  => 260,
            'step' => 10,
        ],
    ]);
}
add_action('customize_register', 'custom_model_card_settings');

function get_random_contacts()
{
    $i = rand(1, 4); // случайный вариант от 1 до 4

    $tg  = get_theme_mod("contact_telegram_$i");
    $wa  = get_theme_mod("contact_whatsapp_$i");
    $tel = get_theme_mod("contact_number");
    $mail = get_theme_mod("contact_email");

    return [
        'telegram' => $tg,
        'whatsapp' => $wa,
        'number'   => $tel,
        'email'    => $mail,
        'agency'   => get_theme_mod('contact_agency'),
        'street'   => get_theme_mod('contact_street'),
        'index'    => $i, // для отладки или аналитики
    ];
}


function get_contact_whatsapp()
{
    $number = get_theme_mod('contact_number');
    if ($number) {
        // Удаляем "+" в начале, если он есть
        $number = ltrim($number, '+');
        return 'https://wa.me/' . esc_attr($number);
    }
}
add_shortcode('whatsapp_button', 'get_contact_whatsapp');


function get_contact_telegram()
{
    $telegram = get_theme_mod('contact_telegram');
    if ($telegram) {
        return 'https://t.me/' . esc_attr($telegram);
    }
}
add_shortcode('telegram_button', 'get_contact_telegram');


// Contact redirect router: /go/tg and /go/wa
add_action('template_redirect', function () {
    if (is_admin()) {
        return;
    }

    $request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if ($request_uri === '') {
        return;
    }

    $request_path = trim((string) parse_url($request_uri, PHP_URL_PATH), '/');
    if ($request_path === '') {
        return;
    }

    if (!preg_match('~^go/([a-z0-9_-]+)$~i', $request_path, $m)) {
        return;
    }

    $type = strtolower((string) ($m[1] ?? ''));
    $normalize_tg = static function ($value) {
        $value = trim((string) $value);
        if ($value === '') return '';
        $value = preg_replace('~^https?://t\.me/~i', '', $value);
        $value = ltrim($value, '@');
        return preg_replace('~[^a-z0-9_]+~i', '', $value);
    };
    $normalize_wa = static function ($value) {
        return preg_replace('~\D+~', '', (string) $value);
    };

    $pick_from_theme_mods = static function (string $mod_base, callable $normalizer) {
        $pool = [];
        $main = get_theme_mod($mod_base);
        if (!empty($main)) {
            $clean = $normalizer($main);
            if ($clean !== '') $pool[] = $clean;
        }
        for ($i = 1; $i <= 5; $i++) {
            $value = get_theme_mod("{$mod_base}_{$i}");
            if (empty($value)) continue;
            $clean = $normalizer($value);
            if ($clean !== '') $pool[] = $clean;
        }
        if (empty($pool)) return '';
        return $pool[array_rand($pool)];
    };

    $target = '';
    if ($type === 'tg') {
        $tg = $pick_from_theme_mods('contact_telegram', $normalize_tg);
        if ($tg !== '') $target = 'https://t.me/' . $tg;
    } elseif ($type === 'wa') {
        $wa = $pick_from_theme_mods('contact_whatsapp', $normalize_wa);
        if ($wa !== '') $target = 'https://wa.me/' . $wa;
    }

    if ($target === '') {
        wp_safe_redirect(home_url('/kontakty/'), 302);
        exit;
    }

    wp_redirect($target, 302, 'Contact Go Router');
    exit;
}, 0);


add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
    if (strpos($requested_url, 'sitemap.xml') !== false) {
        return false; // отключаем редирект именно для sitemap.xml
    }
    return $redirect_url;
}, 10, 2);

// 301 для старых city-slug URL на новые URL без города.
add_action('template_redirect', function () {
    if (is_admin()) {
        return;
    }

    $request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if ($request_uri === '') {
        return;
    }

    $request_path = trim((string) parse_url($request_uri, PHP_URL_PATH), '/');
    if ($request_path === '') {
        return;
    }

    $legacy_slug_map = [
        'eskort'     => 'escort',
        'vse-uslugi' => 'services',
    ];

    foreach ($legacy_slug_map as $old_slug => $new_slug) {
        $suffix = '';
        if ($request_path === $old_slug) {
            $suffix = '';
        } elseif (strpos($request_path, $old_slug . '/') === 0) {
            $suffix = substr($request_path, strlen($old_slug));
        } else {
            continue;
        }

        $target_path = trim($new_slug . $suffix, '/');
        $target_url  = user_trailingslashit(home_url('/' . $target_path));

        $query = (string) parse_url($request_uri, PHP_URL_QUERY);
        if ($query !== '') {
            $target_url .= (strpos($target_url, '?') === false ? '?' : '&') . $query;
        }

        wp_safe_redirect($target_url, 301);
        exit;
    }
}, 1);



remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'rest_output_link_wp_head');
remove_action('template_redirect', 'rest_output_link_header', 11);
remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
remove_action('wp_head', 'rel_canonical');
add_filter('rest_enabled', '__return_false');
add_filter('rest_jsonp_enabled', '__return_false');
remove_action('xmlrpc_rsd_apis', 'rest_output_rsd');
remove_action('wp_head', 'rest_output_link_wp_head', 10);
remove_action('template_redirect', 'rest_output_link_header', 11);
remove_action('auth_cookie_malformed', 'rest_cookie_collect_status');
remove_action('auth_cookie_expired', 'rest_cookie_collect_status');
remove_action('auth_cookie_bad_username', 'rest_cookie_collect_status');
remove_action('auth_cookie_bad_hash', 'rest_cookie_collect_status');
remove_action('auth_cookie_valid', 'rest_cookie_collect_status');
remove_action('wp_head', 'wp_oembed_add_discovery_links');
remove_action('wp_head', 'wp_oembed_add_host_js');
remove_action('template_redirect', 'rest_output_link_header', 11);
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', '_wp_render_title_tag', 1);
add_filter('xmlrpc_enabled', '__return_false');

// Полное отключение всех RSS-лент
function disable_feed()
{
    wp_die(__('RSS-фиды отключены. Пожалуйста, заходите напрямую на сайт.'));
}
add_action('do_feed', 'disable_feed', 1);
add_action('do_feed_rdf', 'disable_feed', 1);
add_action('do_feed_rss', 'disable_feed', 1);
add_action('do_feed_rss2', 'disable_feed', 1);
add_action('do_feed_atom', 'disable_feed', 1);
remove_action('wp_head', 'feed_links', 2);
remove_action('wp_head', 'feed_links_extra', 3);

if (!function_exists('dosugmoskva24_slugify_latin')) {
    /**
     * Транслитерация кириллицы в ASCII-slug.
     */
    function dosugmoskva24_slugify_latin(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $map = [
            'А' => 'a',  'а' => 'a',
            'Б' => 'b',  'б' => 'b',
            'В' => 'v',  'в' => 'v',
            'Г' => 'g',  'г' => 'g',
            'Д' => 'd',  'д' => 'd',
            'Е' => 'e',  'е' => 'e',
            'Ё' => 'yo', 'ё' => 'yo',
            'Ж' => 'zh', 'ж' => 'zh',
            'З' => 'z',  'з' => 'z',
            'И' => 'i',  'и' => 'i',
            'Й' => 'y',  'й' => 'y',
            'К' => 'k',  'к' => 'k',
            'Л' => 'l',  'л' => 'l',
            'М' => 'm',  'м' => 'm',
            'Н' => 'n',  'н' => 'n',
            'О' => 'o',  'о' => 'o',
            'П' => 'p',  'п' => 'p',
            'Р' => 'r',  'р' => 'r',
            'С' => 's',  'с' => 's',
            'Т' => 't',  'т' => 't',
            'У' => 'u',  'у' => 'u',
            'Ф' => 'f',  'ф' => 'f',
            'Х' => 'h',  'х' => 'h',
            'Ц' => 'c',  'ц' => 'c',
            'Ч' => 'ch', 'ч' => 'ch',
            'Ш' => 'sh', 'ш' => 'sh',
            'Щ' => 'shch','щ' => 'shch',
            'Ъ' => '',   'ъ' => '',
            'Ы' => 'y',  'ы' => 'y',
            'Ь' => '',   'ь' => '',
            'Э' => 'e',  'э' => 'e',
            'Ю' => 'yu', 'ю' => 'yu',
            'Я' => 'ya', 'я' => 'ya',
            'І' => 'i',  'і' => 'i',
            'Ї' => 'yi', 'ї' => 'yi',
            'Є' => 'ye', 'є' => 'ye',
        ];

        $latin = strtr($value, $map);
        $latin = strtolower($latin);
        $latin = preg_replace('~[^a-z0-9]+~', '-', $latin);
        $latin = trim((string) $latin, '-');

        return $latin;
    }
}

/**
 * Разрешаем clean-slug URL вида /services/{latin-slug}/
 * даже если реальный slug терма в БД сохранен как percent-encoded.
 */
add_filter('request', function ($vars) {
    if (is_admin() || !is_array($vars) || empty($vars['uslugi_tax'])) {
        return $vars;
    }

    $requested_slug = trim((string) $vars['uslugi_tax']);
    if ($requested_slug === '' || strpos($requested_slug, '%') !== false) {
        return $vars;
    }

    $existing_term = get_term_by('slug', $requested_slug, 'uslugi_tax');
    if ($existing_term && !is_wp_error($existing_term)) {
        return $vars;
    }

    static $slug_aliases = null;
    if ($slug_aliases === null) {
        $slug_aliases = [];
        $terms = get_terms([
            'taxonomy'   => 'uslugi_tax',
            'hide_empty' => false,
        ]);

        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                if (!($term instanceof WP_Term)) {
                    continue;
                }

                $actual_slug      = (string) $term->slug;
                $decoded_slug     = rawurldecode($actual_slug);
                $from_slug        = sanitize_title($decoded_slug);
                $from_name        = sanitize_title((string) $term->name);
                $from_slug_latin  = dosugmoskva24_slugify_latin($decoded_slug);
                $from_name_latin  = dosugmoskva24_slugify_latin((string) $term->name);
                $slug_variants = array_unique(array_filter([
                    $from_slug,
                    $from_name,
                    $from_slug_latin,
                    $from_name_latin,
                ]));

                foreach ($slug_variants as $variant) {
                    if (strpos((string) $variant, '%') !== false) {
                        continue;
                    }
                    if (!isset($slug_aliases[$variant])) {
                        $slug_aliases[$variant] = $actual_slug;
                    }
                }
            }
        }
    }

    if (isset($slug_aliases[$requested_slug])) {
        $resolved_slug = (string) $slug_aliases[$requested_slug];
        if ($resolved_slug !== '') {
            $vars['uslugi_tax'] = $resolved_slug;
            if (isset($vars['term'])) {
                $vars['term'] = $resolved_slug;
            }
        }
    }

    return $vars;
}, 9);


add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query() || !is_tax()) {
        return;
    }

    // For custom taxonomies the term slug lives in taxonomy-specific query vars.
    $tax_query_vars = [
        'uslugi_tax',
        'rayonu_tax',
        'metro_tax',
        'price_tax',
        'vozrast_tax',
        'nationalnost_tax',
        'ves_tax',
        'cvet-volos_tax',
        'rost_tax',
        'grud_tax',
    ];

    $slug = '';
    foreach ($tax_query_vars as $var_name) {
        $candidate = (string) $query->get($var_name);
        if ($candidate !== '') {
            $slug = $candidate;
            break;
        }
    }

    if ($slug === '') {
        $slug = (string) $query->get('term');
    }

    if ($slug === '') {
        return;
    }

    $page = get_page_by_path($slug, OBJECT, 'page');
    if (!($page instanceof WP_Post) || $page->post_status !== 'publish') {
        return;
    }

    $query->set('post_type', 'page');
    $query->set('page_id', $page->ID);
    $query->is_page = true;
    $query->is_tax = false;
    $query->is_archive = false;
    $query->is_singular = true;
    $query->is_404 = false;
});

// Для терминов, у которых есть одноименная опубликованная page, отдаем канонический URL страницы.
add_filter('term_link', function ($termlink, $term, $taxonomy) {
    if (!($term instanceof WP_Term)) {
        return $termlink;
    }

    $supported_taxonomies = [
        'uslugi_tax',
        'rayonu_tax',
        'metro_tax',
        'price_tax',
        'vozrast_tax',
        'nationalnost_tax',
        'ves_tax',
        'cvet-volos_tax',
        'rost_tax',
        'grud_tax',
    ];

    if (!in_array((string) $taxonomy, $supported_taxonomies, true)) {
        return $termlink;
    }

    $page = get_page_by_path((string) $term->slug, OBJECT, 'page');
    if (!($page instanceof WP_Post) || $page->post_status !== 'publish') {
        return $termlink;
    }

    $page_url = (string) get_permalink((int) $page->ID);
    return $page_url !== '' ? $page_url : $termlink;
}, 20, 3);

// Если tax-URL подменен на page (через pre_get_posts выше), фиксируем единый канон через 301 на permalink страницы.
add_action('template_redirect', function () {
    if (is_admin() || !is_page()) {
        return;
    }

    $request_uri  = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $request_path = trim((string) parse_url($request_uri, PHP_URL_PATH), '/');
    if ($request_path === '') {
        return;
    }

    $segments = explode('/', $request_path);
    if (count($segments) < 2) {
        return;
    }

    $tax_base_to_taxonomy = [
        'services'    => 'uslugi_tax',
        'rajony'      => 'rayonu_tax',
        'metro'       => 'metro_tax',
        'price'       => 'price_tax',
        'vozrast'     => 'vozrast_tax',
        'nationalnost'=> 'nationalnost_tax',
        'ves'         => 'ves_tax',
        'cvet-volos'  => 'cvet-volos_tax',
        'rost'        => 'rost_tax',
        'grud'        => 'grud_tax',
    ];

    $base = (string) ($segments[0] ?? '');
    if (!isset($tax_base_to_taxonomy[$base])) {
        return;
    }

    $page_id = (int) get_queried_object_id();
    if ($page_id <= 0) {
        return;
    }

    $page_slug = (string) get_post_field('post_name', $page_id);
    if ($page_slug === '' || (string) end($segments) !== $page_slug) {
        return;
    }

    $term = get_term_by('slug', $page_slug, $tax_base_to_taxonomy[$base]);
    if (!$term || is_wp_error($term)) {
        return;
    }

    $target_url = (string) get_permalink($page_id);
    if ($target_url === '') {
        return;
    }

    $target_path = trim((string) parse_url($target_url, PHP_URL_PATH), '/');
    if ($target_path === '' || $target_path === $request_path) {
        return;
    }

    $query = (string) parse_url($request_uri, PHP_URL_QUERY);
    if ($query !== '') {
        $target_url .= (strpos($target_url, '?') === false ? '?' : '&') . $query;
    }

    wp_safe_redirect($target_url, 301);
    exit;
}, 2);

add_filter('template_include', function ($template) {
    if (!is_page()) {
        return $template;
    }

    $slug = (string) get_query_var('pagename');
    if ($slug === '') {
        $page_id = get_queried_object_id();
        if ($page_id) {
            $slug = (string) get_post_field('post_name', $page_id);
        }
    }

    $term_landing_slugs = [
        'services',
        'rajony',
        'metro',
        'price',
        'cvet-volos',
        'grud',
        'vozrast',
        'nationalnost',
        'ves',
        'rost',
    ];

    if (!in_array($slug, $term_landing_slugs, true)) {
        return $template;
    }

    $forced = locate_template('pages/term-landing.php');
    return $forced ?: $template;
}, 99);



// Отключаем meta name="robots", который WP добавляет через wp_head
add_action('init', function () {
    // В ядре он вешается как add_action( 'wp_head', 'wp_robots', 1 )
    remove_action('wp_head', 'wp_robots', 1);
}, 11);

// Глобальный массив для сбора моделей
$GLOBALS['site_ldjson_models'] = [];

function site_ldjson_collect_model($model)
{
    if (!empty($model)) {
        $GLOBALS['site_ldjson_models'][] = $model;
    }
}

add_image_size('model_card', 334, 500, true); // hard crop

add_filter('acf/settings/show_updates', '__return_false');

/**
 * For selected taxonomy landing pages (/services/{slug}, /price/{slug}, etc.)
 * point frontend admin-bar "Edit" action to the linked CPT post edit screen.
 */
add_action('admin_bar_menu', function (WP_Admin_Bar $wp_admin_bar): void {
    if (is_admin() || !is_user_logged_in()) {
        return;
    }

    $tax_to_post_type = [
        'uslugi_tax'      => 'uslugi',
        'price_tax'       => 'tsena',
        'vozrast_tax'     => 'vozrast',
        'nationalnost_tax'=> 'nacionalnost',
        'rost_tax'        => 'rost',
        'grud_tax'        => 'grud',
        'ves_tax'         => 'ves',
        'cvet-volos_tax'  => 'tsvet-volos',
    ];

    $qo = get_queried_object();
    if (!($qo instanceof WP_Term) || empty($qo->taxonomy) || empty($qo->slug)) {
        return;
    }

    $taxonomy = (string) $qo->taxonomy;
    if (!isset($tax_to_post_type[$taxonomy]) || !is_tax($taxonomy)) {
        return;
    }

    $linked_post = get_page_by_path((string) $qo->slug, OBJECT, $tax_to_post_type[$taxonomy]);
    if (!($linked_post instanceof WP_Post) || empty($linked_post->ID)) {
        return;
    }

    if (!current_user_can('edit_post', (int) $linked_post->ID)) {
        return;
    }

    $node = $wp_admin_bar->get_node('edit');
    if (!$node) {
        return;
    }

    $wp_admin_bar->add_node([
        'id'    => 'edit',
        'title' => 'Изменить запись',
        'href'  => get_edit_post_link((int) $linked_post->ID, 'raw'),
        'meta'  => $node->meta,
    ]);
}, 999);
add_filter('rest_pre_dispatch', function($result, $server, $request) {
    $token = $request->get_header('X-Zaliv-Key');
    
    // Сверяем ключ с тем, что прописан в твоем Config.php
    if (!empty($token) && $token === \Zaliv\Config::API_SECRET_KEY) {
        // Находим админа (обычно ID 1) и авторизуем запрос под ним
        $admin_user = get_users(['role' => 'administrator', 'number' => 1])[0] ?? null;
        if ($admin_user) {
            wp_set_current_user($admin_user->ID);
        }
    }
    return $result;
}, 10, 3);
