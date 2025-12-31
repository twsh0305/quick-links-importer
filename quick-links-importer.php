<?php
/**
 * Plugin Name: 链接批量导入工具
 * Plugin URI: https://wxsnote.cn/7261.html
 * Description: 快速批量导入链接到WordPress链接管理器，支持JSON格式导入，包含名称、网址、简介、Logo
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Version: 1.0.0
 * Author: 天无神话
 * Author URI: https://wxsnote.cn
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: quick-links-importer
 */

if (!defined('ABSPATH')) {
    exit;
}

// 插件统一版本
function wxs_qli_plugin_version() {
    return '1.0.0';
}
$wxs_qli_version = wxs_qli_plugin_version();

// 定义插件根目录路径
define('WXS_QLI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WXS_QLI_PLUGIN_URL', plugin_dir_url(__FILE__));

// 配置获取
if (!function_exists('wxs_qli_get_setting')) {
    function wxs_qli_get_setting($key = '', $default = null) {
        $all_settings = get_option('wxs_qli_options', []);
        return isset($all_settings[$key]) ? $all_settings[$key] : $default;
    }
}

// 判断当前主题是否是zibll主题或其子主题
function wxs_qli_is_zibll_themes() {
    // 获取当前主题对象
    $current_theme = wp_get_theme();
    
    // 检测当前主题是否是zibll主主题
    if ($current_theme->get_stylesheet() === 'zibll') {
        return true;
    }
    
    // 检测当前主题是否是zibll的子主题（父主题为zibll）
    if ($current_theme->get('Template') === 'zibll') {
        return true;
    }
    
    // 都不是
    return false;
}

// 加载插件后台样式
function wxs_qli_enqueue_admin_styles($hook) {
    // 仅在插件设置页面加载样式
    if (strpos($hook, 'quick-links-importer') === false) {
        return;
    }
    
    // 加载插件样式
    wp_enqueue_style(
        'wxs-qli-style',
        WXS_QLI_PLUGIN_URL . 'lib/assets/css/style.min.css',
        [],
        wxs_qli_plugin_version(),
        'all'
    );
    
    wp_enqueue_style(
        'wxs-qli-admin-style',
        WXS_QLI_PLUGIN_URL . 'lib/assets/css/admin-style.min.css',
        [],
        wxs_qli_plugin_version(),
        'all'
    );
    
    // 加载插件脚本
    wp_enqueue_script(
        'wxs-qli-admin-script',
        WXS_QLI_PLUGIN_URL . 'lib/assets/js/admin-script.min.js',
        ['jquery'],
        wxs_qli_plugin_version(),
        true
    );
    
    wp_localize_script('wxs-qli-admin-script', 'wxs_qli_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wxs_qli_nonce')
    ]);
}

// 初始化所有功能
function wxs_qli_init_functions() {
    // 全局配置变量
    global $wxs_qli_config;
    $wxs_qli_config = get_option('wxs_qli_options', []);
    
    // 记录CSF初始化状态的变量
    $csf_initialized = false;
    
    // 初始化CSF设置面板
    if (class_exists('CSF')) {
        $csf_initialized = wxs_qli_init_csf_settings();
    }
    
    // 始终加载插件样式（使用较高优先级确保覆盖主题样式）
    add_action('admin_enqueue_scripts', 'wxs_qli_enqueue_admin_styles', 999);
}
add_action('init', 'wxs_qli_init_functions');

// 根据主题加载不同的设置
if (wxs_qli_is_zibll_themes()) {
    // 使用子比函数挂载
    require_once WXS_QLI_PLUGIN_DIR . 'lib/wxs-settings.php';
    add_action('zib_require_end', 'wxs_qli_init_csf_settings');
} else {
    // 非子比引入必要文件
    $wxs_qli_required_files = [
        '/lib/codestar-framework/codestar-framework.php',
        '/lib/wxs-settings.php',
    ];
    
    // 检查Codestar Framework是否已存在
    $wxs_qli_csf_exists = class_exists('CSF');
    foreach ($wxs_qli_required_files as $wxs_qli_file) {
        $wxs_qli_full_path = WXS_QLI_PLUGIN_DIR . $wxs_qli_file;
        // 如果是Codestar框架文件且已存在，则跳过加载
        if ($wxs_qli_file === '/lib/codestar-framework/codestar-framework.php' && $wxs_qli_csf_exists) {
            continue;
        }
        // 加载其他文件
        if (file_exists($wxs_qli_full_path)) {
            require_once $wxs_qli_full_path;
        } else {
            if (WP_DEBUG && WP_DEBUG_LOG) {
                error_log('Quick Links Importer Plugin Error: Missing file - ' . $wxs_qli_full_path);
            }
        }
    }
}

// 注册AJAX处理
add_action('wp_ajax_wxs_qli_import_links', 'wxs_qli_ajax_import_links');
add_action('wp_ajax_wxs_qli_get_categories', 'wxs_qli_ajax_get_categories');
add_action('wp_ajax_wxs_qli_export_links', 'wxs_qli_ajax_export_links');

/**
 * AJAX导入链接
 */
