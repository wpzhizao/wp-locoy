<?php

namespace WPLocoy;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Server;

use WPLocoy\PublishApi;

class RESTController extends WP_REST_Controller {


    public function register_routes() {
        $version = '1';
        $namespace = 'wp-locoy/v' . $version;
        $base = 'posts';

        register_rest_route($namespace, '/' . $base, array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'create_item'),
                'permission_callback' => array($this, 'create_item_permissions_check'),
            )
        ));
    }

    public function create_item($request) {
        $settings = get_option('wp_locoy_settings');

        $api = new PublishApi($settings);

        $post_id = $api->publish($request->get_params());

        if (is_wp_error($post_id)) {
            return $post_id;
        } else {
            $post = get_post($post_id);
            return new WP_REST_Response($post, 200);
        }
    }

    public function create_item_permissions_check($request) {
        return true;
        return current_user_can('read');
    }

    public function get_item_permissions_check($request) {
        return true;
        return current_user_can('read');
    }
}
