<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\PatientDocument;
use App\Repository\PatientDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/patient-documents')]
#[IsGranted('ROLE_USER')]
class PatientDocumentController extends AbstractController
{
    #[Route('/upload/{id}', name: 'app_patient_document_upload', methods: ['POST'])]
    public function upload(
        Contact $contact,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if ($contact->getType() !== 'client') {
            throw $this->createNotFoundException('Client not found.');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('document');
        $label = $request->request->get('label');
        $description = $request->request->get('description');

        if (!$file || !$label) {
            $this->addFlash('error', 'File and label are required.');
            return $this->redirectToRoute('app_clients_show', ['slug' => $contact->getSlug()]);
        }

        $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/patient_docs';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0777, true);
        }

        $newFilename = uniqid() . '-' . cleanFilename($file->getClientOriginalName());
        try {
            $file->move($uploadsDir, $newFilename);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to save the uploaded file.');
            return $this->redirectToRoute('app_clients_show', ['slug' => $contact->getSlug()]);
        }

        $doc = new PatientDocument();
        $doc->setContact($contact);
        $doc->setLabel($label);
        $doc->setDescription($description);
        $doc->setFilename($newFilename);
        $doc->setUploadedBy($this->getUser());

        $entityManager->persist($doc);
        $entityManager->flush();

        $this->addFlash('success', 'Document uploaded successfully.');
        return $this->redirectToRoute('app_clients_show', ['slug' => $contact->getSlug()]);
    }

    #[Route('/download/{id}', name: 'app_patient_document_download', methods: ['GET'])]
    public function download(
        PatientDocument $doc
    ): Response {
        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/patient_docs/' . $doc->getFilename();
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('File not found on server.');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $doc->getLabel() . '.' . pathinfo($doc->getFilename(), PATHINFO_EXTENSION)
        );

        return $response;
    }

    #[Route('/delete/{id}', name: 'app_patient_document_delete', methods: ['POST'])]
    public function delete(
        PatientDocument $doc,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $contact = $doc->getContact();
        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/patient_docs/' . $doc->getFilename();

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $entityManager->remove($doc);
        $entityManager->flush();

        $this->addFlash('success', 'Document deleted successfully.');
        return $this->redirectToRoute('app_clients_show', ['slug' => $contact->getSlug()]);
    }
}

function cleanFilename(string $filename): string
{
    return preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
}
