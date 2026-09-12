<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShopController extends AbstractController
{
    #[Route('/shop', name: 'app_shop')]
    public function index(Request $request, ProductRepository $productRepository, EntityManagerInterface $em): Response
    {
        // Handle direct order placement from shop if submitted
        if ($request->isMethod('POST')) {
            /** @var User|null $user */
            $user = $this->getUser();
            if (!$user) {
                $this->addFlash('error', 'Please log in to place an order.');
                return $this->redirectToRoute('app_login');
            }

            $productId = (int) $request->request->get('product_id');
            $quantity = max(1, (int) $request->request->get('quantity', 1));
            $diningOption = $request->request->get('dining_option', 'Dine-in');
            $paymentMethod = $request->request->get('payment_method', 'Cash on Counter');

            $product = $productRepository->find($productId);
            if ($product && $product->isActive()) {
                $order = new Order();
                $order->setUser($user);
                $order->setProduct($product);
                $order->setQuantity($quantity);
                $order->setDiningOption($diningOption);
                $order->setPaymentMethod($paymentMethod);
                $order->setStatus('Pending');

                $em->persist($order);
                $em->flush();

                $this->addFlash('success', "Order placed successfully for {$quantity}x {$product->getName()}!");
                return $this->redirectToRoute('app_my_orders');
            } else {
                $this->addFlash('error', 'Selected item is currently unavailable.');
            }
        }

        $category = $request->query->get('category', 'All');
        $search = trim($request->query->get('search', ''));

        $qb = $productRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.id', 'ASC');

        if ($category !== 'All' && $category !== 'all' && $category !== '') {
            $qb->andWhere('c.categoryname = :cat')
               ->setParameter('cat', $category);
        }

        if ($search !== '') {
            $qb->andWhere('p.name LIKE :srch OR p.description LIKE :srch')
               ->setParameter('srch', '%' . $search . '%');
        }

        $products = $qb->getQuery()->getResult();

        return $this->render('shop/index.html.twig', [
            'products' => $products,
            'selectedCategory' => $category,
            'searchTerm' => $search,
        ]);
    }

    #[Route('/my-orders', name: 'app_my_orders')]
    public function myOrders(OrderRepository $orderRepository): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Please log in to view your orders.');
            return $this->redirectToRoute('app_login');
        }

        $orders = $orderRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('shop/my_orders.html.twig', [
            'orders' => $orders,
        ]);
    }
}

