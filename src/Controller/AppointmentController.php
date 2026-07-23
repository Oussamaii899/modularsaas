<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Repository\AppointmentRepository;
use App\Repository\ContactRepository;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/appointments')]
#[IsGranted('ROLE_USER')]
class AppointmentController extends AbstractController
{
    #[Route('/', name: 'app_appointments_index')]
    public function index(
        AppointmentRepository $appointmentRepository,
        SettingRepository $settingRepository,
        ContactRepository $contactRepository
    ): Response {
        $activeModule = $settingRepository->findOneBy(['keyName' => 'active_module'])?->getValue() ?? 'none';
        if ($activeModule === 'doctor') {
            $contacts = $contactRepository->findBy(['type' => 'client'], ['name' => 'ASC']);
        } else {
            $contacts = $contactRepository->findBy([], ['name' => 'ASC']);
        }

        return $this->render('appointments/index.html.twig', [
            'contacts' => $contacts,
            'active_module' => $activeModule,
            'company_logo' => $settingRepository->findOneBy(['keyName' => 'company_logo'])?->getValue(),
            'company_name' => $settingRepository->findOneBy(['keyName' => 'company_name'])?->getValue(),
        ]);
    }

    #[Route('/api/events', name: 'app_appointments_api_events', methods: ['GET'])]
    public function apiEvents(
        Request $request,
        AppointmentRepository $appointmentRepository
    ): JsonResponse {
        $startParam = $request->query->get('start');
        $endParam = $request->query->get('end');

        $start = $startParam ? new \DateTime(substr($startParam, 0, 19)) : new \DateTime('today 00:00:00');
        $end = $endParam ? new \DateTime(substr($endParam, 0, 19)) : new \DateTime('+30 days 23:59:59');

        $user = $this->getUser();
        $doctorId = $this->isGranted('ROLE_ADMIN') ? null : $user->getId();

        $appointments = $appointmentRepository->findByDateRange($start, $end, $doctorId);

        $events = [];
        foreach ($appointments as $apt) {
            $statusColors = [
                'scheduled' => ['#6366f1', '#eef2ff'],
                'confirmed' => ['#059669', '#ecfdf5'],
                'completed' => ['#64748b', '#f8fafc'],
                'cancelled' => ['#dc2626', '#fef2f2'],
                'no_show'   => ['#f59e0b', '#fffbeb'],
            ];
            $colors = $statusColors[$apt->getStatus()] ?? ['#6366f1', '#eef2ff'];

            $events[] = [
                'id'              => $apt->getId(),
                'title'           => $apt->getPatient()?->getName() . ($apt->getReason() ? ' - ' . $apt->getReason() : ''),
                'start'           => $apt->getStartAt()->format('Y-m-d\TH:i:s'),
                'end'             => $apt->getEndAt()?->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $colors[1],
                'borderColor'     => $colors[0],
                'textColor'       => $colors[0],
                'extendedProps'   => [
                    'patientName' => $apt->getPatient()?->getName(),
                    'doctorName'  => $apt->getDoctor()?->getFullName(),
                    'reason'      => $apt->getReason(),
                    'notes'       => $apt->getNotes(),
                    'status'      => $apt->getStatus(),
                ],
            ];
        }

        return $this->json($events);
    }

    #[Route('/api/create', name: 'app_appointments_api_create', methods: ['POST'])]
    public function apiCreate(
        Request $request,
        EntityManagerInterface $entityManager,
        ContactRepository $contactRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $patientId = $data['patientId'] ?? null;
        $startAt = $data['startAt'] ?? null;
        $endAt = $data['endAt'] ?? null;
        $reason = $data['reason'] ?? null;
        $notes = $data['notes'] ?? null;

        if (!$patientId || !$startAt) {
            return $this->json(['error' => 'Patient and start time are required.'], 400);
        }

        $patient = $contactRepository->find($patientId);
        if (!$patient) {
            return $this->json(['error' => 'Patient not found.'], 404);
        }

        $appointment = new Appointment();
        $appointment->setPatient($patient);
        $appointment->setDoctor($this->getUser());
        $appointment->setStartAt(new \DateTime($startAt));
        if ($endAt) {
            $appointment->setEndAt(new \DateTime($endAt));
        } else {
            $appointment->setEndAt((new \DateTime($startAt))->modify('+30 minutes'));
        }
        $appointment->setReason($reason);
        $appointment->setNotes($notes);
        $appointment->setStatus('scheduled');

        $entityManager->persist($appointment);
        $entityManager->flush();

        return $this->json(['success' => true, 'id' => $appointment->getId()], 201);
    }

    #[Route('/api/{id}/update-status', name: 'app_appointments_api_update_status', methods: ['POST'])]
    public function apiUpdateStatus(
        Appointment $appointment,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $status = $data['status'] ?? null;

        $allowed = ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'];
        if (!in_array($status, $allowed)) {
            return $this->json(['error' => 'Invalid status.'], 400);
        }

        $appointment->setStatus($status);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/api/{id}/delete', name: 'app_appointments_api_delete', methods: ['POST'])]
    public function apiDelete(
        Appointment $appointment,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $entityManager->remove($appointment);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }
}
