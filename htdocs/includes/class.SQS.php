<?php

/**
 * Interact with AWS’ Simple Queuing Service.
 */
class SQS
{

    use Aws\Sqs\SqsClient;

    public function __construct()
    {

        $this->sqs_client = new SqsClient([
            'profile'	=> 'default',
            'region'	=> 'us-east-1',
            'version'	=> '2012-11-05',
            'key'		=> AWS_ACCESS_KEY,
            'secret'	=> AWS_SECRET_KEY
        ]);

    }

    /**
     * Send a message via SQS.
     */
    public function send()
    {

        if (!isset($this->message))
        {
            return FALSE;
        }

        $this->sqs_client->sendMessage([
            'MessageGroupId'			=> '1',
            'MessageDeduplicationId'	=> mt_rand(),
            'QueueUrl'    				=> SQS_VIDEO_URL,
            'MessageBody' 				=> json_encode($this->message)
        ]);

        return TRUE;

    }

    /**
     * Get a message via SQS.
     */
    public function get()
    {

        $result = $this->sqs_client->ReceiveMessage([
            'QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/947603853016/rs-video-harvester.fifo'
        ]);

        return TRUE;

    }

    /**
     * Delete a message from SQS.
     */
    public function delete()
    {

        $this->sqs_client->DeleteMessage([
            'QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/947603853016/rs-video-harvester.fifo',
            'ReceiptHandle' => $message['ReceiptHandle']
        ]);

        return TRUE;

    }

}
