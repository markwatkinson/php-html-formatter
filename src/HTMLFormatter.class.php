<?php

require_once dirname(__FILE__) . '/HTMLParser.class.php';

class HTMLFormatter {

    private $options = array(
            'link' => true,
            'paragraph' => true,
            'prepend_paragraph' => true,
            'linebreak' => true,
            'ltrim' => true,
            'rtrim' => true,
        );
        

    private function format_options($node) {
        // we're overriding the template here, so we don't
        // set a 'full' template, we just change what we're interested in
        $template = array();
        $block = false;
        $element = $node->type() === HTMLNodeType::ELEMENT;

        $parent = $node->par();
        $grandparent = ($parent === null)? null : $parent->par();
        $direct_child = $parent !== null && $grandparent === null;

        $tag = null;

        if ($element) {
            $tag = $node->tag();
            $block = HTMLNodeUtils::is_block($tag);

            
            if ($tag === 'a') $template['link'] = false;
            
            elseif ($tag === 'p') {
                $template['paragraph'] = false;
            }
            
            elseif (in_array($tag, array('dl', 'ol', 'ul', 'table'))) {
                $template = array(
                    'paragraph' => false,
                    'linebreak' => false
                );
            }
            // override from the prev. rule
            elseif (in_array($tag, array('dd', 'dt', 'li', 'td', 'th'))) {
                $template = array(
                    'linebreak' => true,
                    'rtrim' => true,
                    'ltrim' => true
                );
            }
            elseif (in_array($tag, array('code', 'pre'))) {
                $template = array(
                    'paragraph' => false,
                    'linebreak' => false,
                    'link' => false,
                    'ltrim' => false,
                    'rtrim' => false,
                    'prepend_paragraph' => false
                );
            }
            elseif( in_array($tag, array('blockquote') ) ) {
                $template = array(
                    'paragraph' => false,
                    'linebreak' => false
                );
            }
        }
        
        

        if (!isset($template['ltrim']) && !isset($template['rtrim'])) {
            if ($element && !$block) {
                $template['ltrim'] = false;
                $template['rtrim'] = false;
            }
            
            else {
                $prev = $node->prev_sibling();
                $next = $node->next_sibling();
                $prev_block = $prev !== null && $prev->type() ===
                    HTMLNodeType::ELEMENT &&
                    HTMLNodeUtils::is_block($prev->tag());
                $next_block = $next !== null && $next->type() === 
                    HTMLNodeType::ELEMENT &&
                    HTMLNodeUtils::is_block($next->tag());
                if ($prev !== null)
                    $template['ltrim'] = $prev_block;
                if ($next !== null)
                    $template['rtrim'] = $next_block;
            }
        }
        
        
        if (!isset($template['prepend_paragraph']) &&
            $node->type() === HTMLNodeType::TEXT) 
        {
            $p = $node;
            $para_allowed = true;
            do {
                if ($p->type() === HTMLNodeType::ELEMENT) {
                    $tag = $p->tag();
                    if (in_array($tag, array('li', 'ol', 'ul', 'table'))) {
                        $para_allowed = false;
                    }
                }
            }
            while( ($p = $p->par()) !== null && $para_allowed );
            
            if ($para_allowed) {
        
                $type = $node->type();
                $trimmed_text = trim($node->text());
                $prev = $node->prev_sibling();
                $next = $node->next_sibling();
                
                $prev_type = ($prev === null)? null : $prev->type();
                $next_type  = ($next === null)? null : $next->type();
                
                $prev_is_block = $prev_type === HTMLNodeType::ELEMENT &&
                    HTMLNodeUtils::is_block($prev->tag());
                $prev_is_inline = ($prev !== null && !$prev_is_block);
                $next_is_block = $next_type === HTMLNodeType::ELEMENT &&
                    HTMLNodeUtils::is_block($next->tag());
                $next_is_inline = ($prev !== null && !$prev_is_block);
                    
                
                if ($prev === null || (
                        $prev !== null && $prev->type() === HTMLNodeType::TEXT &&
                        trim($prev->text()) == ''
                    )
                )
                {
                    $prev = $node->prev_sibling_of_type(HTMLNodeType::ELEMENT);
                    $prev_is_block = ($prev !== null && 
                        HTMLNodeUtils::is_block($prev->tag())
                    );
                    $prev_is_inline = ($prev !== null && !$prev_is_block);
                }
                if ($next === null || (
                        $next !== null && $next->type() === HTMLNodeType::TEXT &&
                        trim($next->text()) == ''
                    )
                ) 
                {
                    $next = $node->next_sibling_of_type(HTMLNodeType::ELEMENT);
                    $next_is_block = ($next !== null &&
                        HTMLNodeUtils::is_block($next->tag())
                    );
                    $next_is_inline = ($prev !== null && !$prev_is_block);
                }
                
                if ( !empty($trimmed_text) && (
                        $prev_is_block && !$next_is_inline || 
                        $next_is_block && !$prev_is_inline
                     )
                ) {
                    $template['prepend_paragraph'] = true;
                }
                else if ($direct_child && 
                    ($next !== null && !$next_is_block) &&
                    ($prev_is_block || $prev === null)
                ) {
                    $template['prepend_paragraph'] = true;
                }
                else if ($direct_child && $prev === null && $next === null) {
                    $template['prepend_paragraph'] = true;
                }
            }
        }
        return $template;
    }
    
