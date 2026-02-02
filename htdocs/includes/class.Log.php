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
    public function put($message, $level = 3)
    {
        if (!isset($message)) {
            return false;
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
                $message = "<!channel> {$message}";
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
        // Check Memcached for active rate limit
        if (MEMCACHED_SERVER != '') {
            $mc = new Memcached();
            $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
            $retry_after = $mc->get('slack-retry-after');
            if ($mc->getResultCode() == Memcached::RES_SUCCESS && $retry_after > time()) {
                return false;
            }
        }

        $room = ($room) ? $room : 'general';
        $data = 'payload=' . json_encode(array(
                'channel'       =>  '#' . $room,
                'text'          =>  $message,
                'icon_emoji'    =>  $icon
            ));

        $ch = curl_init(SLACK_WEBHOOK);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // If rate-limited, store the retry-after time in Memcached
        if ($http_code == 429 && MEMCACHED_SERVER != '') {
            $retry_seconds = 60; // default
            if (preg_match('/^retry-after:\s*(\d+)/mi', $response, $matches)) {
                $retry_seconds = (int)$matches[1];
            }
            if (!isset($mc)) {
                $mc = new Memcached();
                $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
            }
            $mc->set('slack-retry-after', time() + $retry_seconds, $retry_seconds);
        }

        return $response;
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

        // Keep logs in the project log directory, regardless of invocation context.
        $log_dir = __DIR__ . '/../logs';
        $file = $log_dir . '/site.log';

        // Make the directory, if it doesn't exist.
        if (!is_dir($log_dir)) {
            if (!@mkdir($log_dir, 0775, true) && !is_dir($log_dir)) {
                return false;
            }
        }

        // Avoid warnings that would become 500s under JSON error handlers.
        if (!is_writable($log_dir)) {
            return false;
        }
        if (file_exists($file) && !is_writable($file)) {
            return false;
        }

        if (@file_put_contents($file, $message, FILE_APPEND) === false) {
            return false;
        }
        return true;
    }
}
