<?php

class Log
{
    public function __construct()
    {

        /*
         * Set the minimum threshold of the messages we want (on a scale of 1–8, 1
         * being debug, 8 being an emergency).
         */
        if (defined('LOG_VERBOSITY')) {
            $this->verbosity = LOG_VERBOSITY;
        } else {
            $this->verbosity = 5;
        }

        /*
         * Where we store our logs.
         */
        if (defined('LOG_OUTPUT')) {
            $this->output = LOG_OUTPUT;
        } else {
            $this->output = 'slack';
        }
    }

    public function put($message, $level)
    {
        if (!isset($message)) {
            return false;
        }
        if (!isset($level)) {
            $level = 3;
        }

        /*
         * If this is being invoked at the CLI, display all messages.
         */
        if (PHP_SAPI === 'cli') {
            echo $message . "\n";
        }

        /*
         * Always write all messages to the filesystem log
         */
        $this->filesystem($message);

        /*
         * If the level of this message is below our verbosity level, ignore it.
         */
        if ($level < $this->verbosity) {
            return true;
        }

        /*
         * Send our log entry to Slack.
         */
        if ($this->output == 'slack') {
            $emoji = array(
                1 => ':white_large_square:',
                2 => ':white_large_square:',
                3 => ':white_large_square:',
                4 => ':large_orange_diamond: ',
                5 => ':large_orange_diamond: ',
                6 => ':rotating_light:',
                7 => ':scream:',
                8 => ':skull:'
                );
            $this->slack($message, 'rs', $emoji[$level]);
        }

        return true;
    }

    public function slack($message, $room = 'rs', $icon = ':longbox:')
    {
        $room = ($room) ? $room : 'general';
        $data = 'payload=' . json_encode(array(
                'channel'       =>  '#' . $room,
                'text'          =>  $message,
                'icon_emoji'    =>  $icon
            ));

        // You can get your webhook endpoint from your Slack settings
        $ch = curl_init(SLACK_WEBHOOK);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    /**
     * Log an error to a text file.
     */
    public function filesystem($message)
    {

    /*
     * Prepend the message with a timestamp.
     */
        $message = date('Y-m-d H:i:s') . ' ' . $message;

        /*
         * Keep logs in different locations, depending on how this has been invoked.
         */
        if (PHP_SAPI === 'cli') {
            $file = __DIR__ . '../logs/site.log';
        } else {
            $file = __DIR__ . '../../logs/site.log';
        }

        if (file_put_contents($file, $message, FILE_APPEND) === false) {
            return false;
        }
        return true;
    }
}
