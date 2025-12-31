<?php
/**
 * 链接批量导入工具 CSF设置面板配置
 * 
 * @package 链接批量导入工具
 * @version 1.0.0
 */

// 防止直接访问
if (!defined('ABSPATH')) exit;

/**
 * 初始化CSF设置面板
 */
function wxs_qli_init_csf_settings() {
    
    // 只有后台才执行此代码
    if (!is_admin()) {
        return;
    }
    
    // 检查CSF是否可用
    if (!class_exists('CSF')) {
        return false;
    }
    
    $prefix = 'wxs_qli_options';
    
    // 统一版本号
    $version = function_exists('wxs_qli_plugin_version') ? wxs_qli_plugin_version() : '1.0.0';
    
    // 底部文字
    $footer_text = sprintf(
        '作者：<a href="https://wxsnote.cn/" target="_blank">天无神话</a> | 版本：v%s <i class="fa fa-fw fa-heart-o" aria-hidden="true"></i> 感谢您使用链接批量导入工具',
        esc_html($version)
    );
    
    // 创建设置页面
    CSF::createOptions($prefix, [
        'menu_title'      => '链接批量导入',
        'menu_slug'       => 'quick-links-importer',
        'menu_type'       => 'submenu',
        'menu_parent'     => 'tools.php',
        'framework_title' => '链接批量导入工具 <small style="color: #666;">v' . esc_html($version) . '</small>',
        'footer_text'     => $footer_text,
        'show_bar_menu'   => false,
        'theme'           => 'light',
        'show_in_customizer' => false,
        'show_search'     => false,
        'show_reset_all'  => false,
        'show_reset_section' => false,
        'show_footer'     => true,
        'show_form_warning' => false,
        'show_all_options' => false,
        'sticky_header'   => false,
        'save_defaults'   => false,
        'footer_credit'   => '<a href="https://wxsnote.cn/" target="_blank">天无神话</a>感谢您使用链接批量导入工具 ',
        'class'           => 'wxs-no-save',
    ]);

    // 添加各个设置面板
    wxs_qli_create_import_section($prefix);
    wxs_qli_create_export_section($prefix);
    wxs_qli_create_help_section($prefix);
    
    return true;
}

/**
 * 创建导入设置面板
 */
function wxs_qli_create_import_section($prefix) {
    
    // 获取所有链接分类
    $link_categories = get_terms([
        'taxonomy' => 'link_category',
        'hide_empty' => false,
    ]);
    
    $category_options = [];
    if (!is_wp_error($link_categories)) {
        foreach ($link_categories as $cat) {
            $category_options[$cat->term_id] = $cat->name;
        }
    }
    
    CSF::createSection($prefix, [
        'id'    => 'wxs_import',
        'title' => '导入链接',
        'icon'  => 'fa fa-upload',
        'fields' => [
            // 导入设置标题
            [
                'type'    => 'heading',
                'content' => '导入设置',
            ],
            
            // 选择链接分类
            [
                'id'      => 'category_id',
                'type'    => 'select',
                'title'   => '选择链接分类',
                'desc'    => '选择要将链接导入到哪个分类',
                'options' => $category_options,
                'default' => !empty($category_options) ? array_key_first($category_options) : '',
            ],
            
            // 新建分类
            [
                'id'      => 'new_category',
                'type'    => 'text',
                'title'   => '新建分类',
                'desc'    => '如果要创建新分类，在此输入分类名称（留空则使用上方选择的分类）',
                'placeholder' => '输入新分类名称...',
            ],
            
            // 导入模式选择
            [
                'id'      => 'import_mode',
                'type'    => 'select',
                'title'   => '导入模式',
                'desc'    => '选择导入模式：<br>「指定分类」- 将所有链接导入到上方选择的分类<br>「按JSON分类」- 根据JSON中的category字段自动分类导入，不存在的分类将自动创建',
                'options' => [
                    'specified' => '指定分类（使用上方选择的分类）',
                    'json_category' => '按JSON分类（根据JSON中的category字段）',
                ],
                'default' => 'specified',
            ],
            
            // 链接打开方式
            [
                'id'      => 'link_target',
                'type'    => 'select',
                'title'   => '链接打开方式',
                'desc'    => '设置导入链接的打开方式',
                'options' => [
                    '_blank'  => '新窗口打开 (_blank)',
                    '_self'   => '当前窗口打开 (_self)',
                    '_top'    => '顶层窗口打开 (_top)',
                    '_none'   => '无指定',
                ],
                'default' => '_blank',
            ],
            
            // JSON数据输入
            [
                'type'    => 'heading',
                'content' => 'JSON数据',
            ],
            
            [
                'type'    => 'notice',
                'style'   => 'info',
                'content' => '<strong>JSON格式说明：</strong><br>粘贴由Python工具生成的JSON数据，格式示例：<pre style="background:#f5f5f5;padding:10px;border-radius:4px;margin-top:8px;"><code>[{
  "name": "网站名称",
  "url": "https://example.com",
  "description": "网站简介",
  "logo": "https://example.com/logo.png",
  "category": "分类名称"
}]</code></pre><p style="margin-top:8px;color:#666;">提示：当导入模式为「按JSON分类」时，需要包含category字段</p>',
            ],
            
            // JSON数据文本框
            [
                'id'      => 'json_data',
                'type'    => 'textarea',
                'title'   => 'JSON数据',
                'desc'    => '粘贴要导入的链接数据（JSON数组格式）',
                'placeholder' => '在此粘贴JSON数据...',
                'attributes'  => [
                    'rows' => 15,
                    'style' => 'font-family: monospace;',
                ],
            ],
            
            // 导入按钮区域
            [
                'type'    => 'callback',
                'function' => 'wxs_qli_render_import_buttons',
            ],
            
            // 导入结果区域
            [
                'type'    => 'callback',
                'function' => 'wxs_qli_render_result_area',
            ],
        ]
    ]);
}

