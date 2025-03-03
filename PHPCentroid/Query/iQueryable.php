<?php

namespace PHPCentroid\Query;

use Closure;

interface iQueryable
{
    /**
     * @param string|SelectableExpression ...$args
     * @return $this
     */
    public function select(mixed ...$args): self;

    /**
     * @param string|SelectableExpression $arg,...
     * @return $this
     */
    public function alsoSelect(mixed $arg): self;

    public function hasFields();

    public function hasFilter();

    /**
     * @param SelectableExpression|string $expr,...
     * @return $this
     */
    public function groupBy(mixed ...$expr): self;

    /**
     * @param SelectableExpression|string $expr,...
     * @return $this
     */
    public function orderBy(mixed ...$expr): self;

    /**
     * @param SelectableExpression|string $expr,...
     * @return $this
     */
    public function thenBy(mixed $expr): self;

    /**
     * @param SelectableExpression|string $expr,...
     * @return $this
     */
    public function orderByDescending(mixed $expr): self;

    /**
     * @param SelectableExpression|string $expr,...
     * @return $this
     */
    public function thenByDescending(mixed $expr): self;

    /**
     * @param string|SelectableExpression|Closure $expr
     * @return $this
     */
    public function where(mixed $expr, mixed ...$params): self;

    /**
     * @param mixed $arg
     * @return $this
     */
    public function also(mixed $arg): self;

    /**
     * @param mixed $arg
     * @return $this
     */
    public function either(mixed $arg): self;

    public function prepare();

    /**
     * @param mixed $value
     * @return $this
     */
    public function equal(mixed $value): self;

    /**
     * @param mixed $value
     * @return $this
     */
    public function notEqual(mixed $value): self;

    /**
     * @param mixed $value
     * @return $this
     */
    public function lowerThan(mixed $value): self;

    /**
     * @param mixed $value
     * @return $this
     */
    public function lowerOrEqual(mixed $value): self;

    /**
     * @param mixed $value
     * @return $this
     */
    public function greaterThan(mixed $value): self;

    /**
     * @param mixed $value
     * @return $this
     */
    public function greaterOrEqual(mixed $value): self;

    public function getDay();

    public function getMonth();

    public function getYear();

    public function getSeconds();

    public function getMinutes();

    public function getHours();

    public function getDate();

    public function toLowerCase();

    public function toUpperCase();

    public function floor();

    public function ceil();

    public function trim();

    public function length();

    public function round(int $n) : self;

    /**
     * @param mixed $x
     * @return $this
     */
    public function add(mixed $x): self;

    /**
     * @param mixed $x
     * @return $this
     */
    public function subtract(mixed $x): self;

    /**
     * @param mixed $x
     * @return $this
     */
    public function multiply(mixed $x): self;

    /**
     * @param mixed $x
     * @return $this
     */
    public function divide(mixed$x): self;

    /**
     * @param mixed $x
     * @return $this
     */
    public function mod(mixed $x): self;

    /**
     * @param mixed $x
     * @return $this
     */
    public function bit(mixed $x): self;

}