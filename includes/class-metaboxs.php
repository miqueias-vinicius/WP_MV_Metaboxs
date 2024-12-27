<?php

if (!class_exists('WP_MV_Metabox')) {
    class WP_MV_Metabox
    {
        private $id;
        private $title;
        private $post_type;
        private $groups = [];

        public function __construct($post_type, $args = array())
        {
            $this->id = $args['id'];
            $this->title = $args['title'];
            $this->post_type = $post_type;

            add_action("admin_enqueue_scripts", [$this, "assets"]);
            add_action("add_meta_boxes", [$this, "add_meta_box"]);
            add_action("save_post", [$this, "save_meta_box"]);
            add_action('rest_api_init', [$this, 'register_rest_routes']);
        }

        public function assets()
        {
            wp_enqueue_style("wp-mv-metaboxs", WP_MV_URL . "css/style.css", array(), time(), 'all');

            wp_enqueue_style(
                "wp-mv-metaboxs-icons",
                "https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
            );

            wp_enqueue_style(
                "wp-mv-metaboxs-selectize",
                "https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.15.2/css/selectize.default.min.css"
            );

            wp_enqueue_script(
                "wp-mv-metaboxs-jquery",
                "https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"
            );

            wp_enqueue_script(
                "wp-mv-metaboxs-selectize",
                "https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.15.2/js/selectize.min.js"
            );

            wp_enqueue_script(
                "wp-mv-metaboxs",
                WP_MV_URL . "/js/script.js",
                array(),
                time(),
                array()
            );
        }

        public function add_meta_box()
        {
            add_meta_box(
                $this->id,
                $this->title,
                [$this, "render_metabox"],
                $this->post_type,
                "normal",
                "high"
            );
        }

        public function add_group($id, $args = array())
        {
            $this->groups[$id] = [
                'label' => $args['label'],
                'icon' => $args['icon'] ?? 'edit',
                'items' => []
            ];
        }

        public function add_field($group, $args = array())
        {
            if (!isset($this->groups[$group])) {
                throw new Exception("O grupo '{$group}' não existe.");
            }

            $this->groups[$group]['items'][$args['id']] = [
                'type' => $args['type'],
                'label' => $args['label'],
                'options' => $args['options'] ?? [],
                'mask' => $args['mask'] ?? '',
                'fields' => $args['fields'] ?? [],
                'sanitize_callback' => $args['sanitize_callback'] ?? 'sanitize_text_field',
            ];
        }

        private function metaboxs()
        {
            return $this->groups;
        }

        public function render_metabox($post)
        {
            $metaboxs = $this->metaboxs();

            echo "<style> #{$this->id} .inside { padding: 0; margin: 0; } </style>";
?>
            <div class="wp_mv_metaboxs">
                <ul class="wp_mv_metaboxs__sidebar">
                    <?php foreach ($metaboxs as $metabox => $attr): ?>
                        <li class="wp_mv_metaboxs__sidebar__item" data-tab="#<?php echo esc_attr($metabox); ?>">
                            <a class="wp-mv-tab" href="#">
                                <span class="wp-mv-tab-icon material-symbols-outlined"><?php echo $attr['icon']; ?></span>
                                <div class="wp-mv-tab-info">
                                    <span class="wp-mv-tab-label"><?php echo esc_html($attr["label"]); ?></span>
                                    <span class="wp-mv-tab-description"><?php echo (array_key_exists("description", $attr)) ?? esc_html($attr["description"]); ?></span>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="wp_mv_metaboxs__content">
                    <?php foreach ($metaboxs as $metabox => $attr): ?>
                        <div class="wp_mv_metaboxs__tab" id="<?php echo esc_attr($metabox); ?>">
                            <div class="wp_mv_metaboxs__header">
                                <h2 class="wp_mv_metaboxs__title"><?php echo esc_html($attr["label"]); ?></h2>
                            </div>
                            <div class="wp_mv_metaboxs__items">
                                <?php foreach ($attr["items"] as $name => $field): ?>
                                    <?php $value = get_post_meta($post->ID, $name, true); ?>
                                    <?php $this->render_field($name, $field, $value); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
        }

        private function render_field($name, $field, $value)
        {

            $mask = isset($field["mask"]) ? "aria-mask='{$field["mask"]}'" : "";

            switch ($field["type"]) {
                case "checkbox":
                    $checked = checked($value, '1', false);
                    echo "<div class='group'>";
                    echo "<input type='hidden' name='{$name}' value='0' />";
                    echo "<label for='{$name}'>{$field["label"]}</label>";
                    echo "<input {$mask} type='checkbox' id='{$name}' name='{$name}' value='1' {$checked} />";
                    echo "</div>";
                    break;
                case "textarea":
                    echo "<div class='group'>";
                    echo "<label for='{$name}'>{$field["label"]}</label>";
                    echo "<textarea {$mask} id='{$name}' name='{$name}'>" . esc_textarea($value) . "</textarea>";
                    echo "</div>";
                    break;
                case "editor":
                    echo "<div class='group'>";
                    echo "<label for='{$name}'>{$field["label"]}</label>";
                    $editor_settings = [
                        'textarea_name' => $name,
                        'media_buttons' => false,
                        'textarea_rows' => 10,
                        'teeny'         => true,
                        'quicktags'     => true
                    ];
                    wp_editor($value, $name, $editor_settings);
                    echo "</div>";
                    break;
                case "select":
                    echo "<div class='group'>";
                    echo "<label for='{$name}'>{$field["label"]}</label>";
                    echo "<select {$mask} id='{$name}' name='{$name}'>";
                    echo "<option value='no_set'>Selecione uma opção</option>";

                    foreach ($field['options'] as $option_value => $option_label) {
                        $selected = selected($value, $option_value, false);
                        echo "<option value='{$option_value}' {$selected}>{$option_label}</option>";
                    }

                    echo "</select>";
                    echo "</div>";
                    echo "<script>
                    jQuery(document).ready(function($) {
                        $('#{$name}').selectize({
                            maxItems: 1,
                            valueField: 'value',
                            labelField: 'text',
                            searchField: 'text',
                            create: false
                        });
                    });
                </script>";
                    break;
                case "media":
                    echo "<div class='group'>";
                    echo "<label for='{$name}'>{$field['label']}</label>";
                    echo "<div>";
                    echo "<div id='{$name}_preview' style='margin-top: 10px; " . ($value ? '' : 'display: none;') . "'>";
                    if ($value) {
                        $file_extension = pathinfo($value, PATHINFO_EXTENSION);
                        $video_extensions = ['mp4', 'mov', 'avi', 'mkv', 'flv'];

                        if (in_array(strtolower($file_extension), $video_extensions)) {
                            echo "<video width='131' height='131' controls>
                                        <source src='" . esc_url($value) . "' type='video/" . esc_attr($file_extension) . "'>
                                        Seu navegador não suporta o elemento de vídeo.
                                      </video>";
                        } else {
                            echo "<img src='" . esc_url($value) . "' style='width: 131px; height: 131px; object-fit: cover;'>";
                        }
                    }
                    echo "</div>";
                    echo "<input type='hidden' id='{$name}' name='{$name}' value='" . esc_attr($value) . "'>";
                    echo "<button type='button' id='{$name}_button' class='button button-primary'>Enviar mídia</button>";
                    echo "<button type='button' id='{$name}_remove_button' class='button' style='display: " . ($value ? 'inline' : 'none') . ";'>Excluir</button>";
                    echo "</div>";
                    echo "</div>";
            ?>
                    <script>
                        (function($) {
                            $(document).ready(function() {
                                const mediaField = $('#<?php echo $name; ?>');
                                const mediaPreview = $('#<?php echo $name; ?>_preview');
                                const uploadButton = $('#<?php echo $name; ?>_button');
                                const removeButton = $('#<?php echo $name; ?>_remove_button');

                                uploadButton.on('click', function(e) {
                                    e.preventDefault();
                                    const mediaUploader = wp.media({
                                        title: 'Selecione uma mídia',
                                        button: {
                                            text: 'Selecionar'
                                        },
                                        multiple: false
                                    }).on('select', function() {
                                        const attachment = mediaUploader.state().get('selection').first().toJSON();
                                        const url = attachment.url;
                                        const file_extension = url.split('.').pop().toLowerCase();
                                        mediaField.val(url);

                                        if (['mp4', 'mov', 'avi', 'mkv', 'flv'].includes(file_extension)) {
                                            mediaPreview.html(`<video width='131' height='131' controls>
                                                                <source src="${url}" type="video/${file_extension}">
                                                                Seu navegador não suporta o elemento de vídeo.
                                                              </video>`).show();
                                        } else {
                                            mediaPreview.html(`<img src="${url}" style="width: 131px; height: 131px; object-fit: cover;">`).show();
                                        }
                                        removeButton.show();
                                    }).open();
                                });

                                removeButton.on('click', function(e) {
                                    e.preventDefault();
                                    mediaField.val('');
                                    mediaPreview.hide();
                                    removeButton.hide();
                                });
                            });
                        })(jQuery);
                    </script>
                <?php
                    break;
                case "gallery":
                    echo "<div class='group gallery-group'>";
                    echo "<label for='{$name}'>{$field['label']}</label>";
                    echo "<div id='{$name}_gallery_wrapper' class='gallery-wrapper'>";

                    if (!empty($value) && is_array($value)) {
                        foreach ($value as $index => $url) {
                            $file_extension = pathinfo($url, PATHINFO_EXTENSION);
                            $video_extensions = ['mp4', 'mov', 'avi', 'mkv', 'flv'];
                            echo "<div class='gallery-item' data-index='{$index}'>";
                            if (in_array(strtolower($file_extension), $video_extensions)) {
                                echo "<video width='131' height='131' controls>
                                            <source src='" . esc_url($url) . "' type='video/" . esc_attr($file_extension) . "'>
                                            Seu navegador não suporta o elemento de vídeo.
                                          </video>";
                            } else {
                                echo "<img src='" . esc_url($url) . "' style='width: 131px; height: 131px; object-fit: cover;'>";
                            }
                            echo "<input type='hidden' name='{$name}[]' value='" . esc_attr($url) . "'>";
                            echo "<button type='button' class='button remove-gallery-item'>Remover</button>";
                            echo "</div>";
                        }
                    }

                    echo "</div>";
                    echo "<button type='button' id='{$name}_add_button' class='button button-primary'>Adicionar mídia</button>";
                    echo "</div>";
                ?>
                    <script>
                        (function($) {
                            $(document).ready(function() {
                                const galleryWrapper = $('#<?php echo $name; ?>_gallery_wrapper');
                                const addButton = $('#<?php echo $name; ?>_add_button');

                                addButton.on('click', function(e) {
                                    e.preventDefault();
                                    const mediaUploader = wp.media({
                                        title: 'Selecione mídias',
                                        button: {
                                            text: 'Adicionar'
                                        },
                                        multiple: true
                                    }).on('select', function() {
                                        const attachments = mediaUploader.state().get('selection').toJSON();
                                        attachments.forEach(function(attachment) {
                                            const url = attachment.url;
                                            const file_extension = url.split('.').pop().toLowerCase();
                                            const index = galleryWrapper.children('.gallery-item').length;

                                            let galleryItem = `<div class="gallery-item" data-index="${index}">`;
                                            if (['mp4', 'mov', 'avi', 'mkv', 'flv'].includes(file_extension)) {
                                                galleryItem += `<video width='131' height='131' controls>
                                                                        <source src="${url}" type="video/${file_extension}">
                                                                        Seu navegador não suporta o elemento de vídeo.
                                                                    </video>`;
                                            } else {
                                                galleryItem += `<img src="${url}" style="width: 131px; height: 131px; object-fit: cover;">`;
                                            }
                                            galleryItem += `<input type="hidden" name="<?php echo $name; ?>[]" value="${url}">`;
                                            galleryItem += `<button type="button" class="button remove-gallery-item">Remover</button>`;
                                            galleryItem += `</div>`;

                                            galleryWrapper.append(galleryItem);
                                        });
                                    }).open();
                                });

                                galleryWrapper.on('click', '.remove-gallery-item', function(e) {
                                    e.preventDefault();
                                    $(this).closest('.gallery-item').remove();
                                });
                            });
                        })(jQuery);
                    </script>
                <?php
                    break;
                case "post_type":
                    if (!empty($field['options']['post_type'])) {

                        $posts = new WP_Query(array(
                            'post_type' => $field['options']['post_type'],
                            'posts_per_page' => -1
                        ));

                        if (!is_wp_error($posts) && $posts->have_posts()) {
                            echo "<div class='group'>";
                            echo "<label for='{$name}'>{$field["label"]}</label>";
                            echo "<select {$mask} id='{$name}' name='{$name}' class='selectize'>";
                            echo "<option value='no_set'>Selecione uma opção</option>";

                            while ($posts->have_posts()) {
                                $posts->the_post();

                                $id = get_the_ID();
                                $title = get_the_title();

                                $selected = selected($value, $id, false);
                                echo "<option value='{$id}' {$selected}>{$title}</option>";
                            }
                            wp_reset_postdata();

                            echo "</select>";
                            echo "</div>";

                            echo "<script>
                                    jQuery(document).ready(function($) {
                                        $('#{$name}').selectize({
                                            maxItems: 1,
                                            valueField: 'value',
                                            labelField: 'text',
                                            searchField: 'text',
                                            create: false
                                        });
                                    });
                                </script>";
                        } else {
                            echo "<div class='group'>";
                            echo "<label>{$field["label"]}</label>";
                            echo "<em>Não foram encontrados posts para '{$field['options']['post_type']}'</em>";
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='group'>";
                        echo "<label>{$field["label"]}</label>";
                        echo "<em>O tipo de post não foi especificada no campo.</em>";
                        echo "</div>";
                    }
                    break;
                case "taxonomy":
                    if (!empty($field['options']['taxonomy'])) {
                        $terms = get_terms([
                            'taxonomy' => $field['options']['taxonomy'],
                            'hide_empty' => false,
                        ]);

                        if (!is_wp_error($terms) && !empty($terms)) {
                            echo "<div class='group'>";
                            echo "<label for='{$name}'>{$field["label"]}</label>";
                            echo "<select {$mask} id='{$name}' name='{$name}'>";
                            echo "<option value='no_set'>Selecione uma opção</option>";

                            foreach ($terms as $term) {
                                $selected = selected($value, $term->term_id, false);
                                echo "<option value='{$term->term_id}' {$selected}>{$term->name}</option>";
                            }

                            echo "</select>";
                            echo "</div>";

                            echo "<script>
                            jQuery(document).ready(function($) {
                                $('#{$name}').selectize({
                                    maxItems: 1,
                                    valueField: 'value',
                                    labelField: 'text',
                                    searchField: 'text',
                                    create: false
                                });
                            });
                        </script>";
                        } else {
                            echo "<div class='group'>";
                            echo "<label>{$field["label"]}</label>";
                            echo "<em>Não foram encontrados termos para a taxonomia '{$field['options']['taxonomy']}'</em>";
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='group'>";
                        echo "<label>{$field["label"]}</label>";
                        echo "<em>A taxonomia não foi especificada no campo.</em>";
                        echo "</div>";
                    }
                    break;
                case "users":
                    if (!empty($field['options']['role'])) {

                        $args = array(
                            'role__in'    => $field['options']['role'],
                            'orderby' => 'display_name',
                            'order'   => 'ASC',
                        );

                        $users = get_users($args);

                        if (!empty($users)) {
                            echo "<div class='group'>";
                            echo "<label for='{$name}'>{$field["label"]}</label>";
                            echo "<select {$mask} id='{$name}' name='{$name}' class='selectize'>";
                            echo "<option value='no_set'>Selecione um usuário</option>";

                            foreach ($users as $user) {
                                $id = $user->ID;
                                $display_name = $user->display_name;

                                $selected = selected($value, $id, false);
                                echo "<option value='{$id}' {$selected}>{$display_name}</option>";
                            }

                            echo "</select>";
                            echo "</div>";

                            echo "<script>
                                        jQuery(document).ready(function($) {
                                            $('#{$name}').selectize({
                                                maxItems: 1,
                                                valueField: 'value',
                                                labelField: 'text',
                                                searchField: 'text',
                                                create: false
                                            });
                                        });
                                    </script>";
                        } else {
                            echo "<div class='group'>";
                            echo "<label>{$field["label"]}</label>";
                            echo "<em>Não foram encontrados usuários com o papel '{$field['options']['role']}'</em>";
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='group'>";
                        echo "<label>{$field["label"]}</label>";
                        echo "<em>O papel do usuário não foi especificado no campo.</em>";
                        echo "</div>";
                    }
                    break;
                case "multi":
                    echo "<div class='group multi-field'>";
                    echo "<label>{$field["label"]}</label>";

                    echo "<button type='button' class='button add-multi-group'>Adicionar</button>";

                    echo "<div class='multi-groups'>";
                    if (!empty($value) && is_array($value)) {
                        foreach ($value as $index => $group) {
                            echo "<div class='multi-group' data-index='{$index}'>";
                            foreach ($field['fields'] as $sub_field) {
                                $sub_name = "{$name}[{$index}][{$sub_field['id']}]";
                                $sub_value = isset($group[$sub_field['id']]) ? $group[$sub_field['id']] : '';
                                $this->render_field($sub_name, $sub_field, $sub_value);
                            }
                            echo "<button type='button' class='button remove-multi-group'>Excluir</button>";
                            echo "</div>";
                        }
                    }
                    echo "</div>";
                    echo "</div>";

                ?>
                    <script>
                        (function($) {
                            $(document).ready(function() {
                                $('.multi-field').each(function() {
                                    const $multiField = $(this);
                                    const $wrapper = $multiField.find('.multi-groups');
                                    const $addButton = $multiField.find('.add-multi-group');

                                    function getNextIndex() {
                                        let nextIndex = 0;
                                        $wrapper.find('.multi-group').each(function() {
                                            const index = $(this).data('index');
                                            if (index >= nextIndex) {
                                                nextIndex = index + 1;
                                            }
                                        });
                                        return nextIndex;
                                    }

                                    $addButton.on('click', function(e) {
                                        e.preventDefault();

                                        const index = getNextIndex();

                                        let newGroup = '<div class="multi-group" data-index="' + index + '">';

                                        <?php foreach ($field['fields'] as $sub_field) { ?>
                                            newGroup += `
                            <div class="group">
                                <label for="${'<?php echo $name; ?>'}[${index}][<?php echo $sub_field['id']; ?>]">
                                    <?php echo $sub_field['label']; ?>
                                </label>
                                <input type="<?php echo $sub_field['type']; ?>"
                                       name="<?php echo $name; ?>[${index}][<?php echo $sub_field['id']; ?>]"
                                       value="" />
                            </div>
                        `;
                                        <?php } ?>

                                        newGroup += `
                        <button type="button" class="button remove-multi-group">Remover</button>
                    </div>`;

                                        $wrapper.append(newGroup);
                                    });

                                    $wrapper.on('click', '.remove-multi-group', function(e) {
                                        e.preventDefault();
                                        const $group = $(this).closest('.multi-group');
                                        $group.remove();
                                    });
                                });
                            });
                        })(jQuery);
                    </script>
<?php
                    break;
                default:
                    echo "<div class='group'>";
                    echo "<label for='{$name}'>{$field["label"]}</label>";
                    echo "<input {$mask} id='{$name}' type='{$field["type"]}' name='{$name}' value='" . esc_attr($value) . "' />";
                    echo "</div>";
                    break;
            }
        }

        public function save_meta_box($post_id)
        {
            if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
                return;
            }

            if (!current_user_can('edit_post', $post_id)) {
                return;
            }

            $metaboxs = $this->metaboxs();

            foreach ($metaboxs as $metabox) {
                foreach ($metabox['items'] as $name => $field) {
                    if (isset($_POST[$name])) {
                        if ($field['type'] === 'multi' && is_array($_POST[$name])) {
                            $multi_values = array_map(function ($group) use ($field) {
                                $sanitized_group = [];
                                foreach ($field['fields'] as $sub_field) {
                                    $sub_field_id = $sub_field['id'];
                                    $sanitize_sub_callback = $sub_field['sanitize_callback'] ?? 'sanitize_text_field';
                                    if ($sub_field['type'] === 'editor') {
                                        $sanitize_sub_callback = 'wp_kses_post';
                                    }
                                    $sanitized_group[$sub_field_id] = isset($group[$sub_field_id])
                                        ? call_user_func($sanitize_sub_callback, $group[$sub_field_id])
                                        : '';
                                }
                                return $sanitized_group;
                            }, $_POST[$name]);

                            update_post_meta($post_id, $name, $multi_values);
                        } elseif ($field['type'] === 'gallery' && is_array($_POST[$name])) {
                            $gallery_values = array_map('esc_url_raw', $_POST[$name]);
                            update_post_meta($post_id, $name, $gallery_values);
                        } else {
                            $sanitize_callback = $field['sanitize_callback'] ?? 'sanitize_text_field';
                            if ($field['type'] === 'editor') {
                                $sanitize_callback = 'wp_kses_post';
                            }
                            $value = call_user_func($sanitize_callback, $_POST[$name]);
                            update_post_meta($post_id, $name, $value);
                        }
                    } else {
                        delete_post_meta($post_id, $name);
                    }
                }
            }
        }

        public function register_rest_routes()
        {
            register_rest_route(
                "wp-mv/v1",
                "{$this->post_type}",
                [
                    "methods" => "GET",
                    "callback" => [$this, "api_get_metabox_item_data"],
                ]
            );

            register_rest_route(
                "wp-mv/v1",
                "{$this->post_type}",
                [
                    "methods" => "POST",
                    "callback" => [$this, "api_update_metabox_item_data"],
                ]
            );
        }

        public function api_get_metabox_item_data(WP_REST_Request $request)
        {
            $post_id = $request->get_param('post_id');

            if (!get_post($post_id)) {
                return new WP_REST_Response(['error' => 'O ID do post informado é inválido ou não existe'], 403);
            }

            $metabox_data = [];
            $metaboxs = $this->metaboxs();

            $metabox_data['id'] = $post_id;
            $metabox_data['title'] = get_the_title($post_id);
            $metabox_data['content'] = apply_filters('the_content', get_post_field('post_content', $post_id));

            foreach ($metaboxs as $group_id => $group) {
                foreach ($group['items'] as $field_id => $field) {
                    $metabox_data[$field_id] = get_post_meta($post_id, $field_id, true);
                }
            }

            return new WP_REST_Response($metabox_data, 200);
        }

        public function api_update_metabox_item_data(WP_REST_Request $request)
        {
            $post_id = $request->get_param('post_id');
            $updated_data = $request->get_json_params();

            if (!get_post($post_id)) {
                return new WP_REST_Response(['error' => 'O ID do post informado é inválido ou não existe'], 403);
            }

            $metaboxs = $this->metaboxs();

            $metabox_data['id'] = $post_id;
            $metabox_data['title'] = get_the_title($post_id);
            $metabox_data['content'] = apply_filters('the_content', get_post_field('post_content', $post_id));

            foreach ($updated_data as $field_id => $value) {
                foreach ($metaboxs as $group_id => $group) {
                    if (isset($group['items'][$field_id])) {
                        $sanitize_callback = $group['items'][$field_id]['sanitize_callback'] ?? 'sanitize_text_field';
                        $sanitized_value = call_user_func($sanitize_callback, $value);
                        update_post_meta($post_id, $field_id, $sanitized_value);
                    }
                }
            }

            return new WP_REST_Response(['success' => true], 200);
        }
    }
}
