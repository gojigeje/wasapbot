<?php

class SessionRecord
{
    const ARCHIVED_STATES_MAX_LENGTH = 40;
    protected $previousStates;
    protected $sessionState;
    protected $fresh;

    public function SessionRecord($sessionState = null, $serialized = null)
    {
        /*
        :type sessionState: SessionState
        :type serialized: str
        */
        $this->previousStates = [];
        if ($sessionState != null) {
            $this->sessionState = $sessionState;
            $this->fresh = false;
        } elseif ($serialized != null) {
            $record = new Textsecure_RecordStructure();
            $record->parseFromString($serialized);
            $this->sessionState = new SessionState($record->getCurrentSession());
            $this->fresh = false;
            foreach ($record->getPreviousSessions() as $previousStructure) {
                $this->previousStates[] = new SessionState($previousStructure);
            }
        } else {
            $this->fresh = true;
            $this->sessionState = new SessionState();
        }
    }

    public function hasSessionState($version, $aliceBaseKey)
    {
        if ($this->sessionState->getSessionVersion() == $version && $aliceBaseKey == $this->sessionState->getAliceBaseKey()) {
            return true;
        }

        foreach ($this->previousStates as $state) {
            if ($state->getSessionVersion() == $version && $aliceBaseKey == $state->getAliceBaseKey()) {
                return true;
            }
        }

        return false;
    }

    public function getSessionState()
    {
        return $this->sessionState;
    }

    public function getPreviousSessionStates()
    {
        return $this->previousStates;
    }

    public function removePreviousSessionStateAt($i)
    {
        if (isset($this->previousStates[$i])) {
            unset($this->previousStates[$i]);
            $this->previousStates = array_values($this->previousStates);
        }
    }

    public function isFresh()
    {
        return $this->fresh;
    }

    public function archiveCurrentState()
    {
        $this->promoteState(new SessionState());
    }

    public function promoteState($promotedState)
    {
        array_unshift($this->previousStates, $this->sessionState);
        $this->sessionState = $promotedState;
        if (count($this->previousStates) > self::ARCHIVED_STATES_MAX_LENGTH) {
            array_pop($this->previousStates);
        }
    }

    public function setState($sessionState)
    {
        $this->sessionState = $sessionState;
    }

    public function serialize()
    {
        $previousStructures = [];
        //previousState.getStructure() for previousState in self.previousStates
        $record = new Textsecure_RecordStructure();
        $record->setCurrentSession($this->sessionState->getStructure());
        foreach ($this->previousStates as $previousState) {
            $record->appendPreviousSessions($previousState->getStructure());
        }
        /*
            Python
            record.currentSession.MergeFrom(self.sessionState.getStructure())
            record.previousSessions.extend(previousStructures)

            return record.SerializeToString()
        */
        return $record->serializeToString();
    }
}
