<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class OrderAdminController extends AbstractController
{
    #[Route('/requests', name: 'admin_orders')]
    public function index(Request $request, OrderRepository $orderRepository, EntityManagerInterface $em): Response
    {
        $statusFilter = $request->query->get('status', 'all');
        $search = trim($request->query->get('search', ''));

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');
            $orderId = (int) $request->request->get('order_id');
            $order = $orderRepository->find($orderId);

            if ($order) {
                if ($action === 'update_status') {
                    $newStatus = $request->request->get('status', 'Completed');
                    $order->setStatus($newStatus);
                    $em->flush();
                    $this->addFlash('success', "Order ticket #$orderId status changed to $newStatus.");
                } elseif ($action === 'delete_order') {
                    $em->remove($order);
                    $em->flush();
                    $this->addFlash('success', "Order ticket #$orderId deleted.");
                }
            }
            return $this->redirectToRoute('admin_orders', [
                'status' => $statusFilter,
                'search' => $search,
            ]);
        }

        $qb = $orderRepository->createQueryBuilder('o')
            ->leftJoin('o.user', 'u')
            ->leftJoin('o.product', 'p')
            ->addSelect('u', 'p')
            ->orderBy('o.createdAt', 'DESC');

        if ($statusFilter !== 'all' && $statusFilter !== '') {
            $qb->andWhere('o.status = :st')
               ->setParameter('st', $statusFilter);
        }

        if ($search !== '') {
            $qb->andWhere('u.username LIKE :srch OR p.name LIKE :srch OR o.diningOption LIKE :srch OR o.paymentMethod LIKE :srch')
               ->setParameter('srch', '%' . $search . '%');
        }

        $orders = $qb->getQuery()->getResult();
        $counts = $orderRepository->countByStatus();

        return $this->render('admin/orders.html.twig', [
            'orders' => $orders,
            'statusFilter' => $statusFilter,
            'search' => $search,
            'counts' => $counts,
            'activePage' => 'requests',
            'topbarTitle' => 'Requests & Orders',
        ]);
    }

    #[Route('/requests/{id}/status', name: 'admin_order_status', methods: ['POST'])]
    public function updateStatus(int $id, Request $request, OrderRepository $orderRepository, EntityManagerInterface $em): Response
    {
        $order = $orderRepository->find($id);
        if ($order) {
            $newStatus = $request->request->get('status', 'Completed');
            $order->setStatus($newStatus);
            $em->flush();
            $this->addFlash('success', "Order #$id status changed to $newStatus.");
        }
        return $this->redirectToRoute('admin_orders');
    }

    #[Route('/requests/{id}/delete', name: 'admin_order_delete', methods: ['POST'])]
    public function delete(int $id, OrderRepository $orderRepository, EntityManagerInterface $em): Response
    {
        $order = $orderRepository->find($id);
        if ($order) {
            $em->remove($order);
            $em->flush();
            $this->addFlash('success', "Order ticket #$id deleted.");
        }
        return $this->redirectToRoute('admin_orders');
    }
}

