<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('dosugmoskva24_auto_text_clean')) {
    function dosugmoskva24_auto_text_clean(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('~\s+~u', ' ', wp_strip_all_tags($value));
        return trim((string) $value);
    }
}

if (!function_exists('dosugmoskva24_auto_text_plural')) {
    function dosugmoskva24_auto_text_plural(int $n, string $one, string $few, string $many): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return $many;
        }
        if ($n1 > 1 && $n1 < 5) {
            return $few;
        }
        if ($n1 === 1) {
            return $one;
        }
        return $many;
    }
}

if (!function_exists('dosugmoskva24_auto_text_join')) {
    function dosugmoskva24_auto_text_join(array $paragraphs, string $format = 'html'): string
    {
        $chunks = [];
        foreach ($paragraphs as $paragraph) {
            $text = dosugmoskva24_auto_text_clean((string) $paragraph);
            if ($text !== '') {
                $chunks[] = $text;
            }
        }

        if (empty($chunks)) {
            return '';
        }

        if ($format === 'plain') {
            return implode("\n\n", $chunks);
        }

        return '<p>' . implode('</p><p>', array_map('esc_html', $chunks)) . '</p>';
    }
}

if (!function_exists('dosugmoskva24_auto_text_count_models')) {
    function dosugmoskva24_auto_text_count_models(array $base_tax = []): int
    {
        if (empty($base_tax['taxonomy']) || empty($base_tax['terms'])) {
            $count_obj = wp_count_posts('models');
            return isset($count_obj->publish) ? (int) $count_obj->publish : 0;
        }

        $q = new WP_Query([
            'post_type'      => 'models',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
            'tax_query'      => [[
                'taxonomy' => (string) $base_tax['taxonomy'],
                'field'    => 'term_id',
                'terms'    => array_map('intval', (array) $base_tax['terms']),
                'operator' => 'IN',
            ]],
        ]);

        $count = (int) $q->found_posts;
        wp_reset_postdata();
        return $count;
    }
}

if (!function_exists('dosugmoskva24_auto_text_count_models_by_meta')) {
    function dosugmoskva24_auto_text_count_models_by_meta(array $meta_keys = []): int
    {
        $meta_keys = array_values(array_filter(array_map('sanitize_key', $meta_keys)));
        if (empty($meta_keys)) {
            return 0;
        }

        $or = ['relation' => 'OR'];
        foreach ($meta_keys as $meta_key) {
            $or[] = [
                'key' => $meta_key,
                'value' => 0,
                'type' => 'NUMERIC',
                'compare' => '>',
            ];
        }

        $q = new WP_Query([
            'post_type'      => 'models',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
            'meta_query'     => $or,
        ]);

        $count = (int) $q->found_posts;
        wp_reset_postdata();
        return $count;
    }
}

if (!function_exists('dosugmoskva24_auto_text_term_name')) {
    function dosugmoskva24_auto_text_term_name(array $base_tax = [], int $post_id = 0): string
    {
        if (!empty($base_tax['taxonomy']) && !empty($base_tax['terms'])) {
            $term = get_term((int) ((array) $base_tax['terms'])[0], (string) $base_tax['taxonomy']);
            if ($term && !is_wp_error($term) && !empty($term->name)) {
                return dosugmoskva24_auto_text_clean((string) $term->name);
            }
        }

        if ($post_id > 0) {
            $title = get_the_title($post_id);
            if (is_string($title) && $title !== '') {
                return dosugmoskva24_auto_text_clean($title);
            }
        }

        return '';
    }
}

