<?php

namespace App\Controller;

use App\Repository\AppointmentRepository;
use App\Repository\ContactMessageRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[Route('/admin', name: 'admin_dashboard')]
    #[Route('/admin/dashboard', name: 'admin_dashboard_alt')]
    public function index(
        OrderRepository $orderRepository,
        AppointmentRepository $appointmentRepository,
        ProductRepository $productRepository,
        UserRepository $userRepository,
        ContactMessageRepository $contactMessageRepository
    ): Response {
        $revenue = $orderRepository->calculateGrossRevenue();
        $orderCounts = $orderRepository->countByStatus();
        $prodCount = $productRepository->count([]);
        $userCount = $userRepository->count([]);
        $staffCount = $userRepository->count(['role' => 'staff']);
        $customerCount = $userRepository->count(['role' => 'customer']);
        $adminCount = $userRepository->count(['role' => 'admin']);
        $msgCount = $contactMessageRepository->count([]);
        $bookingCounts = $appointmentRepository->countByStatus();
        $bookingCount = $bookingCounts['all'] ?? 0;
        $pendingBookings = $bookingCounts['pending'] ?? 0;
        $totalRecords = $prodCount + ($orderCounts['all'] ?? 0) + $bookingCount + $msgCount;

        $recentOrders = $orderRepository->findBy([], ['createdAt' => 'DESC'], 8);

        return $this->render('dashboard/index.html.twig', [
            'revenue' => $revenue,
            'orderCounts' => $orderCounts,
            'prodCount' => $prodCount,
            'userCount' => $userCount,
            'staffCount' => $staffCount,
            'customerCount' => $customerCount,
            'adminCount' => $adminCount,
            'totalRecords' => $totalRecords,
            'msgCount' => $msgCount,
            'bookingCount' => $bookingCount,
            'pendingBookings' => $pendingBookings,
            'recentOrders' => $recentOrders,
            'activePage' => 'dashboard',
            'topbarTitle' => 'Dashboard Overview',
        ]);
    }
}

