<?php

/**
 * Something that sends email: PhpMailerMailer, which really sends it, or RecordingMailer, which
 * keeps it (for tests, and for a dry run). PortfolioDigest sends through one of these, so that
 * everything but the sending can be tested.
 */
interface Mailer
{
    /**
     * Send a message, in plain text and HTML.
     *
     * @param array $headers More headers, by name (e.g., List-Unsubscribe).
     * @return bool Whether it was sent (or, for RecordingMailer, kept).
     */
    public function send(string $to, string $to_name, string $subject, string $text, string $html, array $headers = []): bool;
}
