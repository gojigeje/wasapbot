<?php

class Logger{

  protected $logfile;

  public function __construct($logfile)
  {
      if (!file_exists($logfile)) {
          if (!touch($logfile)) throw new Exception('Log file ' . $logfile . ' cannot be created');
      }
      if (!is_writable($logfile)) throw new Exception('Log file ' . $logfile . ' is not writeable');
        $this->logfile = $logfile;
  }

  public function log($level, $message, $context = array())
  {
       $logline = '[' . date('Y-m-d H:i:s') . '] ' . '[' . strtoupper($level) . ']: ' . $this->interpolate($message, $context) . "\n";
       file_put_contents($this->logfile, $logline, FILE_APPEND | LOCK_EX);
  }

   /**
    * Interpolates context values into the message placeholders.
    *
    * This function is just copied from the example in the PSR-3 spec
    *
    */
   protected function interpolate($message, $context = array())
   {
       $replace = array();
       foreach ($context as $key => $val) {
           $replace['{' . $key . '}'] = $val;
       }
       return strtr($message, $replace);
   }
}
