<?php

require_once __DIR__.'/AxolotlLogger.php';
require_once __DIR__.'/AxolotlLoggerProvider.php';
class Log extends AxolotlLogger
{
    public static function verbose($tag, $msg) // [String tag, String msg]
    {
        self::writeLog(self::VERBOSE, $tag, $msg);
    }

    public static function verboseException($tag, $msg, $tr) // [String tag, String msg, Throwable tr]
    {
        self::writeLog(self::VERBOSE, $tag, (($msg.'\n').self::getStackTraceString($tr)));
    }

    public static function debug($tag, $msg) // [String tag, String msg]
    {
        self::writeLog(self::DEBUG, $tag, $msg);
    }

    public static function debugException($tag, $msg, $tr) // [String tag, String msg, Throwable tr]
    {
        self::writeLog(self::DEBUG, $tag, (($msg.'\n').self::getStackTraceString($tr)));
    }

    public static function info($tag, $msg) // [String tag, String msg]
    {
        self::writeLog(self::INFO, $tag, $msg);
    }

    public static function infoException($tag, $msg, $tr) // [String tag, String msg, Throwable tr]
    {
        self::writeLog(self::INFO, $tag, (($msg.'\n').self::getStackTraceString($tr)));
    }

    public static function warn($tag, $msg) // [String tag, String msg]
    {
        self::writeLog(self::WARN, $tag, $msg);
    }

    public static function warnException($tag, $msg, $tr) // [String tag, String msg, Throwable tr]
    {
        self::writeLog(self::WARN, $tag, (($msg.'\n').self::getStackTraceString($tr)));
    }

    public static function warnShortException($tag, $tr) // [String tag, Throwable tr]
    {
        self::writeLog(self::WARN, $tag, self::getStackTraceString($tr));
    }

    public static function error($tag, $msg) // [String tag, String msg]
    {
        self::writeLog(self::ERROR, $tag, $msg);
    }

    public static function errorException($tag, $msg, $tr) // [String tag, String msg, Throwable tr]
    {
        self::writeLog(self::ERROR, $tag, (($msg.'\n').self::getStackTraceString($tr)));
    }

    protected static function getStackTraceString($tr) // [Throwable tr]
    {
        if ($tr instanceof Exception) {
            return $tr->getTrace();
        } else {
            return '';
        }
    }

    //old function name log

    public static function writeLog($priority, $tag, $msg) // [int priority, String tag, String msg]
    {
        $logger = AxolotlLoggerProvider::getProvider();
        if (($logger != null)) {
            $logger->log($priority, $tag, $msg);
        }
    }
}
