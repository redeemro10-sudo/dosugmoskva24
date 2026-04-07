<?php

/**
 * Template Name: Родительский шаблон (термы)
 * Description: Выводит записи связанного CPT плитками, без пагинации.
 */

if (!defined('ABSPATH')) exit;
require_once get_template_directory() . '/components/auto-text.php';
get_header();

$page_id    = get_queried_object_id();
$page_title = (string) get_the_title($page_id);
$h1_manual  = function_exists('get_field') ? (string) (get_field('h1', $page_id) ?: '') : '';
$h1         = $h1_manual !== '' ? $h1_manual : $page_title;
$p          = function_exists('get_field') ? (get_field('p',  $page_id) ?: '') : '';
$text_block = function_exists('get_field') ? (get_field('seo', $page_id) ?: '') : '';

// Определяем CPT по слагу текущей страницы
$page_slug = (string) get_post_field('post_name', $page_id);
$slug_to_post_type = [
    'services'     => 'uslugi',
    'rajony'       => 'rajon',
    'metro'        => 'metro',
    'price'        => 'tsena',
    'vozrast'      => 'vozrast',
    'nationalnost' => 'nacionalnost',
    'ves'          => 'ves',
    'rost'         => 'rost',
    'grud'         => 'grud',
    'cvet-volos'   => 'tsvet-volos',

    // Поддержка старых слагов на случай legacy-страниц.
    'tsena'        => 'tsena',
    'nacionalnost' => 'nacionalnost',
    'tsvet-volos'  => 'tsvet-volos',
];
$post_type = $slug_to_post_type[$page_slug] ?? 'uslugi';

// Для корректной вложенности URL категории строим ссылку через term_link.
$slug_to_taxonomy = [
    'services'     => 'uslugi_tax',
    'rajony'       => 'rayonu_tax',
    'metro'        => 'metro_tax',
    'price'        => 'price_tax',
    'vozrast'      => 'vozrast_tax',
    'nationalnost' => 'nationalnost_tax',
    'ves'          => 'ves_tax',
    'rost'         => 'rost_tax',
    'grud'         => 'grud_tax',
    'cvet-volos'   => 'cvet-volos_tax',

    // legacy-страницы
    'tsena'        => 'price_tax',
    'nacionalnost' => 'nationalnost_tax',
];
$taxonomy = $slug_to_taxonomy[$page_slug] ?? '';

// Проверка регистрации CPT
if (!post_type_exists($post_type)) {
    echo '<main class="mx-auto w-full lg:w-[1200px] px-4 py-8">';
    echo '<h1 class="text-3xl font-bold mb-4">' . esc_html($h1) . '</h1>';
    if ($p) {
        echo '<div class="prose prose-neutral max-w-none mb-6 content">' . wpautop(wp_kses_post($p)) . '</div>';
    }
    echo '<p class="text-black">Тип записи <code>' . esc_html($post_type) . '</code> не найден. Проверьте регистрацию через register_post_type().</p>';
    echo '</main>';
    get_footer();
    return;
}

// Запрос всех услуг (без пагинации)
$q = new WP_Query([
    'post_type'           => $post_type,
    'post_status'         => 'publish',
    'posts_per_page'      => -1, // все записи
    'orderby'             => ['menu_order' => 'ASC', 'title' => 'ASC'],
    'no_found_rows'       => true,
    'ignore_sticky_posts' => true,
]);

if (function_exists('dosugmoskva24_generate_term_parent_auto_text')) {
    $auto_text = dosugmoskva24_generate_term_parent_auto_text([
        'post_type' => $post_type,
        'taxonomy' => $taxonomy,
        'page_slug' => $page_slug,
        'items_count' => (int) $q->post_count,
        'city' => 'Москва',
    ]);

    if ($h1_manual === '' && !empty($auto_text['h1'])) {
        $h1 = (string) $auto_text['h1'];
    }
    if ($p === '' && !empty($auto_text['p'])) {
        $p = (string) $auto_text['p'];
    }
    if ($text_block === '' && !empty($auto_text['seo'])) {
        $text_block = (string) $auto_text['seo'];
    }
}
?>

