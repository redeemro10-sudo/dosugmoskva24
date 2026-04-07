<?php
if (!defined('ABSPATH')) exit;

function render_model_grid_with_filters()
{
  global $wpdb, $post;

  $is_ajax = defined('DOING_AJAX') && DOING_AJAX;
  $append  = (!empty($_POST['append']) && $_POST['append'] == '1');

  if (!$is_ajax && !$append) {
    $GLOBALS['site_ldjson_models'] = [];
  }

  // --- 1. Контекст "НОВЫЕ" ---
  $is_novye_page = is_page('novye');
  $is_novye_post = (isset($_POST['is_novye']) && $_POST['is_novye'] == '1');
  $referer = wp_get_referer();
  $is_novye_referer = ($is_ajax && $referer && strpos($referer, '/novye') !== false);
  $is_novye = $is_novye_page || $is_novye_post || $is_novye_referer;

  // --- 2. Контекст "ДЕШЕВЫЕ" / "ЭЛИТНЫЕ" и страницы-разделы ---
  $page_slug = '';
  if (is_page()) {
    $qo = get_queried_object();
    if ($qo && !is_wp_error($qo) && !empty($qo->post_name)) $page_slug = (string) $qo->post_name;
  }

  $cheap_only = false;
  if (isset($_POST['cheap_only']) && in_array($_POST['cheap_only'], ['1', 'true', 'on'])) $cheap_only = true;
  if (!$cheap_only && $page_slug === 'deshevyye-prostitutki') $cheap_only = true;

  $elite_only = false;
  if (isset($_POST['elite_only']) && in_array($_POST['elite_only'], ['1', 'true', 'on'])) $elite_only = true;
  if (!$elite_only && $page_slug === 'elitnyye-prostitutki') $elite_only = true;

  $is_escort_page      = in_array($page_slug, ['escort', 'eskort'], true);
  $is_individual_page  = ($page_slug === 'individualki');
  $is_soderzhanki_page = ($page_slug === 'soderzhanki');
  $is_kizdar_page      = ($page_slug === 'kizdar');

  $has_active_filters = (isset($_POST['has_active_filters']) && in_array($_POST['has_active_filters'], ['1', 'true', 'on'], true));

  // --- 3. Топ-60 НОВЫХ ---
  $global_newest_ids = get_posts([
    'post_type'        => 'models',
    'post_status'      => 'publish',
    'posts_per_page'   => 60,
    'orderby'          => 'date',
    'order'            => 'DESC',
    'fields'           => 'ids',
    'ignore_sticky_posts' => true,
    'suppress_filters' => true,
    'no_found_rows'    => true,
  ]);
  if (empty($global_newest_ids)) $global_newest_ids = [0];

  // --- 4. Лимиты ---
  $per_page_default = 48;
  if ($is_ajax) {
    $per_page = isset($_POST['per_page']) ? max(1, (int)$_POST['per_page']) : 48;
  } else {
    $per_page = $per_page_default;
  }
  if ($is_novye && empty($_POST['per_page'])) $per_page = 48;

  $paged = isset($_POST['paged'])
    ? max(1, (int) $_POST['paged'])
    : max(1, (int) (get_query_var('paged') ?: get_query_var('page') ?: 1));
  $is_first_render = (!$is_ajax && !$append && $paged === 1);

  // --- 5. Определение LCP ---
  $is_mobile = wp_is_mobile();
  if (is_front_page() || is_home()) {
    $lcp_limit = 0;
  } else {
    $lcp_limit = $is_mobile ? 1 : 4;
  }

  /* ===== Таксономии ===== */
  $ALLOWED_TAX = [
    'ves_tax', 'vozrast_tax', 'grud_tax', 'drygie_tax', 'metro_tax',
    'nationalnost_tax', 'rayonu_tax', 'rost_tax', 'cvet-volos_tax',
    'price_tax', 'vneshnost_tax', 'figura_tax', 'uslugi_tax',
  ];
  $base_tax = [];

  if (!$is_novye) {
    // --- Base tax из POST ---
    if (!empty($_POST['base_tax_taxonomy']) && in_array($_POST['base_tax_taxonomy'], $ALLOWED_TAX, true)) {
      $tx = sanitize_text_field($_POST['base_tax_taxonomy']);
      $terms_raw = isset($_POST['base_tax_terms']) ? (array)$_POST['base_tax_terms'] : [];
      if (count($terms_raw) === 1 && is_string($terms_raw[0]) && strpos($terms_raw[0], ',') !== false) {
        $terms_raw = array_map('trim', explode(',', $terms_raw[0]));
      }
      $terms = array_map('intval', array_filter($terms_raw));
      if ($terms) $base_tax = ['taxonomy' => $tx, 'terms' => $terms];
    } elseif (($bt = get_query_var('base_tax')) && is_array($bt) && !empty($bt['taxonomy'])) {
      $base_tax = ['taxonomy' => $bt['taxonomy'], 'terms' => array_map('intval', (array)$bt['terms'])];
    }

    // --- Base tax из is_tax() ---
    if (empty($base_tax) && is_tax()) {
      $term = get_queried_object();
      if ($term && !is_wp_error($term) && in_array($term->taxonomy, $ALLOWED_TAX, true)) {
        $base_tax = ['taxonomy' => $term->taxonomy, 'terms' => [(int)$term->term_id]];
      }
    }

    // --- Base tax из CPT-посадочных страниц ---
    if (empty($base_tax)) {
      $qo        = get_queried_object();
      $slug      = (is_object($qo) && !empty($qo->post_name)) ? (string)$qo->post_name : '';
      $post_type = ($qo instanceof WP_Post) ? $qo->post_type : '';

      $CPT_TAX_MAP = [
        'tsena'        => 'price_tax',
        'vozrast'      => 'vozrast_tax',
        'nacionalnost' => 'nationalnost_tax',
        'rajon'        => 'rayonu_tax',
        'metro'        => 'metro_tax',
        'rost'         => 'rost_tax',
        'grud'         => 'grud_tax',
        'ves'          => 'ves_tax',
        'tsvet-volos'  => 'cvet-volos_tax',
        'uslugi'       => 'uslugi_tax',
      ];

      if ($slug !== '' && isset($CPT_TAX_MAP[$post_type])) {
        $tx = $CPT_TAX_MAP[$post_type];
        $t  = get_term_by('slug', $slug, $tx);
        if ($t && !is_wp_error($t)) {
          $base_tax = ['taxonomy' => $tx, 'terms' => [(int)$t->term_id]];
        }
      }
    }
  }

  // --- Страница "Кыздар": жёстко фильтруем по национальности ---
  if ($is_kizdar_page) {
    $t = get_term_by('slug', 'kazashki', 'nationalnost_tax');
    $base_tax = ($t && !is_wp_error($t))
      ? ['taxonomy' => 'nationalnost_tax', 'terms' => [(int)$t->term_id]]
      : ['taxonomy' => 'nationalnost_tax', 'terms' => [0]];
  }

  /* ===== Исключения ===== */
  $exclude_ids_post = isset($_POST['exclude_ids'])
    ? array_map('intval', is_array($_POST['exclude_ids']) ? $_POST['exclude_ids'] : explode(',', (string)$_POST['exclude_ids']))
    : [];
  $reco_global = !empty($GLOBALS['esc_featured_model_ids']) ? array_map('intval', (array)$GLOBALS['esc_featured_model_ids']) : [];

  $base_static_ids       = array_values(array_unique($reco_global));
  $exclude_ids_static    = [];
  $exclude_ids_rendered  = array_values(array_unique($exclude_ids_post));
  $all_ex                = array_values(array_unique(array_merge($exclude_ids_rendered, $exclude_ids_static)));

  /* ===== Tax Query ===== */
  $normalize_values = static function ($v) {
    if (is_array($v)) return array_values(array_filter($v));
    return [$v];
  };

  $tax_query = [];

  if (!$is_novye) {
    // Пользовательские фильтры из POST
    foreach ($ALLOWED_TAX as $tx) {
      if (!isset($_POST[$tx])) continue;
      $vals = $normalize_values($_POST[$tx]);
      if (!empty($vals)) {
        $tax_query[] = ['taxonomy' => $tx, 'field' => 'slug', 'terms' => $vals, 'operator' => 'IN'];
      }
    }

    // Base tax — добавляем первым
    if (!empty($base_tax)) {
      array_unshift($tax_query, [
        'taxonomy' => $base_tax['taxonomy'],
        'field'    => 'term_id',
        'terms'    => $base_tax['terms'],
        'operator' => 'IN',
      ]);
    }

    // --- ESCORT ---
    if ($is_escort_page) {
      $eskort_term = get_term_by('slug', 'eskort', 'drygie_tax');
      $eskort_id   = ($eskort_term && !is_wp_error($eskort_term)) ? (int)$eskort_term->term_id : 0;

      if (empty($base_tax)) {
        $base_tax = ['taxonomy' => 'drygie_tax', 'terms' => [$eskort_id]];
      }

      // Только IN eskort — без NOT IN:
      // анкеты одновременно имеют несколько терминов (eskort + individualki и т.д.),
      // NOT IN убивал бы большинство анкет
      $tax_query[] = [
        'taxonomy' => 'drygie_tax',
        'field'    => 'term_id',
        'terms'    => [$eskort_id],
        'operator' => 'IN',
      ];
    }

    // --- INDIVIDUALKI ---
    if ($is_individual_page) {
      $individual_term = get_term_by('slug', 'individualki', 'drygie_tax');
      $individual_id   = ($individual_term && !is_wp_error($individual_term)) ? (int)$individual_term->term_id : 0;

      if (empty($base_tax)) {
        $base_tax = ['taxonomy' => 'drygie_tax', 'terms' => [$individual_id]];
      }

      $tax_query[] = [
        'taxonomy' => 'drygie_tax',
        'field'    => 'term_id',
        'terms'    => [$individual_id],
        'operator' => 'IN',
      ];
      $tax_query[] = [
        'taxonomy' => 'drygie_tax',
        'field'    => 'slug',
        'terms'    => ['soderzhanki', 'kizdar'],
        'operator' => 'NOT IN',
      ];
    }

    // --- SODERZHANKI ---
    if ($is_soderzhanki_page) {
      $soderzhanki_term = get_term_by('slug', 'soderzhanki', 'drygie_tax');
      $soderzhanki_id   = ($soderzhanki_term && !is_wp_error($soderzhanki_term)) ? (int)$soderzhanki_term->term_id : 0;

      if (empty($base_tax)) {
        $base_tax = ['taxonomy' => 'drygie_tax', 'terms' => [$soderzhanki_id]];
      }

      $tax_query[] = [
        'taxonomy' => 'drygie_tax',
        'field'    => 'term_id',
        'terms'    => [$soderzhanki_id],
        'operator' => 'IN',
      ];
      $tax_query[] = [
        'taxonomy' => 'drygie_tax',
        'field'    => 'slug',
        'terms'    => ['eskort', 'individualki', 'kizdar'],
        'operator' => 'NOT IN',
      ];
    }

    // Для /kizdar — только фильтр по национальности (без drygie_tax),
    // чтобы выводились все казашки, даже без термина раздела.

    // Устанавливаем relation если несколько условий
    if (count($tax_query) > 1 && !isset($tax_query['relation'])) {
      $tax_query = array_merge(['relation' => 'AND'], $tax_query);
    }
  }

  /* ===== Meta & Sort ===== */
  $force_video_page = (is_page() && get_queried_object()->post_name === 's-video');
  $videos_only = $force_video_page;
  if (isset($_POST['video_only']) && in_array($_POST['video_only'], ['1', 'true', 'on'])) $videos_only = true;

  $sort      = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'date_desc';
  if ($is_novye) $sort = 'date_desc';
  $rand_seed = (isset($_POST['rand_seed']) && (int)$_POST['rand_seed'] > 0) ? (int)$_POST['rand_seed'] : (int)wp_rand(1, 2147483647);

  $orderby_arg = 'none';
  $meta_key    = null;
  $meta_type   = null;
  switch ($sort) {
    case 'date_desc':
      $orderby_arg = ['date' => 'DESC', 'ID' => 'DESC'];
      break;
    case 'date_asc':
      $orderby_arg = ['date' => 'ASC', 'ID' => 'ASC'];
      break;
    case 'price_asc':
      $meta_key    = 'price_outcall';
      $meta_type   = 'NUMERIC';
      $orderby_arg = ['meta_value_num' => 'ASC', 'date' => 'DESC', 'ID' => 'DESC'];
      break;
    case 'price_desc':
      $meta_key    = 'price_outcall';
      $meta_type   = 'NUMERIC';
      $orderby_arg = ['meta_value_num' => 'DESC', 'date' => 'DESC', 'ID' => 'DESC'];
      break;
    case 'random':
    default:
      $orderby_arg = ['date' => 'DESC', 'ID' => 'DESC'];
  }

  /* ===== Meta Query ===== */
  $meta_query = ['relation' => 'AND'];

  if ($sort === 'price_asc' || $sort === 'price_desc') {
    $meta_query[] = ['key' => 'price_outcall', 'compare' => 'EXISTS'];
    $meta_query[] = ['key' => 'price_outcall', 'value' => 0, 'type' => 'NUMERIC', 'compare' => '>'];
  }
  if ($cheap_only) {
    $meta_query[] = [
      'relation' => 'OR',
      ['key' => 'price_outcall', 'value' => 15000, 'type' => 'NUMERIC', 'compare' => '<'],
      ['key' => 'price',         'value' => 15000, 'type' => 'NUMERIC', 'compare' => '<'],
    ];
  }
  if ($elite_only) {
    $meta_query[] = [
      'relation' => 'OR',
      ['key' => 'vip',           'value' => '1', 'compare' => '='],
      ['key' => 'price_outcall', 'value' => 25000, 'type' => 'NUMERIC', 'compare' => '>='],
      ['key' => 'price',         'value' => 25000, 'type' => 'NUMERIC', 'compare' => '>='],
    ];
  }
  if ($videos_only) {
    $meta_query[] = ['key' => 'video', 'value' => '[^[:space:]]', 'compare' => 'REGEXP'];
  }

  /* ===== WP_Query args ===== */
  $args = [
    'post_type'           => 'models',
    'post_status'         => 'publish',
    'posts_per_page'      => $per_page,
    'paged'               => $paged,
    'fields'              => 'ids',
    'no_found_rows'       => false,
    'orderby'             => $orderby_arg,
    'ignore_sticky_posts' => true,
    'suppress_filters'    => false,
  ];

  // --- post__in / post__not_in ---
  if ($is_novye) {
    $candidates = $global_newest_ids;
    if (!empty($exclude_ids_rendered)) $candidates = array_diff($candidates, $exclude_ids_rendered);
    $args['post__in'] = !empty($candidates) ? array_slice($candidates, 0, $per_page + 1) : [0];
    $args['paged']    = 1;
  } else {
    if ($append || $paged > 1) {
      if (!empty($all_ex)) $args['post__not_in'] = $all_ex;
    } else {
      if (!empty($exclude_ids_static)) $args['post__not_in'] = $exclude_ids_static;
    }
  }

  // --- Tax query ---
  // Применяем всегда, если есть (cheap/elite работают через meta_query, не мешают)
  if (!empty($tax_query) && !$is_novye) {
    $args['tax_query'] = $tax_query;
  }

  // --- Meta query ---
  if (count($meta_query) > 1) $args['meta_query'] = $meta_query;
  if (!empty($meta_key)) {
    $args['meta_key']  = $meta_key;
    $args['meta_type'] = $meta_type ?: 'NUMERIC';
  }

  /* ===== Hooks для DISTINCT ===== */
  $distinct_cb = null;
  $groupby_cb  = null;
  if (!empty($args['tax_query'])) {
    $distinct_cb = function () { return 'DISTINCT'; };
    add_filter('posts_distinct', $distinct_cb, PHP_INT_MAX);

    $groupby_cb = function ($g) use ($wpdb) {
      $id = "{$wpdb->posts}.ID";
      return (strpos($g, $id) === false) ? "$g, $id" : $g;
    };
    add_filter('posts_groupby', $groupby_cb, PHP_INT_MAX);
  }

  $q = new WP_Query($args);

  if ($groupby_cb) remove_filter('posts_groupby',  $groupby_cb,  PHP_INT_MAX);
  if ($distinct_cb) remove_filter('posts_distinct', $distinct_cb, PHP_INT_MAX);

  $found_ids   = $q->posts;
  $total_pages = max(1, (int)$q->max_num_pages);
  $has_more    = ($paged < $total_pages);

  /* ===== Пагинация ===== */
  $pagination_links = [];
  $is_tax_ctx       = is_tax() || (!empty($base_tax) && !$is_novye);
  if (!empty($is_paginated_page)) $is_tax_ctx = false;

  if (!$append && !$is_tax_ctx && $total_pages > 1) {
    $big            = 999999999;
    $pagination_base = str_replace((string)$big, '%#%', esc_url_raw(get_pagenum_link($big)));
    $pagination_links = paginate_links([
      'base'      => $pagination_base,
      'format'    => '',
      'current'   => $paged,
      'total'     => $total_pages,
      'type'      => 'array',
      'add_args'  => false,
      'mid_size'  => 1,
      'end_size'  => 1,
      'prev_text' => '‹',
      'next_text' => '›',
    ]) ?: [];
  }

  /* ===== Прогрев кешей ===== */
  if (!empty($found_ids)) {
    _prime_post_caches($found_ids, true, true);
    $ids_sql = implode(',', array_map('intval', $found_ids));
    $raw_meta = $wpdb->get_results("SELECT meta_value FROM $wpdb->postmeta WHERE post_id IN ($ids_sql) AND meta_key = 'photo'");
    $att_ids = [];
    foreach ($raw_meta as $row) {
      $val = maybe_unserialize($row->meta_value);
      if (is_numeric($val))        $att_ids[] = $val;
      elseif (is_array($val))      foreach ($val as $v) if (is_numeric($v)) $att_ids[] = $v;
      elseif (isset($val['ID']))   $att_ids[] = $val['ID'];
    }
    if (!empty($att_ids)) _prime_post_caches(array_unique($att_ids), true, true);
  }

  $page_mode = 'pagination';

  ob_start();

  /* ===== JS env ===== */
  if (!$append) {
    static $js_env_printed = false;
    if (!$js_env_printed) {
      $js_env_printed = true;
      $ajax_url = admin_url('admin-ajax.php');
      $nonce    = wp_create_nonce('site_filter_nonce');
      $bt_tax   = !empty($base_tax['taxonomy']) ? $base_tax['taxonomy'] : '';
      $bt_ids   = !empty($base_tax['terms'])    ? implode(',', (array)$base_tax['terms']) : '';
      echo '<div id="mf-env" class="hidden"'
        . ' data-ajax="'          . esc_attr($ajax_url)              . '"'
        . ' data-nonce="'         . esc_attr($nonce)                 . '"'
        . ' data-mode="'          . esc_attr($page_mode)             . '"'
        . ' data-rand-seed="'     . (int)$rand_seed                  . '"'
        . ' data-is-tax="'        . ($is_tax_ctx  ? '1' : '0')      . '"'
        . ' data-basetax-tax="'   . esc_attr($bt_tax)                . '"'
        . ' data-basetax-terms="' . esc_attr($bt_ids)                . '"'
        . ' data-video-only="'    . ($videos_only ? '1' : '0')       . '"'
        . ' data-cheap-only="'    . ($cheap_only  ? '1' : '0')       . '"'
        . ' data-is-novye="'      . ($is_novye    ? '1' : '0')       . '"'
        . ' data-per-page="'      . (int)$per_page                   . '"'
        . ' data-current-page="'  . (int)$paged                      . '"'
        . ' data-total-pages="'   . (int)$total_pages                . '"'
        . '></div>';
    }
  }

  /* ===== Карточки ===== */
  if (!empty($found_ids)) :
    if (!$append) echo '<ul id="mf-list" class="cards-grid cards-grid--models mb-4">';
    if ($is_novye)  echo '<input type="hidden" name="is_novye" value="1">';

    foreach ($found_ids as $index => $pid) {
      $is_new_flag = in_array($pid, $global_newest_ids, true);
      $post = get_post($pid);
      setup_postdata($post);

      $name        = get_post_meta($pid, 'name', true) ?: get_the_title($pid);
      $raw_gallery = get_post_meta($pid, 'photo', true);
      if (empty($name) || empty($raw_gallery)) continue;

      $gallery = [];
      if (is_array($raw_gallery)) {
        if (isset($raw_gallery['ID'])) $gallery = [$raw_gallery];
        else foreach ($raw_gallery as $item) if (is_array($item) || is_numeric($item)) $gallery[] = $item;
      } elseif (is_numeric($raw_gallery)) {
        $gallery = [(int)$raw_gallery];
      }
      if (empty($gallery)) continue;

      $is_priority_image = ($is_first_render && $index < $lcp_limit);

      $district_names = get_the_terms($pid, 'rayonu_tax');
      $district       = (!is_wp_error($district_names) && $district_names) ? implode(', ', wp_list_pluck($district_names, 'name')) : '';
      $services_terms = get_the_terms($pid, 'uslugi_tax');
      $services       = (!is_wp_error($services_terms) && $services_terms) ? wp_list_pluck($services_terms, 'name') : [];

      $model = [
        'ID'                     => $pid,
        'is_new'                 => $is_new_flag,
        'name'                   => $name,
        'uri'                    => get_permalink($pid),
        'modelGalleryThumbnail'  => $gallery,
        'district'               => $district,
        'services'               => $services,
        'price'                  => get_post_meta($pid, 'price',        true),
        'price_outcall'          => get_post_meta($pid, 'price_outcall', true),
        'video'                  => get_post_meta($pid, 'video',        true),
        'selfie'                 => get_post_meta($pid, 'selfie',       true),
        'height'                 => get_post_meta($pid, 'height',       true),
        'weight'                 => get_post_meta($pid, 'weight',       true),
        'age'                    => get_post_meta($pid, 'age',          true),
        'bust'                   => get_post_meta($pid, 'bust',         true),
        'description'            => get_post_meta($pid, 'description',  true),
        'is_lcp_priority'        => $is_priority_image,
      ];

      if (!$is_ajax && function_exists('site_ldjson_collect_model')) site_ldjson_collect_model($model);
      set_query_var('model', $model);

      ob_start();
      get_template_part('components/ModelCard');
      $card_html = ob_get_clean();
      $card_html = preg_replace('~<li([^>]*)class="~i',        '<li$1class="mf-item ', $card_html, 1, $c1);
      if (!$c1) $card_html = preg_replace('~<li(?![^>]*class=)~i', '<li class="mf-item"', $card_html, 1);
      $card_html = preg_replace('~<li([^>]*)>~i',              '<li$1 data-id="' . (int)$pid . '">', $card_html, 1);
      echo $card_html;
    }

    if (!$append) echo '</ul>';

    /* ===== Пагинация HTML ===== */
    if (!$append && !empty($pagination_links) && !$is_tax_ctx) : ?>
      <div id="mf-pagination" class="w-full flex flex-col items-center gap-3 mt-3 mb-4">
        <nav id="mf-pages" class="w-full flex justify-center" aria-label="Пагинация">
          <ul class="flex flex-wrap justify-center gap-2">
            <?php foreach ($pagination_links as $link) :
              $text = wp_strip_all_tags($link);
              preg_match('~href=["\']([^"\']+)~', $link, $m);
              $href = $m[1] ?? '#';
              if ($href !== '#' && preg_match('~/page/1/?([?#].*)?$~', $href)) {
                $href = esc_url_raw(get_pagenum_link(1));
              }
              $is_cur   = strpos($link, 'current') !== false;
              $page_num = 0;
              if (preg_match('~page/([0-9]+)/?~', $href, $pm)) $page_num = (int)$pm[1];
              elseif (is_numeric($text))                         $page_num = (int)$text;
            ?>
              <li>
                <?php if ($is_cur) : ?>
                  <span class="px-4 h-10 inline-flex items-center justify-center rounded-[10px] border text-[15px] font-semibold bg-[#e865a0] text-white border-[#e865a0]">
                    <?php echo esc_html($text); ?>
                  </span>
                <?php else : ?>
                  <a
                    class="mf-page-btn px-4 h-10 inline-flex items-center justify-center rounded-[10px] border text-[15px] font-semibold bg-white text-[#e865a0] border-[#e865a0] hover:bg-[#e865a0] hover:text-white transition"
                    data-page="<?php echo esc_attr($page_num ?: $text); ?>"
                    href="<?php echo esc_url($href); ?>"
                  ><?php echo esc_html($text); ?></a>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </nav>
      </div>
    <?php endif;

  else :
    if (!$append && $paged === 1 && empty($exclude_ids_rendered)) {
      echo '<p class="text-neutral-600">Модели не найдены.</p>';
    }
  endif;

  wp_reset_postdata();
  $html = ob_get_clean();

  /* ===== JSON-LD ===== */
  if (!$is_ajax && !$append) {
    $collected_models = $GLOBALS['site_ldjson_models'] ?? [];
    if (!empty($collected_models)) {
      set_query_var('models', $collected_models);
      ob_start();
      get_template_part('json-ld/person-list');
      $html .= ob_get_clean();
    }
  }

  $GLOBALS['models_last_query_meta'] = [
    'paged'       => (int)$paged,
    'max'         => (int)$total_pages,
    'rand_seed'   => (int)$rand_seed,
    'used_offset' => isset($args['offset']) ? (int)$args['offset'] : 0,
    'has_more'    => (bool)$has_more,
  ];

  if ($append && trim($html) === '') wp_send_json_error();
  if ($is_ajax) wp_send_json_success(['html' => $html, 'has_more' => $has_more, 'rendered_ids' => $found_ids]);

  echo $html;
}
