<?php
/**
 * Class wrapper for removing unnecessary WordPress functions
 *
 * @author Michiel Tramper - https://michieltramper.com
 */
namespace Classes\WP_Optimize;
use WP_Error as WP_Error;

defined( 'ABSPATH' ) or die( 'Go eat veggies!' );

class MT_WP_Optimize {
        
    /**
     * Holds the configurations for the optimizations
     */
    private $optimize = array();
            
    /** 
     * Constructor
     *
     * @param array $optimize The optimalizations to conduct
     */
    public function __construct(Array $optimizations = array()) {
        $defaults =  array(
            'no_scripts_styles_version' => true,
            'no_wp_version'             => true,
            'no_feed'                   => false,
            'no_shortlinks'             => true,
            'no_rsd_manifest'           => true,
            'no_wp_emoji'               => true,
            'disable_xmlrpc'            => true,
            'block_external_http'       => false,
            'stop_heartbeat'            => false,
            'disable_comments'          => false,
            'no_jquery'                 => false,
            'no_embed'                  => false,
            'defer_js'                  => false,
            'defer_css'                 => false,
        );
        
        $this->optimize = wp_parse_args($optimizations, $defaults);
        $this->optimize();
    }
    
    /**
     * Hit it! Runs eachs of the functions if enabled
     */
    private function optimize() {

        foreach($this->optimize as $key => $value) {
            if($value === true && method_exists($this, $key) ) {
                $this->$key();
            }
        }
        
    }
    
    /**
     * Removes the version hook on scripts and styles
     *
     * @uses MT_WP_Optimize::no_scripts_styles_version_hook
     */
    private function no_scripts_styles_version() {
        add_filter( 'style_loader_src', array($this, 'no_scripts_styles_version_hook'), 20000 );
        add_filter( 'script_loader_src', array($this, 'no_scripts_styles_version_hook'), 20000 );        
    }
    
    /**
     * Removes version numbers from scripts and styles. 
     * The absence of version numbers increases the likelyhood of scripts and styles being cached.
     *
     * @param string @target_url The url of the script
     */
    public function no_scripts_styles_version_hook($target_url = '') {        
        if( strpos( $target_url, 'ver=' ) ) {
            $target_url = remove_query_arg( 'ver', $target_url );
        }
        return $target_url;      
    }
    
    /**
     * Defers all JS
     */
    private function defer_js() {
        add_filter( 'script_loader_tag', array($this, 'defer_js_hook'), 10, 1 );    
    }
    
    /**
     * The hook for deferring js
     *
     * @param string @tag The src tag of the script
     */
    public function defer_js_hook($tag) {        
        return str_replace( ' src', ' defer="defer" src', $tag );      
    }
    
    /**
     * Defers all CSS using loadCSS from the Filament Group
     */
    private function defer_css() {
        add_action( 'wp_head', array($this, 'defer_css_hook'), 9999);     
        add_action( 'wp_enqueue_scripts', array($this, 'dequeue_css'), 9999);     
    }
    
    /**
     * Dequeues css
     */   
    public function dequeue_css() {
        global $wp_styles;

        // Save the queued styles
        foreach($wp_styles->queue as $style) {    
            $this->styles[] = $wp_styles->registered[$style];  
            $dependencies   = $wp_styles->registered[$style]->deps;
            
            if( ! $dependencies)
                continue;
            
            // Add dependencies
            foreach($dependencies as $dependency) {
                $this->styles[] = $wp_styles->registered[$dependency];
            }                

        }
        
        // Dequeue styles and their dependencies except for conditionals
        foreach($this->styles as $style) {
            if( isset($style->extra['conditional']) ) 
                continue;            
            
            wp_dequeue_style($style->handle);
        }
        
    }
    
