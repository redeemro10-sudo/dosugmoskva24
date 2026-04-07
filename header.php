<?php

/**
 * The header for our theme
 */

// ===== SEO: генерация title/descr =====
$post_id   = get_queried_object_id();
$post_type = get_post_type($post_id);

$title     = '';
$descr     = '';
$canonical = ($post_id ? get_permalink($post_id) : home_url('/'));

// Пробрасываем в шаблоны
set_query_var('seo_title', $title);
set_query_var('seo_descr', $descr);
$GLOBALS['seo_title'] = $title;
$GLOBALS['seo_descr'] = $descr;
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <?php get_template_part('components/seo-head'); ?>
    <?php if (!empty($keywords)): ?>
        <meta name="keywords" content="<?php echo esc_attr($keywords); ?>" />
    <?php endif; ?>
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/icons/favicon-32x32.png'); ?>" />
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/icons/favicon-16x16.png'); ?>" />
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/icons/apple-touch-icon.png'); ?>" />
    <meta name="yandex-verification" content="635b0832d5683f81" />
    <?php if ($post_type === 'models' && is_singular('models') && !empty($og_image_alt)): ?>
        <meta property="og:image:alt" content="<?php echo esc_attr($og_image_alt); ?>" />
    <?php endif; ?>
    <?php wp_head(); ?>
    <?php get_template_part('json-ld/index'); ?>
    <style>
        :root {
            --ea-accent: #e865a0;
            /* розовый как на сайте */
            --ea-accent-2: #ff4b88;
            /* чуть светлее для градиента/hover */
            --ea-bg: #ffffff;
            /* белый фон карточки */
            --ea-text: #111827;
            /* почти чёрный текст (gray-900) */
            --ea-subtext: #4b5563;
            /* серый для описаний */
            --ea-border: rgba(17, 24, 39, .08);
            --ea-shadow: 0 14px 35px rgba(0, 0, 0, .12);
            --ea-badge-bg: rgba(232,101,160, .08);
            --ea-badge-brd: rgba(232,101,160, .25);
            --ea-badge-text: #e865a0;
        }

        .ea-tg-pop-overlay {
            position: fixed;
            inset: 0;
            display: none;
            background: rgba(0, 0, 0, .20);
            /* слегка затемняем только на мобилке (включается скриптом) */
            z-index: 9998;
            backdrop-filter: blur(2px);
        }

        .ea-tg-pop {
            position: fixed;
            z-index: 9999;
            display: none;
            max-width: 580px;
            width: calc(100vw - 24px);
            background: var(--ea-bg);
            color: var(--ea-text);
            border: 1px solid var(--ea-border);
            box-shadow: var(--ea-shadow);
            border-radius: 16px;
            padding: 16px;
            gap: 12px;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity .3s ease, transform .3s ease;
        }

        .ea-tg-pop.ea-show {
            opacity: 1;
            transform: translateY(0);
        }

        /* мобилка: снизу по центру */
        @media (max-width: 767px) {
            .ea-tg-pop {
                left: 50%;
                transform: translateX(-50%) translateY(10px);
                margin-bottom: 14px;
            }

            .ea-tg-pop.ea-show {
                transform: translateX(-50%) translateY(0);
            }
        }

        /* ПК: снизу слева */
        @media (min-width: 768px) {
            .ea-tg-pop {
                left: 16px;
                bottom: 16px;
                width: 380px;
            }
        }

        .ea-tg-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 2px;
        }

        .ea-tg-title {
            font-weight: 800;
            font-size: 16px;
            line-height: 1.2;
            margin: 0;
            color: var(--ea-text);
        }

        .ea-tg-close {
            margin-left: auto;
            width: 30px;
            height: 30px;
            border-radius: 10px;
            border: 1px solid var(--ea-border);
            background: #fff;
            color: #111827;
            display: grid;
            place-items: center;
            cursor: pointer;
            transition: background .2s ease, transform .08s ease, border-color .2s ease;
        }

        .ea-tg-close:hover {
            background: #f9fafb;
            border-color: rgba(17, 24, 39, .15);
        }

        .ea-tg-close:active {
            transform: scale(.96);
        }

        .ea-tg-body {
            font-size: 14px;
            line-height: 1.5;
            color: var(--ea-subtext);
        }

        .ea-tg-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
        }

        .ea-tg-join {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 44px;
            padding: 0 14px;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(180deg, var(--ea-accent), var(--ea-accent-2));
            color: #fff;
            font-weight: 800;
            letter-spacing: .2px;
            text-decoration: none;
            cursor: pointer;
            transition: filter .2s ease, transform .08s ease, box-shadow .2s ease;
            box-shadow: 0 8px 18px rgba(232,101,160, .28);
        }

        .ea-tg-join:hover {
            filter: brightness(.98);
        }

        .ea-tg-join:active {
            transform: translateY(1px);
        }

        .ea-tg-join svg {
            width: 18px;
            height: 18px;
        }

        .ea-tg-later {
            height: 44px;
            padding: 0 14px;
            border-radius: 12px;
            border: 1px solid var(--ea-border);
            background: #fff;
            color: #111827;
            cursor: pointer;
            font-weight: 600;
            transition: background .2s ease, transform .08s ease, border-color .2s ease;
            white-space: nowrap;
        }

        .ea-tg-later:hover {
            background: #f9fafb;
            border-color: rgba(17, 24, 39, .15);
        }

        .ea-tg-later:active {
            transform: translateY(1px);
        }

        .ea-tg-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--ea-badge-bg);
            color: var(--ea-badge-text);
            font-weight: 800;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid var(--ea-badge-brd);
        }

        .ea-tg-badge svg {
            width: 14px;
            height: 14px;
        }
    </style>
