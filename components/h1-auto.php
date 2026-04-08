<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('_auto_heading_clean')) {
    function _auto_heading_clean(string $s): string
    {
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace('~\s+~u', ' ', $s);
        return trim((string) $s);
    }
}

if (!function_exists('_auto_heading_ru_years')) {
    function _auto_heading_ru_years(int $age): string
    {
        $n  = abs($age) % 100;
        $n1 = $n % 10;

        if ($n > 10 && $n < 20) {
            return 'лет';
        }
        if ($n1 > 1 && $n1 < 5) {
            return 'года';
        }
        if ($n1 == 1) {
            return 'год';
        }
        return 'лет';
    }
}

$h1 = '';
$h2 = '';

$qo          = get_queried_object();
$id          = 0;
$post_type   = '';
$taxonomy    = '';
$title_piece = '';

// Для taxonomy-URL определяем связанную CPT-запись по slug.
$linked_post_from_term = null;
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

    $tx = (string) $qo->taxonomy;
    if (isset($tax_to_post_type[$tx])) {
        $linked_post_from_term = get_page_by_path((string) $qo->slug, OBJECT, $tax_to_post_type[$tx]);
    }
}

if ($linked_post_from_term instanceof WP_Post) {
    $id          = (int) $linked_post_from_term->ID;
    $post_type   = (string) $linked_post_from_term->post_type;
    $title_raw   = get_the_title($linked_post_from_term);
    $title_piece = is_string($title_raw) ? _auto_heading_clean(wp_strip_all_tags($title_raw)) : '';
} elseif ($qo instanceof WP_Post) {
    $id          = $qo->ID;
    $post_type   = $qo->post_type;
    $title_raw   = get_the_title($qo);
    $title_piece = is_string($title_raw) ? _auto_heading_clean(wp_strip_all_tags($title_raw)) : '';
} elseif ($qo instanceof WP_Term) {
    $id          = $qo->term_id;
    $taxonomy    = $qo->taxonomy;
    $name_raw    = $qo->name ?? '';
    $title_piece = is_string($name_raw) ? _auto_heading_clean(wp_strip_all_tags($name_raw)) : '';
} else {
    $id          = get_queried_object_id();
    $post_type   = $id ? get_post_type($id) : '';
    $title_raw   = $id ? get_the_title($id) : '';
    $title_piece = is_string($title_raw) ? _auto_heading_clean(wp_strip_all_tags($title_raw)) : '';
}

/**
 * Для моделей используем ACF-поле name как имя, если оно задано.
 */
if ($post_type === 'models' && function_exists('get_field') && $id) {
    $acf_name = get_field('name', $id);
    if (is_string($acf_name) && $acf_name !== '') {
        $title_piece = _auto_heading_clean($acf_name);
    }
}

$context = '';

if ($post_type === 'models') {
    $context = 'models';
} elseif ($post_type === 'metro' || $taxonomy === 'metro' || $taxonomy === 'metro_tax') {
    $context = 'metro';
} elseif ($post_type === 'rajon' || $taxonomy === 'rajon' || $taxonomy === 'rayonu_tax') {
    $context = 'rajon';
} elseif ($post_type === 'uslugi' || $taxonomy === 'uslugi' || $taxonomy === 'uslugi_tax') {
    $context = 'uslugi';
} elseif ($post_type === 'nacionalnost' || $taxonomy === 'nationalnost_tax') {
    $context = 'nationalnost';
}

$h1_from_record = '';
$h2_from_record = '';
if (function_exists('get_field') && $id) {
    $h1_from_record = _auto_heading_clean((string) get_field('h1_atc', $id));
    $h2_from_record = _auto_heading_clean((string) get_field('h2_title', $id));
}

// Для посадочных страниц приоритет всегда у полей связанной записи.
if ($linked_post_from_term instanceof WP_Post) {
    $h1 = $h1_from_record;
    $h2 = $h2_from_record;
    if ($h1 === '' && $title_piece !== '') {
        $h1 = $title_piece;
    }
} elseif ($h1_from_record !== '' || $h2_from_record !== '') {
    $h1 = $h1_from_record;
    $h2 = $h2_from_record;
}

