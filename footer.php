<?php

/**
 * The template for displaying the footer
 */
if (!defined('ABSPATH')) exit;

// === ОСНОВНЫЕ ПЕРЕМЕННЫЕ ===
$year      = (int) date('Y');
$site_name = trim((string) get_bloginfo('name'));
if ($site_name === '' || preg_match('~dosugmoskva24~iu', $site_name)) {
    $site_name = 'dosugmoskva24';
}
$home_url  = home_url('/');
$logo_url  = get_stylesheet_directory_uri() . '/assets/icons/logo.png';

// === КОНТАКТЫ (С ЛОГИКОЙ DEEP CONTACTS) ===
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

// Контекст "Дешевые"
$is_cheap_page = is_page('deshevyye-prostitutki');
$is_cheap_model = is_singular('models') && has_term('deshevyye-prostitutki', 'price_tax');
$use_cheap_contacts = ($is_cheap_page || $is_cheap_model);

$__final_tg = '';
$__final_wa = '';

if ($use_cheap_contacts) {
    $__final_tg = $__mr_norm_tg(get_theme_mod('contact_telegram_5'));
    $__final_wa = $__mr_norm_wa(get_theme_mod('contact_whatsapp_5'));
}

// Обычная логика
if (empty($__final_tg) && empty($__final_wa)) {
    $__tg_variants = [];
    $__wa_variants = [];

    $main_tg = get_theme_mod('contact_telegram');
    $main_wa = get_theme_mod('contact_whatsapp');
    if (!empty($main_tg)) $__tg_variants[] = $__mr_norm_tg($main_tg);
    if (!empty($main_wa)) $__wa_variants[] = $__mr_norm_wa($main_wa);

    for ($i = 1; $i <= 4; $i++) {
        $tg = get_theme_mod("contact_telegram_$i");
        $wa = get_theme_mod("contact_whatsapp_$i");
        if (!empty($tg)) $__tg_variants[] = $__mr_norm_tg($tg);
        if (!empty($wa)) $__wa_variants[] = $__mr_norm_wa($wa);
    }

    $__final_tg = !empty($__tg_variants) ? $__tg_variants[array_rand($__tg_variants)] : '';
    $__final_wa = !empty($__wa_variants) ? $__wa_variants[array_rand($__wa_variants)] : '';
}

$tg_user_handle   = $__final_tg !== '' ? $__final_tg : 'dosugmoskva24';
$wa_number_digits = $__final_wa !== '' ? $__final_wa : '79874684644';
$tg_channel_handle = ltrim((string) get_theme_mod('contact_telegram_channel', 'Telegram_Channel_Name'), '@');

// === [ЗАЩИТА] КОДИРОВАНИЕ ДЛЯ ФУТЕРА ===
// Кодируем ссылки для блока ссылок в самом футере
$enc_tg_user    = base64_encode('https://t.me/' . $tg_user_handle);
$enc_tg_channel = base64_encode('https://t.me/' . $tg_channel_handle);
$enc_wa_number  = base64_encode('https://wa.me/' . $wa_number_digits);


unset($__mr_norm_tg, $__mr_norm_wa, $__tg_variants, $__wa_variants, $__final_tg, $__final_wa, $i, $is_cheap_page, $is_cheap_model, $use_cheap_contacts);


// === НАВИГАЦИЯ В ФУТЕРЕ ===
$nav_links = [
    ['label' => 'Политика конфиденциальности', 'slug' => 'politika-konfidentsialnosti'],
    ['label' => 'Условия пользования',         'slug' => 'usloviya-polzovaniya'],
    ['label' => 'Карта сайта',                 'slug' => 'sitemap'],
    ['label' => 'Метро',              'slug' => 'metro'],
    ['label' => 'Районы',             'slug' => 'rajony'],
    ['label' => 'Контакты',                    'slug' => 'kontakty'],
    ['label' => 'О сайте',                     'slug' => 'o-sajte'],
    ['label' => 'Отзывы',                      'slug' => 'otzyvy'],
    ['label' => 'Все услуги',                  'slug' => 'services'],
    ['label' => 'Faq',                        'slug' => 'faq'],
    ['label' => 'Блог',                        'slug' => 'blog'],
];

$normalize_slug = static function ($link) {
    $raw = '';
    if (!empty($link['slug'])) {
        $raw = (string)$link['slug'];
    } elseif (!empty($link['url'])) {
        $u = wp_parse_url($link['url']);
        $raw = $u['path'] ?? '';
    }
    return trim(strtolower($raw), '/');
};

$legal_slugs_map = ['politika-konfidentsialnosti', 'usloviya-polzovaniya'];

$legal_links = [];
$main_links  = [];