if (!function_exists('dosugmoskva24_auto_text_context')) {
    function dosugmoskva24_auto_text_context(array $args = []): string
    {
        $post_type = (string) ($args['post_type'] ?? '');
        $taxonomy = (string) ($args['taxonomy'] ?? '');
        $page_slug = (string) ($args['page_slug'] ?? '');

        if ($taxonomy === '' && !empty($args['base_tax']['taxonomy'])) {
            $taxonomy = (string) $args['base_tax']['taxonomy'];
        }

        $tax_map = [
            'metro_tax' => 'metro',
            'rayonu_tax' => 'rajon',
            'uslugi_tax' => 'uslugi',
            'price_tax' => 'price',
            'vozrast_tax' => 'vozrast',
            'nationalnost_tax' => 'nationalnost',
            'rost_tax' => 'rost',
            'ves_tax' => 'ves',
            'grud_tax' => 'grud',
            'cvet-volos_tax' => 'cvet_volos',
        ];
        if (isset($tax_map[$taxonomy])) {
            return $tax_map[$taxonomy];
        }

        $pt_map = [
            'metro' => 'metro',
            'rajon' => 'rajon',
            'uslugi' => 'uslugi',
            'tsena' => 'price',
            'price' => 'price',
            'vozrast' => 'vozrast',
            'nacionalnost' => 'nationalnost',
            'nationalnost' => 'nationalnost',
            'rost' => 'rost',
            'ves' => 'ves',
            'grud' => 'grud',
            'tsvet-volos' => 'cvet_volos',
        ];
        if (isset($pt_map[$post_type])) {
            return $pt_map[$post_type];
        }

        $slug_map = [
            'services' => 'uslugi',
            'rajony' => 'rajon',
            'metro' => 'metro',
            'price' => 'price',
            'tsena' => 'price',
            'vozrast' => 'vozrast',
            'nationalnost' => 'nationalnost',
            'nacionalnost' => 'nationalnost',
            'rost' => 'rost',
            'ves' => 'ves',
            'grud' => 'grud',
            'cvet-volos' => 'cvet_volos',
        ];
        if (isset($slug_map[$page_slug])) {
            return $slug_map[$page_slug];
        }

        return 'general';
    }
}

if (!function_exists('dosugmoskva24_generate_landing_auto_text')) {
    function dosugmoskva24_generate_landing_auto_text(array $args = []): array
    {
        $city = dosugmoskva24_auto_text_clean((string) ($args['city'] ?? 'Москва'));
        if ($city === '') {
            $city = 'Москва';
        }

        $post_id = (int) ($args['post_id'] ?? 0);
        $base_tax = (array) ($args['base_tax'] ?? []);
        $format = (string) ($args['format'] ?? 'html');
        $context = dosugmoskva24_auto_text_context($args);
        $term_name = dosugmoskva24_auto_text_term_name($base_tax, $post_id);
        if (isset($args['models_count'])) {
            $models_count = (int) $args['models_count'];
        } elseif ($context === 'incall') {
            $models_count = dosugmoskva24_auto_text_count_models_by_meta(['price', 'price_2_hours', 'price_night']);
        } else {
            $models_count = dosugmoskva24_auto_text_count_models($base_tax);
        }

        $count_label = $models_count > 0
            ? $models_count . ' ' . dosugmoskva24_auto_text_plural($models_count, 'анкета', 'анкеты', 'анкет')
            : '';

        $intro = [];
        $under_h2 = [];
        $content = [];
        $seo = [];

       

   

        return [
            'p_after_h1' => dosugmoskva24_auto_text_join($intro, $format),
            'p_under_h2' => dosugmoskva24_auto_text_join($under_h2, $format),
            'content' => dosugmoskva24_auto_text_join($content, $format),
            'text_block' => dosugmoskva24_auto_text_join($seo, $format),
            'models_count' => $models_count,
            'context' => $context,
            'term_name' => $term_name,
        ];
    }
}

