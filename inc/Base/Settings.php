<?php

namespace BPPR\Base;


class Settings {


    public function register(){
        add_action('admin_menu', [$this, 'add_opt_in_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action( 'admin_init', [$this, 'register_settings'] );
        add_action( 'rest_api_init', [$this, 'register_settings']);
    }

    // add a page settings
    public function add_opt_in_menu(){
        add_submenu_page( 'tools.php', __("Post Reaction", "post-reaction"), __("Post Reaction", "post-reaction"), 'manage_options', 'post-reaction', [$this, 'callback']
        );
    }

    public function callback(){
        wp_enqueue_script('settings');
        wp_enqueue_style('settings');
        ?>
        <div id="countPostReactSettings"></div>
        <?php
    }

    public function admin_enqueue_scripts(){
        wp_register_style('settings', BPPR_PLUGIN_DIR . 'dist/settings.css', ['wp-components'], BPPR_VER);
        wp_register_script('settings', BPPR_PLUGIN_DIR . 'dist/settings.js', ['react', 'react-dom', 'wp-components', 'wp-element', 'wp-api', 'wp-i18n', 'wp-data', 'wp-block-editor'], BPPR_VER, true);
    }

    function register_settings(){
        register_setting( "cpr", "cprSettings", array(
            'show_in_rest' => array(
                'name' => "cprSettings",
                'schema' => array(
                    'type'  => 'string',
                ),
            ),
            'type' => 'string',
            'default' => '{"enabled": true,"postTypes":["post","page"], "enabledReacts": ["like", "love", "wow", "angry"], "customReacts":[], "onlyUserCanReact": true, "iconSize": "30px","activeBackground":"#12ff0045", "design": "design-1" }',
            'sanitize_callback' => 'wp_kses_post',
        ));

        register_setting( "cpr", "cprPostTypes", array(
            'show_in_rest' => array(
                'name' => "cprPostTypes",
                'schema' => array(
                    'type'  => 'array',
                    'items' => [
                        'type' => 'string'
                    ]
                ),
            ),
            'type' => 'array',
            'default' => $this->get_post_types(),
            'sanitize_callback' => 'sanitize_text_field',
        ));
    }

    // 
    private function get_post_types(){
        $exclude_post_types = ['attachment', 'elementor_library'];

        // post types
        $post_types = [];
        foreach(get_post_types(['public'   => true]) as $post_type){
            if(!in_array($post_type, $exclude_post_types)){
                $post_types[] = $post_type;
            }
        }
        return $post_types;
    }

}