    /**
     * The hook for deferring css
     */
    public function defer_css_hook() {  
        global $wp_styles;
        
        $output = '<script>function loadCSS(a,b,c,d){"use strict";var e=window.document.createElement("link"),f=b||window.document.getElementsByTagName("script")[0],g=window.document.styleSheets;return e.rel="stylesheet",e.href=a,e.media="only x",d&&(e.onload=d),f.parentNode.insertBefore(e,f),e.onloadcssdefined=function(b){for(var c,d=0;d<g.length;d++)g[d].href&&g[d].href.indexOf(a)>-1&&(c=!0);c?b():setTimeout(function(){e.onloadcssdefined(b)})},e.onloadcssdefined(function(){e.media=c||"all"}),e}';
        
        foreach($this->styles as $style) { 
            
            if( isset($style->extra['conditional']) ) 
                continue;
            
            if( strpos($style->src, 'http') === false )    
                $style->src = site_url() . $style->src;
            
            $output .= 'loadCSS("' . $style->src . '", "", "' . $style->args . '");';           
        }
        
        $output .= '</script>';
        
        echo $output;
        
    }     
 
    /**
     * Removes the WP Version as generated by WP
     */
    private function no_wp_version() {
        remove_action( 'wp_head', 'wp_generator' ); 
        add_filter( 'the_generator', '__return_null' ); 
    }
       
    /**
     * Removes links to RSS feeds
     * 
     * @uses MT_WP_Optimize::no_feed_hook
     */
    private function no_feed() {        
        remove_action( 'wp_head', 'feed_links_extra', 3 ); 
        remove_action( 'wp_head', 'feed_links', 2 );   
        add_action( 'do_feed', array($this, 'no_feed_hook'), 1 );
        add_action( 'do_feed_rdf', array($this, 'no_feed_hook'), 1 );
        add_action( 'do_feed_rss', array($this, 'no_feed_hook'), 1 );
        add_action( 'do_feed_rss2', array($this, 'no_feed_hook'), 1 );
        add_action( 'do_feed_atom', array($this, 'no_feed_hook'), 1 );        
    }
    
    /**
     * Removes the actual feed links
     */
    public function no_feed_hook() {
        wp_die( '<p>' . __('Feed not available. Return to the', 'language_domain') . '<a href="'. esc_url(get_bloginfo('url')) . '">' . __('Homepage', 'language_domain') . '</a>');    
    }
    
