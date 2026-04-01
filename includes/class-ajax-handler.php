<?php
defined( 'ABSPATH' ) || exit;

class WP_AI_SEO_Ajax_Handler {

    public static function init() {
        add_action( 'wp_ajax_wp_ai_seo_generate_seo',     array( __CLASS__, 'generate_seo' ) );
        add_action( 'wp_ajax_wp_ai_seo_generate_tags',    array( __CLASS__, 'generate_tags' ) );
        add_action( 'wp_ajax_wp_ai_seo_generate_content', array( __CLASS__, 'generate_content' ) );
    }

    // -----------------------------------------------------------------------
    // 通用鉴权
    // -----------------------------------------------------------------------

    private static function verify_request() {
        if ( ! check_ajax_referer( 'wp_ai_seo_ajax', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => '安全验证失败，请刷新页面重试。' ), 403 );
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => '权限不足。' ), 403 );
        }
    }

    private static function get_post_id(): int {
        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => '无效的文章 ID。' ), 400 );
        }
        return $post_id;
    }

    // -----------------------------------------------------------------------
    // 1. 生成 SEO 标题 / 关键词 / 描述
    // -----------------------------------------------------------------------

    public static function generate_seo() {
        self::verify_request();
        $post_id  = self::get_post_id();
        $post     = get_post( $post_id );
        $title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? $post->post_title ) );
        $site_url    = esc_url_raw( wp_unslash( $_POST['site_url'] ?? '' ) );
        $ref_content = sanitize_textarea_field( wp_unslash( $_POST['ref_content'] ?? '' ) );

        $url_context = '';
        if ( ! empty( $site_url ) ) {
            $url_context = WP_AI_SEO_API_Client::fetch_url_content( $site_url );
            if ( ! empty( $url_context ) ) {
                $url_context = "\n\n以下是来自参考站点 {$site_url} 的内容摘要（仅供参考）：\n{$url_context}";
            }
        }

        $ref_section = '';
        if ( ! empty( $ref_content ) ) {
            $ref_section = "\n\n以下是用户提供的参考资料（请优先提炼其中的核心信息辅助生成 SEO 元信息）：\n{$ref_content}";
        }

        $prompt = <<<PROMPT
你是专业的中文 SEO 专家。请根据以下文章标题，生成适合 Google 收录的 SEO 元信息。

文章标题：{$title}{$ref_section}{$url_context}

请严格按以下 JSON 格式输出（不要输出任何说明文字，只输出 JSON）：
{
  "seo_title": "15到30个中文字符的 SEO 标题，包含核心关键词",
  "keywords": "关键词1,关键词2,关键词3,关键词4,关键词5",
  "description": "50到150个字符的中文描述，自然融入关键词，吸引用户点击"
}
PROMPT;

        $result = WP_AI_SEO_API_Client::chat( $prompt );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // 提取 JSON
        preg_match( '/\{[\s\S]*\}/u', $result, $matches );
        $json = json_decode( $matches[0] ?? '{}', true );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $json ) ) {
            wp_send_json_error( array( 'message' => 'AI 返回格式异常，请重试。原始内容：' . mb_substr( $result, 0, 200 ) ) );
        }

        // 规范化关键词：拆分后去首尾空格再拼回，确保逗号后无空格
        $raw_kw      = sanitize_text_field( $json['keywords'] ?? '' );
        $kw_parts    = array_filter( array_map( 'trim', explode( ',', $raw_kw ) ) );
        $clean_kw    = implode( ',', $kw_parts );

        wp_send_json_success( array(
            'seo_title'   => sanitize_text_field( $json['seo_title']   ?? '' ),
            'keywords'    => $clean_kw,
            'description' => sanitize_textarea_field( $json['description'] ?? '' ),
        ) );
    }

    // -----------------------------------------------------------------------
    // 2. 生成标签
    // -----------------------------------------------------------------------

    public static function generate_tags() {
        self::verify_request();
        $post_id  = self::get_post_id();
        $post     = get_post( $post_id );
        $title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? $post->post_title ) );
        $site_url    = esc_url_raw( wp_unslash( $_POST['site_url'] ?? '' ) );
        $ref_content = sanitize_textarea_field( wp_unslash( $_POST['ref_content'] ?? '' ) );

        $url_context = '';
        if ( ! empty( $site_url ) ) {
            $url_context = WP_AI_SEO_API_Client::fetch_url_content( $site_url );
            if ( ! empty( $url_context ) ) {
                $url_context = "\n\n参考站点内容摘要：\n{$url_context}";
            }
        }

        $ref_section = '';
        if ( ! empty( $ref_content ) ) {
            $ref_section = "\n\n用户提供的参考资料：\n{$ref_content}";
        }

        $prompt = <<<PROMPT
你是 SEO 专家。请根据以下文章信息，生成适合作为 WordPress 标签的中文关键词列表。

文章标题：{$title}{$ref_section}{$url_context}

