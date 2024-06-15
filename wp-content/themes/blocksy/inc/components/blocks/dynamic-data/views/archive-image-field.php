<?php

$aspectRatio = blocksy_akg('aspectRatio', $attributes, 'auto');
$height = blocksy_akg('height', $attributes, '');

$img_atts = [
	'style' => ''
];

// Aspect aspectRatio with a height set needs to override the default width/height.
if (! empty($aspectRatio)) {
	$img_atts['style'] .= 'width:100%;height:100%;';
} elseif (! empty($height) ) {
	$img_atts['style'] .= "height:{$attributes['height']};";
}

$img_atts['style'] .= "object-fit: cover;";

if (! empty(blocksy_akg('alt_text', $attributes, ''))) {
	$img_atts['alt'] = blocksy_akg('alt_text', $attributes, '');
}

$attachment_id = null;

$term_id = get_queried_object_id();

if ($term_id && is_archive()) {
    $id = get_term_meta($term_id, 'thumbnail_id');
    
    if ($id && !empty($id)) {
        $attachment_id = $id[0];
    }

    if (! $id) {
        $attachment_id = null;
    }

    $term_atts = get_term_meta(
        $term_id,
        'blocksy_taxonomy_meta_options'
    );

    if (empty($term_atts)) {
        $term_atts = [[]];
    }

    $maybe_image = blocksy_akg('image', $term_atts[0], '');

    if (
        $maybe_image
        &&
        is_array($maybe_image)
        &&
        isset($maybe_image['attachment_id'])
    ) {
        $attachment_id = $maybe_image['attachment_id'];
    }
}

if (empty($attachment_id)) {
	return;
}

$value = blocksy_media(
    [
        'attachment_id' => $attachment_id, 
        'size' => blocksy_akg('sizeSlug', $attributes, 'full'),
        'aspect_ratio' => $attributes['aspectRatio'],
        'img_atts' => $img_atts
    ]
);

if (empty($value)) {
	return;
}

$classes = [
	'wp-block-image'
];

$styles = [];

if (! empty($attributes['width'])) {
	$styles[] = 'width: ' . $attributes['width'] . ';';
}

if (! empty($attributes['height'])) {
	$styles[] = 'height: ' . $attributes['height'] . ';';
}

if (! empty($attributes['aspectRatio'])) {
	$styles[] = 'aspect-ratio: ' . $aspectRatio . ';';
}

if (! empty($attributes['imageAlign'])) {
	$classes[] = 'align' . $attributes['imageAlign'];
}

if (! empty($attributes['className'])) {
	$classes[] = $attributes['className'];
}

$border_result = get_block_core_post_featured_image_border_attributes(
	$attributes
);

if (! empty($border_result['class'])) {
	$classes[] = $border_result['class'];
}

if (! empty($border_result['style'])) {
	$styles[] = $border_result['style'];
}

$wrapper_attr = [
	'class' => 'ct-dynamic-media'
];

$wrapper_attr['style'] = implode(' ', $styles);


$wrapper_attr['class'] .= ' ' . implode(' ', $classes);

$tag_name = 'figure';

$wrapper_attr = get_block_wrapper_attributes($wrapper_attr);

echo blocksy_html_tag($tag_name, $wrapper_attr, $value);