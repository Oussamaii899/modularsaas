<?php

namespace App\Controller;

use App\Entity\Note;
use App\Repository\NoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notes')]
#[IsGranted('ROLE_USER')]
class NoteController extends AbstractController
{
    #[Route('/create', name: 'app_note_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $content = $request->request->get('content');
        $targetType = $request->request->get('targetType');
        $targetId = $request->request->get('targetId');
        $redirectUrl = $request->request->get('redirectUrl');

        if (empty($content) || empty($targetType) || empty($targetId)) {
            $this->addFlash('error', 'Note content cannot be empty.');
            return $redirectUrl ? $this->redirect($redirectUrl) : $this->redirectToRoute('app_dashboard');
        }

        $note = new Note();
        $note->setContent($content);
        $note->setTargetType($targetType);
        $note->setTargetId((int)$targetId);
        $note->setUser($this->getUser());

        $entityManager->persist($note);
        $entityManager->flush();

        $this->addFlash('success', 'Note added successfully.');

        return $redirectUrl ? $this->redirect($redirectUrl) : $this->redirectToRoute('app_dashboard');
    }

    #[Route('/{id}/delete', name: 'app_note_delete', methods: ['POST'])]
    public function delete(Note $note, Request $request, EntityManagerInterface $entityManager): Response
    {
        $redirectUrl = $request->request->get('redirectUrl');

        // Check if user is the author or admin
        if ($note->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You can only delete your own notes.');
        }

        $entityManager->remove($note);
        $entityManager->flush();

        $this->addFlash('success', 'Note deleted successfully.');

        return $redirectUrl ? $this->redirect($redirectUrl) : $this->redirectToRoute('app_dashboard');
    }

    #[Route('/{id}/edit', name: 'app_note_edit', methods: ['POST'])]
    public function edit(Note $note, Request $request, EntityManagerInterface $entityManager): Response
    {
        $redirectUrl = $request->request->get('redirectUrl');
        $content = $request->request->get('content');

        // Check if user is the author or admin
        if ($note->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You can only edit your own notes.');
        }

        if (empty($content)) {
            $this->addFlash('error', 'Note content cannot be empty.');
            return $redirectUrl ? $this->redirect($redirectUrl) : $this->redirectToRoute('app_dashboard');
        }

        $note->setContent($content);
        $entityManager->flush();

        $this->addFlash('success', 'Note updated successfully.');

        return $redirectUrl ? $this->redirect($redirectUrl) : $this->redirectToRoute('app_dashboard');
    }
}
