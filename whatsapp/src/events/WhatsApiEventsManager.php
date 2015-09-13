<?php
class WhatsApiEventsManager
{
    private $listeners = array();

    public function bind($event, $callback)
    {
        $this->listeners[$event][] = $callback;
    }

    public function fire($event, array $parameters)
    {
        if (!empty($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $listener) {
                call_user_func_array($listener, $parameters);
            }
        }
    }
}
