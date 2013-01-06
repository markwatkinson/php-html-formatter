<?php

require_once dirname(__FILE__) . '/HTMLNode.class.php';

class HTMLParser {

    private $node_stack = array();
    
    /// because we're possibly operating on fragments, we aren't going to end
    /// up with a nice single root node. So we're going to keep a set of 
    /// nodes which each represent a root for separate trees, although 
    /// we'll bung it all into one tree at the end.
    private $nodes = array();
    
    
    /** Parses the attributes out of a tag string and returns them as 
        a map of name=>value
    */
    private static function attributes($s) {
    
        $attrs = array();
        // black magic follows
        
        if (!preg_match_all('/
            (\\b[a-zA-Z_:\\-]+[a-zA-Z_:\\-]*) # attribute name     g1
            (=\s*+\s*+)                                          # g2
            (?: 
              (()([^\'"\s>]++)())   # unquoted                     g3-6
            | ((\')([^\'>]*+)(\')) # single quotes                 g7-10 
            | ((")([^">]*+)("))    # double quotes                 g11-14
            | ((\')([^\'>]*+)((?=>)))  # error case                g15-18
            | ((")([^">]*+)((?=>)))  # error case                  g19-22
        )/x', $s, $matches, PREG_SET_ORDER)) {
            return array();
        }
        
        foreach($matches as $m) {
            // take the first group that isn't empty. This is confusing. 
            for ($i=3; $i<=19; $i+=4) {
                if ($m[$i] !== '') {
                    $attrs[$m[1]] = $m[$i+2];
                    break;
                 }
            }
        }
        
        return $attrs;
    }
    
    /**
        Extracts a tag name from a string
    */
    private static function tag_name($s) {
        if (preg_match('%</? ([a-zA-Z_]+[\-\w_]* (:[a-zA-Z_]+[\-\w_]*)* ) %x', $s, $m)) {
            return strtolower($m[1]);
        }
        else return null;
    }
    
    /**
        Returns a reference to the current 'active' node. If none is active,
        return @c null
    */
    private function current_node() {
        $c = count($this->node_stack);
        return $c? $this->node_stack[$c-1] : null;
    }
    
    /** 
        Appends a self contained node (i.e. it is complete and has 
        no chance of any children being appended during the rest of the
        parse) to the current head of the stack. If the stack is empty, it
        is added to the list of known heads.
     */
    private function append_to_current($node) {
        $c = $this->current_node();
        if ($c !== null) $c->append($node);
        else $this->nodes[] = $node;
    }
    
    
    private function close_block_terminated() {
        $required = false;
        $at = false;
        foreach($this->node_stack as $index=>$n) {
            if (HTMLNodeUtils::terminated_by_block($n->tag())) {
                $required = true;
                $at = $index;
                break;
            }
        }
        if (!$required) return;

        while (count($this->node_stack) > $at) {
            $n = array_pop($this->node_stack);
            if ($n === null) break;
            $c = $this->current_node();
            if ($c !== null) $c->append($n);
            else $this->nodes[] = $n;
        }
    }
    
    
    private function open_tag($s) {
        $tag = self::tag_name($s);
        $strlen = strlen($s);
        $self_close = $strlen > 2 && $s[$strlen-2] === '/' ||
            HTMLNodeUtils::self_closing($tag);
        $attributes = self::attributes($s);
        
        if (HTMLNodeUtils::is_block($tag)) {
            $this->close_block_terminated();
        }
        
        $n = new HTMLElementNode();
        $n->tag($tag);
        $n->attributes($attributes);
        
        if ($n->tag() === 'img' && !$n->attribute('alt')) {
            if ($n->attribute('title')) $n->attribute('alt', $n->attribute('title'));
            else $n->attribute('alt', '');
        }
        
        if ($self_close) {
            $current = $this->current_node();
            if ($current) $current->append($n);
            else $this->nodes[] = $n;
        }
        else {
            $this->node_stack[] = $n;
        }
    }
    
    private function close_tag($s) {
        $tag = self::tag_name($s);
    
        $has_parent = false;
        foreach($this->node_stack as $n) {
            if ($n->tag() == $tag) {
                $has_parent = true;
                break;
            }
        }
        if (!$has_parent) { 
            // closing a non-open node, discard
            return;
        }

        while (($old_head = array_pop($this->node_stack)) !== null) {
            $tag_ = $old_head->tag();
            $new_head = $this->current_node();
            if ($new_head === null) {
                $this->nodes[] = $old_head;
                break;
            }
            else {
                $new_head->append($old_head);
                if ($tag_ == $tag) {
                    break;
                }
            }
        }
    }

    private function tag($s) {
        $comment = isset($s[1]) && $s[1] === '!';
        $close = isset($s[1]) && $s[1] === '/';
        if ($comment) {
            $this->comment($s);
        }
        else if ($close) {
            $this->close_tag($s);
        } else {
            $this->open_tag($s);
        }
    }
    
    private function text($s) {
        $node = new HTMLTextNode();
        $node->text($s);
        $this->append_to_current($node);
    }
    
    private function comment($s) {
        $node = new HTMLCommentNode();
        $text = preg_replace('% ^<!(--)?\s* | \s*-->$ %x', '', $s);
        $node->text = $text;
        $this->append_to_current($node);
    }

    /**
      * Parses an HTML fragment into a set of nodes. The return value is
      * an array of HTMLNodes each representing a root node of a separate tree.
      * In reality, you probably want to treat them as siblings.
      */
    function parse($s) {
        if (!is_string($s)) throw new InvalidArgumentException();
        
        // basic plan is:
        // we split the string by an HTML tag pattern, then we go through 
        // the resulting array and build up a rough DOM tree for the given
        // fragment.
        
        // preg_split seems to return overlapping results if we use subgroupings?
        $pattern_ = '%(</?[a-zA-Z_]+\w*+[^>]*/?>)%';
        $text_parts = preg_split($pattern_,
            $s,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        foreach($text_parts as $t) {
            if (isset($t[0]) && $t[0] == '<') {
                $this->tag($t);
            } else {
                $this->text($t);
            }
        }
        
        while( ($node = array_pop($this->node_stack) ) !== null) {
            $new_head = $this->current_node();
            if ($new_head !== null) {
                $new_head->append($node);
            } else {
                $this->nodes[] = $node;
            }
        }
        
        return $this->nodes;
    }
}