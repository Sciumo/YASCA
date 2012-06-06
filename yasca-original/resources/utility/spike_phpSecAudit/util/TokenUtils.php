<?
    /*
    *  $Id: TokenUtils.php 28215 2005-07-28 02:53:05Z hkodungallur $
    *
    *  Copyright(c) 2004-2005, SpikeSource Inc. All Rights Reserved.
    *  Licensed under the Open Software License version 2.1
    *  (See http://www.spikesource.com/license.html)
    */
?>
<?php
    if (!defined("T_ML_COMMENT")) {
        define("T_ML_COMMENT", T_COMMENT);
    }

    define("NEXT_TOKEN", "1");
    define("NEXT_VALID_TOKEN", "2");
    define("PRVS_TOKEN", "3");
    define("PRVS_VALID_TOKEN", "4");
    

    /** 
     * Class that stores the tokens for a particular class and provide
     * utility functions like getting the next/previous token,
     * checking whether the token is of particular type etc.
     * 
     * @author Hari Kodungallur <hkodungallur@spikesource.com>
     */
    class TokenUtils
    {
        /*{{{ Variables */

        var $fileRef;
        var $tokens;
        var $totalNumTokens;
        var $curTokenNumber;
        var $curLineNumber;

        /*}}}*/

        /*{{{ PHP 4 constructor */
        
        /** 
         * php 4 constructor 
         * 
         * @return 
         */
        function TokenUtils() 
        {
            $this->__construct();
        }

        /*}}}*/

        /*{{{ PHP 5 constructor */
        
        /** 
         * php 5 constructor 
         * 
         * @return 
         */
        function __construct() 
        {
            $this->reset();
        }

        /*}}}*/

        /*{{{ function tokenize */

        /** 
         * Tokenizes the input php file and stores all the tokens in the 
         * $this->tokens variable. 
         * 
         * @param $filename 
         * @return 
         */
        function tokenize($filename) 
        {
            $contents = "";
            if (filesize($filename)) {
                $fp = fopen($filename, "r");
                $contents = fread($fp, filesize($filename));
                fclose($fp);
            }
            $this->tokens = token_get_all($contents);
            $this->totalNumTokens = count($this->tokens);

            return $this->totalNumTokens;
        }

        /*}}}*/

        /*{{{ function getNextToken */

        /** 
         * Gets the next token; in the process moves the index to the
         * next position, updates the current line number etc
         * 
         * @param &$line = 0 
         * @return the next token
         */
        function getNextToken(&$line)
        {
            if (!isset($line)) {
                $line = 0;
            }
            
            $ret = false;
            if ($this->curTokenNumber < $this->totalNumTokens) {
                $ret = $this->tokens[$this->curTokenNumber++];
                $line = $this->curLineNumber;
                $this->curLineNumber = $this->_updatedLineNumber($ret);
            }
            return $ret;
        }

        /*}}}*/

        /*{{{ function peekNextToken */

        /** 
         * Peeks the next token, i.e., returns the next token without moving 
         * the index. 
         * 
         * @param &$line
         * @return next token
         */
        function peekNextToken(&$line)
        {
            if (!isset($line)) {
                $line = 0;
            }
            
            $ret = false;
            if ($this->curTokenNumber <= $this->totalNumTokens) {
                $line = $this->curLineNumber;
                $ret = $this->tokens[$this->curTokenNumber];
            }
            return $ret;
        }

        /*}}}*/

        /*{{{ function peekPrvsToken */

        /** 
         * Peeks at the previous token. That is it returns the previous token
         * without moving the index
         * 
         * @param &$line = 0 
         * @return 
         */
        function peekPrvsToken(&$line = 0)
        {
            $ret = false;
            if ($this->nextTokenNumber > 1) {
                $line = $this->nextLineNumber
                      - $this->numberOfNewLines($this->tokens[$this->nextTokenNumber - 1])
                      - $this->numberOfNewLines($this->tokens[$this->nextTokenNumber - 2]);
                $ret = $this->tokens[$this->nextTokenNumber - 2];
            }
            return $ret;
        }

        /*}}}*/

        /*{{{ function peekNextValidToken */

        /** 
         * Peeks at the next valid token. A valid token is one that is not
         * a whitespace or a comment
         * 
         * @param &$line
         * @return 
         */
        function peekNextValidToken(&$line) 
        {
            if (!isset($line)) {
                $line = 0;
            }
            
            $ret = false;
            $tmpTokenNumber = $this->curTokenNumber;
            $line = $this->curLineNumber;
            while ($tmpTokenNumber <= $this->totalNumTokens) {
                $line += $this->numberOfNewLines($ret);
                $ret = $this->tokens[$tmpTokenNumber++];
                if (is_array($ret)) {
                    list ($k, $v) = $ret;
                    if ($k == T_WHITESPACE || $k == T_COMMENT
                        || $k == T_ML_COMMENT || $k == T_DOC_COMMENT) {
                        continue;
                    } else {
                        break;
                    }
                } else {
                    break;
                }
            }
            return $ret;
        }

        /*}}}*/

        /*{{{ function peekPrvsValidToken */

        /** 
         * Peeks at the previous valid token. A valid token is one that is not
         * a whitespace or a comment
         * 
         * @param &$line
         * @return 
         */
        function peekPrvsValidToken(&$line = 0)
        {
            $ret = false;
            $tmpTokenNumber = $this->nextTokenNumber - 2;
            $line = $this->nextLineNumber
                      - $this->numberOfNewLines($this->tokens[$this->nextTokenNumber - 1]);
            while ($tmpTokenNumber > 0) {
                $line -= $this->numberOfNewLines($this->tokens[$tmpTokenNumber]);
                $ret = $this->tokens[$tmpTokenNumber--];
                if (is_array($ret)) {
                    list ($k, $v) = $ret;
                    if ($k == T_WHITESPACE || $k == T_COMMENT
                        || $k == T_ML_COMMENT || $k == T_DOC_COMMENT) {
                        continue;
                    } else {
                        break;
                    }
                } else {
                    break;
                }
            }
            return $ret;
        }

        /*}}}*/

        /*{{{ function docCommentExistsForCurrentToken */

        /** 
         * Check for the existence of a docblock for the current token
         *  o  go back and find the previous token that is not a whitespace
         *  o  if it is a access specifier (private, public etc), then
         *     see if private members are excluded from comment check
         *     (input argument specified this). if we find an access
         *     specifier move on to find the next best token
         *  o  if the found token is a T_DOC_COMMENT, then we have a docblock
         * 
         * This, of course, assumes that the function or the class has to be
         * immediately preceded by docblock. Even regular comments are not 
         * allowed, which I think is okay.
         * 
         * @param $pr true/false specifying whether private members are
         *        excluded from test
         * @return boolean true: found docblock; false: did not find docblock
         */
        function docCommentExistsForCurrentToken($pr) 
        {
            // current token = the token after T_CLASS or T_FUNCTION
            //
            // token positions:
            //  .  curToken - 1 = T_CLASS/T_FUNCTION
            //  .  curToken - 2 = whitespace before T_CLASS/T_FUNCTION
            //  .  curToken - 3 = T_ABSTRACT/T_PUBLIC/T_PROTECTED/T_PRIVATE
            //                    or T_DOC_COMMENT, if it is present
            //
            // ISSUE: Assumes that there is no token between T_PUBLIC/etc..
            // and the T_CLASS/T_FUNCTION
            // eg., 
            //    public /* some comment */ MyClassName
            //    { ...
            //
            // will not work.

            $ret = false;

            $tmpTokenNumber = $this->curTokenNumber - 3;
            $curTok = $this->tokens[$this->curTokenNumber - 1];

            // if we find T_ABSTRACT, skip token and the whitespace
            // before it
            $ptok = $this->tokens[$tmpTokenNumber];
            if ($this->checkProvidedToken($ptok, T_ABSTRACT)) {
                $tmpTokenNumber -= 2;
            }

            if (is_array($curTok)) {
                list ($k, $v) = $curTok;
                if ($k == T_CLASS || $k == T_FUNCTION) {
                    // check for the existence of T_PUBLIC/T_PRIVATE/T_PROTECTED
                    // if found skip them too
                    if ($k == T_FUNCTION) {
                        $ptok = $this->tokens[$tmpTokenNumber];
                        if (is_array($ptok)) {
                            list ($k1, $v1) = $ptok;
                            if ($k1 == T_PUBLIC || $k1 == T_PROTECTED) {
                                $tmpTokenNumber--;
                            } elseif ($k1 == T_PRIVATE) {
                                if (!$pr) {
                                    return true;
                                }
                                $tmpTokenNumber--;
                            }
                        }
                    }

                    // check of the previous valid token is a T_DOC_COMMENT
                    // ignore whitespaces and comments in between
                    while ($tmpTokenNumber > 0) {
                        $ret = $this->tokens[$tmpTokenNumber--];

                        if (is_array($ret)) { 
                            list ($k2, $v2) = $ret; 
                            if ($k2 == T_DOC_COMMENT) {
                                break;
                            } elseif ($k2 == T_WHITESPACE || $k2 == T_COMMENT 
                                  || $k2 == T_ML_COMMENT) {
                                continue;
                            } else {
                                $ret = false;
                                break;
                            }
                        } else {
                            $ret = false;
                            break;
                        }
                    }
                }
            }
            return $ret;
        }

        /*}}}*/

        /*{{{ function reset */

        /** 
         * Resets all local variables 
         * 
         * @return 
         */
        function reset() 
        {
            $this->fileRef = false;
            $this->curTokenNumber = 0;
            $this->curLineNumber = 1;
            $this->totalNumTokens = 0;
            $this->tokens = array();
            $this->currentLine = "";
        }

        /*}}}*/

        /*{{{ function _updatedLineNumber */

        // based on the example at http://us4.php.net/token_get_all
        function _updatedLineNumber($t)
        {
            $ret = $this->curLineNumber;
            $numNewLines = $this->numberOfNewLines($t);
            if (1 <= $numNewLines) {
               $ret += $numNewLines;
            }

            return $ret;
        }

        /*}}}*/

       /*{{{ function numberOfNewLines */

        function numberOfNewLines($t) 
        {
            if (is_array($t)) {
                list ($k, $v) = $t;
            } else {
                $v = $t;
            }
            $numNewLines = substr_count($v, "\n");
            return $numNewLines;
        }

        /*}}}*/
        
        /*{{{ function checkProvidedToken */
        function checkProvidedToken($token, $value, $text = false) 
        {
            $ret = false;
            if (is_array($token)) {
                list ($k, $v) = $token;
                if ($k == $value) {
                    if ($text) {
                        if ($v == $text) {
                            $ret = true;
                        }
                    } else {
                        $ret = true;
                    }
                }
            }
            return $ret;
        }

        /*}}}*/

        /*{{{ function checkToken */

        function checkToken($which, $value, $text = false) 
        {
            $ret = false;
            $token = $this->peekToken($which);
            if (is_array($token)) {
                list ($k, $v) = $token;
                if ($k == $value) {
                    if ($text) {
                        if ($v == $text) {
                            $ret = true;
                        }
                    } else {
                        $ret = true;
                    }
                }
            }
            return $ret;
        }

        /*}}}*/

        /*{{{ function checkText */

        function checkTokenText($which, $text) 
        {
            $ret = false;
            $token = $this->peekToken($which);
            if (is_string($token)) {
                if ($token == $text) {
                    $ret = true;
                }
            } 
            return $ret;
        }

        /*}}}*/

        /*{{{ function checkProvidedText */

        function checkProvidedText($token, $text) 
        {
            $ret = false;
            if (is_string($token)) {
                if ($token == $text) {
                    $ret = true;
                }
            } 
            return $ret;
        }

        /*}}}*/

        /*{{{ function _tokenText */

        function extractTokenText($token) 
        {
            $ret = $token;
            if (is_array($token)) {
                list ($k, $ret) = $token;
            }
            return $ret;
        }

        /*}}}*/

        /*{{{ function peekToken */

        function peekToken($which, &$line)
        {
            if (!isset($line)) {
                $line = 0;
            }
            
            $ret = false;
            switch ($which) {
                case NEXT_TOKEN:
                    $ret = $this->peekNextToken($line);
                    break;

                case NEXT_VALID_TOKEN:
                    $ret = $this->peekNextValidToken($line);
                    break;

                case PRVS_TOKEN:
                    $ret = $this->peekPrvsToken($line);
                    break;

                case PRVS_VALID_TOKEN:
                    $ret = $this->peekPrvsValidToken($line);
                    break;

                default:
                    echo "default...\n";
                    break;
            }
            return $ret;
        }

        /*}}}*/

    }
?>
