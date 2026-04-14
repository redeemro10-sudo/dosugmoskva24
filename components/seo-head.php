<?php

/**
 * Component: SEO Head
 * Подключать в начале <head>, ПЕРЕД wp_head().
 */

if (!defined('ABSPATH')) exit;
if (defined('SEO_HEAD_PRINTED')) return;
define('SEO_HEAD_PRINTED', true);
if (!defined('SEO_SITE_BRAND')) define('SEO_SITE_BRAND', 'dosugmoskva24');

/* ================= helpers ================= */

function _seo_site_brand(): string
{
    $name = trim((string) wp_strip_all_tags(get_bloginfo('name', 'display')));
    if ($name === '') return SEO_SITE_BRAND;

    // Если в настройках осталось старое имя проекта, принудительно используем актуальный бренд.
    if (preg_match('~dosugmoskva24~iu', $name)) return SEO_SITE_BRAND;

    return $name;
}

function _seo_normalize_brand_text(string $s): string
{
    $s = trim($s);
    if ($s === '') return '';

    $s = preg_replace('~dosugmoskva24(?:\.net)?~iu', SEO_SITE_BRAND, $s);
    $s = preg_replace('~dosugmoskva24~iu', SEO_SITE_BRAND, $s);
    $s = preg_replace('~\s+~u', ' ', (string) $s);

    return trim((string) $s);
}

function _seo_normalize_descr_text(string $s): string
{
    $s = trim($s);
    if ($s === '') return '';

    // В description не используем домен/бренд, заменяем на нейтральное "сайте".
    $s = preg_replace('~dosugmoskva24(?:\.net)?~iu', 'сайте', $s);
    $s = preg_replace('~dosugmoskva24~iu', 'сайте', $s);
    $s = preg_replace('~dosugmoskva24~iu', 'сайте', $s);
    $s = preg_replace('~\bсайте\s+сайте\b~iu', 'сайте', $s);
    $s = preg_replace('~\s+~u', ' ', (string) $s);

    return trim((string) $s);
}

function _seo_is_individualki_page(array $ctx): bool
{
    if (function_exists('is_page') && is_page('individualki')) {
        return true;
    }

    if (!empty($ctx['id'])) {
        $slug = (string) get_post_field('post_name', (int) $ctx['id']);
        if ($slug === 'individualki') {
            return true;
        }
    }

    $pagename = trim((string) get_query_var('pagename'));
    return trim($pagename, '/') === 'individualki';
}

function _seo_preserve_individualki_variants(array $ctx): bool
{
    $post_type = (string) ($ctx['post_type'] ?? '');
    if (in_array($post_type, ['metro', 'rajon', 'uslugi', 'nacionalnost'], true)) {
        return true;
    }

    if (is_tax()) {
        $qo = get_queried_object();
        if ($qo instanceof WP_Term) {
            return in_array((string) $qo->taxonomy, ['metro_tax', 'rayonu_tax', 'uslugi_tax', 'nationalnost_tax'], true);
        }
    }

    return false;
}

function _seo_strip_individualki_mentions(string $s): string
{
    if ($s === '') return '';

    $map = [
        'Индивидуалки'  => 'Проститутки',
        'индивидуалки'  => 'проститутки',
        'Индивидуалок'  => 'Проституток',
        'индивидуалок'  => 'проституток',
        'Индивидуалка'  => 'Проститутка',
        'индивидуалка'  => 'проститутка',
        'Индивидуалке'  => 'Проститутке',
        'индивидуалке'  => 'проститутке',
        'Индивидуалкам' => 'Проституткам',
        'индивидуалкам' => 'проституткам',
        'Индивидуалками' => 'Проститутками',
        'индивидуалками' => 'проститутками',
        'Индивидуалках' => 'Проститутках',
        'индивидуалках' => 'проститутках',
    ];

    return strtr($s, $map);
}

