<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('dosugmoskva24_seo_template_defaults')) {
    function dosugmoskva24_seo_template_defaults(): array
    {
        return [
            'verbs_text' => implode("\n", [
                'снять',
                'заказать',
                'вызвать',
            ]),
            'noun_variants_text' => implode("\n", [
                'проститутку|проституток|проститутки',
                'шлюху|шлюх|шлюхи',
                'индивидуалку|индивидуалок|индивидуалки',
            ]),
            'templates' => [
                'metro' => [
                    'h1' => 'Проститутки у метро {station_name}',
                    'h2' => 'Анкеты проституток у метро {station_name}',
                    'title' => 'Проститутки {station_name} - {verb} {noun_acc} у метро {station_name} | {count} {count_word}',
                    'description' => 'Проститутки у станции метро {station_name}, {verb} проститутку от {price} рублей с выездом или приемом у себя 24/7',
                ],
                'rajon' => [
                    'h1' => 'Проститутки район {district_name}',
                    'h2' => 'Анкеты проституток в районе {district_name}',
                    'title' => 'Проститутки {district_name} - {verb} {noun_acc} в районе {district_name} 24/7 | доступно {count} {count_word}',
                    'description' => 'Проститутки в районе {district_name} | {verb} от {price} рублей за час, проверенные анкеты {noun_gen} в районе {district_name}',
                ],
                'nationality' => [
                    'h1' => 'Проститутки {nationality_name} в Москве',
                    'h2' => 'Анкеты проституток {nationality_name_gen}',
                    'title' => 'Проститутки {nationality_name} Москвы - {verb} {noun_acc} {nationality_name_acc} от {price} рублей',
                    'description' => '{verb} {nationality_name_gen} в Москве, {count} {count_word} доступно | анкеты проституток {nationality_name_gen} с проверенными фото | выезд прием 24/7',
                ],
                'uslugi' => [
                    'h1' => 'Проститутки с услугой {service_name} в Москве',
                    'h2' => 'Анкеты проституток с услугой {service_name}',
                    'title' => 'Проститутки для {service_name_gen} в Москве, {verb} {noun_acc} для услуги {service_name} в Москве 24/7',
                    'description' => '{service_name} в Москве {count} проверенных {count_word} с выездом и приемом у себя, лучшие {noun_nom} для {service_name_gen} в Москве от {price} рублей за час',
                ],
            ],
        ];
    }
}

if (!function_exists('dosugmoskva24_seo_template_context_map')) {
    function dosugmoskva24_seo_template_context_map(): array
    {
        return [
            'nationalnost' => 'nationality',
            'nationality' => 'nationality',
            'metro' => 'metro',
            'rajon' => 'rajon',
            'uslugi' => 'uslugi',
        ];
    }
}

if (!function_exists('dosugmoskva24_seo_template_normalize_context')) {
    function dosugmoskva24_seo_template_normalize_context(string $context): string
    {
        $context = trim($context);
        $map = dosugmoskva24_seo_template_context_map();
        return $map[$context] ?? $context;
    }
}

if (!function_exists('dosugmoskva24_seo_template_parse_lines')) {
    function dosugmoskva24_seo_template_parse_lines(string $text): array
    {
        $lines = preg_split('~\R+~u', $text) ?: [];
        $result = [];
        foreach ($lines as $line) {
            $line = trim(wp_strip_all_tags((string) $line));
            if ($line !== '') {
                $result[] = $line;
            }
        }
        return $result;
    }
}

if (!function_exists('dosugmoskva24_seo_template_parse_noun_variants')) {
    function dosugmoskva24_seo_template_parse_noun_variants(string $text): array
    {
        $rows = dosugmoskva24_seo_template_parse_lines($text);
        $variants = [];

        foreach ($rows as $row) {
            $parts = array_map('trim', explode('|', $row));
            if (count($parts) < 3) {
                continue;
            }

            [$acc, $gen, $nom] = array_slice($parts, 0, 3);
            if ($acc === '' || $gen === '' || $nom === '') {
                continue;
            }

            $variants[] = [
                'acc' => $acc,
                'gen' => $gen,
                'nom' => $nom,
            ];
        }

        return $variants;
    }
}

