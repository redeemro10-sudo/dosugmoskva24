<?php
// Получаем данные. Приоритет у query_var (так быстрее всего работает наш цикл)
$model = get_query_var('model', []);

// Если вдруг вызвали не из нашего цикла (фолбэк на $args)
if (empty($model) && isset($args['model'])) {
    $model = $args['model'];
}

// Если данных нет вообще — выходим
if (empty($model)) {
    // Опционально: можно вывести пустую заглушку, но лучше просто выйти
    // echo '<li class="list-none"><p>No model data found.</p></li>';
    return;
}

/** БАЗА */
$post_id     = (int)($model['ID'] ?? $model['id'] ?? get_the_ID());
$name        = trim((string)($model['name'] ?? get_the_title($post_id)));
$profile_url = $model['uri'] ?? get_permalink($post_id);

/* -------------------- ОПТИМИЗАЦИЯ: НОВАЯ МОДЕЛЬ -------------------- */
if (isset($model['is_new'])) {
    $is_new = (bool)$model['is_new'];
} else {
    static $counter = 0; // Статическая переменная сохраняет значение между вызовами файла
    $is_new = ($counter < 60);
    $counter++;
}

/** ПАРАМЕТРЫ */
$age    = $model['age'] ?? '';
$height = $model['height'] ?? '';
$weight = $model['weight'] ?? '';
$bust   = $model['bust'] ?? '';

$district = $model['district'] ?? '';
$metro = $model['metro'] ?? '';
if (empty($metro)) {
    $metro_terms = get_the_terms($post_id, 'metro_tax');
    if ($metro_terms && !is_wp_error($metro_terms)) {
        $metro = wp_list_pluck($metro_terms, 'name');
    }
}

/** Услуги */
$services = $model['services'] ?? [];
if (empty($services)) {
    // Используем get_the_terms (кэшируется), а не wp_get_post_terms
    $service_terms = get_the_terms($post_id, 'uslugi_tax');
    if ($service_terms && !is_wp_error($service_terms)) {
        $services = wp_list_pluck($service_terms, 'name');
    }
}

/** Цены */
$currency      = 'RUB';
$to_int        = static fn($v) => (int)preg_replace('~\D+~', '', (string)$v);
$price_incall_1h  = $to_int($model['price'] ?? '');
$price_outcall_1h = $to_int($model['price_outcall'] ?? '');
$price_incall_2h  = $to_int($model['price_2_hours'] ?? ($price_incall_1h ? $price_incall_1h * 2 : 0));
$price_outcall_2h = $to_int($model['price_outcall_2_hours'] ?? ($price_outcall_1h ? $price_outcall_1h * 2 : 0));
$price_incall_night  = $to_int($model['price_night'] ?? ($price_incall_1h ? $price_incall_1h * 8 : 0));
$price_outcall_night = $to_int($model['price_outcall_night'] ?? ($price_outcall_1h ? $price_outcall_1h * 8 : 0));

/* -------------------- ОПТИМИЗАЦИЯ ФОТО -------------------- */
$gallery = $model['modelGalleryThumbnail'] ?? [];
$img_src = '';
$img_w   = 340;
$img_h   = 500;

// Проверка LCP приоритета (передается из родительского цикла)
// Проверяем и переменную query_var, и ключ в массиве $model
$is_priority = get_query_var('is_lcp_priority', false) || !empty($model['is_lcp_priority']);

if (!empty($model['image_url'])) {
    $img_src = $model['image_url'];
} elseif (!empty($gallery)) {
    $first = is_array($gallery) ? ($gallery[0] ?? null) : $gallery;

    // Если это массив ACF
    if (is_array($first) && !empty($first['sizes']['model_card'])) {
        $img_src = $first['sizes']['medium_large'];
        $img_w   = $first['sizes']['medium_large-width'] ?? 600;
        $img_h   = $first['sizes']['medium_large-height'] ?? 900;
    }
    // Если это ID
    elseif (is_numeric($first) || (is_array($first) && !empty($first['ID']))) {
        $att_id = is_array($first) ? $first['ID'] : $first;
        $img_data = wp_get_attachment_image_src((int)$att_id, 'model_card');
        if ($img_data) {
            $img_src = $img_data[0];
            $img_w   = $img_data[1];
            $img_h   = $img_data[2];
        }
    }
}

// Fallback
if (!$img_src) {
    $img_src = get_stylesheet_directory_uri() . '/assets/images/placeholder-thumbs.webp';
}