function wxs_qli_ajax_import_links() {
    check_ajax_referer('wxs_qli_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('权限不足');
    }
    
    $json_data = isset($_POST['json_data']) ? wp_unslash($_POST['json_data']) : '';
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $new_category = isset($_POST['new_category']) ? sanitize_text_field($_POST['new_category']) : '';
    $link_target = isset($_POST['link_target']) ? sanitize_text_field($_POST['link_target']) : '_blank';
    $import_mode = isset($_POST['import_mode']) ? sanitize_text_field($_POST['import_mode']) : 'specified';
    
    // 处理link_target，_none表示不设置
    if ($link_target === '_none') {
        $link_target = '';
    }
    
    // 如果指定了新分类（仅在指定分类模式下），先创建
    if ($import_mode === 'specified' && !empty($new_category)) {
        $term = wp_insert_term($new_category, 'link_category');
        if (!is_wp_error($term)) {
            $category_id = $term['term_id'];
        }
    }
    
    $links = json_decode($json_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error('JSON解析错误：' . json_last_error_msg());
    }
    
    if (!is_array($links)) {
        wp_send_json_error('数据格式错误，需要是链接数组');
    }
    
    $success = 0;
    $failed = 0;
    $skipped = 0;
    $errors = [];
    $category_cache = []; // 缓存分类ID
    
    foreach ($links as $index => $link) {
        $name = isset($link['name']) ? sanitize_text_field($link['name']) : '';
        $url = isset($link['url']) ? esc_url_raw($link['url']) : '';
        $description = isset($link['description']) ? sanitize_textarea_field($link['description']) : '';
        $logo = isset($link['logo']) ? esc_url_raw($link['logo']) : '';
        
        if (empty($name) || empty($url)) {
            $failed++;
            $errors[] = '第 ' . ($index + 1) . ' 条：名称或网址为空';
            continue;
        }
        
        // 确定当前链接的分类ID
        $current_category_id = $category_id;
        
        if ($import_mode === 'json_category') {
            // 按JSON分类模式
            $json_cat_name = isset($link['category']) ? sanitize_text_field($link['category']) : '';
            
            if (!empty($json_cat_name)) {
                // 检查缓存
                if (isset($category_cache[$json_cat_name])) {
                    $current_category_id = $category_cache[$json_cat_name];
                } else {
                    // 查找分类是否存在
                    $existing_term = get_term_by('name', $json_cat_name, 'link_category');
                    if ($existing_term) {
                        $current_category_id = $existing_term->term_id;
                    } else {
                        // 创建新分类
                        $new_term = wp_insert_term($json_cat_name, 'link_category');
                        if (!is_wp_error($new_term)) {
                            $current_category_id = $new_term['term_id'];
                        } else {
                            $failed++;
                            $errors[] = '第 ' . ($index + 1) . ' 条：创建分类失败 - ' . $json_cat_name;
                            continue;
                        }
                    }
                    // 缓存分类ID
                    $category_cache[$json_cat_name] = $current_category_id;
                }
            }
        }
        
        // 检查链接是否已存在
        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT link_id FROM {$wpdb->links} WHERE link_url = %s",
            $url
        ));
        
        if ($existing) {
            $skipped++;
            $errors[] = '第 ' . ($index + 1) . ' 条：链接已存在 - ' . $url;
            continue;
        }
        
        $link_data = [
            'link_name' => $name,
            'link_url' => $url,
            'link_description' => $description,
            'link_image' => $logo,
            'link_target' => $link_target,
            'link_visible' => 'Y',
            'link_category' => $current_category_id,
        ];
        
        $result = wp_insert_link($link_data);
        
        if ($result) {
            // 设置链接分类
            if ($current_category_id > 0) {
                wp_set_object_terms($result, [$current_category_id], 'link_category');
            }
            $success++;
        } else {
            $failed++;
            $errors[] = '第 ' . ($index + 1) . ' 条：插入失败 - ' . $name;
        }
    }
    
    $message = '导入完成！成功：' . $success;
    if ($skipped > 0) {
        $message .= '，跳过重复：' . $skipped;
    }
    if ($failed > 0) {
        $message .= '，失败：' . $failed;
    }
    
    wp_send_json_success([
        'success' => $success,
        'failed' => $failed,
        'skipped' => $skipped,
        'errors' => $errors,
        'message' => $message
    ]);
}

/**
 * AJAX获取分类
 */
function wxs_qli_ajax_get_categories() {
    check_ajax_referer('wxs_qli_nonce', 'nonce');
    
    $categories = get_terms([
        'taxonomy' => 'link_category',
        'hide_empty' => false,
    ]);
    
    $result = [];
    foreach ($categories as $cat) {
        $result[] = [
            'id' => $cat->term_id,
            'name' => $cat->name
        ];
    }
    
    wp_send_json_success($result);
}

/**
 * AJAX导出链接
 */
function wxs_qli_ajax_export_links() {
    check_ajax_referer('wxs_qli_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('权限不足');
    }
    
    $export_category = isset($_POST['export_category']) ? sanitize_text_field($_POST['export_category']) : 'all';
    
    // 获取链接
    $args = [
        'orderby' => 'name',
        'order' => 'ASC',
        'limit' => -1,
    ];
    
    if ($export_category !== 'all') {
        $args['category'] = intval($export_category);
    }
    
    $links = get_bookmarks($args);
    
    if (empty($links)) {
        wp_send_json_error('没有找到链接');
    }
    
    $export_data = [];
    
    foreach ($links as $link) {
        // 获取链接的分类
        $link_cats = wp_get_object_terms($link->link_id, 'link_category');
        $category_name = '';
        if (!empty($link_cats) && !is_wp_error($link_cats)) {
            $category_name = $link_cats[0]->name;
        }
        
        $export_data[] = [
            'name' => $link->link_name,
            'url' => $link->link_url,
            'description' => $link->link_description,
            'logo' => $link->link_image,
            'category' => $category_name,
        ];
    }
    
    // 获取分类名称用于显示
    $category_name_display = '全部分类';
    if ($export_category !== 'all') {
        $term = get_term(intval($export_category), 'link_category');
        if ($term && !is_wp_error($term)) {
            $category_name_display = $term->name;
        }
    }
    
    wp_send_json_success([
        'data' => $export_data,
        'count' => count($export_data),
        'category_name' => $category_name_display,
        'message' => '导出成功！共' . count($export_data) . '条链接'
    ]);
}