if (!function_exists('dosugmoskva24_generate_term_parent_auto_text')) {
    function dosugmoskva24_generate_term_parent_auto_text(array $args = []): array
    {
        $args['format'] = 'plain';
        $city = dosugmoskva24_auto_text_clean((string) ($args['city'] ?? 'Москва'));
        if ($city === '') {
            $city = 'Москва';
        }

        $context = dosugmoskva24_auto_text_context($args);
        $items_count = (int) ($args['items_count'] ?? 0);
        $items_label = $items_count > 0
            ? $items_count . ' ' . dosugmoskva24_auto_text_plural($items_count, 'раздел', 'раздела', 'разделов')
            : '';

        $h1 = '';
        $p = '';
        $seo = '';

        if ($context === 'uslugi') {
            $h1 = "Услуги проституток {$city}";
            $p = dosugmoskva24_auto_text_join([
                "Ниже собраны основные категории услуг, по которым можно быстро перейти к нужным анкетам.",
                $items_label !== '' ? "В каталоге доступно {$items_label} с актуальными подборками." : '',
            ], 'plain');
            $seo = dosugmoskva24_auto_text_join([
                "Раздел по услугам помогает быстро сузить поиск и открыть только релевантные анкеты.",
                "Используйте фильтры внутри категории, чтобы подобрать вариант по цене, району и параметрам.",
            ], 'plain');
        } elseif ($context === 'metro') {
            $h1 = "Проститутки у метро {$city}";
            $p = dosugmoskva24_auto_text_join([
                "Выберите станцию метро, чтобы открыть подборку анкет в нужной локации.",
                $items_label !== '' ? "Сейчас доступно {$items_label}." : '',
            ], 'plain');
            $seo = dosugmoskva24_auto_text_join([
                "Каталог метро ускоряет поиск анкет рядом с удобной станцией и экономит время на выбор.",
            ], 'plain');
        } elseif ($context === 'rajon') {
            $h1 = "Проститутки по районам {$city}";
            $p = dosugmoskva24_auto_text_join([
                "Раздел позволяет открыть анкеты по конкретному району и сразу сравнить предложения.",
                $items_label !== '' ? "Доступно {$items_label} для быстрого перехода." : '',
            ], 'plain');
            $seo = dosugmoskva24_auto_text_join([
                "Страница районов упрощает поиск по локации и помогает выбрать анкеты рядом с нужной точкой города.",
            ], 'plain');
        } elseif ($context === 'price') {
            $h1 = "Проститутки по цене в {$city}";
            $p = dosugmoskva24_auto_text_join([
                "Выберите категорию стоимости, чтобы перейти к подходящему бюджету.",
                $items_label !== '' ? "В каталоге доступно {$items_label}." : '',
            ], 'plain');
            $seo = dosugmoskva24_auto_text_join([
                "Раздел по цене помогает быстро сравнить категории и открыть релевантные анкеты без лишнего поиска.",
            ], 'plain');
        } else {
            $h1 = '';
            $p = dosugmoskva24_auto_text_join([
                "Выберите нужный раздел, чтобы перейти к подборке анкет по выбранному параметру.",
                $items_label !== '' ? "Сейчас доступно {$items_label}." : '',
            ], 'plain');
            $seo = dosugmoskva24_auto_text_join([
                "Этот каталог создан для быстрого перехода к релевантным подборам по ключевым параметрам.",
            ], 'plain');
        }

        return [
            'h1' => $h1,
            'p' => $p,
            'seo' => $seo,
            'context' => $context,
        ];
    }
}

if (!function_exists('dosugmoskva24_auto_text_get_model_ids_by_terms')) {
    function dosugmoskva24_auto_text_get_model_ids_by_terms(string $taxonomy, array $term_ids, int $limit = 260): array
    {
        $term_ids = array_values(array_unique(array_filter(array_map('intval', $term_ids))));
        if ($taxonomy === '' || empty($term_ids)) {
            return [];
        }

        $ids = get_posts([
            'post_type'           => 'models',
            'post_status'         => 'publish',
            'posts_per_page'      => $limit > 0 ? $limit : 260,
            'fields'              => 'ids',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'tax_query'           => [[
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $term_ids,
                'operator' => 'IN',
            ]],
        ]);

        return array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
    }
}

if (!function_exists('dosugmoskva24_auto_text_get_term_rows_by_models')) {
    function dosugmoskva24_auto_text_get_term_rows_by_models(array $model_ids, string $taxonomy, array $exclude_term_ids = [], int $limit = 8): array
    {
        $model_ids = array_values(array_unique(array_filter(array_map('intval', $model_ids))));
        if (empty($model_ids) || $taxonomy === '') {
            return [];
        }

        $exclude_map = array_fill_keys(array_map('intval', $exclude_term_ids), true);
        $terms_raw = wp_get_object_terms($model_ids, $taxonomy, ['fields' => 'all_with_object_id']);
        if (is_wp_error($terms_raw) || empty($terms_raw)) {
            return [];
        }

        $bucket = [];
        foreach ($terms_raw as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }
            $term_id = (int) $term->term_id;
            if (isset($exclude_map[$term_id])) {
                continue;
            }
            if (!isset($bucket[$term_id])) {
                $bucket[$term_id] = [
                    'term' => $term,
                    'count' => 0,
                ];
            }
            $bucket[$term_id]['count']++;
        }

        if (empty($bucket)) {
            return [];
        }

        uasort($bucket, static function (array $a, array $b): int {
            if ($a['count'] === $b['count']) {
                return strnatcasecmp((string) $a['term']->name, (string) $b['term']->name);
            }
            return ($a['count'] > $b['count']) ? -1 : 1;
        });

        if ($limit > 0) {
            $bucket = array_slice($bucket, 0, $limit, true);
        }

        return array_values($bucket);
    }
}

