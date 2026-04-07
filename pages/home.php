<?php
/*
Template Name: Универсальный для страниц моделей/ главной
*/
/* Template Post Type: page, tsena, vozrast, nacionalnost, rajon, metro, rost, grud, ves, tsvet-volos, uslugi */

if (!defined('ABSPATH')) exit;

require_once get_template_directory() . '/components/ModelFilter.php';
require_once get_template_directory() . '/components/ModelGrid.php';
require_once get_template_directory() . '/components/auto-text.php';

/* ============================================================
 * 1) БАЗОВЫЙ ФИЛЬТР + ПРЕДВАРИТЕЛЬНЫЙ СПИСОК ДЛЯ JSON-LD
 * ============================================================ */
$ALLOWED_TAX = [
    'price_tax',          // Цена
    'vozrast_tax',        // Возраст
    'rayonu_tax',         // Районы
    'metro_tax',          // Метро
    'rost_tax',           // Рост
    'ves_tax',            // Вес
    'cvet-volos_tax',     // Цвет волос
    'nationalnost_tax',   // Национальность
    'grud_tax',           // Грудь
    'drygie_tax',
    'uslugi_tax'       // Другие
];

/** 1.1 Получаем base_tax для текущего урла */
$base_tax = [];

// a) Архив таксономии
if (is_tax() || is_tag() || is_category()) {
    $qo = get_queried_object();
    if ($qo instanceof WP_Term && in_array($qo->taxonomy, $ALLOWED_TAX, true)) {
        $base_tax = ['taxonomy' => $qo->taxonomy, 'terms' => [(int)$qo->term_id]];
    }
}

// b) Статическая страница: слаг совпадает с термом одной из разрешённых такс
if (empty($base_tax) && is_page()) {
    $page_id   = get_queried_object_id();
    $page_slug = $page_id ? (string) get_post_field('post_name', $page_id) : '';
    // Страница "Элитные" фильтруется через meta_query (vip / price) в ModelGrid,
    // а не через term drygie_tax/elitnyye-prostitutki — иначе попадают только анкеты с термом.
    if ($page_slug !== '' && $page_slug !== 'elitnyye-prostitutki') {
        foreach ($ALLOWED_TAX as $tx) {
            $t = get_term_by('slug', $page_slug, $tx);
            if ($t && !is_wp_error($t)) {
                $base_tax = ['taxonomy' => $tx, 'terms' => [(int)$t->term_id]];
                break;
            }
        }
    }
}

// c) Посадочные CPT
if (empty($base_tax)) {
    $qo        = get_queried_object();
    $post_type = ($qo instanceof WP_Post) ? $qo->post_type : '';
    $page_slug = ($qo instanceof WP_Post && !empty($qo->post_name)) ? (string) $qo->post_name : '';

    $CPT_TAX_MAP = [
        'tsena'         => 'price_tax',
        'vozrast'       => 'vozrast_tax',
        'nacionalnost'  => 'nationalnost_tax',
        'rajon'         => 'rayonu_tax',
        'metro'         => 'metro_tax',
        'rost'          => 'rost_tax',
        'grud'          => 'grud_tax',
        'ves'           => 'ves_tax',
        'tsvet-volos'   => 'cvet-volos_tax',
        'uslugi'        => 'uslugi_tax',
    ];

    if ($page_slug !== '' && isset($CPT_TAX_MAP[$post_type])) {
        $tx = $CPT_TAX_MAP[$post_type];
        $t  = get_term_by('slug', $page_slug, $tx);
        if ($t && !is_wp_error($t)) {
            $base_tax = ['taxonomy' => $tx, 'terms' => [(int)$t->term_id]];
        }
    }
}

/** 1.2 Готовим лёгкий список моделей для JSON-LD */
$ld_models = [];
$args = [
    'post_type'           => 'models',
    'post_status'         => 'publish',
    'posts_per_page'      => 9,
    'no_found_rows'       => true,
    'orderby'             => 'date',
    'order'               => 'DESC',
    'fields'              => 'ids',
    'suppress_filters'    => false,
    'ignore_sticky_posts' => true,
];
if (!empty($base_tax)) {
    $args['tax_query'] = [[
        'taxonomy' => $base_tax['taxonomy'],
        'field'    => 'term_id',
        'terms'    => array_map('intval', (array)$base_tax['terms']),
        'operator' => 'IN',
    ]];
}
$ids = get_posts($args);

$ph = get_stylesheet_directory_uri() . '/assets/images/placeholder-thumbs.webp';
foreach ((array)$ids as $pid) {
    $name = get_the_title($pid);
    $uri  = get_permalink($pid);
    if (!$name || !$uri) continue;
    $img  = get_the_post_thumbnail_url($pid, 'medium') ?: $ph;
    $ld_models[] = ['name' => $name, 'uri' => $uri, 'image' => $img];
}

/** 1.3 Прокинем base_tax диспетчеру JSON-LD и во фронт */
set_query_var('base_tax', $base_tax);

/* ===== ШАПКА ===== */
get_header();

$paged   = max(1, (int)(get_query_var('paged') ?: get_query_var('page') ?: 1));
$post_id = get_queried_object_id();

// Для taxonomy-URL (services/slug и т.п.) поля должны браться из связанной CPT-записи.
$qo = get_queried_object();
if ($qo instanceof WP_Term && !empty($qo->slug)) {
    $tax_to_post_type = [
        'uslugi_tax'       => 'uslugi',
        'price_tax'        => 'tsena',
        'vozrast_tax'      => 'vozrast',
        'nationalnost_tax' => 'nacionalnost',
        'rayonu_tax'       => 'rajon',
        'metro_tax'        => 'metro',
        'rost_tax'         => 'rost',
        'grud_tax'         => 'grud',
        'ves_tax'          => 'ves',
        'cvet-volos_tax'   => 'tsvet-volos',
    ];

    $taxonomy = (string) $qo->taxonomy;
    if (isset($tax_to_post_type[$taxonomy])) {
        $linked_post = get_page_by_path((string) $qo->slug, OBJECT, $tax_to_post_type[$taxonomy]);
        if ($linked_post instanceof WP_Post && !empty($linked_post->ID)) {
            $post_id = (int) $linked_post->ID;
        }
    }
}
set_query_var('landing_source_post_id', $post_id);

/**
 * 2) ACF-поля и контент
 */
$p_after_h1_manual = function_exists('get_field') ? (get_field('p_atc', $post_id) ?: '') : '';
$p_after_h1 = $p_after_h1_manual;
$p_under_h2 = function_exists('get_field') ? (get_field('p_title', $post_id) ?: '') : '';
$content    = function_exists('get_field') ? (get_field('content', $post_id) ?: '') : '';
$text_block = function_exists('get_field') ? (get_field('text_block', $post_id) ?: '') : '';
$h1_manual_from_admin = '';
if (function_exists('get_field')) {
    $h1_manual_from_admin = (string) (get_field('h1_atc', $post_id) ?: '');
    if ($h1_manual_from_admin === '') {
        $h1_manual_from_admin = (string) (get_field('h1', $post_id) ?: '');
    }
}
$p_after_h1_is_auto = false;
$auto_links_block = '';
$custom_h1_override = '';