<main class="page-hero page-hero--uslugi">
    <div class="page-hero__inner max-w-[1200px] mx-auto text-black">

    <header class="mb-10 grid grid-cols-1 lg:grid-cols-[1fr_1.2fr] gap-6 items-end">
        <div>
            <h1 class="page-title"><?php echo esc_html($h1); ?></h1>
        </div>
        <?php if ($p) { ?>
            <div class="content text-[15px] md:text-[16px] leading-6 text-neutral-700">
                <?php echo wpautop(wp_kses_post($p)); ?>
            </div>
        <?php } ?>
    </header>

    <?php if ($q->have_posts()) { ?>
        <!-- Плитки ссылок -->
        <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 items-start">
            <?php
            while ($q->have_posts()) {
                $q->the_post();
                $pid    = get_the_ID();
                $title  = get_the_title();
                $url    = get_permalink();

                if ($taxonomy !== '' && taxonomy_exists($taxonomy)) {
                    $term = get_term_by('slug', (string) get_post_field('post_name', $pid), $taxonomy);
                    if ($term && !is_wp_error($term)) {
                        $term_url = get_term_link($term);
                        if (!is_wp_error($term_url) && is_string($term_url) && $term_url !== '') {
                            $url = $term_url;
                        }
                    }
                }

                // Превью
                $thumb_id  = get_post_thumbnail_id($pid);
                $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '';
                $thumb_alt = $thumb_id ? get_post_meta($thumb_id, '_wp_attachment_image_alt', true) : '';
                if ($thumb_alt === '') $thumb_alt = $title;

                // 1) Описание из ACF p_atc
                $desc_raw = function_exists('get_field') ? (get_field('p_atc', $pid) ?: '') : '';
                // 2) Fallback: excerpt -> content
                if ($desc_raw === '' || (is_string($desc_raw) && trim($desc_raw) === '')) {
                    $desc_raw = get_the_excerpt() ?: get_the_content('');
                }
            ?>
                <li>
                    <a href="<?php echo esc_url($url); ?>"
                        class="group flex flex-col md:flex-row-reverse rounded-2xl border border-[rgba(232,101,160,.2)] bg-white shadow-[0_6px_20px_rgba(0,0,0,.05)] hover:shadow-[0_10px_26px_rgba(0,0,0,.08)] transition overflow-hidden"
                        aria-label="<?php echo esc_attr($title); ?>">
                        <?php if ($thumb_url) { ?>
                            <div class="md:w-[42%]">
                                <div class="aspect-[4/3] bg-neutral-100">
                                <img
                                    src="<?php echo esc_url($thumb_url); ?>"
                                    alt="<?php echo esc_attr($thumb_alt); ?>"
                                    class="w-full h-full object-cover"
                                    loading="lazy"
                                    decoding="async">
                                </div>
                            </div>
                        <?php } ?>
                        <div class="p-4 md:p-5 flex-1">
                            <h3 class="text-[17px] md:text-[18px] font-semibold group-hover:text-rose-600 transition-colors">
                                <?php echo esc_html($title); ?>
                            </h3>
                        </div>
                    </a>
                </li>
            <?php }
            wp_reset_postdata(); ?>
        </ul>
    <?php } else { ?>
        <p class="text-neutral-600">Не найдено записей типа <code><?php echo esc_html($post_type); ?></code>.</p>
    <?php } ?>

    <?php if (!empty($text_block)) : ?>
        <div class="content mx-auto max-w-[1200px] mt-10 bg-white text-neutral-800 border border-[rgba(232,101,160,.18)] rounded-2xl p-6 md:p-8 shadow-[0_8px_24px_rgba(0,0,0,.04)]">
            <?php echo wpautop(wp_kses_post($text_block)); ?>
        </div>
    <?php endif; ?>

    </div>
</main>

<?php get_footer(); ?>