if (!function_exists('dosugmoskva24_auto_text_terms_from_rows')) {
    function dosugmoskva24_auto_text_terms_from_rows(array $rows): array
    {
        $terms = [];
        foreach ($rows as $row) {
            $term = $row['term'] ?? null;
            if ($term instanceof WP_Term) {
                $terms[] = $term;
            }
        }
        return $terms;
    }
}

if (!function_exists('dosugmoskva24_auto_text_render_term_links')) {
    function dosugmoskva24_auto_text_render_term_links(array $terms, string $prefix = '', string $separator = ', '): string
    {
        $chunks = [];
        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }
            $url = get_term_link($term);
            if (is_wp_error($url) || !is_string($url) || $url === '') {
                continue;
            }
            $name = dosugmoskva24_auto_text_clean((string) $term->name);
            if ($name === '') {
                continue;
            }
            $label = $prefix !== '' ? trim($prefix . ' ' . $name) : $name;
            $chunks[] = '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        return implode($separator, $chunks);
    }
}

if (!function_exists('dosugmoskva24_auto_text_render_term_list')) {
    function dosugmoskva24_auto_text_render_term_list(array $terms, string $prefix = ''): string
    {
        $items = [];
        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }
            $url = get_term_link($term);
            if (is_wp_error($url) || !is_string($url) || $url === '') {
                continue;
            }
            $name = dosugmoskva24_auto_text_clean((string) $term->name);
            if ($name === '') {
                continue;
            }
            $label = $prefix !== '' ? trim($prefix . ' ' . $name) : $name;
            $items[] = '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
        }
        return implode('', $items);
    }
}

if (!function_exists('dosugmoskva24_auto_text_find_current_term')) {
    function dosugmoskva24_auto_text_find_current_term(array $args = []): ?WP_Term
    {
        $base_tax = (array) ($args['base_tax'] ?? []);
        if (!empty($base_tax['taxonomy']) && !empty($base_tax['terms'])) {
            $term = get_term((int) ((array) $base_tax['terms'])[0], (string) $base_tax['taxonomy']);
            if ($term instanceof WP_Term && !is_wp_error($term)) {
                return $term;
            }
        }

        $taxonomy = (string) ($args['taxonomy'] ?? '');
        $post_id = (int) ($args['post_id'] ?? 0);

        if ($taxonomy === '') {
            $context = dosugmoskva24_auto_text_context($args);
            $context_map = [
                'metro' => 'metro_tax',
                'rajon' => 'rayonu_tax',
                'uslugi' => 'uslugi_tax',
                'price' => 'price_tax',
                'vozrast' => 'vozrast_tax',
                'nationalnost' => 'nationalnost_tax',
                'rost' => 'rost_tax',
                'ves' => 'ves_tax',
                'grud' => 'grud_tax',
                'cvet_volos' => 'cvet-volos_tax',
            ];
            $taxonomy = $context_map[$context] ?? '';
        }

        if ($taxonomy !== '' && $post_id > 0) {
            $slug = (string) get_post_field('post_name', $post_id);
            if ($slug !== '') {
                $term = get_term_by('slug', $slug, $taxonomy);
                if ($term instanceof WP_Term && !is_wp_error($term)) {
                    return $term;
                }
            }
        }

        return null;
    }
}

