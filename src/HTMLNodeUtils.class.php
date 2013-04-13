<?php

class HTMLNodeUtils {

    public static function escape($s) {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8', false);
    }
    public static function self_closing($tag) {
        return in_array($tag, array('area', 'base', 'basefont', 'br', 'hr', 
            'input', 'img', 'link', 'source'));
    }
    
    public static function terminated_by_block($tag) {
        return in_array($tag, array('p'));
    }
    
    public static function is_block($tag) {
    // possibly not exhaustive
    // http://www.cs.sfu.ca/CourseCentral/165/common/ref/wdgxhtml10/block.html
    return in_array($tag, array(
        'address',
        'aside',
        'article',
        'blockquote',
        'center',
        'dir',
        'div',
        'dl',
        'fieldset',
        'form',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'hr',
        'isindex',
        'menu',
        'noframes',
        'noscript',
        'ol',
        'p',
        'pre',
        'table',
        'ul',
        'dd',
        'dt',
        'frameset',
        'tbody',
        'td',
        'tfoot',
        'th',
        'thead',
        'tr',
        'applet',
        'button',
        'del',
        'iframe',
        'ins',
        'map',
        'object',
        'script',
        'li'));
    }
    
}