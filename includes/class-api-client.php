<?php
defined( 'ABSPATH' ) || exit;

class WP_AI_SEO_API_Client {

    /** 调用当前配置的模型，返回生成文本或 WP_Error */
    public static function chat( string $prompt ) {
        $model = WP_AI_SEO_Settings::get( 'active_model', 'deepseek' );

        if ( $model === 'glm' ) {
            return self::call_glm( $prompt );
        }

        return self::call_deepseek( $prompt );
    }

    /** 抓取指定 URL 的可读文本（最多 3000 字作为上下文） */
    public static function fetch_url_content( string $url ): string {
        if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return '';
        }

        // 防止 SSRF：只允许公网 HTTP/HTTPS
        $parsed = parse_url( $url );
        if ( ! in_array( $parsed['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
            return '';
        }
        $host = $parsed['host'] ?? '';
        // 拒绝 localhost / 私有地址
        if ( self::is_private_host( $host ) ) {
            return '';
        }

        $response = wp_remote_get( $url, array(
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (compatible; WP-AI-SEO/1.0)',
            'sslverify'  => true,
        ) );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $body = wp_remote_retrieve_body( $response );

        // 去除 HTML 标签，提取纯文本，截取前 3000 字
        $text = wp_strip_all_tags( $body );
        $text = preg_replace( '/\s+/', ' ', $text );
        $text = trim( $text );

        return mb_substr( $text, 0, 3000 );
    }

    // -----------------------------------------------------------------------
    // DeepSeek
    // -----------------------------------------------------------------------

    private static function call_deepseek( string $prompt ) {
        $api_key = WP_AI_SEO_Settings::get( 'deepseek_key' );
        $model   = WP_AI_SEO_Settings::get( 'deepseek_model', 'deepseek-chat' );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_key', 'DeepSeek API Key 未配置，请前往「设置 → AI SEO」添加。' );
        }

        return self::call_openai_compatible(
            'https://api.deepseek.com/v1/chat/completions',
            $api_key,
            $model,
            $prompt
        );
    }

    // -----------------------------------------------------------------------
    // NVIDIA GLM (兼容 OpenAI 协议)
    // -----------------------------------------------------------------------

    private static function call_glm( string $prompt ) {
        $api_key = WP_AI_SEO_Settings::get( 'glm_key' );
        $model   = WP_AI_SEO_Settings::get( 'glm_model', 'nvidia/glm-4-9b-chat' );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_key', 'NVIDIA GLM API Key 未配置，请前往「设置 → AI SEO」添加。' );
        }

        return self::call_openai_compatible(
            'https://integrate.api.nvidia.com/v1/chat/completions',
            $api_key,
            $model,
            $prompt
        );
    }

    // -----------------------------------------------------------------------
    // 通用 OpenAI 兼容协议
    // -----------------------------------------------------------------------

    private static function call_openai_compatible(
        string $endpoint,
        string $api_key,
        string $model,
        string $prompt
    ) {
        $body = wp_json_encode( array(
            'model'       => $model,
            'messages'    => array(
                array( 'role' => 'system', 'content' => '你是一名专业的中文 SEO 内容创作专家，擅长撰写结构清晰、关键词布局合理、易于被 Google 收录的中文文章。' ),
                array( 'role' => 'user',   'content' => $prompt ),
            ),
            'temperature' => 0.7,
            'max_tokens'  => 8000,
        ) );

        $response = wp_remote_post( $endpoint, array(
            'timeout' => 120,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body'    => $body,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $raw       = wp_remote_retrieve_body( $response );
        $data      = json_decode( $raw, true );

        if ( $http_code !== 200 ) {
            $msg = $data['error']['message'] ?? "HTTP {$http_code}";
            return new WP_Error( 'api_error', '调用 AI 接口失败：' . $msg );
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        if ( empty( $content ) ) {
            return new WP_Error( 'empty_response', 'AI 返回内容为空，请重试。' );
        }

        return $content;
    }

    // -----------------------------------------------------------------------
    // 安全：检测私有/本地 host
    // -----------------------------------------------------------------------

    private static function is_private_host( string $host ): bool {
        if ( in_array( strtolower( $host ), array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
            return true;
        }
        $ip = gethostbyname( $host );
        if ( $ip === $host ) {
            return false; // 无法解析，放行（让 wp_remote_get 处理超时）
        }
        return filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false;
    }
}