if (function_exists('dosugmoskva24_generate_landing_auto_text')) {
    $auto_text = dosugmoskva24_generate_landing_auto_text([
        'post_id' => $post_id,
        'post_type' => (string) get_post_type($post_id),
        'page_slug' => $post_id ? (string) get_post_field('post_name', $post_id) : '',
        'taxonomy' => ($qo instanceof WP_Term) ? (string) $qo->taxonomy : '',
        'base_tax' => $base_tax,
        'city' => 'Москва',
    ]);

    if ($p_after_h1 === '' && !empty($auto_text['p_after_h1'])) {
        $p_after_h1 = (string) $auto_text['p_after_h1'];
        $p_after_h1_is_auto = true;
    }
    if ($p_under_h2 === '' && !empty($auto_text['p_under_h2'])) {
        $p_under_h2 = (string) $auto_text['p_under_h2'];
    }
    if ($content === '' && !empty($auto_text['content'])) {
        $content = (string) $auto_text['content'];
    }
    if ($text_block === '' && !empty($auto_text['text_block'])) {
        $text_block = (string) $auto_text['text_block'];
    }
}

if ($paged === 1 && $p_after_h1_manual === '' && function_exists('dosugmoskva24_generate_landing_links_block')) {
    $auto_links_block = dosugmoskva24_generate_landing_links_block([
        'post_id' => $post_id,
        'post_type' => (string) get_post_type($post_id),
        'page_slug' => $post_id ? (string) get_post_field('post_name', $post_id) : '',
        'taxonomy' => ($qo instanceof WP_Term) ? (string) $qo->taxonomy : '',
        'base_tax' => $base_tax,
        'city' => 'Москва',
    ]);
}

$is_district_context = (($base_tax['taxonomy'] ?? '') === 'rayonu_tax' && !empty($base_tax['terms']));
if ($is_district_context) {
    $district_term_id = (int) ((array) $base_tax['terms'])[0];
    $district_term = get_term($district_term_id, 'rayonu_tax');

    if ($district_term instanceof WP_Term && !is_wp_error($district_term)) {
        $district_name = function_exists('dosugmoskva24_auto_text_clean')
            ? dosugmoskva24_auto_text_clean((string) $district_term->name)
            : trim(wp_strip_all_tags((string) $district_term->name));

        if ($district_name !== '') {
            $district_name_safe = esc_html($district_name);

            $models_count = isset($auto_text['models_count']) ? (int) $auto_text['models_count'] : 0;
            if ($models_count <= 0 && function_exists('dosugmoskva24_auto_text_count_models')) {
                $models_count = dosugmoskva24_auto_text_count_models($base_tax);
            }
            $models_count_text = number_format_i18n(max(0, $models_count));

            $model_ids_at_district = [];
            if (function_exists('dosugmoskva24_auto_text_get_model_ids_by_terms')) {
                $model_ids_at_district = dosugmoskva24_auto_text_get_model_ids_by_terms('rayonu_tax', [$district_term_id], 420);
            }

            $station_terms = [];
            if (
                !empty($model_ids_at_district)
                && function_exists('dosugmoskva24_auto_text_get_term_rows_by_models')
                && function_exists('dosugmoskva24_auto_text_terms_from_rows')
            ) {
                $metro_rows = dosugmoskva24_auto_text_get_term_rows_by_models($model_ids_at_district, 'metro_tax', [], 3);
                $station_terms = dosugmoskva24_auto_text_terms_from_rows($metro_rows);
            }

            if (count($station_terms) < 3) {
                $fallback_stations = get_terms([
                    'taxonomy' => 'metro_tax',
                    'hide_empty' => true,
                    'number' => 8,
                ]);
                if (!is_wp_error($fallback_stations) && !empty($fallback_stations)) {
                    $known_station_ids = [];
                    foreach ($station_terms as $station_term) {
                        if ($station_term instanceof WP_Term) {
                            $known_station_ids[(int) $station_term->term_id] = true;
                        }
                    }
                    foreach ($fallback_stations as $fallback_station) {
                        if (!$fallback_station instanceof WP_Term) {
                            continue;
                        }
                        $fallback_station_id = (int) $fallback_station->term_id;
                        if (isset($known_station_ids[$fallback_station_id])) {
                            continue;
                        }
                        $station_terms[] = $fallback_station;
                        $known_station_ids[$fallback_station_id] = true;
                        if (count($station_terms) >= 3) {
                            break;
                        }
                    }
                }
            }

            $station_items = [];
            foreach ($station_terms as $station_term) {
                if (!$station_term instanceof WP_Term) {
                    continue;
                }
                $name = function_exists('dosugmoskva24_auto_text_clean')
                    ? dosugmoskva24_auto_text_clean((string) $station_term->name)
                    : trim(wp_strip_all_tags((string) $station_term->name));
                if ($name === '') {
                    continue;
                }
                $station_url = get_term_link($station_term);
                $station_items[] = (is_string($station_url) && $station_url !== '' && !is_wp_error($station_url))
                    ? '<a href="' . esc_url($station_url) . '">' . esc_html($name) . '</a>'
                    : esc_html($name);
                if (count($station_items) >= 3) {
                    break;
                }
            }
            $station_fallback_labels = ['центральных станций', 'транспортных узлов', 'пересадочных станций'];
            while (count($station_items) < 3) {
                $station_items[] = esc_html($station_fallback_labels[count($station_items)]);
            }

            $h1_station_name = 'Москвы';
            foreach ($station_terms as $station_term) {
                if (!$station_term instanceof WP_Term) {
                    continue;
                }
                $station_name_for_h1 = function_exists('dosugmoskva24_auto_text_clean')
                    ? dosugmoskva24_auto_text_clean((string) $station_term->name)
                    : trim(wp_strip_all_tags((string) $station_term->name));
                if ($station_name_for_h1 !== '') {
                    $h1_station_name = $station_name_for_h1;
                    break;
                }
            }
            $district_h1 = "Проститутки район {$district_name}";
            $custom_h1_override = $district_h1;
            set_query_var('auto_h1', $district_h1);
            $GLOBALS['auto_h1'] = $district_h1;
            $district_h2 = "Анкеты проституток в районе {$district_name}";
            set_query_var('auto_h2', $district_h2);
            $GLOBALS['auto_h2'] = $district_h2;

            $resolve_min_price = static function (string $meta_key, int $term_id = 0): int {
                $args = [
                    'post_type' => 'models',
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                    'fields' => 'ids',
                    'no_found_rows' => true,
                    'orderby' => 'meta_value_num',
                    'order' => 'ASC',
                    'meta_key' => $meta_key,
                    'meta_type' => 'NUMERIC',
                    'meta_query' => [[
                        'key' => $meta_key,
                        'value' => 0,
                        'type' => 'NUMERIC',
                        'compare' => '>',
                    ]],
                ];
                if ($term_id > 0) {
                    $args['tax_query'] = [[
                        'taxonomy' => 'rayonu_tax',
                        'field' => 'term_id',
                        'terms' => [$term_id],
                        'operator' => 'IN',
                    ]];
                }

                $q = new WP_Query($args);

                $price = 0;
                if (!empty($q->posts)) {
                    $pid = (int) $q->posts[0];
                    $price = (int) get_post_meta($pid, $meta_key, true);
                }
                wp_reset_postdata();
                return max(0, $price);
            };

            $min_price_outcall = $resolve_min_price('price_outcall', $district_term_id);
            $min_price_incall = $resolve_min_price('price', $district_term_id);
            $price_pool = array_filter([$min_price_outcall, $min_price_incall], static function (int $price): bool {
                return $price > 0;
            });
            $min_price = !empty($price_pool) ? min($price_pool) : 0;
            if ($min_price <= 0) {
                $global_min_price_outcall = $resolve_min_price('price_outcall');
                $global_min_price_incall = $resolve_min_price('price');
                $global_price_pool = array_filter([$global_min_price_outcall, $global_min_price_incall], static function (int $price): bool {
                    return $price > 0;
                });
                $min_price = !empty($global_price_pool) ? min($global_price_pool) : 0;
            }
            if ($min_price <= 0 && function_exists('_seo_min_price_label_by_term')) {
                $min_price_label_raw = (string) _seo_min_price_label_by_term($district_term, 'rayonu_tax');
                $min_price = (int) preg_replace('~\D+~', '', $min_price_label_raw);
            }
            $min_price_text = number_format_i18n(max(1, $min_price));

            $neighbor_terms = [];
            if (
                !empty($station_terms)
                && function_exists('dosugmoskva24_auto_text_get_model_ids_by_terms')
                && function_exists('dosugmoskva24_auto_text_get_term_rows_by_models')
                && function_exists('dosugmoskva24_auto_text_terms_from_rows')
            ) {
                $station_ids = [];
                foreach ($station_terms as $station_term) {
                    if ($station_term instanceof WP_Term) {
                        $station_ids[] = (int) $station_term->term_id;
                    }
                }
                if (!empty($station_ids)) {
                    $model_ids_by_stations = dosugmoskva24_auto_text_get_model_ids_by_terms('metro_tax', $station_ids, 560);
                    $neighbor_rows = dosugmoskva24_auto_text_get_term_rows_by_models(
                        $model_ids_by_stations,
                        'rayonu_tax',
                        [$district_term_id],
                        3
                    );
                    $neighbor_terms = dosugmoskva24_auto_text_terms_from_rows($neighbor_rows);
                }
            }

            if (count($neighbor_terms) < 3) {
                $fallback_neighbors = get_terms([
                    'taxonomy' => 'rayonu_tax',
                    'hide_empty' => true,
                    'exclude' => [$district_term_id],
                    'number' => 8,
                ]);
                if (!is_wp_error($fallback_neighbors) && !empty($fallback_neighbors)) {
                    $known_neighbor_ids = [];
                    foreach ($neighbor_terms as $neighbor_term) {
                        if ($neighbor_term instanceof WP_Term) {
                            $known_neighbor_ids[(int) $neighbor_term->term_id] = true;
                        }
                    }
                    foreach ($fallback_neighbors as $fallback_neighbor) {
                        if (!$fallback_neighbor instanceof WP_Term) {
                            continue;
                        }
                        $fallback_neighbor_id = (int) $fallback_neighbor->term_id;
                        if (isset($known_neighbor_ids[$fallback_neighbor_id])) {
                            continue;
                        }
                        $neighbor_terms[] = $fallback_neighbor;
                        $known_neighbor_ids[$fallback_neighbor_id] = true;
                        if (count($neighbor_terms) >= 3) {
                            break;
                        }
                    }
                }
            }

            $neighbor_items = [];
            foreach ($neighbor_terms as $neighbor_term) {
                if (!$neighbor_term instanceof WP_Term) {
                    continue;
                }
                $neighbor_name = function_exists('dosugmoskva24_auto_text_clean')
                    ? dosugmoskva24_auto_text_clean((string) $neighbor_term->name)
                    : trim(wp_strip_all_tags((string) $neighbor_term->name));
                if ($neighbor_name === '') {
                    continue;
                }
                $neighbor_url = get_term_link($neighbor_term);
                $neighbor_items[] = '<li>' . (
                    is_string($neighbor_url) && $neighbor_url !== '' && !is_wp_error($neighbor_url)
                        ? '<a href="' . esc_url($neighbor_url) . '">' . esc_html($neighbor_name) . '</a>'
                        : esc_html($neighbor_name)
                ) . '</li>';
                if (count($neighbor_items) >= 3) {
                    break;
                }
            }
            while (count($neighbor_items) < 3) {
                $neighbor_items[] = '<li>Соседний район</li>';
            }
            $neighbor_list = implode('', $neighbor_items);

            $p_after_h1 = '<p>В этом разделе представлен актуальный список проверенных анкет проституток, предлагающих интимный досуг в границах района ' . $district_name_safe . '. Если вы ищете качественный секс отдых без посредников, здесь собраны профили ' . esc_html($models_count_text) . ' проституток, готовых к встрече в ближайшее время. Благодаря удобному расположению в ' . $district_name_safe . ', вы можете организовать свидание с девушкой в течение 15-20 минут.</p>';
            $p_after_h1_is_auto = true;

            $p_under_h2 = '';
            $auto_links_block = '';
            $content = '<h2>Интимный отдых и услуги секса в районе ' . $district_name_safe . '</h2>'
                . '<p>Выбор проститутки в ' . $district_name_safe . ' гарантирует вам полную анонимность и большой выбор программ. Девушки из нашего каталога работают в частном секторе и современных ЖК, обеспечивая комфортный интим сервис в шаговой доступности от ключевых точек района.</p>'
                . '<ul>'
                . '<li>Локация и метро: Основная концентрация анкет сосредоточена возле станций ' . $station_items[0] . ', ' . $station_items[1] . ' и ' . $station_items[2] . '.</li>'
                . '<li>Стоимость услуг: Цены на интим в районе ' . $district_name_safe . ' начинаются от ' . esc_html($min_price_text) . ' рублей за час.</li>'
                . '<li>Реальные фото: Все анкеты девушек проходят верификацию. Пометка «Проверено» подтверждает, что снимки в профиле на 100% соответствуют реальности.</li>'
                . '<li>Выезд и прием: Большинство мастеров предлагают как прием в своих апартаментах, так и выезд проституток по любому адресу в пределах ' . $district_name_safe . '.</li>'
                . '</ul>'
                . '<h2>Стоимость проституток и подбор анкет поблизости</h2>'
                . '<p>Если вы не нашли подходящий вариант для досуга непосредственно в ' . $district_name_safe . ', рекомендуем расширить географию поиска. Вы можете найти дешевых проституток или VIP-моделей в соседних локациях:</p>'
                . '<ol>' . $neighbor_list . '</ol>'
                . '<p>Такая навигация позволит вам быстро забронировать интим услуги у проверенной леди в радиусе 10-15 минут на авто или такси.</p>';
            $text_block = '';
        }
    }
}

