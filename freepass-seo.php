<?php
/*
Plugin Name: FreePass SEO Meta Generator
Description: Generates meta tags, Open Graph, Schema.org, and canonical URLs for pages and posts.
Version: 1.8
*/

// Enqueue media library scripts
function freepass_seo_enqueue_admin_scripts($hook) {
    if (in_array($hook, ['edit.php', 'post.php', 'post-new.php', 'settings_page_freepass-seo-meta-generator'])) {
        wp_enqueue_media();
    }
}
add_action('admin_enqueue_scripts', 'freepass_seo_enqueue_admin_scripts');

// Detect the page type
function freepass_seo_get_schema_type($post) {
    // Check whether the schema type is set in the meta field
    $schema_type = get_post_meta($post->ID, '_freepass_seo_schema_type', true);

    if ($schema_type) {
        return $schema_type;
    }

    // Determine the schema type from the post type and other criteria
    if (is_front_page() || is_home()) {
        return 'WebSite';
    } elseif ($post->post_type == 'post') {
        return 'Article';
    } elseif ($post->post_type == 'page') {
        // Additional page type detection logic
        $slug = $post->post_name;
        if ($slug == 'contact') {
            return 'ContactPage';
        } elseif ($slug == 'about') {
            return 'AboutPage';
        } else {
            return 'WebPage';
        }
    } else {
        return 'WebPage';
    }
}

