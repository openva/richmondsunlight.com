<?php

use PHPUnit\Framework\TestCase;

/**
 * first_sentences(), which shortens text (e.g., posts from legislators' websites) to a number of
 * sentences. The text here is adapted from real feeds.
 */
class FirstSentencesTest extends TestCase
{
    public function testKeepsTheFirstFourSentences(): void
    {
        $text = 'One is here. Two is here! Is three here? Four is here. Five is here. Six is here.';
        $this->assertSame('One is here. Two is here! Is three here? Four is here. …', first_sentences($text, 4));
    }

    public function testLeavesShortTextAlone(): void
    {
        $text = 'Read the Virginian Pilot article here.';
        $this->assertSame($text, first_sentences($text, 4));
        $this->assertSame('One. Two. Three. Four.', first_sentences('One. Two. Three. Four.', 4));
    }

    public function testDoesNotSplitAtAbbreviationsOrInitials(): void
    {
        $text = 'Del. Jessica Anderson met Sen. Creigh Deeds in Richmond, Va. on Monday. '
            . 'Del.-elect Anderson thanked Gov. Spanberger and J. H. Glass. '
            . 'They met at 10 a.m. Tuesday in the U.S. Capitol. '
            . 'It was brief. It ended early.';
        $this->assertSame(
            'Del. Jessica Anderson met Sen. Creigh Deeds in Richmond, Va. on Monday. '
            . 'Del.-elect Anderson thanked Gov. Spanberger and J. H. Glass. '
            . 'They met at 10 a.m. Tuesday in the U.S. Capitol. '
            . 'It was brief. …',
            first_sentences($text, 4)
        );
    }

    public function testCountsSentencesThatEndInQuotes(): void
    {
        $text = '“Making child care more affordable is going to lift all the boats.” She said so. '
            . 'Then she left. Then she came back. Then it ended.';
        $this->assertSame(
            '“Making child care more affordable is going to lift all the boats.” She said so. '
            . 'Then she left. Then she came back. …',
            first_sentences($text, 4)
        );
    }

    public function testTurnsHtmlIntoText(): void
    {
        $html = '<p>Anderson defeated Delegate &amp; Republican House Caucus Chair Amanda Batten</p>'
            . '<p>Williamsburg, VA &#8211; Jessica Anderson declared victory.</p>';
        $this->assertSame(
            'Anderson defeated Delegate & Republican House Caucus Chair Amanda Batten '
            . 'Williamsburg, VA – Jessica Anderson declared victory.',
            first_sentences($html, 4)
        );
    }

    public function testDoesNotEndASentenceBeforeALowercaseWord(): void
    {
        $text = 'Funding rose 3.5 percent, e.g. in schools. Then it fell. It rose. It fell. It rose.';
        $this->assertSame(
            'Funding rose 3.5 percent, e.g. in schools. Then it fell. It rose. It fell. …',
            first_sentences($text, 4)
        );
    }

    public function testHandlesEmptyText(): void
    {
        $this->assertSame('', first_sentences('', 4));
        $this->assertSame('', first_sentences('<p> </p>', 4));
    }
}
