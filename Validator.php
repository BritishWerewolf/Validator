<?php
/**
 * 
 */
Class Validator
{
    private $canNull = false;
    private $canEmpty = false;
    private $minLen = -1;
    private $maxLen = -1;
    private $regex = "//";
    private $type = ""; // something like email; where it's a specific string format
    private $dataType = "";
    private $dateFormat = "Y-m-d";
    private $isDev = false;

    public $success = false;
    public $errno = 0;
    public $error = "";
    public $errorList = [];

    /**
     * Called when creating a new class.
     * @param bool $isDev If true, developer error messages will be displayed, 
     *                    otherwise display user friendly messages.
     */
    public function __construct(bool $isDev = false)
    {
        $this->isDev = $isDev;
    }

    /**
     * Whether the variable is allowed to be null or not.
     * @param  bool   $isNull If true, a null value will validate to true.
     */
    public function canBeNull(bool $isNull = true)
    {
        $this->canNull = $isNull;

        return $this;
    }

    /**
     * Whether the variable is allowed to be empty or not.
     * Empty can be defined as the following:
     * - "" (an empty string)
     * - 0 (0 as an integer)
     * - 0.0 (0 as a float)
     * - "0" (0 as a string)
     * - null
     * - false
     * - array() (an empty array)
     * - $var; (a variable declared, but without a value)
     * @param  bool $isEmpty If true, a variable can qualify as any of those.
     */
    public function canBeEmpty(bool $isEmpty = true)
    {
        $this->canEmpty = $isEmpty;

        return $this;
    }

    /**
     * The minimum length that the variable can be. If data type is set to a numeric
     * one then len will be taken as the maximum value that the number can be.
     * @param float $len The inclusive numeric value for the length / value.
     */
    public function setMinLen(float $len)
    {
        $this->minLen = $len;

        return $this;
    }

    /**
     * The maxium length that the variable can be. If data type is set to a numeric
     * one then len will be taken as the maximum value that the number can be.
     * @param float $len The inclusive numeric value for the length / value.
     */
    public function setMaxLen(float $len)
    {
        $this->maxLen = $len;

        return $this;
    }

    /**
     * Set the regular expression that will be used when sanitising.
     * @param string $regex This can come in the following formats:
     *                      - Entire RegEx with flags: /body/im
     *                      - Entire RegEx without flags: /body/
     *                      - Body of RegEx: body
     *                      - Negating first or last slash: body/   or    /body
     *                      - As above with flags: body/im
     */
    public function setRegEx(string $regex)
    {
        // RegEx must have a "/" at the start
        if (substr($regex, 0, 1) !== "/") {
            $regex = "/" . $regex;
        }
        // make sure that there isn't just one passed
        if (strlen($regex) == 1 && $regex == "/") {
            $regex .= "/";
        }

        // RegEx must have a slash at the end or before flags
        if (preg_match("/(\/[imsxe]*)$/", $regex, $matches)) {
            if (substr($regex, strrpos($regex, $matches[1]) - 1, 1) == "\\") {
                $regex = substr($regex, 0, strrpos($regex, $matches[1]))
                         . "\\"
                         . substr($regex, strrpos($regex, $matches[1]));
            }
        } elseif (substr($regex, -1) !== "/") {
            $regex .= "/";
        }

        // make sure no duplicate flags
        if (preg_match("/^\/(.+)\/([imsxe]*)$/", $regex, $matches)) {
            $matches[1] = str_replace("\\\\/", "\/", str_replace("/", "\/", $matches[1]));
            $matches[2] = implode("", array_unique(str_split($matches[2])));
            $regex = "/{$matches[1]}/{$matches[2]}";
        }
        // remove bogus flags
        if (preg_match("/^\/(.+)\/([^\\\]*)$/", $regex, $matches)) {
            $matches[2] = preg_replace("/[^imsxe]*/i", "", $matches[2]);
            $regex = "/{$matches[1]}/{$matches[2]}";
        }

        $this->regex = $regex;

        return $this;
    }

    /**
     * Set the type that variable must adhere to.
     * For instance the email type will be of data type string, but will have
     * rules like the format it is displayed in.
     * @param string $type A spcecific ruleset the data type must adhere to.
     */
    public function setType(string $type)
    {
        $this->type = strtolower($type);

        if ($type == "email") {
            $this->setRegEx("/^.+@[^.]+(?:\..+)?$/");
            $this->setMinLen(3); // something like a@a
            $this->setMaxLen(254); // or is it 320??
        }

        return $this;
    }

    /**
     * The type that the data must be.
     * @param string $type Any valid data type of: string (str), 
     *                     integer (int), double (float), boolean 
     *                     (boolean), array, object, and numberic.
     */
    public function setDataType(string $type)
    {
        $this->dataType = strtolower($type);

        if ($type == "bool") {
            $this->dataType = "boolean";
        } elseif ($type == "int") {
            $this->dataType = "integer";
        } elseif ($type == "str") {
            $this->dataType = "string";
        } elseif ($type == "null") {
            $this->dataType = "NULL";
        } elseif ($type == "number") {
            $this->dataType = "numeric";
        } elseif ($type == "float") {
            $this->dataType = "double";
        }

        return $this;
    }

    /**
     * Sets the format that the date should be when validating.
     * @param string $format A valid format according to the PHP documentation
     *                       for the http://php.net/manual/en/class.datetime.php
     *                       class.
     */
    public function setDateFormat(string $format)
    {
        $this->dateFormat = $format;
        $this->dataType = "date";

        return $this;
    }

    public function clearNotErrors()
    {
        // store since they're removed in clear()
        $errno = $this->errno;
        $error = $this->error;
        $errLst = $this->errorList;

        $this->clear();

        // reset back to what they were
        $this->errno = $errno;
        $this->error = $error;
        $this->errorList = $errLst;
    }

    /**
     * Removes all errors.
     */
    public function clearErrors()
    {
        $this->errno = 0;
        $this->error = "";
        $this->errorList = [];

        return $this;
    }

    /**
     * Removes the minimum length restriction.
     */
    public function clearMinLen()
    {
        $this->minLen = -1;

        return $this;
    }

    /**
     * Removes the maximum length restriction.
     */
    public function clearMaxLen()
    {
        $this->maxLen = -1;

        return $this;
    }

    /**
     * Removes the regular expression restriction.
     */
    public function clearRegEx()
    {
        $this->regex = "//";

        return $this;
    }

    /**
     * Removes the type restriction.
     */
    public function clearType()
    {
        if ($this->type == "email") {
            $this->clearRegEx();
            $this->clearMinLen();
            $this->clearMaxLen();
        }

        $this->type = "";

        return $this;
    }

    /**
     * Removes the data type restriction.
     */
    public function clearDataType()
    {
        $this->dataType = "";

        return $this;
    }

    /**
     * Removes the date format validation.
     */
    public function clearDateFormat()
    {
        $this->dateFormat = "d-M-Y";

        return $this;
    }

    /**
     * Removes all restictions.
     */
    public function clear()
    {
        $this->clearErrors();
        $this->clearMinLen();
        $this->clearMaxLen();
        $this->clearRegEx();
        $this->clearType();
        $this->clearDataType();
        $this->clearDateFormat();

        return $this;
    }

    /**
     * Validate the specified $value in accordance with the defined parameters.
     * @param  mixed  $value     The value which is being validated.
     * @param  string $displayAs Rather than show the value in the error message, 
     *                           show this string instead.
     * @return bool              True if $value validates successfully, 
     *                           false otherwise.
     */
    public function validate($value, string $displayAs = null)
    {
        if (is_object($value) || is_array($value) || is_resource($value)) {
            $v = "Variable";
        } else {
            $v = isset($displayAs) ? $displayAs : "'$value'";
        }


        if ($this->canNull && is_null($value)) {
            return true;
        }
        if ($this->canEmpty && empty($value)) {
            return true;
        }

        // whether to take the literal value of max length if an integer is passed
        // or whether to take the horizontal length of the string.
        // If this var is true then setMaxLen(12) will validate 11 as true since 
        // 11 is less than 12. However, setMaxLen(3) will validate "asdf" as false
        // since the length of the string is 4.
        $intLen = $this->dataType == "numeric" || $this->dataType == "integer"
                  || $this->dataType == "double"
                  ? true : false;

        if ($this->maxLen !== -1
            && ((!$intLen && strlen($value) > $this->maxLen)
            || ($intLen && $value > $this->maxLen))
        ) {
            $this->addError(1, "$v exceeds maximum value of {$this->maxLen}.");
        }


        if (($this->minLen === 1 && strlen($value) < $this->minLen) || empty($value)) {
            $this->addError(2, "$v cannot be empty.");
        } elseif ($this->minLen !== -1
            && ((!$intLen && strlen($value) < $this->minLen)
            || ($intLen && $value < $this->minLen))
        ) {
            $this->addError(3, "$v exceeds minimum value of {$this->minLen}.");
        }

        if ($this->regex !== "//" && !preg_match($this->regex, $value)) {
            $this->addError(4, "$v is not in the correct format.");
        }


        // perform special validation for types that are not found in the gettype() function
        if (!$this->canNull && $value === null) {
            $this->addError(8, "$v cannot be null.");
        } else {
            if ($this->dataType == "numeric" || $this->dataType == "date" || $this->dataType == "time" || $this->dataType == "datetime") {
                if ($this->dataType == "numeric" && !is_numeric($value)) {
                    $this->addError(5, "$v is not a number.");
                }
                
                DateTime::createFromFormat($this->dateFormat, $value);
                if ($this->dataType == "date" && (DateTime::getLastErrors()["warning_count"] || DateTime::getLastErrors()["error_count"])) {
                    $this->addError(7, "$v is not a valid date.");
                }
            } elseif ($this->dataType != "" && gettype($value) !== $this->dataType) {
                $t = gettype($value);
                $this->addError(6, "$v is of data type '$t', but should be of type '{$this->dataType}'.");
            }
        }

        return $this->success = (bool)!$this->errno;
    }

    /**
     * Adds an error to the error list, as well as storing in sepate variable for the newest error.
     * @param int    $errNo The number of the error.
     * @param string $err   A description of the error.
     */
    public function addError(int $errNo, string $err)
    {
        /* Errors:
         * 1 - $v length exceeds maximum value of {$this->maxLen}.
         * 2 - $v cannot be empty.
         * 3 - $v length exceeds maximum value of {$this->minLen}.
         * 4 - $v is not in the correct format.
         * 5 - $v is not a number.
         * 6 - $v is of data type {gettype($value)}, but should be of type {$this->dataType}.
         * 7 - $v is not a valid date.
         * 8 - $v cannot be null.
         */
        $this->errno = $errNo;
        $this->error = "$err";

        array_push($this->errorList, ["errno" => $errNo, "error" => $err]);
    }
}

