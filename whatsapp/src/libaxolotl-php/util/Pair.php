<?php
class Pair {
    protected $v1;    // T1
    protected $v2;    // T2
    private function __init() { // default class members
    }
    public static function __staticinit() { // static class members
    }
    public static function constructor__4b76b80 ($v1, $v2) // [T1 v1, T2 v2]
    {
        $me = new self();
        $me->__init();
        $me->v1 = $v1;
        $me->v2 = $v2;
        return $me;
    }
    public function first ()
    {
        return $this->v1;
    }
    public function second ()
    {
        return $this->v2;
    }
    public function equals ($o) // [Object o]
    {
        return (($o instanceof Pair && $this->equal($o::first(), $this->first())) && $this->equal($o::second(), $this->second()));
    }
    public function hashCode ()
    {
        return ($this->first()->hashCode() ^ $this->second()->hashCode());
    }
    protected function equal ($first, $second) // [Object first, Object second]
    {
        if ((($first == null) && ($second == null)))
            return  TRUE ;
        if ((($first == null) || ($second == null)))
            return  FALSE ;
        return $first->equals($second);
    }
}
Pair::__staticinit(); // initialize static vars for this class on load
