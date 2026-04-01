<?php
defined( 'ABSPATH' ) || exit;

class WP_AI_SEO_Settings {

    const OPTION_KEY = 'wp_ai_seo_options';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    public static function add_menu() {
        add_options_page(
            'AI SEO 设置',
            'AI SEO',
            'manage_options',
            'wp-ai-seo',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function register_settings() {
        register_setting(
            'wp_ai_seo_group',
            self::OPTION_KEY,
            array( __CLASS__, 'sanitize_options' )
        );

        add_settings_section( 'api_section', 'API 配置', '__return_false', 'wp-ai-seo' );

        $fields = array(
            'active_model'    => array( 'label' => '使用模型',         'type' => 'select' ),
            'deepseek_key'    => array( 'label' => 'DeepSeek API Key', 'type' => 'password' ),
            'deepseek_model'  => array( 'label' => 'DeepSeek 模型',    'type' => 'deepseek_model', 'default' => 'deepseek-chat' ),
            'glm_key'         => array( 'label' => 'NVIDIA GLM API Key', 'type' => 'password' ),
            'glm_model'       => array( 'label' => 'GLM 模型',         'type' => 'glm_model',     'default' => 'nvidia/glm-4-9b-chat' ),
            'content_length'  => array( 'label' => '正文目标字数',     'type' => 'select' ),
        );

        foreach ( $fields as $id => $field ) {
            add_settings_field(
                $id,
                $field['label'],
                array( __CLASS__, 'render_field' ),
                'wp-ai-seo',
                'api_section',
                array( 'id' => $id, 'type' => $field['type'], 'default' => $field['default'] ?? '' )
            );
        }
    }

    public static function sanitize_options( $input ) {
        $clean = array();

        $clean['active_model']   = in_array( $input['active_model'] ?? '', array( 'deepseek', 'glm' ), true )
                                   ? $input['active_model']
                                   : 'deepseek';
        $clean['deepseek_key']   = sanitize_text_field( $input['deepseek_key'] ?? '' );
        $deepseek_models = array( 'deepseek-chat', 'deepseek-reasoner' );
        $clean['deepseek_model'] = in_array( $input['deepseek_model'] ?? '', $deepseek_models, true )
                                   ? $input['deepseek_model']
                                   : 'deepseek-chat';
        $clean['glm_key']        = sanitize_text_field( $input['glm_key'] ?? '' );
        $glm_models = array( 'nvidia/glm-4-9b-chat', 'nvidia/llama-3.1-nemotron-70b-instruct', 'meta/llama-3.1-8b-instruct', 'meta/llama-3.1-70b-instruct' );
        $clean['glm_model']      = in_array( $input['glm_model'] ?? '', $glm_models, true )
                                   ? $input['glm_model']
                                   : 'nvidia/glm-4-9b-chat';
        $clean['content_length'] = in_array( (int) ( $input['content_length'] ?? 1500 ), array( 800, 1500, 2500, 4000 ), true )
                                   ? (int) $input['content_length']
                                   : 1500;

        return $clean;
    }

    public static function render_field( $args ) {
        $options = get_option( self::OPTION_KEY, array() );
        $id      = $args['id'];
        $value   = $options[ $id ] ?? $args['default'];
        $name    = self::OPTION_KEY . '[' . $id . ']';

        if ( $id === 'active_model' ) {
            echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '">';
            foreach ( array( 'deepseek' => 'DeepSeek', 'glm' => 'NVIDIA GLM' ) as $k => $label ) {
                echo '<option value="' . esc_attr( $k ) . '" ' . selected( $value, $k, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
            return;
        }

        if ( $id === 'content_length' ) {
            echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '">';
            foreach ( array( 800 => '~800字（简洁版）', 1500 => '~1500字（标准版）', 2500 => '~2500字（详尽版）', 4000 => '~4000字（深度版）' ) as $num => $label ) {
                echo '<option value="' . esc_attr( $num ) . '" ' . selected( (int) $value, $num, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
            return;
        }

        if ( $id === 'deepseek_model' ) {
            $models = array(
                'deepseek-chat'     => 'deepseek-chat (DeepSeek-V3)',
                'deepseek-reasoner' => 'deepseek-reasoner (DeepSeek-R1)',
            );
            echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '">';
            foreach ( $models as $k => $label ) {
                echo '<option value="' . esc_attr( $k ) . '" ' . selected( $value, $k, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
            return;
        }

        if ( $id === 'glm_model' ) {
            $models = array(
                'nvidia/glm-4-9b-chat'         => 'GLM-4-9B-Chat',
                'nvidia/llama-3.1-nemotron-70b-instruct' => 'Llama-3.1-Nemotron-70B',
                'meta/llama-3.1-8b-instruct'   => 'Llama-3.1-8B-Instruct',
                'meta/llama-3.1-70b-instruct'  => 'Llama-3.1-70B-Instruct',
            );
            echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '">';
            foreach ( $models as $k => $label ) {
                echo '<option value="' . esc_attr( $k ) . '" ' . selected( $value, $k, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
            return;
        }

        $type = $args['type'] === 'password' ? 'password' : 'text';
        echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '" class="regular-text" autocomplete="off" />';
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>AI SEO 插件设置</h1>
            <?php settings_errors( 'wp_ai_seo_group' ); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wp_ai_seo_group' );
                do_settings_sections( 'wp-ai-seo' );
                submit_button( '保存设置' );
                ?>
            </form>
            <hr/>
            <h2>API 端点说明</h2>
            <table class="widefat striped">
                <thead><tr><th>模型</th><th>API 端点</th><th>说明</th></tr></thead>
                <tbody>
                    <tr><td>DeepSeek</td><td><code>https://api.deepseek.com/v1/chat/completions</code></td><td>需要 DeepSeek 账号 API Key</td></tr>
                    <tr><td>NVIDIA GLM</td><td><code>https://integrate.api.nvidia.com/v1/chat/completions</code></td><td>NVIDIA NIM 免费额度，需要 NVIDIA 账号</td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function get( $key, $default = '' ) {
        $options = get_option( self::OPTION_KEY, array() );
        return $options[ $key ] ?? $default;
    }
}
