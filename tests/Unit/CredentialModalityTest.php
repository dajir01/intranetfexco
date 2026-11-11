<?php

namespace Tests\Unit;

use App\Support\CredentialModality;
use PHPUnit\Framework\TestCase;

class CredentialModalityTest extends TestCase
{
    public function test_event_19_is_always_digital_even_when_saved_as_physical(): void
    {
        $this->assertSame(1, CredentialModality::normalize(19, 0));
        $this->assertSame('Digital', CredentialModality::label(19, 0));
    }

    public function test_other_events_keep_their_saved_modality(): void
    {
        $this->assertSame(0, CredentialModality::normalize(20, 0));
        $this->assertSame('Física', CredentialModality::label(20, 0));
    }
}