function _seo_decode_entities(string $s): string
{
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function _seo_normalize_meta_text(string $s): string
{
    $s = _seo_decode_entities($s);
    $s = str_replace(["\u{00A0}", '&nbsp;', '&#160;'], ' ', $s);
    $s = preg_replace('~\s+~u', ' ', (string) $s);
    return trim((string) $s);
}

function _seo_trim_170(string $s): string
{
    $s = trim(preg_replace('~\s+~u', ' ', wp_strip_all_tags($s)));
    $s = _seo_normalize_meta_text($s);
    if (mb_strlen($s, 'UTF-8') > 170) $s = mb_substr($s, 0, 169, 'UTF-8') . '…';
    return $s;
}

function _seo_strip_model_count_mentions(string $s): string
{
    $s = str_replace(['{count}', '{count_word}'], '', $s);
    $s = preg_replace('~\s*\|\s*\d+\s+(?:анкета|анкеты|анкет)\b~u', '', $s);
    $s = preg_replace('~\s*\|\s*доступно\s+\d+\s+(?:анкета|анкеты|анкет)\b~u', '', $s);
    $s = preg_replace('~,\s*\d+\s+(?:анкета|анкеты|анкет)\s+доступно~u', '', $s);
    $s = preg_replace('~\b\d+\s+проверенных\s+(?:анкета|анкеты|анкет)\b\s*~u', '', $s);
    $s = preg_replace('~\s{2,}~u', ' ', (string) $s);
    $s = preg_replace('~\s+\|~u', ' |', (string) $s);
    $s = preg_replace('~\|\s*\|~u', '|', (string) $s);
    return trim((string) $s, " \t\n\r\0\x0B|,");
}

/** Взять первые 170 символов из HTML ПОСЛЕ первого </h1> */
function _seo_take_after_h1_170(string $html): string
{
    if ($html === '') return '';
    if (preg_match('~</h1>~iu', $html, $m, PREG_OFFSET_CAPTURE)) {
        $pos  = $m[0][1] + strlen($m[0][0]);
        $html = substr($html, $pos);
    }
    return _seo_trim_170($html);
}

function _seo_ru_years($n): string
{
    $n  = abs((int)$n);
    $n1 = $n % 10;
    $n2 = $n % 100;
    if ($n1 == 1 && $n2 != 11) return 'год';
    if ($n1 >= 2 && $n1 <= 4 && ($n2 < 10 || $n2 >= 20)) return 'года';
    return 'лет';
}

function _seo_get_meta_str(int $post_id, string $key): string
{
    if (function_exists('get_field')) {
        $v = get_field($key, $post_id);
        if (is_string($v) && $v !== '') return $v;
    }
    $v = get_post_meta($post_id, $key, true);
    return is_string($v) ? $v : '';
}

/** Найти соответствующий терм по slug в таксономии */
function _seo_find_term_by_slug(string $taxonomy, string $slug)
{
    $slug = sanitize_title($slug);
    if (!$slug) return null;
    $t = get_term_by('slug', $slug, $taxonomy);
    return ($t && !is_wp_error($t)) ? $t : null;
}

function _seo_is_special_price_slug(string $slug): bool
{
    $slug = sanitize_title($slug);
    if ($slug === '') return false;

    return in_array($slug, ['deshevyye-prostitutki', 'elitnyye-prostitutki'], true);
}

function _seo_skip_generated_price_landing_meta(array $ctx, string $taxonomy, $term = null): bool
{
    if (empty($ctx['is_singular']) || $taxonomy !== 'price_tax') {
        return false;
    }

    $slug = (string) ($ctx['slug'] ?? '');
    if (_seo_is_special_price_slug($slug)) {
        return true;
    }

    if ($term instanceof WP_Term && _seo_is_special_price_slug((string) $term->slug)) {
        return true;
    }

    return false;
}

function _seo_resolve_landing_term(array $ctx, string $taxonomy)
{
    $base_tax = get_query_var('base_tax');
    if (is_array($base_tax) && !empty($base_tax['taxonomy']) && (string) $base_tax['taxonomy'] === $taxonomy && !empty($base_tax['terms'])) {
        $term_id = (int) ((array) $base_tax['terms'])[0];
        if ($term_id > 0) {
            $term = get_term($term_id, $taxonomy);
            if ($term instanceof WP_Term && !is_wp_error($term)) {
                return $term;
            }
        }
    }

    $slug = (string) ($ctx['slug'] ?? '');
    if ($slug !== '') {
        $term = _seo_find_term_by_slug($taxonomy, $slug);
        if ($term instanceof WP_Term && !is_wp_error($term)) {
            return $term;
        }
    }

    $obj = $ctx['obj'] ?? null;
    if ($obj instanceof WP_Term && (string) $obj->taxonomy === $taxonomy) {
        return $obj;
    }

    return null;
}

function _seo_resolve_base_tax_landing(array $ctx): array
{
    $base_tax = get_query_var('base_tax');
    $taxonomy = '';
    $term = null;

    if (is_array($base_tax) && !empty($base_tax['taxonomy'])) {
        $taxonomy = (string) $base_tax['taxonomy'];
        $term = _seo_resolve_landing_term($ctx, $taxonomy);
    }

    if (($taxonomy === '' || !$term instanceof WP_Term || is_wp_error($term)) && !empty($_SERVER['REQUEST_URI'])) {
        $request_path = trim((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $segments = $request_path !== '' ? explode('/', $request_path) : [];
        if (count($segments) >= 2) {
            $base = (string) ($segments[0] ?? '');
            $term_slug = (string) ($segments[1] ?? '');
            $base_to_taxonomy = [
                'services' => 'uslugi_tax',
                'rajony' => 'rayonu_tax',
                'metro' => 'metro_tax',
                'price' => 'price_tax',
                'vozrast' => 'vozrast_tax',
                'nationalnost' => 'nationalnost_tax',
                'ves' => 'ves_tax',
                'cvet-volos' => 'cvet-volos_tax',
                'rost' => 'rost_tax',
                'grud' => 'grud_tax',
            ];
            if (isset($base_to_taxonomy[$base])) {
                $taxonomy = $base_to_taxonomy[$base];
                $maybe_term = get_term_by('slug', sanitize_title($term_slug), $taxonomy);
                if ($maybe_term instanceof WP_Term && !is_wp_error($maybe_term)) {
                    $term = $maybe_term;
                }
            }
        }
    }

    if ($taxonomy === '' || !$term instanceof WP_Term || is_wp_error($term)) {
        return [];
    }

    $kind = _seo_landing_kind_by_taxonomy($taxonomy);
    if ($kind === '') {
        return [];
    }

    return [
        'taxonomy' => $taxonomy,
        'kind' => $kind,
        'term' => $term,
        'name' => _seo_decode_entities((string) $term->name),
    ];
}

/** Посчитать количество анкет models, привязанных к терму */
function _seo_count_models_by_term($term, string $taxonomy): int
{
    if (!$term) return 0;

    $tax_query = [[
        'taxonomy' => $taxonomy,
        'field'    => 'term_id',
        'terms'    => [(int) $term->term_id],
        'operator' => 'IN',
    ]];

    if ($taxonomy === 'nationalnost_tax') {
        $tax_query[0]['include_children'] = false;
    }

    $q = new WP_Query([
        'post_type'      => 'models',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => false,
        'tax_query'      => $tax_query,
    ]);

    $n = (int) $q->found_posts;
    wp_reset_postdata();
    return $n;
}

/** Проверка: будет ли модель реально показана в каталоге */
function _seo_is_renderable_model(int $post_id): bool
{
    if ($post_id <= 0) return false;

    $name        = get_post_meta($post_id, 'name', true) ?: get_the_title($post_id);
    $raw_gallery = get_post_meta($post_id, 'photo', true);
    if (empty($name) || empty($raw_gallery)) return false;

    $gallery = [];
    if (is_array($raw_gallery)) {
        if (isset($raw_gallery['ID'])) {
            $gallery = [$raw_gallery];
        } else {
            foreach ($raw_gallery as $item) {
                if (is_array($item) || is_numeric($item)) $gallery[] = $item;
            }
        }
    } elseif (is_numeric($raw_gallery)) {
        $gallery = [(int) $raw_gallery];
    }

    return !empty($gallery);
}

/** Посчитать количество анкет, реально отображаемых на текущей странице каталога */
function _seo_count_models_on_page_by_term($term, string $taxonomy, int $paged = 1, int $per_page = 48): int
{
    if (!$term) return 0;

    $tax_query = [[
        'taxonomy' => $taxonomy,
        'field'    => 'term_id',
        'terms'    => [(int) $term->term_id],
        'operator' => 'IN',
    ]];

    if ($taxonomy === 'nationalnost_tax') {
        $tax_query[0]['include_children'] = false;
    }

    $q = new WP_Query([
        'post_type'      => 'models',
        'post_status'    => 'publish',
        'posts_per_page' => max(1, $per_page),
        'paged'          => max(1, $paged),
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'orderby'        => ['date' => 'DESC', 'ID' => 'DESC'],
        'tax_query'      => $tax_query,
    ]);

    $count = 0;
    foreach ((array) $q->posts as $post_id) {
        if (_seo_is_renderable_model((int) $post_id)) {
            $count++;
        }
    }

    wp_reset_postdata();
    return $count;
}

/** Найти минимальную цену среди моделей, привязанных к терму, и вернуть её как строку */
function _seo_min_price_label_by_term($term, string $taxonomy): string
{
    if (!$term) return '';

    $tax_query = [[
        'taxonomy' => $taxonomy,
        'field'    => 'term_id',
        'terms'    => [(int) $term->term_id],
        'operator' => 'IN',
    ]];

    if ($taxonomy === 'nationalnost_tax') {
        $tax_query[0]['include_children'] = false;
    }

    $q = new WP_Query([
        'post_type'      => 'models',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'tax_query'      => $tax_query,
    ]);

    $min_num   = null;
    $min_label = '';

    foreach ((array) $q->posts as $pid) {
        $label = '';

        if (function_exists('get_field')) {
            $label = (string) get_field('price', $pid);
            if ($label === '') $label = (string) get_field('price_from', $pid);
        }
        if ($label === '') {
            $label = (string) get_post_meta($pid, 'price', true);
            if ($label === '') $label = (string) get_post_meta($pid, 'price_from', true);
        }

        $label = trim($label);
        if ($label === '') continue;

        $num = (int) preg_replace('~\D+~', '', $label);
        if ($num <= 0) continue;

        if ($min_num === null || $num < $min_num) {
            $min_num   = $num;
            $min_label = $label;
        }
    }

    wp_reset_postdata();
    return $min_label !== '' ? _seo_decode_entities($min_label) : '';
}

function _seo_min_price_num_by_term($term, string $taxonomy): int
{
    $label = _seo_min_price_label_by_term($term, $taxonomy);
    if ($label === '') return 0;
    return (int) preg_replace('~\D+~', '', $label);
}

function _seo_format_meta_price(int $price_num): string
{
    if ($price_num <= 0) {
        return '';
    }

    return _seo_normalize_meta_text(number_format_i18n($price_num));
}

function _seo_find_related_term_name($term, string $source_taxonomy, string $target_taxonomy): string
{
    if (!$term) return '';

    $q = new WP_Query([
        'post_type'      => 'models',
        'post_status'    => 'publish',
        'posts_per_page' => 140,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'tax_query'      => [[
            'taxonomy' => $source_taxonomy,
            'field'    => 'term_id',
            'terms'    => [(int) $term->term_id],
            'operator' => 'IN',
        ]],
    ]);

    $stat = [];
    foreach ((array) $q->posts as $pid) {
        $terms = get_the_terms((int) $pid, $target_taxonomy);
        if (empty($terms) || is_wp_error($terms)) continue;
        foreach ($terms as $t) {
            $tid = (int) $t->term_id;
            if (!isset($stat[$tid])) {
                $stat[$tid] = ['name' => (string) $t->name, 'cnt' => 0];
            }
            $stat[$tid]['cnt']++;
        }
    }
    wp_reset_postdata();

    if (empty($stat)) return '';
    uasort($stat, static function ($a, $b): int {
        return ((int) $b['cnt']) <=> ((int) $a['cnt']);
    });

    $top = reset($stat);
    return !empty($top['name']) ? _seo_decode_entities((string) $top['name']) : '';
}

function _seo_landing_kind_by_taxonomy(string $taxonomy): string
{
    $tax_to_kind = [
        'metro_tax' => 'metro',
        'rayonu_tax' => 'rajon',
        'uslugi_tax' => 'uslugi',
        'vozrast_tax' => 'appearance',
        'rost_tax' => 'appearance',
        'grud_tax' => 'appearance',
        'ves_tax' => 'appearance',
        'cvet-volos_tax' => 'appearance',
        'nationalnost_tax' => 'nationality',
        'price_tax' => 'price',
    ];
    return $tax_to_kind[$taxonomy] ?? '';
}

function _seo_random_phrase_vin(int $seed = 0): array
{
    $phrase = _seo_random_phrase_pack($seed);
    return [$phrase['verb'], $phrase['noun_acc']];
}

function _seo_random_phrase_pack(int $seed = 0): array
{
    if (function_exists('dosugmoskva24_seo_pick_phrase_variant')) {
        return dosugmoskva24_seo_pick_phrase_variant($seed);
    }

    $verbs = ['снять', 'заказать', 'вызвать'];
    $noun_forms = [
        ['acc' => 'проститутку', 'gen' => 'проституток', 'nom' => 'проститутки'],
        ['acc' => 'шлюху', 'gen' => 'шлюх', 'nom' => 'шлюхи'],
        ['acc' => 'индивидуалку', 'gen' => 'индивидуалок', 'nom' => 'индивидуалки'],
    ];
    $verb_index = $seed > 0 ? ($seed % count($verbs)) : array_rand($verbs);
    $noun_index = $seed > 0 ? (((int) floor($seed / count($verbs))) % count($noun_forms)) : array_rand($noun_forms);

    return [
        'verb' => $verbs[$verb_index],
        'noun_acc' => $noun_forms[$noun_index]['acc'],
        'noun_gen' => $noun_forms[$noun_index]['gen'],
        'noun_nom' => $noun_forms[$noun_index]['nom'],
    ];
}

function _seo_random_verb(int $seed = 0): string
{
    return _seo_random_phrase_pack($seed)['verb'];
}

function _seo_random_noun_gen(int $seed = 0): string
{
    return _seo_random_phrase_pack($seed)['noun_gen'];
}

/** Родительный падеж ед.ч. для услуги (классический массаж → классического массажа). Грубая эвристика. */
function _seo_inflect_usluga_gen(string $name): string
{
    $name = trim($name);
    if ($name === '') return $name;
    $words = preg_split('~\s+~u', $name);
    foreach ($words as &$w) {
        $low = function_exists('mb_strtolower') ? mb_strtolower($w, 'UTF-8') : strtolower($w);
        if (preg_match('~ия$~u', $low))      { $w = mb_substr($w, 0, -1, 'UTF-8') . 'и'; continue; }
        if (preg_match('~ие$~u', $low))      { $w = mb_substr($w, 0, -1, 'UTF-8') . 'я'; continue; }
        if (preg_match('~ье$~u', $low))      { $w = mb_substr($w, 0, -1, 'UTF-8') . 'я'; continue; }
        if (preg_match('~ая$~u', $low))      { $w = mb_substr($w, 0, -2, 'UTF-8') . 'ой'; continue; }
        if (preg_match('~яя$~u', $low))      { $w = mb_substr($w, 0, -2, 'UTF-8') . 'ей'; continue; }
        if (preg_match('~ый$~u', $low))      { $w = mb_substr($w, 0, -2, 'UTF-8') . 'ого'; continue; }
        if (preg_match('~ий$~u', $low))      { $w = mb_substr($w, 0, -2, 'UTF-8') . 'его'; continue; }
        if (preg_match('~ка$~u', $low))      { $w = mb_substr($w, 0, -1, 'UTF-8') . 'и'; continue; }
        if (preg_match('~а$~u',  $low))      { $w = mb_substr($w, 0, -1, 'UTF-8') . 'ы'; continue; }
        if (preg_match('~я$~u',  $low))      { $w = mb_substr($w, 0, -1, 'UTF-8') . 'и'; continue; }
        if (preg_match('~[бвгдзжклмнпрстфхцчшщ]$~u', $low)) { $w .= 'а'; continue; }
    }
    return implode(' ', $words);
}

/** Винительный падеж ед.ч. для жен. национальности (русская→русскую, армянка→армянку). */
function _seo_inflect_nationality_acc(string $name): string
{
    $name = trim($name);
    if ($name === '') return $name;
    $low = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    if (preg_match('~ские$~u', $low)) return mb_substr($name, 0, -2, 'UTF-8') . 'ую';
    if (preg_match('~ые$~u', $low))   return mb_substr($name, 0, -2, 'UTF-8') . 'ую';
    if (preg_match('~ки$~u', $low))   return mb_substr($name, 0, -1, 'UTF-8') . 'у';
    if (preg_match('~ая$~u', $low)) return mb_substr($name, 0, -2, 'UTF-8') . 'ую';
    if (preg_match('~яя$~u', $low)) return mb_substr($name, 0, -2, 'UTF-8') . 'юю';
    if (preg_match('~а$~u',  $low)) return mb_substr($name, 0, -1, 'UTF-8') . 'у';
    return $name;
}

/** Родительный падеж мн.ч. для жен. национальности (русская→русских, армянка→армянок, узбечка→узбечек). */
function _seo_inflect_nationality_gen(string $name): string
{
    $name = trim($name);
    if ($name === '') return $name;
    $low = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    if (preg_match('~ские$~u', $low)) return mb_substr($name, 0, -2, 'UTF-8') . 'их';
    if (preg_match('~ые$~u', $low))   return mb_substr($name, 0, -2, 'UTF-8') . 'ых';
    if (preg_match('~([чшжщ])ки$~u', $low)) return mb_substr($name, 0, -2, 'UTF-8') . 'ек';
    if (preg_match('~ки$~u', $low)) return mb_substr($name, 0, -2, 'UTF-8') . 'ок';
    if (preg_match('~ая$~u', $low)) return mb_substr($name, 0, -2, 'UTF-8') . 'их';
    if (preg_match('~([чшжщ])ка$~u', $low, $m)) return mb_substr($name, 0, -2, 'UTF-8') . 'ек';
    if (preg_match('~ка$~u', $low)) return mb_substr($name, 0, -2, 'UTF-8') . 'ок';
    if (preg_match('~а$~u',  $low)) return mb_substr($name, 0, -1, 'UTF-8');
    return $name;
}

function _seo_plural_anket(int $n): string
{
    $n100 = abs($n) % 100;
    $n10  = $n100 % 10;
    if ($n100 > 10 && $n100 < 20) return 'анкет';
    if ($n10 === 1) return 'анкета';
    if ($n10 >= 2 && $n10 <= 4) return 'анкеты';
    return 'анкет';
}

function _seo_prepare_landing_extra($term, string $taxonomy, string $kind): array
{
    if (!$term || !($term instanceof WP_Term) || $taxonomy === '' || $kind === '') {
        return [];
    }

    $extra = [
        'term_id' => (int) $term->term_id,
    ];

    if (in_array($kind, ['metro', 'rajon', 'nationality', 'uslugi'], true)) {
        $extra['count'] = _seo_count_models_by_term($term, $taxonomy);
        $paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
        $extra['page_count'] = _seo_count_models_on_page_by_term($term, $taxonomy, $paged, 48);
        $price_num = _seo_min_price_num_by_term($term, $taxonomy);
        $extra['price_txt'] = _seo_format_meta_price($price_num);
    }

    return $extra;
}

function _seo_build_landing_title_by_kind(string $kind, string $cat_name, string $price_txt = '', array $extra = []): string
{
    if ($kind === 'metro') {
        $term_id = (int) ($extra['term_id'] ?? 0);
        $count   = (int) ($extra['count'] ?? 0);
        $phrase  = _seo_random_phrase_pack($term_id);
        $template = function_exists('dosugmoskva24_seo_template_get_string')
            ? dosugmoskva24_seo_template_get_string('metro', 'title', 'Проститутки {station_name} - {verb} {noun_acc} у метро {station_name}')
            : 'Проститутки {station_name} - {verb} {noun_acc} у метро {station_name}';
        $result = function_exists('dosugmoskva24_seo_template_render')
            ? dosugmoskva24_seo_template_render($template, [
                'name' => $cat_name,
                'station_name' => $cat_name,
                'verb' => $phrase['verb'],
                'noun_acc' => $phrase['noun_acc'],
                'noun_gen' => $phrase['noun_gen'],
                'noun_nom' => $phrase['noun_nom'],
                'count' => $count,
                'count_word' => _seo_plural_anket($count),
                'price' => $price_txt,
            ])
            : "Проститутки {$cat_name} - {$phrase['verb']} {$phrase['noun_acc']} у метро {$cat_name}";
        return _seo_strip_model_count_mentions($result);
    }

    if ($kind === 'rajon') {
        $term_id = (int) ($extra['term_id'] ?? 0);
        $count   = (int) ($extra['count'] ?? 0);
        $phrase  = _seo_random_phrase_pack($term_id);
        $template = function_exists('dosugmoskva24_seo_template_get_string')
            ? dosugmoskva24_seo_template_get_string('rajon', 'title', 'Проститутки {district_name} - {verb} {noun_acc} в районе {district_name} 24/7')
            : 'Проститутки {district_name} - {verb} {noun_acc} в районе {district_name} 24/7';
        $result = function_exists('dosugmoskva24_seo_template_render')
            ? dosugmoskva24_seo_template_render($template, [
                'name' => $cat_name,
                'district_name' => $cat_name,
                'verb' => $phrase['verb'],
                'noun_acc' => $phrase['noun_acc'],
                'noun_gen' => $phrase['noun_gen'],
                'noun_nom' => $phrase['noun_nom'],
                'count' => $count,
                'count_word' => _seo_plural_anket($count),
                'price' => $price_txt,
            ])
            : "Проститутки {$cat_name} - {$phrase['verb']} {$phrase['noun_acc']} в районе {$cat_name} 24/7";
        return _seo_strip_model_count_mentions($result);
    }

    if ($kind === 'uslugi') {
        $term_id = (int) ($extra['term_id'] ?? 0);
        $phrase = _seo_random_phrase_pack($term_id);
        $usl_gen = _seo_inflect_usluga_gen($cat_name);
        $template = function_exists('dosugmoskva24_seo_template_get_string')
            ? dosugmoskva24_seo_template_get_string('uslugi', 'title', 'Проститутки для {service_name_gen} в Москве, {verb} {noun_acc} для услуги {service_name} в Москве 24/7')
            : 'Проститутки для {service_name_gen} в Москве, {verb} {noun_acc} для услуги {service_name} в Москве 24/7';
        return function_exists('dosugmoskva24_seo_template_render')
            ? dosugmoskva24_seo_template_render($template, [
                'name' => $cat_name,
                'service_name' => $cat_name,
                'service_name_gen' => $usl_gen,
                'verb' => $phrase['verb'],
                'noun_acc' => $phrase['noun_acc'],
                'noun_gen' => $phrase['noun_gen'],
                'noun_nom' => $phrase['noun_nom'],
                'count' => (int) ($extra['count'] ?? 0),
                'count_word' => _seo_plural_anket((int) ($extra['count'] ?? 0)),
                'price' => $price_txt,
            ])
            : "Проститутки для {$usl_gen} в Москве, {$phrase['verb']} {$phrase['noun_acc']} для услуги {$cat_name} в Москве 24/7";
    }

    if ($kind === 'appearance') {
        return "{$cat_name} индивидуалки Москва — девушки с внешностью {$cat_name} в Москве";
    }

    if ($kind === 'nationality') {
        $term_id = (int) ($extra['term_id'] ?? 0);
        $phrase = _seo_random_phrase_pack($term_id);
        $nat_acc = _seo_inflect_nationality_acc($cat_name);
        $template = function_exists('dosugmoskva24_seo_template_get_string')
            ? dosugmoskva24_seo_template_get_string('nationality', 'title', 'Проститутки {nationality_name} Москвы - {verb} {noun_acc} {nationality_name_acc} от {price} рублей')
            : 'Проститутки {nationality_name} Москвы - {verb} {noun_acc} {nationality_name_acc} от {price} рублей';
        return function_exists('dosugmoskva24_seo_template_render')
            ? dosugmoskva24_seo_template_render($template, [
                'name' => $cat_name,
                'nationality_name' => $cat_name,
                'nationality_name_acc' => $nat_acc,
                'nationality_name_gen' => _seo_inflect_nationality_gen($cat_name),
                'verb' => $phrase['verb'],
                'noun_acc' => $phrase['noun_acc'],
                'noun_gen' => $phrase['noun_gen'],
                'noun_nom' => $phrase['noun_nom'],
                'count' => (int) ($extra['count'] ?? 0),
                'count_word' => _seo_plural_anket((int) ($extra['count'] ?? 0)),
                'price' => $price_txt,
            ])
            : "Проститутки {$cat_name} Москвы - {$phrase['verb']} {$phrase['noun_acc']} {$nat_acc} от {$price_txt} рублей";
    }

    if ($kind === 'price') {
        if ($price_txt !== '') {
            return "Проститутки по цене {$cat_name} в Москве — анкеты с фото (от {$price_txt} руб.)";
        }
        return "Проститутки по цене {$cat_name} в Москве — анкеты с фото и фильтрами";
    }

    return '';
}

function _seo_build_landing_descr_by_kind(string $kind, string $cat_name, array $extra = []): string
{
    $count_for_description = (int) ($extra['page_count'] ?? ($extra['count'] ?? 0));

    if ($kind === 'metro') {
        $term_id   = (int) ($extra['term_id'] ?? 0);
        $price_txt = (string) ($extra['price_txt'] ?? '');
        $phrase = _seo_random_phrase_pack($term_id);
        $template = function_exists('dosugmoskva24_seo_template_get_string')
            ? dosugmoskva24_seo_template_get_string('metro', 'description', 'Проститутки у станции метро {station_name}, {verb} проститутку от {price} рублей с выездом или приемом у себя 24/7')
            : 'Проститутки у станции метро {station_name}, {verb} проститутку от {price} рублей с выездом или приемом у себя 24/7';
        return function_exists('dosugmoskva24_seo_template_render')
            ? dosugmoskva24_seo_template_render($template, [
                'name' => $cat_name,
                'station_name' => $cat_name,
                'verb' => $phrase['verb'],
                'noun_acc' => $phrase['noun_acc'],
                'noun_gen' => $phrase['noun_gen'],
                'noun_nom' => $phrase['noun_nom'],
                'count' => $count_for_description,
                'count_word' => _seo_plural_anket($count_for_description),
                'price' => $price_txt,
            ])
            : "Проститутки у станции метро {$cat_name}, {$phrase['verb']} проститутку от {$price_txt} рублей с выездом или приемом у себя 24/7";
    }

    if ($kind === 'rajon') {
        $term_id   = (int) ($extra['term_id'] ?? 0);
        $price_txt = (string) ($extra['price_txt'] ?? '');
        $phrase = _seo_random_phrase_pack($term_id);
        $template = function_exists('dosugmoskva24_seo_template_get_string')
            ? dosugmoskva24_seo_template_get_string('rajon', 'description', 'Проститутки в районе {district_name} | {verb} от {price} рублей за час, проверенные анкеты {noun_gen} в районе {district_name}')
            : 'Проститутки в районе {district_name} | {verb} от {price} рублей за час, проверенные анкеты {noun_gen} в районе {district_name}';
        return function_exists('dosugmoskva24_seo_template_render')
            ? dosugmoskva24_seo_template_render($template, [
                'name' => $cat_name,
                'district_name' => $cat_name,
                'verb' => $phrase['verb'],
                'noun_acc' => $phrase['noun_acc'],
                'noun_gen' => $phrase['noun_gen'],
                'noun_nom' => $phrase['noun_nom'],
                'count' => $count_for_description,
                'count_word' => _seo_plural_anket($count_for_description),
                'price' => $price_txt,
            ])
            : "Проститутки в районе {$cat_name} | {$phrase['verb']} от {$price_txt} рублей за час, проверенные анкеты {$phrase['noun_gen']} в районе {$cat_name}";
    }

    if ($kind === 'uslugi') {
        $term_id   = (int) ($extra['term_id'] ?? 0);
        $count     = $count_for_description;
        $price_txt = (string) ($extra['price_txt'] ?? '');
        $phrase = _seo_random_phrase_pack($term_id);
        $usl_gen = _seo_inflect_usluga_gen($cat_name);
        $template = function_exists('dosugmoskva24_seo_template_get_string')
            ? dosugmoskva24_seo_template_get_string('uslugi', 'description', '{service_name} в Москве с выездом и приемом у себя, лучшие {noun_nom} для {service_name_gen} в Москве от {price} рублей за час')
            : '{service_name} в Москве с выездом и приемом у себя, лучшие {noun_nom} для {service_name_gen} в Москве от {price} рублей за час';
        $result = function_exists('dosugmoskva24_seo_template_render')
            ? dosugmoskva24_seo_template_render($template, [
                'name' => $cat_name,
                'service_name' => $cat_name,
                'service_name_gen' => $usl_gen,
                'verb' => $phrase['verb'],
                'noun_acc' => $phrase['noun_acc'],
                'noun_gen' => $phrase['noun_gen'],
                'noun_nom' => $phrase['noun_nom'],
                'count' => $count,
                'count_word' => _seo_plural_anket($count),
                'price' => $price_txt,
            ])
            : "{$cat_name} в Москве с выездом и приемом у себя, лучшие {$phrase['noun_nom']} для {$usl_gen} в Москве от {$price_txt} рублей за час";
        return _seo_strip_model_count_mentions($result);
    }

    if ($kind === 'appearance') {
        return "Актуальный каталог проституток в Москве: анкеты с фото, ценами и фильтрами по внешности «{$cat_name}».";
    }

    if ($kind === 'nationality') {
        $term_id = (int) ($extra['term_id'] ?? 0);
        $count   = $count_for_description;
        $phrase = _seo_random_phrase_pack($term_id);
        $nat_gen = _seo_inflect_nationality_gen($cat_name);
        $template = function_exists('dosugmoskva24_seo_template_get_string')
            ? dosugmoskva24_seo_template_get_string('nationality', 'description', '{verb} {nationality_name_acc} в Москве | анкеты проституток {nationality_name_gen} с проверенными фото | выезд прием 24/7')
            : '{verb} {nationality_name_acc} в Москве | анкеты проституток {nationality_name_gen} с проверенными фото | выезд прием 24/7';
        $result = function_exists('dosugmoskva24_seo_template_render')
            ? dosugmoskva24_seo_template_render($template, [
                'name' => $cat_name,
                'nationality_name' => $cat_name,
                'nationality_name_acc' => _seo_inflect_nationality_acc($cat_name),
                'nationality_name_gen' => $nat_gen,
                'verb' => $phrase['verb'],
                'noun_acc' => $phrase['noun_acc'],
                'noun_gen' => $phrase['noun_gen'],
                'noun_nom' => $phrase['noun_nom'],
                'count' => $count,
                'count_word' => _seo_plural_anket($count),
                'price' => (string) ($extra['price_txt'] ?? ''),
            ])
            : "{$phrase['verb']} {$nat_gen} в Москве | анкеты проституток {$nat_gen} с проверенными фото | выезд прием 24/7";
        return _seo_strip_model_count_mentions($result);
    }

    if ($kind === 'price') {
        return "Актуальный каталог проституток в Москве: анкеты с фото, ценами и фильтрами по цене «{$cat_name}».";
    }

    return "Актуальный каталог проституток в Москве: анкеты с фото, ценами и фильтрами по параметрам каталога.";
}

/** Список станций метро для анкеты models */
function _seo_get_model_metro_list(int $post_id): string
{
    $taxes = ['metro_tax', 'metro'];
    foreach ($taxes as $tax) {
        $terms = get_the_terms($post_id, $tax);
        if (!empty($terms) && !is_wp_error($terms)) {
            // Берем первый элемент массива терминов
            $first_term = reset($terms);
            
            if (!empty($first_term->name)) {
                // Возвращаем очищенное имя сразу же
                return _seo_decode_entities($first_term->name);
            }
        }
    }
    return '';
}

/** Текущий контекст */
function _seo_ctx(): array
{
    $id  = (int) get_queried_object_id();
    $obj = get_queried_object();
    $paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));

    return [
        'id'          => $id,
        'obj'         => $obj,
        'post_type'   => $id ? get_post_type($id) : '',
        'is_home'     => (is_front_page() || is_home()),
        'is_singular' => is_singular(),
        'site'        => _seo_site_brand(),
        'slug'        => (is_singular() && is_object($obj) && !empty($obj->post_name)) ? $obj->post_name : '',
        'paged'       => $paged > 0 ? $paged : 1,
    ];
}

/** Каноникал */
function _seo_canonical(array $ctx): string
{
    $paged = max(1, (int) $ctx['paged']);
    $base  = ($ctx['is_singular'] && $ctx['id']) ? get_permalink($ctx['id']) : home_url(add_query_arg([]));

    if ($ctx['is_home'] && $paged === 1) return home_url('/');
    if ($paged > 1) return get_pagenum_link($paged);

    return $base;
}

function _seo_append_page_suffix(string $text, int $paged): string
{
    if ($paged <= 1) return $text;
    $text = trim($text);
    if ($text === '') return '';
    return _seo_trim_170($text . ' — страница ' . $paged);
}

/* ================= генерация заголовков/descr ================= */

/**
 * TITLE (по ТЗ):
 * - uslugi:       {Service_Name} Москва — заказать интим услуги в Москве (цены от {Price})
 * - appearance:   {Appearance_Type} индивидуалки Москва — девушки с внешностью {Appearance_Type} в Москве
 * - nationality:  Проститутки национальности {Nationality} в Москве — анкеты с фото и ценами
 * - rajon:        Индивидуалки {District} — интим услуги в районе {District} (фото и цены)
 * - metro:        Проститутки метро {Station} — интим услуги рядом с метро (цены от {Price})
 * - models/прочие: прежняя логика.
 */
function _seo_build_title(array $ctx): string
{
    $site = $ctx['site'];
    $pt   = $ctx['post_type'];
    $slug = $ctx['slug'];

    // Карта CPT -> taxonomy для посадочных страниц по термам.
    $tx_map = [
        'metro'   => 'metro_tax',
        'rajon'   => 'rayonu_tax',
        'uslugi'  => 'uslugi_tax',
        'vozrast' => 'vozrast_tax',
        'rost'    => 'rost_tax',
        'price'   => 'price_tax',
        'tsena'   => 'price_tax',
        'nacionalnost' => 'nationalnost_tax',
        'grud'    => 'grud_tax',
        'ves'     => 'ves_tax',
        'tsvet-volos' => 'cvet-volos_tax',
    ];
    // Иерархические "страничные" CPT
    if ($ctx['is_singular'] && $pt && isset($tx_map[$pt]) && $ctx['id']) {
        $tax = $tx_map[$pt];
        $term = _seo_resolve_landing_term($ctx, $tax);
        if (!_seo_skip_generated_price_landing_meta($ctx, $tax, $term)) {
            $cat_name = $term ? $term->name : get_the_title($ctx['id']);
            $cat_name = _seo_decode_entities($cat_name);

            $price_num = _seo_min_price_num_by_term($term, $tax);
            $price_txt = _seo_format_meta_price($price_num);
            $kind = _seo_landing_kind_by_taxonomy($tax);
            if ($kind !== '') {
                $extra = _seo_prepare_landing_extra($term, $tax, $kind);
                $built = _seo_build_landing_title_by_kind($kind, $cat_name, $price_txt, $extra);
                if ($built !== '') return $built;
            }
        }
    }

    // Архивы таксономий (services/slug, metro/slug и т.д.)
    if (is_tax()) {
        $qo = get_queried_object();
        if ($qo instanceof WP_Term && !empty($qo->taxonomy)) {
            $tax = (string) $qo->taxonomy;
            $kind = _seo_landing_kind_by_taxonomy($tax);
            if ($kind !== '') {
                $cat_name = _seo_decode_entities((string) $qo->name);
                $price_num = _seo_min_price_num_by_term($qo, $tax);
                $price_txt = _seo_format_meta_price($price_num);
                $extra = _seo_prepare_landing_extra($qo, $tax, $kind);
                $built = _seo_build_landing_title_by_kind($kind, $cat_name, $price_txt, $extra);
                if ($built !== '') return $built;
            }
        }
    }

    $base_tax_landing = _seo_resolve_base_tax_landing($ctx);
    if ($ctx['is_singular'] && !empty($base_tax_landing)) {
        $tax = (string) $base_tax_landing['taxonomy'];
        $kind = (string) $base_tax_landing['kind'];
        $term = $base_tax_landing['term'];
        if (!_seo_skip_generated_price_landing_meta($ctx, $tax, $term)) {
            $cat_name = (string) $base_tax_landing['name'];
            $price_num = _seo_min_price_num_by_term($term, $tax);
            $price_txt = _seo_format_meta_price($price_num);
            $extra = _seo_prepare_landing_extra($term, $tax, $kind);
            $built = _seo_build_landing_title_by_kind($kind, $cat_name, $price_txt, $extra);
            if ($built !== '') return $built;
        }
    }

    // Страница анкеты models
    if ($ctx['is_singular'] && $pt === 'models' && $ctx['id']) {
        if (function_exists('get_field')) {
            $name = get_field('name', $ctx['id']) ?: get_the_title($ctx['id']);
        } else {
            $name = get_the_title($ctx['id']);
        }
        $name = _seo_decode_entities($name);

        $metro_list = _seo_get_model_metro_list($ctx['id']);

        $age    = function_exists('get_field') ? trim((string) get_field('age', $ctx['id'])) : '';
        $height = function_exists('get_field') ? trim((string) (get_field('height', $ctx['id']) ?: get_field('rost', $ctx['id']))) : '';
        $bust   = function_exists('get_field') ? trim((string) get_field('bust', $ctx['id'])) : '';

        $first = $metro_list !== ''
            ? "{$name} - проститутка у метро {$metro_list}."
            : "{$name} - проститутка в Москве.";

        $parts = [];
        if ($age !== '')    $parts[] = "Возраст - {$age}";
        if ($height !== '') $parts[] = "рост - {$height}";
        if ($bust !== '')   $parts[] = "размер груди - {$bust}";

        $details = $parts ? ' ' . implode(', ', $parts) : '';

        return $first . $details;
    }

    // Прочие singular: ручной title или заголовок записи
    if ($ctx['is_singular'] && $ctx['id']) {
        $manual = _seo_normalize_brand_text(_seo_get_meta_str($ctx['id'], 'title'));
        if ($manual !== '') return _seo_decode_entities($manual);
        $t = _seo_decode_entities(_seo_normalize_brand_text(get_the_title($ctx['id'])));
        return $t;
    }

    if (is_search()) {
        $q = trim((string) get_search_query());
        return $q !== '' ? "Поиск «{$q}» — {$site}" : "Поиск по сайту — {$site}";
    }

    if (is_404()) {
        return "Страница не найдена — {$site}";
    }

    if (is_post_type_archive()) {
        $pt_obj = get_queried_object();
        $label = (is_object($pt_obj) && !empty($pt_obj->labels->name))
            ? (string) $pt_obj->labels->name
            : 'Каталог';
        return _seo_decode_entities("{$label} — {$site}");
    }

    if (is_tax() || is_category() || is_tag()) {
        $term_title = trim((string) single_term_title('', false));
        if ($term_title !== '') {
            return _seo_decode_entities("{$term_title} — {$site}");
        }
    }

    if (is_author()) {
        $author = trim((string) get_the_author_meta('display_name', (int) get_query_var('author')));
        if ($author !== '') return _seo_decode_entities("{$author} — {$site}");
    }

    if (is_date()) {
        return "Архив публикаций — {$site}";
    }

    if (is_archive()) {
        return "Каталог — {$site}";
    }

    return $site !== '' ? $site : SEO_SITE_BRAND;
}

/**
 * DESCRIPTION:
 * Для каталоговых страниц используем единый шаблон:
 * "Актуальный каталог проституток ...: анкеты с фото, ценами и фильтрами по ..."
 * с подстановкой текста в зависимости от типа фильтра (район/метро/услуга/внешность/национальность/цена).
 */
function _seo_build_descr(array $ctx): string
{
    $pt = $ctx['post_type'];
    $tx_map = [
        'metro'   => 'metro_tax',
        'rajon'   => 'rayonu_tax',
        'uslugi'  => 'uslugi_tax',
        'vozrast' => 'vozrast_tax',
        'rost'    => 'rost_tax',
        'price'   => 'price_tax',
        'tsena'   => 'price_tax',
        'nacionalnost' => 'nationalnost_tax',
        'grud'    => 'grud_tax',
        'ves'     => 'ves_tax',
        'tsvet-volos' => 'cvet-volos_tax',
    ];
    if ($ctx['is_singular'] && $pt && isset($tx_map[$pt]) && $ctx['id']) {
        // Ручное поле descr всегда в приоритете.
        $d = _seo_normalize_descr_text(_seo_get_meta_str($ctx['id'], 'descr'));
        if ($d !== '') return _seo_trim_170($d);

        $tax = $tx_map[$pt];
        $term = _seo_resolve_landing_term($ctx, $tax);
        if (!_seo_skip_generated_price_landing_meta($ctx, $tax, $term)) {
            $cat_name = $term ? _seo_decode_entities((string) $term->name) : _seo_decode_entities(get_the_title($ctx['id']));
            $kind = _seo_landing_kind_by_taxonomy($tax);
            if ($kind !== '') {
                $extra = _seo_prepare_landing_extra($term, $tax, $kind);
                return _seo_trim_170(_seo_build_landing_descr_by_kind($kind, $cat_name, $extra));
            }
        }
    }

    if (is_tax()) {
        $qo = get_queried_object();
        if ($qo instanceof WP_Term && !empty($qo->taxonomy)) {
            $kind = _seo_landing_kind_by_taxonomy((string) $qo->taxonomy);
            if ($kind !== '') {
                $cat_name = _seo_decode_entities((string) $qo->name);
                $extra = _seo_prepare_landing_extra($qo, (string) $qo->taxonomy, $kind);
                return _seo_trim_170(_seo_build_landing_descr_by_kind($kind, $cat_name, $extra));
            }
        }
    }

    $base_tax_landing = _seo_resolve_base_tax_landing($ctx);
    if ($ctx['is_singular'] && !empty($base_tax_landing)) {
        $tax = (string) $base_tax_landing['taxonomy'];
        $kind = (string) $base_tax_landing['kind'];
        $term = $base_tax_landing['term'];
        if (!_seo_skip_generated_price_landing_meta($ctx, $tax, $term)) {
            $cat_name = (string) $base_tax_landing['name'];
            $extra = _seo_prepare_landing_extra($term, $tax, $kind);
            return _seo_trim_170(_seo_build_landing_descr_by_kind($kind, $cat_name, $extra));
        }
    }

    // models: ACF description
    if ($ctx['is_singular'] && $pt === 'models' && $ctx['id']) {
        $raw = function_exists('get_field') ? (string) get_field('description', $ctx['id']) : '';
        if ($raw !== '') return _seo_trim_170($raw);

        $raw = get_post_field('post_excerpt', $ctx['id']) ?: get_post_field('post_content', $ctx['id']);
        if ($raw) return _seo_trim_170($raw);
    }

    // Прочие singular: descr / excerpt / content
    if ($ctx['is_singular'] && $ctx['id']) {
        $d = _seo_normalize_descr_text(_seo_get_meta_str($ctx['id'], 'descr'));
        if ($d !== '') return _seo_trim_170($d);

        $raw = get_post_field('post_excerpt', $ctx['id']) ?: get_post_field('post_content', $ctx['id']);
        if ($raw) return _seo_trim_170($raw);
    }

    if ($ctx['is_home']) {
        return 'Эскорт-модели с фото, видео и ценами. Фильтры по возрасту, району, метро, национальности. Обновляем ежедневно.';
    }

    if (is_search()) {
        return _seo_trim_170("Результаты поиска по сайту. Используйте фильтры, чтобы быстрее найти подходящие анкеты.");
    }

    if (is_404()) {
        return _seo_trim_170("Страница не найдена. Перейдите в каталог и воспользуйтесь фильтрами каталога.");
    }

    if (is_post_type_archive() || is_archive() || is_tax() || is_category() || is_tag()) {
        return _seo_trim_170("Актуальный каталог анкет с фото, ценами и фильтрами по параметрам каталога.");
    }

    return _seo_trim_170("Каталог анкет с фото, описаниями и удобными фильтрами для быстрого подбора.");
}

/** OG картинка */
function _seo_og_image(): array
{
    if (is_singular() && has_post_thumbnail()) {
        $id  = get_post_thumbnail_id();
        $src = wp_get_attachment_image_src($id, 'large');
        return [
            'url'    => $src[0] ?? '',
            'width'  => $src[1] ?? '',
            'height' => $src[2] ?? '',
            'alt'    => trim(wp_strip_all_tags(get_post_meta($id, '_wp_attachment_image_alt', true))),
        ];
    }
    return ['url' => home_url('/apple-touch-icon.png')];
}

/* ================= build & print ================= */

$ctx       = _seo_ctx();
$title     = _seo_build_title($ctx);
$descr     = _seo_build_descr($ctx);
$title     = _seo_normalize_meta_text($title);
$descr     = _seo_normalize_descr_text($descr);
$descr     = _seo_normalize_meta_text($descr);

if (!_seo_is_individualki_page($ctx) && !_seo_preserve_individualki_variants($ctx)) {
    $title = _seo_strip_individualki_mentions($title);
    $descr = _seo_strip_individualki_mentions($descr);
}

$title     = _seo_append_page_suffix($title, $ctx['paged']);
$descr     = _seo_append_page_suffix($descr, $ctx['paged']);

if (trim($title) === '') {
    $title = _seo_site_brand();
}
if (trim($descr) === '') {
    $descr = _seo_trim_170('Актуальные анкеты с фото, ценами и фильтрами каталога.');
    $descr = _seo_append_page_suffix($descr, $ctx['paged']);
}
$canonical = _seo_canonical($ctx);
if ($ctx['paged'] > 1) {
    $canonical = untrailingslashit($canonical); // для пагинации убираем закрывающий слэш
}
$og        = _seo_og_image();
$og_type   = (is_singular() ? 'article' : 'website');

set_query_var('seo_title', $title);
set_query_var('seo_descr', $descr);
$GLOBALS['seo_title'] = $title;
$GLOBALS['seo_descr'] = $descr;

remove_action('wp_head', 'rel_canonical');

echo '<title>' . esc_html($title) . "</title>\n";
if ($descr !== '') {
    echo '<meta name="description" content="' . esc_attr($descr) . "\" />\n";
}
echo '<link rel="canonical" href="' . esc_url($canonical) . "\" />\n";
echo '<meta name="robots" content="index,follow,max-snippet:-1,max-video-preview:-1" />' . "\n";

echo '<meta property="og:type" content="' . esc_attr($og_type) . "\" />\n";
echo '<meta property="og:title" content="' . esc_attr($title) . "\" />\n";
if ($descr !== '') {
    echo '<meta property="og:description" content="' . esc_attr($descr) . "\" />\n";
}
echo '<meta property="og:url" content="' . esc_url($canonical) . "\" />\n";
if (!empty($og['url'])) {
    echo '<meta property="og:image" content="' . esc_url($og['url']) . "\" />\n";
    if (!empty($og['alt']))    echo '<meta property="og:image:alt" content="' . esc_attr($og['alt']) . "\" />\n";
    if (!empty($og['width']))  echo '<meta property="og:image:width" content="' . (int) $og['width'] . "\" />\n";
    if (!empty($og['height'])) echo '<meta property="og:image:height" content="' . (int) $og['height'] . "\" />\n";
}
