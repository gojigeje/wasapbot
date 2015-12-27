<?php
abstract class AxolotlLogger {
    const VERBOSE = 2;
    const DEBUG = 3;
    const INFO = 4;
    const WARN = 5;
    const ERROR = 6;
    const ASSERT = 7;
    //abstract static function writeLog ($priority, $tag, $message); // [int priority, String tag, String message]
}