// Save metadata when the post is saved
function freepass_seo_save_meta_data($post_id, $post, $update) {
    // Security checks
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Check whether metadata auto-update is disabled
    $disable_auto_update = get_post_meta($post_id, '_freepass_seo_disable_auto_update', true);

    // Exit if auto-update is disabled
    if ($disable_auto_update) {
        return;
    }

    // Check the post type
    if ($post->post_type === 'post' || $post->post_type === 'page') {
        // Get post data
        $title = get_the_title($post_id);
        $content = $post->post_content;
        $auto_description = wp_trim_words(wp_strip_all_tags(strip_shortcodes($content)), 25, '...');
        $auto_image = get_the_post_thumbnail_url($post_id, 'full') ?: '';
        $author = get_the_author_meta('display_name', $post->post_author);
        $author_url = get_author_posts_url($post->post_author) ?: 'https://freepass.asia';
        $date_published = get_the_date('c', $post_id);
        $date_modified = get_the_modified_date('c', $post_id);
        $canonical_url = get_permalink($post_id);

        // Get metadata from user input
        $custom_meta_description = get_post_meta($post_id, '_freepass_seo_meta_description', true);
        $custom_og_title = get_post_meta($post_id, '_freepass_seo_og_title', true);
        $custom_og_description = get_post_meta($post_id, '_freepass_seo_og_description', true);
        $custom_og_image = get_post_meta($post_id, '_freepass_seo_og_image', true);

        // Use custom values if present
        $description = !empty($custom_meta_description) ? $custom_meta_description : $auto_description;
        $og_title = $title;
        $og_description = !empty($custom_og_description) ? $custom_og_description : $auto_description;
        $image = !empty($custom_og_image) ? $custom_og_image : $auto_image;

        // Save metadata for list columns and Quick Edit
        update_post_meta($post_id, '_freepass_seo_meta_description', $description);
        update_post_meta($post_id, '_freepass_seo_og_title', $og_title);
        update_post_meta($post_id, '_freepass_seo_og_description', $og_description);
        update_post_meta($post_id, '_freepass_seo_og_image', $image);

        // Generate meta tags HTML
        $meta_tags = '';
        $meta_tags .= '<html prefix="og: http://ogp.me/ns#">' . "\n";

        if ($description) {
            $meta_tags .= '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }
        if ($og_title) {
            $meta_tags .= '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";
        }
        if ($og_description) {
            $meta_tags .= '<meta property="og:description" content="' . esc_attr($og_description) . '">' . "\n";
        }
        if ($image) {
            $meta_tags .= '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
                $meta_tags .= '<meta property="og:image:width" content="1200">' . "\n";
                $meta_tags .= '<meta property="og:image:height" content="630">' . "\n";
                $meta_tags .= '<meta property="og:image:type" content="image/png">' . "\n";
        }
        $meta_tags .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
        $meta_tags .= '<meta name="twitter:title" content="' . esc_attr($og_title) . '">' . "\n";
        $meta_tags .= '<meta name="twitter:description" content="' . esc_attr($og_description) . '">' . "\n";
        $meta_tags .= '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
        $meta_tags .= '<meta property="og:type" content="article">' . "\n";
        $meta_tags .= '<meta property="og:url" content="' . esc_url($canonical_url) . '">' . "\n";

        // Determine the schema type
        $schema_type = freepass_seo_get_schema_type($post);

        // Generate Schema.org markup
        $schema = [
            "@context" => "https://schema.org",
            "@type" => $schema_type,
        ];

        // Get custom values from meta fields
        $alternative_headline = get_post_meta($post_id, '_freepass_seo_alternative_headline', true);
        $editor = get_post_meta($post_id, '_freepass_seo_editor', true);
        $keywords = get_post_meta($post_id, '_freepass_seo_keywords', true);
        $in_language = get_post_meta($post_id, '_freepass_seo_in_language', true);

        // Fill Schema.org data depending on the type
        if (in_array($schema_type, ['Article', 'BlogPosting'])) {
            $schema['headline'] = $og_title;
            if ($alternative_headline) {
                $schema['alternativeHeadline'] = $alternative_headline;
            }
            $schema['description'] = $description;
            $schema['image'] = $image;
            $schema['author'] = [
                "@type" => "Person",
                "name" => $author,
                "url" => $author_url
            ];
            if ($editor) {
                $schema['editor'] = [
                    "@type" => "Person",
                    "name" => $editor
                ];
            }
            $schema['datePublished'] = $date_published;
            $schema['dateModified'] = $date_modified;
            $schema['mainEntityOfPage'] = $canonical_url;
            if ($keywords) {
                $schema['keywords'] = $keywords;
            }
            $schema['articleSection'] = strip_tags(get_the_category_list(', ', '', $post_id)); // Categories
            $content_spaced = preg_replace('/<\/[^>]+>/', '$0 ', strip_shortcodes($content));
            $content_clean = wp_strip_all_tags($content_spaced);                                  
            $content_escaped = str_replace('"', '\"', $content_clean);                          
            $schema['articleBody'] = trim(preg_replace('/\s+/', ' ', $content_escaped));     
            $schema['wordCount'] = str_word_count(strip_tags($content));
            $schema['commentCount'] = get_comments_number($post_id);
        } elseif (in_array($schema_type, ['WebPage', 'AboutPage', 'ContactPage'])) {
            $schema['name'] = $og_title;
            $schema['description'] = $description;
            $schema['url'] = $canonical_url;
            $schema['datePublished'] = $date_published;
            $schema['dateModified'] = $date_modified;
            $schema['primaryImageOfPage'] = $image;
            if ($in_language) {
                $schema['inLanguage'] = $in_language;
            } else {
                $schema['inLanguage'] = get_bloginfo('language');
            }
        } elseif ($schema_type == 'Service') {
            $schema['name'] = $og_title;
            $schema['description'] = $description;
            $schema['provider'] = [
                '@type' => 'Organization',
                'name' => get_option('freepass_seo_org_name', 'FreePass Asia'),
                'url' => get_option('freepass_seo_org_url', home_url()),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => get_option('freepass_seo_org_logo', get_template_directory_uri() . '/images/logo.png')
                ]
            ];
            $service_type = get_post_meta($post_id, '_freepass_seo_service_type', true) ?: 'Booking';
            $area_served = get_post_meta($post_id, '_freepass_seo_area_served', true) ?: 'Asia';
            $price_range = get_post_meta($post_id, '_freepass_seo_price_range', true) ?: '$';

            $schema['serviceType'] = $service_type;
            $schema['areaServed'] = $area_served;
            $schema['priceRange'] = $price_range;
        }

        // Common static fields for Organization
        $organization = [
            '@type' => 'Organization',
            'name' => get_option('freepass_seo_org_name', 'FreePass Asia'),
            'url' => get_option('freepass_seo_org_url', home_url()),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => get_option('freepass_seo_org_logo', get_template_directory_uri() . '/images/logo.png')
            ],
            // Add social profiles
            'sameAs' => [
                'https://www.facebook.com/groups/freepass.asia',
                'https://www.instagram.com/freepass_asia/',
                'https://t.me/freepass_asia',
                'https://www.threads.net/@freepass_asia',
                'https://linkedin.com/company/freepass-asia'
            ],
            // Contact information
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => get_option('freepass_seo_org_phone', '+66 69 4538 8352'),
                'contactType' => 'Customer Service',
                'areaServed' => 'ASIA',
                'availableLanguage' => ['English']
            ]
        ];

        // Nest Organization into the main schema
        if ($schema_type != 'WebSite') {
            $schema['publisher'] = $organization;
        } else {
            $schema = array_merge($schema, $organization);
        }

        // Generate JSON-LD
        //$schema_org_json = wp_json_encode($schema);
        $schema_org_json = wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Add Schema.org to the meta tags
        $meta_tags .= '<script type="application/ld+json">' . $schema_org_json . '</script>' . "\n";

        // Save the meta tags HTML to a meta field
        update_post_meta($post_id, '_freepass_seo_meta_tags_html', $meta_tags);
    }
}
add_action('save_post', 'freepass_seo_save_meta_data', 10, 3);

