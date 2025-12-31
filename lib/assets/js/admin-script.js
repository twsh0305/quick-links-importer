jQuery(document).ready(function($) {
    
    // 适配CSF框架的选择器 - 使用name属性和data-depend-id
    var categorySelector = 'select[data-depend-id="category_id"], select[name*="[category_id]"]';
    var newCategoryInput = 'input[data-depend-id="new_category"], input[name*="[new_category]"]';
    var linkTargetSelector = 'select[data-depend-id="link_target"], select[name*="[link_target]"]';
    var jsonDataTextarea = 'textarea[data-depend-id="json_data"], textarea[name*="[json_data]"]';
    var importModeSelector = 'select[data-depend-id="import_mode"], select[name*="[import_mode]"]';
    var exportCategorySelector = 'select[data-depend-id="export_category"], select[name*="[export_category]"]';
    
    // 重置CSF的未保存状态
    function resetCSFSaveState() {
        // 移除CSF的未保存标记
        $('.csf-save-warn').removeClass('csf-save-warn');
        if (window.CSF_DATA) {
            window.CSF_DATA.modified = false;
        }
        // 触发一个自定义事件来通知CSF重置
        $(document).trigger('csf-reset-save');
    }
    
    // 预览功能
    $(document).on('click', '#wxs-preview-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // 重置CSF保存状态
        setTimeout(resetCSFSaveState, 100);
        
        var jsonData = $(jsonDataTextarea).val().trim();
        
        if (!jsonData) {
            alert('请输入JSON数据');
            return;
        }
        
        try {
            var links = JSON.parse(jsonData);
            
            if (!Array.isArray(links)) {
                alert('JSON数据格式错误，需要是数组格式');
                return;
            }
            
            var tbody = $('#wxs-preview-table tbody');
            tbody.empty();
            
            links.forEach(function(link, index) {
                var logoHtml = link.logo ? 
                    '<img src="' + escapeHtml(link.logo) + '" alt="logo" style="max-width:50px;max-height:30px;" onerror="this.style.display=\'none\'">' : 
                    '-';
                    
                var row = '<tr>' +
                    '<td>' + escapeHtml(link.name || '-') + '</td>' +
                    '<td><a href="' + escapeHtml(link.url || '#') + '" target="_blank">' + escapeHtml(link.url || '-') + '</a></td>' +
                    '<td>' + escapeHtml(link.description || '-') + '</td>' +
                    '<td>' + logoHtml + '</td>' +
                    '</tr>';
                tbody.append(row);
            });
            
            $('#wxs-preview-area').show();
            
        } catch (e) {
            alert('JSON解析错误: ' + e.message);
        }
    });
    
    // 导入功能
    $(document).on('click', '#wxs-import-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // 重置CSF保存状态
        setTimeout(resetCSFSaveState, 100);
        
        var jsonData = $(jsonDataTextarea).val().trim();
        var categoryId = $(categorySelector).val();
        var newCategory = $(newCategoryInput).val().trim();
        var linkTarget = $(linkTargetSelector).val() || '_blank';
        var importMode = $(importModeSelector).val() || 'specified';
        
        if (!jsonData) {
            alert('请输入JSON数据');
            return;
        }
        
        // 验证JSON
        try {
            var parsed = JSON.parse(jsonData);
            if (!Array.isArray(parsed) || parsed.length === 0) {
                alert('JSON数据格式错误，需要是非空数组格式');
                return;
            }
        } catch (e) {
            alert('JSON格式错误: ' + e.message);
            return;
        }
        
        if (!confirm('确定要导入这些链接吗？')) {
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('导入中...');
        $('#wxs-loading').show();
        
        $.ajax({
            url: wxs_qli_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wxs_qli_import_links',
                nonce: wxs_qli_ajax.nonce,
                json_data: jsonData,
                category_id: categoryId || 0,
                new_category: newCategory,
                link_target: linkTarget,
                import_mode: importMode
            },
            success: function(response) {
                $('#wxs-loading').hide();
                $btn.prop('disabled', false).text('开始导入');
                
                if (response.success) {
                    var data = response.data;
                    var resultHtml = '<div class="notice notice-success" style="padding:10px;margin:10px 0;"><p>' + data.message + '</p></div>';
                    
                    if (data.errors && data.errors.length > 0) {
                        resultHtml += '<div class="notice notice-warning" style="padding:10px;margin:10px 0;"><strong>错误详情:</strong><ul style="margin:10px 0 0 20px;">';
                        data.errors.forEach(function(err) {
                            resultHtml += '<li style="color:#c00;">' + escapeHtml(err) + '</li>';
                        });
                        resultHtml += '</ul></div>';
                    }
                    
                    $('#wxs-result').html(resultHtml);
                    $('#wxs-result-area').show();
                    
                    // 导入成功后清空输入
                    if (data.success > 0) {
                        $(jsonDataTextarea).val('');
                        $(newCategoryInput).val('');
                        $('#wxs-preview-area').hide();
                    }
                } else {
                    alert('导入失败: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                $('#wxs-loading').hide();
                $btn.prop('disabled', false).text('开始导入');
                alert('请求失败，请重试。错误: ' + error);
            }
        });
    });
    
    // HTML转义函数
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // 导出数据存储
    var exportedData = null;
    
    // 导出功能
    $(document).on('click', '#wxs-export-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        setTimeout(resetCSFSaveState, 100);
        
        var exportCategory = $(exportCategorySelector).val() || 'all';
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('导出中...');
        $('#wxs-export-loading').show();
        
        $.ajax({
            url: wxs_qli_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wxs_qli_export_links',
                nonce: wxs_qli_ajax.nonce,
                export_category: exportCategory
            },
            success: function(response) {
                $('#wxs-export-loading').hide();
                $btn.prop('disabled', false).text('导出链接');
                
                if (response.success) {
                    var data = response.data;
                    exportedData = data.data;
                    
                    var jsonStr = JSON.stringify(data.data, null, 2);
                    $('#wxs-export-data').val(jsonStr);
                    $('#wxs-export-info').html('<div class="notice notice-success" style="padding:10px;margin:0;"><p>' + data.message + ' (分类: ' + escapeHtml(data.category_name) + ')</p></div>');
                    $('#wxs-export-result-area').show();
                    $('#wxs-download-btn').show();
                } else {
                    alert('导出失败: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                $('#wxs-export-loading').hide();
                $btn.prop('disabled', false).text('导出链接');
                alert('请求失败，请重试。错误: ' + error);
            }
        });
    });
    
    // 下载JSON文件
    $(document).on('click', '#wxs-download-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (!exportedData) {
            alert('请先导出数据');
            return;
        }
        
        var jsonStr = JSON.stringify(exportedData, null, 2);
        var blob = new Blob([jsonStr], {type: 'application/json'});
        var url = URL.createObjectURL(blob);
        
        var date = new Date();
        var dateStr = date.getFullYear() + '-' + 
                     String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                     String(date.getDate()).padStart(2, '0');
        
        var a = document.createElement('a');
        a.href = url;
        a.download = 'links-export-' + dateStr + '.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
});
