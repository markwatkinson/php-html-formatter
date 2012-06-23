<?php

require_once dirname(__FILE__) . '/../src/HTMLFormatter.class.php';

// high level test only so far because I'm lazy. 

$exit = 0;

function test($input, $output, $paragraph=false) {
    global $exit;
    $p = new HTMLFormatter ();
    if (!$paragraph) $p->set('prepend_paragraph', false);
    $out = $p->format($input);
    if ($out != $output) {
        echo "Expected:\n$output\n";
        echo "Got:\n$out\n";
        echo "\n";
        $exit = 1;
    }
}

function test_unchanged($input) {
    test($input, $input);
}

test_unchanged('abc');
test_unchanged('<a></a>');
test_unchanged('<img src=\'something.jpg\'/>');

// stuff should be escaped
test('<a href="\'">', '<a href=\'&#039;\'></a>');
// unterminated quote should get corrected
test('<a href="123>', '<a href=\'123\'></a>');


// whitespacing
test('1
2
3

4', '1
<br>
2
<br>
3
<p>
4');

test_unchanged('<pre>
1
2
3

4
</pre>');

// test the paragraphing



test('<h1>Heading</h1>Text', '<h1>Heading</h1>
<p>
Text', true);

test('<span>Heading</span>Text', '
<p>
<span>Heading</span>Text', true);

test('<ol><li>123
456</li></ol>
p', '<ol><li>123
<br>
456</li></ol>
<p>
p', true);

test('here is <em>some</em> <span>inline</span> <a>text</a>.', '
<p>
here is <em>some</em> <span>inline</span> <a>text</a>.', true);

test('some text

<h3>heading</h3>', '
<p>
some text<h3>heading</h3>', true);

exit($exit);