if (!empty($nav_links) && is_array($nav_links)) {
    foreach ($nav_links as $link) {
        $label = trim($link['label'] ?? '');
        if ($label === '') continue;
        $norm = $normalize_slug($link);
        if (in_array($norm, $legal_slugs_map, true)) {
            $legal_links[] = $link;
        } else {
            $main_links[]  = $link;
        }
    }
}

$build_url = static function ($link) {
    if (!empty($link['url'])) return esc_url($link['url']);
    $slug = trim($link['slug'] ?? '', '/');
    return esc_url(home_url("/{$slug}"));
};

$site_name = $site_name ?? trim((string) get_bloginfo('name'));
if ($site_name === '' || preg_match('~dosugmoskva24~iu', $site_name)) {
    $site_name = 'dosugmoskva24';
}
$year      = $year ?? date_i18n('Y');

?>
<footer class="site-footer">
    <div class="site-footer__top">
        <?php if (!empty($main_links)): ?>
            <nav class="site-footer__nav" aria-label="Навигация">
                <?php foreach ($main_links as $link): ?>
                    <a href="<?= $build_url($link) ?>"><?= esc_html($link['label'] ?? '') ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </div>

    <div class="site-footer__bottom">
        <p>© <?= esc_html($site_name) ?>, <?= esc_html($year) ?></p>
        <?php
            $contact_links = [];
            if (!empty($tg_user_handle)) $contact_links[] = ['label' => 'Telegram', 'enc' => $enc_tg_user, 'go' => 'tg'];
            if (!empty($tg_channel_handle)) $contact_links[] = ['label' => 'Telegram Channel', 'enc' => $enc_tg_channel];
            if (!empty($wa_number_digits)) $contact_links[] = ['label' => 'WhatsApp', 'enc' => $enc_wa_number, 'go' => 'wa'];
        ?>
        <?php if (!empty($contact_links)): ?>
            <p class="site-footer__contacts">
                <span>Связаться:</span>
                <?php foreach ($contact_links as $idx => $c): ?>
                    <a href="javascript:void(0);"
                       data-enc="<?= esc_attr($c['enc']) ?>"
                       <?php if (!empty($c['go'])): ?>data-go="<?= esc_attr($c['go']) ?>"<?php endif; ?>
                       class="protected-contact"><?= esc_html($c['label']) ?></a>
                    <?php if ($idx < count($contact_links) - 1): ?>
                        <span class="site-footer__dot">•</span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
        

        <?php if (!empty($legal_links)): ?>
            <div class="site-footer__links">
                <?php foreach ($legal_links as $idx => $link): ?>
                    <a href="<?= $build_url($link) ?>"><?= esc_html($link['label'] ?? '') ?></a>
                    <?php if ($idx < count($legal_links) - 1): ?>
                        <span class="site-footer__dot">•</span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</footer>

<?php
// === Логика ссылок и активной страницы ===
$home_link = user_trailingslashit(home_url('/'));
$current_url = user_trailingslashit(home_url(add_query_arg([], $GLOBALS['wp']->request)));
$is_home_active = ($home_link === $current_url);

// === Логика контактов (как делали ранее) ===
// 1. Определяем: это "Дешевые"?
$is_cheap_context = (is_page('deshevyye-prostitutki') || has_term('deshevyye-prostitutki', 'price_tax', get_the_ID()));

// 2. Выбираем контакты
if ($is_cheap_context) {
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

// 3. Нормализация
$tg_clean = trim((string)$raw_tg);
$tg_clean = preg_replace('~^https?://t\.me/~i', '', $tg_clean);
$tg_clean = ltrim($tg_clean, '@');
$tg_clean = preg_replace('~[^a-z0-9_]+~i', '', $tg_clean);
$wa_clean = preg_replace('~\D+~', '', (string)$raw_wa);

// === [ЗАЩИТА] КОДИРОВАНИЕ ДЛЯ ПЛАВАЮЩЕЙ ПАНЕЛИ ===
$enc_float_tg = base64_encode('https://t.me/' . $tg_clean);
$enc_float_wa = base64_encode('https://wa.me/' . $wa_clean);
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const scrollBtn = document.querySelector('[data-scroll-btn]');
        if (!scrollBtn) return;

        // 1. Логика появления кнопки при скролле
        function toggleScrollBtn() {
            // Если прокрутили больше 20px, показываем кнопку
            if (window.scrollY > 20) {
                scrollBtn.classList.remove('hidden');
            } else {
                scrollBtn.classList.add('hidden');
            }
        }

        // Слушаем событие скролла (passive: true для производительности)
        window.addEventListener('scroll', toggleScrollBtn, {
            passive: true
        });
        // Запускаем один раз при загрузке (если страница обновлена посередине)
        toggleScrollBtn();

        // 2. Логика клика (плавный скролл наверх)
        scrollBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    });
</script>

<?php wp_footer(); ?>

</body>
