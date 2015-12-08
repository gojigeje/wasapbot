<?php
class InvalidKeyException extends Exception {

    public function InvalidKeyException ($detailMessage) // [String detailMessage]
    {
        $this->message = $detailMessage;

    }
}
