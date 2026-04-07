<?php
/* Template Name: Model — Media (tabs + sticky left + badges) */
/* Template Post Type: models */

if (!defined('ABSPATH')) exit;
require_once get_template_directory() . '/components/auto-text.php';
get_header();

$id   = get_the_ID();
$name = function_exists('get_field') ? (get_field('name', $id) ?: get_the_title($id)) : get_the_title($id);
$ACCENT = '#e1315a';
$date_published = get_the_date('d.m.Y', $id);

/* ================== ACF ================== */
$gallery    = (array)(function_exists('get_field') ? (get_field('photo',  $id) ?: []) : []);
$selfies    = (array)(function_exists('get_field') ? (get_field('selfie', $id) ?: []) : []);
$videos_raw =            function_exists('get_field') ?  get_field('video', $id)      : [];

/* videos_raw → список URL */
$videos = [];
if (is_string($videos_raw)) {
    $s = trim($videos_raw);
    if ($s !== '') $videos = preg_split('~[\s,;]+~u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [$s];
} elseif (is_array($videos_raw)) {
    if (isset($videos_raw['url']) && is_string($videos_raw['url'])) {
        $videos[] = trim((string)$videos_raw['url']);
    } elseif (array_keys($videos_raw) === range(0, count($videos_raw) - 1)) {
        foreach ($videos_raw as $it) {
            if (is_array($it) && !empty($it['url'])) $videos[] = trim((string)$it['url']);
            elseif (is_string($it))                  $videos[] = trim($it);
        }
    }
}
$videos = array_values(array_unique(array_filter($videos)));

/* ================== термы/поля ================== */
$districts = get_the_terms($id, 'rayonu_tax');
if (is_wp_error($districts)) $districts = [];
$hair      = wp_get_post_terms($id, 'cvet-volos_tax',   ['fields' => 'names']) ?: [];
$nation    = wp_get_post_terms($id, 'nationalnost_tax', ['fields' => 'names']) ?: [];
$metro     = get_the_terms($id, 'metro_tax');
if (is_wp_error($metro)) $metro = [];

/* ================== услуги модели ================== */
$services_raw = function_exists('get_field') ? get_field('services', $id) : null;
if (empty($services_raw)) {
    $services_raw = get_post_meta($id, 'services', true);
}

$services_available_ids = [];
$services_available_slugs = [];
$services_available_names = [];
$services_lower = static function ($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
};
$services_add_term = static function ($term) use (&$services_available_ids, &$services_available_slugs, &$services_available_names, $services_lower) {
    if (!$term || is_wp_error($term)) return;
    $services_available_ids[(int)$term->term_id] = true;
    if (!empty($term->slug)) $services_available_slugs[$services_lower($term->slug)] = true;
    if (!empty($term->name)) $services_available_names[$services_lower($term->name)] = true;
};
$services_add_name = static function ($name) use (&$services_available_names, $services_lower, $services_add_term) {
    $name = trim((string)$name);
    if ($name === '') return;
    if (is_numeric($name)) {
        $term = get_term((int)$name, 'uslugi_tax');
        if ($term && !is_wp_error($term)) {
            $services_add_term($term);
            return;
        }
    }
    $services_available_names[$services_lower($name)] = true;
};

if (!empty($services_raw)) {
    if (is_string($services_raw)) {
        $parts = preg_split('~[\r\n,;]+~u', $services_raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($parts as $part) $services_add_name($part);
    } elseif (is_array($services_raw)) {
        foreach ($services_raw as $item) {
            if (is_object($item) && isset($item->term_id)) {
                $services_add_term($item);
            } elseif (is_array($item)) {
                if (!empty($item['term_id']) || !empty($item['id'])) {
                    $term_id = (int)($item['term_id'] ?? $item['id']);
                    $services_add_term(get_term($term_id, 'uslugi_tax'));
                } elseif (!empty($item['slug'])) {
                    $services_available_slugs[$services_lower($item['slug'])] = true;
                } elseif (!empty($item['name'])) {
                    $services_add_name($item['name']);
                }
            } elseif (is_numeric($item)) {
                $services_add_term(get_term((int)$item, 'uslugi_tax'));
            } elseif (is_string($item)) {
                $services_add_name($item);
            }
        }
    }
}

if (empty($services_available_ids) && empty($services_available_slugs) && empty($services_available_names)) {
    $service_terms = get_the_terms($id, 'uslugi_tax');
    if ($service_terms && !is_wp_error($service_terms)) {
        foreach ($service_terms as $term) $services_add_term($term);
    }
}

$services_terms_all = [];
if (taxonomy_exists('uslugi_tax')) {
    $terms = get_terms([
        'taxonomy'   => 'uslugi_tax',
        'hide_empty' => false,
        'orderby'    => 'term_order',
        'order'      => 'ASC',
    ]);

    if (!is_wp_error($terms) && !empty($terms)) {
        $has_children = [];
        foreach ($terms as $term) {
            if ((int)$term->parent !== 0) {
                $has_children[(int)$term->parent] = true;
            }
        }
        $leaf_terms = array_values(array_filter($terms, static function ($term) use ($has_children) {
            return empty($has_children[(int)$term->term_id]);
        }));
        $services_terms_all = !empty($leaf_terms) ? $leaf_terms : $terms;
    }
}


$age    = trim((string)(function_exists('get_field') ? get_field('age',    $id) : ''));
$height = trim((string)(function_exists('get_field') ? get_field('height', $id) : ''));
$weight = trim((string)(function_exists('get_field') ? get_field('weight', $id) : ''));
$bust   = trim((string)(function_exists('get_field') ? get_field('bust',   $id) : ''));

// Вспомогательная функция для получения числового значения из ACF с фолбэком
$get_p = function($field, $fallback = 0) use ($id) {
    $v = function_exists('get_field') ? get_field($field, $id) : null;
    if (!$v || (float)$v <= 0) return (float)$fallback;
    return (float)$v;
};

$price_in_1h     = $get_p('price');
$price_in_2h     = $get_p('price_2_hours', $price_in_1h ? $price_in_1h * 2 : 0);
$price_in_night  = $get_p('price_night', $price_in_1h ? $price_in_1h * 8 : 0);

$price_out_1h    = $get_p('price_outcall');
$price_out_2h    = $get_p('price_outcall_2_hours', $price_out_1h ? $price_out_1h * 2 : 0);
$price_out_night = $get_p('price_outcall_night', $price_out_1h ? $price_out_1h * 8 : 0);

$about     = function_exists('get_field') ? get_field('description', $id) : '';
if (trim(wp_strip_all_tags((string) $about)) === '' && function_exists('dosugmoskva24_generate_model_auto_about')) {
    $about = dosugmoskva24_generate_model_auto_about([
        'post_id' => $id,
        'name' => $name,
        'city' => 'Москва',
        'age' => $age,
        'height' => $height,
        'weight' => $weight,
        'bust' => $bust,
        'districts' => $districts,
        'metro' => $metro,
    ]);
}

/* Статусы */
$vip      = function_exists('get_field') ? (bool)get_field('vip', $id)       : false;
$verified = function_exists('get_field') ? (bool)get_field('verified', $id)  : false;
$online   = function_exists('get_field') ? get_field('online', $id)          : '';

/* Контакты */
// --- нормализация
$__mr_norm_tg = function ($v) {
    $v = trim((string)$v);
    if ($v === '') return '';
    $v = preg_replace('~^https?://t\.me/~i', '', $v);
    $v = ltrim($v, '@');
    return preg_replace('~[^a-z0-9_]+~i', '', $v);
};
$__mr_norm_wa = function ($v) {
    return preg_replace('~\D+~', '', (string)$v);
};

// --- собрать варианты
$__tg_variants = [];
$__wa_variants = [];

// старые поля (основные)
$main_tg = get_theme_mod('contact_telegram');
$main_wa = get_theme_mod('contact_whatsapp');
if (!empty($main_tg)) $__tg_variants[] = $__mr_norm_tg($main_tg);
if (!empty($main_wa)) $__wa_variants[] = $__mr_norm_wa($main_wa);

// новые 4 варианта
for ($i = 1; $i <= 4; $i++) {
    $tg = get_theme_mod("contact_telegram_$i");
    $wa = get_theme_mod("contact_whatsapp_$i");
    if (!empty($tg)) $__tg_variants[] = $__mr_norm_tg($tg);
    if (!empty($wa)) $__wa_variants[] = $__mr_norm_wa($wa);
}

// --- выбрать случайные
$__chosen_tg = !empty($__tg_variants) ? $__tg_variants[array_rand($__tg_variants)] : '';
$__chosen_wa = !empty($__wa_variants) ? $__wa_variants[array_rand($__wa_variants)] : '';

// --- финальные значения (не меняем имена переменных!)
$phone = trim((string) get_theme_mod('contact_number'));

// если задано хоть одно значение — заменяем
// 1. Определяем: это "Дешевые"? (Страница или категория модели)
$is_cheap_context = (is_page('deshevyye-prostitutki') || has_term('deshevyye-prostitutki', 'price_tax', get_the_ID()));

// 2. Выбираем "сырые" данные
if ($is_cheap_context) {
    // Логика для дешевых: берем строго 5-й контакт
    $raw_tg = get_theme_mod('contact_telegram_5');
    $raw_wa = get_theme_mod('contact_whatsapp_5');
} else {
    // Логика для остальных: собираем пул и берем рандом
    $tg_pool = [];
    $wa_pool = [];

    // Основной
    if ($t = get_theme_mod('contact_telegram')) $tg_pool[] = $t;
    if ($w = get_theme_mod('contact_whatsapp')) $wa_pool[] = $w;

    // Дополнительные 1-4
    for ($i = 1; $i <= 4; $i++) {
        if ($t = get_theme_mod("contact_telegram_$i")) $tg_pool[] = $t;
        if ($w = get_theme_mod("contact_whatsapp_$i")) $wa_pool[] = $w;
    }

    // Выбираем случайный, если есть из чего
    $raw_tg = !empty($tg_pool) ? $tg_pool[array_rand($tg_pool)] : '';
    $raw_wa = !empty($wa_pool) ? $wa_pool[array_rand($wa_pool)] : '';
}

// 3. Нормализация (чистим мусор, чтобы получить чистый ник/номер)
$tg = trim((string)$raw_tg);
$tg = preg_replace('~^https?://t\.me/~i', '', $tg); // Убираем https://t.me/
$tg = ltrim($tg, '@'); // Убираем @
$tg = preg_replace('~[^a-z0-9_]+~i', '', $tg); // Убираем лишние символы

$wa = preg_replace('~\D+~', '', (string)$raw_wa); // Оставляем только цифры

// 4. Формирование ссылок
$tel_href = (isset($phone) && $phone) ? 'tel:' . preg_replace('~\D+~', '', $phone) : '';
$wa_href  = $wa ? 'https://wa.me/' . $wa : '';
$tg_href  = $tg ? 'https://t.me/' . $tg : '';
// очистка временных
unset($__mr_norm_tg, $__mr_norm_wa, $__tg_variants, $__wa_variants, $__chosen_tg, $__chosen_wa, $tg, $wa, $main_tg, $main_wa, $i);

/* ALT */
$hair_str   = $hair ? implode(', ', $hair) : '';
$nation_str = $nation ? implode(', ', $nation) : '';
$alt_parts = [];
if ($bust   !== '') $alt_parts[] = 'грудь ' . $bust;
if ($height !== '') $alt_parts[] = 'рост ' . $height . ' см';
if ($weight !== '') $alt_parts[] = 'вес ' . $weight . ' кг';
if ($hair_str   !== '') $alt_parts[] = 'цвет волос ' . $hair_str;
if ($nation_str !== '') $alt_parts[] = 'национальность ' . $nation_str;
$alt = 'Проститутка ' . $name . ($alt_parts ? ' - ' . implode(', ', $alt_parts) : '');

/* ================== helpers ================== */
function em_normalize_video_canonical($raw)
{
    if (is_array($raw)) $raw = (string)($raw['url'] ?? '');
    $raw = trim((string)$raw);
    if ($raw === '') return null;

    if (stripos($raw, '<iframe') !== false && preg_match('~src=["\']([^"\']+)~i', $raw, $m)) $raw = $m[1];

    $u = @parse_url($raw);
    $scheme = strtolower($u['scheme'] ?? '');
    $host = strtolower($u['host'] ?? '');
    $path = $u['path'] ?? '';
    $query  = [];
    if (!empty($u['query'])) parse_str($u['query'], $query);

    if ($scheme && preg_match('~(^|\.)youtube\.com$|(^|\.)youtu\.be$~', $host)) {
        $id = '';
        if (preg_match('~^/([A-Za-z0-9_\-]{6,})$~', $path, $m)) $id = $m[1];
        if (!$id && !empty($query['v'])) $id = preg_replace('~[^A-Za-z0-9_\-]~', '', $query['v']);
        if (!$id && preg_match('~^/(?:embed|shorts)/([A-Za-z0-9_\-]{6,})~', $path, $m)) $id = $m[1];
        if ($id) return ['key' => 'yt:' . $id, 'type' => 'embed', 'src' => 'https://www.youtube.com/embed/' . $id, 'poster' => 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg'];
    }

    if ($scheme && preg_match('~(^|\.)vimeo\.com$~', $host)) {
        if (preg_match('~^/(?:video/)?(\d+)~', $path, $m)) {
            $vid = $m[1];
            return ['key' => 'vimeo:' . $vid, 'type' => 'embed', 'src' => 'https://player.vimeo.com/video/' . $vid, 'poster' => ''];
        }
    }

    if ($scheme && preg_match('~\.mp4$~i', $path)) {
        $hostKey = preg_replace('~^www\.~i', '', $host);
        $key     = strtolower($hostKey . $path);
        return ['key' => 'mp4:' . $key, 'type' => 'mp4', 'src' => $raw, 'poster' => ''];
    }

    if (in_array($scheme, ['http', 'https'], true)) {
        return ['key' => 'embed:' . $host . ($path ?: '/'), 'type' => 'embed', 'src' => $raw, 'poster' => ''];
    }
    return null;
}

function em_img_single_sizes($im)
{
    $id = 0;
    $url = '';
    if (is_array($im)) {
        $id = isset($im['ID']) ? (int)$im['ID'] : 0;
        $url = $im['url'] ?? '';
    } elseif (is_numeric($im)) {
        $id = (int)$im;
    } elseif (is_string($im)) {
        $url = trim($im);
    }

    if ($id) {
        $ml   = wp_get_attachment_image_src($id, 'medium_large');
        $md   = wp_get_attachment_image_src($id, 'medium');
        $th   = wp_get_attachment_image_src($id, 'thumbnail');
        $full = wp_get_attachment_image_src($id, 'full');
        return [
            'ml'    => $ml[0]   ?? ($full[0] ?? ''),
            'ml_w'  => $ml[1]   ?? null,
            'ml_h'  => $ml[2]   ?? null,
            'thumb' => $md[0]   ?? ($th[0] ?? ($ml[0] ?? $full[0] ?? '')),
            'full'  => $full[0] ?? ($ml[0] ?? ''),
        ];
    }
    if ($url !== '') return ['ml' => $url, 'ml_w' => null, 'ml_h' => null, 'thumb' => $url, 'full' => $url];
    return null;
}

/* ================== Фото ================== */
$images = [];
foreach ((array)$gallery as $im) {
    $r = em_img_single_sizes($im);
    if ($r && $r['ml']) {
        $images[] = ['type' => 'image', 'src' => $r['full'], 'ml' => $r['ml'], 'ml_w' => $r['ml_w'], 'ml_h' => $r['ml_h'], 'thumb' => $r['thumb'], 'poster' => ''];
    }
}
$images_count = count($images);

/* фолбэк-постер для видео */
$poster_fallback = $images_count ? ($images[0]['thumb'] ?: $images[0]['src']) : '';

/* ================== Видео (дедуп по key) ================== */
$videos_media_map = [];
foreach ($videos as $vraw) {
    $norm = em_normalize_video_canonical($vraw);
    if (!$norm) continue;
    if ($norm['poster'] === '' && $poster_fallback) $norm['poster'] = $poster_fallback;
    $videos_media_map[$norm['key']] = ['type' => ($norm['type'] === 'mp4' ? 'mp4' : 'video'), 'src' => $norm['src'], 'poster' => $norm['poster']];
}
$videos_media = array_values($videos_media_map);
$videos_count = count($videos_media);

/* ================== Селфи ================== */
$selfies_items = [];
foreach ($selfies as $im) {
    if (is_array($im)) {
        $full  = $im['url'] ?? '';
        $thumb = $im['sizes']['medium'] ?? ($im['url'] ?? '');
    } else {
        $arrF  = wp_get_attachment_image_src((int)$im, 'full');
        $arrM  = wp_get_attachment_image_src((int)$im, 'medium');
        $full  = $arrF[0] ?? '';
        $thumb = $arrM[0] ?? $full;
    }
    if ($full) $selfies_items[] = ['type' => 'image', 'src' => $full, 'thumb' => $thumb, 'poster' => ''];
}
$selfies_count = count($selfies_items);

/* ================== Лайтбокс-поток ================== */
$lb_items = array_merge(
    array_map(fn($m) => [
        'type'   => 'image',
        'src'    => $m['src'],
        'poster' => '',
        'alt'    => $alt,
    ], $images),
    array_map(fn($v) => [
        'type'   => $v['type'],
        'src'    => $v['src'],
        'poster' => $v['poster'],
        'alt'    => $alt, // тот же alt для постеров видео
    ], $videos_media),
    array_map(fn($m) => [
        'type'   => 'image',
        'src'    => $m['src'],
        'poster' => '',
        'alt'    => $alt, // при желании можно 'Селфи ' . $name
    ], $selfies_items)
);

?>
<main class="mx-auto w-full lg:w-[1200px] px-4 bg-white text-neutral-700 singlepage-root">
    <style>
        .singlepage-root.singlepage-root *,
        .singlepage-root.singlepage-root *::before,
        .singlepage-root.singlepage-root *::after,
        .singlepage-root.singlepage-root [class*="rounded"] {
            border-radius: 0;
        }
    </style>

    <article class="grid grid-cols-1 lg:grid-cols-12 gap-2 lg:gap-8 py-6">
        <!-- ========== ЛЕВО (5/12): компактнее ========== -->
        <section class="lg:col-span-5 lg:top-6 lg:self-start" aria-label="Фото и контакты модели">
            <h1 id="model-title" class="text-2xl sm:text-3xl font-bold leading-tight mb-8">
                <?php
                    $auto_h1_component = get_theme_file_path('components/h1-auto.php');
                    if (file_exists($auto_h1_component)) { require $auto_h1_component; }
                    $h1 = get_query_var('auto_h1');
                    if (empty($h1) && !empty($GLOBALS['auto_h1'])) { $h1 = $GLOBALS['auto_h1']; }
                    if (empty($h1)) { $h1 = 'Проститутка ' . $name . ', Москва'; }
                    echo esc_html($h1);
                ?>
            </h1>

            <div class="relative rounded-lg overflow-hidden border border-neutral-200 bg-neutral-100 aspect-[3/4] mb-3">
                <?php
                    $on = (bool)$online;
                    if ($online !== '' && $on) { ?>
                        <span class="absolute top-3 left-3 z-40 px-2 py-1 rounded-full text-xs font-semibold bg-emerald-500/90 text-white backdrop-blur-sm shadow-md select-none">Онлайн</span>
                <?php } ?>

                <div class="w-full h-full relative" id="main-photo-container">
                    <?php if ($images_count) { ?>
                        <?php foreach ($images as $idx => $m) { 
                            $opacityClass = ($idx === 0) ? 'opacity-100 z-10' : 'opacity-0 z-0'; 
                        ?>
                            <div class="absolute inset-0 w-full h-full transition-opacity duration-300 ease-in-out js-main-photo <?php echo $opacityClass; ?>" 
                                data-index="<?php echo $idx; ?>">
                                <img
                                    src="<?php echo esc_url($m['ml'] ?? $m['src']); ?>"
                                    alt="<?php echo esc_attr($alt); ?>"
                                    class="w-full h-full object-cover cursor-pointer js-open-lightbox"
                                    data-idx="<?php echo esc_attr($idx); ?>"
                                    loading="<?php echo $idx === 0 ? 'eager' : 'lazy'; ?>"
                                >
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>
                
                <div class="md:hidden absolute bottom-3 left-4 z-30 flex items-center gap-1 bg-black/60 backdrop-blur-sm text-white text-xs font-semibold px-3 py-1 rounded-full">
                    <span class="js-photo-index">1</span>/<span><?php echo (int)$images_count; ?></span>
                </div>
            </div>

            <?php if ($images_count > 1) { ?>
                <div class="relative px-10 mt-4">
                    <button type="button" 
                        style="background: #fdb6c4; padding: 6px; left: -10px;"                        
                        class="js-thumb-prev absolute top-1/2 -translate-y-1/2 z-30 w-6 h-6 flex items-center justify-center shadow-md rounded-full" 
                        aria-label="Назад">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    </button>

                    <div class="swiper js-thumbs-slider">
                        <div class="swiper-wrapper">
                            <?php foreach ($images as $idx => $m) { ?>
                                <div class="swiper-slide">
                                    <div class="w-full aspect-[3/4] rounded-md overflow-hidden transition-all duration-200 js-thumb-item <?php echo ($idx === 0) ? '' : 'opacity-60'; ?>" 
                                        data-index="<?php echo $idx; ?>">
                                        <img src="<?php echo esc_url($m['thumb'] ?? $m['src']); ?>" 
                                            class="w-full h-full object-cover pointer-events-none" 
                                            alt="thumb">
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <button type="button"
                        style="background: #fdb6c4; padding: 6px; right: -10px;"        
                        class="js-thumb-next absolute top-1/2 -translate-y-1/2 z-30 w-6 h-6 flex items-center justify-center shadow-md rounded-full" 
                        aria-label="Вперед">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </button>
                </div>
            <?php } ?>

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const mainPhotos = document.querySelectorAll('.js-main-photo');
                    const thumbItems = document.querySelectorAll('.js-thumb-item');
                    const counterEl = document.querySelector('.js-photo-index');

                    function setActivePhoto(index) {
                        mainPhotos.forEach((photo, i) => {
                            if (i === index) {
                                photo.classList.replace('opacity-0', 'opacity-100');
                                photo.classList.replace('z-0', 'z-10');
                            } else {
                                photo.classList.replace('opacity-100', 'opacity-0');
                                photo.classList.replace('z-10', 'z-0');
                            }
                        });

                        thumbItems.forEach((thumb, i) => {
                            if (i === index) {
                                thumb.classList.remove('opacity-60');
                                thumb.classList.add('opacity-100');
                            } else {
                                thumb.classList.add('border-transparent', 'opacity-60');
                                thumb.classList.remove('opacity-100');
                            }
                        });

                        if (counterEl) counterEl.textContent = index + 1;
                    }

                    const thumbsSwiper = new Swiper('.js-thumbs-slider', {
                        // ЯВНО УКАЗЫВАЕМ КОЛИЧЕСТВО:
                        slidesPerView: 4, // 4 миниатюры в ряд на мобильном
                        spaceBetween: 8,
                        breakpoints: {
                            640: { slidesPerView: 5 }, // 5 на планшетах
                            1024: { slidesPerView: 6 } // 6 на десктопе
                        },
                        navigation: {
                            nextEl: '.js-thumb-next',
                            prevEl: '.js-thumb-prev',
                        },
                        watchSlidesProgress: true,
                        slideToClickedSlide: true,
                        on: {
                            // При клике на слайд
                            click: function(s) {
                                if (typeof s.clickedIndex !== 'undefined') {
                                    setActivePhoto(s.clickedIndex);
                                }
                            },
                            // При перетаскивании/кнопках
                            slideChange: function() {
                                setActivePhoto(this.activeIndex);
                            }
                        }
                    });
                });
            </script>
        </section>


        <!-- ========== ПРАВО (7/12) ========== -->
        <section class="lg:col-span-7" aria-labelledby="model-title">
            <!-- Заголовок + кнопка избранного -->
            <header class="mt-1 flex items-start justify-end">
                <!-- Сердечко -->
                <button
                    type="button"
                    id="fav-toggle"
                    aria-label="В избранное"
                    title="В избранное"
                    class="shrink-0 inline-flex items-center justify-center 
               w-10 h-10 p-2 rounded-full
               lg:w-auto lg:h-auto lg:px-3 lg:py-2 lg:rounded-lg
               border border-neutral-200 text-neutral-700 hover:bg-rose-50 transition-colors"
                    aria-pressed="false"
                    data-id="<?php echo (int)$id; ?>"
                    data-title="<?php echo esc_attr($name); ?>">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 1 0-7.8 7.8l1 1L12 22l7.8-8.6 1-1a5.5 5.5 0 0 0 0-7.8z" />
                    </svg>
                    <span class="hidden lg:inline ml-2">В избранное</span>
                </button>
            </header>

            <!-- Параметры -->
            <section aria-label="Параметры модели" class="mt-8">
                <style>
                    /* Зебра для параметров: по умолчанию (мобилки) 1 колонка */
                    .params-grid div:nth-child(even) { background-color: #f2f2f2ff; }
                    .params-grid div:nth-child(odd) { background-color: #fdf2f4; }

                    @media (min-width: 640px) {
                        /* Для 2 колонок (sm:grid-cols-2): 
                           строка 1: 1 - odd, 2 - even -> оба серые? Нет, в дизайне строки чередуются.
                           Чтобы строки чередовались:
                           1, 2 - серые (odd, even)
                           3, 4 - розовые (odd, even)
                           5, 6 - серые ...
                        */
                        .params-grid div:nth-child(4n-3),
                        .params-grid div:nth-child(4n-2) { background-color: #f2f2f2ff; }
                        
                        .params-grid div:nth-child(4n-1),
                        .params-grid div:nth-child(4n) { background-color: #fdf2f4; }
                    }
                </style>
                <dl class="params-grid grid grid-cols-1 sm:grid-cols-2" style="gap: 5px 20px;">
                    <?php
                    // Функция для вывода параметра с правильной семантикой (dt/dd)
                    $row = function ($label, $val, $taxonomy = null) {
                        if ($val === '' || $val === null) return;

                        if ($taxonomy) {
                            $terms = wp_get_post_terms(get_the_ID(), $taxonomy);
                            if (!empty($terms) && !is_wp_error($terms)) {
                                $term = $terms[0];

                                // Нативная ссылка терма учитывает rewrite и вложенность.
                                $term_link = get_term_link($term);

                                if (!empty($term_link) && !is_wp_error($term_link)) {
                                    $val = '<a href="' . esc_url($term_link) . '" class="text-[#e865a0] hover:underline font-normal">' . esc_html($val) . '</a>';
                                }
                            }
                        }

                        echo '<div class="flex items-center justify-between" style="padding: 5px 10px;">';
                        echo '<dt class="text-neutral-700 font-normal">' . esc_html($label) . '</dt>';
                        echo '<dd class="font-normal text-right">' . (strpos($val, '<a') === false ? esc_html($val) : $val) . '</dd>';
                        echo '</div>';
                    };

                    // Вывод параметров
                    $row('Возраст',           $age ? $age : '', 'vozrast_tax');
                    $row('Рост',              $height ? $height : '', 'rost_tax');
                    $row('Вес',               $weight ? $weight : '', 'ves_tax');
                    $row('Грудь',             $bust, 'grud_tax');
                    $row('Цвет волос',        $hair ? implode(', ', $hair) : '', 'cvet-volos_tax');
                    $row('Национальность',    $nation ? implode(', ', $nation) : '', 'nationalnost_tax');
                    $row('Цена',              $price_in_1h ? number_format($price_in_1h, 0, ',', ' ') . ' RUB' : '', 'price_tax');
                    ?>
                </dl>
            </section>

            <!-- Price Table (Fixed Grid) -->
            <div class="mt-8 mb-8">
                <style>
                    .price-grid {
                        display: grid;
                        grid-template-columns: repeat(3, 1fr);
                        gap: 4px;
                        font-family: 'Libertinus', sans-serif;
                    }
                    .price-grid__header {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 8px;
                        padding: 10px 0;
                        color: #404040; /* text-neutral-700 */
                        font-weight: 500;
                        font-size: 18px;
                    }
                    .price-grid__cell {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: 10px 4px;
                        border-radius: 4px;
                        font-size: 18px;
                        color: #404040; /* text-neutral-700 */
                    }
                    .price-grid__cell--gray { background-color: #f2f2f2ff; }
                    .price-grid__cell--pink { background-color: #fdf2f4; }
                    .price-grid__cell--value { font-weight: 500; }
                </style>

                <?php
                $format_table = fn($v) => $v > 0 ? number_format($v, 0, ',', ' ') . ' RUB' : '-';
                ?>
                <div class="price-grid">
                    <!-- Headings -->
                    <div class="price-grid__header">
                        <svg class="w-5 h-5 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <span>Время</span>
                    </div>
                    <div class="price-grid__header">
                        <svg class="w-5 h-5 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        <span>Апартаменты</span>
                    </div>
                    <div class="price-grid__header">
                        <svg class="w-5 h-5 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="2" y="10" width="20" height="8" rx="2"></rect>
                            <path d="M7 10V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v5"></path>
                            <circle cx="7" cy="18" r="2"></circle>
                            <circle cx="17" cy="18" r="2"></circle>
                        </svg>
                        <span>Выезд</span>
                    </div>

                    <!-- Row 1: 1 час -->
                    <div class="price-grid__cell price-grid__cell--gray">1 Час</div>
                    <div class="price-grid__cell price-grid__cell--gray price-grid__cell--value"><?= esc_html($format_table($price_in_1h)) ?></div>
                    <div class="price-grid__cell price-grid__cell--gray price-grid__cell--value"><?= esc_html($format_table($price_out_1h)) ?></div>

                    <!-- Row 2: 2 часа -->
                    <div class="price-grid__cell price-grid__cell--pink">2 Часа</div>
                    <div class="price-grid__cell price-grid__cell--pink price-grid__cell--value"><?= esc_html($format_table($price_in_2h)) ?></div>
                    <div class="price-grid__cell price-grid__cell--pink price-grid__cell--value"><?= esc_html($format_table($price_out_2h)) ?></div>

                    <!-- Row 3: Ночь -->
                    <div class="price-grid__cell price-grid__cell--gray">Ночь</div>
                    <div class="price-grid__cell price-grid__cell--gray price-grid__cell--value"><?= esc_html($format_table($price_in_night)) ?></div>
                    <div class="price-grid__cell price-grid__cell--gray price-grid__cell--value"><?= esc_html($format_table($price_out_night)) ?></div>
                </div>
            </div>

            <!-- Район, метро и проверено -->
            <section class="mt-8 mb-8" aria-label="Район, метро и проверка">
                <?php if (!empty($districts)) { ?>
                    <div class="text-neutral-700 text-lg">
                        <svg class="inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 22" width="24" height="24" fill="#404040" style="opacity:1;"><path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10"/><path d="M8 8a2 2 0 1 1 0-4a2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6a3 3 0 0 0 0 6"/></svg>
                        <span>Район:</span>
                        <?php
                            $district_links = [];
                            foreach ($districts as $d) {
                                $link = get_term_link($d);
                                if (is_wp_error($link)) continue;
                                $district_links[] = '<a class="hover:underline" href="' . esc_url($link) . '">' . esc_html($d->name) . '</a>';
                            }
                            echo implode(', ', $district_links);
                        ?>
                    </div>
                <?php } ?>

                <?php if (!empty($metro)) { ?>
                    <div class="text-neutral-700 text-lg mt-3">
                        <svg class="inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 22" width="24" height="24" fill="#404040" style="opacity:1;"><path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10"/><path d="M8 8a2 2 0 1 1 0-4a2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6a3 3 0 0 0 0 6"/></svg>
                        <span>Метро:</span>
                        <?php
                            $metro_links = [];
                            foreach ($metro as $m) {
                                $link = get_term_link($m);
                                if (is_wp_error($link)) continue;
                                $metro_links[] = '<a class="hover:underline" href="' . esc_url($link) . '">' . esc_html($m->name) . '</a>';
                            }
                            echo implode(', ', $metro_links);
                        ?>
                    </div>
                <?php } ?>

                <?php
                    $verify_raw = function_exists('get_field')
                        ? get_field('data_verify', get_the_ID())
                        : get_post_meta(get_the_ID(), 'data_verify', true);

                    if (!empty($verify_raw)) {
                        $verify_ts = is_numeric($verify_raw) ? (int)$verify_raw : strtotime((string)$verify_raw);
                        $verify_fmt = $verify_ts
                            ? date_i18n(get_option('date_format') ?: 'd.m.Y', $verify_ts)
                            : wp_strip_all_tags((string)$verify_raw);

                        echo '
                        <div class="w-full mt-5 text-sm text-neutral-700 flex items-center justify-between pr-4" style="padding-right: 10px;">
                            <div class="flex items-center gap-1.5">
                                <svg class="text-[#22c55e]" viewBox="0 0 20 24" width="26" height="22" fill="none" aria-hidden="true">
                                    <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="text-xl">Проверено: ' . esc_html($verify_fmt) . '</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 15 20" width="26" height="26" fill="#352222ff" style="opacity:1;">
                                    <path  d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M2 2a1 1 0 0 0-1 1v1h14V3a1 1 0 0 0-1-1zm13 3H1v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1z"/>
                                </svg> 
                                <span class="text-xl">' . esc_html($date_published) . '</span>
                            </div>
                        </div>';
                    }
                ?>
            </section>

            <!-- Контакты под ценой -->
            <?php ?>
                <button type="button" 
                        class="inline-flex items-center gap-2 px-4 py-3 text-white font-medium js-show-phone w-full justify-center"
                        style="background-color: #fdf2f4;"
                        data-phone="<?php echo esc_attr($phone); ?>">
                    <span class="text-neutral-700 capitalize text-2xl">Посмотреть телефон</span>
                </button>

                <script>
                    (function() {
                        const btn = document.querySelector('.js-show-phone');
                        if (!btn) return;
                        btn.addEventListener('click', function() {
                            const phone = this.dataset.phone || '-';
                            const span = this.querySelector('span');
                            
                            if (span) span.textContent = phone;
                            setTimeout(() => {
                                this.outerHTML = `<a href="tel:${phone.replace(/\D/g, '')}" class="${this.className}" style="${this.style.cssText}">${this.innerHTML}</a>`;
                            }, 50);
                        }, { once: true });
                    })();
                </script>
            <?php ?>
            <div class="grid grid-cols-2 gap-1 mt-1 mb-8 text-white">
                <?php if (!empty($tg_href)) { ?>
                    <button type="button" data-go="tg"
                        style="border: 0; cursor: pointer;"
                        class="flex items-center justify-center gap-2 py-3 bg-[#229ED9] hover:bg-[#1e88c7] font-medium text-2xl transition-all">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="currentColor">
                           <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .33z"/>
                        </svg>
                        Telegram
                    </button>
                <?php } ?>
                <?php if (!empty($wa_href)) { ?>
                    <button type="button" data-go="wa"
                        style="border: 0; cursor: pointer;"
                        class="flex items-center justify-center gap-2 py-3 bg-[#25D366] hover:bg-[#22c55e] font-medium text-2xl transition-all">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="currentColor">
                           <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884"/>
                        </svg>
                        WhatsApp
                    </button>
                <?php } ?>
            </div>

            <!-- Описание -->
            <section class="mt-8 mb-8" aria-label="Описание модели">
                <div class="w-full" style="background-color: #f2f2f2ff; padding: 30px 20px 20px">
                    <?php if (!empty($about)) { 
                        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        $is_bot = (bool) preg_match('/bot|crawl|spider|slurp|mediapartners-google|bingpreview|duckduckbot|baiduspider|yandex|ahrefs|semrush|screaming\s?frog|facebookexternalhit|telegrambot/i', $ua);
                        $uid = uniqid('desc_');
                    ?>
                        <div id="<?= $uid ?>_box" 
                             class="relative overflow-hidden transition-[max-height] duration-300 ease-in-out prose prose-neutral max-w-none text-xl text-center" 
                             style="<?= $is_bot ? 'max-height:none' : 'max-height:10rem' ?>">
                            
                            <style>
                                .desc-responsive-flex {
                                    display: flex;
                                    flex-direction: column;
                                    justify-content: space-between;
                                    align-items: center;
                                    gap: 16px;
                                    color: #404040;
                                }
                                @media (min-width: 768px) {
                                    .desc-responsive-flex {
                                        flex-direction: row;
                                        align-items: flex-end;
                                    }
                                }
                            </style>
                            <div class="desc-responsive-flex">
                                <div style="max-width: 600px; margin-bottom: 1rem">
                                    <?php echo wpautop(wp_kses_post($about)); ?>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="min-width: 60px; height: 46px; fill: #404040">
                                    <path d="M9.983 3v7.391c0 5.704-3.731 9.57-8.983 10.609l-.995-2.151c2.432-.917 3.995-3.638 3.995-5.849h-4v-10h9.983zm14.017 0v7.391c0 5.704-3.748 9.571-9 10.609l-.996-2.151c2.433-.917 3.996-3.638 3.996-5.849h-3.983v-10h9.983z"/>
                                </svg> 
                            </div>

                            <div id="<?= $uid ?>_fade" class="pointer-events-none absolute left-0 right-0 bottom-0 h-16" style="<?= $is_bot ? 'display:none' : 'background:linear-gradient(to bottom, rgba(242,242,242,0), #f2f2f2 80%)' ?>"></div>
                        </div>

                        <button id="<?= $uid ?>_btn"
                                class="mt-4 mx-auto flex items-center gap-2 text-[#e865a0] font-semibold hover:opacity-90 transition"
                                aria-expanded="<?= $is_bot ? 'true' : 'false' ?>"
                                <?= $is_bot ? 'hidden' : '' ?>>
                            <svg class="w-4 h-4 transition-transform duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M6 9l6 6 6-6" stroke-width="2" />
                            </svg>
                            <span data-label><?= $is_bot ? 'Свернуть' : 'Показать ещё' ?></span>
                        </button>

                        <script>
                            (function() {
                                var box = document.getElementById('<?= $uid ?>_box');
                                var fade = document.getElementById('<?= $uid ?>_fade');
                                var btn = document.getElementById('<?= $uid ?>_btn');
                                if (!box || !btn) return;

                                var collapsedMax = 10 * 16; // 10rem

                                if (box.scrollHeight <= collapsedMax + 10) {
                                    box.style.maxHeight = 'none';
                                    if (fade) fade.style.display = 'none';
                                    btn.style.display = 'none';
                                    return;
                                }

                                btn.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    var opened = btn.getAttribute('aria-expanded') === 'true';
                                    var arrow = btn.querySelector('svg');

                                    if (opened) {
                                        box.style.maxHeight = collapsedMax + 'px';
                                        if (fade) fade.style.display = '';
                                        btn.setAttribute('aria-expanded', 'false');
                                        btn.querySelector('[data-label]').textContent = 'Показать ещё';
                                        arrow.style.transform = 'rotate(0deg)';
                                    } else {
                                        box.style.maxHeight = box.scrollHeight + 'px';
                                        setTimeout(function() {
                                            box.style.maxHeight = 'none';
                                        }, 350);
                                        if (fade) fade.style.display = 'none';
                                        btn.setAttribute('aria-expanded', 'true');
                                        btn.querySelector('[data-label]').textContent = 'Свернуть';
                                        arrow.style.transform = 'rotate(180deg)';
                                    }
                                });
                            })();
                        </script>
                    <?php } else { ?>
                        <p class="text-neutral-600">Описание пока не добавлено.</p>
                    <?php } ?>
                </div>
            </section>

        </section>

        <?php if (!empty($services_terms_all)) : ?>
            <!-- Услуги -->
            <section class="mt-8 w-full lg:col-span-12 services-full" aria-label="Услуги модели">
                <style>
                    .services-full { grid-column: 1 / -1; }
                    .services-grid {
                        display: grid;
                        gap: 10px 24px;
                        grid-template-columns: repeat(1, minmax(0, 1fr));
                        list-style: none;
                        margin: 0;
                        padding: 0;
                    }
                    @media (min-width: 768px) {
                        .services-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                    }
                    @media (min-width: 1024px) {
                        .services-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
                    }
                    .service-item {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        font-size: 16px;
                        color: #404040;
                    }
                    .service-item .service-icon svg {
                        width: 16px;
                        height: 16px;
                    }
                    .service-item.is-missing { color: #9ca3af; }
                    .service-item.is-missing .service-name {
                        text-decoration: line-through;
                        text-decoration-thickness: 1px;
                        text-decoration-color: #d1d5db;
                    }
                </style>

                <h2 class="text-2xl font-semibold text-neutral-900 mb-4">Услуги</h2>
                <ul class="services-grid">
                    <?php foreach ($services_terms_all as $term) :
                        $service_name = $term->name ?? '';
                        $service_slug = $term->slug ?? '';
                        $service_id = (int)($term->term_id ?? 0);
                        $service_key = $service_name !== '' ? $services_lower($service_name) : '';
                        $is_available = (
                            ($service_id && isset($services_available_ids[$service_id])) ||
                            ($service_slug && isset($services_available_slugs[$services_lower($service_slug)])) ||
                            ($service_key && isset($services_available_names[$service_key]))
                        );
                    ?>
                        <li class="service-item <?php echo $is_available ? 'is-available' : 'is-missing'; ?>">
                            <span class="service-icon" aria-hidden="true">
                                <?php if ($is_available) : ?>
                                    <svg viewBox="0 0 20 20" fill="none">
                                        <path d="M4 10.5l4 4 8-9" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                <?php else : ?>
                                    <svg viewBox="0 0 20 20" fill="none">
                                        <path d="M5 5l10 10M15 5L5 15" stroke="#e865a0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                <?php endif; ?>
                            </span>
                            <?php
                                $service_link = get_term_link($term);
                                $service_link_is_valid = !is_wp_error($service_link);

                                if ($service_link_is_valid) {
                                    $service_path = (string) parse_url((string) $service_link, PHP_URL_PATH);
                                    $decoded_path = rawurldecode($service_path);

                                    // Не выводим ссылки с кириллицей/percent-encoding в slug.
                                    $has_encoded_bytes = (bool) preg_match('~%[0-9a-f]{2}~i', $service_path);
                                    $has_non_ascii = (bool) preg_match('~[^\x00-\x7F]~u', $decoded_path);
                                    if ($has_encoded_bytes || $has_non_ascii) {
                                        $service_link_is_valid = false;
                                    }
                                }

                                if (!$service_link_is_valid) {
                                    $pretty_slug_source = $service_slug !== ''
                                        ? rawurldecode((string) $service_slug)
                                        : (string) $service_name;
                                    $pretty_slug = function_exists('dosugmoskva24_slugify_latin')
                                        ? dosugmoskva24_slugify_latin($pretty_slug_source)
                                        : '';

                                    if ($pretty_slug === '' && $service_name !== '') {
                                        $pretty_slug = function_exists('dosugmoskva24_slugify_latin')
                                            ? dosugmoskva24_slugify_latin((string) $service_name)
                                            : '';
                                    }

                                    if ($pretty_slug === '') {
                                        $pretty_slug = sanitize_title($pretty_slug_source);
                                    }

                                    if ($pretty_slug !== '' && strpos($pretty_slug, '%') === false) {
                                        $service_link = home_url('/services/' . $pretty_slug . '/');
                                        $service_link_is_valid = true;
                                    }
                                }
                            ?>
                            <?php if ($service_link_is_valid) : ?>
                                <a class="service-name hover:underline" href="<?php echo esc_url($service_link); ?>"><?php echo esc_html($service_name); ?></a>
                            <?php else : ?>
                                <span class="service-name"><?php echo esc_html($service_name); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <style>
            @media (min-width: 1024px) {
                .reviews-full { grid-column: span 12; }
            }

            .model-reviews__header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                color: #404040;
            }

            .model-reviews__layout {
                display: grid;
                gap: 24px;
                align-items: start;
                max-width: 100%;
            }

            @media (min-width: 1024px) {
                .model-reviews__layout {
                    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
                }
            }

            .model-reviews__map {
                border-radius: 0;
                overflow: hidden;
                background: #f5f5f5;
                min-height: 340px;
                aspect-ratio: 4 / 3;
                border: 1px solid #e6e6e6;
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }

            .model-reviews__map iframe {
                width: 100%;
                height: 100%;
                max-width: 100%;
                border: 0;
                display: block;
            }

            .model-reviews__title {
                font-size: clamp(24px, 3.2vw, 34px);
                font-weight: 700;
                color: #404040;
                margin: 0;
            }

            .model-reviews__btn {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                background: #4b4b4b;
                color: #fff;
                padding: 15px 40px;
                font-weight: 600;
                font-size: 18px;
                border-radius: 0;
                line-height: 1;
                transition: background .2s ease;
            }

            .model-reviews__btn:hover {
                background: #3f3f3f;
            }

            .model-reviews__btn svg {
                width: 18px;
                height: 18px;
            }

            .model-reviews__hearts {
                margin-top: 8px;
                display: flex;
                gap: 6px;
                color: #e865a0;
            }

            .model-reviews__hearts svg {
                width: 26px;
                height: 26px;
                stroke: currentColor;
                fill: none;
                stroke-width: 2;
                stroke-linecap: round;
                stroke-linejoin: round;
            }

            .model-reviews__hearts svg.is-active {
                fill: currentColor;
            }

            .model-reviews #reviews {
                margin-top: 14px;
            }

            .model-reviews #reviews > h2 {
                display: none;
            }

            .model-reviews__empty {
                background: transparent;
                border: 0;
                padding: 0;
                font-size: clamp(22px, 3vw, 30px);
                font-weight: 600;
                color: #404040;
            }

            .model-reviews__modal {
                position: fixed;
                inset: 0;
                z-index: 10000;
                display: none;
            }

            .model-reviews__modal.is-open {
                display: block;
            }

            .model-reviews__backdrop {
                position: absolute;
                inset: 0;
                background: rgba(0, 0, 0, 0.35);
            }

            .model-reviews__dialog {
                position: relative;
                max-width: 560px;
                margin: 6vh auto;
                background: #fff;
                border-radius: 0;
                border: 1px solid #d1d5db;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                padding: 0;
                max-height: 88vh;
                overflow: auto;
            }

            .model-reviews__modal-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 0;
                padding: 16px 18px;
                border-bottom: 1px solid #e5e7eb;
            }

            .model-reviews__modal-head h3 {
                font-size: 20px;
                font-weight: 600;
                color: #404040;
                margin: 0;
            }

            .model-reviews__close {
                width: 32px;
                height: 32px;
                border-radius: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: #333;
                background: transparent;
                border: 1px solid #d1d5db;
            }

            .model-reviews__close svg {
                width: 18px;
                height: 18px;
            }

            .model-reviews__rules {
                margin: 16px 0 14px 0;
                padding-left: 20px;
                color: #6b6b6b;
                font-size: 14px;
                line-height: 1.5;
                list-style: decimal;
                list-style-position: inside;
            }

            .model-reviews__modal-body {
                padding: 0 18px 18px;
            }

            .model-reviews__form-card {
                margin-top: 6px;
            }

            .model-reviews__form-card .js-mr-form label,
            .model-reviews__form-card .js-mr-form .text-sm {
                color: #6b7280;
                font-weight: 500;
            }

            .model-reviews__form-card .js-mr-form textarea,
            .model-reviews__form-card .js-mr-form input[type="text"],
            .model-reviews__form-card .js-mr-form input[type="email"] {
                border: 1px solid #d1d5db;
                border-radius: 0;
                padding: 12px 12px;
                font-size: 14px;
                color: #374151;
                background: #fff;
                box-shadow: none;
            }

            .model-reviews__form-card .js-mr-form textarea::placeholder,
            .model-reviews__form-card .js-mr-form input::placeholder {
                color: #9ca3af;
            }

            .model-reviews__form-card .js-mr-form textarea {
                min-height: 120px;
            }

            .model-reviews__form-card .js-mr-form .mr-send {
                width: 100%;
                justify-content: center;
                border-radius: 0;
                padding: 12px 16px;
                font-weight: 600;
                background: #f26aa0;
                color: #fff;
            }

            .model-reviews__form-card .js-mr-form .mr-send:hover {
                background: #e95a93;
            }

            .model-reviews__form-card #mr-stars-<?php echo esc_attr($uid); ?>,
            .model-reviews__form-card .mr-stars {
                justify-content: center;
                gap: 8px;
            }

            @media (max-width: 640px) {
                .model-reviews__dialog {
                    width: calc(100% - 24px);
                    margin: 12px auto;
                    max-height: calc(100vh - 24px);
                }

                .model-reviews__modal-head {
                    padding: 14px 14px;
                }

                .model-reviews__modal-body {
                    padding: 0 14px 14px;
                }

                .model-reviews__rules {
                    margin: 12px 0 12px 0;
                }

                .model-reviews__close {
                    width: 28px;
                    height: 28px;
                }

                .model-reviews__form-card .js-mr-form textarea {
                    min-height: 96px;
                }

                .model-reviews__form-card .js-mr-form .mr-send {
                    padding: 12px 12px;
                }
            }

            .model-reviews,
            .model-reviews__content,
            .model-reviews__content #reviews article {
                color: #404040;
            }

            .model-reviews__content #reviews h3,
            .model-reviews__content #reviews time,
            .model-reviews__content #reviews .text-neutral-700,
            .model-reviews__content #reviews .text-black {
                color: #374151;
            }

            .model-reviews__form-card {
                border: 0;
                padding: 0;
                background: transparent;
                box-shadow: none;
            }

            .model-reviews__form-card h3 {
                display: none;
            }

            .model-reviews__form-card .mr-send {
                width: 100%;
                justify-content: center;
                border-radius: 8px;
                padding: 12px 16px;
                font-weight: 700;
            }

            .model-reviews__form-card label.star svg {
                fill: none;
                stroke: currentColor;
                stroke-width: 2;
                stroke-linecap: round;
                stroke-linejoin: round;
            }

            .model-reviews__form-card label.star.text-yellow-500 {
                color: #e865a0;
            }

            .model-reviews__form-card label.star.text-neutral-300 {
                color: #f3b4cc;
            }

            .model-reviews__form-card label.star.text-yellow-500 svg {
                fill: currentColor;
            }

            .model-reviews__form-card label.star.text-neutral-300 svg {
                fill: none;
            }

            .model-reviews__content #reviews .grid > div:last-child {
                max-height: 320px;
                overflow-y: auto;
                padding-right: 8px;
            }

            .model-reviews__content #reviews article {
                border: 0;
                border-radius: 0;
                box-shadow: none;
                background: #f5f5f5;
                padding: 14px 16px;
                margin-bottom: 12px;
            }

            .model-reviews__content #reviews article header {
                display: block;
                margin: 0 0 6px;
            }

            .model-reviews__content #reviews .mr-headline {
                font-size: 16px;
                font-weight: 600;
                color: #374151;
                line-height: 1.3;
            }

            .model-reviews__content #reviews .mr-headline .mr-date {
                font-weight: 500;
                color: #6b6b6b;
            }

            .model-reviews__content #reviews .mr-headline .mr-date-icon {
                display: inline-flex;
                align-items: center;
                vertical-align: middle;
                margin: 0 4px 0 2px;
                color: #6b6b6b;
            }

            .model-reviews__content #reviews .mr-headline .mr-date-icon svg {
                display: block;
                width: 14px;
                height: 14px;
            }

            .model-reviews__content #reviews article .mr-rating {
                margin-top: 6px;
                display: flex;
                gap: 6px;
                color: #ff5b9a;
            }

            .model-reviews__content #reviews article .mr-rating svg {
                width: 18px;
                height: 18px;
            }

            .model-reviews__content #reviews article .text-neutral-700 {
                margin-top: 6px;
                color: #374151;
            }

            .model-reviews__content #reviews article footer {
                display: none;
            }

            .model-reviews__content #reviews article:last-child {
                margin-bottom: 0;
            }

            .model-reviews__content #reviews [aria-label^="Рейтинг"] {
                color: #ff5b9a;
                gap: 6px;
            }

            .model-reviews__content #reviews [aria-label^="Рейтинг"] svg {
                width: 18px;
                height: 18px;
            }

            @media (max-width: 640px) {
                .model-reviews__header {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .model-reviews__btn {
                    width: 100%;
                    justify-content: center;
                }

                .model-reviews__layout {
                    overflow-x: hidden;
                }

                .model-reviews__content #reviews .grid > div:last-child {
                    max-height: 320px;
                }
            }
        </style>

        <section class="lg:col-span-7 lg:col-start-6 reviews-full" aria-label="Дополнительная информация">
            <!-- ===== Отзывы ===== -->
            <?php
            $reviews_available = function_exists('mr_render_reviews_block');
            $avg_rating_round = 0;
            if ($reviews_available) {
                global $wpdb;
                $avg_rating_raw = $wpdb->get_var($wpdb->prepare(
                    "SELECT AVG(CAST(ratingmeta.meta_value AS DECIMAL(10,2)))
                     FROM {$wpdb->postmeta} ratingmeta
                     INNER JOIN {$wpdb->postmeta} modelmeta ON ratingmeta.post_id = modelmeta.post_id
                     INNER JOIN {$wpdb->posts} p ON p.ID = ratingmeta.post_id
                     WHERE ratingmeta.meta_key = %s
                       AND modelmeta.meta_key = %s
                       AND modelmeta.meta_value = %d
                       AND p.post_type = %s
                       AND p.post_status = %s",
                    '_mr_rating',
                    '_mr_model_id',
                    (int) $id,
                    'model_review',
                    'publish'
                ));
                if ($avg_rating_raw !== null) {
                    $avg_rating_round = (int) round((float) $avg_rating_raw);
                }
            }
            ?>
            <section class="mt-10 model-reviews js-model-reviews" aria-label="Отзывы о модели">
                <div class="model-reviews__layout">
                    <div class="model-reviews__map">
                        <?php
                        $district_map = function_exists('get_field') ? trim((string) get_field('district', $id)) : '';
                        if (!$district_map && !empty($districts)) {
                            $district_map = (string) ($districts[0]->name ?? '');
                        }
                        $map_query = trim((string) $district_map);
                        if ($map_query !== '') {
                            $has_district_word = (stripos($map_query, 'район') !== false) || (stripos($map_query, 'р-н') !== false);
                            if (!$has_district_word) {
                                $map_query = 'район ' . $map_query;
                            }
                            if (stripos($map_query, 'моск') === false) {
                                $map_query .= ', Москва';
                            }
                        }
                        if ($map_query !== ''):
                            $map_src = 'https://yandex.ru/map-widget/v1/?mode=search&text=' . rawurlencode($map_query) . '&z=12';
                        ?>
                            <iframe
                                src="<?php echo esc_url($map_src); ?>"
                                title="<?php echo esc_attr('Карта: ' . $map_query); ?>"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"></iframe>
                        <?php else: ?>
                            <div class="p-4 text-sm text-neutral-600">Локация не указана.</div>
                        <?php endif; ?>
                    </div>

                    <div class="model-reviews__main">
                        <div class="model-reviews__header">
                            <h2 class="model-reviews__title">Отзывы</h2>
                            <?php if ($reviews_available): ?>
                                <button type="button" class="model-reviews__btn js-model-reviews-open">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M12 20h9" />
                                        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" />
                                    </svg>
                                    Оставить отзыв...
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="model-reviews__hearts" aria-hidden="true">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <svg class="<?php echo $i <= $avg_rating_round ? 'is-active' : ''; ?>" viewBox="0 0 24 24">
                                    <path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733C11.285 4.876 9.623 3.75 7.688 3.75 5.099 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                </svg>
                            <?php endfor; ?>
                        </div>
                        <div class="model-reviews__content">
                            <?php
                            if ($reviews_available) {
                                // можно переопределить заголовок через фильтр 'mr/reviews_heading'
                                mr_render_reviews_block($id /*, ['class' => ''] */);
                            } else {
                                echo '<p class="text-neutral-600">Блок отзывов недоступен.</p>';
                            }
                            ?>
                        </div>

                        <?php if ($reviews_available): ?>
                            <div class="model-reviews__modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="model-reviews-title">
                                <div class="model-reviews__backdrop js-model-reviews-close" tabindex="-1"></div>
                                <div class="model-reviews__dialog" role="document">
                                    <div class="model-reviews__modal-head">
                                        <h3 id="model-reviews-title">Ваш отзыв</h3>
                                        <button type="button" class="model-reviews__close js-model-reviews-close" aria-label="Закрыть">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M6 6L18 18M18 6L6 18" />
                                            </svg>
                                        </button>
                                    </div>
                                    <ol class="model-reviews__rules">
                                        <li>Напишите отзыв от 4 до 500 символов, избегайте спец. символы.</li>
                                        <li>Пройдите капчу.</li>
                                        <li>Поставьте оценку с помощью сердец (ниже поля для ввода текста).</li>
                                        <li>Для одного объявления можно оставить 1 отзыв в сутки.</li>
                                    </ol>
                                    <div class="model-reviews__modal-body"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

        </section>
    </article>

    <!-- ===== Лайтбокс ===== -->
    <div id="lb" class="fixed inset-0 z-[100] hidden">
        <div id="lb-overlay" class="absolute inset-0 bg-black/90 z-10"></div>
        <div class="absolute inset-0 z-20 flex items-center justify-center">
            <div class="relative w-full h-full">
                <div class="swiper js-lightbox h-full">
                    <div class="swiper-wrapper"></div>
                    <div class="swiper-button-prev !text-white"></div>
                    <div class="swiper-button-next !text-white"></div>
                    <div class="swiper-pagination"></div>
                </div>
                <button type="button" id="lb-close" aria-label="Закрыть"
                    class="absolute top-3 right-3 z-30 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ACCENT = '<?php echo esc_js($ACCENT); ?>';


            /* Swiper: левый слайдер и отзывы */
            if (window.Swiper) {
                var leftSliderEl = document.querySelector('.js-left-slider');
                if (leftSliderEl) {
                    var leftSlider = new Swiper('.js-left-slider', {
                        slidesPerView: 1,
                        spaceBetween: 0,
                        loop: <?php echo $images_count > 1 ? 'true' : 'false'; ?>,
                        pagination: <?php echo $images_count > 1 ? "{ el: '.js-left-slider .swiper-pagination', clickable: true }" : 'false'; ?>,
                        navigation: <?php echo $images_count > 1 ? "{ nextEl: '.js-left-slider .swiper-button-next', prevEl: '.js-left-slider .swiper-button-prev' }" : 'false'; ?>
                    });
                    var thumbs = Array.from(document.querySelectorAll('.js-thumb-btn'));
                    thumbs.forEach(function(btn, i) {
                        btn.addEventListener('click', function() {
                            if (leftSlider.slideToLoop) leftSlider.slideToLoop(i);
                            else leftSlider.slideTo(i);
                            thumbs.forEach(b => b.classList.remove('active'));
                            btn.classList.add('active');
                        });
                    });
                    if (thumbs.length) {
                        leftSlider.on('slideChange', function() {
                            var idx = leftSlider.realIndex ?? leftSlider.activeIndex;
                            thumbs.forEach((b, k) => b.classList.toggle('active', k === idx));
                        });
                        thumbs[0].classList.add('active');
                    }
                }
                if (document.querySelector('.js-reviews-slider')) {
                    new Swiper('.js-reviews-slider', {
                        slidesPerView: 1,
                        spaceBetween: 20,
                        loop: true,
                        autoplay: {
                            delay: 5000,
                            disableOnInteraction: false
                        },
                        breakpoints: {
                            768: {
                                slidesPerView: 2,
                                spaceBetween: 24
                            },
                            1024: {
                                slidesPerView: 3,
                                spaceBetween: 30
                            }
                        },
                        navigation: {
                            nextEl: '.js-reviews-slider ~ .swiper-button-next, .js-reviews-slider .swiper-button-next',
                            prevEl: '.js-reviews-slider ~ .swiper-button-prev, .js-reviews-slider .swiper-button-prev'
                        },
                        pagination: {
                            el: '.js-reviews-slider ~ .swiper-pagination, .js-reviews-slider .swiper-pagination',
                            clickable: true
                        }
                    });
                }
            }

            /* Reviews modal */
            var reviewsSection = document.querySelector('.js-model-reviews');
            if (reviewsSection) {
                var openBtn = reviewsSection.querySelector('.js-model-reviews-open');
                var modal = reviewsSection.querySelector('.model-reviews__modal');
                var modalBody = reviewsSection.querySelector('.model-reviews__modal-body');
                var closeBtns = reviewsSection.querySelectorAll('.js-model-reviews-close');
                var form = reviewsSection.querySelector('.js-mr-form');
                var formCard = form ? form.closest('.bg-white') : null;

                if (formCard && modalBody) {
                    formCard.classList.add('model-reviews__form-card');
                    var formCol = formCard.parentElement;
                    modalBody.appendChild(formCard);
                    if (formCol && formCol.children.length === 0) {
                        formCol.remove();
                    }
                }

                var emptyCard = reviewsSection.querySelector('#reviews article');
                if (emptyCard && emptyCard.textContent && emptyCard.textContent.indexOf('Пока нет отзывов') !== -1) {
                    emptyCard.textContent = 'Отзывов пока нет';
                    emptyCard.classList.add('model-reviews__empty');
                }

                if (formCard) {
                    var heartSvg = '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                        '<path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733C11.285 4.876 9.623 3.75 7.688 3.75 5.099 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>' +
                        '</svg>';
                    formCard.querySelectorAll('label.star').forEach(function(label) {
                        label.innerHTML = heartSvg;
                    });
                }

                var ratingBox = reviewsSection.querySelectorAll('#reviews [aria-label^="Рейтинг"] svg');
                if (ratingBox.length) {
                    ratingBox.forEach(function(icon) {
                        var isFilled = icon.classList.contains('text-yellow-500');
                        var fillAttr = isFilled ? 'currentColor' : 'none';
                        var heartSmall = '<svg viewBox="0 0 24 24" fill="' + fillAttr + '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                            '<path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733C11.285 4.876 9.623 3.75 7.688 3.75 5.099 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>' +
                            '</svg>';
                        icon.outerHTML = heartSmall;
                    });
                }

                var reviewCards = reviewsSection.querySelectorAll('#reviews article');
                if (reviewCards.length) {
                    reviewCards.forEach(function(card) {
                        if (!card || card.dataset.mrStyled === '1') return;
                        card.dataset.mrStyled = '1';
                        if (card.classList.contains('model-reviews__empty')) return;

                        var header = card.querySelector('header');
                        var footer = card.querySelector('footer');
                        var timeEl = footer ? footer.querySelector('time') : null;
                        var nameEl = header ? header.querySelector('h3') : null;

                        if (header && nameEl && timeEl) {
                            var headline = document.createElement('div');
                            headline.className = 'mr-headline';

                            var nameSpan = document.createElement('span');
                            nameSpan.className = 'mr-name';
                            nameSpan.textContent = (nameEl.textContent || '').trim();

                            var dateSpan = document.createElement('span');
                            dateSpan.className = 'mr-date';
                            dateSpan.textContent = (timeEl.textContent || '').trim();

                            var calendarSpan = document.createElement('span');
                            calendarSpan.className = 'mr-date-icon';
                            calendarSpan.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 15 20" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M2 2a1 1 0 0 0-1 1v1h14V3a1 1 0 0 0-1-1zm13 3H1v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1z"/></svg>';

                            headline.appendChild(nameSpan);
                            headline.appendChild(document.createTextNode(' / '));
                            headline.appendChild(calendarSpan);
                            headline.appendChild(dateSpan);

                            nameEl.replaceWith(headline);
                        }

                        if (header) {
                            var ratingWrap = header.querySelector('[aria-label^="Рейтинг"]');
                            if (ratingWrap) {
                                var parentWrap = ratingWrap.parentElement;
                                if (parentWrap && parentWrap !== header && parentWrap.childElementCount === 1) {
                                    ratingWrap = parentWrap;
                                }
                                ratingWrap.classList.add('mr-rating');
                                header.insertAdjacentElement('afterend', ratingWrap);
                            }
                        }

                        if (footer) footer.remove();
                    });
                }

                function openModal() {
                    if (!modal) return;
                    modal.classList.add('is-open');
                    modal.setAttribute('aria-hidden', 'false');
                    document.documentElement.classList.add('overflow-hidden');
                    var firstField = modal.querySelector('input, textarea, select, button');
                    if (firstField) firstField.focus();
                }

                function closeModal() {
                    if (!modal) return;
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                    document.documentElement.classList.remove('overflow-hidden');
                    if (openBtn) openBtn.focus();
                }

                if (openBtn && modal) {
                    openBtn.addEventListener('click', openModal);
                }

                closeBtns.forEach(function(btn) {
                    btn.addEventListener('click', closeModal);
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
                        closeModal();
                    }
                });
            }

            /* Лайтбокс */
            var LB_ITEMS = <?php echo wp_json_encode($lb_items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
            var lb = document.getElementById('lb');
            var lbWrap = document.querySelector('.js-lightbox .swiper-wrapper');

            function ensureVideoFrame(slide) {
                if (slide.querySelector('.lb-video-iframe')) return;
                var src = slide.dataset.src || '';
                if (!src) return;
                var poster = slide.querySelector('.lb-video-poster');
                if (poster) poster.remove();
                var box = document.createElement('div');
                box.className = 'lb-video-iframe';
                if (slide.dataset.type === 'mp4') {
                    box.innerHTML = '<video src="' + src + '" controls autoplay playsinline class="w-screen h-screen max-w-screen max-h-screen object-contain"></video>';
                } else {
                    var url = src + (src.indexOf('?') >= 0 ? '&' : '?') + 'autoplay=1';
                    box.innerHTML = '<iframe src="' + url + '" frameborder="0" allow="autoplay; fullscreen; encrypted-media" allowfullscreen referrerpolicy="no-referrer-when-downgrade" class="w-screen h-screen"></iframe>';
                }
                slide.appendChild(box);
            }

            if (lbWrap && LB_ITEMS.length) {
                LB_ITEMS.forEach(function(item) {
                    var slide = document.createElement('div');
                    slide.className = 'swiper-slide';
                    slide.dataset.type = item.type;
                    slide.dataset.src = item.src || '';
                    slide.dataset.poster = item.poster || '';
                    if (item.type === 'video' || item.type === 'mp4') {
                        slide.innerHTML =
                            '<div class="lb-video-poster">' +
                            (item.poster ? '<img src="' + item.poster + '" alt="' + item.alt + '" class="max-w-screen max-h-screen object-contain">' : '<div class="w-screen h-screen bg-black"></div>') +
                            '<span class="play"><svg width="40" height="40" viewBox="0 0 24 24" fill="#fff"><path d="M8 5v14l11-7-11-7z"/></svg></span>' +
                            '</div>';
                    } else {
                        slide.innerHTML = '<img src="' + (item.src || '') + '" alt="' + item.alt + '" class="max-w-screen max-h-screen object-contain">';
                    }
                    lbWrap.appendChild(slide);
                });
            }

            var lightbox = new Swiper('.js-lightbox', {
                loop: LB_ITEMS.length > 1,
                navigation: {
                    nextEl: '.js-lightbox .swiper-button-next',
                    prevEl: '.js-lightbox .swiper-button-prev'
                },
                pagination: {
                    el: '.js-lightbox .swiper-pagination',
                    clickable: true
                },
                keyboard: {
                    enabled: true
                },
                on: {
                    slideChange: function() {
                        document.querySelectorAll('.js-lightbox .swiper-slide').forEach(function(slide) {
                            if ((slide.dataset.type === 'video' || slide.dataset.type === 'mp4') && !slide.classList.contains('swiper-slide-active')) {
                                var v = slide.querySelector('.lb-video-iframe');
                                if (v) {
                                    v.remove();
                                    if (!slide.querySelector('.lb-video-poster')) {
                                        var poster = slide.dataset.poster || '';
                                        var altTxt = slide.dataset.alt || '';
                                        var wrap = document.createElement('div');
                                        wrap.className = 'lb-video-poster';
                                        wrap.innerHTML = (poster ?
                                                '<img src="' + poster + '" alt="' + altTxt + '" class="max-w-screen max-h-screen object-contain">' :
                                                '<div class="w-screen h-screen bg-black"></div>') +
                                            '<span class="play"><svg width="40" height="40" viewBox="0 0 24 24" fill="#fff"><path d="M8 5v14l11-7-11-7z"/></svg></span>';
                                        slide.appendChild(wrap);

                                    }
                                }
                            }
                        });
                        var active = document.querySelector('.js-lightbox .swiper-slide-active');
                        if (active && (active.dataset.type === 'video' || active.dataset.type === 'mp4')) ensureVideoFrame(active);
                    }
                }
            });

            function openLightbox(index) {
                if (!LB_ITEMS.length) return;
                lb.classList.remove('hidden');
                document.documentElement.classList.add('overflow-hidden');
                var slideIndex = parseInt(index) || 0;
                if (lightbox.slideToLoop) lightbox.slideToLoop(slideIndex, 0);
                else lightbox.slideTo(slideIndex, 0);
                setTimeout(function() {
                    var active = document.querySelector('.js-lightbox .swiper-slide-active');
                    if (active && (active.dataset.type === 'video' || active.dataset.type === 'mp4')) ensureVideoFrame(active);
                }, 100);
            }

            function closeLightbox() {
                lb.classList.add('hidden');
                document.documentElement.classList.remove('overflow-hidden');
                document.querySelectorAll('.lb-video-iframe').forEach(function(n) {
                    n.remove();
                });
            }
            document.querySelectorAll('.js-open-lightbox').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    openLightbox(parseInt(btn.getAttribute('data-idx')) || 0);
                });
            });
            document.getElementById('lb-close')?.addEventListener('click', closeLightbox);
            document.getElementById('lb-overlay')?.addEventListener('click', closeLightbox);
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !lb.classList.contains('hidden')) closeLightbox();
            });

            /* Звёзды */
            var starsBox = document.getElementById('mr-stars');
            if (starsBox) {
                var labels = Array.from(starsBox.querySelectorAll('label.star'));

                function paint(r) {
                    labels.forEach(function(l) {
                        var v = parseInt(l.dataset.val, 10);
                        l.classList.toggle('text-yellow-500', v <= r);
                        l.classList.toggle('text-gray-300', v > r);
                    });
                }
                labels.forEach(function(l) {
                    var v = parseInt(l.dataset.val, 10);
                    l.addEventListener('mouseenter', function() {
                        paint(v);
                    });
                    l.addEventListener('click', function() {
                        paint(v);
                        document.getElementById('mr-star-' + v).checked = true;
                    });
                });
                starsBox.addEventListener('mouseleave', function() {
                    var c = document.querySelector('#model-review-form input[name="rating"]:checked');
                    paint(c ? parseInt(c.value, 10) : 0);
                });
            }

            /* AJAX форма */
            var reviewForm = document.getElementById('model-review-form');
            if (reviewForm) {
                var msgEl = document.getElementById('mr-msg');
                reviewForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    if (msgEl) msgEl.textContent = '';
                    var submitBtn = reviewForm.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.classList.add('opacity-50');
                    }
                    try {
                        var fd = new FormData(reviewForm);
                        var res = await fetch(reviewForm.action, {
                            method: 'POST',
                            body: fd,
                            credentials: 'same-origin'
                        });
                        var out = await res.json();
                        if (out && out.success) {
                            if (msgEl) {
                                msgEl.classList.remove('text-red-600');
                                msgEl.classList.add('text-green-600');
                                msgEl.textContent = (out.data && out.data.message) ? out.data.message : 'Спасибо! Отзыв отправлен на модерацию.';
                            }
                            reviewForm.reset();
                            if (typeof paint === 'function') paint(0);
                        } else {
                            if (msgEl) {
                                msgEl.classList.remove('text-green-600');
                                msgEl.classList.add('text-red-600');
                                msgEl.textContent = (out && out.data && out.data.message) ? out.data.message : 'Ошибка отправки отзыва.';
                            }
                        }
                    } catch (err) {
                        if (msgEl) {
                            msgEl.classList.remove('text-green-600');
                            msgEl.classList.add('text-red-600');
                            msgEl.textContent = 'Сетевая ошибка. Попробуйте позже.';
                        }
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('opacity-50');
                    }
                });
            }
        });
    </script>

    <script>
        (function() {
            const KEY = 'favModels'; // единый ключ

            // Нормализация: сделать из чего угодно -> [int,int]
            function normalize(v) {
                let ids = [];
                try {
                    const parsed = typeof v === 'string' ? JSON.parse(v) : v;
                    if (Array.isArray(parsed)) {
                        for (const it of parsed) {
                            if (it && typeof it === 'object' && 'id' in it) {
                                const id = parseInt(it.id, 10);
                                if (id) ids.push(id);
                            } else {
                                const id = parseInt(it, 10);
                                if (id) ids.push(id);
                            }
                        }
                    } else {
                        const id = parseInt(parsed, 10);
                        if (id) ids.push(id);
                    }
                } catch (e) {
                    if (typeof v === 'string') {
                        ids = v.split(/[\s,]+/).map(x => parseInt(x, 10)).filter(Boolean);
                    }
                }
                return Array.from(new Set(ids));
            }

            // Миграция со старых ключей (если были)
            (function migrate() {
                const keys = ['favModels', 'favModelsV1', 'favorites', 'favoritesModels'];
                const collected = new Set();
                for (const k of keys) {
                    const raw = localStorage.getItem(k);
                    if (!raw) continue;
                    for (const id of normalize(raw)) collected.add(id);
                    if (k !== KEY) localStorage.removeItem(k);
                }
                localStorage.setItem(KEY, JSON.stringify(Array.from(collected)));
            })();

            function getList() {
                return normalize(localStorage.getItem(KEY) || '[]');
            }

            function saveList(ids) {
                localStorage.setItem(
                    KEY,
                    JSON.stringify(Array.from(new Set(ids.map(x => parseInt(x, 10)).filter(Boolean))))
                );
                document.dispatchEvent(new CustomEvent('favorites:changed', {
                    detail: {
                        ids: getList()
                    }
                }));
            }

            function has(id) {
                id = parseInt(id, 10);
                return getList().includes(id);
            }

            function add(id) {
                id = parseInt(id, 10);
                const list = getList();
                if (!list.includes(id)) {
                    list.push(id);
                    saveList(list);
                }
            }

            function remove(id) {
                id = parseInt(id, 10);
                saveList(getList().filter(x => x !== id));
            }

            // UI
            const btn = document.getElementById('fav-toggle');
            if (!btn) return; // теперь return внутри функции — ок

            const id = parseInt(btn.dataset.id, 10);

            function paint() {
                const active = has(id);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                btn.classList.toggle('bg-rose-500', active);
                btn.classList.toggle('text-white', active);
                btn.classList.toggle('border-rose-500', active);
                btn.classList.toggle('hover:bg-rose-600', active);
                const span = btn.querySelector('span');
                if (span) span.textContent = active ? 'В избранном' : 'В избранное';
                const svg = btn.querySelector('svg');
                if (svg) svg.setAttribute('fill', active ? 'currentColor' : 'none');
            }

            btn.addEventListener('click', function() {
                if (has(id)) remove(id);
                else add(id);
                paint();
            });

            // Первичная отрисовка состояния
            paint();
        })();
    </script>



</main>
<?php get_footer();
