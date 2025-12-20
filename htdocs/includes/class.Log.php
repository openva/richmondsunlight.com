<?php

/**
 * Emits application log messages to multiple backends (filesystem, Slack, stdout).
 */
class Log
{
    public $verbosity;
    public $output;

    /**
     * Configure logging verbosity and destination.
     */
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

    /**
     * Record a log message if it meets the configured verbosity threshold.
     *
     * @param string $message Message to log.
     * @param int    $level   Severity level (1=debug .. 8=emergency).
     *
     * @return bool True after the message is handled; false when input is invalid.
     */
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
            if ($level >= 6) {
                $message = "@channel {$message}";
            }
            $this->slack($message, 'rs', $emoji[$level]);
        }

        return true;
    }

    /**
     * Send a log message to Slack via incoming webhook.
     *
     * @param string $message Message body.
     * @param string $room    Slack channel slug (without leading #).
     * @param string $icon    Emoji identifier to show with the message.
     *
     * @return string|false Slack API response body, or false on failure.
     */
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
     * Append a log message to the filesystem log file.
     *
     * @param string $message Message text.
     *
     * @return bool True on success, false when writing fails.
     */
    public function filesystem($message)
    {

        // Prepend the message with a timestamp and follow it with a newline.
        $message = date('Y-m-d H:i:s') . ' ' . $message . "\n";

        // Keep logs in different locations, depending on how this has been invoked.
        if (PHP_SAPI === 'cli') {
            $file = __DIR__ . '/../logs/site.log';
        } else {
            $file = __DIR__ . '/../../logs/site.log';
        }

        // Make the directory, if it doesn't exist
        if (!file_exists(__DIR__ . '/../logs/')) {
            mkdir(__DIR__ . '/../logs/');
        }

        if (file_put_contents($file, $message, FILE_APPEND) === false) {
            return false;
        }
        return true;
    }
}