$is_metro_context = (($base_tax['taxonomy'] ?? '') === 'metro_tax' && !empty($base_tax['terms']));
if ($is_metro_context) {
    $station_term_id = (int) ((array) $base_tax['terms'])[0];
    $station_term = get_term($station_term_id, 'metro_tax');

    if ($station_term instanceof WP_Term && !is_wp_error($station_term)) {
        $station_name = function_exists('dosugmoskva24_auto_text_clean')
            ? dosugmoskva24_auto_text_clean((string) $station_term->name)
            : trim(wp_strip_all_tags((string) $station_term->name));

        if ($station_name !== '') {
            $station_name_safe = esc_html($station_name);

            $models_count = isset($auto_text['models_count']) ? (int) $auto_text['models_count'] : 0;
            if ($models_count <= 0 && function_exists('dosugmoskva24_auto_text_count_models')) {
                $models_count = dosugmoskva24_auto_text_count_models($base_tax);
            }
            $models_count_text = number_format_i18n(max(0, $models_count));

            $metro_h1 = "Проститутки у метро {$station_name}";
            $custom_h1_override = $metro_h1;
            set_query_var('auto_h1', $metro_h1);
            $GLOBALS['auto_h1'] = $metro_h1;
            $metro_h2 = "Анкеты проституток у метро {$station_name}";
            set_query_var('auto_h2', $metro_h2);
            $GLOBALS['auto_h2'] = $metro_h2;

            $line_name = '';
            $line_meta_keys = ['line_name', 'metro_line', 'line', 'vetka', 'line_title'];
            foreach ($line_meta_keys as $line_meta_key) {
                $line_raw = (string) get_term_meta($station_term_id, $line_meta_key, true);
                $line_raw = function_exists('dosugmoskva24_auto_text_clean')
                    ? dosugmoskva24_auto_text_clean($line_raw)
                    : trim(wp_strip_all_tags($line_raw));
                if ($line_raw !== '') {
                    $line_name = $line_raw;
                    break;
                }
            }
            $station_parent_id = (int) $station_term->parent;
            if ($line_name === '' && $station_parent_id > 0) {
                $parent_term = get_term($station_parent_id, 'metro_tax');
                if ($parent_term instanceof WP_Term && !is_wp_error($parent_term)) {
                    $line_name = function_exists('dosugmoskva24_auto_text_clean')
                        ? dosugmoskva24_auto_text_clean((string) $parent_term->name)
                        : trim(wp_strip_all_tags((string) $parent_term->name));
                }
            }
            if ($line_name === '') {
                $line_name = 'основная';
            }
            $line_name_safe = esc_html($line_name);

            $model_ids_at_station = [];
            if (function_exists('dosugmoskva24_auto_text_get_model_ids_by_terms')) {
                $model_ids_at_station = dosugmoskva24_auto_text_get_model_ids_by_terms('metro_tax', [$station_term_id], 420);
            }

            $district_terms = [];
            if (
                !empty($model_ids_at_station)
                && function_exists('dosugmoskva24_auto_text_get_term_rows_by_models')
                && function_exists('dosugmoskva24_auto_text_terms_from_rows')
            ) {
                $district_rows = dosugmoskva24_auto_text_get_term_rows_by_models($model_ids_at_station, 'rayonu_tax', [], 3);
                $district_terms = dosugmoskva24_auto_text_terms_from_rows($district_rows);
            }
            if (empty($district_terms)) {
                $district_fallback = get_terms([
                    'taxonomy' => 'rayonu_tax',
                    'hide_empty' => true,
                    'number' => 3,
                ]);
                if (!is_wp_error($district_fallback) && !empty($district_fallback)) {
                    foreach ($district_fallback as $fallback_district) {
                        if ($fallback_district instanceof WP_Term) {
                            $district_terms[] = $fallback_district;
                        }
                    }
                }
            }

            $district_label = 'ближайшего района';
            $district_link = esc_html($district_label);
            if (!empty($district_terms) && $district_terms[0] instanceof WP_Term) {
                $main_district = $district_terms[0];
                $district_name_raw = function_exists('dosugmoskva24_auto_text_clean')
                    ? dosugmoskva24_auto_text_clean((string) $main_district->name)
                    : trim(wp_strip_all_tags((string) $main_district->name));
                if ($district_name_raw !== '') {
                    $district_label = $district_name_raw;
                    $district_url = get_term_link($main_district);
                    if (is_string($district_url) && $district_url !== '' && !is_wp_error($district_url)) {
                        $district_link = '<a href="' . esc_url($district_url) . '">' . esc_html($district_name_raw) . '</a>';
                    } else {
                        $district_link = esc_html($district_name_raw);
                    }
                }
            }

            $resolve_min_price = static function (string $meta_key, string $taxonomy = '', int $term_id = 0): int {
                $args = [
                    'post_type' => 'models',
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                    'fields' => 'ids',
                    'no_found_rows' => true,
                    'orderby' => 'meta_value_num',
                    'order' => 'ASC',
                    'meta_key' => $meta_key,
                    'meta_type' => 'NUMERIC',
                    'meta_query' => [[
                        'key' => $meta_key,
                        'value' => 0,
                        'type' => 'NUMERIC',
                        'compare' => '>',
                    ]],
                ];
                if ($taxonomy !== '' && $term_id > 0) {
                    $args['tax_query'] = [[
                        'taxonomy' => $taxonomy,
                        'field' => 'term_id',
                        'terms' => [$term_id],
                        'operator' => 'IN',
                    ]];
                }

                $q = new WP_Query($args);
                $price = 0;
                if (!empty($q->posts)) {
                    $pid = (int) $q->posts[0];
                    $price = (int) get_post_meta($pid, $meta_key, true);
                }
                wp_reset_postdata();
                return max(0, $price);
            };

            $min_price_outcall = $resolve_min_price('price_outcall', 'metro_tax', $station_term_id);
            $min_price_incall = $resolve_min_price('price', 'metro_tax', $station_term_id);
            $price_pool = array_filter([$min_price_outcall, $min_price_incall], static function (int $price): bool {
                return $price > 0;
            });
            $min_price = !empty($price_pool) ? min($price_pool) : 0;
            if ($min_price <= 0) {
                $global_min_price_outcall = $resolve_min_price('price_outcall');
                $global_min_price_incall = $resolve_min_price('price');
                $global_price_pool = array_filter([$global_min_price_outcall, $global_min_price_incall], static function (int $price): bool {
                    return $price > 0;
                });
                $min_price = !empty($global_price_pool) ? min($global_price_pool) : 0;
            }
            if ($min_price <= 0 && function_exists('_seo_min_price_label_by_term')) {
                $min_price_label_raw = (string) _seo_min_price_label_by_term($station_term, 'metro_tax');
                $min_price = (int) preg_replace('~\D+~', '', $min_price_label_raw);
            }
            $min_price_text = number_format_i18n(max(1, $min_price));

            $neighbor_terms = [];
            $known_neighbor_ids = [];
            if ($station_parent_id > 0) {
                $siblings = get_terms([
                    'taxonomy' => 'metro_tax',
                    'hide_empty' => true,
                    'parent' => $station_parent_id,
                    'exclude' => [$station_term_id],
                    'number' => 12,
                    'orderby' => 'name',
                    'order' => 'ASC',
                ]);
                if (!is_wp_error($siblings) && !empty($siblings)) {
                    foreach ($siblings as $sibling) {
                        if (!$sibling instanceof WP_Term) {
                            continue;
                        }
                        $sid = (int) $sibling->term_id;
                        if (isset($known_neighbor_ids[$sid])) {
                            continue;
                        }
                        $neighbor_terms[] = $sibling;
                        $known_neighbor_ids[$sid] = true;
                        if (count($neighbor_terms) >= 3) {
                            break;
                        }
                    }
                }
            }

            if (
                count($neighbor_terms) < 3
                && !empty($district_terms)
                && function_exists('dosugmoskva24_auto_text_get_model_ids_by_terms')
                && function_exists('dosugmoskva24_auto_text_get_term_rows_by_models')
                && function_exists('dosugmoskva24_auto_text_terms_from_rows')
            ) {
                $district_ids = [];
                foreach ($district_terms as $district_term) {
                    if ($district_term instanceof WP_Term) {
                        $district_ids[] = (int) $district_term->term_id;
                    }
                }
                if (!empty($district_ids)) {
                    $model_ids_by_district = dosugmoskva24_auto_text_get_model_ids_by_terms('rayonu_tax', $district_ids, 560);
                    $near_metro_rows = dosugmoskva24_auto_text_get_term_rows_by_models(
                        $model_ids_by_district,
                        'metro_tax',
                        [$station_term_id],
                        8
                    );
                    $near_metro_terms = dosugmoskva24_auto_text_terms_from_rows($near_metro_rows);
                    foreach ($near_metro_terms as $near_metro_term) {
                        if (!$near_metro_term instanceof WP_Term) {
                            continue;
                        }
                        $mid = (int) $near_metro_term->term_id;
                        if (isset($known_neighbor_ids[$mid])) {
                            continue;
                        }
                        $neighbor_terms[] = $near_metro_term;
                        $known_neighbor_ids[$mid] = true;
                        if (count($neighbor_terms) >= 3) {
                            break;
                        }
                    }
                }
            }

            if (count($neighbor_terms) < 3) {
                $fallback_neighbors = get_terms([
                    'taxonomy' => 'metro_tax',
                    'hide_empty' => true,
                    'exclude' => [$station_term_id],
                    'number' => 20,
                    'orderby' => 'name',
                    'order' => 'ASC',
                ]);
                if (!is_wp_error($fallback_neighbors) && !empty($fallback_neighbors)) {
                    foreach ($fallback_neighbors as $fallback_neighbor) {
                        if (!$fallback_neighbor instanceof WP_Term) {
                            continue;
                        }
                        $fallback_id = (int) $fallback_neighbor->term_id;
                        if (isset($known_neighbor_ids[$fallback_id])) {
                            continue;
                        }
                        $neighbor_terms[] = $fallback_neighbor;
                        $known_neighbor_ids[$fallback_id] = true;
                        if (count($neighbor_terms) >= 3) {
                            break;
                        }
                    }
                }
            }

            $neighbor_items = [];
            foreach ($neighbor_terms as $neighbor_term) {
                if (!$neighbor_term instanceof WP_Term) {
                    continue;
                }
                $neighbor_name = function_exists('dosugmoskva24_auto_text_clean')
                    ? dosugmoskva24_auto_text_clean((string) $neighbor_term->name)
                    : trim(wp_strip_all_tags((string) $neighbor_term->name));
                if ($neighbor_name === '') {
                    continue;
                }
                $neighbor_url = get_term_link($neighbor_term);
                $neighbor_label = is_string($neighbor_url) && $neighbor_url !== '' && !is_wp_error($neighbor_url)
                    ? '<a href="' . esc_url($neighbor_url) . '">' . esc_html($neighbor_name) . '</a>'
                    : esc_html($neighbor_name);
                $neighbor_items[] = '<li>м. ' . $neighbor_label . '</li>';
                if (count($neighbor_items) >= 3) {
                    break;
                }
            }
            while (count($neighbor_items) < 3) {
                $neighbor_items[] = '<li>м. Ближайшая станция</li>';
            }
            $neighbor_list = implode('', $neighbor_items);

            $p_after_h1 = '<p>В данном разделе нашего каталога представлены реальные анкеты проституток, которые предлагают интим услуги в шаговой доступности от станции метро ' . $station_name_safe . ' (' . $line_name_safe . ' линия). Если вы ищете качественный досуг без посредников, здесь собраны ' . esc_html($models_count_text) . ' проституток, готовых к встрече. Среднее время ожидания проститутки в этом районе - всего 10-15 минут.</p>';
            $p_after_h1_is_auto = true;

            $p_under_h2 = '';
            $auto_links_block = '';
            $content = '<h2>Интимный досуг и отдых у м. ' . $station_name_safe . '</h2>'
                . '<p>Выбор проститутки у метро ' . $station_name_safe . ' - это гарантия конфиденциальности и экономии времени. Девушки принимают в уютных квартирах и приватных апартаментах, расположенных рядом с выходом из метрополитена.</p>'
                . '<ul>'
                . '<li>Стоимость и цены: Минимальная цена за час отдыха в локации ' . $station_name_safe . ' начинается от ' . esc_html($min_price_text) . ' рублей.</li>'
                . '<li>Реальные фото: Все профили проходят строгую модерацию. Метка «Проверено» подтверждает, что фото проституток на 100% соответствуют действительности.</li>'
                . '<li>Выбор услуг: Проститутки предлагают широкий спектр программ для полноценного интимного отдыха и расслабления после работы.</li>'
                . '<li>Удобная локация: Станция удобна как для жителей района ' . $district_link . ', так и для тех, кто ищет встречу в удобной транспортной доступности.</li>'
                . '</ul>'
                . '<h2>Поиск проститутки в районе ' . esc_html($district_label) . '</h2>'
                . '<p>Если среди текущих предложений на ' . $station_name_safe . ' вы не нашли подходящую кандидатуру, рекомендуем расширить поиск. Обратите внимание на анкеты девушек у соседних станций этой ветки:</p>'
                . '<ol>' . $neighbor_list . '</ol>'
                . '<p>Такая перелинковка поможет вам найти идеальный вариант для секс-досуга в радиусе 15-20 минут езды, если на основной точке все мастера заняты.</p>';
            $text_block = '';
        }
    }
}

