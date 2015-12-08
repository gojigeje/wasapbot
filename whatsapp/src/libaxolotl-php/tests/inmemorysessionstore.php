<?php
//from axolotl.state.sessionstore import SessionStore
//from axolotl.state.sessionrecord import SessionRecord
require_once __DIR__ ."/../state/SessionStore.php";
require_once __DIR__ ."/../state/SessionRecord.php";
//if someone asks why the separator is __putaidea__ for the key,is because was the first thing i listened around when i was thinking how to implement the tuple in php
class InMemorySessionStore extends SessionStore{
    protected $sessions;
    public function InMemorySessionStore(){
        $this->sessions = [];
    }


    public function loadSession($recepientId, $deviceId){
        if ($this->containsSession($recepientId, $deviceId))
            return new SessionRecord(null, $this->sessions[$this->Key($recepientId,$deviceId)]);
        else{
            return new SessionRecord();
        }
    }

    public function getSubDeviceSessions($recepientId){
        $deviceIds = [];
        foreach(array_keys($this->sessions) as $key){
            $k = $this->SplitKey($key);
            if ($k[0] == $recepientId)
                $deviceIds[] = $k[1];
        }

        return $deviceIds;
    }
    private function Key($recepientId,$deviceId){
        return $recepientId."__putaidea__".$deviceId;
    }
    private function SplitKey($key){
        return explode("__putaidea__",$key);
    }
    public function storeSession($recepientId, $deviceId, $sessionRecord){
        $this->sessions[$this->Key($recepientId, $deviceId)] = $sessionRecord->serialize();
    }

    public function containsSession($recepientId, $deviceId){
        return isset($this->sessions[$this->Key($recepientId, $deviceId)]);
    }

    public function deleteSession($recepientId, $deviceId){
        unset($this->sessions[$this->Key($recepientId, $deviceId)]);
    }

    public function deleteAllSessions($recepientId){
        foreach (array_keys($this->sessions) as $key) {
            $k = $this->SplitKey($key);
            if ($k[0] == $recepientId)
                unset($this->sessions[$key]);
        }
    }
}