Class VD
{
    private static $c;
    private static $initialised = false;

    private static function init()
    {
        // remove "false &&" if you want to avoid creating a new instance each time
        // I want it there because I do not need to store information once finished
        if (false && self::$initialised) {
            return;
        }

        self::$c = new Validator();
        self::$initialised = true;
    }
    public static function setMinLen(float $len) { self::init(); return self::$c->setMinLen($len); }
    public static function setMaxLen(float $len) { self::init(); return self::$c->setMaxLen($len); }
    public static function setRegEx(string $regex) { self::init(); return self::$c->setRegEx($regex); }
    public static function setType(string $type) { self::init(); return self::$c->setType($type); }
    public static function setDataType(string $type) { self::init(); return self::$c->setDataType($type); }
    public static function setDateFormat(string $format) { self::init(); return self::$c->setDateFormat($format); }
    public static function clearErrors() { self::init(); return self::$c->clearErrors(); }
    public static function clearMinLen() { self::init(); return self::$c->clearMinLen(); }
    public static function clearMaxLen() { self::init(); return self::$c->clearMaxLen(); }
    public static function clearRegEx() { self::init(); return self::$c->clearRegEx(); }
    public static function clearDataType() { self::init(); return self::$c->clearDataType(); }
    public static function clearDateFormat() { self::init(); return self::$c->clearDateFormat(); }
    public static function clear() { self::init(); return self::$c->clear(); }
    public static function validate($value, string $displayAs = null) { self::init(); return self::$c->validate($value, $displayAs); }
    public static function getLastError() { return self::$c->error; }
    public static function getLastErrorNum() { return self::$c->errno; }
    public static function getErrorList() { return self::$c->errorList; }
}
