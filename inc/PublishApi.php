<?php

namespace WPLocoy;

use WP_Error;
use WP_Query;

class PublishApi
{
    public string $secret = '';

    public bool $check_title_empty = false;

    public bool $check_title_dup = false;

    public bool $check_slug_dup = true;

    public int $post_date_interval = 0;

    public int $default_post_author = 1;

    public $ondup = 'update'; // skip|update

    public function __construct($args = array())
    {
        foreach (get_object_vars($this) as $key => $value) {
            if (isset($args[$key])) {
                $this->$key = $args[$key];
            }
        }
    }

    /* Get post by meta.
    * 
    * Note: specific post type and post status for more effectily performance.
    */
    public function post_exists_by_meta($meta_key, $meta_value, $args = array())
    {
        $defaults = array(
            'post_type'      => 'any',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'     => $meta_key,
                    'value' => $meta_value
                )
            ),
            'suppress_filters' => true,
            'ignore_sticky_posts' => true,
            'no_found_rows'  => true,
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'update_term_meta_cache' => false,
        );

        $query = new \WP_Query(array_merge($defaults, $args));

        return !empty($query->posts[0]) ? $query->posts[0] : 0;
    }

    public function publish($postarr)
    {
        $unsanitized_postarr = $postarr;

        // Map post fields.
        $postarr = $this->map_postarr($postarr);

        // check post exists by meta
        // if (!empty($postarr['meta_input']['source_id'])) {
        //     $post = $this->post_exists_by_meta('source_id', $postarr['meta_input']['source_id'], ['post_type' => 'post']);

        //     if ($post) {
        //         return new WP_Error('source_id_exists', __('Source ID已存在', 'wp-locoy'), $post);
        //     }
        // }

        $post_title = !empty($postarr['post_title']) ? trim(stripslashes($postarr['post_title'])) : '';
        $post_type = empty($postarr['post_type']) ? 'post' : $postarr['post_type'];

        $post_before = null;

        if (!empty($postarr['ID'])) {
            $post_before = get_post($postarr['ID']);

            if (!$post_before) {
                return new WP_Error('invalid_id', __('Invalid ID', 'wp-locoy'), $postarr);
            }
        }

        if (!$post_before) {
            // Check title empty.
            if ($this->check_title_empty && $post_title == '') {
                return new WP_Error('empty_title', __('标题为空', 'wp-locoy'));
            }

            // Check post type.
            if (isset($postarr['post_type']) && !get_post_type_object($postarr['post_type'])) {
                return new WP_Error('invalid_post_type', __('无效的文章类型。', 'wp-locoy'));
            }

            // check title duplicate.
            $post_before = null;
            if ($this->check_title_dup) {
                $title_to_check = $post_title;
                $post_before = self::get_post_by_title($title_to_check, $post_type);

                if ($post_before && $this->ondup == 'skip') {
                    return new WP_Error('dup_title', __('标题重复', 'wp-locoy'), $post_before);
                }
            }

            if ($this->check_slug_dup && !$post_before) {
                $slug_to_check = !empty($postarr['post_name']) ? $postarr['post_name'] : sanitize_title($post_title);
                $post_before = get_page_by_path($slug_to_check, OBJECT, $post_type);

                if ($post_before && $this->ondup == 'skip') {
                    return new WP_Error('dup_slug', __('Slug重复', 'wp-locoy'), $post_before);
                }
            }
        }

        // Sanitize post data.
        if (!empty($postarr['ID'])) {
            unset($postarr['ID']);
        }

        $process_status = '';

        if ($post_before) {
            $postarr['ID'] = $post_before->ID;

            $process_status = 'updated';
        } else {
            $process_status = 'created';
        }


        $postarr = array_merge(array(
            'post_status' => 'publish'
        ), $postarr);



        $postarr = apply_filters('wp_locoy_post_data', $postarr, $unsanitized_postarr);



        // Handle post date.
        if (!empty($postarr['post_date'])) {
            $post_time = strtotime($postarr['post_date']);
            $post_date = date('Y-m-d H:i:s', $post_time);
            $postarr['post_date'] = $post_date;
        } elseif (!empty($postarr['post_date_gmt'])) {
            $post_time = strtotime($postarr['post_date_gmt']);
            $post_date = date('Y-m-d H:i:s', $post_time);
            $postarr['post_date_gmt'] = $post_date;
        } else {
            // Calc post date.
            $post_date = current_time('mysql');

            if ($this->post_date_interval) {
                $post_date = $this->get_last_post_date();
                $post_time = strtotime($post_date) + $this->post_date_interval;
                $post_date = date('Y-m-d H:i:s', $post_time);
            }
        }



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
            foreach ($postarr['tax_input'] as $taxonomy => $terms) {
                $taxonomy_obj = get_taxonomy($taxonomy);

                if (!$taxonomy_obj) {
                    unset($postarr['tax_input'][$taxonomy]);
                    continue;
                }

                // create terms for hierarchical taxonomy.
                if ($taxonomy_obj->hierarchical) {
                    $term_ids_is_int = false;
                    foreach ($terms as $term) {
                        $term_ids_is_int = is_int($term);

                        if (!$term_ids_is_int) {
                            break;
                        }
                    }

                    if (!$term_ids_is_int) {
                        $terms_data = $this->insert_hierarchical_terms($terms, $taxonomy);

                        if (!empty($terms_data['term_ids'])) {
                            $postarr['tax_input'][$taxonomy] = $terms_data['term_ids'];
                        } else {
                            unset($postarr['tax_input'][$taxonomy]);
                        }
                    }
                }
            }
        }


        // Set global `$current_user` to pass `current_user_can` check.
        global $current_user;
        $current_user = get_user_by('id', $post_author);


        if ($post_before)
            $postarr = array_merge(json_decode(json_encode($post_before), true), $postarr);

        do_action('wp_locoy_before_insert_post', $postarr);
        $post_id = wp_insert_post($postarr, true, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (!$post_id) {
            return new WP_Error(__('unkown_error', __('未知错误，发布失败。', 'wp-locoy')));
        }

        if (!empty($_FILES['thumbnail_file']) || !empty($_FILES["gallery_files"])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

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
        if (!empty($_FILES["gallery_files"])) {
            $i = 0;
            $post = get_post($post_id);
            $post_content = $post->post_content;

            $set_first_post_image_as_post_thumbnail = true;

            while (isset($_FILES["gallery_files{$i}"])) {
                $filename = $_FILES["gallery_files{$i}"]['title'];

                $image_id = media_handle_upload("gallery_files{$i}", $post_id);

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
        }

        $post = get_post($post_id);
        do_action('wp_locoy_after_insert_post', $post_id, $post, $postarr);

        return [
            'process_status' => $process_status,
            'post_id' => $post_id
        ];
    }


    public function get_post_by_title($title, $post_type = 'post')
    {

        $query = new WP_Query(
            array(
                'post_type'              => $post_type,
                'title'                  => $title,
                'post_status'            => 'all',
                'numberposts'            => 1,
                'update_post_term_cache' => false,
                'update_post_meta_cache' => false,
                'orderby'                => 'post_date ID',
                'order'                  => 'ASC',
            )
        );

        $post = null;

        if (!empty($query->post)) {
            $post = $query->post;
        } else {
        }

        return $post;
    }

    public function resolve_user($user_login, $display_name = '')
    {
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

    public function map_postarr($postarr = array())
    {
        $map = array(
            'meta_input' => array('post_meta', 'meta'),
            'tax_input'  => array('post_taxonomy_list', 'tax'),
            'tags_input' => array('tags', 'post_tags', 'post_tag')
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

    public function insert_hierarchical_terms($term_names, $taxonomy, $args = array())
    {
        $term_ids = array();
        $parent_id = 0;
        foreach ($term_names as $term_name) {

            $result = wp_insert_term($term_name, $taxonomy, array(
                'parent' => $parent_id
            ));

            $term_id = null;
            if (is_wp_error($result)) {
                if ($result->get_error_code() == 'term_exists') {
                    $term_id = $result->get_error_data();
                }
            } elseif (!empty($result['term_id'])) {
                $term_id = $result['term_id'];
            }

            if ($term_id) {
                $parent_id = $term_id;
                $term_ids[] = $term_id;
            } else {
                break;
            }
        }

        return $term_ids;
    }

    public function insert_terms($terms, $taxonomy)
    {
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
            $term_obj = get_term_by($this->is_int($term) ? 'id' : 'title', $term, $taxonomy);

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
     * @return string The date of the last post, or false on failure.
     */
    function get_last_post_date($timezone = 'server', $field = 'date', $post_type = 'any')
    {
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

    public function is_int($value)
    {
        return is_numeric($value) && is_int($value + 0);
    }
}