// Output meta tags in <head>
function freepass_seo_output_meta_tags() {
    global $post;

    if (!isset($post)) {
        return;
    }

    // Get the saved meta tags HTML
    $meta_tags = '';

    if (is_singular(['post', 'page'])) {
        $meta_tags = get_post_meta($post->ID, '_freepass_seo_meta_tags_html', true);
    } elseif (is_front_page() || is_home()) {
        // Generate Schema.org for the front page
        $meta_tags = freepass_seo_generate_home_schema();
    }

    if ($meta_tags) {
        echo $meta_tags;
    }
}
// Priority 1 so the meta tags are output first
add_action('wp_head', 'freepass_seo_output_meta_tags', 1);

// Generate Schema.org for the front page
function freepass_seo_generate_home_schema() {
    // Generate WebSite markup
    $schema = [
        "@context" => "https://schema.org",
        "@type" => "WebSite",
        'name' => get_bloginfo('name'),
        'url' => home_url(),
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => home_url('/?s={search_term_string}'),
            'query-input' => 'required name=search_term_string'
        ],
        // Common static fields for Organization
        '@type' => 'Organization',
        'name' => get_option('freepass_seo_org_name', 'FreePass Asia'),
        'url' => get_option('freepass_seo_org_url', home_url()),
        'logo' => [
            '@type' => 'ImageObject',
            'url' => get_option('freepass_seo_org_logo', get_template_directory_uri() . '/images/logo.png')
        ],
        // Add social profiles
        'sameAs' => [
            'https://www.facebook.com/groups/freepass.asia',
            'https://www.instagram.com/freepass_asia/',
            'https://t.me/freepass_asia',
            'https://www.threads.net/@freepass_asia',
            'https://linkedin.com/company/freepass-asia'
        ],
        // Contact information
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'telephone' => get_option('freepass_seo_org_phone', '+66 69 4538 8352'),
            'contactType' => 'Customer Service',
            'areaServed' => 'ASIA',
            'availableLanguage' => ['English']
        ]
    ];

    //return '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
    return '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>' . "\n";
}

// Add new columns to the post list
function freepass_seo_add_custom_columns($columns) {
    $columns['meta_description'] = 'Meta Description';
    $columns['og_title'] = 'OG Title';
    $columns['og_description'] = 'OG Description';
    $columns['og_image'] = 'OG Image';
    $columns['disable_auto_update'] = 'Disable auto-update';
    return $columns;
}
add_filter('manage_post_posts_columns', 'freepass_seo_add_custom_columns');
add_filter('manage_page_posts_columns', 'freepass_seo_add_custom_columns');

