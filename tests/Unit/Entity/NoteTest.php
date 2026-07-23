<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Note;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class NoteTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $note = new Note();
        $user = new User();
        $now = new \DateTime();

        $note->setContent('Patient follow up note')
            ->setUser($user)
            ->setTargetType('patient')
            ->setTargetId(42)
            ->setCreatedAt($now);

        $this->assertNull($note->getId());
        $this->assertEquals('Patient follow up note', $note->getContent());
        $this->assertSame($user, $note->getUser());
        $this->assertEquals('patient', $note->getTargetType());
        $this->assertEquals(42, $note->getTargetId());
        $this->assertSame($now, $note->getCreatedAt());
    }

    public function testConstructorInitializesCreatedAt(): void
    {
        $note = new Note();
        $this->assertInstanceOf(\DateTimeInterface::class, $note->getCreatedAt());
    }
}
