<?php

abstract class SessionStore
{
    abstract public function loadSession($recipientId, $deviceId);

 // [long recipientId, int deviceId]

    abstract public function getSubDeviceSessions($recipientId);

 // [long recipientId]

    abstract public function storeSession($recipientId, $deviceId, $record);

 // [long recipientId, int deviceId, SessionRecord record]

    abstract public function containsSession($recipientId, $deviceId);

 // [long recipientId, int deviceId]

    abstract public function deleteSession($recipientId, $deviceId);

 // [long recipientId, int deviceId]

    abstract public function deleteAllSessions($recipientId);

 // [long recipientId]
}