</head>

<body <?php body_class('bg-white mx-auto !mb-0 !pb-0'); ?>>
    <?php wp_body_open(); ?>

    <?php
    if (!defined('ABSPATH')) exit;

    /** Бренд */
    $site_name = trim((string) get_bloginfo('name'));
    if ($site_name === '' || preg_match('~dosugmoskva24~iu', $site_name)) {
        $site_name = 'dosugmoskva24';
    }
    $logo_url  = get_stylesheet_directory_uri() . '/assets/icons/logo.png';

    /** * МЕНЮ 
     * Структура полностью соответствует изображению.
     * Ключ массива используется для поиска иконки.
     */
$menu = [
    'escort' => ['Эскорт', '/escort'], // Добавлен слэш для корректной работы из любого раздела
    'novye'         => ['Новые', '/novye'],
    's_video'       => ['С видео', '/s-video'],
    
    // Выпадающий список "Доступность"
    'accessibility' => [
        'label' => 'Доступность',
        'sub_menu' => [
            'outcall' => ['Выезд', '/prostitutki-na-vyyezd'],
            'incall'  => ['Прием', '/prostitutki-priyem'],
        ]
    ],

    // Выпадающий список "Цена"
    'price_filter' => [
        'label' => 'Цена',
        'sub_menu' => [
            'elite' => ['Элитные', '/elitnyye-prostitutki'],
            'cheap' => ['Дешевые', '/deshevyye-prostitutki'],
        ]
    ],

    'favorites'  => ['Избранные', '/favorites'],
    'individual' => ['Индивидуалки', '/individualki'],
];

    ?>

    <?php
    // Печатаем модалку и JS (без кнопок)
    get_template_part('components/auth-ui', null, [
        'render_buttons' => false,
    ]);
    ?>

    <header class="sticky inset-x-0 top-0 z-50 bg-black text-gray-300 border-b border-gray-800">
        <div class="px-4">
            <div class="h-16 flex justify-between items-center gap-4">
                <?php
                // Проверка для логотипа
                $is_home = is_front_page();
                $logo_tag = $is_home ? 'span' : 'a';
                $logo_href = $is_home ? '' : 'href="' . esc_url(home_url('/')) . '"';
                ?>
                <<?php echo $logo_tag; ?> <?php echo $logo_href; ?>
                    class="flex items-center gap-2 select-none shrink-0 <?php echo $is_home ? 'cursor-default' : ''; ?>"
                    aria-label="Логотип <?php echo esc_attr($site_name); ?>">
                    <img src="<?php echo esc_url($logo_url); ?>"
                        alt="<?php echo esc_attr($site_name . ' — логотип'); ?>" width="279" height="60"
                        class="h-12 md:h-14 w-auto object-contain" loading="eager" decoding="async" fetchpriority="high">
                </<?php echo $logo_tag; ?>>

                <?php if (!wp_is_mobile()) : ?>
                    <?php
                    $current_url = home_url(add_query_arg([], $GLOBALS['wp']->request));
                    $current_url = user_trailingslashit($current_url);
                    $favorites_item = $menu['favorites'] ?? ['Избранные', '/favorites'];
                    $favorites_link = user_trailingslashit(home_url('/' . trim($favorites_item[1], '/')));
                    $is_favorites_active = ($favorites_link === $current_url);
                    ?>
                    <nav class="hidden md:flex flex-grow justify-center" aria-label="Основное меню">
                        <ul id="main-nav" class="flex items-center gap-3 whitespace-nowrap">
                            <?php
                            foreach ($menu as $key => $item) :
                                if ($key === 'favorites') continue;
                            ?>
                                <li class="relative group">
                                    <?php if (isset($item['sub_menu'])) : ?>
                                        <div
                                            class="group inline-flex items-center px-2 py-1 text-gray-300 hover:text-[#e865a0] transition-colors cursor-pointer select-none uppercase underline underline-offset-4 text-[16px] font-semibold">
                                            <span class="ml-1"><?php echo esc_html($item['label']); ?></span>
                                        </div>

                                        <ul
                                            class="sub-menu absolute left-0 hidden mt-2 min-w-[220px] bg-black shadow-xl ring-black/30 group-hover:block z-50 py-2">
                                            <?php foreach ($item['sub_menu'] as $sub_key => $sub_item) :
                                                $link = user_trailingslashit(home_url('/' . trim($sub_item[1], '/')));
                                                $is_active = ($link === $current_url);
                                            ?>
                                                <li>
                                                    <?php if ($is_active) : ?>
                                                        <span class="block px-4 py-2 text-white bg-gray-800/70 cursor-default font-semibold uppercase tracking-wide">
                                                            <?php echo esc_html($sub_item[0]); ?>
                                                        </span>
                                                    <?php else : ?>
                                                        <a href="<?php echo esc_url($link); ?>"
                                                            class="block px-4 py-2 text-gray-300 hover:text-[#e865a0] hover:bg-gray-800/50 transition-colors uppercase tracking-wide">
                                                            <?php echo esc_html($sub_item[0]); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else :
                                        $link = user_trailingslashit(home_url('/' . trim($item[1], '/')));
                                        $is_active = ($link === $current_url);
                                    ?>
                                        <?php if ($is_active) : ?>
                                            <span
                                                class="inline-flex items-center px-2 py-1 text-[#e865a0] font-bold cursor-default uppercase underline underline-offset-4 text-[16px]">
                                                <span class="ml-1"><?php echo esc_html($item[0]); ?></span>
                                            </span>
                                        <?php else : ?>
                                            <a href="<?php echo esc_url($link); ?>"
                                                class="inline-flex items-center px-2 py-1 text-gray-300 hover:text-[#e865a0] transition-colors uppercase underline underline-offset-4 text-[16px] font-semibold">
                                                <span class="ml-1"><?php echo esc_html($item[0]); ?></span>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>

                    <div class="hidden md:flex items-center shrink-0">
                        <?php if ($is_favorites_active) : ?>
                            <span
                                class="inline-flex items-center justify-center w-10 h-10 rounded-full border border-[#e865a0] text-[#e865a0] cursor-default"
                                aria-current="page"
                                aria-label="<?php echo esc_attr($favorites_item[0]); ?>"
                                title="<?php echo esc_attr($favorites_item[0]); ?>">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 1 0-7.8 7.8l1 1L12 22l7.8-8.6 1-1a5.5 5.5 0 0 0 0-7.8z" />
                                </svg>
                            </span>
                        <?php else : ?>
                            <a href="<?php echo esc_url($favorites_link); ?>"
                                class="inline-flex items-center justify-center w-10 h-10 rounded-full border border-gray-700 text-gray-300 hover:text-[#e865a0] hover:border-[#e865a0] transition-colors"
                                aria-label="<?php echo esc_attr($favorites_item[0]); ?>"
                                title="<?php echo esc_attr($favorites_item[0]); ?>">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 1 0-7.8 7.8l1 1L12 22l7.8-8.6 1-1a5.5 5.5 0 0 0 0-7.8z" />
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="hidden md:flex items-center gap-3">
                    <button type="button" data-auth-btn-login
                        class="inline-flex items-center h-10 px-3 border border-gray-700 text-gray-200 hover:text-white hover:border-gray-500 transition"
                        aria-label="Войти" title="Войти">
                        <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M13 12H3" />
                            <path d="M8 7l-5 5 5 5" />
                            <path d="M21 3h-6v18h6" />
                        </svg>
                        Войти
                    </button>

                    <button type="button" data-auth-btn-add
                        class="inline-flex items-center h-10 px-4 bg-[#e865a0] text-white hover:opacity-90 transition"
                        aria-label="Добавить анкету" title="Добавить анкету">
                        <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M20 6L9 17l-5-5" />
                        </svg>
                        Добавить анкету
                    </button>
                </div>

                <div class="md:hidden">
                    <button id="burger-btn"
                        class="inline-flex items-center justify-center w-10 h-10 text-gray-300 hover:text-white"
                        aria-label="Открыть меню" aria-expanded="false">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="white" stroke="currentColor">
                            <path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div id="drawer-overlay"
            class="fixed inset-0 bg-black/60 opacity-0 pointer-events-none transition-opacity md:hidden z-40"></div>
        <?php if (wp_is_mobile()) : ?>
            <nav id="mobile-drawer"
                class="fixed inset-y-0 right-0 h-[100vh] hidden w-screen max-w-xs bg-black text-gray-200 border-l border-gray-800 translate-x-full transition-transform md:hidden flex flex-col z-50"
                aria-label="Мобильное меню">
                <div class="h-16 flex justify-between items-center px-4 border-b border-gray-800 shrink-0">
                    <button type="button" class="p-2 -mr-2 text-gray-400 hover:text-white" aria-label="Закрыть"
                        data-close-drawer>
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="px-2 py-2 overflow-y-auto">
                    <ul class="flex flex-col">
                        <?php
                        // Для мобильного меню тоже делаем проверку активной ссылки
                        $current_url_mobile = home_url(add_query_arg([], $GLOBALS['wp']->request));
                        $current_url_mobile = user_trailingslashit($current_url_mobile);

                        foreach ($menu as $key => $item) :
                        ?>
                            <?php if (isset($item['sub_menu'])) : ?>
                                <li class="border-b border-gray-800 last:border-b-0">
                                    <button
                                        class="flex items-center px-3 py-3 text-gray-300 hover:text-[#e865a0] hover:bg-gray-800/50 transition-colors rounded-md w-full uppercase underline underline-offset-4 text-[16px] font-semibold">
                                        <span class="flex-1 text-left"><?php echo esc_html($item['label']); ?></span>
                                        <svg class="w-5 h-5 ml-auto text-gray-300 transition-transform transform group-hover:rotate-180"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-width="2" d="M7 10l5 5 5-5H7z" />
                                        </svg>
                                    </button>

                                    <ul class="sub-menu-mobile hidden pl-6">
                                        <?php foreach ($item['sub_menu'] as $sub_key => $sub_item) :
                                            $link = user_trailingslashit(home_url('/' . trim($sub_item[1], '/')));
                                            $is_active = ($link === $current_url_mobile);
                                        ?>
                                            <li>
                                                <?php if ($is_active) : ?>
                                                    <span
                                                        class="block px-3 py-2 text-[#e865a0] bg-gray-800/80 rounded-md font-semibold cursor-default uppercase underline underline-offset-4 text-[15px]">
                                                        <?php echo esc_html($sub_item[0]); ?>
                                                    </span>
                                                <?php else : ?>
                                                    <a href="<?php echo esc_url($link); ?>"
                                                        class="block px-3 py-2 text-gray-300 hover:text-[#e865a0] transition-colors uppercase underline underline-offset-4 text-[15px] font-semibold">
                                                        <?php echo esc_html($sub_item[0]); ?>
                                                    </a>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </li>
                            <?php else :
                                $link = user_trailingslashit(home_url('/' . trim($item[1], '/')));
                                $is_active = ($link === $current_url_mobile);
                            ?>
                                <li class="border-b border-gray-800 last:border-b-0">
                                    <?php if ($is_active) : ?>
                                        <span
                                            class="flex items-center px-3 py-3 text-white bg-gray-800/80 rounded-md font-medium cursor-default">
                                            <span><?php echo esc_html($item[0]); ?></span>
                                        </span>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url($link); ?>"
                                            class="flex items-center px-3 py-3 text-gray-300 hover:text-[#e865a0] hover:bg-gray-800/50 transition-colors rounded-md uppercase underline underline-offset-4 text-[16px] font-semibold">
                                            <span><?php echo esc_html($item[0]); ?></span>
                                        </a>
                                    <?php endif; ?>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>

                    <div class="mt-auto p-4 border-t border-gray-800">
                        <div class="flex flex-col gap-3">
                            <button type="button" data-auth-btn-login
                                class="w-full inline-flex justify-center items-center h-11 rounded-lg border border-gray-700 text-gray-200 hover:text-white hover:border-gray-500 transition"
                                aria-label="Войти" title="Войти">
                                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" aria-hidden="true">
                                    <path d="M13 12H3" />
                                    <path d="M8 7l-5 5 5 5" />
                                    <path d="M21 3h-6v18h6" />
                                </svg>
                                Войти
                            </button>

                            <button type="button" data-auth-btn-add
                                class="w-full inline-flex justify-center items-center h-11 rounded-full bg-[#e865a0] text-white font-medium hover:opacity-90 transition"
                                aria-label="Добавить анкету" title="Добавить анкету">
                                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" aria-hidden="true">
                                    <path d="M20 6L9 17l-5-5" />
                                </svg>
                                Добавить анкету
                            </button>
                        </div>
                    </div>
                </div>
            </nav>
        <?php endif; ?>
    </header>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ваш код мобильного дропдауна оставляем как есть
            (function initMobileDropdown() {
                const dropdownButtons = document.querySelectorAll('#mobile-drawer button');
                dropdownButtons.forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const subMenu = btn.nextElementSibling;
                        const icon = btn.querySelector('svg');
                        if (!subMenu) return;

                        const isOpen = !subMenu.classList.contains('hidden');

                        document.querySelectorAll('#mobile-drawer .sub-menu-mobile').forEach(
                            ul => {
                                if (ul !== subMenu) {
                                    ul.classList.add('hidden');
                                    const otherIcon = ul.previousElementSibling
                                        ?.querySelector('svg');
                                    if (otherIcon) otherIcon.classList.remove('rotate-180');
                                }
                            });

                        if (isOpen) {
                            subMenu.classList.add('hidden');
                            if (icon) icon.classList.remove('rotate-180');
                        } else {
                            subMenu.classList.remove('hidden');
                            if (icon) icon.classList.add('rotate-180');
                        }
                    });
                });
            })();

            // ====== логика хедера и хлебных крошек ======
            const header = document.querySelector('header');
            const breadcrumbs = document.querySelector('.breadcrumbs-wrapper');
            if (!header || !breadcrumbs) return;

            // базовые стили через JS
            Object.assign(header.style, {
                position: 'sticky',
                top: '0',
                zIndex: '100',
                transition: 'transform 0.4s ease, opacity 0.3s ease',
                willChange: 'transform, opacity'
            });
            Object.assign(breadcrumbs.style, {
                position: 'sticky',
                zIndex: '50',
                transition: 'top 0.25s ease'
            });

            let lastScrollY = window.scrollY;
            let headerHidden = false;

            function getHeaderHeight() {
                // учитываем текущую трансформацию
                if (headerHidden) return 0;
                const rect = header.getBoundingClientRect();
                return Math.max(0, rect.height);
            }

            function applyOffsets() {
                const h = getHeaderHeight();
                breadcrumbs.style.top = h + 'px';
            }

            // первичная установка
            applyOffsets();

            // пересчёт при ресайзе/ориентации/шрифтах
            const ro = new ResizeObserver(applyOffsets);
            ro.observe(header);
            window.addEventListener('resize', applyOffsets);

            // показать/скрыть хедер по направлению скролла
            function onScroll() {
                const y = window.scrollY;

                // вниз — прячем, вверх — показываем
                if (y > lastScrollY && y > 80 && !headerHidden) {
                    headerHidden = true;
                    header.style.transform = 'translateY(-100%)';
                    header.style.opacity = '0';
                    applyOffsets(); // крошки встают к верху
                } else if ((y < lastScrollY || y <= 0) && headerHidden) {
                    headerHidden = false;
                    header.style.transform = 'translateY(0)';
                    header.style.opacity = '1';
                    applyOffsets(); // крошки под хедер
                }

                lastScrollY = y;
            }

            // rAF-троттлинг
            let ticking = false;
            window.addEventListener('scroll', () => {
                if (!ticking) {
                    requestAnimationFrame(() => {
                        onScroll();
                        ticking = false;
                    });
                    ticking = true;
                }
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const header = document.querySelector('header');
            const breadcrumbs = document.querySelector('.breadcrumbs-wrapper');
            if (!header || !breadcrumbs) return;

            let lastScrollY = window.scrollY;
            let ticking = false;
            let hidden = false;

            // базовые стили
            header.style.transition = 'transform 0.4s ease, opacity 0.3s ease';
            header.style.willChange = 'transform, opacity';
            header.style.zIndex = '100'; // хедер всегда выше
            breadcrumbs.style.transition = 'top 0.4s ease';
            breadcrumbs.style.zIndex = '50'; // крошки под хедером

            function onScroll() {
                const currentScroll = window.scrollY;

                // прокрутка вниз — прячем хедер
                if (currentScroll > lastScrollY && currentScroll > 80 && !hidden) {
                    header.style.transform = 'translateY(-100%)';
                    header.style.opacity = '0';
                    hidden = true;
                }
                // прокрутка вверх — показываем хедер
                else if ((currentScroll < lastScrollY || currentScroll <= 0) && hidden) {
                    header.style.transform = 'translateY(0)';
                    header.style.opacity = '1';
                    hidden = false;
                }

                lastScrollY = currentScroll;
                ticking = false;
            }

            window.addEventListener('scroll', function() {
                if (!ticking) {
                    window.requestAnimationFrame(onScroll);
                    ticking = true;
                }
            });
        });
    </script>

    <?php get_template_part('components/breadcrumbs'); ?>

    <!-- СКРИПТ ДЛЯ РАБОТЫ БУРГЕР-МЕНЮ -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const burgerBtn = document.getElementById('burger-btn');
            const mobileDrawer = document.getElementById('mobile-drawer');
            const drawerOverlay = document.getElementById('drawer-overlay');
            const closeButtons = document.querySelectorAll('[data-close-drawer]');
            const TRANSITION_MS = 300; // подгоняй под duration в CSS (Tailwind duration-300)

            if (!mobileDrawer) return;

            function openDrawer() {
                // показать drawer
                mobileDrawer.classList.remove('hidden'); // сделать видимым
                // форсим reflow, чтобы анимация translate сработала
                void mobileDrawer.offsetWidth;
                mobileDrawer.classList.remove('translate-x-full');

                // показать оверлей (если есть)
                if (drawerOverlay) {
                    drawerOverlay.classList.remove('hidden', 'pointer-events-none', 'opacity-0');
                }

                document.body.style.overflow = 'hidden'; // блокируем скролл
                if (burgerBtn) burgerBtn.setAttribute('aria-expanded', 'true');
            }

            function closeDrawer() {
                // запускаем анимацию закрытия
                mobileDrawer.classList.add('translate-x-full');

                if (drawerOverlay) {
                    drawerOverlay.classList.add('opacity-0', 'pointer-events-none');
                }

                // по окончании анимации скрываем элемент классом hidden
                setTimeout(() => {
                    mobileDrawer.classList.add('hidden');
                    if (drawerOverlay) drawerOverlay.classList.add('hidden');
                    document.body.style.overflow = '';
                    if (burgerBtn) burgerBtn.setAttribute('aria-expanded', 'false');
                }, TRANSITION_MS);
            }

            // навешиваем обработчики
            if (burgerBtn) burgerBtn.addEventListener('click', openDrawer);
            if (drawerOverlay) drawerOverlay.addEventListener('click', closeDrawer);
            closeButtons.forEach(btn => btn.addEventListener('click', closeDrawer));

            // ESC для закрытия
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeDrawer();
            });
        });
    </script>