if (!function_exists('dosugmoskva24_seo_template_settings')) {
    function dosugmoskva24_seo_template_settings(): array
    {
        $defaults = dosugmoskva24_seo_template_defaults();
        $saved = get_option('dosugmoskva24_seo_template_settings', []);
        $saved = is_array($saved) ? $saved : [];

        $settings = $defaults;
        if (isset($saved['verbs_text']) && is_string($saved['verbs_text'])) {
            $settings['verbs_text'] = $saved['verbs_text'];
        }
        if (isset($saved['noun_variants_text']) && is_string($saved['noun_variants_text'])) {
            $settings['noun_variants_text'] = $saved['noun_variants_text'];
        }

        $settings['templates'] = $defaults['templates'];
        if (!empty($saved['templates']) && is_array($saved['templates'])) {
            foreach ($defaults['templates'] as $context => $fields) {
                if (empty($saved['templates'][$context]) || !is_array($saved['templates'][$context])) {
                    continue;
                }
                foreach ($fields as $field => $value) {
                    if (isset($saved['templates'][$context][$field]) && is_string($saved['templates'][$context][$field])) {
                        $settings['templates'][$context][$field] = $saved['templates'][$context][$field];
                    }
                }
            }
        }

        $verbs = dosugmoskva24_seo_template_parse_lines((string) $settings['verbs_text']);
        if (empty($verbs)) {
            $verbs = dosugmoskva24_seo_template_parse_lines((string) $defaults['verbs_text']);
        }

        $noun_variants = dosugmoskva24_seo_template_parse_noun_variants((string) $settings['noun_variants_text']);
        if (empty($noun_variants)) {
            $noun_variants = dosugmoskva24_seo_template_parse_noun_variants((string) $defaults['noun_variants_text']);
        }

        $settings['verbs'] = $verbs;
        $settings['noun_variants'] = $noun_variants;

        return $settings;
    }
}

if (!function_exists('dosugmoskva24_seo_template_get_string')) {
    function dosugmoskva24_seo_template_get_string(string $context, string $field, string $fallback = ''): string
    {
        $context = dosugmoskva24_seo_template_normalize_context($context);
        $settings = dosugmoskva24_seo_template_settings();
        $value = (string) ($settings['templates'][$context][$field] ?? '');
        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('dosugmoskva24_seo_template_render')) {
    function dosugmoskva24_seo_template_render(string $template, array $vars = []): string
    {
        $replace = [];
        foreach ($vars as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replace['{' . $key . '}'] = trim((string) $value);
            }
        }
        return strtr($template, $replace);
    }
}

if (!function_exists('dosugmoskva24_seo_pick_phrase_variant')) {
    function dosugmoskva24_seo_pick_phrase_variant(int $seed = 0): array
    {
        $settings = dosugmoskva24_seo_template_settings();
        $verbs = $settings['verbs'] ?: ['снять', 'заказать', 'вызвать'];
        $noun_variants = $settings['noun_variants'] ?: [
            ['acc' => 'проститутку', 'gen' => 'проституток', 'nom' => 'проститутки'],
        ];

        $verb_count = count($verbs);
        $noun_count = count($noun_variants);

        if ($seed > 0) {
            $verb_index = $seed % max(1, $verb_count);
            $noun_index = ((int) floor($seed / max(1, $verb_count))) % max(1, $noun_count);
        } else {
            $verb_index = array_rand($verbs);
            $noun_index = array_rand($noun_variants);
        }

        $noun = $noun_variants[$noun_index];

        return [
            'verb' => (string) $verbs[$verb_index],
            'noun_acc' => (string) ($noun['acc'] ?? ''),
            'noun_gen' => (string) ($noun['gen'] ?? ''),
            'noun_nom' => (string) ($noun['nom'] ?? ''),
        ];
    }
}

if (!function_exists('dosugmoskva24_seo_template_sanitize')) {
    function dosugmoskva24_seo_template_sanitize($input): array
    {
        $defaults = dosugmoskva24_seo_template_defaults();
        $input = is_array($input) ? $input : [];

        $output = [
            'verbs_text' => sanitize_textarea_field((string) ($input['verbs_text'] ?? $defaults['verbs_text'])),
            'noun_variants_text' => sanitize_textarea_field((string) ($input['noun_variants_text'] ?? $defaults['noun_variants_text'])),
            'templates' => [],
        ];

        foreach ($defaults['templates'] as $context => $fields) {
            $output['templates'][$context] = [];
            foreach ($fields as $field => $default_value) {
                $output['templates'][$context][$field] = sanitize_textarea_field((string) ($input['templates'][$context][$field] ?? $default_value));
            }
        }

        return $output;
    }
}

