<?php

abstract class CiphertextMessage
{
    const UNSUPPORTED_VERSION = 1;
    const CURRENT_VERSION = 3;
    const WHISPER_TYPE = 2;
    const PREKEY_TYPE = 3;
    const SENDERKEY_TYPE = 4;
    const SENDERKEY_DISTRIBUTION_TYPE = 5;
    const ENCRYPTED_MESSAGE_OVERHEAD = 53;

    abstract public function serialize();

    abstract public function getType();
}