$is_service_context = (($base_tax['taxonomy'] ?? '') === 'uslugi_tax' && !empty($base_tax['terms']));
if ($is_service_context) {
    $service_term_id = (int) ((array) $base_tax['terms'])[0];
    $service_term = get_term($service_term_id, 'uslugi_tax');

    if ($service_term instanceof WP_Term && !is_wp_error($service_term)) {
        $service_name = function_exists('dosugmoskva24_auto_text_clean')
            ? dosugmoskva24_auto_text_clean((string) $service_term->name)
            : trim(wp_strip_all_tags((string) $service_term->name));

        if ($service_name !== '') {
            $service_name_safe = esc_html($service_name);

            $service_models_count = isset($auto_text['models_count']) ? (int) $auto_text['models_count'] : 0;
            if ($service_models_count <= 0 && function_exists('dosugmoskva24_auto_text_count_models')) {
                $service_models_count = dosugmoskva24_auto_text_count_models($base_tax);
            }
            $service_count_text = 'актуальные анкеты';
            if ($service_models_count > 0) {
                $service_count_label = number_format_i18n($service_models_count);
                $service_count_word = function_exists('dosugmoskva24_auto_text_plural')
                    ? dosugmoskva24_auto_text_plural($service_models_count, 'анкета', 'анкеты', 'анкет')
                    : 'анкет';
                $service_count_text = $service_count_label . ' ' . $service_count_word;
            }

            $service_h1 = "{$service_name} в Москве";
            $custom_h1_override = $service_h1;
            set_query_var('auto_h1', $service_h1);
            $GLOBALS['auto_h1'] = $service_h1;
            set_query_var('auto_h2', 'Анкеты проституток');
            $GLOBALS['auto_h2'] = 'Анкеты проституток';

            $resolve_service_min_price = static function (string $meta_key, int $term_id): int {
                $q = new WP_Query([
                    'post_type' => 'models',
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                    'fields' => 'ids',
                    'no_found_rows' => true,
                    'orderby' => 'meta_value_num',
                    'order' => 'ASC',
                    'meta_key' => $meta_key,
                    'meta_type' => 'NUMERIC',
                    'meta_query' => [[
                        'key' => $meta_key,
                        'value' => 0,
                        'type' => 'NUMERIC',
                        'compare' => '>',
                    ]],
                    'tax_query' => [[
                        'taxonomy' => 'uslugi_tax',
                        'field' => 'term_id',
                        'terms' => [$term_id],
                        'operator' => 'IN',
                    ]],
                ]);

                $price = 0;
                if (!empty($q->posts)) {
                    $pid = (int) $q->posts[0];
                    $price = (int) get_post_meta($pid, $meta_key, true);
                }
                wp_reset_postdata();
                return max(0, $price);
            };

            $service_min_outcall = $resolve_service_min_price('price_outcall', $service_term_id);
            $service_min_incall = $resolve_service_min_price('price', $service_term_id);
            $service_price_pool = array_filter([$service_min_outcall, $service_min_incall], static function (int $price): bool {
                return $price > 0;
            });
            $service_min_price = !empty($service_price_pool) ? min($service_price_pool) : 0;
            if ($service_min_price <= 0 && function_exists('_seo_min_price_label_by_term')) {
                $service_min_price_raw = (string) _seo_min_price_label_by_term($service_term, 'uslugi_tax');
                $service_min_price = (int) preg_replace('~\D+~', '', $service_min_price_raw);
            }
            $service_min_price_text = number_format_i18n(max(1, $service_min_price));

            $p_after_h1 = '<p>Услуга «' . $service_name_safe . '» в Москве представлена в формате проверенных частных анкет без посредников. Сейчас в разделе ' . esc_html($service_count_text) . ', новые предложения добавляются регулярно.</p>';
            $p_after_h1_is_auto = true;

            $p_under_h2 = '';
            $auto_links_block = '';
            $content = '<h2>Как выбрать ' . $service_name_safe . ' в Москве</h2>'
                . '<p>Закажите ' . $service_name_safe . ' в Москве в удобном формате: у нас собраны актуальные предложения от лучших анкет столицы с понятными условиями встречи.</p>'
                . '<ul>'
                . '<li>Проверенные анкеты: каждая карточка содержит фото, базовые параметры и описание условий.</li>'
                . '<li>Стоимость: предложения по услуге «' . $service_name_safe . '» начинаются от ' . esc_html($service_min_price_text) . ' рублей за час.</li>'
                . '<li>Условия встречи: сравнивайте формат, доступность и дополнительные опции прямо в карточках.</li>'
                . '<li>Быстрый выбор: используйте фильтры по цене и параметрам, чтобы сократить поиск до 2-3 подходящих вариантов.</li>'
                . '</ul>'
                . '<p>Если первый вариант не подходит, обновите сортировку по дате добавления и сравните свежие анкеты в этом же разделе.</p>';
            $text_block = '';
        }
    }
}