if (!function_exists('dosugmoskva24_generate_landing_links_block')) {
    function dosugmoskva24_generate_landing_links_block(array $args = []): string
    {
        $context = dosugmoskva24_auto_text_context($args);
        if (!in_array($context, ['metro', 'rajon'], true)) {
            return '';
        }

        $current_term = dosugmoskva24_auto_text_find_current_term($args);
        if (!$current_term instanceof WP_Term) {
            return '';
        }

        $term_id = (int) $current_term->term_id;
        if ($term_id <= 0) {
            return '';
        }

        $cache_key = 'kz_auto_links_' . $context . '_' . $term_id;
        $cached = get_transient($cache_key);
        if (is_string($cached)) {
            return $cached;
        }

        $current_name = dosugmoskva24_auto_text_clean((string) $current_term->name);
        if ($current_name === '') {
            return '';
        }

        $html = '';

        if ($context === 'metro') {
            $model_ids_at_metro = dosugmoskva24_auto_text_get_model_ids_by_terms('metro_tax', [$term_id], 260);
            $district_rows = dosugmoskva24_auto_text_get_term_rows_by_models($model_ids_at_metro, 'rayonu_tax', [], 3);
            $district_terms = dosugmoskva24_auto_text_terms_from_rows($district_rows);
            $district_links = dosugmoskva24_auto_text_render_term_links($district_terms);

            $district_ids = [];
            foreach ($district_terms as $district_term) {
                $district_ids[] = (int) $district_term->term_id;
            }

            $near_metro_rows = [];
            if (!empty($district_ids)) {
                $model_ids_by_district = dosugmoskva24_auto_text_get_model_ids_by_terms('rayonu_tax', $district_ids, 320);
                $near_metro_rows = dosugmoskva24_auto_text_get_term_rows_by_models(
                    $model_ids_by_district,
                    'metro_tax',
                    [$term_id],
                    6
                );
            }

            if (empty($near_metro_rows)) {
                $fallback_metro = get_terms([
                    'taxonomy'   => 'metro_tax',
                    'hide_empty' => true,
                    'exclude'    => [$term_id],
                    'number'     => 6,
                ]);
                if (!is_wp_error($fallback_metro) && !empty($fallback_metro)) {
                    foreach ($fallback_metro as $term) {
                        if ($term instanceof WP_Term) {
                            $near_metro_rows[] = ['term' => $term, 'count' => 0];
                        }
                    }
                }
            }

            $near_metro_terms = dosugmoskva24_auto_text_terms_from_rows($near_metro_rows);
            $near_metro_list = dosugmoskva24_auto_text_render_term_list($near_metro_terms, 'м.');

            if ($district_links === '' && $near_metro_list === '') {
                set_transient($cache_key, '', 6 * HOUR_IN_SECONDS);
                return '';
            }

            $title = 'Если нет подходящих вариантов на ' . $current_name;
            $title = function_exists('mb_strtoupper') ? mb_strtoupper($title, 'UTF-8') : strtoupper($title);

            $parts = [];
            $parts[] = '<h2 class="text-2xl md:text-4xl font-extrabold leading-tight tracking-tight border-l-2 border-neutral-300 pl-3">' . esc_html($title) . '</h2>';

            if ($district_links !== '') {
                $parts[] = '<p class="mt-5">Станция территориально относится к</p>';
                $parts[] = '<p class="mt-2 font-semibold leading-8">' . $district_links . '</p>';
            }

            if ($near_metro_list !== '') {
                $parts[] = '<p class="mt-8">Рекомендуем расширить поиск и посмотреть анкеты на соседних станциях этой ветки:</p>';
                $parts[] = '<ul class="mt-3 list-disc space-y-4 pl-6 font-semibold">' . $near_metro_list . '</ul>';
            }

            $parts[] = '<p class="mt-8">Это позволит найти подходящий вариант в радиусе 15-20 минут езды, если на станции ' . esc_html($current_name) . ' все заняты.</p>';
            $html = implode("\n", $parts);
        } elseif ($context === 'rajon') {
            $model_ids_at_district = dosugmoskva24_auto_text_get_model_ids_by_terms('rayonu_tax', [$term_id], 320);
            $metro_rows = dosugmoskva24_auto_text_get_term_rows_by_models($model_ids_at_district, 'metro_tax', [], 6);

            if (empty($metro_rows)) {
                $fallback_metro = get_terms([
                    'taxonomy'   => 'metro_tax',
                    'hide_empty' => true,
                    'number'     => 6,
                ]);
                if (!is_wp_error($fallback_metro) && !empty($fallback_metro)) {
                    foreach ($fallback_metro as $term) {
                        if ($term instanceof WP_Term) {
                            $metro_rows[] = ['term' => $term, 'count' => 0];
                        }
                    }
                }
            }

            $metro_terms = dosugmoskva24_auto_text_terms_from_rows($metro_rows);
            $metro_inline = dosugmoskva24_auto_text_render_term_links($metro_terms, 'м.');
            if ($metro_inline === '') {
                set_transient($cache_key, '', 6 * HOUR_IN_SECONDS);
                return '';
            }

            $title = function_exists('mb_strtoupper') ? mb_strtoupper('Где искать: метро и улицы', 'UTF-8') : strtoupper('Где искать: метро и улицы');

            $parts = [];
            $parts[] = '<h2 class="text-2xl md:text-4xl font-extrabold leading-tight tracking-tight border-l-2 border-neutral-300 pl-3">' . esc_html($title) . '</h2>';
            $parts[] = '<p class="mt-5">Административный район ' . esc_html($current_name) . ' обслуживается несколькими транспортными узлами.</p>';
            $parts[] = '<p class="mt-2">Для удобства поиска рекомендуем смотреть анкеты не только по всему району, но и точечно у ближайших станций метро:</p>';
            $parts[] = '<p class="mt-4 font-semibold leading-8">' . $metro_inline . '</p>';
            $parts[] = '<p class="mt-8">Это сэкономит время на дорогу и даст больше доступных вариантов в соседних локациях.</p>';
            $html = implode("\n", $parts);
        }

        set_transient($cache_key, $html, 6 * HOUR_IN_SECONDS);
        return $html;
    }
}

