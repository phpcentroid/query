<?php

namespace PHPCentroid\Query;

use Exception;

class ObjectNameValidator {

    protected string $pattern;
    protected string $qualifiedPattern;

    public function __construct(ValidatorPatternsEnum $pattern = ValidatorPatternsEnum::Default) {
        $this->pattern = $pattern->value;
        $this->qualifiedPattern = "\\*$|^{$this->pattern}((\\.|\\/){$this->pattern})*(\\.\\*)?$";
    }

    /**
     * Tests if the given name is valid.
     * @param string $name
     * @param bool $qualified
     * @param bool $throw_error
     * @throws \Exception
     * @return bool
     */
    public function test(string $name, bool $qualified=TRUE, bool $throw_error=TRUE) {
        $pattern = $qualified ? $this->qualifiedPattern : $this->pattern;
        $result = preg_match("/^{$pattern}$/", $name);
        if ($result === FALSE) {
            if ($throw_error) {
                throw new Exception("Invalid object name pattern.");
            }
            return FALSE;
        }
        return $result === 1;
    }

    /**
     * 
     * Escapes a database object name based on the given format
     * @param string $name
     * @param string $format_string
     * @throws \Exception
     * @return string
     */
    public function escape(string $name, string $format_string='$1'): string {
        $this->test($name, TRUE, TRUE);
        return (string)preg_replace("/{$this->pattern}/", $format_string, $name);
    }

}