    private function format_node($node, $options) {
        
        if ($node->type() !== HTMLNodeType::TEXT) {
            assert(0);
            return;
        }
        
        $template = array(
            'link' => true,
            'paragraph' => true,
            'prepend_paragraph' => false,
            'linebreak' => true,
            'ltrim' => true ,
            'rtrim' => true 
        );
        $template = array_merge($template, $options);
        $text = $node->text();
        $prepend_paragraph = false;
        if ($template['ltrim'] && $this->options['ltrim']) {
            $text = ltrim($text);
        }
        if ($template['rtrim'] && $this->options['rtrim']) {
            $text = rtrim($text);
        }
        if ($template['link'] && $this->options['link']) {
            // TODO this regex could be better. Escaping and stuff as well.
            $text = preg_replace('%https?://[^\s]+%i', '<a href="$0">$0</a>', $text);
        }
        if ($template['prepend_paragraph'] && $this->options['prepend_paragraph']) {
            $text = '<p>' . $text;
            $prepend_paragraph = true;
        }
        if ($template['paragraph'] && $this->options['paragraph']) {
            $text = preg_replace("/(\r\n|[\r\n]){2,}/", '<p>', $text);
        }
        if ($template['linebreak'] && $this->options['linebreak'] && !$prepend_paragraph) {
            $text = str_replace("\r\n", "<br>", $text);
            $text = str_replace("\r", "<br>", $text);
            $text = str_replace("\n", "<br>", $text);
        }
        
        // for vague readability of the source, we now reinsert the line breaks
        if ($template['paragraph'] && $this->options['paragraph']) {
            $text = str_replace('<p>', "\n<p>\n", $text);
        }
        if ($template['linebreak'] && $this->options['linebreak']) {
            $text = str_replace('<br>', "\n<br>\n", $text);
        }
        
        $node->html($text);
    }

    private function format_recurse($node, $options=array()) {
        // NOTE: due to the recursion, the options are hierarchical and
        // get overridden at each level. This is confusing, but it is also
        // pretty awesome!
        $options = array_merge($options, $this->format_options($node));
        if($node->type() === HTMLNodeType::TEXT) {
            $this->format_node($node, $options);
        }
        foreach($node->children() as $c) {
            $this->format_recurse($c, $options);
        }
    }

    function format($s) {
        if (!is_string($s)) throw new InvalidArgumentException();
        
        $parser = new HTMLParser();
        $nodes = $parser->parse($s);
        
        // we use a dummy root node to keep things neat - there's no 
        // reason to believe that $s will be a nice self-contained tree - more
        // likely it was a fragment with several nodes at the root level, so 
        // we contain it all under this one for now.
        
        $dummy_root = new HTMLElementNode();
        $dummy_root->tag('body');
        foreach($nodes as $n) {
            $dummy_root->append($n);
        }
        $this->format_recurse($dummy_root);
        $ret = '';
        foreach($dummy_root->children() as $c) {
            $ret .= $c->text();
        }
        return $ret;
    }
    
    function set($option, $value) {
        if (isset($this->options[$option])) {
            $this->options[$option] = $value;
        }
        else throw new InvalidArgumentException();
    }
}