$is_incall_page = is_page('prostitutki-priyem');
if (!$is_incall_page && $qo instanceof WP_Post) {
    $is_incall_page = ((string) $qo->post_name === 'prostitutki-priyem');
}

if ($is_incall_page) {
    $incall_models_count = 0;
    if (function_exists('dosugmoskva24_auto_text_count_models_by_meta')) {
        $incall_models_count = dosugmoskva24_auto_text_count_models_by_meta(['price', 'price_2_hours', 'price_night']);
    }
    if ($incall_models_count <= 0 && function_exists('dosugmoskva24_auto_text_count_models')) {
        $incall_models_count = dosugmoskva24_auto_text_count_models([]);
    }

    $incall_count_text = 'актуальные анкеты';
    if ($incall_models_count > 0) {
        $incall_count_label = number_format_i18n($incall_models_count);
        $incall_count_word = function_exists('dosugmoskva24_auto_text_plural')
            ? dosugmoskva24_auto_text_plural($incall_models_count, 'анкета', 'анкеты', 'анкет')
            : 'анкет';
        $incall_count_text = $incall_count_label . ' ' . $incall_count_word;
    }

    $resolve_incall_min_price = static function (string $meta_key): int {
        $q = new WP_Query([
            'post_type' => 'models',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'meta_key' => $meta_key,
            'meta_type' => 'NUMERIC',
            'meta_query' => [[
                'key' => $meta_key,
                'value' => 0,
                'type' => 'NUMERIC',
                'compare' => '>',
            ]],
        ]);

        $price = 0;
        if (!empty($q->posts)) {
            $pid = (int) $q->posts[0];
            $price = (int) get_post_meta($pid, $meta_key, true);
        }
        wp_reset_postdata();
        return max(0, $price);
    };

    $incall_price_pool = array_filter([
        $resolve_incall_min_price('price'),
        $resolve_incall_min_price('price_2_hours'),
        $resolve_incall_min_price('price_night'),
    ], static function (int $price): bool {
        return $price > 0;
    });
    $incall_min_price = !empty($incall_price_pool) ? min($incall_price_pool) : 0;
    $incall_min_price_text = number_format_i18n(max(1, $incall_min_price));

    $incall_h1 = function_exists('get_field') ? (string) (get_field('h1_atc', $post_id) ?: '') : '';
    if ($incall_h1 === '') {
        $incall_h1 = 'Проститутки с апартаментами в Москве';
    }
    $custom_h1_override = $incall_h1;
    set_query_var('auto_h1', $incall_h1);
    $GLOBALS['auto_h1'] = $incall_h1;
    set_query_var('auto_h2', 'Анкеты проституток');
    $GLOBALS['auto_h2'] = 'Анкеты проституток';

    $p_after_h1 = '<p>Когда не хочется тратить время на дорогу и лишние согласования, формат «приём» становится самым удобным решением. На странице собраны анкеты девушек, которые принимают у себя в Москве: это комфортная локация, предсказуемые условия встречи и возможность быстро подобрать подходящий вариант под ваш ритм.</p>'
        . '<p>Сейчас в разделе доступны ' . esc_html($incall_count_text) . '. Мы регулярно обновляем каталог, чтобы в выдаче оставались актуальные анкеты с заполненными параметрами, фотографиями и понятными условиями.</p>';
    $p_after_h1_is_auto = true;

    $p_under_h2 = '<p>Используйте фильтры по району, метро, возрасту и стоимости, чтобы сразу отсечь неподходящие варианты и оставить в выдаче только релевантные анкеты.</p>';
    $auto_links_block = '';
    $content = '<h2>Проститутки с апартаментами в Москве: как выбрать анкету без лишней суеты</h2>'
        . '<p>Категория «приём» создана для тех, кто ценит приватность и спокойный формат встречи. Вы не зависите от дорожной ситуации и заранее понимаете, где именно состоится досуг. Такой подход особенно удобен в плотном графике: выбор, сравнение и связь с девушкой занимают минимум времени.</p>'
        . '<p>Минимальная стоимость в разделе начинается от ' . esc_html($incall_min_price_text) . ' рублей. Итоговая цена зависит от продолжительности, локации и дополнительных условий, поэтому лучше сравнить несколько карточек, прежде чем принимать решение.</p>'
        . '<h3>На что обращать внимание в первую очередь</h3>'
        . '<ul>'
        . '<li>Полнота анкеты: фото, возраст, параметры, цены за 1/2 часа и ночь.</li>'
        . '<li>Локация: район и ближайшее метро, чтобы оценить время в пути.</li>'
        . '<li>Формат встречи: приём в апартаментах, дополнительные опции, доступность по времени.</li>'
        . '<li>Репутация анкеты: приоритет карточкам с актуальными данными и регулярными обновлениями.</li>'
        . '</ul>'
        . '<h3>Как быстрее найти «свой» вариант</h3>'
        . '<p>Сначала задайте диапазон стоимости и район, затем отсортируйте выдачу по дате добавления. После этого откройте 3-5 анкет и сравните не только цену, но и общие условия: это обычно даёт лучший результат, чем выбор только по минимальной стоимости.</p>'
        . '<p>Если первый вариант не подошёл, не закрывайте поиск: переключите сортировку, обновите фильтры и проверьте соседние районы. В категории «приём» хороший результат чаще всего находится после короткого сравнения нескольких анкет.</p>';
    $text_block = '<h2>Почему формат «приём» остаётся самым востребованным в Москве</h2>'
        . '<p>Услуги проституток с апартаментами в Москве стабильно пользуются спросом, потому что такой формат даёт максимум контроля и минимум неопределённости. Вы заранее понимаете условия, экономите время на логистике и получаете более предсказуемый сценарий встречи.</p>'
        . '<p>Внутри каталога собраны анкеты девушек, которые работают в формате приёма в разных районах города. Это позволяет выбрать не только по бюджету, но и по географии: рядом с домом, офисом или привычным маршрутом. При корректно настроенных фильтрах подбор занимает считанные минуты.</p>'
        . '<h3>Преимущества выбора через каталог</h3>'
        . '<ul>'
        . '<li>Понятная структура: цены, параметры, район, метро и базовые условия в одном месте.</li>'
        . '<li>Быстрое сравнение: можно сразу сопоставить несколько предложений по стоимости и локации.</li>'
        . '<li>Актуальность: новые анкеты регулярно попадают в выдачу, что расширяет выбор.</li>'
        . '<li>Гибкость: легко перейти от бюджетного сегмента к более премиальным вариантам и обратно.</li>'
        . '</ul>'
        . '<h3>Как читать анкету с пользой для себя</h3>'
        . '<p>Смотрите на анкету как на чек-лист: сначала базовые параметры и стоимость, затем локация и формат, после этого дополнительные детали. Такой подход снижает риск ошибочного выбора и помогает быстро понять, подходит ли конкретный вариант под ваш запрос.</p>'
        . '<p>Отдельное внимание стоит уделять времени встречи и транспортной доступности. Иногда анкета с чуть более высокой ценой оказывается выгоднее за счёт удобной локации и экономии времени.</p>'
        . '<h3>Подбор проституток с приёмом в Москве</h3>'
        . '<p>Если вам нужны проститутки с приёмом в Москве, этот раздел помогает найти анкету без долгого ручного поиска. Используйте фильтры по району, станции метро, возрасту и цене, чтобы получить точную выдачу под конкретный запрос. Для наиболее релевантного результата сравнивайте несколько карточек и выбирайте оптимальный баланс между стоимостью, локацией и условиями.</p>'
        . '<p>Каталог подходит как для быстрого выбора «здесь и сейчас», так и для более вдумчивого сравнения вариантов. Благодаря структурированной выдаче вы видите не только цену, но и контекст — район, формат встречи, заполненность анкеты и дополнительные параметры. Это делает подбор более прозрачным и удобным.</p>'
        . '<p>Раздел обновляется регулярно, поэтому в нём сохраняется актуальный пул предложений. Если подходящий вариант не найден с первого просмотра, расширьте фильтры, измените сортировку и проверьте соседние локации — чаще всего это быстро приводит к нужному результату.</p>';
}


