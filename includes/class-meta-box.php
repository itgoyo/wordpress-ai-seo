<?php
defined( 'ABSPATH' ) || exit;

class WP_AI_SEO_Meta_Box {

    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
        add_action( 'save_post',      array( __CLASS__, 'save_seo_meta' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
    }

    public static function register_meta_boxes( $post_type ) {
        $default_types = get_post_types( array( 'public' => true ) );
        $supported     = apply_filters( 'wp_ai_seo_post_types', array_values( $default_types ) );
        if ( ! in_array( $post_type, $supported, true ) ) {
            return;
        }

        add_meta_box(
            'wp_ai_seo_meta',
            '🤖 AI SEO',
            array( __CLASS__, 'render_seo_box' ),
            $post_type,
            'side',
            'high'
        );

        add_meta_box(
            'wp_ai_seo_content',
            '🤖 AI 正文生成',
            array( __CLASS__, 'render_content_box' ),
            $post_type,
            'normal',
            'high'
        );
    }

    // -----------------------------------------------------------------------
    // SEO Meta Box（侧边栏）
    // -----------------------------------------------------------------------

    public static function render_seo_box( $post ) {
        wp_nonce_field( 'wp_ai_seo_save', 'wp_ai_seo_nonce' );

        $seo_title   = get_post_meta( $post->ID, '_wp_ai_seo_title',       true );
        $seo_kw      = get_post_meta( $post->ID, '_wp_ai_seo_keywords',    true );
        $seo_desc    = get_post_meta( $post->ID, '_wp_ai_seo_description', true );
        $site_url    = get_post_meta( $post->ID, '_wp_ai_seo_site_url',    true );
        ?>
        <div id="wp-ai-seo-box">

            <p class="wp-ai-seo-note">留空字段将由 AI 生成，也可手动填写。</p>

            <div class="wp-ai-seo-field">
                <label for="wp_ai_seo_site_url"><strong>参考站点 URL</strong></label>
                <input type="url" id="wp_ai_seo_site_url" name="wp_ai_seo_site_url"
                       value="<?php echo esc_attr( $site_url ); ?>"
                       placeholder="https://example.com"
                       class="widefat" />
                <span class="description">AI 将抓取此页面内容辅助生成，留空则仅凭标题生成。</span>
            </div>

            <div class="wp-ai-seo-field">
                <label for="wp_ai_seo_title"><strong>自定义标题</strong></label>
                <input type="text" id="wp_ai_seo_title" name="wp_ai_seo_title"
                       value="<?php echo esc_attr( $seo_title ); ?>"
                       placeholder="留空则获取文章标题"
                       class="widefat" maxlength="60" />
                <span class="description">Title · 建议 15–30 字符</span>
                <span class="wp-ai-seo-counter" id="seo_title_counter">0 / 60</span>
            </div>

            <div class="wp-ai-seo-field">
                <label for="wp_ai_seo_keywords"><strong>自定义关键词</strong></label>
                <input type="text" id="wp_ai_seo_keywords" name="wp_ai_seo_keywords"
                       value="<?php echo esc_attr( $seo_kw ); ?>"
                       placeholder="留空则获取文章标签"
                       class="widefat" />
                <span class="description">Keywords · 每个关键词用英文逗号隔开</span>
            </div>

            <div class="wp-ai-seo-field">
                <label for="wp_ai_seo_description"><strong>自定义描述</strong></label>
                <textarea id="wp_ai_seo_description" name="wp_ai_seo_description"
                          rows="4" class="widefat"
                          maxlength="160"
                          placeholder="留空则获取文章简介或摘要"><?php echo esc_textarea( $seo_desc ); ?></textarea>
                <span class="description">Description · 建议 50–150 字符</span>
                <span class="wp-ai-seo-counter" id="seo_desc_counter">0 / 160</span>
            </div>

            <div class="wp-ai-seo-actions">
                <button type="button" id="wp-ai-seo-generate-seo" class="button button-primary wp-ai-seo-btn">
                    ✨ AI 生成 SEO 信息
                </button>
                <span class="wp-ai-seo-spinner spinner"></span>
            </div>

            <div id="wp-ai-seo-preview" class="wp-ai-seo-preview" style="display:none;"></div>

            <hr/>

            <div class="wp-ai-seo-actions">
                <strong>AI 生成标签</strong><br/>
                <button type="button" id="wp-ai-seo-generate-tags" class="button wp-ai-seo-btn">
                    🏷️ AI 推荐标签
                </button>
                <span class="wp-ai-seo-spinner spinner" id="tags-spinner"></span>
                <div id="wp-ai-seo-tags-preview" class="wp-ai-seo-tags-preview" style="display:none;"></div>
            </div>

            <hr/>

            <div class="wp-ai-seo-actions">
                <strong>SEO 评分检测</strong><br/>
                <button type="button" id="wp-ai-seo-score-btn" class="button wp-ai-seo-btn">
                    📊 检测当前 SEO 评分
                </button>
            </div>
            <div id="wp-ai-seo-score-result" class="wp-ai-seo-score-result" style="display:none;"></div>
        </div>
        <?php
    }

    // -----------------------------------------------------------------------
    // 正文 AI 生成 Box（主编辑区上方）
    // -----------------------------------------------------------------------

    public static function render_content_box( $post ) {
        $site_url = get_post_meta( $post->ID, '_wp_ai_seo_site_url', true );
        $length   = WP_AI_SEO_Settings::get( 'content_length', 1500 );
        ?>
        <div id="wp-ai-content-box">
            <div id="wp-ai-original-panel" class="wp-ai-original-panel" style="display:none;">
                <span id="wp-ai-original-info"></span>
                <button type="button" id="wp-ai-restore-original" class="button wp-ai-seo-btn">↩️ 恢复原正文</button>
            </div>
            <p>根据<strong>文章标题</strong>和<strong>参考站点 URL</strong>（在右侧 SEO 面板填写），AI 将一键生成含表格、数据对比的深度中文内容，直接替换编辑器现有正文。</p>

            <div class="wp-ai-content-meta">
                <label>目标字数：
                    <select id="wp-ai-content-length">
                        <?php foreach ( array( 800 => '~800字（简洁版）', 1500 => '~1500字（标准版）', 2500 => '~2500字（详尽版）', 4000 => '~4000字（深度版）' ) as $num => $label ) : ?>
                            <option value="<?php echo esc_attr( $num ); ?>" <?php selected( (int) $length, $num ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                &nbsp;&nbsp;
                <button type="button" id="wp-ai-seo-generate-content" class="button button-primary wp-ai-seo-btn">
                    📝 AI 生成正文（替换）
                </button>
                <button type="button" id="wp-ai-seo-append-content" class="button wp-ai-seo-btn">
                    📎 AI 原文追加
                </button>
                <span class="wp-ai-seo-spinner spinner" id="content-spinner"></span>
            </div>

            <div id="wp-ai-content-progress" style="display:none;">
                <div class="wp-ai-progress-bar"><div class="wp-ai-progress-inner" id="wp-ai-progress-inner"></div></div>
                <span id="wp-ai-progress-text">正在连接 AI...</span>
            </div>

            <div id="wp-ai-content-error" class="wp-ai-error notice notice-error" style="display:none;"></div>
        </div>
        <?php
    }

    // -----------------------------------------------------------------------
    // 保存元数据
    // -----------------------------------------------------------------------

    public static function save_seo_meta( $post_id, $post ) {
        if ( ! isset( $_POST['wp_ai_seo_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_ai_seo_nonce'] ) ), 'wp_ai_seo_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = array(
            'wp_ai_seo_title'       => '_wp_ai_seo_title',
            'wp_ai_seo_keywords'    => '_wp_ai_seo_keywords',
            'wp_ai_seo_description' => '_wp_ai_seo_description',
            'wp_ai_seo_site_url'    => '_wp_ai_seo_site_url',
        );

        foreach ( $fields as $post_key => $meta_key ) {
            $raw = wp_unslash( $_POST[ $post_key ] ?? '' );

            if ( $post_key === 'wp_ai_seo_site_url' ) {
                $value = esc_url_raw( $raw );
            } elseif ( $post_key === 'wp_ai_seo_description' ) {
                $value = sanitize_textarea_field( $raw );
            } else {
                $value = sanitize_text_field( $raw );
            }

            update_post_meta( $post_id, $meta_key, $value );
        }
    }

    // -----------------------------------------------------------------------
    // 资源加载
    // -----------------------------------------------------------------------

    public static function enqueue_assets( $screen ) {
        if ( ! in_array( $screen, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        wp_enqueue_style(
            'wp-ai-seo-admin',
            WP_AI_SEO_URL . 'assets/admin.css',
            array(),
            WP_AI_SEO_VERSION
        );

        wp_enqueue_script(
            'wp-ai-seo-admin',
            WP_AI_SEO_URL . 'assets/admin.js',
            array( 'jquery' ),
            WP_AI_SEO_VERSION,
            true
        );

        wp_localize_script( 'wp-ai-seo-admin', 'wpAiSeo', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wp_ai_seo_ajax' ),
            'postId'  => get_the_ID(),
            'i18n'    => array(
                'generating'    => '正在生成，请稍候...',
                'applyConfirm'  => '确认将 AI 生成内容填入对应字段？',
                'applyTags'     => '选中的标签已添加',
                'error'         => 'AI 生成失败：',
                'contentDone'   => '✅ 正文已生成并填入编辑器',
            ),
        ) );
    }
}
