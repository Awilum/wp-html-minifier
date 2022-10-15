<?php

declare(strict_types=1);

/**
 * Plugin Name: HTML Minifier
 * Plugin URI: https://github.com/Awilum/wp-html-minifier
 * Description: Plugin for WordPress allows to improve your website overall performance by minifing HTML output across your entire website.
 * Author: Sergey Romanenko
 * Version: 1.0.0
 * Author URI: https://awilum.github.io/
 */

use voku\helper\HtmlMin;

! is_file($autoload = __DIR__ . '/vendor/autoload.php') and exit('Please run: <i>composer install</i>');

require_once $autoload;

version_compare($ver = PHP_VERSION, $req = '7.1.3', '<') and exit(sprintf('You are running PHP %s, but plugin needs at least <strong>PHP %s</strong> to run.', $ver, $req));

class HtmlMinifier {

    private static array $options = [];
    private static $htmlMin;

    public function __construct(){
        if(!is_admin()){
            
            self::$options = json_decode(file_get_contents(__DIR__ . '/settings.json'), true);
            self::$htmlMin = new HtmlMin();

            add_action('wp_loaded', array('HtmlMinifier', 'buffer_start'));
            add_action('shutdown', array('HtmlMinifier', 'buffer_end'));
        }
    }

    private static function callback($buffer) {

        $options = self::$options;
        $htmlMin = self::$htmlMin;
        
        // optimize html via "HtmlDomParser()"
        if ($options['optimize_via_dom_parser']) {
            $htmlMin->doOptimizeViaHtmlDomParser();        
        }
        
        // remove default HTML comments (depends on "doOptimizeViaHtmlDomParser(true)")
        if ($options['remove_comments'] && $options['optimize_via_dom_parser']) {
            $htmlMin->doRemoveComments();        
        }
        
        // sum-up extra whitespace from the Dom (depends on "doOptimizeViaHtmlDomParser(true)")
        if ($options['sum_up_whitespace'] && $options['optimize_via_dom_parser']) {
            $htmlMin->doSumUpWhitespace();  
        }
        
        // remove whitespace around tags (depends on "doOptimizeViaHtmlDomParser(true)")
        if ($options['remove_whitespace_around_tags'] && $options['optimize_via_dom_parser']) {
            $htmlMin->doRemoveWhitespaceAroundTags();  
        }
        
        // optimize html attributes (depends on "doOptimizeViaHtmlDomParser(true)")
        if ($options['optimize_attributes'] && $options['optimize_via_dom_parser']) {
            $htmlMin->doOptimizeAttributes();  
        }
        
        // remove optional "http:"-prefix from attributes (depends on "doOptimizeAttributes(true)")
        if ($options['remove_http_prefix_from_attributes'] && $options['optimize_attributes']) {
            $htmlMin->doRemoveHttpPrefixFromAttributes();  
        }
        
        // remove optional "https:"-prefix from attributes (depends on "doOptimizeAttributes(true)")
        if ($options['remove_https_prefix_from_attributes'] && $options['optimize_attributes']) {
            $htmlMin->doRemoveHttpsPrefixFromAttributes();  
        }
        
        // keep "http:"- and "https:"-prefix for all external links 
        if ($options['keep_http_and_https_prefix_on_external_attributes']) {
            $htmlMin->doKeepHttpAndHttpsPrefixOnExternalAttributes();  
        }
        
        // make some links relative, by removing the domain from attributes
        if ($options['make_same_domains_links_relative']) {
            $htmlMin->doMakeSameDomainsLinksRelative($options['make_same_domains_links_relative']); 
        }
        
        // remove defaults (depends on "doOptimizeAttributes(true)" | disabled by default)
        if ($options['remove_default_attributes'] && $options['optimize_attributes']) {
            $htmlMin->doRemoveDefaultAttributes($options['remove_default_attributes']); 
        }
        
        // remove deprecated anchor-jump (depends on "doOptimizeAttributes(true)")
        if ($options['remove_deprecated_anchor_name'] && $options['optimize_attributes']) {
            $htmlMin->doRemoveDeprecatedAnchorName(); 
        }
        
        // remove deprecated charset-attribute - the browser will use the charset from the HTTP-Header, anyway (depends on "doOptimizeAttributes(true)")
        if ($options['remove_deprecated_script_charset_attribute'] && $options['optimize_attributes']) {
            $htmlMin->doRemoveDeprecatedScriptCharsetAttribute(); 
        }
        
        // remove deprecated script-mime-types (depends on "doOptimizeAttributes(true)")
        if ($options['remove_deprecated_type_from_script_tag'] && $options['optimize_attributes']) {
            $htmlMin->doRemoveDeprecatedTypeFromScriptTag(); 
        }
        
        // remove "type=text/css" for css links (depends on "doOptimizeAttributes(true)")
        if ($options['remove_deprecated_type_from_stylesheet_link'] && $options['optimize_attributes']) {
            $htmlMin->doRemoveDeprecatedTypeFromStylesheetLink(); 
        }
        
        // remove "type=text/css" from all links and styles
        if ($options['remove_deprecated_type_from_style_and_link_tag']) {
            $htmlMin->doRemoveDeprecatedTypeFromStyleAndLinkTag(); 
        }
        
        // remove "media="all" from all links and styles
        if ($options['remove_default_media_type_from_style_and_link_tag']) {
            $htmlMin->doRemoveDefaultMediaTypeFromStyleAndLinkTag();
        }
        
        // remove type="submit" from button tags 
        if ($options['remove_default_type_from_button']) {
            $htmlMin->doRemoveDefaultTypeFromButton();
        }
        
        // remove some empty attributes (depends on "doOptimizeAttributes(true)")
        if ($options['remove_empty_attributes'] && $options['optimize_attributes']) {
            $htmlMin->doRemoveEmptyAttributes();
        }
        
        // remove 'value=""' from empty <input> (depends on "doOptimizeAttributes(true)")
        if ($options['remove_value_from_empty_input'] && $options['optimize_attributes']) {
            $htmlMin->doRemoveValueFromEmptyInput();
        }
        
        // sort css-class-names, for better gzip results (depends on "doOptimizeAttributes(true)")
        if ($options['sort_css_class_names'] && $options['optimize_attributes']) {
            $htmlMin->doSortCssClassNames();
        }
        
        // sort html-attributes, for better gzip results (depends on "doOptimizeAttributes(true)")
        if ($options['sort_html_attributes'] && $options['optimize_attributes']) {
            $htmlMin->doSortHtmlAttributes();
        }
        
        // remove more (aggressive) spaces in the dom (disabled by default)
        if ($options['remove_spaces_between_tags']) {
            $htmlMin->doRemoveSpacesBetweenTags();
        }
        
        // remove quotes e.g. class="lall" => class=lall
        if ($options['remove_omitted_quotes']) {
            $htmlMin->doRemoveOmittedQuotes();
        }
        
        // remove ommitted html tags e.g. <p>lall</p> => <p>lall 
        if ($options['remove_omitted_html_tags']) {
            $htmlMin->doRemoveOmittedHtmlTags();
        }

        return $htmlMin->minify($buffer);
    }

    public static function buffer_start() {
        ob_start(array('HtmlMinifier', 'callback'));
    }

    public static function buffer_end() {
        if(ob_get_length()){
            ob_end_flush();
        }
    }
}

new HtmlMinifier();
