/* global wpAiSeo, wp */
(function ($) {
  'use strict';

  // =========================================================================
  // 工具函数
  // =========================================================================

  function getPostTitle() {
    // 兼容经典编辑器和块编辑器
    if (typeof wp !== 'undefined' && wp.data) {
      try {
        return wp.data.select('core/editor').getEditedPostAttribute('title') || '';
      } catch (e) {}
    }
    return $('#title').val() || $('[name="post_title"]').val() || '';
  }

  function getSiteUrl() {
    return $('#wp_ai_seo_site_url').val() || '';
  }

  function getRefContent() {
    return $('#wp_ai_seo_ref_content').val() || '';
  }

  function showSpinner($btn, $spinner) {
    $btn.prop('disabled', true);
    $spinner.addClass('is-active');
  }

  function hideSpinner($btn, $spinner) {
    $btn.prop('disabled', false);
    $spinner.removeClass('is-active');
  }

  function showError($container, message) {
    $container
      .html('<p><strong>错误：</strong>' + $('<span>').text(message).html() + '</p>')
      .show();
  }

  // =========================================================================
  // 字符计数
  // =========================================================================

  function initCounters() {
    $('#wp_ai_seo_title').on('input', function () {
      $('#seo_title_counter').text($(this).val().length + ' / 60');
    }).trigger('input');

    $('#wp_ai_seo_description').on('input', function () {
      $('#seo_desc_counter').text($(this).val().length + ' / 160');
    }).trigger('input');
  }

  // =========================================================================
  // 1. 生成 SEO 信息
  // =========================================================================

  function initGenerateSeo() {
    $('#wp-ai-seo-generate-seo').on('click', function () {
      var $btn     = $(this);
      var $spinner = $btn.next('.wp-ai-seo-spinner');
      var $preview = $('#wp-ai-seo-preview');

      var title    = getPostTitle();
      var siteUrl  = getSiteUrl();

      if (!title) {
        alert('请先填写文章标题再生成 SEO 信息。');
        return;
      }

      showSpinner($btn, $spinner);
      $preview.hide().empty();

      $.post(wpAiSeo.ajaxUrl, {
        action:      'wp_ai_seo_generate_seo',
        nonce:       wpAiSeo.nonce,
        post_id:     wpAiSeo.postId,
        title:       title,
        site_url:    siteUrl,
        ref_content: getRefContent(),
      })
      .done(function (res) {
        if (!res.success) {
          showError($preview, res.data ? res.data.message : '未知错误');
          $preview.show();
          return;
        }

        var d = res.data;
        var html = '<div class="wp-ai-seo-result">'
          + '<h4>AI 生成结果预览</h4>'
          + '<div class="wp-ai-result-row"><span class="label">标题：</span><span class="value" id="ai-title-val">' + escHtml(d.seo_title) + '</span></div>'
          + '<div class="wp-ai-result-row"><span class="label">关键词：</span><span class="value" id="ai-kw-val">' + escHtml(d.keywords) + '</span></div>'
          + '<div class="wp-ai-result-row"><span class="label">描述：</span><span class="value" id="ai-desc-val">' + escHtml(d.description) + '</span></div>'
          + '<div class="wp-ai-seo-result-actions">'
          + '<button type="button" class="button button-primary" id="ai-seo-apply">✅ 应用到字段</button>'
          + '<button type="button" class="button" id="ai-seo-discard">✖ 放弃</button>'
          + '</div></div>';

        $preview.html(html).show();
      })
      .fail(function () {
        showError($preview, '网络请求失败，请检查网络后重试。');
        $preview.show();
      })
      .always(function () {
        hideSpinner($btn, $spinner);
      });
    });

    // 应用结果
    $(document).on('click', '#ai-seo-apply', function () {
      var aiTitle = $('#ai-title-val').text();
      var aiKw    = $('#ai-kw-val').text();
      var aiDesc  = $('#ai-desc-val').text();

      // 填充插件自身字段
      $('#wp_ai_seo_title').val(aiTitle).trigger('input');
      $('#wp_ai_seo_keywords').val(aiKw);
      $('#wp_ai_seo_description').val(aiDesc).trigger('input');

      // 同步填充 OneNav / CSF 主题的 SEO 字段
      var $csfTitle = $('input[name="post-seo_post_meta[_seo_title]"]');
      if ($csfTitle.length) {
        // 展开隱藏的 CSF 面板区块
        $csfTitle.closest('.csf-section').removeClass('hidden');
        $csfTitle.val(aiTitle).trigger('input').trigger('change');
        $('input[name="post-seo_post_meta[_seo_metakey]"]').val(aiKw).trigger('input').trigger('change');
        $('textarea[name="post-seo_post_meta[_seo_desc]"]').val(aiDesc).trigger('input').trigger('change');
      }

      $('#wp-ai-seo-preview').slideUp();
    });

    // 放弃
    $(document).on('click', '#ai-seo-discard', function () {
      $('#wp-ai-seo-preview').slideUp();
    });
  }

  // =========================================================================
  // 2. 生成标签
  // =========================================================================

  function initGenerateTags() {
    $('#wp-ai-seo-generate-tags').on('click', function () {
      var $btn     = $(this);
      var $spinner = $('#tags-spinner');
      var $preview = $('#wp-ai-seo-tags-preview');

      var title   = getPostTitle();
      var siteUrl = getSiteUrl();

      if (!title) {
        alert('请先填写文章标题再生成标签。');
        return;
      }

      showSpinner($btn, $spinner);
      $preview.hide().empty();

      $.post(wpAiSeo.ajaxUrl, {
        action:      'wp_ai_seo_generate_tags',
        nonce:       wpAiSeo.nonce,
        post_id:     wpAiSeo.postId,
        title:       title,
        site_url:    siteUrl,
        ref_content: getRefContent(),
      })
      .done(function (res) {
        if (!res.success) {
          showError($preview, res.data ? res.data.message : '未知错误');
          $preview.show();
          return;
        }

        var tags = res.data.tags || [];
        if (!tags.length) {
          showError($preview, '没有生成有效标签，请重试。');
          $preview.show();
          return;
        }

        var checkboxes = tags.map(function (tag) {
          return '<label class="wp-ai-tag-item">'
            + '<input type="checkbox" class="ai-tag-checkbox" value="' + escAttr(tag) + '" checked> '
            + escHtml(tag) + '</label>';
        }).join('');

        var html = '<div class="wp-ai-tags-wrap">'
          + '<p>选择要添加的标签：</p>'
          + '<div class="wp-ai-tags-list">' + checkboxes + '</div>'
          + '<div class="wp-ai-tags-actions">'
          + '<button type="button" class="button button-primary" id="ai-tags-apply">✅ 添加选中标签</button>'
          + '<button type="button" class="button" id="ai-tags-discard">✖ 放弃</button>'
          + '</div></div>';

        $preview.html(html).show();
      })
      .fail(function () {
        showError($preview, '网络请求失败，请检查网络后重试。');
        $preview.show();
      })
      .always(function () {
        hideSpinner($btn, $spinner);
      });
    });

    // 添加标签到 WordPress 标签区
    $(document).on('click', '#ai-tags-apply', function () {
      var selected = [];
      $('.ai-tag-checkbox:checked').each(function () {
        selected.push($(this).val());
      });

      if (!selected.length) {
        alert('请至少选择一个标签。');
        return;
      }

      selected.forEach(function (tag) {
        addWordPressTag(tag);
      });

      // 复制标签到系统剪贴板（格式：标签1,标签2,标签3）
      var tagStr = selected.join(',');
      copyToClipboard(tagStr, function (ok) {
        $('#wp-ai-seo-tags-preview').slideUp();
        if (ok) {
          alert('已添加 ' + selected.length + ' 个标签，同时已复制到剪贴板：\n' + tagStr);
        } else {
          alert('已添加 ' + selected.length + ' 个标签。\n剪贴板复制失败，可手动复制：' + tagStr);
        }
      });
    });

    $(document).on('click', '#ai-tags-discard', function () {
      $('#wp-ai-seo-tags-preview').slideUp();
    });
  }

  /** 向 WordPress 原生标签 meta box 添加标签 */
  function addWordPressTag(tag) {
    // 经典编辑器
    if (typeof tagBox !== 'undefined') {
      tagBox.userAction = true;
      var $input = $('#new-tag-post_tag');
      if ($input.length) {
        $input.val(tag);
        $('#add-post_tag').click();
        return;
      }
    }

    // 块编辑器 (Gutenberg)
    if (typeof wp !== 'undefined' && wp.data) {
      try {
        var store  = wp.data.select('core/editor');
        var current = store.getEditedPostAttribute('tags') || [];
        // 需要先确保 tag 存在或创建，简化处理：直接写入输入框
      } catch (e) {}
    }

    // 回退：写入网址标签输入框
    var $tagInput = $('#new-tag-post_tag, #tax-input-post_tag');
    if ($tagInput.length) {
      var cur = $tagInput.val();
      $tagInput.val(cur ? cur + ',' + tag : tag);
    }
  }

  // =========================================================================
  // 3. 生成正文
  // =========================================================================

  function initGenerateContent() {
    // 替换模式
    $('#wp-ai-seo-generate-content').on('click', function () {
      doGenerateContent($(this), 'replace');
    });

    // 追加模式
    $('#wp-ai-seo-append-content').on('click', function () {
      doGenerateContent($(this), 'append');
    });
  }

  function doGenerateContent($btn, mode) {
    var $spinner  = $('#content-spinner');
    var $progress = $('#wp-ai-content-progress');
    var $error    = $('#wp-ai-content-error');

    var title   = getPostTitle();
    var siteUrl = getSiteUrl();
    var length  = $('#wp-ai-content-length').val() || '1500';

    if (!title) {
      alert('请先填写文章标题再生成正文。');
      return;
    }

    var confirmMsg = mode === 'append'
      ? 'AI 生成的内容将追加到现有正文末尾，是否继续？'
      : '将用 AI 生成的内容替换编辑器中的现有正文，是否继续？';
    if (!confirm(confirmMsg)) {
      return;
    }

    // 禁用两个按钮
    $('#wp-ai-seo-generate-content, #wp-ai-seo-append-content').prop('disabled', true);
    showSpinner($btn, $spinner);
    $error.hide().empty();
    $progress.show();
    startProgressAnimation();

    var keywords = $('#wp_ai_seo_keywords').val()
      || $('input[name="post-seo_post_meta[_seo_metakey]"]').val()
      || '';
    var seoTitle = $('#wp_ai_seo_title').val()
      || $('input[name="post-seo_post_meta[_seo_title]"]').val()
      || '';
    var seoDesc = $('#wp_ai_seo_description').val()
      || $('textarea[name="post-seo_post_meta[_seo_desc]"]').val()
      || '';

    $.post(wpAiSeo.ajaxUrl, {
      action:         'wp_ai_seo_generate_content',
      nonce:          wpAiSeo.nonce,
      post_id:        wpAiSeo.postId,
      title:          title,
      site_url:       siteUrl,
      content_length: length,
      keywords:       keywords,
      seo_title:      seoTitle,
      seo_desc:       seoDesc,
      ref_content:    getRefContent(),
    })
    .done(function (res) {
      stopProgressAnimation(100);

      if (!res.success) {
        showError($error, res.data ? res.data.message : '未知错误');
        $error.show();
        return;
      }

      if (mode === 'append') {
        appendContentToEditor(res.data.content);
      } else {
        injectContentToEditor(res.data.content);
      }

      var wordCount = res.data.word_count || 0;
      var label = mode === 'append' ? '已追加约 ' : '已生成约 ';
      $btn.after('<span class="wp-ai-success-msg">✅ ' + label + wordCount + ' 字</span>');
      setTimeout(function () { $('.wp-ai-success-msg').fadeOut(); }, 4000);
    })
    .fail(function () {
      showError($error, '网络请求失败，请检查网络后重试。');
      $error.show();
    })
    .always(function () {
      hideSpinner($btn, $spinner);
      $('#wp-ai-seo-generate-content, #wp-ai-seo-append-content').prop('disabled', false);
      setTimeout(function () { $progress.hide(); }, 1000);
    });
  }

  /** 将 HTML 内容注入编辑器（兼容经典编辑器和块编辑器） */
  function injectContentToEditor(html) {
    // 块编辑器 (Gutenberg)
    if (typeof wp !== 'undefined' && wp.blocks && wp.data) {
      try {
        var blocks = wp.blocks.rawHandler({ HTML: html });
        wp.data.dispatch('core/editor').resetBlocks(blocks);
        return;
      } catch (e) {
        console.warn('[WP AI SEO] Gutenberg inject failed, falling back.', e);
      }
    }

    // 经典编辑器 TinyMCE
    if (typeof tinyMCE !== 'undefined') {
      var editor = tinyMCE.get('content');
      if (editor && !editor.isHidden()) {
        editor.setContent(html);
        return;
      }
    }

    // 纯 textarea 回退
    $('#content').val(html);
  }

  /** 将 HTML 内容追加到编辑器末尾 */
  function appendContentToEditor(html) {
    // 块编辑器 (Gutenberg)
    if (typeof wp !== 'undefined' && wp.blocks && wp.data) {
      try {
        var newBlocks = wp.blocks.rawHandler({ HTML: html });
        var existingBlocks = wp.data.select('core/block-editor').getBlocks();
        var allBlocks = existingBlocks.concat(newBlocks);
        wp.data.dispatch('core/editor').resetBlocks(allBlocks);
        return;
      } catch (e) {
        console.warn('[WP AI SEO] Gutenberg append failed, falling back.', e);
      }
    }

    // 经典编辑器 TinyMCE
    if (typeof tinyMCE !== 'undefined') {
      var editor = tinyMCE.get('content');
      if (editor && !editor.isHidden()) {
        var existing = editor.getContent();
        editor.setContent(existing + '<hr/>' + html);
        return;
      }
    }

    // 纯 textarea 回退
    var current = $('#content').val() || '';
    $('#content').val(current + '\n\n<hr/>\n\n' + html);
  }

  // =========================================================================
  // 进度条动画
  // =========================================================================

  var progressTimer = null;
  var progressVal   = 0;

  function startProgressAnimation() {
    progressVal = 0;
    setProgress(0, '正在连接 AI...');

    var steps = [
      { target: 15, delay: 500,  msg: '正在抓取参考页面...' },
      { target: 30, delay: 2000, msg: '正在构建 Prompt...' },
      { target: 55, delay: 4000, msg: 'AI 正在生成正文内容...' },
      { target: 75, delay: 8000, msg: '正在整理文章结构...' },
      { target: 90, delay: 3000, msg: '即将完成...' },
    ];

    var i = 0;
    function step() {
      if (i >= steps.length) {
        return;
      }
      var s = steps[i++];
      progressTimer = setTimeout(function () {
        setProgress(s.target, s.msg);
        step();
      }, s.delay);
    }
    step();
  }

  function stopProgressAnimation(val) {
    if (progressTimer) {
      clearTimeout(progressTimer);
      progressTimer = null;
    }
    setProgress(val, val >= 100 ? '✅ 生成完成！' : '生成中...');
  }

  function setProgress(val, msg) {
    progressVal = val;
    $('#wp-ai-progress-inner').css('width', val + '%');
    $('#wp-ai-progress-text').text(msg || '');
  }

  // =========================================================================
  // XSS 转义工具
  // =========================================================================

  function escHtml(str) {
    return $('<span>').text(str || '').html();
  }

  function escAttr(str) {
    return (str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // =========================================================================
  // 剪贴板工具
  // =========================================================================

  function copyToClipboard(text, callback) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(
        function () { callback(true); },
        function () { fallbackCopy(text, callback); }
      );
    } else {
      fallbackCopy(text, callback);
    }
  }

  function fallbackCopy(text, callback) {
    var $ta = $('<textarea>').val(text).css({ position: 'fixed', top: 0, left: 0, opacity: 0 }).appendTo('body');
    $ta[0].select();
    try {
      document.execCommand('copy');
      callback(true);
    } catch (e) {
      callback(false);
    }
    $ta.remove();
  }

  // =========================================================================
  // 编辑器内容读取
  // =========================================================================

  function getEditorContent() {
    // 块编辑器 (Gutenberg)
    if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
      try {
        var editorStore = wp.data.select('core/editor');
        if (editorStore && typeof editorStore.getEditedPostContent === 'function') {
          var content = editorStore.getEditedPostContent();
          if (content) return content;
        }
        if (editorStore && typeof editorStore.getEditedPostAttribute === 'function') {
          var content2 = editorStore.getEditedPostAttribute('content');
          if (content2) return content2;
        }
      } catch (e) {
        console.warn('[WP AI SEO] Gutenberg content read failed:', e);
      }
    }
    // 经典编辑器 TinyMCE
    if (typeof tinyMCE !== 'undefined') {
      var editor = tinyMCE.get('content');
      if (editor && !editor.isHidden()) {
        return editor.getContent();
      }
    }
    return $('#content').val() || '';
  }

  function getEditorTextContent() {
    return getEditorContent().replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
  }

  // =========================================================================
  // 4. SEO 评分检测
  // =========================================================================

  function initSeoScore() {
    $(document).on('click', '#wp-ai-seo-score-btn', function () {
      var $btn = $(this);
      var $result = $('#wp-ai-seo-score-result');

      $btn.prop('disabled', true).text('⏳ 检测中...');

      // 延迟一帧让按钮状态更新可见
      setTimeout(function () {
        try {
          var result = computeSeoScore();
          renderSeoScore(result);
          $result.slideDown();
        } catch (e) {
          console.error('[WP AI SEO] SEO 评分检测错误:', e);
          $result.html('<p style="color:#c0392b;"><strong>评分检测出错：</strong>' + escHtml(e.message || '未知错误') + '</p>').slideDown();
        } finally {
          $btn.prop('disabled', false).text('📊 检测当前 SEO 评分');
        }
      }, 50);
    });
  }

  function computeSeoScore() {
    var score = 0;
    var details = [];

    // 1. SEO 标题 (15分)
    var seoTitle = $('#wp_ai_seo_title').val()
      || $('input[name="post-seo_post_meta[_seo_title]"]').val()
      || '';
    var postTitle = getPostTitle();
    var effectiveTitle = seoTitle || postTitle;
    if (effectiveTitle) {
      score += 8;
      var tLen = effectiveTitle.replace(/\s/g, '').length;
      if (tLen >= 10 && tLen <= 70) {
        score += 7;
        details.push({ ok: true, msg: 'SEO 标题长度合适（' + tLen + ' 字）' });
      } else {
        details.push({ ok: false, msg: 'SEO 标题建议 10–70 字，当前 ' + tLen + ' 字（-7分）' });
      }
    } else {
      details.push({ ok: false, msg: '缺少 SEO 标题（-15分）' });
    }

    // 2. Meta 描述 (15分)
    var desc = $('#wp_ai_seo_description').val()
      || $('textarea[name="post-seo_post_meta[_seo_desc]"]').val()
      || '';
    if (desc) {
      score += 8;
      var dLen = desc.replace(/\s/g, '').length;
      if (dLen >= 50 && dLen <= 160) {
        score += 7;
        details.push({ ok: true, msg: 'Meta 描述长度合适（' + dLen + ' 字）' });
      } else {
        details.push({ ok: false, msg: 'Meta 描述建议 50–160 字，当前 ' + dLen + ' 字（-7分）' });
      }
    } else {
      details.push({ ok: false, msg: '缺少 Meta 描述（-15分）' });
    }

    // 3. 关键词 (10分)
    var kw = $('#wp_ai_seo_keywords').val()
      || $('input[name="post-seo_post_meta[_seo_metakey]"]').val()
      || '';
    var kwArr = kw ? kw.split(',').map(function (k) { return k.trim(); }).filter(Boolean) : [];
    if (kwArr.length >= 1) {
      score += 5;
      if (kwArr.length >= 3 && kwArr.length <= 8) {
        score += 5;
        details.push({ ok: true, msg: '关键词数量合适（共 ' + kwArr.length + ' 个）' });
      } else if (kwArr.length > 8) {
        details.push({ ok: false, msg: '关键词过多（' + kwArr.length + ' 个），建议 3–8 个（-5分）' });
      } else {
        details.push({ ok: false, msg: '关键词偏少（' + kwArr.length + ' 个），建议 3–8 个（-5分）' });
      }
    } else {
      details.push({ ok: false, msg: '缺少关键词（-10分）' });
    }

    // 4. 正文长度 (20分)
    var textContent = getEditorTextContent();
    var contentLen = textContent.replace(/\s/g, '').length;
    if (contentLen >= 1500) {
      score += 20;
      details.push({ ok: true, msg: '正文内容充实（约 ' + contentLen + ' 字）' });
    } else if (contentLen >= 800) {
      score += 15;
      details.push({ ok: false, msg: '正文 ' + contentLen + ' 字，建议 1500 字以上（-5分）' });
    } else if (contentLen >= 300) {
      score += 10;
      details.push({ ok: false, msg: '正文 ' + contentLen + ' 字，建议 800 字以上（-10分）' });
    } else if (contentLen > 0) {
      score += 5;
      details.push({ ok: false, msg: '正文仅 ' + contentLen + ' 字，建议至少 300 字（-15分）' });
    } else {
      details.push({ ok: false, msg: '无正文内容（-20分）' });
    }

    // 5. 文章标题 (10分)
    if (postTitle) {
      score += 5;
      var ptLen = postTitle.replace(/\s/g, '').length;
      if (ptLen >= 10 && ptLen <= 60) {
        score += 5;
        details.push({ ok: true, msg: '文章标题长度合适（' + ptLen + ' 字）' });
      } else {
        details.push({ ok: false, msg: '文章标题建议 10–60 字，当前 ' + ptLen + ' 字（-5分）' });
      }
    } else {
      details.push({ ok: false, msg: '缺少文章标题（-10分）' });
    }

    // 6. 关键词在 SEO 标题中出现 (10分)
    if (kwArr.length && effectiveTitle) {
      var titleLow = effectiveTitle.toLowerCase();
      var kwInTitle = kwArr.some(function (k) { return titleLow.indexOf(k.toLowerCase()) !== -1; });
      if (kwInTitle) {
        score += 10;
        details.push({ ok: true, msg: '关键词已出现在 SEO 标题中' });
      } else {
        details.push({ ok: false, msg: '关键词未出现在 SEO 标题中（-10分）' });
      }
    }

    // 7. 关键词在描述中出现 (10分)
    if (kwArr.length && desc) {
      var descLow = desc.toLowerCase();
      var kwInDesc = kwArr.some(function (k) { return descLow.indexOf(k.toLowerCase()) !== -1; });
      if (kwInDesc) {
        score += 10;
        details.push({ ok: true, msg: '关键词已出现在 Meta 描述中' });
      } else {
        details.push({ ok: false, msg: '关键词未出现在 Meta 描述中（-10分）' });
      }
    }

    // 8. 标签 (5分)
    var tagCount = $('.tagchecklist .ntdelclose').length || $('.tagchecklist li').length;
    if (tagCount > 0) {
      score += 5;
      details.push({ ok: true, msg: '已设置 ' + tagCount + ' 个文章标签' });
    } else {
      details.push({ ok: false, msg: '未设置文章标签（-5分）' });
    }

    // 9. 固定链接 (5分)
    var slug = $('#post_name').val() || $('[name="post_name"]').val() || '';
    if (slug && !/^\d+$/.test(slug)) {
      score += 5;
      details.push({ ok: true, msg: '固定链接已设置（' + slug + '）' });
    } else if (!slug) {
      details.push({ ok: false, msg: '固定链接未设置（-5分）' });
    } else {
      details.push({ ok: false, msg: '固定链接为纯数字，建议使用英文别名（-5分）' });
    }

    return { score: Math.min(100, score), details: details };
  }

  function renderSeoScore(result) {
    var s = result.score;
    var grade, gradeClass;
    if (s >= 80)      { grade = '优秀'; gradeClass = 'excellent'; }
    else if (s >= 60) { grade = '良好'; gradeClass = 'good'; }
    else if (s >= 40) { grade = '待改进'; gradeClass = 'fair'; }
    else              { grade = '较差'; gradeClass = 'poor'; }

    var detailsHtml = result.details.map(function (d) {
      return '<li class="wp-ai-score-item ' + (d.ok ? 'ok' : 'fail') + '">'
        + (d.ok ? '✓ ' : '✗ ') + escHtml(d.msg) + '</li>';
    }).join('');

    var html = '<div class="wp-ai-score-header">'
      + '<div class="wp-ai-score-circle ' + gradeClass + '">'
      + '<span class="wp-ai-score-num">' + s + '</span>'
      + '<span class="wp-ai-score-den">/100</span>'
      + '</div>'
      + '<div class="wp-ai-score-grade ' + gradeClass + '">' + grade + '</div>'
      + '</div>'
      + '<ul class="wp-ai-score-details">' + detailsHtml + '</ul>';

    $('#wp-ai-seo-score-result').html(html);
  }

  // =========================================================================
  // 5. 原正文备份与恢复
  // =========================================================================

  var originalContent = null;

  function initOriginalContent() {
    function trySnapshot() {
      if (originalContent !== null) { return; }
      var content = getEditorContent();
      originalContent = content;
      updateOriginalPanel();
    }

    // 立即尝试（经典编辑器纯 textarea）
    trySnapshot();
    // 等待编辑器初始化后再尝试
    setTimeout(trySnapshot, 1000);
    setTimeout(trySnapshot, 2500);

    // 恢复按钮
    $(document).on('click', '#wp-ai-restore-original', function () {
      if (originalContent === null) {
        alert('未能备份原正文，请刷新页面后再试。');
        return;
      }
      if (confirm('确认恢复到页面加载时的原始正文？当前内容将被替换。')) {
        injectContentToEditor(originalContent);
      }
    });
  }

  function updateOriginalPanel() {
    var charCount = (originalContent || '').replace(/<[^>]+>/g, '').replace(/\s+/g, '').length;
    var msg = charCount > 0
      ? '📄 原正文已备份（约 ' + charCount + ' 字）'
      : '📄 原正文为空（新文章）';
    $('#wp-ai-original-info').text(msg);
    $('#wp-ai-original-panel').show();
  }

  // =========================================================================
  // 初始化
  // =========================================================================

  $(function () {
    initCounters();
    initGenerateSeo();
    initGenerateTags();
    initGenerateContent();
    initSeoScore();
    initOriginalContent();
  });

}(jQuery));
