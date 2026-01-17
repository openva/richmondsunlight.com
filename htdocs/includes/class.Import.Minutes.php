<?php

/**
 * Minutes parsing helpers for the Import class.
 */
trait ImportMinutes
{
    /**
     * Build readable minutes text from the structured LIS API response.
     *
     * @param array $minutes_book The MinutesBook object from the API.
     * @return string Formatted minutes text.
     */
    public function build_minutes_text(array $minutes_book): string
    {
        $text_parts = [];

        if (empty($minutes_book['MinutesCategories'])) {
            return '';
        }

        foreach ($minutes_book['MinutesCategories'] as $category) {
            # Add category description as a header if present
            if (!empty($category['CategoryDescription'])) {
                $text_parts[] = '<b>' . htmlspecialchars($category['CategoryDescription']) . '</b>';
            }

            if (empty($category['MinutesEntries'])) {
                continue;
            }

            foreach ($category['MinutesEntries'] as $entry) {
                $entry_text = '';

                # Add legislation number if present
                if (!empty($entry['LegislationNumber'])) {
                    $entry_text .= '<b>' . htmlspecialchars($entry['LegislationNumber']) . '</b>';
                    if (!empty($entry['LegislationDescription'])) {
                        $entry_text .= ' - ' . htmlspecialchars($entry['LegislationDescription']);
                    }
                } elseif (!empty($entry['EntryText'])) {
                    $entry_text .= htmlspecialchars($entry['EntryText']);
                }

                # Process activities
                if (!empty($entry['MinutesActivities'])) {
                    foreach ($entry['MinutesActivities'] as $activity) {
                        # Skip deleted activities
                        if (!empty($activity['DeletionDate'])) {
                            continue;
                        }

                        $activity_text = '';

                        # Build text from activity references
                        if (!empty($activity['ActivityReferences'])) {
                            foreach ($activity['ActivityReferences'] as $ref) {
                                if (!empty($ref['ReferenceText'])) {
                                    $activity_text .= htmlspecialchars($ref['ReferenceText']);
                                }
                            }
                        } elseif (!empty($activity['Description'])) {
                            $activity_text = htmlspecialchars($activity['Description']);
                        }

                        # Add vote tally if present
                        if (!empty($activity['VoteTally'])) {
                            $activity_text .= ' - ' . htmlspecialchars($activity['VoteTally']);
                        }

                        if (!empty($activity_text)) {
                            if (!empty($entry_text)) {
                                $entry_text .= '<br>' . "\n";
                            }
                            $entry_text .= $activity_text;
                        }
                    }
                }

                if (!empty($entry_text)) {
                    $text_parts[] = $entry_text;
                }
            }
        }

        return implode("\n\n", $text_parts);
    }

    /**
     * Extract the House minutes ID from the minutes page HTML.
     *
     * @param string $html
     * @return int|null
     */
    public function parse_house_minutes_id(string $html): ?int
    {
        if (preg_match('/id="minute-id"\\s+value="(\\d+)"/', $html, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    /**
     * Extract the House minutes date and text content from the HTML.
     *
     * @param string $html
     * @return array|null
     */
    public function extract_house_minutes_data(string $html): ?array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $html = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' . $html;
        $loaded = $dom->loadHTML($html);
        libxml_clear_errors();

        if (!$loaded) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $container = $xpath->query(
            '//div[contains(concat(" ", normalize-space(@class), " "), " minutes-admin-vga ")]'
        )->item(0);

        if (!$container) {
            return null;
        }

        $date_text = '';
        $header = $xpath->query('.//header', $container)->item(0);
        if ($header) {
            $h5s = $xpath->query('.//h5', $header);
            if ($h5s->length > 0) {
                $date_text = trim($h5s->item($h5s->length - 1)->textContent);
            }
        }

        $date_text = preg_replace('/\\s+/', ' ', $date_text);
        $minutes_date = '';
        if (!empty($date_text)) {
            $date = DateTime::createFromFormat('l, F j, Y', $date_text);
            if (!$date) {
                $timestamp = strtotime($date_text);
                if ($timestamp !== false) {
                    $date = new DateTime('@' . $timestamp);
                }
            }
            if ($date) {
                $minutes_date = $date->format('Y-m-d');
            }
        }

        if (empty($minutes_date)) {
            return null;
        }

        foreach ($xpath->query('.//script|.//style', $container) as $node) {
            $node->parentNode->removeChild($node);
        }

        foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " controls ")]', $container)
            as $node) {
            $node->parentNode->removeChild($node);
        }

        $inner_html = '';
        foreach ($container->childNodes as $child) {
            $inner_html .= $dom->saveHTML($child);
        }

        $minutes_text = $this->house_minutes_html_to_text($inner_html);

        return [
            'date' => $minutes_date,
            'text' => $minutes_text,
        ];
    }

    /**
     * Normalize House minutes HTML into display-ready text with line breaks.
     *
     * @param string $html
     * @return string
     */
    public function house_minutes_html_to_text(string $html): string
    {
        $html = preg_replace('/<\\s*br\\s*\\/?\\s*>/i', "\n", $html);
        $html = preg_replace(
            '/<\\/\\s*(p|div|section|header|h[1-6]|li|ul|ol|table|tr|td|th|blockquote)\\s*>/i',
            "\n",
            $html
        );
        $html = preg_replace('/<\\s*li\\b[^>]*>/i', '- ', $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\\r\\n?/", "\n", $text);
        $text = preg_replace("/[ \\t]+\\n/", "\n", $text);
        $text = preg_replace("/\\n{3,}/", "\n\n", $text);
        $text = trim($text);

        $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return nl2br($text);
    }
}
