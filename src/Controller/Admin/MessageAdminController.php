<?php

namespace App\Controller\Admin;

use App\Entity\ContactMessage;
use App\Repository\ContactMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class MessageAdminController extends AbstractController
{
    #[Route('/messages', name: 'admin_messages')]
    public function index(Request $request, ContactMessageRepository $messageRepository, EntityManagerInterface $em): Response
    {
        $search = trim($request->query->get('search', ''));

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');
            $messageId = (int) $request->request->get('message_id');
            $message = $messageRepository->find($messageId);

            if ($message && $action === 'delete_message') {
                $em->remove($message);
                $em->flush();
                $this->addFlash('success', "Message #$messageId deleted successfully.");
            }

            return $this->redirectToRoute('admin_messages', ['search' => $search]);
        }

        $qb = $messageRepository->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC');

        if ($search !== '') {
            $qb->andWhere('m.name LIKE :srch OR m.email LIKE :srch OR m.subject LIKE :srch OR m.message LIKE :srch')
               ->setParameter('srch', '%' . $search . '%');
        }

        $messages = $qb->getQuery()->getResult();

        return $this->render('admin/messages.html.twig', [
            'messages' => $messages,
            'search' => $search,
            'activePage' => 'messages',
            'topbarTitle' => 'Customer Messages',
        ]);
    }

    #[Route('/messages/{id}/delete', name: 'admin_message_delete', methods: ['POST'])]
    public function delete(int $id, ContactMessageRepository $messageRepository, EntityManagerInterface $em): Response
    {
        $message = $messageRepository->find($id);
        if ($message) {
            $em->remove($message);
            $em->flush();
            $this->addFlash('success', "Message #$id deleted successfully.");
        }
        return $this->redirectToRoute('admin_messages');
    }
}

