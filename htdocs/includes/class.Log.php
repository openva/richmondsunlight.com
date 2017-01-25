class Log
{

    function __construct()
    {

        /*
         * Set the minimum threshold of the messages we want (on a scale of 1–8, 1
         * being debug, 8 being an emergency).
         */
        if (defined('LOG_VERBOSITY'))
        {
            $this->verbosity = LOG_VERBOSITY;
        }
        else
        {
            $this->verbosity = 5;
        }

        /*
         * Where we store our logs.
         */
        if (defined('LOG_OUTPUT'))
        {
            $this->output = LOG_OUTPUT;
        }
        else
        {
            $this->output = 'slack';
        }

    }
    
    function put($message, $level)
    {

        if (!isset($message))
        {
            return FALSE;
        }
        if (!isset($level))
        {
            $level = 3;
        }

        /*
         * If the level of this message is below our verbosity level, ignore it.
         */
        if ($level < $this->verbosity)
        {
            return TRUE;
        }

        /*
         * Send our log entry to Slack.
         */
        if ($this->output == 'slack')
        {
            $this->slack($message);
        }

        /*
         * If this is a top-level error, send it via Pushover, too.
         */
        if ($level == 8)
        {
            $this->pushover('RS: Serious Error', $message);
        }

        return TRUE;

    }

    function slack($message, $room = 'rs' $icon = ':longbox:'')
    {

        $room = ($room) ? $room : "engineering";
        $data = "payload=" . json_encode(array(
                "channel"       =>  "#{$room}",
                "text"          =>  $message,
                "icon_emoji"    =>  $icon
            ));

        // You can get your webhook endpoint from your Slack settings
        $ch = curl_init(SLACK_WEBHOOK);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        
        return $result;

    }

    /**
     * Send an alert to the Pushover iOS app.
     */
    function pushover($title, $message)
    {
        
        if ( !defined('PUSHOVER_KEY') || !isset($title) || !isset($message) )
        {
            return FALSE;
        }
        
        if (strlen($title) > 100)
        {
            $title = substr($title, 0, 100);
        }
        
        if (strlen($message) > 412)
        {
            $message = substr($message, 0, 412);
        }
        
        curl_setopt_array($ch = curl_init(), array(
            CURLOPT_URL => "https://api.pushover.net/1/messages.json",
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_POSTFIELDS => array(
                "token" => PUSHOVER_KEY,
                "user" => "unBH1CeWWY4F5JL2TzhUodQASDUAUG",
                "title" => $title,
                "message" => $message,
            ),
            CURLOPT_SAFE_UPLOAD => true,
        ));
        curl_exec($ch);
        curl_close($ch);
        
        return TRUE;
        
    }

    
}
