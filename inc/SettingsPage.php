<?php

namespace WPLocoy;

class SettingsPage {
    private $option_page = 'wp-locoy-settings';
    private $page_hook;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_page'));
        add_action('admin_init', array($this, 'settings_init'));
    }

    public function remove_admin_notices() {
        global $page_hook;
        if ($page_hook !== $this->page_hook) {
            return;
        }

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
    }

    public function add_page() {
        $this->page_hook = add_options_page(
            __('Publish API Settings', 'wp-locoy'),
            __('Publish API', 'wp-locoy'),
            'manage_options',
            $this->option_page,
            array($this, 'page_callback'),
            2
        );

        add_action("admin_head-{$this->page_hook}", array($this, 'remove_admin_notices'), 999);
    }

    public function settings_init() {
        // delete_option('wp_locoy_settings');

        $section = 'wp_locoy_settings_general';

        add_settings_section(
            $section,
            '',
            array(),
            $this->option_page
        );

        $settings = get_option('wp_locoy_settings');

        $fields = array(
            array(
                'section' => $section,
                'label' => __('发布接口密钥', 'wp-locoy'),
                'id' => 'secret',
                'type' => 'text',
            ),

            array(
                'section' => $section,
                'label' => __('检查标题为空', 'wp-locoy'),
                'checkbox_label' => __('检查标题是否为空，如为空，不发布。', 'wp-locoy'),
                'id' => 'check_title_empty',
                'type' => 'checkbox',
                'default' => true
            ),

            array(
                'section' => $section,
                'label' => __('判断标题重复', 'wp-locoy'),
                'id' => 'check_title_dup',
                'type' => 'checkbox',
                'checkbox_label' => __('判断标题是否重复，如重复，不发布。', 'wp-locoy'),
                'default' => true
            ),

            array(
                'section' => $section,
                'label' => __('默认文章作者', 'wp-locoy'),
                'desc' => __('当【Post数据内容】里没有设置表单名<code>post_author</code>或为空时，使用此设置作为默认作者。', 'wp-locoy'),
                'id' => 'default_post_author',
                'type' => 'dropdown_users'
            ),

            array(
                'section' => $section,
                'label' => __('文章日期间隔', 'wp-locoy'),
                'desc' => __('定时文章的发布日期间隔，单位为秒。如不设置或设置为0，文章会即时发布。', 'wp-locoy'),
                'id' => 'post_date_interval',
                'type' => 'number',
                'default' => null
            )
        );
        foreach ($fields as $field) {
            add_settings_field(
                $field['id'],
                $field['label'],
                array($this, 'field_callback'),
                $this->option_page,
                $field['section'],
                $field
            );
        }
    }

    public function page_callback() {


        global $plugin_page;
?>
        <div class="wrap">
            <h1><?php echo get_admin_page_title(); ?></h1>

            <br />

            <nav class="nav-tab-wrapper">
                <?php
                $tabs = array(
                    array(
                        'id' => 'general',
                        'label' => '设置'
                    ),
                    array(
                        'id' => 'help',
                        'label' => '帮助'
                    )
                );
                $current_tab = !empty($_GET['tab']) ? $_GET['tab'] : 'general';
                foreach ($tabs as $tab) {
                    $tab_url = sprintf('?page=%s&tab=%s', $plugin_page, $tab['id']);
                    $class = 'nav-tab' . ($current_tab == $tab['id'] ? ' nav-tab-active' : '');
                    echo '<a class="' . $class . '" href="' . $tab_url . '">' . $tab['label'] . '</a>';
                }
                ?>
            </nav>

            <?php call_user_func(array($this, 'tab_' . $current_tab)); ?>
        <?php }

    public function tab_general() { ?>
            <form method="POST" action="options.php">
                <?php
                settings_fields($this->option_page);
                do_settings_sections($this->option_page);
                submit_button();
                ?>
            </form>
        </div>
    <?php }

    public function tab_help() {

        $success_messages = array(
            __('发布成功', 'wp-locoy')
        );

        $error_messages = array(
            __('标题为空', 'wp-locoy'),
            __('标题重复', 'wp-locoy'),
            __('无效的密钥', 'wp-locoy'),
        );

        $settings = get_option('wp_locoy_settings');



        $secret = $settings['secret'] ?? '';
        // untrailingslashit(home_url()) . 
        $publish_url = '?wp-locoy&secret=' . $secret;

    ?>

        <br />
        <p class="description"><?php _e('请将以下信息复制粘贴到火车头里的对应位置。', 'wp-locoy'); ?></p>

        <table class="form-table">
            <tbody>
                <tr>
                    <th><?php _e('发布地址', 'wp-locoy'); ?></th>
                    <td>
                        <input readonly type="text" class="large-text" value="<?php echo home_url($publish_url); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><?php _e('发布成功标识码', 'wp-locoy'); ?></th>
                    <td>
                        <?php
                        echo '<textarea readonly class="large-text">' . implode("\n", $success_messages) . '</textarea>';
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('发布失败标识码', 'wp-locoy'); ?></th>
                    <td>
                        <?php
                        echo '<textarea class="large-text" readonly rows=6>' . implode("\n", $error_messages) . '</textarea>';
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Post数据内容', 'wp-locoy'); ?></th>
                    <td>
                        <p class="description"><?php _e('这仅是一个支持的表单名和表单值参考列表，实际使用中只需要在火车头里设置需要的表单名即可。', 'wp-locoy'); ?></p>
                        <br />

                        <table class="wp-list-table widefat">
                            <thead>
                                <tr>
                                    <td>表单名</td>
                                    <td>表单值示例</td>
                                    <td>说明</td>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $fields = array(
                                    array(
                                        'title' => 'post_type',
                                        'value' => 'post',
                                        'info' =>  __('文章类型，默认<code>post</code>', 'wp-locoy')
                                    ),
                                    array(
                                        'title' => 'post_title',
                                        'value' => '[标签:标题]',
                                        'info' =>  __('标题', 'wp-locoy')
                                    ),
                                    array(
                                        'title' => 'post_content',
                                        'value' => '[标签:内容]',
                                        'info' =>  __('内容', 'wp-locoy')
                                    ),
                                    array(
                                        'title' => 'post_excerpt',
                                        'value' => '[标签:摘要]',
                                        'info' =>  __('摘要', 'wp-locoy')
                                    ),
                                    array(
                                        'title' => 'post_date',
                                        'value' => '[标签:日期]',
                                        'info' =>  __('日期', 'wp-locoy')
                                    ),
                                    array(
                                        'title' => 'post_author',
                                        'value' => '[标签:作者]',
                                        'info' =>  __('作者，默认使用设置里的【默认文章作者】', 'wp-locoy')
                                    ),
                                    array(
                                        'title' => '[post_category]',
                                        'value' => '[标签:分类]',
                                        'info' =>  __('文章分类', 'wp-locoy')
                                    ),
                                    array(
                                        'title' => 'tags_input',
                                        'value' => '[标签:标签]',
                                        'info' =>  __('文章标签', 'wp-locoy')
                                    ),
                                    array(
                                        'title' => 'tax_input[自定义分类]',
                                        'value' => '[标签:自定义分类]',
                                        'info' =>  __('自定义分类', 'wp-locoy')
                                    ),
                                    array(
                                        'title' => 'meta_input[字段名]',
                                        'value' => '[标签:来源]',
                                        'info' =>  __('自定义字段，字段名建议用英文', 'wp-locoy')
                                    ),
                                );

                                foreach ($fields as $field) {
                                    echo ' <tr>
                                    <td>' . $field['title'] . '</td>
                                    <td>' . $field['value'] . '</td>
                                    <td>' . $field['info'] . '</td>
                                </tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

        <table class="form-table">
            <tbody>
                <tr>
                    <th><?php _e('文件上传设置', 'wp-locoy'); ?></th>
                    <td>
                        <p class="description"><?php _e('【高级功能】->【文件上传设置】', 'wp-locoy'); ?></p>
                        <br />

                        <table class="wp-list-table widefat">
                            <thead>
                                <tr>
                                    <td>标签名</td>
                                    <td>表单名</td>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $fields = array(
                                    array(
                                        'tag_name' => '内容',
                                        'title' => 'post_image递增数字'
                                    ),
                                    array(
                                        'tag_name' => '缩略图',
                                        'title' => 'thumbnail_file'
                                    ),
                                );

                                foreach ($fields as $field) {
                                    echo ' <tr>
                                    <td>' . $field['tag_name'] . '</td>
                                    <td>' . $field['title'] . '</td>
                                </tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>


<?php }

    public function field_callback($field) {
        $settings = get_option('wp_locoy_settings');

        $field_id = $field['id'];
        $field_name = 'wp_locoy_settings[' . $field_id . ']';

        $value = $settings[$field['id']] ?? $field['default'];

        $placeholder = '';
        if (isset($field['placeholder'])) {
            $placeholder = $field['placeholder'];
        }
        switch ($field['type']) {
            case 'dropdown_users':
                echo wp_dropdown_users(
                    array(
                        'role__in' => ['administrator', 'editor', 'author'],
                        'echo' => false,
                        'name' => 'wp_locoy_settings[default_post_author]',
                        'id' => 'default_post_author',
                        'default' => '',
                        'selected' => $settings['default_post_author'] ?? ''
                    )
                );
            case 'select':
            case 'multiselect':
                if (!empty($field['options']) && is_array($field['options'])) {
                    $attr = '';
                    $options = '';
                    foreach ($field['options'] as $key => $label) {
                        $options .= sprintf(
                            '<option value="%s" %s>%s</option>',
                            $key,
                            selected($value, $key, false),
                            $label
                        );
                    }
                    if ($field['type'] === 'multiselect') {
                        $attr = ' multiple="multiple" ';
                    }
                    printf(
                        '<select name="%1$s" id="%1$s" %2$s>%3$s</select>',
                        $field_id,
                        $attr,
                        $options
                    );
                }
                break;

            case 'checkbox':
                $content = sprintf(
                    '<input %s id="%s" name="%s" type="checkbox" value="1">',
                    $value ? 'checked' : '',
                    $field_id,
                    $field_name
                );

                if (!empty($field['checkbox_label'])) {
                    $content = '<label>' . $content . ' ' . $field['checkbox_label'] . '</label>';
                }

                echo $content;
                break;

            default:
                printf(
                    '<input name="%1$s" id="%2$s" type="%3$s" placeholder="%4$s" value="%5$s" />',
                    $field_name,
                    $field_id,
                    $field['type'],
                    $placeholder,
                    $value
                );
        }
        if (isset($field['desc'])) {
            if ($desc = $field['desc']) {
                printf('<p class="description">%s </p>', $desc);
            }
        }
    }
}
