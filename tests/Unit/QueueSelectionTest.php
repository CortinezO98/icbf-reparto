<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Users\QueueSelection;
use PHPUnit\Framework\TestCase;

final class QueueSelectionTest extends TestCase
{
    public function testAllQueueTokensAreRecognized(): void
    {
        self::assertTrue(QueueSelection::meansAll('TODAS'));
        self::assertTrue(QueueSelection::meansAll('all'));
        self::assertTrue(QueueSelection::meansAll('*'));
        self::assertFalse(QueueSelection::meansAll('PETICIONES,ANEXOS'));
    }
}