/** ИКОНКИ И МЕТА */
$is_verified    = has_term('', 'drygie_tax', $post_id);
$is_recommended = !empty($model['recommended']) ? $model['recommended'] : get_post_meta($post_id, 'recommended', true);
$elite_threshold = 25000;
$has_elite_term = has_term('elitnyye-prostitutki', 'drygie_tax', $post_id);
$elite_by_price = ($price_outcall_1h >= $elite_threshold) || ($price_incall_1h >= $elite_threshold);
$elite_by_acf   = function_exists('get_field') ? (bool)get_field('vip', $post_id) : false;
$is_elite       = ($has_elite_term || $elite_by_price || $elite_by_acf);
$is_individual  = has_term('individualki', 'drygie_tax', $post_id);

if (empty($name) || empty($img_src)) return;

/* ========================================================= */
?>

<?php
$description = strip_tags($model['description'] ?? get_the_excerpt($post_id) ?? '');
$desc_limit  = (int) get_theme_mod('model_card_desc_length', 220);
$desc_limit  = max(160, min(260, $desc_limit));
$short_desc  = mb_substr($description, 0, $desc_limit) . (mb_strlen($description) > $desc_limit ? '…' : '');
$services_preview = array_slice($services, 0, 3);
$format_price = static function (int $val): string {
    return $val > 0 ? number_format($val, 0, ',', ' ') : '—';
};
$has_outcall_prices = ($price_outcall_1h || $price_outcall_2h || $price_outcall_night);
$has_incall_prices  = ($price_incall_1h || $price_incall_2h || $price_incall_night);
$has_stats = ($age || $height || $weight || $bust);

$resolve_contacts = static function (bool $is_cheap) {
    static $cache = [
        'cheap' => null,
        'normal' => null,
    ];
    $key = $is_cheap ? 'cheap' : 'normal';
    if ($cache[$key] !== null) return $cache[$key];

    if ($is_cheap) {
        $raw_tg = get_theme_mod('contact_telegram_5');
        $raw_wa = get_theme_mod('contact_whatsapp_5');
    } else {
        $tg_pool = [];
        $wa_pool = [];
        if ($t = get_theme_mod('contact_telegram')) $tg_pool[] = $t;
        if ($w = get_theme_mod('contact_whatsapp')) $wa_pool[] = $w;
        for ($i = 1; $i <= 4; $i++) {
            if ($t = get_theme_mod("contact_telegram_$i")) $tg_pool[] = $t;
            if ($w = get_theme_mod("contact_whatsapp_$i")) $wa_pool[] = $w;
        }
        $raw_tg = !empty($tg_pool) ? $tg_pool[array_rand($tg_pool)] : '';
        $raw_wa = !empty($wa_pool) ? $wa_pool[array_rand($wa_pool)] : '';
    }

    $tg_clean = trim((string)$raw_tg);
    $tg_clean = preg_replace('~^https?://t\.me/~i', '', $tg_clean);
    $tg_clean = ltrim($tg_clean, '@');
    $tg_clean = preg_replace('~[^a-z0-9_]+~i', '', $tg_clean);
    $wa_clean = preg_replace('~\D+~', '', (string)$raw_wa);

    $cache[$key] = [
        'tg' => $tg_clean,
        'wa' => $wa_clean,
    ];

    return $cache[$key];
};

$is_cheap_model = has_term('deshevyye-prostitutki', 'price_tax', $post_id);
$contacts = $resolve_contacts($is_cheap_model);
$tg_handle = $contacts['tg'] ?? '';
$wa_number = $contacts['wa'] ?? '';
?>

