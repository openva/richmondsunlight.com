<?php

/**
 * Keeps the messages it's given, rather than sending them: for tests, and for a dry run of
 * PortfolioDigest::run(). It can be told to fail, to test what happens when sending fails.
 */
class RecordingMailer implements Mailer
{
    /**
     * The messages, each with to, to_name, subject, text, html, and headers.
     */
    public array $messages = [];

    public function __construct(public bool $fail = false)
    {
    }

    public function send(string $to, string $to_name, string $subject, string $text, string $html, array $headers = []): bool
    {
        if ($this->fail) {
            return false;
        }
        $this->messages[] = compact('to', 'to_name', 'subject', 'text', 'html', 'headers');
        return true;
    }
}