if ($h1 === '' && $context === 'models' && $title_piece !== '') {

    // Берём возраст ТАК ЖЕ, как в шаблоне
    $age_raw = trim((string) (function_exists('get_field') ? get_field('age', $id) : ''));

    // Фолбэк, если age пустое — пробуем vozrast
    if ($age_raw === '') {
        $age_raw = trim((string) (function_exists('get_field') ? get_field('vozrast', $id) : ''));
    }

    // Доп. фолбэк на метаполя, если вдруг ACF нет/не сработал
    if ($age_raw === '' && $id) {
        $meta_age = get_post_meta($id, 'age', true);
        if ($meta_age === '' || $meta_age === null) {
            $meta_age = get_post_meta($id, 'vozrast', true);
        }
        $age_raw = trim((string) $meta_age);
    }

    $age_int = 0;
    if ($age_raw !== '') {
        $age_int = (int) preg_replace('~\D+~', '', $age_raw);
    }

    if ($age_int > 0) {
        $years_word = _auto_heading_ru_years($age_int);
        $h1 = "Проститутка {$title_piece}, {$age_int} {$years_word}, Москва";
    } else {
        $h1 = "Проститутка {$title_piece}, Москва";
    }
} elseif ($h1 === '' && $context === 'metro' && $title_piece !== '') {

    $h1 = "Проститутки у метро {$title_piece}";
    $h2 = "Анкеты проституток у метро {$title_piece}";
} elseif ($h1 === '' && $context === 'rajon' && $title_piece !== '') {

    $h1 = "Проститутки район {$title_piece}";
    $h2 = "Анкеты проституток в районе {$title_piece}";
} elseif ($h1 === '' && $context === 'uslugi' && $title_piece !== '') {

    $h1 = "Проститутки с услугой {$title_piece} в Москве";
    $h2 = "Анкеты проституток с услугой {$title_piece}";
} elseif ($h1 === '' && $context === 'nationalnost' && $title_piece !== '') {

    $nat_gen = function_exists('_seo_inflect_nationality_gen')
        ? _seo_inflect_nationality_gen($title_piece)
        : $title_piece;
    $h1 = "Проститутки {$title_piece} в Москве";
    $h2 = "Анкеты проституток {$nat_gen}";
} else {

    if (function_exists('get_field') && $id) {
        $h1 = (string) get_field('h1_atc', $id);
        $h2 = (string) get_field('h2_title', $id);
    }

    $h1 = _auto_heading_clean($h1);

    if ($h1 === '' && $title_piece !== '') {
        $h1 = $title_piece;
    }
}

if ($h2 === '' && function_exists('get_field') && $id) {
    $h2 = (string) get_field('h2_title', $id);
}

// Принудительная авто-генерация H1/H2 для metro.
if ($context === 'metro' && $title_piece !== '') {
    $h1_template = function_exists('dosugmoskva24_seo_template_get_string')
        ? dosugmoskva24_seo_template_get_string('metro', 'h1', 'Проститутки у метро {station_name}')
        : 'Проститутки у метро {station_name}';
    $h2_template = function_exists('dosugmoskva24_seo_template_get_string')
        ? dosugmoskva24_seo_template_get_string('metro', 'h2', 'Анкеты проституток у метро {station_name}')
        : 'Анкеты проституток у метро {station_name}';
    $h1 = function_exists('dosugmoskva24_seo_template_render')
        ? dosugmoskva24_seo_template_render($h1_template, ['name' => $title_piece, 'station_name' => $title_piece])
        : "Проститутки у метро {$title_piece}";
    $h2 = function_exists('dosugmoskva24_seo_template_render')
        ? dosugmoskva24_seo_template_render($h2_template, ['name' => $title_piece, 'station_name' => $title_piece])
        : "Анкеты проституток у метро {$title_piece}";
}

