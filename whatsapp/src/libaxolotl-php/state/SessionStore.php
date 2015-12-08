<?php
abstract class SessionStore {
    abstract function loadSession ($recipientId, $deviceId); // [long recipientId, int deviceId]
    abstract function getSubDeviceSessions ($recipientId); // [long recipientId]
    abstract function storeSession ($recipientId, $deviceId, $record); // [long recipientId, int deviceId, SessionRecord record]
    abstract function containsSession ($recipientId, $deviceId); // [long recipientId, int deviceId]
    abstract function deleteSession ($recipientId, $deviceId); // [long recipientId, int deviceId]
    abstract function deleteAllSessions ($recipientId); // [long recipientId]
}