if (!function_exists('dosugmoskva24_seo_template_token_help')) {
    function dosugmoskva24_seo_template_token_help(string $context): string
    {
        $map = [
            'metro' => '{station_name}, {name}, {verb}, {noun_acc}, {noun_gen}, {noun_nom}, {count}, {count_word}, {price}',
            'rajon' => '{district_name}, {name}, {verb}, {noun_acc}, {noun_gen}, {noun_nom}, {count}, {count_word}, {price}',
            'nationality' => '{nationality_name}, {name}, {nationality_name_acc}, {nationality_name_gen}, {verb}, {noun_acc}, {noun_gen}, {noun_nom}, {count}, {count_word}, {price}',
            'uslugi' => '{service_name}, {name}, {service_name_gen}, {verb}, {noun_acc}, {noun_gen}, {noun_nom}, {count}, {count_word}, {price}',
        ];

        return $map[$context] ?? '';
    }
}

if (!function_exists('dosugmoskva24_render_seo_template_admin_page')) {
    function dosugmoskva24_render_seo_template_admin_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = dosugmoskva24_seo_template_settings();
        $contexts = [
            'metro' => 'Метро',
            'rajon' => 'Районы',
            'nationality' => 'Национальности',
            'uslugi' => 'Услуги',
        ];
        ?>
        <div class="wrap">
            <h1>SEO шаблоны каталога</h1>
            <p>Здесь редактируются шаблоны H1, H2, title и description для каталожных страниц без правок PHP.</p>

            <form method="post" action="options.php">
                <?php settings_fields('dosugmoskva24_seo_template_settings_group'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="dosugmoskva24_verbs_text">Глаголы</label></th>
                            <td>
                                <textarea
                                    id="dosugmoskva24_verbs_text"
                                    name="dosugmoskva24_seo_template_settings[verbs_text]"
                                    rows="5"
                                    class="large-text code"><?php echo esc_textarea((string) $settings['verbs_text']); ?></textarea>
                                <p class="description">По одному значению в строке. Пример: снять, заказать, вызвать.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="dosugmoskva24_noun_variants_text">Существительные</label></th>
                            <td>
                                <textarea
                                    id="dosugmoskva24_noun_variants_text"
                                    name="dosugmoskva24_seo_template_settings[noun_variants_text]"
                                    rows="6"
                                    class="large-text code"><?php echo esc_textarea((string) $settings['noun_variants_text']); ?></textarea>
                                <p class="description">Одна строка = один вариант в формате: винительный ед.|родительный мн.|именительный мн.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php foreach ($contexts as $context => $label) : ?>
                    <hr>
                    <h2><?php echo esc_html($label); ?></h2>
                    <p>Доступные плейсхолдеры: <code><?php echo esc_html(dosugmoskva24_seo_template_token_help($context)); ?></code></p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <?php foreach (['h1' => 'H1', 'h2' => 'H2', 'title' => 'Title', 'description' => 'Description'] as $field => $field_label) : ?>
                                <tr>
                                    <th scope="row">
                                        <label for="dosugmoskva24_<?php echo esc_attr($context . '_' . $field); ?>"><?php echo esc_html($field_label); ?></label>
                                    </th>
                                    <td>
                                        <textarea
                                            id="dosugmoskva24_<?php echo esc_attr($context . '_' . $field); ?>"
                                            name="dosugmoskva24_seo_template_settings[templates][<?php echo esc_attr($context); ?>][<?php echo esc_attr($field); ?>]"
                                            rows="3"
                                            class="large-text code"><?php echo esc_textarea((string) ($settings['templates'][$context][$field] ?? '')); ?></textarea>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>

                <?php submit_button('Сохранить шаблоны'); ?>
            </form>
        </div>
        <?php
    }
}

add_action('admin_init', static function (): void {
    register_setting(
        'dosugmoskva24_seo_template_settings_group',
        'dosugmoskva24_seo_template_settings',
        [
            'type' => 'array',
            'sanitize_callback' => 'dosugmoskva24_seo_template_sanitize',
            'default' => dosugmoskva24_seo_template_defaults(),
        ]
    );
});

add_action('admin_menu', static function (): void {
    add_theme_page(
        'SEO шаблоны',
        'SEO шаблоны',
        'manage_options',
        'dosugmoskva24-seo-templates',
        'dosugmoskva24_render_seo_template_admin_page'
    );
});
