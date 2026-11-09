<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BookingController extends AbstractController
{
    #[Route('/book', name: 'app_book')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            /** @var User|null $user */
            $user = $this->getUser();
            if (!$user) {
                $this->addFlash('error', 'Please log in first to book a table reservation.');
                return $this->redirectToRoute('app_login');
            }

            $guests = (int) $request->request->get('guests', 2);
            $dateStr = $request->request->get('booking_date');
            $timeStr = $request->request->get('booking_time');
            $notes = trim($request->request->get('notes', ''));

            if (!$dateStr || !$timeStr) {
                $this->addFlash('error', 'Please select a valid date and time for your table.');
            } else {
                try {
                    $bookingDate = new \DateTime($dateStr);
                    $bookingTime = new \DateTime($timeStr);

                    $appointment = new Appointment();
                    $appointment->setUser($user);
                    $appointment->setGuests($guests);
                    $appointment->setBookingDate($bookingDate);
                    $appointment->setBookingTime($bookingTime);
                    $appointment->setNotes($notes ?: null);
                    $appointment->setStatus('Pending');

                    $em->persist($appointment);
                    $em->flush();

                    $this->addFlash(
                        'success',
                        'Your reservation request for ' . $guests . ' guest(s) on ' . $dateStr . ' at ' . $timeStr . ' has been booked!'
                    );
                    return $this->redirectToRoute('app_book');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Invalid date or time format provided.');
                }
            }
        }

        return $this->render('booking/index.html.twig');
    }
}