/**
 * 渲染导入按钮
 */
function wxs_qli_render_import_buttons() {
    ?>
    <div class="wxs-button-group" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 4px;">
        <button type="button" id="wxs-preview-btn" class="button button-secondary button-large" style="margin-right: 10px;">
            预览数据
        </button>
        <button type="button" id="wxs-import-btn" class="button button-primary button-large">
            开始导入
        </button>
        <span id="wxs-loading" style="display: none; margin-left: 15px;">
            <span class="spinner is-active" style="float: none; margin: 0;"></span>
            处理中...
        </span>
    </div>
    <?php
}

/**
 * 渲染结果区域
 */
function wxs_qli_render_result_area() {
    ?>
    <div id="wxs-preview-area" style="display: none; margin-top: 20px;">
        <h3>预览导入数据</h3>
        <table class="wp-list-table widefat fixed striped" id="wxs-preview-table">
            <thead>
                <tr>
                    <th style="width: 15%;">名称</th>
                    <th style="width: 30%;">网址</th>
                    <th style="width: 35%;">简介</th>
                    <th style="width: 20%;">Logo</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    
    <div id="wxs-result-area" style="display: none; margin-top: 20px;">
        <h3>导入结果</h3>
        <div id="wxs-result"></div>
    </div>
    <?php
}

/**
 * 创建导出设置面板
 */
function wxs_qli_create_export_section($prefix) {
    
    // 获取所有链接分类
    $link_categories = get_terms([
        'taxonomy' => 'link_category',
        'hide_empty' => false,
    ]);
    
    $category_options = ['all' => '全部分类'];
    if (!is_wp_error($link_categories)) {
        foreach ($link_categories as $cat) {
            $category_options[$cat->term_id] = $cat->name;
        }
    }
    
    CSF::createSection($prefix, [
        'id'    => 'wxs_export',
        'title' => '导出链接',
        'icon'  => 'fa fa-download',
        'fields' => [
            // 导出设置标题
            [
                'type'    => 'heading',
                'content' => '导出设置',
            ],
            
            // 选择导出分类
            [
                'id'      => 'export_category',
                'type'    => 'select',
                'title'   => '选择导出分类',
                'desc'    => '选择要导出的链接分类，或导出全部链接',
                'options' => $category_options,
                'default' => 'all',
            ],
            
            // 导出按钮区域
            [
                'type'    => 'callback',
                'function' => 'wxs_qli_render_export_buttons',
            ],
            
            // 导出结果区域
            [
                'type'    => 'callback',
                'function' => 'wxs_qli_render_export_result_area',
            ],
        ]
    ]);
}

