<?php

class InvalidMessageException extends Exception
{
    public function InvalidMessageException($detailMessage, $throw = null) // [String detailMessage]
    {
        $this->message = $detailMessage;
        if ($throw != null) {
            $this->previous = $throw;
        }
    }
}
