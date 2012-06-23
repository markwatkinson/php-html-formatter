HTML formatter for PHP

Basically what this does is it attempts to apply some typography to an HTML
fragment. It will try to insert paragraphs, linebreaks, links, etc in sensible
places and *try not to insert them in wrong places* (like messing with
paragraphs in pre/code blocks).


Usage:

require_once 'src/HTMLFormatter.class.php';

$f = new HTMLFormatter();
echo $p->format($text);