/* 3) Локализация JS */
wp_register_script('models-filter-app', false, [], null, true);
wp_enqueue_script('models-filter-app');
wp_localize_script('models-filter-app', 'SiteModelsFilter', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce'   => wp_create_nonce('site_filter_nonce'),
    'baseTax' => $base_tax,
    'perPage' => 48,
]);
?>

<main class="bg-white text-black">

    <section>
        <?php
        // Автоматический H1 (компонент)
        $auto_h1_component = get_theme_file_path('components/h1-auto.php');
        if (file_exists($auto_h1_component)) {
            require $auto_h1_component;
        }
        ?>

        <h1 class="max-w-[1280px] 2xl:max-w-[1400px] mx-auto mt-2 p-4 text-3xl md:text-5xl font-extrabold tracking-tight leading-tight text-center">
            <?php
            $h1_from_admin = trim((string) $h1_manual_from_admin) !== '';
            if ($h1_from_admin) {
                $h1 = (string) $h1_manual_from_admin;
            } elseif ($custom_h1_override !== '') {
                $h1 = $custom_h1_override;
            } else {
                $h1 = get_query_var('auto_h1');
                if (empty($h1) && !empty($GLOBALS['auto_h1'])) {
                    $h1 = $GLOBALS['auto_h1'];
                }
                if (empty($h1)) {
                    $h1 = function_exists('get_field') ? (get_field('h1_atc', $post_id) ?: '') : '';
                }
                if (empty($h1)) {
                    $h1 = get_the_title($post_id);
                }
            }
            // Для авто-H1 убираем хвост после двоеточия, ручной H1 из админки не трогаем.
            if (!$h1_from_admin) {
                $h1 = trim((string) preg_replace('~\s*:\s*.*$~u', '', (string) $h1));
            }
            if ($paged > 1) {
                $h1 = trim($h1) . ' — страница ' . $paged;
            }
            echo esc_html($h1);
            ?>
        </h1>


        <?php if ($p_after_h1 && $paged === 1 && !$p_after_h1_is_auto):
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $is_bot = (bool) preg_match('/bot|crawl|spider|slurp|mediapartners-google|bingpreview|duckduckbot|baiduspider|yandex|ahrefs|semrush|screaming\s?frog|facebookexternalhit|telegrambot/i', $ua);
            $text_html = $p_after_h1_is_auto
                ? wp_kses_post($p_after_h1)
                : wp_kses_post(apply_filters('the_content', $p_after_h1));
            $uid = uniqid('ah1_'); 
        ?>
            <div class="content mx-auto max-w-[1280px] 2xl:max-w-[1400px] px-4 mt-4 md:mt-5 text-base md:text-lg leading-relaxed space-y-4
            [&_p]:text-justify [&_li]:text-justify [&_p]:[hyphens:auto] [&_li]:[hyphens:auto]">

                <div id="<?= $uid ?>_box"
                    class="relative overflow-hidden transition-[max-height] duration-300 ease-in-out"
                    style="<?= $is_bot ? 'max-height:none' : 'max-height:14rem' ?>">
                    <?= $text_html ?>
                    <div id="<?= $uid ?>_fade"
                        class="pointer-events-none absolute left-0 right-0 bottom-0 h-16"
                        style="<?= $is_bot ? 'display:none' : 'background:linear-gradient(to bottom, rgba(255,255,255,0), #fff 70%)' ?>"></div>
                </div>

                <button id="<?= $uid ?>_btn"
                    class="mt-3 inline-flex items-center gap-2 text-[#e865a0] font-semibold hover:opacity-90 transition"
                    aria-expanded="<?= $is_bot ? 'true' : 'false' ?>"
                    <?= $is_bot ? 'hidden' : '' ?>>
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M6 9l6 6 6-6" stroke-width="2" />
                    </svg>
                    <span data-label><?= $is_bot ? 'Свернуть' : 'Показать ещё' ?></span>
                </button>

            </div>
            <script>
                (function() {
                    var box = document.getElementById('<?= $uid ?>_box');
                    var fade = document.getElementById('<?= $uid ?>_fade');
                    var btn = document.getElementById('<?= $uid ?>_btn');
                    if (!box || !btn) return;

                    var collapsedMax = 224; 
                    if (box.scrollHeight <= collapsedMax + 5) {
                        box.style.maxHeight = 'none';
                        if (fade) fade.style.display = 'none';
                        btn.style.display = 'none';
                        return;
                    }

                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        var opened = btn.getAttribute('aria-expanded') === 'true';

                        if (opened) {
                            box.style.maxHeight = collapsedMax + 'px';
                            if (fade) fade.style.display = '';
                            btn.setAttribute('aria-expanded', 'false');
                            btn.querySelector('[data-label]').textContent = 'Показать ещё';
                        } else {
                            box.style.maxHeight = box.scrollHeight + 'px';
                            setTimeout(() => { box.style.maxHeight = 'none'; }, 250);
                            if (fade) fade.style.display = 'none';
                            btn.setAttribute('aria-expanded', 'true');
                            btn.querySelector('[data-label]').textContent = 'Свернуть';
                        }
                    });
                })();
            </script>
        <?php endif; ?>

    </section>

    <!-- Секция видео-сторис (только на спец. странице) -->
    <?php 
    if (is_page('s-video')) {
        require_once get_template_directory() . '/components/stories-modal.php'; // Исправлено: был лишний слэш
    }
    ?>

    <!-- Секция моделей -->
    <section class="mx-auto max-w-[1280px] 2xl:max-w-[1400px] px-4 flex flex-col items-center gap-8 mt-8">

        <div class="w-full flex-1">

            <div id="filter-sorting-area" class="w-full flex flex-col gap-6">
                <?php echo render_model_filter(); ?>
                
                <div class="title-and-sorting flex flex-col justify-between gap-4">
                    <?php 
                        $h2_models = get_query_var('auto_h2') ?: ($GLOBALS['auto_h2'] ?? '');
                        if ($h2_models === '') {
                            $h2_models = function_exists('get_field') ? (string) (get_field('h2_title', $post_id) ?: '') : '';
                        }
                        if ($h2_models === '') {
                            $h2_models = 'Проститутки Москвы';
                        }
                        if (!empty($h2_models)): ?>
                            <h2 class="text-2xl md:text-3xl font-bold tracking-tight break-words [hyphens:auto]">
                                <?= esc_html($h2_models) ?>
                            </h2>
                        <?php endif; ?>
                    <div class="flex items-center gap-3 self-end md:self-auto">
                        <label for="mf-sort-trigger" class="text-sm font-bold uppercase tracking-wide text-black-500">Сортировка:</label>
                        
                        <div class="relative mf-dropdown-container" id="mf-sort-container" style="width: auto;">
                            <!-- Dropdown trigger -->
                            <button type="button" id="mf-sort-trigger"
                                class="mf-dropdown-trigger h-10 px-2 flex items-center justify-between border border-neutral-200 rounded-md bg-white hover:border-neutral-400 transition-colors text-left font-bold"
                                style="min-width: 260px;">
                                <span class="text-[14px] text-black font-medium truncate mf-trigger-label">Дата добавления — новые</span>
                                <svg class="w-5 h-5 text-neutral-300 pointer-events-none flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>

                            <!-- Dropdown content -->
                            <div class="mf-dropdown-content absolute left-0 right-0 top-full mt-1 z-[70] hidden bg-white border border-neutral-200 rounded-md shadow-xl max-h-60 overflow-y-auto p-1 space-y-1">
                                <div class="mf-sort-item mf-dropdown-item is-active flex items-center px-2 py-2 rounded-md cursor-pointer transition-all duration-200 hover:bg-neutral-50 group" data-value="date_desc">
                                    <span class="text-[11px] font-bold text-neutral-700 group-hover:text-neutral-900 transition-colors">Дата добавления — новые</span>
                                </div>
                                <div class="mf-sort-item mf-dropdown-item flex items-center px-2 py-2 rounded-md cursor-pointer transition-all duration-200 hover:bg-neutral-50 group" data-value="date_asc">
                                    <span class="text-[11px] font-bold text-neutral-700 group-hover:text-neutral-900 transition-colors">Дата добавления — старые</span>
                                </div>
                                <div class="mf-sort-item mf-dropdown-item flex items-center px-2 py-2 rounded-md cursor-pointer transition-all duration-200 hover:bg-neutral-50 group" data-value="price_asc">
                                    <span class="text-[11px] font-bold text-neutral-700 group-hover:text-neutral-900 transition-colors">Цена — дешёвые</span>
                                </div>
                                <div class="mf-sort-item mf-dropdown-item flex items-center px-2 py-2 rounded-md cursor-pointer transition-all duration-200 hover:bg-neutral-50 group" data-value="price_desc">
                                    <span class="text-[11px] font-bold text-neutral-700 group-hover:text-neutral-900 transition-colors">Цена — дорогие</span>
                                </div>
                            </div>
                            <input type="hidden" id="mf-sort" name="sort" value="date_desc">
                        </div>
                    </div>
                </div>
                <style>.title-and-sorting { @media (min-width: 768px) { flex-direction: row } }</style>
            </div>

            <?php if (!empty($p_under_h2) && $paged === 1) : ?>
                <div class="content mt-4 text-neutral-700">
                    <?= wp_kses_post($p_under_h2) ?>
                </div>
            <?php endif; ?>

            <div id="ajax-models" class="mt-8">
                <?php echo render_model_grid_with_filters(); ?>
            </div>

        </div>
    </section>

    <script>
        window.pageContext = {
            post_type: '<?= esc_js(get_post_type($post_id)); ?>',
            post_slug: '<?= esc_js((string) get_post_field('post_name', $post_id)); ?>',
            is_singular: <?= is_singular() ? 'true' : 'false'; ?>,
            is_tax: <?= is_tax() ? 'true' : 'false'; ?>,
            taxonomy: '<?= is_tax() ? esc_js(get_queried_object()->taxonomy ?? '') : ''; ?>',
            term_slug: '<?= is_tax() ? esc_js(get_queried_object()->slug ?? '') : ''; ?>'
        };
    </script>


    <?php
    // For taxonomy landings keep only manual SEO texts from admin fields.