/**
 * 渲染导出按钮
 */
function wxs_qli_render_export_buttons() {
    ?>
    <div class="wxs-button-group" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 4px;">
        <button type="button" id="wxs-export-btn" class="button button-primary button-large">
            导出链接
        </button>
        <button type="button" id="wxs-download-btn" class="button button-secondary button-large" style="margin-left: 10px; display: none;">
            下载JSON文件
        </button>
        <span id="wxs-export-loading" style="display: none; margin-left: 15px;">
            <span class="spinner is-active" style="float: none; margin: 0;"></span>
            导出中...
        </span>
    </div>
    <?php
}

/**
 * 渲染导出结果区域
 */
function wxs_qli_render_export_result_area() {
    ?>
    <div id="wxs-export-result-area" style="display: none; margin-top: 20px;">
        <h3>导出结果</h3>
        <div id="wxs-export-info" style="margin-bottom: 10px;"></div>
        <textarea id="wxs-export-data" rows="15" style="width: 100%; font-family: monospace;" readonly></textarea>
    </div>
    <?php
}

/**
 * 创建帮助说明面板
 */
function wxs_qli_create_help_section($prefix) {
    CSF::createSection($prefix, [
        'id'    => 'wxs_help',
        'title' => '使用帮助',
        'icon'  => 'fa fa-question-circle',
        'fields' => [
            [
                'type'    => 'content',
                'content' => wxs_qli_get_help_content(),
            ],
        ]
    ]);
}

/**
 * 获取帮助内容
 */
function wxs_qli_get_help_content() {
    ob_start();
    ?>
    <div class="wxs-help-panel" style="padding: 20px;">
        <h2 style="color: #2271b1; margin-top: 0;">链接批量导入工具</h2>
        
        <div style="background: #f0f6fc; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <h3 style="margin-top: 0;">功能介绍</h3>
            <p>此插件可以快速批量导入链接到WordPress链接管理器，支持JSON格式导入，包含名称、网址、简介、Logo等信息。</p>
        </div>
        
        <div style="background: #d1ecf1; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #0c5460;">
            <h3 style="margin-top: 0; color: #0c5460;">配套工具下载</h3>
            <p style="margin-bottom: 10px;">提供Windows客户端工具，可批量根据网址自动获取链接信息（名称、简介、Logo等）并生成JSON格式，可直接导入到此插件。</p>
            <a href="https://wxsnote.cn/7261.html" target="_blank" style="display: inline-block; background: #0c5460; color: #fff; padding: 8px 16px; border-radius: 4px; text-decoration: none;">前往下载页面 →</a>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;">
            <div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                <h4 style="margin-top: 0;">快速导入</h4>
                <p>支持JSON格式数据导入，可一次导入多个链接</p>
            </div>
            <div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                <h4 style="margin-top: 0;">分类管理</h4>
                <p>可选择现有分类或创建新分类</p>
            </div>
            <div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                <h4 style="margin-top: 0;">数据预览</h4>
                <p>导入前可预览数据，确保数据正确</p>
            </div>
            <div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                <h4 style="margin-top: 0;">重复检测</h4>
                <p>自动检测重复链接，避免重复导入</p>
            </div>
        </div>
        
        <div style="background: #fff3cd; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <h3 style="margin-top: 0;">JSON格式说明</h3>
            <p>数据必须是JSON数组格式，每个链接对象包含以下字段：</p>
            <ul>
                <li><code>name</code> - 链接名称（必填）</li>
                <li><code>url</code> - 链接地址（必填）</li>
                <li><code>description</code> - 链接描述（选填）</li>
                <li><code>logo</code> - Logo图片URL（选填）</li>
                <li><code>category</code> - 要上传的分类（选填）</li>
            </ul>
        </div>
        
        <div style="background: #f5f5f5; padding: 15px; border-radius: 4px;">
            <h3 style="margin-top: 0;">使用步骤</h3>
            <ol>
                <li>在"导入链接"面板中选择或创建链接分类</li>
                <li>粘贴JSON格式的链接数据</li>
                <li>点击"预览数据"按钮检查数据</li>
                <li>确认无误后点击"开始导入"按钮</li>
            </ol>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
