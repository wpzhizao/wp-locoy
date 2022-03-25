<?php

namespace WPLocoy;

use WP_Error;

class PublishApi {
    public string $secret = '';

    public bool $check_title_empty = false;

    public bool $check_title_dup = false;

    public int $post_date_interval = 0;

    public int $default_post_author = 1;

    public function __construct($args = array()) {
        foreach (get_object_vars($this) as $key => $value) {
            if (isset($args[$key])) {
                $this->$key = $args[$key];
            }
        }
    }

    public function publish() {
        $postarr = $_POST;
        $unsanitized_postarr = $postarr;

        if (empty($postarr))
            return new WP_Error('post_request_only', __('只接受POST请求', 'wp-locoy'));;

        // Check secret.
        if (!$this->verify_secret()) {
            return new WP_Error('invalid_secret', __('无效的密钥', 'wp-locoy'));
        }

        $post_title = !empty($postarr['post_title']) ? trim(stripslashes($postarr['post_title'])) : '';

        // Check post type.
        if (isset($postarr['post_type']) && !get_post_type_object($postarr['post_type'])) {
            return new WP_Error('invalid_post_type', __('无效的文章类型。', 'wp-locoy'));
        }

        // Check title empty.
        if ($this->check_title_empty && $post_title == '') {
            return new WP_Error('empty_title', __('标题为空', 'wp-locoy'));
        }

        // check title duplicate.
        $post_type = empty($postarr['post_type']) ? 'post' : $postarr['post_type'];

        if ($this->check_title_dup) {
            $post_before = get_page_by_title($post_title, OBJECT, $post_type);

            if ($post_before) {
                return new WP_Error('dup_title', __('标题重复', 'wp-locoy'));
            }
        }

        // Map post fields.
        $postarr = $this->map_postarr($postarr);

        // Sanitize post data.
        if (!empty($postarr['ID'])) {
            unset($postarr['ID']);
        }

        $postarr = array_merge(array(
            'post_status' => 'publish'
        ), $postarr);

        $postarr = apply_filters('wp_locoy_post_data', $postarr, $unsanitized_postarr);

        // Handle post date.
        if (!empty($postarr['post_date'])) {
            $post_time = strtotime($postarr['post_date']);
            $post_date = date('Y-m-d H:i:s', $post_time);
        } else {
            // Calc post date.
            $post_date = current_time('mysql');

            if ($this->post_date_interval) {
                $post_date = $this->get_last_post_date();
                $post_time = strtotime($post_date) + $this->post_date_interval;
                $post_date = date('Y-m-d H:i:s', $post_time);
            }
        }
        $postarr['post_date'] = $post_date;

        // Handle post author.
        $post_author = 0;
        if (!empty($postarr['post_author'])) {
            $post_author = $postarr['post_author'];

            if (!$this->is_int($post_author)) {
                $user_id = $this->resolve_user($post_author);

                if (is_wp_error($user_id)) {
                    $user_login = 'wppa_' . substr(md5($post_author), 0, 12);
                    $user_id = $this->resolve_user($user_login, $post_author);
                }

                if (!is_wp_error($user_id)) {
                    $post_author = $user_id;
                }
            }
        } else {
            $post_author = $this->default_post_author;
        }
        $postarr['post_author'] = $post_author;

        // Handle `post_category`
        if (!empty($postarr['post_category'])) {
            $terms = $postarr['post_category'];

            $terms_data = $this->insert_terms($terms, 'category');

            if (!empty($terms_data['term_ids'])) {
                $postarr['post_category'] = $terms_data['term_ids'];
            } else {
                unset($postarr['post_category']);
            }
        }

        // Handle `tax_input`
        if (!empty($postarr['tax_input'])) {
            // create terms for hierarchical taxonomy.
            foreach ($postarr['tax_input'] as $taxonomy => $terms) {
                $taxonomy_obj = get_taxonomy($taxonomy);

                if ($taxonomy_obj->hierarchical) {
                    $terms_data = $this->insert_terms($terms, $taxonomy);

                    if (!empty($terms_data['term_ids'])) {
                        $postarr['tax_input'][$taxonomy] = $terms_data['term_ids'];
                    } else {
                        unset($postarr['tax_input'][$taxonomy]);
                    }
                }
            }
        }

        // Set global `$current_user` to pass `current_user_can` check.
        global $current_user;
        $current_user = get_user_by('id', $this->default_post_author);

        $post_id = wp_insert_post($postarr, true, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (!$post_id) {
            return new WP_Error(__('unkown_error', __('未知错误，发布失败。', 'wp-locoy')));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $post_thumbnail_set = false;

        // Handle thumbnail file.
        if (!empty($_FILES['thumbnail_file'])) {
            $thumbnail_id = media_handle_upload('thumbnail_file', $post_id);

            if (!is_wp_error($thumbnail_id)) {
                set_post_thumbnail($post_id, $thumbnail_id);
                $post_thumbnail_set = true;
            }
        }

        // Handle post images.
        $i = 0;
        $post = get_post($post_id);
        $post_content = $post->post_content;

        $set_first_post_image_as_post_thumbnail = true;

        while (isset($_FILES["post_image{$i}"])) {
            $filename = $_FILES["post_image{$i}"]['name'];

            $image_id = media_handle_upload("post_image{$i}", $post_id);

            if (!is_wp_error($image_id)) {
                $image_url = wp_get_attachment_image_url($image_id);

                if ($set_first_post_image_as_post_thumbnail && !$post_thumbnail_set) {
                    set_post_thumbnail($post_id, $image_id);
                    $post_thumbnail_set = true;
                }

                if ($post_content)
                    $post_content = str_replace($filename, $image_url, $post_content);
            }

            $i++;
        }

        if ($post_content) {
            wp_update_post(array('ID' => $post_id, 'post_content' => $post_content), true, false);
        }

        return $post_id;
    }

    public function resolve_user($user_login, $display_name = '') {
        $user = get_user_by('login', $user_login);

        if ($user) {
            return $user->ID;
        } else {
            return wp_insert_user(array(
                'user_login' => $user_login,
                'user_pass' => wp_generate_password(),
                'role' => 'contributor',
                'display_name' => $display_name
            ));
        }
    }

    public function map_postarr($postarr = array()) {
        $map = array(
            'meta_input' => array('post_meta', 'meta'),
            'tax_input'  => array('post_taxonomy_list', 'tax'),
            'tags_input' => array('tags', 'tag', 'post_tags', 'post_tag')
        );

        foreach ($map as $key => $alt_keys) {
            if (isset($postarr[$key]))
                continue;

            foreach ($alt_keys as $alt_key) {
                if (isset($postarr[$alt_key])) {
                    $postarr[$key] = $postarr[$alt_key];
                    unset($postarr[$alt_key]);
                    break;
                }
            }
        }

        return $postarr;
    }

    public function verify_secret() {
        return !empty($_REQUEST['secret']) && $_REQUEST['secret'] == $this->secret;
    }

    public function insert_terms($terms, $taxonomy) {
        if (!is_array($terms)) {
            $comma = _x(',', 'tag delimiter');
            if (',' !== $comma) {
                $terms = str_replace($comma, ',', $terms);
            }
            $terms = explode(',', trim($terms, " \n\t\r\0\x0B,"));
        }

        $term_ids = array();
        $errors = array();
        foreach ($terms as $term) {
            $term_obj = get_term_by($this->is_int($term) ? 'id' : 'name', $term, $taxonomy);

            if ($term_obj) {
                $term_ids[] = $term_obj->term_id;
            } else {
                $term_data = wp_insert_term($term, $taxonomy);

                if (is_wp_error($term_data)) {
                    $errors[] = $term_data;
                } else {
                    $term_ids[] = $term_data['term_id'];
                }
            }
        }

        return array('term_ids' => $term_ids, 'errors' => $errors);
    }

    /**
     * Gets the timestamp of the last time any post with post status `publish`, `future` or `pending`.
     * 
     * Modifled version of WP core function `_get_last_post_time` which only supports `publish` status.
     *
     * @param [type] $timezone
     * @param [type] $field
     * @param string $post_type
     * @return void
     */
    function get_last_post_date($timezone = 'server', $field = 'date', $post_type = 'any') {
        global $wpdb;

        if (!in_array($field, array('date', 'modified'), true)) {
            return false;
        }

        $timezone = strtolower($timezone);

        $key = "wp_locoy:lastpost{$field}:$timezone";
        if ('any' !== $post_type) {
            $key .= ':' . sanitize_key($post_type);
        }

        $date = wp_cache_get($key, 'timeinfo');
        if (false !== $date) {
            return $date;
        }

        if ('any' === $post_type) {
            $post_types = get_post_types(array('public' => true));
            array_walk($post_types, array($wpdb, 'escape_by_ref'));
            $post_types = "'" . implode("', '", $post_types) . "'";
        } else {
            $post_types = "'" . sanitize_key($post_type) . "'";
        }

        $post_status_query = "(post_status == 'publish' OR post_status == 'future' OR post_status == 'pending')";

        switch ($timezone) {
            case 'gmt':
                $date = $wpdb->get_var("SELECT post_{$field}_gmt FROM $wpdb->posts WHERE {$post_status_query} AND post_type IN ({$post_types}) ORDER BY post_{$field}_gmt DESC LIMIT 1");
                break;
            case 'blog':
                $date = $wpdb->get_var("SELECT post_{$field} FROM $wpdb->posts WHERE {$post_status_query} AND post_type IN ({$post_types}) ORDER BY post_{$field}_gmt DESC LIMIT 1");
                break;
            case 'server':
                $add_seconds_server = gmdate('Z');
                $date               = $wpdb->get_var("SELECT DATE_ADD(post_{$field}_gmt, INTERVAL '$add_seconds_server' SECOND) FROM $wpdb->posts WHERE {$post_status_query} AND post_type IN ({$post_types}) ORDER BY post_{$field}_gmt DESC LIMIT 1");
                break;
        }

        if ($date) {
            wp_cache_set($key, $date, 'timeinfo');

            return $date;
        }

        return false;
    }

    public function is_int($value) {
        return is_numeric($value) && is_int($value + 0);
    }
}
