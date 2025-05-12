<?php

namespace AirBerry;

class Logger
{
    private float $start_time;
    private float $end_time;

    public int $api_calls = 0;

    public function __construct()
    {
        $this->start_time = microtime(true);
    }

    public function stop(): void
    {
        $this->end_time = microtime(true);
    }

    public function __tostring(): string
    {
        $dt = \DateTime::createFromFormat('U.u', sprintf('%.6f', $this->end_time));
        $dt->setTimezone(new \DateTimeZone('Europe/Prague'));
        $formattedTime = $dt->format('d.m.H:i:s');
        $durationMs = round(($this->end_time - $this->start_time) * 1000);
        return "{$formattedTime}   
duration:  {$durationMs}ms
api_calls: {$this->api_calls}
";
    }
}