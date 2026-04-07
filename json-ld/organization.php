<?php
// organization.php — JSON-LD разметка организации (Москва)

if (!defined('ABSPATH')) exit;

// Базовые данные
$org_name        = 'Эскорт Москва';
$org_url         = home_url('/');
$org_logo        = function_exists('get_site_icon_url') && get_site_icon_url() ? get_site_icon_url(192) : get_site_url(null, 'favicon-32x32.png');
$org_description = function_exists('get_field') ? (get_field('descr') ?: get_bloginfo('description')) : get_bloginfo('description');

// Контакты (кастомайзер/ACF)
$phone_mod   = trim((string) get_theme_mod('contact_number'));
$whatsapp    = trim((string) get_theme_mod('contact_whatsapp'));       // запасной источник
$email_mod   = trim((string) get_theme_mod('contact_email'));

// Телефон: берём из кастомайзера, иначе запасной номер по умолчанию
$org_phone = $phone_mod ?: $whatsapp ?: '+79874684644';

// E-mail: берём из кастомайзера, иначе admin@<домен сайта>
if ($email_mod) {
    $org_email = $email_mod;
} else {
    $host      = parse_url($org_url, PHP_URL_HOST) ?: 'dosugmoskva24.com';
    $org_email = 'admin@' . $host;
}

// География: Россия / Москва
$area_served = [
    [
        '@type' => 'City',
        'name'  => 'Москва',
    ],
];

// Схема
$organization = [
    '@context'     => 'https://schema.org',
    '@type'        => 'Organization',
    '@id'          => rtrim($org_url, '/') . '#organization',
    'name'         => $org_name,
    'url'          => $org_url,
    'logo'         => $org_logo,
    'description'  => $org_description,
    'contactPoint' => [
        '@type'       => 'ContactPoint',
        'telephone'   => $org_phone,
        'contactType' => 'Customer Service',
        'email'       => $org_email,
        'areaServed'  => $area_served,
    ],
];

echo '<script type="application/ld+json">' .
    wp_json_encode($organization, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) .
    '</script>';
