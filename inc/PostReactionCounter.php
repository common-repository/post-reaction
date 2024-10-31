<?php
namespace BPPR;


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PostReactionCounter{

    private $table_name = 'bppr_post_reactions';
    private $allowed_reactions = array('like', 'love', 'wow', 'angry');
    private $settings = array();
    public function register() {

        $this->settings =  $this->getSettings();
        // Register hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_update_post_reaction', array($this, 'save_reaction_callback'));
        add_action('wp_ajax_nopriv_update_post_reaction', array($this, 'save_reaction_callback'));
        add_action('the_content', array($this, 'display_reactions_count'));
        add_action('wp_footer', [$this, 'footerAlert']);
    }

    // Enqueue frontend scripts and styles
    public function enqueue_scripts() {
        wp_enqueue_style('bppr-post-reactions', BPPR_PLUGIN_DIR . 'dist/public.css', array(), BPPR_VER, 'all');

        wp_enqueue_script('bppr-post-reactions-script', BPPR_PLUGIN_DIR . 'dist/public.js', array('jquery'), BPPR_VER, true);
        wp_localize_script( 'bppr-post-reactions-script', 'postReactScript', [
            'ajaxURL' => admin_url('admin-ajax.php'),
            'onlyUserCanReact' => $this->settings['onlyUserCanReact'],
            'nonce' => wp_create_nonce('wp_ajax')
        ] );


        // add_inline styles instead internal styles
        $style = '.post-reactions-list{font-size:'. esc_html($this->settings['iconSize']).'; gap: calc('.esc_html($this->settings['iconSize']).' / 2)} .post-reactions-list li svg {width: '.esc_html($this->settings['iconSize']).'}.post-reactions-list.design-1 .reacted_to{background: '.esc_html($this->settings['activeBackground']).'}.post-reactions-list.design-2 li span:not(.prc_react_icon){font-size: }';

        wp_add_inline_style('bppr-post-reactions', $style);
    }

    // AJAX callback to save reaction
    public function save_reaction_callback() {

        // do nonce verification
        $nonce = $_POST['nonce'];
        
        if(!wp_verify_nonce($nonce, 'wp_ajax')){
            wp_send_json_error('unauthorized access');
        }

        $user_id = get_current_user_id();
        
        if($user_id == 0 && $this->settings['onlyUserCanReact']) {
            wp_send_json_error('Please Login to react!');
            
        }

        if(is_array($this->settings['customReacts'] ?? [])){
            foreach($this->settings['customReacts'] as $react){
                if(isset($react['id'])){
                    $this->allowed_reactions = array_merge($this->allowed_reactions, [$react['id']]);
                }
            }
        }

        // Example: 
        $post_id = absint(sanitize_text_field($_POST['post_id']));
        $reaction_type = sanitize_text_field($_POST['reaction_type']);

        
        // Save the reaction in the custom table
        if (!in_array($reaction_type, $this->allowed_reactions)) {
            wp_send_json_error('Invalid reaction type.');
        }
        
        // Check if the reaction already exists for the post
        if ($this->reaction_exists($post_id, $reaction_type)) {
            if($this->remove_reaction($post_id, $reaction_type)){
                wp_send_json_success(['count' => $this->get_reactions_count($post_id, $user_id)]);
            }else {
                wp_send_json_error('Reaction already exists.');
            }
        }
        
        // Save the reaction in the custom table
        if ($this->save_reaction($post_id, $reaction_type)) {
            // wp_send_json_error( 'trying to save' );
            wp_send_json_success(['count' => $this->get_reactions_count($post_id, $user_id)]);
        } else {
            wp_send_json_error('Failed to save reaction.');
        }

        // Send a response back (if needed)
        wp_send_json_success(['count' => $this->get_reactions_count($post_id, $user_id)]);
    }

    // Display reactions count on posts
    public function display_reactions_count($content) {
        global $post;
        if(!$this->settings['enabled'] || !in_array($post->post_type, $this->settings['postTypes'])) {
            return $content;
        }
        
        $reactions_count = $this->settings['beforeContent'] . $this->get_reactions_contents($post->ID). $this->settings['afterContent'];

        // Format and add the count to the content
        $count_html = sprintf('<div class="post-reactions-count">%s</div>', $reactions_count);

        if($this->settings['contentPosition'] === 'after_content') {
            return  $content . $count_html;
        }
        return $count_html . $content;
    }

    private function get_reactions_contents($post_id){
        $user_id = get_current_user_id();
        $formatted_counts = $this->get_reactions_count($post_id, $user_id);
        $reacted = $formatted_counts['reacted'];

        $likeClass = $reacted === 'like' ? 'reacted_to': '';
        $loveClass = $reacted === 'love' ? 'reacted_to': '';
        $wowClass = $reacted === 'wow' ? 'reacted_to': '';
        $angryClass = $reacted === 'angry' ? 'reacted_to': '';

        $count_html = '<ul class="post-reactions-list '. $this->settings['design'] . ' ">';

        if(in_array('like', $this->settings['enabledReacts'])){
            $count_html .= '<li title="like" class="'.$likeClass.'" data-post-id="'.$post_id.'" data-reaction-type="like"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M313.4 32.9c26 5.2 42.9 30.5 37.7 56.5l-2.3 11.4c-5.3 26.7-15.1 52.1-28.8 75.2H464c26.5 0 48 21.5 48 48c0 18.5-10.5 34.6-25.9 42.6C497 275.4 504 288.9 504 304c0 23.4-16.8 42.9-38.9 47.1c4.4 7.3 6.9 15.8 6.9 24.9c0 21.3-13.9 39.4-33.1 45.6c.7 3.3 1.1 6.8 1.1 10.4c0 26.5-21.5 48-48 48H294.5c-19 0-37.5-5.6-53.3-16.1l-38.5-25.7C176 420.4 160 390.4 160 358.3V320 272 247.1c0-29.2 13.3-56.7 36-75l7.4-5.9c26.5-21.2 44.6-51 51.2-84.2l2.3-11.4c5.2-26 30.5-42.9 56.5-37.7zM32 192H96c17.7 0 32 14.3 32 32V448c0 17.7-14.3 32-32 32H32c-17.7 0-32-14.3-32-32V224c0-17.7 14.3-32 32-32z"/></svg> <span>' . $formatted_counts['like'] . '</span></li>';
        }

        if(in_array('love', $this->settings['enabledReacts'])){
            $count_html .= '<li title="love" class="'.$loveClass.'" data-post-id="'.$post_id.'" data-reaction-type="love"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="#db1428"><path d="M47.6 300.4L228.3 469.1c7.5 7 17.4 10.9 27.7 10.9s20.2-3.9 27.7-10.9L464.4 300.4c30.4-28.3 47.6-68 47.6-109.5v-5.8c0-69.9-50.5-129.5-119.4-141C347 36.5 300.6 51.4 268 84L256 96 244 84c-32.6-32.6-79-47.5-124.6-39.9C50.5 55.6 0 115.2 0 185.1v5.8c0 41.5 17.2 81.2 47.6 109.5z"/></svg> <span>' . $formatted_counts['love'] . '</span></li>';
        }

        if(in_array('wow', $this->settings['enabledReacts'])){
            $count_html .= '<li class="'.$wowClass.'" title="wow"  data-post-id="'.$post_id.'" data-reaction-type="wow"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM176.4 176a32 32 0 1 1 0 64 32 32 0 1 1 0-64zm128 32a32 32 0 1 1 64 0 32 32 0 1 1 -64 0zM256 288a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/></svg><span>' . $formatted_counts['wow'] . '</span></li>';
        }

        if(in_array('angry', $this->settings['enabledReacts'])){
            $count_html .= '<li class="'.$angryClass.'" title="angry"  data-post-id="'.$post_id.'" data-reaction-type="angry"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="#c92929"><path d="M0 256a256 256 0 1 1 512 0A256 256 0 1 1 0 256zM338.7 395.9c6.6-5.9 7.1-16 1.2-22.6C323.8 355.4 295.7 336 256 336s-67.8 19.4-83.9 37.3c-5.9 6.6-5.4 16.7 1.2 22.6s16.7 5.4 22.6-1.2c11.7-13 31.6-26.7 60.1-26.7s48.4 13.7 60.1 26.7c5.9 6.6 16 7.1 22.6 1.2zM176.4 272c17.7 0 32-14.3 32-32c0-1.5-.1-3-.3-4.4l10.9 3.6c8.4 2.8 17.4-1.7 20.2-10.1s-1.7-17.4-10.1-20.2l-96-32c-8.4-2.8-17.4 1.7-20.2 10.1s1.7 17.4 10.1 20.2l30.7 10.2c-5.8 5.8-9.3 13.8-9.3 22.6c0 17.7 14.3 32 32 32zm192-32c0-8.9-3.6-17-9.5-22.8l30.2-10.1c8.4-2.8 12.9-11.9 10.1-20.2s-11.9-12.9-20.2-10.1l-96 32c-8.4 2.8-12.9 11.9-10.1 20.2s11.9 12.9 20.2 10.1l11.7-3.9c-.2 1.5-.3 3.1-.3 4.7c0 17.7 14.3 32 32 32s32-14.3 32-32z"/></svg> <span>' . $formatted_counts['angry'] . '</span></li>';
        }

        $count_html .= $this->get_customReacts($post_id, $formatted_counts);

        $count_html .= '</ul><div class="cprAlert"><span></span></div>';

        
        return $count_html;
    }

    // Get reactions count for a post
    private function get_reactions_count($post_id, $user_id = null) {
        // Query the custom table and get the count for each reaction type
        // Return a formatted count

        $formatted_counts = array();

        foreach($this->allowed_reactions as $reaction){
            $formatted_counts[$reaction] = 0;
        }

        // Example:
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $counts = $wpdb->get_results( $wpdb->prepare( "SELECT reaction_type, user_id, COUNT(*) as count,  MAX(CASE WHEN user_id = %d THEN %d ELSE 0 END) AS reacted FROM %i WHERE post_id = %d GROUP BY reaction_type", $user_id, $user_id, $table_name, $post_id), ARRAY_A);

        $reacted = false;
        foreach ($counts as $count) {
            $formatted_counts[$count['reaction_type']] = $count['count'];
            if($count['reacted'] == $user_id && $user_id != 0){
                $reacted = $count['reaction_type'];
            }
        }

        if(isset($_COOKIE["postReaction_$post_id"]) && $user_id == 0){
            $reacted = sanitize_text_field($_COOKIE["postReaction_$post_id"]);
        }

        $formatted_counts['reacted'] = $reacted;

        return $formatted_counts;
    }

    // Check if the reaction already exists for the post
    private function reaction_exists($post_id, $reaction_type) {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $user_id = get_current_user_id();

        if($user_id === 0){
            if(isset($_COOKIE["postReaction_$post_id"]) && $_COOKIE["postReaction_$post_id"] == $reaction_type){
                return true;
            }else {
                return false;
            }
        }

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE post_id = %d AND reaction_type = %s AND user_id = %d",
                $post_id,
                $reaction_type,
                $user_id
            )
        );

        return $count > 0;
    }

    // Save the reaction in the custom table
    private function save_reaction($post_id, $reaction_type) {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;

        $currentReact = isset($_COOKIE["postReaction_$post_id"]) ? sanitize_text_field($_COOKIE["postReaction_$post_id"]) : null;

        

        if($this->settings['onlyUserCanReact'] || get_current_user_id() != 0) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE post_id = %d AND user_id = %d",
                    $post_id,
                    get_current_user_id()
                )
            );
    
            if($count > 0) {
                return $this->update_reaction($post_id, $reaction_type);
            }
        }

        if($currentReact){
            return $this->update_reaction($post_id, $reaction_type);
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'post_id' => $post_id,
                'reaction_type' => $reaction_type,
                'user_id' => get_current_user_id()
            ),
            array('%d', '%s')
        );

        setcookie("postReaction_$post_id", $reaction_type, time() + (86400 * 30 * 365), "/");

        return $result !== false;
    }

    // Update the reaction in the custom table
    private function update_reaction($post_id, $reaction_type) {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $currentReact = isset($_COOKIE["postReaction_$post_id"]) ? sanitize_text_field( $_COOKIE["postReaction_$post_id"] ) : null;
        $user_id = get_current_user_id();

        // $wpdb->query($wpdb->prepare())
        
        $query = $wpdb->prepare(
            "UPDATE $table_name
            SET post_id = %d, reaction_type = %s, user_id = %d
            WHERE post_id = %d AND user_id = %d AND reaction_type = %s
            LIMIT 1",
            $post_id,
            $reaction_type,
            $user_id,
            $post_id,
            $user_id,
            $currentReact
        );

        $result = $wpdb->query($query);

        setcookie("postReaction_$post_id", $reaction_type, time() + (86400 * 30 * 365), "/");

        return $result !== false;
    }

    // Remove the reaction from the custom table
    private function remove_reaction($post_id, $reaction_type) {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;

        $result = $wpdb->delete(
            $table_name,
            array(
                'post_id' => $post_id,
                'user_id' => get_current_user_id(),
                'reaction_type' => $reaction_type,
            ),
            array('%d', '%d', '%s')
        );

        setcookie("postReaction_$post_id", 'content', 1, "/");

        return $result !== false;
    }

    private function get_customReacts($post_id, $formatted_counts) {
        $customReacts = '';

        
        if(is_array($this->settings['customReacts']) ?? []){
            foreach($this->settings['customReacts'] as $react){
                if(isset($react['id']) && $this->isset($react, 'enabled') === true){
                    $this->allowed_reactions = array_merge($this->allowed_reactions, [$react['id']]);
                    $icon = $react['id'];
                    if((isset($react['svg']) && $react['svg']) && strlen($react['svg']) > 30 && !strpos($react['svg'], '.')){
                        $icon = base64_decode($react['svg']);
                    }
                    $count = $formatted_counts[$react['id']] ?? 0;
                    $class = $formatted_counts['reacted'] === $react['id'] ? 'reacted_to' : '';
                    $customReacts .= '<li title="'.$react['name'].'" class="'.$class.'" data-post-id="'.$post_id.'" data-reaction-type="'.$react['id'].'">'.$icon.'<span>' . $count . '</span></li>';
                }
            }
        }

        return $customReacts;
    }

    private function isset($array, $key){
        return $array[$key] ?? false;
    }


    private function getSettings(){
        return wp_parse_args( json_decode(get_option('cprSettings'), true), [
            'enabled' => true,
            'customReacts' => [],
            'enabledReacts' => ['like', 'love', 'angry', 'wow'],
            'afterContent' => '',
            'beforeContent' => '',
            'postTypes' => ['post'],
            'contentPosition' => 'after_content',
            'onlyUserCanReact' => true,
            "iconSize" => "30px",
            "activeBackground" =>"#12ff0045", 
            "design" => "design-1"
        ] );
    }

    public function footerAlert(){
        echo wp_kses_post("<img src='".BPPR_PLUGIN_DIR ."assets/svg/sprite.svg' alt='' />");
    }

}