// Fill the new columns with data
function freepass_seo_custom_column_content($column_name, $post_id) {
    if ($column_name == 'meta_description') {
        echo esc_html(get_post_meta($post_id, '_freepass_seo_meta_description', true));
    }
    if ($column_name == 'og_title') {
        echo esc_html(get_post_meta($post_id, '_freepass_seo_og_title', true));
    }
    if ($column_name == 'og_description') {
        echo esc_html(get_post_meta($post_id, '_freepass_seo_og_description', true));
    }
    if ($column_name == 'og_image') {
        $og_image = get_post_meta($post_id, '_freepass_seo_og_image', true);
        if ($og_image) {
            echo '<img src="' . esc_url($og_image) . '" alt="" style="max-width:50px;height:auto;">';
        }
    }
    if ($column_name == 'disable_auto_update') {
        $disable_auto_update = get_post_meta($post_id, '_freepass_seo_disable_auto_update', true);
        echo $disable_auto_update ? 'Yes' : 'No';
    }
}
add_action('manage_post_posts_custom_column', 'freepass_seo_custom_column_content', 10, 2);
add_action('manage_page_posts_custom_column', 'freepass_seo_custom_column_content', 10, 2);

// Add fields to Quick Edit
function freepass_seo_quick_edit_custom_box($column_name, $post_type) {
    if (in_array($post_type, ['post', 'page'])) {
        if (in_array($column_name, ['meta_description', 'og_title', 'og_description', 'og_image', 'disable_auto_update'])) {
            ?>
            <fieldset class="inline-edit-col-right">
                <div class="inline-edit-col">
                    <?php if ($column_name == 'disable_auto_update') : ?>
                        <label class="alignleft">
                            <input type="checkbox" name="disable_auto_update" value="1">
                            <span class="checkbox-title">Disable auto-update</span>
                        </label>
                    <?php else: ?>
                        <label>
                            <span class="title"><?php echo esc_html(ucwords(str_replace('_', ' ', $column_name))); ?></span>
                            <span class="input-text-wrap">
                                <?php if ($column_name == 'og_image') : ?>
                                    <input type="text" name="<?php echo esc_attr($column_name); ?>" value="" placeholder="Select an image" readonly style="width: 80%;">
                                    <button type="button" class="button select-image-button" data-target="<?php echo esc_attr($column_name); ?>">Select image</button>
                                <?php else : ?>
                                    <input type="text" name="<?php echo esc_attr($column_name); ?>" value="">
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endif; ?>
                </div>
            </fieldset>
            <?php
        }
    }
}
add_action('quick_edit_custom_box', 'freepass_seo_quick_edit_custom_box', 10, 2);

// Save Quick Edit data
function freepass_seo_save_quick_edit_data($post_id) {
    // Security checks
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;


    $fields = ['meta_description', 'og_title', 'og_description', 'og_image', 'disable_auto_update'];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            if ($field == 'disable_auto_update') {
                update_post_meta($post_id, '_freepass_seo_disable_auto_update', '1');
            } else {
                $value = sanitize_text_field($_POST[$field]);
                update_post_meta($post_id, '_freepass_seo_' . $field, $value);
            }
        } else {
            if ($field == 'disable_auto_update') {
                delete_post_meta($post_id, '_freepass_seo_disable_auto_update');
            }
        }
    }
}

add_action('save_post', 'freepass_seo_save_quick_edit_data');

// Add JavaScript for Quick Edit
function freepass_seo_quick_edit_javascript() {
    global $current_screen;
    if (($current_screen->post_type == 'post' || $current_screen->post_type == 'page') && $current_screen->base == 'edit') {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var wp_inline_edit = inlineEditPost.edit;
                inlineEditPost.edit = function(post_id) {
                    wp_inline_edit.apply(this, arguments);
                    var id = 0;
                    if (typeof(post_id) == 'object') {
                        id = parseInt(this.getId(post_id));
                    }
                    if (id > 0) {
                        var $edit_row = $('#edit-' + id);
                        var $post_row = $('#post-' + id);
                        var meta_description = $post_row.find('.column-meta_description').text();
                        var og_title = $post_row.find('.column-og_title').text();
                        var og_description = $post_row.find('.column-og_description').text();
                        var og_image = $post_row.find('.column-og_image img').attr('src') || '';
                        var disable_auto_update = $post_row.find('.column-disable_auto_update').text().trim() === 'Yes';

                        $edit_row.find('input[name="meta_description"]').val(meta_description);
                        $edit_row.find('input[name="og_title"]').val(og_title);
                        $edit_row.find('input[name="og_description"]').val(og_description);
                        $edit_row.find('input[name="og_image"]').val(og_image);
                        $edit_row.find('input[name="disable_auto_update"]').prop('checked', disable_auto_update);
                    }
                };

                // Open the media library on button click
                $(document).on('click', '.select-image-button', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var target = button.data('target');
                    var input = $('input[name="' + target + '"]');
                    var frame = wp.media({
                        title: 'Select an image',
                        button: {
                            text: 'Use this image'
                        },
                        multiple: false
                    });
                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        input.val(attachment.url);
                    });
                    frame.open();
                });
            });
        </script>
        <?php
    }
}
add_action('admin_footer', 'freepass_seo_quick_edit_javascript');

