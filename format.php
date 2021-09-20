<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Code for changing gift to answersselect when importing gift.
 *
 * @package    qformat_gift_answersselect
 * @copyright  Joseph Rézeau 2021 <joseph@rezeau.org>
 * @copyright based on work by 1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Define answersselectmode modes.
/**
 * Use all answers (default mode).
 */
define('ANSWERSSELECTMODE_DEFAULT',   '0');

/**
 * Manual selection.
 */
define('ANSWERSSELECTMODE_MANUAL',     '1');

/**
 * Automatic random selection.
 */
define('ANSWERSSELECTMODE_AUTO',    '2');

require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * Importer for answersselect question format FROM gift files.
 *
 * @copyright  Joseph Rézeau 2021  <joseph@rezeau.org>
 * @copyright based on work by 1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qformat_gift_answersselect extends qformat_default {

    /**
     * Provide import
     *
     * @return bool
     */
    public function provide_import() {

        return true;
    }

    /**
     * We do not export
     *
     * @return bool
     */
    public function provide_export() {
        return false;
    }

    /**
     * Check if the given file is capable of being imported by this plugin.
     *
     * Note that expensive or detailed integrity checks on the file should
     * not be performed by this method. Simple file type or magic-number tests
     * would be suitable.
     *
     * @param stored_file $file the file to check
     * @return bool whether this plugin can import the file
     */
    public function can_import_file($file) {
        $mimetypes = array(
            mimeinfo('type', '.txt')
        );

        return in_array($file->get_mimetype(), $mimetypes);
    }

    protected function answerweightparser(&$answer) {
        $answer = substr($answer, 1);                        // Removes initial %.
        $endposition  = strpos($answer, "%");
        $answerweight = substr($answer, 0, $endposition);  // Gets weight as integer.
        $answerweight = $answerweight / 100;                 // Converts to percent.
        $answer = substr($answer, $endposition + 1);          // Removes comment from answer.
        return $answerweight;
    }

    protected function commentparser($answer, $defaultformat) {
        $bits = explode('#', $answer, 2);
        $ans = $this->parse_text_with_format(trim($bits[0]), $defaultformat);
        if (count($bits) > 1) {
            $feedback = $this->parse_text_with_format(trim($bits[1]), $defaultformat);
        } else {
            $feedback = array('text' => '', 'format' => $defaultformat, 'files' => array());
        }
        return array($ans, $feedback);
    }

    protected function split_truefalse_comment($answer, $defaultformat) {
        $bits = explode('#', $answer, 3);
        $ans = $this->parse_text_with_format(trim($bits[0]), $defaultformat);
        if (count($bits) > 1) {
            $wrongfeedback = $this->parse_text_with_format(trim($bits[1]), $defaultformat);
        } else {
            $wrongfeedback = array('text' => '', 'format' => $defaultformat, 'files' => array());
        }
        if (count($bits) > 2) {
            $rightfeedback = $this->parse_text_with_format(trim($bits[2]), $defaultformat);
        } else {
            $rightfeedback = array('text' => '', 'format' => $defaultformat, 'files' => array());
        }
        return array($ans, $wrongfeedback, $rightfeedback);
    }

    protected function escapedchar_pre($string) {
        // Replaces escaped control characters with a placeholder BEFORE processing.

        $escapedcharacters = array("\\:",    "\\#",    "\\=",    "\\{",    "\\}",    "\\~",    "\\n"  );
        $placeholders      = array("&&058;", "&&035;", "&&061;", "&&123;", "&&125;", "&&126;", "&&010");

        $string = str_replace("\\\\", "&&092;", $string);
        $string = str_replace($escapedcharacters, $placeholders, $string);
        $string = str_replace("&&092;", "\\", $string);
        return $string;
    }

    protected function escapedchar_post($string) {
        // Replaces placeholders with corresponding character AFTER processing is done.
        $placeholders = array("&&058;", "&&035;", "&&061;", "&&123;", "&&125;", "&&126;", "&&010");
        $characters   = array(":",     "#",      "=",      "{",      "}",      "~",      "\n"  );
        $string = str_replace($placeholders, $characters, $string);
        return $string;
    }

    protected function check_answer_count($min, $answers, $text) {
        $countanswers = count($answers);
        if ($countanswers < $min) {
            $this->error(get_string('importminerror', 'qformat_gift_answersselect'), $text);
            return false;
        }

        return true;
    }

    protected function parse_text_with_format($text, $defaultformat = FORMAT_MOODLE) {
        $result = array(
            'text' => $text,
            'format' => $defaultformat,
            'answersselectmode' => ANSWERSSELECTMODE_DEFAULT,
            'files' => array(),
        );
        if (strpos($text, '[') === 0) {
            $formatend = strpos($text, ']');
            $result['format'] = $this->format_name_to_const(substr($text, 1, $formatend - 1));
            if ($result['format'] == -1) {
                $result['format'] = $defaultformat;
            } else {
                $result['text'] = substr($text, $formatend + 1);
            }
        }
        $result['text'] = trim($this->escapedchar_post($result['text']));
        return $result;
    }

    protected function parse_text_with_answersselectmode($text, $defaultoption = ANSWERSSELECTMODE_DEFAULT) {
        $text['answersselectmode'] = $defaultoption;
        $t = $text['text'];
        if (strpos($t, '[') === 0) {
            $formatend = strpos($t, ']');
            $text['answersselectmode'] = $this->answersselectmode_name_to_const(substr($t, 1, $formatend - 1));
            $text['text'] = substr($t, $formatend + 1);
        }
        return $text;
    }
    /**
     * @param int $format one of the FORMAT_ constants.
     * @return string the corresponding name.
     */
    protected function format_name_to_const($format) {
        if ($format == 'moodle') {
            return FORMAT_MOODLE;
        } else if ($format == 'html') {
            return FORMAT_HTML;
        } else if ($format == 'plain') {
            return FORMAT_PLAIN;
        } else if ($format == 'markdown') {
            return FORMAT_MARKDOWN;
        } else {
            return -1;
        }
    }

    /**
     * @param int $option one of the ANSWERSSELECTMODE_ constants.
     * @return string the corresponding name.
     */
    protected function answersselectmode_name_to_const($option) {
        if ($option == 'default') {
            return ANSWERSSELECTMODE_DEFAULT;
        } else if ($option == 'manual') {
            return ANSWERSSELECTMODE_MANUAL;
        } else if ($option == 'auto') {
            return ANSWERSSELECTMODE_AUTO;
        } else {
            return -1;
        }
    }

    /**
     * Extract any tags or idnumber declared in the question comment.
     *
     * @param string $comment E.g. "// Line 1.\n//Line 2.\n".
     * @return array with two elements. string $idnumber (or '') and string[] of tags.
     */
    public function extract_idnumber_and_tags_from_comment(string $comment): array {

        // Find the idnumber, if any. There should not be more than one, but if so, we just find the first.
        $idnumber = '';
        if (preg_match('~
                # Start of id token.
                \[id:

                # Any number of (non-control) characters, with any ] escaped.
                # This is the bit we want so capture it.
                (
                    (?:\\\\]|[^][:cntrl:]])+
                )

                # End of id token.
                ]
                ~x', $comment, $match)) {
            $idnumber = str_replace('\]', ']', trim($match[1]));
        }

        // Find any tags.
        $tags = [];
        if (preg_match_all('~
                # Start of tag token.
                \[tag:

                # Any number of allowed characters (see PARAM_TAG), with any ] escaped.
                # This is the bit we want so capture it.
                (
                    (?:\\\\]|[^]<>`[:cntrl:]]|)+
                )

                # End of tag token.
                ]
                ~x', $comment, $matches)) {
            foreach ($matches[1] as $rawtag) {
                $tags[] = str_replace('\]', ']', trim($rawtag));
            }
        }

        return [$idnumber, $tags];
    }

    public function readquestion($lines) {
        // Given an array of lines known to define a question in this format, this function
        // converts it into a question object suitable for processing and insertion into Moodle.

        $question = $this->defaultquestion();
        // Define replaced by simple assignment, stop redefine notices.
        $giftanswerweightregex = '/^%\-*([0-9]{1,2})\.?([0-9]*)%/';

        // Separate comments and implode.
        $comments = '';
        foreach ($lines as $key => $line) {
            $line = trim($line);
            if (substr($line, 0, 2) == '//') {
                $comments .= $line . "\n";
                $lines[$key] = ' ';
            }
        }
        $text = trim(implode("\n", $lines));

        if ($text == '') {
            return false;
        }

        // Substitute escaped control characters with placeholders.
        $text = $this->escapedchar_pre($text);

        // Look for category modifier.
        if (preg_match('~^\$CATEGORY:~', $text)) {
            $newcategory = trim(substr($text, 10));

            // Build fake question to contain category.
            $question->qtype = 'category';
            $question->category = $newcategory;
            return $question;
        }

        // Question name parser.
        if (substr($text, 0, 2) == '::') {
            $text = substr($text, 2);

            $namefinish = strpos($text, '::');
            if ($namefinish === false) {
                $question->name = false;
                // Name will be assigned after processing question text below.
            } else {
                $questionname = substr($text, 0, $namefinish);
                $question->name = $this->clean_question_name($this->escapedchar_post($questionname));
                $text = trim(substr($text, $namefinish + 2)); // Remove name from text.
            }
        } else {
            $question->name = false;
        }

        // Find the answer section.
        $answerstart = strpos($text, '{');
        $answerfinish = strpos($text, '}');

        $description = false;
        if ($answerstart === false && $answerfinish === false) {
            // No answer means it's a description.
            $description = true;
            $answertext = '';
            $answerlength = 0;

        } else if ($answerstart === false || $answerfinish === false) {
            $this->error(get_string('braceerror', 'qformat_gift_answersselect'), $text);
            return false;

        } else {
            $answerlength = $answerfinish - $answerstart;
            $answertext = trim(substr($text, $answerstart + 1, $answerlength - 1));
        }

        // Format the question text, without answer, inserting "_____" as necessary.
        if ($description) {
            $questiontext = $text;
        } else if (substr($text, -1) == "}") {
            // No blank line if answers follow question, outside of closing punctuation.
            $questiontext = substr_replace($text, "", $answerstart, $answerlength + 1);
        } else {
            // Inserts blank line for missing word format.
            $questiontext = substr_replace($text, "_____", $answerstart, $answerlength + 1);
        }

        // Look to see if there is any general feedback.
        $gfseparator = strrpos($answertext, '####');
        if ($gfseparator === false) {
            $generalfeedback = '';
        } else {
            $generalfeedback = substr($answertext, $gfseparator + 4);
            $answertext = trim(substr($answertext, 0, $gfseparator));
        }

        // Get questiontext format from questiontext.
        $text = $this->parse_text_with_format($questiontext);
        $question->questiontextformat = $text['format'];

        // Get questiontext answersselectmode from parsed questiontext.
        $text = $this->parse_text_with_answersselectmode($text);
        $question->answersselectmode = $text['answersselectmode'];

        // One more turn in case the answersselectmode option was written first in the gift file!
        $text = $this->parse_text_with_format($text['text']);
        $question->questiontextformat = $text['format'];
        $question->questiontext = $text['text'];

        // Get generalfeedback format from questiontext.
        $text = $this->parse_text_with_format($generalfeedback, $question->questiontextformat);
        $question->generalfeedback = $text['text'];
        $question->generalfeedbackformat = $text['format'];

        // Set question name if not already set.
        if ($question->name === false) {
            $question->name = $this->create_default_question_name($question->questiontext, get_string('questionname', 'question'));
        }

        // Determine question type.
        $question->qtype = null;

        // Give plugins first try.
        // Plugins must promise not to intercept standard qtypes
        // MDL-12346, this could be called from lesson mod which has its own base class =(.
        if (method_exists($this, 'try_importing_using_qtypes')
                && ($tryquestion = $this->try_importing_using_qtypes($lines, $question, $answertext))) {
            return $tryquestion;
        }

        if ($description) {
            $question->qtype = 'description';

        } else if ($answertext == '') {
            $question->qtype = 'essay';

        } else if ($answertext[0] == '#') {
            $question->qtype = 'numerical';

        } else if (strpos($answertext, '~') !== false) {
            // Only Multiplechoice questions contain tilde ~.
            // Here we "convert" potential Multiplechoice questions to the Random answers select question type.
            $question->qtype = 'answersselect';

        } else if (strpos($answertext, '=') !== false
                && strpos($answertext, '->') !== false) {
            // Only Matching contains both = and ->.
            $question->qtype = 'match';

        } else { // Either truefalse or shortanswer.

            // Truefalse question check.
            $truefalsecheck = $answertext;
            if (strpos($answertext, '#') > 0) {
                // Strip comments to check for TrueFalse question.
                $truefalsecheck = trim(substr($answertext, 0, strpos($answertext, "#")));
            }

            $validtfanswers = array('T', 'TRUE', 'F', 'FALSE');
            if (in_array($truefalsecheck, $validtfanswers)) {
                $question->qtype = 'truefalse';

            } else { // Must be shortanswer.
                $question->qtype = 'shortanswer';
            }
        }

        // Extract any idnumber and tags from the comments.
        list($question->idnumber, $question->tags) =
                $this->extract_idnumber_and_tags_from_comment($comments);

        if (!isset($question->qtype)) {
            $giftqtypenotset = get_string('giftqtypenotset', 'qformat_gift_answersselect');
            $this->error($giftqtypenotset, $text);
            return false;
        }

        // We only import questions of type multichoice converted to answersselect.
        if ($question->qtype == 'answersselect') {
            // Temporary solution to enable choice of answernumbering on GIFT import.
            // by respecting default set for multichoice questions (MDL-59447).
            $question->answernumbering = get_config('qtype_multichoice', 'answernumbering');
            $question->randomselectcorrect = 0;
            $question->randomselectincorrect = 0;
            $question->correctchoicesseparator = 0;

            if (strpos($answertext, "=") === false) {
                $question->single = 0; // Multiple answers are enabled if no single answer is 100% correct.
            } else {
                $question->single = 1; // Only one answer allowed (the default).
            }
            $question = $this->add_blank_combined_feedback($question);

            $answertext = str_replace("=", "~=", $answertext);
            $answers = explode("~", $answertext);
            if (isset($answers[0])) {
                $answers[0] = trim($answers[0]);
            }
            if (empty($answers[0])) {
                array_shift($answers);
            }

            $countanswers = count($answers);

            if (!$this->check_answer_count(2, $answers, $text)) {
                return false;
            }
            $giftanswerweightregex = '/^%*([0-9]{1,2})\.?([0-9]*)%/';
            foreach ($answers as $key => $answer) {
                $answer = trim($answer);
                // Determine answer weight.
                if ($answer[0] == '=') {
                    $answerweight = 1;
                    $answer = substr($answer, 1);
                } else if ($answer[0] != '%') { // Wrong answers initally marked with a ~ character.
                    $answerweight = 0;
                } else if (preg_match($giftanswerweightregex, $answer)) {    // Check for properly formatted answer weight.
                    $answerweight = 1;
                    $answer = substr($answer, 1);                        // Removes initial %.
                    $endposition  = strpos($answer, "%");
                    $answer = substr($answer, $endposition + 1);          // Removes comment from answer.
                } else {  // Default, i.e., wrong anwer.
                    $answerweight = 0;
                    $answer = substr($answer, 1);                        // Removes initial %.
                    $endposition  = strpos($answer, "%");
                    $answer = substr($answer, $endposition + 1);          // Removes comment from answer.
                }

                list($question->answer[$key], $question->feedback[$key]) =
                        $this->commentparser($answer, $question->questiontextformat);
                        $question->correctanswer[] = $answerweight;
                        $question->answer[$key]['format'] = FORMAT_HTML;
                        $question->feedback[$key]['format'] = FORMAT_HTML;
            }  // End foreach answer.

            return $question;
        }
    }
}
