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
 * What does this script do?
 * User inputs (copy & paste) a paragraph of text of at least 3 sentences.
 * Script leaves 1st 2 sentences intact,
 * then every 2nd word has 2nd half of word blanked.
 * PDF mode, blanks = _ (CSS styled with gaps between)
 * Moodle mode, blanks = Moodle > Quiz > Embedded answers (close) short answer format, e.g. second ha{1:SA:=lf} of ev{1:SA:=ery} other wo{1:SA:=rd}
 * HTML form mode, blanks = input text field, background image represents underscores showing number of missing letters, maxlength limits user input to length of blanks.
 * Font is monospace so letters line up with underscores on HTML input fields
 * Background underscore image matches width & height of monospace font characters exactly (12 x 18px)
 * Question: Can the shortanswer qtype be adapted to automagially generate c-tests like this?
 */

/*
 * @package C-test Generator
 * @copyright 2020 Matt Bury (https://matbury.com/)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Set default values for input
$title = '[title]';
$paragraph = '[paragraph]';
$format = 'pdf';
$post_vars_set = false;
// Counter for how many blanked words (for easier scoring)
$blank_word_count = 0;

// Get input data from c-test form
if(isset($_POST['title'])) {
    $title_check = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    if(mb_strlen($title_check) > 0) {
        $title = $title_check;
    }
}

if(isset($_POST['paragraph'])) {
    //$paragraph_check = filter_input(INPUT_POST, 'paragraph', FILTER_UNSAFE_RAW); // For testing only UNSAFE! DO NOT PUT ON PUBLICLY ACCESSIBLE SERVER!
    $paragraph_check = filter_input(INPUT_POST, 'paragraph', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
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
    // Split paragraph words & punctuation into array
    $items_array = split_paragraph($paragraph);
    // Get indexes of ends of sentences, i.e. '.', '!', and '?'
    $indexes = get_sentence_indexes($items_array);
    set_format($format);
    print_output_page();
    
} else {
    // Print ctest form
    print_input_page();
}

/*
 * 
 */
function set_format($format) {
    global $items_array, $indexes, $reassembled_paragraph;
    if(count($indexes) > 2) {
        if($format === 'moodle') {
            $items_array = write_blanks_moodle($items_array, $indexes[1]);
        }
        if($format === 'htmlform') {
            $items_array = write_blanks_html($items_array, $indexes[1]);
        }   
        if($format === 'pdf') {
            $items_array = write_blanks_pdf($items_array, $indexes[1]);
        }
        // Generate paragraph text from array
        $reassembled_paragraph = reassemble_paragraph($items_array);
    } else {
        $reassembled_paragraph = 'Input paragraph text must be at least 3 sentences long.';
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
function write_blanks_pdf(array $items_array, int $index) {
    global $blank_word_count;
    $len = count($items_array);
    $even = false;
    for($i = $index; $i < $len; $i++) { // Start at 3rd sentence index
        if(preg_match('/[a-zA-Z]/', $items_array[$i])) { // Find words
            if($even) { // Every other word
                $even = false;
                $items_array[$i] = blank_word_pdf($items_array[$i]);
                $blank_word_count++;
            } else {
                $even = true;
            }
        }
    }
    return $items_array;
}

/*
 * Select every other word for blanking
 * Parameters array, int
 * Returns array
 */
function write_blanks_html(array $items_array, int $index) {
    global $blank_word_count;
    $len = count($items_array);
    $even = false;
    for($i = $index; $i < $len; $i++) { // Start at 3rd sentence index
        if(preg_match('/[a-zA-Z]/', $items_array[$i])) { // Find words
            if($even) { // Every other word
                $even = false;
                $items_array[$i] = blank_word_html($items_array[$i]);
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
function blank_word_pdf(string $word) {
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
    $return_word = $letters_str.'<em>'.$blanks_str.'</em>';
    return $return_word;
}

/*
 * Blank word, i.e. change letters in last half of word into blanks and add <em></em> tags
 * Parameter string
 * Returns string
 */
function blank_word_html(string $word) {
    $word_array = str_split($word);
    $len = count($word_array);
    $letters_str = '';
    $blanks_str = '';
    for($i = 0; $i < $len; $i++) {
        if($i < floor($len/2)) {
            $letters_str = $letters_str.$word_array[$i];
        } else {
            $blanks_str = $blanks_str.$word_array[$i];
        }
    }
    $blank_len = count(str_split($blanks_str));
    $return_word = $letters_str.'<input type="text" name="ctest[]" id="ctest[]" style="width: '.($blank_len*12).'px;" maxlength="'.$blank_len.'"/>';
    return $return_word;
}

/*
 * Select every other word for blanking
 * Parameters array, int
 * Returns array
 */
function write_blanks_moodle(array $items_array, int $index) {
    global $blank_word_count;
    $len = count($items_array);
    $even = false;
    for($i = $index; $i < $len; $i++) { // Start at 3rd sentence index
        if(preg_match('/[a-zA-Z]/', $items_array[$i])) { // Find words
            if($even) { // Every other word
                $even = false;
                $items_array[$i] = blank_word_moodle($items_array[$i]);
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
function blank_word_moodle(string $word) {
    $word_array = str_split($word);
    $len = count($word_array);
    $letters_str = '';
    $blanks_str = '';
    for($i = 0; $i < $len; $i++) {
        if($i < floor($len/2)) {
            $letters_str = $letters_str.$word_array[$i];
        } else {
            $blanks_str = $blanks_str.$word_array[$i];
        }
    }
    $return_word = $letters_str.'{1:SA:='.$blanks_str.'}';
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
 * Print HTML c-test page
 */
function print_output_page() {
    global $title, $reassembled_paragraph, $blank_word_count, $copyright;
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
                <p>Text reference: '.$copyright.'</p>
                <p>&nbsp;</p>
                <p><strong>Ctest Generator</strong> by Matt Bury <a href="https://matbury.com/" target="_blank">https://matbury.com/</a> is available under a GNU GPL v3 open software licence 
                <a href="https://www.gnu.org/copyleft/gpl.html" target="_blank">https://www.gnu.org/copyleft/gpl.html</a>. Source code is at <a href="https://github.com/matbury" target="_blank">https://github.com/matbury</a></li>
                </p>
            <form> 
                <input type="button" value="Print" 
                   onclick="window.print()" /> 
            </form> 
        </body>
    </html> ';
    echo $output_page;
}

/*
 * Print HTML input form
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
                <form action="index.php" method="post" enctype="multipart/form-data">
                <fieldset>
                    <legend>Ctest Text</legend>
                    Title:<br><textarea minlength=1 name="title" style="width:600px; height:20px;" required></textarea><br>
                    Paragraph:<br><textarea minlength=250 name="paragraph" style="width:600px; height:300px;" required></textarea><br>
                    Copyright:<br><textarea name="copyright" style="width:600px; height:75px;"></textarea><br>
                    <input type="radio" name="format" value="pdf"> Printable PDF format 
                    <input type="radio" name="format" value="moodle"> Moodle Quiz Embedded Answers format 
                    <input type="radio" name="format" value="htmlform"> HTML form<br>
                    <input type="submit" value="Submit"><input type="reset">
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
