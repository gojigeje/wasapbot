<?php

class Helper
{
    public static function checkNotNull($reference, $message = null)
    {
        if ($message === null) {
            $message = 'Unallowed null in reference found.';
        }

        if ($reference === null) {
            throw new Exception($message);
        }

        return $reference;
    }
}
