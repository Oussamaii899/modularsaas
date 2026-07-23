<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Appointment;
use App\Entity\Contact;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Appointment entity.
 *
 * Covers:
 *  - Constructor defaults (status, createdAt)
 *  - All getters / setters
 *  - Doctor and patient associations
 */
class AppointmentTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constructor defaults
    // -------------------------------------------------------------------------

    public function testDefaultStatusIsScheduled(): void
    {
        $appointment = new Appointment();
        $this->assertSame('scheduled', $appointment->getStatus());
    }

    public function testCreatedAtIsSetOnConstruct(): void
    {
        $before = new \DateTimeImmutable();
        $appointment = new Appointment();
        $after  = new \DateTimeImmutable();

        $this->assertNotNull($appointment->getCreatedAt());
        $this->assertGreaterThanOrEqual($before, $appointment->getCreatedAt());
        $this->assertLessThanOrEqual($after, $appointment->getCreatedAt());
    }

    // -------------------------------------------------------------------------
    // Status transitions
    // -------------------------------------------------------------------------

    public function testSetStatus(): void
    {
        $appointment = new Appointment();
        $appointment->setStatus('completed');
        $this->assertSame('completed', $appointment->getStatus());
    }

    public function testSetStatusCancelled(): void
    {
        $appointment = new Appointment();
        $appointment->setStatus('cancelled');
        $this->assertSame('cancelled', $appointment->getStatus());
    }

    // -------------------------------------------------------------------------
    // Patient association
    // -------------------------------------------------------------------------

    public function testSetAndGetPatient(): void
    {
        $appointment = new Appointment();
        $patient = new Contact();
        $patient->setName('John Doe');
        $patient->setType('patient');

        $appointment->setPatient($patient);

        $this->assertSame($patient, $appointment->getPatient());
    }

    public function testSetPatientToNull(): void
    {
        $appointment = new Appointment();
        $appointment->setPatient(null);
        $this->assertNull($appointment->getPatient());
    }

    // -------------------------------------------------------------------------
    // Doctor association
    // -------------------------------------------------------------------------

    public function testSetAndGetDoctor(): void
    {
        $appointment = new Appointment();
        $doctor = new User();
        $doctor->setUsername('dr.smith');

        $appointment->setDoctor($doctor);

        $this->assertSame($doctor, $appointment->getDoctor());
    }

    public function testSetDoctorToNull(): void
    {
        $appointment = new Appointment();
        $appointment->setDoctor(null);
        $this->assertNull($appointment->getDoctor());
    }

    // -------------------------------------------------------------------------
    // Date / time fields
    // -------------------------------------------------------------------------

    public function testSetAndGetStartAt(): void
    {
        $appointment = new Appointment();
        $dt = new \DateTime('2024-06-01 09:00:00');
        $appointment->setStartAt($dt);

        $this->assertSame($dt, $appointment->getStartAt());
    }

    public function testSetAndGetEndAt(): void
    {
        $appointment = new Appointment();
        $dt = new \DateTime('2024-06-01 10:00:00');
        $appointment->setEndAt($dt);

        $this->assertSame($dt, $appointment->getEndAt());
    }

    public function testEndAtIsNullByDefault(): void
    {
        $appointment = new Appointment();
        $this->assertNull($appointment->getEndAt());
    }

    // -------------------------------------------------------------------------
    // Reason / notes
    // -------------------------------------------------------------------------

    public function testSetAndGetReason(): void
    {
        $appointment = new Appointment();
        $appointment->setReason('Annual check-up');
        $this->assertSame('Annual check-up', $appointment->getReason());
    }

    public function testSetAndGetNotes(): void
    {
        $appointment = new Appointment();
        $appointment->setNotes('Patient allergic to penicillin');
        $this->assertSame('Patient allergic to penicillin', $appointment->getNotes());
    }

    public function testReasonIsNullByDefault(): void
    {
        $appointment = new Appointment();
        $this->assertNull($appointment->getReason());
    }

    public function testNotesIsNullByDefault(): void
    {
        $appointment = new Appointment();
        $this->assertNull($appointment->getNotes());
    }

    // -------------------------------------------------------------------------
    // createdAt explicit setter
    // -------------------------------------------------------------------------

    public function testSetCreatedAt(): void
    {
        $appointment = new Appointment();
        $dt = new \DateTimeImmutable('2024-01-01');
        $appointment->setCreatedAt($dt);

        $this->assertSame($dt, $appointment->getCreatedAt());
    }
}