if (!function_exists('dosugmoskva24_generate_model_auto_about')) {
    function dosugmoskva24_generate_model_auto_about(array $args = []): string
    {
        $post_id = (int) ($args['post_id'] ?? 0);
        if ($post_id <= 0) {
            return '';
        }

        $city = dosugmoskva24_auto_text_clean((string) ($args['city'] ?? 'Москва'));
        if ($city === '') {
            $city = 'Москва';
        }

        $name = dosugmoskva24_auto_text_clean((string) ($args['name'] ?? get_the_title($post_id)));
        $age = dosugmoskva24_auto_text_clean((string) ($args['age'] ?? ''));
        $height = dosugmoskva24_auto_text_clean((string) ($args['height'] ?? ''));
        $weight = dosugmoskva24_auto_text_clean((string) ($args['weight'] ?? ''));
        $bust = dosugmoskva24_auto_text_clean((string) ($args['bust'] ?? ''));

        $districts = $args['districts'] ?? get_the_terms($post_id, 'rayonu_tax');
        if (!is_array($districts) || is_wp_error($districts)) {
            $districts = [];
        }

        $metro = $args['metro'] ?? get_the_terms($post_id, 'metro_tax');
        if (!is_array($metro) || is_wp_error($metro)) {
            $metro = [];
        }

        $details = [];
        if ($age !== '') {
            $details[] = 'возраст ' . esc_html($age);
        }
        if ($height !== '') {
            $details[] = 'рост ' . esc_html($height) . ' см';
        }
        if ($weight !== '') {
            $details[] = 'вес ' . esc_html($weight) . ' кг';
        }
        if ($bust !== '') {
            $details[] = 'грудь ' . esc_html($bust);
        }

        $parts = [];
        $lead = $name !== ''
            ? 'Анкета ' . esc_html($name) . ' в ' . esc_html($city) . '.'
            : 'Анкета модели в ' . esc_html($city) . '.';
        if (!empty($details)) {
            $lead .= ' Основные параметры: ' . implode(', ', $details) . '.';
        }
        $parts[] = $lead;

        $district_links = dosugmoskva24_auto_text_render_term_links($districts);
        $metro_links = dosugmoskva24_auto_text_render_term_links($metro, 'м.');

        if ($district_links !== '' || $metro_links !== '') {
            $location_chunks = [];
            if ($district_links !== '') {
                $location_chunks[] = 'район: ' . $district_links;
            }
            if ($metro_links !== '') {
                $location_chunks[] = 'метро: ' . $metro_links;
            }
            $parts[] = 'Локация: ' . implode('; ', $location_chunks) . '.';
        }

        $reco = [];
        if ($district_links !== '') {
            $reco[] = 'Если этот вариант не подходит, смотрите анкеты в районах ' . $district_links;
        }
        if ($metro_links !== '') {
            $reco[] = 'или у станций ' . $metro_links;
        }
        if (!empty($reco)) {
            $parts[] = implode(' ', $reco) . '.';
        }

        return implode("\n\n", $parts);
    }
}