要求：
- 生成 8 到 12 个标签
- 每个标签 2 到 6 个中文字
- 涵盖核心词、长尾词、相关延伸词
- 严格按以下 JSON 格式输出（只输出 JSON，不要说明文字）：

{
  "tags": ["标签1", "标签2", "标签3", "标签4", "标签5", "标签6", "标签7", "标签8"]
}
PROMPT;

        $result = WP_AI_SEO_API_Client::chat( $prompt );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        preg_match( '/\{[\s\S]*\}/u', $result, $matches );
        $json = json_decode( $matches[0] ?? '{}', true );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $json['tags'] ) ) {
            wp_send_json_error( array( 'message' => 'AI 返回格式异常，请重试。' ) );
        }

        $tags = array_map( 'sanitize_text_field', (array) $json['tags'] );
        $tags = array_filter( $tags );

        wp_send_json_success( array( 'tags' => array_values( $tags ) ) );
    }

    // -----------------------------------------------------------------------
    // 3. 生成正文
    // -----------------------------------------------------------------------

    public static function generate_content() {
        self::verify_request();
        $post_id  = self::get_post_id();
        $post     = get_post( $post_id );
        $title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? $post->post_title ) );
        $site_url    = esc_url_raw( wp_unslash( $_POST['site_url'] ?? '' ) );
        $keywords    = sanitize_text_field( wp_unslash( $_POST['keywords'] ?? '' ) );
        $seo_title   = sanitize_text_field( wp_unslash( $_POST['seo_title'] ?? '' ) );
        $seo_desc    = sanitize_textarea_field( wp_unslash( $_POST['seo_desc'] ?? '' ) );
        $ref_content = sanitize_textarea_field( wp_unslash( $_POST['ref_content'] ?? '' ) );
        $length      = (int) ( $_POST['content_length'] ?? 1500 );
        $length      = in_array( $length, array( 800, 1500, 2500, 4000 ), true ) ? $length : 1500;

        $url_context = '';
        if ( ! empty( $site_url ) ) {
            $url_context = WP_AI_SEO_API_Client::fetch_url_content( $site_url );
        }

        $site_context_section = '';
        if ( ! empty( $url_context ) ) {
            $site_context_section = <<<SECTION

## 参考资料来源
以下是来自 {$site_url} 的页面内容摘要，请结合此信息进行创作：

{$url_context}
SECTION;
        }

        $ref_content_section = '';
        if ( ! empty( $ref_content ) ) {
            $ref_content_section = <<<SECTION

## ⚡ 用户参考资料（最高优先级，必须完整使用）
以下是用户提供的原始数据，这是整篇文章最核心的信息来源，你必须：
1. 【强制】将其中所有套餐、规格、价格、节点、流量、速率等结构化数据，100% 完整地整理成 Markdown 对比表格，数据行数与原始资料保持一致，不得遗漏任何一条，不得捏造或修改任何数字
2. 【强制】为每个套餐/节点/产品单独撰写详细的文字介绍段落，包含适用人群、核心优势、使用场景
3. 【强制】表格必须紧接着对应的文字介绍出现，形成「文字介绍 → 数据表格」的配对结构

{$ref_content}
SECTION;
        }

        $seo_meta_section = '';
        $seo_meta_parts   = array();
        if ( ! empty( $seo_title ) ) {
            $seo_meta_parts[] = "- SEO 标题：{$seo_title}";
        }
        if ( ! empty( $keywords ) ) {
            $seo_meta_parts[] = "- 核心关键词：{$keywords}（每个关键词在正文中至少自然出现 2-3 次，优先出现在 H2/H3 标题中）";
        }
        if ( ! empty( $seo_desc ) ) {
            $seo_meta_parts[] = "- Meta 描述定位：{$seo_desc}（文章整体基调与此描述保持一致）";
        }
        if ( ! empty( $seo_meta_parts ) ) {
            $seo_meta_list    = implode( "\n", $seo_meta_parts );
            $seo_meta_section = <<<SECTION

## 已确定的 SEO 元信息（必须严格遵守）
以下是已确认的 SEO 定位，文章内容必须与之高度吻合，不得偏离主题：
{$seo_meta_list}
SECTION;
        }

        $prompt = <<<PROMPT
你是一名专业的中文 SEO 内容创作专家，同时擅长将原始产品数据整理成结构清晰、易于对比的专业文档。

文章主题：{$title}
{$seo_meta_section}{$ref_content_section}{$site_context_section}

## 写作要求

**字数要求：** 不少于 {$length} 字

**【强制】内容结构（必须按此顺序，不得跳过）：**

### 第一部分：引言（约150字）
- 介绍主题背景与读者痛点
- 说明本文将提供哪些对比数据和详细介绍
- 自然融入核心关键词

