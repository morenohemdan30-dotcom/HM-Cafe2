<?php

namespace App\Controller;

use App\Entity\ContactMessage;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        UserRepository $userRepository,
        OrderRepository $orderRepository,
        ReviewRepository $reviewRepository,
        EntityManagerInterface $em
    ): Response {
        if ($request->isMethod('POST')) {
            $name = trim($request->request->get('name', ''));
            $email = trim($request->request->get('email', ''));
            $subject = trim($request->request->get('subject', 'General Inquiry'));
            $messageContent = trim($request->request->get('message', ''));

            if ($name !== '' && $email !== '' && $messageContent !== '') {
                $msg = new ContactMessage();
                $msg->setName($name);
                $msg->setEmail($email);
                $msg->setSubject($subject);
                $msg->setMessage($messageContent);

                $em->persist($msg);
                $em->flush();

                $this->addFlash('success', 'Salamat! Nadawat na ang imong mensahe. We will get back to you shortly!');
            } else {
                $this->addFlash('error', 'Please fill in all contact fields.');
            }

            return $this->redirectToRoute('app_home', ['_fragment' => 'contact']);
        }

        $totalCustomers = $userRepository->count(['role' => 'customer']);
        $completedOrders = $orderRepository->count(['status' => 'Completed']);
        $totalReviews = $reviewRepository->count([]);
        
        $avgRating = 0;
        if ($totalReviews > 0) {
            $ratings = $reviewRepository->createQueryBuilder('r')
                ->select('AVG(r.rating)')
                ->getQuery()
                ->getSingleScalarResult();
            $avgRating = round((float) $ratings, 1);
        }

        $reviews = $reviewRepository->findBy([], ['createdAt' => 'DESC'], 3);
        $popularProducts = $productRepository->findBy(['isActive' => true], ['id' => 'ASC'], 4);

        return $this->render('home/index.html.twig', [
            'totalCustomers' => $totalCustomers > 0 ? $totalCustomers : 50,
            'completedOrders' => $completedOrders > 0 ? $completedOrders : 120,
            'totalReviews' => $totalReviews > 0 ? $totalReviews : 48,
            'avgRating' => $avgRating > 0 ? $avgRating : 4.9,
            'reviews' => $reviews,
            'popularProducts' => $popularProducts,
        ]);
    }
}