// Add styles for Quick Edit
function freepass_seo_quick_edit_styles() {
    global $current_screen;
    if (($current_screen->post_type == 'post' || $current_screen->post_type == 'page') && $current_screen->base == 'edit') {
        ?>
        <style type="text/css">
            .inline-edit-col-right label span.title {
                width: 100px;
            }
            .inline-edit-col-right label span.input-text-wrap {
                display: inline-block;
            }
            .column-og_image img {
                max-width: 50px;
                height: auto;
            }
            .select-image-button {
                margin-left: 5px;
                vertical-align: top;
            }
            .inline-edit-col-right input[name="og_image"] {
                width: calc(100% - 110px);
            }
            .inline-edit-col label.alignleft {
                width: 100%;
                margin-bottom: 5px;
            }
            .inline-edit-col .checkbox-title {
                margin-left: 5px;
            }
        </style>
        <?php
    }
}
add_action('admin_head', 'freepass_seo_quick_edit_styles');

// Add meta boxes for Schema.org and Meta Tags
function freepass_seo_add_meta_boxes() {
    add_meta_box(
        'freepass_seo_schema_meta',
        'Schema.org Metadata',
        'freepass_seo_schema_meta_box_callback',
        ['post', 'page'],
        'normal',
        'high'
    );

    add_meta_box(
        'freepass_seo_meta_tags',
        'Meta Tags',
        'freepass_seo_meta_tags_meta_box_callback',
        ['post', 'page'],
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'freepass_seo_add_meta_boxes');

// Schema.org meta box callback
function freepass_seo_schema_meta_box_callback($post) {
    wp_nonce_field('freepass_seo_schema_meta_box', 'freepass_seo_schema_meta_box_nonce');

    // Get current metadata values
    $schema_type = get_post_meta($post->ID, '_freepass_seo_schema_type', true);
    $service_type = get_post_meta($post->ID, '_freepass_seo_service_type', true);
    $area_served = get_post_meta($post->ID, '_freepass_seo_area_served', true);
    $price_range = get_post_meta($post->ID, '_freepass_seo_price_range', true);
    $alternative_headline = get_post_meta($post->ID, '_freepass_seo_alternative_headline', true);
    $editor = get_post_meta($post->ID, '_freepass_seo_editor', true);
    $keywords = get_post_meta($post->ID, '_freepass_seo_keywords', true);
    $in_language = get_post_meta($post->ID, '_freepass_seo_in_language', true);
    $disable_auto_update = get_post_meta($post->ID, '_freepass_seo_disable_auto_update', true);

    // Meta box fields
    ?>
    <p>
        <label for="freepass_seo_schema_type">Schema.org type:</label>
        <select name="freepass_seo_schema_type" id="freepass_seo_schema_type">
            <option value="">Auto-detect</option>
            <option value="Article" <?php selected($schema_type, 'Article'); ?>>Article</option>
            <option value="BlogPosting" <?php selected($schema_type, 'BlogPosting'); ?>>BlogPosting</option>
            <option value="WebPage" <?php selected($schema_type, 'WebPage'); ?>>WebPage</option>
            <option value="AboutPage" <?php selected($schema_type, 'AboutPage'); ?>>AboutPage</option>
            <option value="ContactPage" <?php selected($schema_type, 'ContactPage'); ?>>ContactPage</option>
            <option value="Service" <?php selected($schema_type, 'Service'); ?>>Service</option>
        </select>
    </p>
    <!-- Common fields -->
    <p>
        <label for="freepass_seo_alternative_headline">Alternative headline:</label>
        <input type="text" name="freepass_seo_alternative_headline" id="freepass_seo_alternative_headline" value="<?php echo esc_attr($alternative_headline); ?>">
    </p>
    <p>
        <label for="freepass_seo_editor">Article editor:</label>
        <input type="text" name="freepass_seo_editor" id="freepass_seo_editor" value="<?php echo esc_attr($editor); ?>">
    </p>
    <p>
        <label for="freepass_seo_keywords">Keywords:</label>
        <input type="text" name="freepass_seo_keywords" id="freepass_seo_keywords" value="<?php echo esc_attr($keywords); ?>">
    </p>
    <p>
        <label for="freepass_seo_in_language">Page language:</label>
        <input type="text" name="freepass_seo_in_language" id="freepass_seo_in_language" value="<?php echo esc_attr($in_language); ?>">
    </p>
    <!-- Service fields -->
    <div id="service-fields" <?php if ($schema_type != 'Service') echo 'style="display:none;"'; ?>>
        <p>
            <label for="freepass_seo_service_type">Service type:</label>
            <input type="text" name="freepass_seo_service_type" id="freepass_seo_service_type" value="<?php echo esc_attr($service_type); ?>">
        </p>
        <p>
            <label for="freepass_seo_area_served">Area served:</label>
            <input type="text" name="freepass_seo_area_served" id="freepass_seo_area_served" value="<?php echo esc_attr($area_served); ?>">
        </p>
        <p>
            <label for="freepass_seo_price_range">Price range:</label>
            <input type="text" name="freepass_seo_price_range" id="freepass_seo_price_range" value="<?php echo esc_attr($price_range); ?>">
        </p>
    </div>
    <!-- Checkbox to disable auto-update -->
    <p>
        <label>
            <input type="checkbox" name="freepass_seo_disable_auto_update" value="1" <?php checked($disable_auto_update, '1'); ?>>
            Disable metadata auto-update
        </label>
    </p>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#freepass_seo_schema_type').change(function() {
                if ($(this).val() == 'Service') {
                    $('#service-fields').show();
                } else {
                    $('#service-fields').hide();
                }
            });
        });
    </script>
    <?php
}