### 第二部分：套餐/节点/产品详细对比（本文核心，篇幅最大）
- 【强制】必须包含一张完整的「总览对比表」，列出所有套餐/节点的核心参数（名称、价格、流量/带宽、节点数量、适用人群等）
- 【强制】总览表之后，对每个套餐/节点逐一展开详细介绍：
  * H3 标题：套餐/节点名称 + 核心卖点
  * 150字以上的文字介绍：适用人群、核心优势、使用场景、注意事项
  * 该套餐/节点的详细参数表格（从参考资料中提取，数据必须与原始资料完全一致）

### 第三部分：官网详细功能介绍
- 基于参考站点URL抓取的内容，详细介绍产品官方特色功能
- 至少包含一张「功能特性对比表」
- 每个功能点 100 字以上的说明

### 第四部分：如何选择适合自己的套餐（选购指南）
- 按使用场景分类推荐（轻度用户/重度用户/企业用户等）
- 包含选购决策表格

### 第五部分：常见问题 FAQ（至少5个问答）
- 问题来源于真实用户关心的点
- 每个回答 80 字以上

### 第六部分：总结
- 约100字，呼应引言，强化关键词

**SEO 要求：**
- H2/H3 标题中必须包含核心关键词
- 关键词在全文自然出现不少于 5 次，不得堆砌
- 段落不超过 150 字

**表格规范（所有表格必须符合）：**
- 使用标准 Markdown 表格语法
- 每列含义明确，表头加粗
- 数字数据保持与参考资料完全一致，不得修改
- 表格前用一句话说明此表的用途

**输出格式：** 直接输出 Markdown 内容，不要包含「以下是文章」「好的，我来」等任何开场白。
PROMPT;

        $result = WP_AI_SEO_API_Client::chat( $prompt );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // 将 Markdown 转换为 WordPress 块编辑器兼容的 HTML
        $html = self::markdown_to_html( $result );

        wp_send_json_success( array(
            'content'  => $html,
            'raw'      => $result,
            'word_count' => mb_strlen( $result ),
        ) );
    }

    // -----------------------------------------------------------------------
    // Markdown → HTML（基础转换，无需第三方库）
    // -----------------------------------------------------------------------

    private static function markdown_to_html( string $md ): string {
        // 代码块
        $md = preg_replace_callback( '/```[\s\S]*?```/m', function ( $m ) {
            $code = preg_replace( '/^```\w*\n?/', '', $m[0] );
            $code = preg_replace( '/```$/', '', $code );
            return '<pre><code>' . esc_html( trim( $code ) ) . '</code></pre>';
        }, $md );

        // 表格
        $md = preg_replace_callback( '/(\|.+\|\n)([\|\-: ]+\|\n)((?:\|.+\|\n?)+)/m', function ( $m ) {
            $header_row = self::parse_table_row( $m[1] );
            $headers    = '<tr>' . implode( '', array_map( fn( $c ) => '<th>' . trim( $c ) . '</th>', $header_row ) ) . '</tr>';
            $body_rows  = '';
            foreach ( explode( "\n", trim( $m[3] ) ) as $row ) {
                if ( empty( trim( $row ) ) ) {
                    continue;
                }
                $cells     = self::parse_table_row( $row );
                $body_rows .= '<tr>' . implode( '', array_map( fn( $c ) => '<td>' . trim( $c ) . '</td>', $cells ) ) . '</tr>';
            }
            return '<table class="wp-ai-seo-table"><thead>' . $headers . '</thead><tbody>' . $body_rows . '</tbody></table>';
        }, $md );

        // 标题
        $md = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $md );
        $md = preg_replace( '/^## (.+)$/m',  '<h2>$1</h2>', $md );
        $md = preg_replace( '/^# (.+)$/m',   '<h1>$1</h1>', $md );

        // 粗体 / 斜体
        $md = preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $md );
        $md = preg_replace( '/\*(.+?)\*/u',     '<em>$1</em>',         $md );

        // 无序列表
        $md = preg_replace_callback( '/((?:^[-*] .+\n?)+)/m', function ( $m ) {
            $items = preg_replace( '/^[-*] (.+)$/m', '<li>$1</li>', trim( $m[0] ) );
            return '<ul>' . $items . '</ul>';
        }, $md );

        // 有序列表
        $md = preg_replace_callback( '/((?:^\d+\. .+\n?)+)/m', function ( $m ) {
            $items = preg_replace( '/^\d+\. (.+)$/m', '<li>$1</li>', trim( $m[0] ) );
            return '<ol>' . $items . '</ol>';
        }, $md );

        // 段落（非标签行，双换行分隔）
        $parts = preg_split( '/\n{2,}/', $md );
        $html  = '';
        foreach ( $parts as $part ) {
            $part = trim( $part );
            if ( empty( $part ) ) {
                continue;
            }
            if ( preg_match( '/^<(h[1-6]|ul|ol|table|pre|blockquote)/i', $part ) ) {
                $html .= $part . "\n";
            } else {
                $part = nl2br( $part );
                $html .= '<p>' . $part . '</p>' . "\n";
            }
        }

        return $html;
    }

    private static function parse_table_row( string $row ): array {
        $row   = trim( $row, "| \n" );
        return explode( '|', $row );
    }
}
