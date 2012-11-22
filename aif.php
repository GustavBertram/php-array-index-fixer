<?php

/**
 * php-array-index-fixer
 * =====================
 *
 * This script parses a PHP source file and replaces unquoted 
 * array indexes with single-quoted array indexes.
 *
 * It will fix the following:
 *  $a[b] = 'c';
 *
 * While not breaking the following:
 *  $b = "$a[b]";
 *  $c = " \"$a[b]\" ";
 *  $d = $e[$f->g];
 *
 * It was meant to be invoked as follows:
 *  php aif.php <source_file>
 *
 * On a Linux machine, the following commandline script
 * will invoke aif.php on all PHP source files in a
 * directory, including subfolders:
 *
 * find . -type f -iname '*.php' -exec php aif.php {} \;
 *
 * License
 * =======
 * 
 * Copyright 2012 Gustav Bertram
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author     Gustav Bertram <gustav.bertram@gmail.com>
 * @copyright  2012 Gustav Bertram
 * @license    http://www.gnu.org/licenses/gpl.html GNU Public License Version 3
 * @version    1.0
 */

// Debug constants
define("DEBUG", FALSE);

// New token type
define("T_CHAR", 0);

/**
 * This class represents tokens.
 **/

class Token {
    public $type;
    public $text;

    function __construct($token) {
        if(!is_array($token)) {
            $this->type = T_CHAR;
            $this->text = $token;
        } else {
            list($this->type, $this->text) = $token;
        }
    }

    public function getName() {
        if ($this->type == T_CHAR) {
            return 'T_CHAR';
        } else {
            return token_name($this->type);
        }
    }
}

/**
 * This class represents the tokenizing and parsing state machine.
 *
 * Usage:
 * $sm = new StateMachine();
 * $sm->tokenize($text);
 * $text = $sm->parse(); 
 *
 *
 */

class StateMachine {
    private $state;
    private $states;
    private $last_token;
    public  $tokens;
    
    function __construct() {
        $this->state = 'DEFAULT';
        $this->states = array('DEFAULT', 'SINGLE_QUOTED', 'DOUBLE_QUOTED', 'ARRAY_INDEX');
    }

    protected function setState($state) {
        if (in_array($state, $this->states)) {
            $this->state = $state;
        } else {
            throw Exception('Invalid state' . $state);
        }
    }

    public function tokenize($text) {
        $php_tokens = token_get_all($text);
        $this->tokens = array();

        foreach ($php_tokens AS $php_token) {
            $this->tokens[] = new Token($php_token);
        }
    }

    public function parse() {
        $parsed_text = '';

        foreach ($this->tokens as $token) {
            $this->parseToken($token);
            $parsed_text .= $token->text;
        }
        
        return $parsed_text;
    }

    protected function parseToken(Token $token) {
        $state_function = 'parseToken' . $this->state;
        $this->$state_function($token);
        $this->last_token = $token;
    }

    protected function parseTokenDEFAULT(Token $token) {
        switch ($token->type) {
        case T_CHAR:
            if ($token->text == '\'') {
                // Entering a single quoted string
                $this->setState('SINGLE_QUOTED');
            }

            if ($token->text == '"') {
                // Entering a double_quoted_string
                $this->setState('DOUBLE_QUOTED');
            }

            if ($token->text == '[') {
                // Entering an array index
                $this->setState('ARRAY_INDEX');
            }
            break;
            
        default:
            break;
        }
    }

    protected function parseTokenSINGLE_QUOTED(Token $token) {
        switch ($token->type) {
        case T_CHAR:
            if ($token->text == '\'') {
                // Exiting a single quoted string
                $this->setState('DEFAULT');
            }
            break;
            
        default:
            break;
        }
    }
 
    protected function parseTokenDOUBLE_QUOTED(Token $token) {
        switch ($token->type) {
        case T_CHAR:
            if ($token->text == '"') {
                // Exiting a double quoted string
                $this->setState('DEFAULT');
            }
            break;
            
        default:
            break;
        }
    }

    protected function parseTokenARRAY_INDEX(Token $token) {
        switch ($token->type) {
        case T_STRING:
            if ($this->last_token->text == '[') {
                $token->text = "'{$token->text}'";
		$token->type = T_CONSTANT_ENCAPSED_STRING;

                if (DEBUG) {
		    echo "REPLACED: " . $token->text;
		}
            }
            else
	    {
	        if (DEBUG) { 
		    echo "NOT REPLACED:" . $token->text;
		}
            }
            break;

        case T_CHAR:
            if ($token->text == ']') {
                // Exiting array index
                $this->setState('DEFAULT');
            }
            break;
            
        default:
            break;
        }
    }
 

}

// Read file
$filename = $argv[1];
$file = file_get_contents($filename);

// Create state machine
$sm = new StateMachine();

// Tokenize
$sm->tokenize($file);

// Parse
$new_file = $sm->parse();

// Write file
if (DEBUG) {
    echo $new_file . "\n";
} else {
    file_put_contents($filename, $new_file);
}