<?php 

class DebugDriver
{
    protected $options;

    function __construct(array $options) //pouzit dsn - smtp:// ?
    {
        $this->options = $options;
    }

    public function send(Message $message)
    {
        print ($message->preview());
    }
}

 ?>