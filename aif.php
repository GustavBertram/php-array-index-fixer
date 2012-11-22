<?php

/**
 * PHP Array Index Fixer
 *
 * This file parses a PHP source file and replaces unquoted 
 * array indexes.
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
 *
 * find . -type f -iname '*.php' -exec php aif.php {} \;
 *
 * @author     Author <gustav.bertram@gmail.com>
 * @copyright  2012 Gustav Bertram
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