// Save Schema.org meta box data
function freepass_seo_save_schema_meta_box_data($post_id) {
    // Security checks
    if (!isset($_POST['freepass_seo_schema_meta_box_nonce'])) {
        return;
    }
    if (!wp_verify_nonce($_POST['freepass_seo_schema_meta_box_nonce'], 'freepass_seo_schema_meta_box')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save data
    $fields = [
        'freepass_seo_schema_type',
        'freepass_seo_service_type',
        'freepass_seo_area_served',
        'freepass_seo_price_range',
        'freepass_seo_alternative_headline',
        'freepass_seo_editor',
        'freepass_seo_keywords',
        'freepass_seo_in_language'
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
        } else {
            delete_post_meta($post_id, '_' . $field);
        }
    }

    // Save the checkbox value
    $disable_auto_update = isset($_POST['freepass_seo_disable_auto_update']) ? '1' : '';
    update_post_meta($post_id, '_freepass_seo_disable_auto_update', $disable_auto_update);
}

// Meta Tags meta box callback
function freepass_seo_meta_tags_meta_box_callback($post) {
    wp_nonce_field('freepass_seo_meta_tags_meta_box', 'freepass_seo_meta_tags_meta_box_nonce');

    // Get current metadata values
    $meta_description = get_post_meta($post->ID, '_freepass_seo_meta_description', true);
    $og_title = get_post_meta($post->ID, '_freepass_seo_og_title', true);
    $og_description = get_post_meta($post->ID, '_freepass_seo_og_description', true);
    $og_image = get_post_meta($post->ID, '_freepass_seo_og_image', true);

    ?>
    <p>
        <label for="freepass_seo_meta_description">Meta Description:</label>
        <textarea name="freepass_seo_meta_description" id="freepass_seo_meta_description" rows="2" style="width:100%;"><?php echo esc_textarea($meta_description); ?></textarea>
    </p>
    <p>
        <label for="freepass_seo_og_title">OG Title:</label>
        <input type="text" name="freepass_seo_og_title" id="freepass_seo_og_title" value="<?php echo esc_attr($og_title); ?>" style="width:100%;">
    </p>
    <p>
        <label for="freepass_seo_og_description">OG Description:</label>
        <textarea name="freepass_seo_og_description" id="freepass_seo_og_description" rows="2" style="width:100%;"><?php echo esc_textarea($og_description); ?></textarea>
    </p>
    <p>
        <label for="freepass_seo_og_image">OG Image:</label>
        <input type="text" name="freepass_seo_og_image" id="freepass_seo_og_image" value="<?php echo esc_attr($og_image); ?>" style="width:80%;" readonly>
        <button type="button" class="button select-og-image-button">Select image</button>
    </p>
    <script type="text/javascript">
        jQuery(document).ready(function($){
            $('.select-og-image-button').click(function(e) {
                e.preventDefault();
                var frame = wp.media({
                    title: 'Select an image',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#freepass_seo_og_image').val(attachment.url);
                });
                frame.open();
            });
        });
    </script>
    <?php
}

