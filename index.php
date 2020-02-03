<?php
/*
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/*
 * @package C-test Generator
 * @copyright 2020 onwards Matt Bury (https://matbury.com/)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Set default values for input
$title = '[title] has not been set. Please go back and enter the title.';
$paragraph = '[paragraph] has not been set. Please enter a paragraph '
        . 'of at least 250 characters and 3 sentences.';
$format = 'pdf';
$post_vars_set = false;
// Set default display format to PDF
$beginblank = '<em>';
$endblank = '</em>';
// Counter for how many blanked words (for easier scoring)
$blank_word_count = 0;

// Get input data from c-test form
if(isset($_POST['title'])) {
    $title_check = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    if(mb_strlen($title_check) >= 1) {
        $title = $title_check;
    }
}

if(isset($_POST['paragraph'])) {
    $paragraph_check = filter_input(INPUT_POST, 'paragraph', FILTER_SANITIZE_STRING);
    if(mb_strlen($paragraph_check) > 249) {
        $paragraph = $paragraph_check;
        $post_vars_set = true;
    }
}

if(isset($_POST['copyright'])) {
    $copyright_check = filter_input(INPUT_POST, 'copyright', FILTER_SANITIZE_STRING);
    if(mb_strlen($paragraph_check) > 2) {
        $copyright = $copyright_check;
    }
}

if(isset($_POST['format'])) {
    $format_check = filter_input(INPUT_POST, 'format', FILTER_SANITIZE_STRING);
    if(mb_strlen($format_check) > 2) {
        $format = $format_check;
    }
}

// Generate c-test
if($post_vars_set) {
    set_format();
    // Split paragraph words & punctuation into array
    $items_array = split_paragraph($paragraph);
    // Get indexes of ends of sentences, i.e. '.', '!', and '?'
    $indexes = get_sentence_indexes($items_array);
    // Leave 1st 2 sentences intact, then blank every 2nd word
    if(count($indexes) > 2) {
        $items_array = write_blanks($items_array, $indexes[1]);
        // Generate paragraph text from array
        $reassembled_paragraph = reassemble_paragraph($items_array);
    } else {
        $reassembled_paragraph = 'Input paragraph text must be at least 3 sentences long.';
    }
    print_output_page();
}else{
    // Print ctest form
    print_input_page();
}

/*
 * 
 */
function set_format() {
    global $format, $beginblank, $endblank;
    if($format === 'moodle') {
        $beginblank = '{1:SA:=';
        $endblank = '}';
    }
}
/*
 * Split paragraph into words and punctuation
 * Parameter string
 * Returns array
 */
function split_paragraph(string $paragraph) {
    $items_array = preg_split('/(\w\S+\w)|(\w+)|(\s*\.{3}\s*)|(\s*[^\w\s]\s*)|\s+/', 
            $paragraph, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);
    return $items_array;
}

/*
 * Find indexes of beginnings of sentences
 * Parameter array
 * Returns array
 */
function get_sentence_indexes(array $items_array) {
    $indexes = array();
    $len = count($items_array);
    for($i = 0; $i < $len; $i++) {
        if(preg_match('/(\?|\.|!)/', $items_array[$i])) { // Match ?.! returns 
            array_push($indexes, $i+1);
        }
    }
    return $indexes;
}

/*
 * Select every other word for blanking
 * Parameters array, int
 * Returns array
 */
function write_blanks(array $items_array, int $index) {
    global $blank_word_count;
    $len = count($items_array);
    $even = false;
    for($i = $index; $i < $len; $i++) { // Start at 3rd sentence index
        if(preg_match('/[a-zA-Z]/', $items_array[$i])) { // Find words
            if($even) { // Every other word
                $even = false;
                $items_array[$i] = blank_word($items_array[$i]);
                $blank_word_count++;
            } else {
                $even = true;
            }
        }
    }
    return $items_array;
}

/*
 * Blank word, i.e. change letters in last half of word into blanks and add <em></em> tags
 * Parameter string
 * Returns string
 */
function blank_word(string $word) {
    global $beginblank, $endblank;
    $word_array = str_split($word);
    $len = count($word_array);
    $letters_str = '';
    $blanks_str = '';
    for($i = 0; $i < $len; $i++) {
        if($i < floor($len/2)) {
            $letters_str = $letters_str.$word_array[$i];
        } else {
            $blanks_str = $blanks_str.'_';
        }
    }
    $return_word = $letters_str.$beginblank.$blanks_str.$endblank;
    return $return_word;
}

