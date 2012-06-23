<?php

require_once dirname(__FILE__) . '/HTMLNodeType.class.php';
require_once dirname(__FILE__) . '/HTMLNodeUtils.class.php';


/**
  * please note!
  * This isn't written to any kind of spec or even convention.
  * Stuff like text() won't behave how you expect.
  * It's just supposed to be a minimal hierarchical node structure, definitely
  * not a fully fledged XML/HTML parser or API.
*/


abstract class HTMLNode {

    /// HTMLNodeType
    protected $type;

    private $attributes = array();

    protected $children = array();

    /// Reference to parent
    public $par = null;

    /// cache so that we don't have to recaculate these every time
    private $next_siblings = array();
    private $prev_siblings = array();
    
    /// HTMLNodeUtils
    protected $utils;
    
    public function HTMLNode() {
        $this->utils = new HTMLNodeUtils();
    }
    
    public function type($type=null) {
        return $this->type;
    }
    
    public function attributes($new_attrs=null, $clear=false) {
        if ($new_attrs !== null) {
            if ($clear) { $this->attributes = array(); }
            foreach($new_attrs as $k=>&$v) {
                $v = $this->utils->escape($v);
            }
            $this->attributes = array_merge($this->attributes, $new_attrs);
        } else { 
            return $this->attributes;
        }        
    }
    
    public function children() {
        return $this->children;
    }
    
    // thanks for making parent a reserved word.
    // the one word in the english language with no direct synonym.
    /// Cannot set parent directly - see append()
    public function par() {
        return $this->par;
    }
    
    
    // shouldn't be called directly.
    public function recalculate_siblings() {
        $this->next_siblings = array();
        $this->prev_siblings = array();
        $before = true;
        if ($this->par !== null) {
            foreach($this->par->children as $c) {
                if ($c === $this) {
                    $before = false;
                    continue;
                }
                if ($before) $this->prev_siblings[] = $c;
                else $this->next_siblings[] = $c;
            }
        }
    }
    
    public function prev_sibling_of_type($type) {
        $this->recalculate_siblings();
    
        foreach($this->prev_siblings as $c) {
            if ($c->type() === $type) return $c;
        }
        return null;
    }
    public function next_sibling_of_type($type) {
        $this->recalculate_siblings();
        foreach($this->next_siblings as $c) {
            if ($c->type() === $type) return $c;
        }
        return null;
    }
    public function next_sibling() {
        $this->recalculate_siblings();
        return !empty($this->next_siblings)? $this->next_siblings[0] : null;
    }
    
    public function prev_sibling() {
        $this->recalculate_siblings();
        $c = count($this->prev_siblings);
        
        return $c? $this->prev_siblings[$c-1] : null;
    }
    
    
    public function replace_child(HTMLNode $child, HTMLNode $with) {
        foreach($this->children as $i=>$c) {
            if ($c === $child) {
                $this->children[$i] = $with;
                break;
            }
        }
    }
    
    
    public function replace_with(HTMLNode $node) {
        $p = $this->par();
        if ($p !== null) {
            $p->replace_child($this, $node);
        }
        
    }
    
    
    
    public function has_parent($tag) {
        $node = $this;
        while ( ($node = $node->par()) !== null ) {
            if ($node->tag() === $tag) return true;
        }
        return false;
    }
}

class HTMLElementNode extends HTMLNode {

    private $tag = null;
    private $self_closing = false;
    
    public function HTMLElementNode() {
        parent::__construct();
        $this->type = HTMLNodeType::ELEMENT;
    }

    public function append(HTMLNode $node) {
        $node->par = &$this;
        $this->children[] = $node;
        $node->recalculate_siblings();
    }
    
    public function tag($tag=null) {
        if ($tag !== null) {
            $tag = strtolower($tag);
            $this->tag = $tag;
            $this->self_closing = $this->utils->self_closing($tag);;
        } else {
            return $this->tag;
        }
    }
    
    public function text() {
        $t = '<' . $this->tag;
        foreach($this->attributes() as $attr=>$value) {
            $t .= " {$attr}='{$value}'";
        }
        if ($this->self_closing) { $t .= '/'; };
        $t .= '>';
        
        foreach($this->children() as $c) {
            $t .= $c->text();
        }
        
        if (!$this->self_closing) {
            $t .= '</' . $this->tag . '>';
        }
        return $t;
    }
}

class HTMLTextNode extends HTMLNode {

    private $text;
    
    public function HTMLTextNode() {
        parent::__construct();
        $this->type = HTMLNodeType::TEXT;
    }
    
    public function text($t = null) {
        if ($t !== null) {
            $this->text = $this->utils->escape($t);
        } else { 
            return $this->text;
        }
    }
    
    public function html($html) {
        // FIXME this should parse the string and inject the correct
        // elements as siblings to this text node.
        $this->text = $html;
    }
}

class HTMLCommentNode extends HTMLTextNode {

    public function HTMLCommentNode() {
        parent::__construct();
        $this->type = HTMLNodeType::COMMENT;
    }
    
    public function text($t) {
        if ($t !== null) {
            parent::text($t);
        } else {
            return '<!--' . parent::text() . '-->';
        }
    }
}