    /**
     * Removes the WP Shortlink 
     */
    private function no_shortlinks() { 
        remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 );        
    }
    
    /**
     * Removes rsd and wlmanifest bloat
     */
    private function no_rsd_manifest() {
        remove_action('wp_head', 'rsd_link'); 
        remove_action('wp_head', 'wlwmanifest_link');         
    }
            
    /**
     * Removes WP Emoji
     * 
     * @uses MT_WP_Optimize::remove_tinymce_emoji
     */
    private function no_wp_emoji() {
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
        add_filter( 'tiny_mce_plugins', array($this, 'remove_tinymce_emoji') );       
    }
    
    /**
     * Hook to remove WPemoji from the TinyMCE Editor
     *
     * @param array $plugins The plugins hooked onto the TinyMCE Editor
     */
    public function remove_tinymce_emoji($plugins) {
        if ( ! is_array($plugins) ) {
            return array();
		}
        return array_diff($plugins, array('wpemoji'));
	}
    
    /**
     * Disables XML RPC. Warning, makes some functions unavailable!
     *
     * @uses MT_WP_Optimize::unset_xmlrpc_headers
     * @uses MT_WP_Optimize::unset_pingback_methods
     */
    private function disable_xmlrpc() {
                 
        update_option( 'default_ping_status', 'closed' );    
        
        add_filter( 'xmlrpc_enabled', '__return_false' ); 
        add_filter( 'pre_update_option_enable_xmlrpc', '__return_false' );
        add_filter( 'pre_option_enable_xmlrpc', '__return_zero' );       
        
        // Removes xmlrpc headers
        add_filter( 'wp_headers', array($this, 'unset_xmlrpc_headers') );
        
        // Removes pingback methods
        add_filter( 'xmlrpc_methods', array($this, 'unset_pingback_methods') );        
    }
    
    /**
     * Unsets xmlrpc headers
     *
     * @param array $headers The array of wp headers    
     */
    public function unset_xmlrpc_headers($headers) {
        if( isset( $headers['X-Pingback'] ) ) {
            unset( $headers['X-Pingback'] );
        }
        return $headers;          
    }
    
    /**
     * Unsets xmlr methods for pingbacks
     *
     * @param array $methods The array of xmlrpc methods
     */    
    public function unset_pingback_methods($methods) {
        unset( $methods['pingback.ping'] );
        unset( $methods['pingback.extensions.getPingbacks'] );
        return $methods;        
    }
    
    /**
     * Removes the WP Heartbeat Api. Caution: this disables the autosave functionality 
     */
    private function stop_heartbeat() {
        add_action('admin_enqueue_scripts', function() {
            wp_deregister_script('heartbeat');    
        });
	}
    
    /**
     * Block plugins to connect to external http's
     */  
    private function block_external_http() {
        if( ! is_admin() ) {
            add_filter( 'pre_http_request', array($this, 'external_http_error'), 100 );
        }
    }
    
    /**
     *  Throws a new WordPress Error when a service has an external request
     */
    public function external_http_error() {
	   return new WP_Error('http_request_failed', __('Request blocked by WP Optimize.'));
    }    
    
    /**
     * Removes the support and appearance of comments
     *
     * @uses MT_WP_Optimize::remove_comments_post_type
     * @uses MT_WP_Optimize::remove_comments_page
     * @uses MT_WP_Optimize::remove_comments_menu
     * @uses MT_WP_Optimize::return_false
     */  
    private function disable_comments() {
        
        // by default, comments are closed.
        update_option( 'default_comment_status', 'closed' );     
        
        // Closes plugins
        add_filter( 'comments_open', array($this, 'return_false'), 20, 2 );
        add_filter( 'pings_open', array($this, 'return_false'), 20, 2 );
        
        // Removes admin support
        add_action( 'admin_init', array($this, 'remove_comments_post_type') ); 
        
        add_action( 'admin_menu', array($this, 'remove_comments_page') );
        
        add_action( 'wp_before_admin_bar_render', array($this, 'remove_comments_menu') );              
        
    }
    
    /**
     * Removes the comments post type support for comments
     */
    public function remove_comments_post_type() {
        $post_types = get_post_types();
        foreach($post_types as $post_type) {
            if (post_type_supports($post_type, 'comments')) {
                remove_post_type_support($post_type, 'comments');
                remove_post_type_support($post_type, 'trackbacks');
            }
        }         
    }
    
    /**
     * Removes the comments admin page from the dashboard
     */
    public function remove_comments_page() {
        remove_menu_page('edit-comments.php');        
    }
    
    /**
     * Removes the comments menu item from the dashboard
     */ 
    public function remove_comments_menu() {
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu('comments');              
    }
    
    /**
     * Helper method which returns false
     */
    public function return_false() {
        return false;
    }

    /**
     * Deregisters jQuery.
     *
     * @uses MT_WP_Optimize::no_jquery_hook
     */    
    public function no_jquery() {
        add_action( 'wp_enqueue_scripts', array($this, 'no_jquery_hook'));
    }    
    
    /**
     * Deregisters jQuery Hook
     */
    public function no_jquery_hook() {
        wp_deregister_script('jquery');
    }
    
    /**
     * Removes the Embed Javascript
     *
     * @uses MT_WP_Optimize::no_embed_hook         
     */    
    public function no_embed() {
        add_action( 'init', array($this, 'no_embed_hook'));
    }    
    
    /**
     * Hooks for removing the Embed Functionality 
     */
    public function no_embed_hook() {
        
        // Removes the oEmbed JavaScript.
        remove_action('wp_head', 'wp_oembed_add_host_js'); 
        
        // Removes the oEmbed discovery links.
        remove_action('wp_head', 'wp_oembed_add_discovery_links');        
        
        // Remove the oEmbed route for the REST API epoint.
        remove_action('rest_api_init', 'wp_oembed_register_route');

        // Disables oEmbed auto discovery.
        remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);

    }    
      
}