/*
 * Reassemble paragraph
 * Parameter array
 * Returns string
 */
function reassemble_paragraph(array $items_array) {
    $len = count($items_array);
    $reassembled_paragraph = '';
    for($i = 0; $i < $len; $i++) {
        if(preg_match('/[a-zA-Z0-9]/', $items_array[$i])) { // Find words
            $reassembled_paragraph = $reassembled_paragraph.' '.$items_array[$i];
        } else {
            $reassembled_paragraph = $reassembled_paragraph.$items_array[$i];
        }
    }
    return $reassembled_paragraph;
}

/*
 * 
 */
function print_output_page() {
    global $title, $paragraph, $reassembled_paragraph, $blank_word_count, $copyright;
    $output_page = ' <!DOCTYPE html>
    <html>
        <head>
            <meta charset="UTF-8">
            <title>C-Test Generator: '.$title.'</title>
            <link rel="stylesheet" type="text/css" href="c-test.css">
            <script type="text/javascript"> 
            </script> 
        </head>

        <body>
        <h5><strong>Ctest Instructions: Read the text and complete the blanked words.</strong></h5>
            <h3>'.$title.'</h3>
                <p class="ctest">'.$reassembled_paragraph.'</p>
                <p class="ctest">Score: ____/'.$blank_word_count.'</p>
                <p>&nbsp;</p>
                <p>Text copyright information: '.$copyright.'</p>
                <p>&nbsp;</p>
            <p>&nbsp;</p>
            <p>&nbsp;</p>
            <h3>Answer Key</h3>
                <p>'.$paragraph.'</p>
                <p>Text copyright information: '.$copyright.'</p>
                <p><strong>Ctest Generator</strong></p>
                <ul>
                <li><i>By Matt Bury <a href="https://matbury.com/" target="_blank">https://matbury.com/</a></li>
                <li>Available under a GNU GPL v3 open software licence 
                <a href="https://www.gnu.org/copyleft/gpl.html" target="_blank">https://www.gnu.org/copyleft/gpl.html</a></li>
                <li>Source code is at <a href="https://github.com/matbury" target="_blank">https://github.com/matbury</a></li>
                </ul>
            <form> 
                <input type="button" value="Print" 
                   onclick="window.print()" /> 
            </form> 
        </body>
    </html> ';
    echo $output_page;
}

/*
 * 
 */
function print_input_page() {
    $output_page = ' <!DOCTYPE html>
    <html>
        <head>
            <meta charset="UTF-8">
            <title>C-Test Generator</title>
            <link rel="stylesheet" type="text/css" href="c-test.css">
            <script type="text/javascript"> 
            </script> 
        </head>

        <body>
        <p><strong>Ctest Instructions: Please enter a single paragraph of text which contains at least 3 sentences and is at least 250 characters long.</strong></p>
                <p>&nbsp;</p>
                <form action="index.php" method="post">
                <fieldset>
                    <legend>Ctest Text</legend>
                    Title:<br><textarea minlength=1 name="title" style="width:600px; height:20px;"></textarea><br>
                    Paragraph:<br><textarea minlength=250 name="paragraph" style="width:600px; height:300px;"></textarea><br>
                    Copyright:<br><textarea name="copyright" style="width:600px; height:75px;"></textarea><br>
                    <input type="radio" name="format" value="pdf"> Printable PDF format
                    <input type="radio" name="format" value="moodle"> Moodle Quiz Embedded Answers format<br>
                    <input type="submit" value="Submit">
                    <input type="reset">
                </fieldset>
                </form>
                <p>&nbsp;</p>
                <p><strong>Ctest Generator</strong></p>
                <ul>
                <li><i>By Matt Bury <a href="https://matbury.com/" target="_blank">https://matbury.com/</a></li>
                <li>Available under a GNU GPL v3 open software licence 
                <a href="https://www.gnu.org/copyleft/gpl.html" target="_blank">https://www.gnu.org/copyleft/gpl.html</a></li>
                <li>Source code is at <a href="https://github.com/matbury" target="_blank">https://github.com/matbury</a></li>
                </ul>
        </body>
    </html> ';
    echo $output_page;
}
