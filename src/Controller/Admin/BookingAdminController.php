<?php

namespace App\Controller\Admin;

use App\Entity\Appointment;
use App\Repository\AppointmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class BookingAdminController extends AbstractController
{
    #[Route('/bookings', name: 'admin_bookings')]
    public function index(Request $request, AppointmentRepository $appointmentRepository, EntityManagerInterface $em): Response
    {
        $statusFilter = $request->query->get('status', 'all');
        $search = trim($request->query->get('search', ''));

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');
            $appointmentId = (int) $request->request->get('appointment_id');
            $appointment = $appointmentRepository->find($appointmentId);

            if ($appointment) {
                if ($action === 'update_status') {
                    $newStatus = $request->request->get('status', 'Confirmed');
                    $appointment->setStatus($newStatus);
                    $em->flush();
                    $this->addFlash('success', "Booking #$appointmentId has been marked as $newStatus.");
                } elseif ($action === 'delete_booking') {
                    $em->remove($appointment);
                    $em->flush();
                    $this->addFlash('success', "Booking #$appointmentId deleted successfully.");
                }
            }
            return $this->redirectToRoute('admin_bookings', [
                'status' => $statusFilter,
                'search' => $search,
            ]);
        }

        $qb = $appointmentRepository->createQueryBuilder('b')
            ->leftJoin('b.user', 'u')
            ->addSelect('u')
            ->orderBy('b.bookingDate', 'DESC')
            ->addOrderBy('b.bookingTime', 'DESC');

        if ($statusFilter !== 'all' && $statusFilter !== '') {
            $qb->andWhere('b.status = :st')
               ->setParameter('st', $statusFilter);
        }

        if ($search !== '') {
            $qb->andWhere('u.username LIKE :srch OR u.email LIKE :srch OR b.notes LIKE :srch')
               ->setParameter('srch', '%' . $search . '%');
        }

        $bookings = $qb->getQuery()->getResult();
        $counts = $appointmentRepository->countByStatus();

        return $this->render('admin/bookings.html.twig', [
            'bookings' => $bookings,
            'statusFilter' => $statusFilter,
            'search' => $search,
            'counts' => $counts,
            'activePage' => 'bookings',
            'topbarTitle' => 'Table Reservations',
        ]);
    }

    #[Route('/bookings/{id}/status', name: 'admin_booking_status', methods: ['POST'])]
    public function updateStatus(int $id, Request $request, AppointmentRepository $appointmentRepository, EntityManagerInterface $em): Response
    {
        $appointment = $appointmentRepository->find($id);
        if ($appointment) {
            $newStatus = $request->request->get('status', 'Confirmed');
            $appointment->setStatus($newStatus);
            $em->flush();
            $this->addFlash('success', "Booking #$id has been marked as $newStatus.");
        }
        return $this->redirectToRoute('admin_bookings');
    }

    #[Route('/bookings/{id}/delete', name: 'admin_booking_delete', methods: ['POST'])]
    public function delete(int $id, AppointmentRepository $appointmentRepository, EntityManagerInterface $em): Response
    {
        $appointment = $appointmentRepository->find($id);
        if ($appointment) {
            $em->remove($appointment);
            $em->flush();
            $this->addFlash('success', "Booking #$id deleted successfully.");
        }
        return $this->redirectToRoute('admin_bookings');
    }
}