// Принудительная авто-генерация H1/H2 для rajon.
if ($context === 'rajon' && $title_piece !== '') {
    $h1_template = function_exists('dosugmoskva24_seo_template_get_string')
        ? dosugmoskva24_seo_template_get_string('rajon', 'h1', 'Проститутки район {district_name}')
        : 'Проститутки район {district_name}';
    $h2_template = function_exists('dosugmoskva24_seo_template_get_string')
        ? dosugmoskva24_seo_template_get_string('rajon', 'h2', 'Анкеты проституток в районе {district_name}')
        : 'Анкеты проституток в районе {district_name}';
    $h1 = function_exists('dosugmoskva24_seo_template_render')
        ? dosugmoskva24_seo_template_render($h1_template, ['name' => $title_piece, 'district_name' => $title_piece])
        : "Проститутки район {$title_piece}";
    $h2 = function_exists('dosugmoskva24_seo_template_render')
        ? dosugmoskva24_seo_template_render($h2_template, ['name' => $title_piece, 'district_name' => $title_piece])
        : "Анкеты проституток в районе {$title_piece}";
}

// Принудительная авто-генерация H1/H2 для nationalnost.
if ($context === 'nationalnost' && $title_piece !== '') {
    $nat_gen_force = function_exists('_seo_inflect_nationality_gen')
        ? _seo_inflect_nationality_gen($title_piece)
        : $title_piece;
    $h1_template = function_exists('dosugmoskva24_seo_template_get_string')
        ? dosugmoskva24_seo_template_get_string('nationality', 'h1', 'Проститутки {nationality_name} в Москве')
        : 'Проститутки {nationality_name} в Москве';
    $h2_template = function_exists('dosugmoskva24_seo_template_get_string')
        ? dosugmoskva24_seo_template_get_string('nationality', 'h2', 'Анкеты проституток {nationality_name_gen}')
        : 'Анкеты проституток {nationality_name_gen}';
    $vars = [
        'name' => $title_piece,
        'nationality_name' => $title_piece,
        'nationality_name_gen' => $nat_gen_force,
    ];
    $h1 = function_exists('dosugmoskva24_seo_template_render')
        ? dosugmoskva24_seo_template_render($h1_template, $vars)
        : "Проститутки {$title_piece} в Москве";
    $h2 = function_exists('dosugmoskva24_seo_template_render')
        ? dosugmoskva24_seo_template_render($h2_template, $vars)
        : "Анкеты проституток {$nat_gen_force}";
}

// Принудительная авто-генерация H1/H2 для uslugi (перебивает ACF).
if ($context === 'uslugi' && $title_piece !== '') {
    $h1_template = function_exists('dosugmoskva24_seo_template_get_string')
        ? dosugmoskva24_seo_template_get_string('uslugi', 'h1', 'Проститутки с услугой {service_name} в Москве')
        : 'Проститутки с услугой {service_name} в Москве';
    $h2_template = function_exists('dosugmoskva24_seo_template_get_string')
        ? dosugmoskva24_seo_template_get_string('uslugi', 'h2', 'Анкеты проституток с услугой {service_name}')
        : 'Анкеты проституток с услугой {service_name}';
    $h1 = function_exists('dosugmoskva24_seo_template_render')
        ? dosugmoskva24_seo_template_render($h1_template, ['name' => $title_piece, 'service_name' => $title_piece])
        : "Проститутки с услугой {$title_piece} в Москве";
    $h2 = function_exists('dosugmoskva24_seo_template_render')
        ? dosugmoskva24_seo_template_render($h2_template, ['name' => $title_piece, 'service_name' => $title_piece])
        : "Анкеты проституток с услугой {$title_piece}";
}

$h1 = _auto_heading_clean((string) $h1);
$h2 = _auto_heading_clean((string) $h2);

// По требованию: в H1 не оставляем хвост после двоеточия.
$h1 = trim((string) preg_replace('~\s*:\s*.*$~u', '', $h1));

if ($h1 !== '') {
    set_query_var('auto_h1', $h1);
    $GLOBALS['auto_h1'] = $h1;
}
if ($h2 !== '') {
    set_query_var('auto_h2', $h2);
    $GLOBALS['auto_h2'] = $h2;
}
