<?php

declare(strict_types=1);

$links = [];
$post ??= get_post();
$meta ??= wp_get_attachment_metadata($post->ID);
$sizes = get_intermediate_image_sizes();

foreach ($sizes as $size) {
    $image = wp_get_attachment_image_src($post->ID, $size);
    if (!is_array($image)) {
        continue;
    }

    $links[] = sprintf(
        '<li>%1$s: <a class="image-size-link" href="%2$s">%3$s &times; %4$s</a> (<strong>%5$s</strong>)</li>',
        esc_html($size),
        esc_url($image[0]),
        esc_html($image[1]),
        esc_html($image[2]),
        esc_html(size_format($meta['sizes'][$size]['filesize'] ?? '')),
    );
}

// This attachment has been "scaled" automatically by WordPress.
if (isset($meta['original_image'])) {
    $original_image = wp_get_original_image_path($post->ID);
    if (!$original_image) {
        return;
    }
    $imagesize = wp_getimagesize($original_image);
    $links[] = sprintf(
        '<li>original_image: <a class="image-size-link" href="%1$s">%2$s &times; %3$s</a> (<strong>%4$s</strong>)</li>',
        esc_url(wp_get_original_image_url($post->ID)),
        esc_html($imagesize[0]),
        esc_html($imagesize[1]),
        esc_html(size_format(wp_filesize($original_image))),
    );
}

// Join the links in a string and return.
printf(
    '<div><ul>%1$s</ul></div>',
    implode('', $links)
);