<li class="mf-item list-none w-full" data-post-id="<?= esc_attr($post_id) ?>">
    <article
        class="anketa-card"
        onclick="(function(e, url) {
            e.metaKey || e.ctrlKey ? window.open(url,'_blank') : window.location.href = url;
        })(event, '<?= esc_js($profile_url) ?>')">
        <a href="<?= esc_url($profile_url) ?>" class="anketa-card__link" aria-label="Профиль <?= esc_attr($name) ?>"></a>

        <div class="anketa-card__header">
            <div class="anketa-card__title"><?= esc_html($name) ?></div>

        </div>

        <div class="anketa-card__main">
            <div class="anketa-card__media-block">
                <div class="anketa-card__img-wrapper">
                    <img
                        src="<?= esc_url($img_src) ?>"
                        alt="<?= esc_attr($name) ?>"
                        <?php if ($is_priority): ?>
                        loading="eager"
                        fetchpriority="high"
                        <?php else: ?>
                        loading="lazy"
                        decoding="async"
                        <?php endif; ?>
                        width="<?= esc_attr($img_w) ?>"
                        height="<?= esc_attr($img_h) ?>"
                        class="anketa-card__img" />
                </div>
            </div>
    
            <div class="anketa-card__content">
                <?php if ($has_stats || $has_outcall_prices || $has_incall_prices): ?>
                    <div class="anketa-card__stats-wrapper">
                        <?php if ($has_stats): ?>
                            <div class="anketa-card__stats">
                                <?php if ($age): ?>
                                    <div><span>Возраст</span><strong><?= esc_html($age) ?></strong></div>
                                <?php endif; ?>
                                <?php if ($height): ?>
                                    <div><span>Рост</span><strong><?= esc_html($height) ?></strong></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($has_stats): ?>
                            <div class="anketa-card__stats">
                                <?php if ($weight): ?>
                                    <div><span>Вес</span><strong><?= esc_html($weight) ?></strong></div>
                                <?php endif; ?>
                                <?php if ($bust): ?>
                                    <div><span>Грудь</span><strong><?= esc_html($bust) ?></strong></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($has_outcall_prices || $has_incall_prices): ?>
                    <div class="anketa-card__price-toggle" style="display: flex; gap: 15px; font-size: 13px; font-weight: 600; cursor: pointer;">
                        <div style="border-bottom: 2px solid #e865a0; color: #e865a0; padding-bottom: 2px;" onclick="
                             event.stopPropagation();
                             var p = this.closest('.anketa-card__content');
                             p.querySelector('.price-incall').style.display = '';
                             p.querySelector('.price-outcall').style.display = 'none';
                             this.style.borderBottomColor = '#e865a0';
                             this.style.color = '#e865a0';
                             this.nextElementSibling.style.borderBottomColor = 'transparent';
                             this.nextElementSibling.style.color = '#888';
                        ">Апартаменты</div>
                        <div style="border-bottom: 2px solid transparent; color: #888; padding-bottom: 2px;" onclick="
                             event.stopPropagation();
                             var p = this.closest('.anketa-card__content');
                             p.querySelector('.price-incall').style.display = 'none';
                             p.querySelector('.price-outcall').style.display = '';
                             this.style.borderBottomColor = '#e865a0';
                             this.style.color = '#e865a0';
                             this.previousElementSibling.style.borderBottomColor = 'transparent';
                             this.previousElementSibling.style.color = '#888';
                        ">Выезд</div>
                    </div>

                    <div class="anketa-card__prices price-incall">
                        <div><span>1 час</span><strong style="color: #e865a0;"><?= esc_html($format_price($price_incall_1h)) ?></strong></div>
                        <div><span>2 часа</span><strong style="color: #e865a0;"><?= esc_html($format_price($price_incall_2h)) ?></strong></div>
                        <?php $color = ($format_price($price_incall_night) === '—') ? 'initial' : '#e865a0'; ?>
                        <div><span>Ночь</span><strong style="color: <?= $color; ?>;"><?= esc_html($format_price($price_incall_night)) ?></strong></div>
                    </div>

                    <div class="anketa-card__prices price-outcall" style="display: none;">
                        <div><span>1 час</span><strong style="color: #e865a0;"><?= esc_html($format_price($price_outcall_1h)) ?></strong></div>
                        <div><span>2 часа</span><strong style="color: #e865a0;"><?= esc_html($format_price($price_outcall_2h)) ?></strong></div>
                        <?php $color = ($format_price($price_outcall_night) === '—') ? 'initial' : '#e865a0'; ?>
                        <div><span>Ночь</span><strong style="color: <?= $color; ?>;"><?= esc_html($format_price($price_outcall_night)) ?></strong></div>
                    </div>
                <?php endif; ?>
                <?php
                    $metro_list = is_array($metro) ? array_values(array_filter($metro)) : (trim((string)$metro) !== '' ? [trim((string)$metro)] : []);
                    $metro_primary = $metro_list[0] ?? '';
                ?>
                <?php if ($metro_primary): ?>
                    <div class="anketa-card__metro" style="display: inline-flex; gap: 6px; color: #352222; font-size: 12px; line-height: 1;">
                        <span style="opacity: .7;">Метро</span>
                        <span style="color: #e865a0; font-weight: 600;"><?= esc_html($metro_primary) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($is_verified): ?>
                    <div style="display: flex; justify-content: center; align-items: center; gap: 5px;">
                        <svg
                            width="20"
                            height="20"
                            viewBox="0 0 32 32"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                            focusable="false"
                            style="display: block;"
                        >
                            <path
                                fill="#3fbf36"
                                d="m16 0c8.836556 0 16 7.163444 16 16s-7.163444 16-16 16-16-7.163444-16-16 7.163444-16 16-16zm0 2c-7.7319865 0-14 6.2680135-14 14s6.2680135 14 14 14 14-6.2680135 14-14-6.2680135-14-14-14zm5.7279221 9 1.4142135 1.4142136-8.4852814 8.4852813-5.6568542-5.6568542 1.4142136-1.4142136 4.2419335 4.2419336z"
                            />
                        </svg>
                        <strong style="color: #3fbf36">Анкета проверена</strong>
                    </div>
                <?php endif; ?>
    
                <?php if (!empty($services_preview)): ?>
                    <div class="anketa-card__services">
                        <?php
                            foreach ($services_preview as $service) {
                                echo '<div class="anketa-card__service">'.htmlspecialchars($service).'</div>';
                            }
                        ?>
                        
                    </div>
                <?php endif; ?>

                <?php
                    $tag_base = 'display: inline-flex; align-items: center; padding: 3px 8px; border-radius: 0; border: 0; font-size: 11px; line-height: 1; font-weight: 700; letter-spacing: .04em; width: max-content;';
                    $tags = [];
                    if ($has_incall_prices) $tags[] = ['label' => 'Выезд', 'style' => $tag_base . ' background: #d68054; color: #ffffff;'];
                    if ($is_elite) $tags[] = ['label' => 'VIP', 'style' => $tag_base . ' background: #dbb54b; color: #000;'];
                    if ($is_individual) $tags[] = ['label' => 'Индивидуалка', 'style' => $tag_base . ' background: #52c4a3; color: #ffffff;'];
                ?>
                <?php if (!empty($tags)): ?>
                    <div class="anketa-card__tags" style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: auto; padding-top: 6px;">
                        <?php foreach ($tags as $tag): ?>
                            <div class="anketa-card__tag" style="<?= esc_attr($tag['style']) ?>"><?= esc_html($tag['label']) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php 
            // Global phone from theme settings
            $phone_global = trim((string) get_theme_mod('contact_number')) ?: '-';
            
            // Build Social Links
            $tg_link = !empty($tg_handle) ? 'https://t.me/' . $tg_handle : '';
            $wa_link = !empty($wa_number) ? 'https://wa.me/' . $wa_number : '';
            
            if ($phone_global || $tg_link || $wa_link): 
        ?>
            <div class="anketa-card__contacts-wrapper" style="margin-top: 10px; position: relative; z-index: 2;">
                <div class="anketa-card__contacts" style="display: flex; gap: 8px;">
                    <?php if ($phone_global): ?>
                        <button type="button" 
                                class="js-card-phone-btn"
                                style="flex: 1; display: inline-flex; align-items: center; justify-content: center; height: 36px; border-radius: 4px; background-color: #436fcc; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; border: none; gap: 6px;"
                                data-phone="<?= esc_attr($phone_global) ?>"
                                onclick="event.stopPropagation(); event.preventDefault(); var phone=this.dataset.phone||'-'; var span=this.querySelector('.js-phone-label'); if(span) span.textContent=phone; var btn=this; setTimeout(function(){ var a=document.createElement('a'); a.href='tel:'+phone.replace(/\D/g,''); a.className=btn.className; a.style.cssText=btn.style.cssText; a.innerHTML=btn.innerHTML; a.onclick=function(ev){ev.stopPropagation();}; btn.replaceWith(a); },50);">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="pointer-events: none;">
                                <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                            </svg>
                            <span class="js-phone-label">Показать</span>
                        </button>
                    <?php endif; ?>

                    <?php if ($tg_link): ?>
                        <button type="button" data-go="tg" aria-label="Открыть Telegram"
                           onclick="event.stopPropagation();"
                           style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 4px; background-color: #229ED9; color: #fff; flex-shrink: 0; border: none; cursor: pointer;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                               <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .33z"/>
                            </svg>
                        </button>
                    <?php endif; ?>

                    <?php if ($wa_link): ?>
                        <button type="button" data-go="wa" aria-label="Открыть WhatsApp"
                           onclick="event.stopPropagation();"
                           style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 4px; background-color: #25D366; color: #fff; flex-shrink: 0; border: none; cursor: pointer;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                               <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884"/>
                            </svg>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </article>
</li>