// Auto-generated template sections are disabled to avoid template content on listing pages.
    if (is_tax()) {
        $manual_p_under_h2 = function_exists('get_field') ? (get_field('p_title', $post_id) ?: '') : '';
        $manual_content    = function_exists('get_field') ? (get_field('content', $post_id) ?: '') : '';
        $manual_text_block = function_exists('get_field') ? (get_field('text_block', $post_id) ?: '') : '';

        $p_after_h1 = $p_after_h1_manual;
        $p_after_h1_is_auto = false;
        $p_under_h2 = $manual_p_under_h2;
        $content = $manual_content;
        $text_block = $manual_text_block;
        $auto_links_block = '';
    }


    $has_bottom_seo = $paged === 1 && (!empty($auto_links_block) || !empty($content) || !empty($text_block));
    $clean_bottom_seo_html = static function ($html) {
        $html = wp_kses_post($html);
        $html = preg_replace('/\s(?:class|style)=(["\']).*?\1/i', '', $html);

        return preg_replace_callback('/<(p|ul|ol)\b[^>]*>/i', static function ($m) {
            $tag = preg_replace('/\s{2,}/', ' ', $m[0]);
            $tag = str_replace(' >', '>', $tag);
            $name = strtolower($m[1]);

            if ($name === 'p') {
                return str_replace('<p', '<p style="margin:0;padding:0"', $tag);
            }

            return str_replace('<' . $name, '<' . $name . ' style="margin:0;padding:0;list-style-position:inside"', $tag);
        }, $html);
    };
    ?>
    <?php if ($has_bottom_seo) : ?>
        <section class="mx-auto max-w-[1280px] 2xl:max-w-[1400px] px-4 mb-6">
            <div class="content bg-neutral-50 text-neutral-800 border border-neutral-200 rounded-sm px-4 py-5 md:py-6">
                <?php if (!empty($auto_links_block)) : ?>
                    <div class="[&_a]:underline [&_a]:underline-offset-4 [&_a]:hover:opacity-80">
                        <?= $clean_bottom_seo_html($auto_links_block) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($content)) : ?>
                    <div class="<?= !empty($auto_links_block) ? 'mt-6' : '' ?> overflow-x-auto md:overflow-x-visible">
                        <?= $clean_bottom_seo_html($content) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($text_block)) : ?>
                    <div class="<?= (!empty($auto_links_block) || !empty($content)) ? 'mt-6' : '' ?>">
                        <?= $clean_bottom_seo_html($text_block) ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>


    <!-- FAQ Section -->
    <?php require_once get_template_directory() . '/components/faq-accordion.php'; ?>


    <!-- Responsive Table Scroll Wrapper -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.content table').forEach(function(t) {
                if (t.closest('.table-scroll') || t.closest('figure.wp-block-table') || t.classList.contains('responsive')) return;
                var w = document.createElement('div');
                w.className = 'table-scroll';
                t.parentNode.insertBefore(w, t);
                w.appendChild(t);
            });
        });
    </script>

</main>

<?php get_footer(); ?>
