<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    /**
     * @var mysqli
     */
    private mysqli $mysqli;

    /**
     * @param mysqli $mysqli
     */
    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * @param string $query
     * @param array $args
     * @return string
     * @throws Exception
     * Builds query from template and passed parameters.
     */
    public function buildQuery(string $query, array $args = []): string
    {
        //first we break query by words and look for substitution marks (?)
        $sections = explode(' ', $query);
        $specs = preg_grep('/[?]/', $sections);

        //then try to look for specificators...
        $i = 0;
        foreach($specs as $k => $v) {
            $subPos = strpos($v, '?');
            $spec = $v[$subPos + 1];

            //then use suitable method accordingly - to modify provided query string
            if ($spec) {
                $sections[$k] = preg_replace('/[?][^.]/', $this->specificSubstitute($args[$i], $spec, gettype($args[$i])), $sections[$k]);
            } else {
                $sections[$k] = preg_replace('/[?]/', $this->nonSpecificSubstitute($args[$i]), $sections[$k]);
            }
            $i+=1;
        }


        //bringing pieces together, validating conditional blocks and cleaning up
        $result = implode(' ', $sections);

        return $this->validateConditionalBlock($result);
    }


    /**
     * @param array $array
     * @return bool
     */
    public function isAssotiativeArray(array $array):bool
    {
        return count(array_filter(array_keys($array), 'is_string')) > 0;
    }

    /**
     * @param $arg
     * @param $spec
     * @return float|int|string|void
     * Handling substitutions with passed specificators.
     */
    public function specificSubstitute($arg, $spec)
    {
        switch($spec) {
            case 'd': return intval($arg);
            case 'f': return floatval($arg);
            case 'a':
                if ($this->isAssotiativeArray($arg)) {
                    $res = [];
                    foreach ($arg as $k => $v)
                    {
                        $k = "`".$k."`";
                        $v = (is_null($v)) ? 'NULL' : "'".$v."'";
                        $res[] = $k .' = '. quotemeta($v);
//                        $res[] = $k .' = '. addslashes($v);
                    }
                    return implode(', ',$res);
                } else {
                    return implode(', ', $arg);
                }
            case '#': return (gettype($arg) === 'string') ? "`".$arg."`" : implode(', ', array_map(function($key) {return "`$key`";}, $arg));

        }

    }

    /**
     * @param $input
     * @return string
     * @throws Exception
     * Handling substitutions w/o specificators.
     */
    public function nonSpecificSubstitute($input)
    {
        $allowedTypes = [
            'string',
            'int',
            'float',
            'bool',
            'NULL'
        ];

        $input = "'".$input."'";

        if (in_array(gettype($input), $allowedTypes)) {
            return quotemeta($input);
//            return addslashes($input);
            } else {
            throw new Exception('Input type ' . gettype($input) . ' not allowed');
        }

    }


    /**
     * @param $input
     * @return array|string|string[]|null
     * Decides validation of conditional block. Returns resulting query.
     */
    public function validateConditionalBlock($input)
    {
        $condBlock = preg_match('/[{].*[}]/', $input, $match);
        if (preg_match('/[{].*[{]/', $input) || (preg_match('/[}].*[}]/', $input)))
            {
                throw new Exception('Nested conditional blocks not allowed.');
            }

        if ($condBlock) {
            if (preg_match('/[9]{3}/', $match[0])) {
                $result = preg_replace('{'.$match[0].'}', '', $input);
            } else {
                $result = $input;
            }
        }

        if (preg_match('/[{]/', $input)) {
            $result = preg_replace('/{/','', $result);
        } else {
            $result = $input;

        }

        if (preg_match('/[}]/', $input)) {
            $result = preg_replace('/}/','', $result);
        } else {
            $result = $input;
        }

        return $result;
    }

    /**
     * @return int
     * Special exit code for conditional blocks.
     */
    public function skip()
    {
        return 999;
    }
}