// Save Meta Tags meta box data
function freepass_seo_save_meta_tags_meta_box_data($post_id) {
    // Security checks
    if (!isset($_POST['freepass_seo_meta_tags_meta_box_nonce'])) return;
    if (!wp_verify_nonce($_POST['freepass_seo_meta_tags_meta_box_nonce'], 'freepass_seo_meta_tags_meta_box')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Save data
    $fields = [
        'freepass_seo_meta_description',
        'freepass_seo_og_title',
        'freepass_seo_og_description',
        'freepass_seo_og_image',
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
        } else {
            delete_post_meta($post_id, '_' . $field);
        }
    }
}

// Save meta box data
add_action('save_post', 'freepass_seo_save_schema_meta_box_data');
add_action('save_post', 'freepass_seo_save_meta_tags_meta_box_data');

// Add the plugin settings page
function freepass_seo_add_admin_menu() {
    add_options_page(
        'FreePass SEO Meta Generator',
        'FreePass SEO Meta Generator',
        'manage_options',
        'freepass-seo-meta-generator',
        'freepass_seo_options_page'
    );
}
add_action('admin_menu', 'freepass_seo_add_admin_menu');

// Settings page
function freepass_seo_options_page() {
    ?>
    <div class="wrap">
        <h1>FreePass SEO Meta Generator Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('freepass_seo_settings_group');
            do_settings_sections('freepass_seo_settings_group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Organization name</th>
                    <td><input type="text" name="freepass_seo_org_name" value="<?php echo esc_attr(get_option('freepass_seo_org_name')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Organization URL</th>
                    <td><input type="text" name="freepass_seo_org_url" value="<?php echo esc_attr(get_option('freepass_seo_org_url')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Organization logo</th>
                    <td>
                        <input type="text" name="freepass_seo_org_logo" id="freepass_seo_org_logo" value="<?php echo esc_attr(get_option('freepass_seo_org_logo')); ?>" />
                        <button type="button" class="button select-logo-button">Select logo</button>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Phone</th>
                    <td><input type="text" name="freepass_seo_org_phone" value="<?php echo esc_attr(get_option('freepass_seo_org_phone')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Contact email</th>
                    <td><input type="email" name="freepass_seo_org_email" value="<?php echo esc_attr(get_option('freepass_seo_org_email')); ?>" /></td>
                </tr>
                <!-- Add more fields here -->
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function($){
            $('.select-logo-button').click(function(e) {
                e.preventDefault();
                var frame = wp.media({
                    title: 'Select a logo',
                    button: {
                        text: 'Use this logo'
                    },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#freepass_seo_org_logo').val(attachment.url);
                });
                frame.open();
            });
        });
    </script>
    <?php
}

// Register settings
function freepass_seo_register_settings() {
    register_setting('freepass_seo_settings_group', 'freepass_seo_org_name');
    register_setting('freepass_seo_settings_group', 'freepass_seo_org_url');
    register_setting('freepass_seo_settings_group', 'freepass_seo_org_logo');
    register_setting('freepass_seo_settings_group', 'freepass_seo_org_phone');
    register_setting('freepass_seo_settings_group', 'freepass_seo_org_email');
    // Register more fields here
}
add_action('admin_init', 'freepass_seo_register_settings');
?>
