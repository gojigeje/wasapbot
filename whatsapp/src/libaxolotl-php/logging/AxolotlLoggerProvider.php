<?php

class AxolotlLoggerProvider
{
    protected static $provider;    // AxolotlLogger

    public static function getProvider()
    {
        return self::$provider;
    }

    public static function setProvider($provider) // [AxolotlLogger provider]
    {
        self::$provider = $provider;
    }
}
