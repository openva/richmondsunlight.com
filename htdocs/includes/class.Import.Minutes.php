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
        if (preg_match('/<input[^>]+id=["\']minute-id["\'][^>]+value=["\'](\d+)["\']/', $html, $matches)) {
            return (int)$matches[1];
        }
        if (preg_match('/<input[^>]+value=["\'](\d+)["\'][^>]+id=["\']minute-id["\']/', $html, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    /**
     * Extract House minutes data (date and text) from HTML.
     *
     * Produces plain text with double newlines between paragraphs, suitable
     * for storage and display via nl2p(). Strips all buttons, controls, and
     * decorative markup from the source HTML.
     *
     * @param string $html HTML response from the House minutes scraper.
     *
     * @return array|null Array with 'date' and 'text' keys, or null on failure.
     */
    public function extract_house_minutes_data(string $html): ?array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);

        $minutes_admin = $xpath->query('//div[@class="minutes-admin-vga"]');
        if ($minutes_admin->length === 0) {
            return null;
        }

        $root = $minutes_admin->item(0);

        // Extract date and header lines from the <header> h5 elements
        $date = null;
        $header_lines = [];
        foreach ($xpath->query('.//header//h5', $root) as $h5) {
            $text = trim($h5->textContent);
            if ($text !== '') {
                $header_lines[] = $text;
            }
            if ($date === null && preg_match(
                '/^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday),\s+(.+)$/i',
                $text,
                $matches
            )) {
                $parsed = strtotime($matches[2]);
                if ($parsed !== false) {
                    $date = date('Y-m-d', $parsed);
                }
            }
        }

        if ($date === null) {
            return null;
        }

        $paragraphs = [implode("\n", $header_lines)];

        // Pull text from each section. In the full admin HTML each section has
        // a div.section-content wrapper; fall back to the section element itself
        // for simpler renderings that use plain <p> tags directly.
        foreach ($xpath->query('.//section', $root) as $section) {
            $content_nodes = $xpath->query('.//div[contains(@class,"section-content")]', $section);
            if ($content_nodes->length > 0) {
                foreach ($content_nodes as $content_node) {
                    $text = $this->extract_minutes_text($content_node);
                    if ($text !== '') {
                        $paragraphs[] = $text;
                    }
                }
            } else {
                $text = $this->extract_minutes_text($section);
                if ($text !== '') {
                    $paragraphs[] = $text;
                }
            }
        }

        return [
            'date' => $date,
            'text' => implode("\n\n", $paragraphs),
        ];
    }

    /**
     * Recursively extract plain text from a DOM node, skipping interactive
     * and decorative elements. Inserts spaces between sibling block elements
     * to prevent words from running together.
     *
     * @param DOMNode $node
     * @return string
     */
    private function extract_minutes_text(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return $node->textContent;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $tag = strtolower($node->nodeName);

        // Drop buttons, inputs, icons, and scripts entirely
        if (in_array($tag, ['button', 'input', 'i', 'script', 'style'], true)) {
            return '';
        }

        // Drop the controls div (move/delete buttons, directional handles)
        if ($tag === 'div' && preg_match('/\bcontrols\b/', (string)$node->getAttribute('class'))) {
            return '';
        }

        $parts = [];
        foreach ($node->childNodes as $child) {
            $child_text = $this->extract_minutes_text($child);
            if ($child_text !== '') {
                $parts[] = $child_text;
            }
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
    }
}
