<?php

namespace App\Controller\Staff;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff')]
#[IsGranted('ROLE_STAFF')]
final class StaffProductController extends AbstractController
{
    #[Route('', name: 'staff_home')]
    #[Route('/products', name: 'staff_products')]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository
    ): Response {
        $categoryFilter = $request->query->get('category', 'all');
        $search = trim($request->query->get('search', ''));
        $ownerFilter = $request->query->get('owner', 'all');

        $qb = $productRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.createdBy', 'u')
            ->addSelect('c', 'u')
            ->orderBy('p.id', 'DESC');

        if ($categoryFilter !== 'all' && $categoryFilter !== '') {
            $qb->andWhere('c.categoryname = :cat')
               ->setParameter('cat', $categoryFilter);
        }

        if ($ownerFilter === 'mine') {
            $qb->andWhere('p.createdBy = :me')
               ->setParameter('me', $this->getUser());
        }

        if ($search !== '') {
            $qb->andWhere('p.name LIKE :srch OR p.description LIKE :srch')
               ->setParameter('srch', '%' . $search . '%');
        }

        $products = $qb->getQuery()->getResult();
        $categories = $categoryRepository->findAll();

        return $this->render('staff/products.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'categoryFilter' => $categoryFilter,
            'ownerFilter' => $ownerFilter,
            'search' => $search,
        ]);
    }

    #[Route('/products/add', name: 'staff_product_add', methods: ['POST'])]
    public function add(Request $request, CategoryRepository $categoryRepository, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('staff_product_add', $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('staff_products');
        }

        $name = trim($request->request->get('product_name', ''));
        $catName = trim($request->request->get('category', 'Coffee'));
        $price = $request->request->get('price', '120.00');
        $stock = (int) $request->request->get('stock', 50);
        $image = trim($request->request->get('image', 'menu-iced-coffee.png'));
        $description = trim($request->request->get('description', ''));

        if ($name === '') {
            $this->addFlash('error', 'Product name is required.');
            return $this->redirectToRoute('staff_products');
        }

        $category = $categoryRepository->findOneBy(['categoryname' => $catName]);
        if (!$category) {
            $category = new Category();
            $category->setCategoryname($catName);
            $em->persist($category);
            $em->flush();
        }

        $product = new Product();
        $product->setName($name);
        $product->setCategory($category);
        $product->setPrice((string) $price);
        $product->setStock($stock);
        $product->setImage($image ?: 'menu-iced-coffee.png');
        $product->setDescription($description);
        $product->setIsActive(true);
        $product->setCreatedBy($this->getUser());

        $em->persist($product);
        $em->flush();

        $this->addFlash('success', "Record '$name' created successfully under your staff account.");
        return $this->redirectToRoute('staff_products');
    }

    #[Route('/products/{id}/edit', name: 'staff_product_edit', methods: ['POST'])]
    public function edit(
        int $id,
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $em
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('staff_product_edit_' . $id, $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('staff_products');
        }

        $product = $productRepository->find($id);
        if (!$product) {
            $this->addFlash('error', 'Product record not found.');
            return $this->redirectToRoute('staff_products');
        }

        // STRICT OWNERSHIP CHECK: Staff can ONLY edit their own records!
        $currentUserId = $this->getUser() ? $this->getUser()->getId() : null;
        $authorId = $product->getCreatedBy() ? $product->getCreatedBy()->getId() : null;

        if ($authorId !== $currentUserId) {
            throw new AccessDeniedException('Access Denied: Staff members are restricted to editing only their own created records.');
        }

        $name = trim($request->request->get('product_name', ''));
        $catName = trim($request->request->get('category', 'Coffee'));
        $price = $request->request->get('price', $product->getPrice());
        $stock = (int) $request->request->get('stock', $product->getStock());
        $image = trim($request->request->get('image', $product->getImage()));
        $description = trim($request->request->get('description', $product->getDescription()));

        if ($name !== '') {
            $product->setName($name);
        }

        $category = $categoryRepository->findOneBy(['categoryname' => $catName]);
        if (!$category && $catName !== '') {
            $category = new Category();
            $category->setCategoryname($catName);
            $em->persist($category);
            $em->flush();
        }
        if ($category) {
            $product->setCategory($category);
        }

        $product->setPrice((string) $price);
        $product->setStock($stock);
        if ($image !== '') {
            $product->setImage($image);
        }
        $product->setDescription($description);

        $em->flush();
        $this->addFlash('success', "Your record '{$product->getName()}' has been updated.");

        return $this->redirectToRoute('staff_products');
    }

    #[Route('/products/{id}/delete', name: 'staff_product_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $em
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('staff_product_delete_' . $id, $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('staff_products');
        }

        $product = $productRepository->find($id);
        if (!$product) {
            $this->addFlash('error', 'Product not found.');
            return $this->redirectToRoute('staff_products');
        }

        // STRICT OWNERSHIP CHECK: Staff can ONLY delete their own records!
        $currentUserId = $this->getUser() ? $this->getUser()->getId() : null;
        $authorId = $product->getCreatedBy() ? $product->getCreatedBy()->getId() : null;

        if ($authorId !== $currentUserId) {
            throw new AccessDeniedException('Access Denied: Staff members cannot delete records created by others.');
        }

        $name = $product->getName();
        $em->remove($product);
        $em->flush();

        $this->addFlash('success', "Your record '$name' has been deleted.");
        return $this->redirectToRoute('staff_products');
    }

    #[Route('/profile', name: 'staff_profile')]
    public function profile(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            if ($action === 'change_password') {
                $token = $request->request->get('_token');
                if (!$this->isCsrfTokenValid('staff_change_password', $token)) {
                    $this->addFlash('error', 'Invalid security token.');
                    return $this->redirectToRoute('staff_profile');
                }

                $currentPassword = $request->request->get('current_password', '');
                $newPassword = $request->request->get('new_password', '');
                $confirmPassword = $request->request->get('confirm_password', '');

                if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $this->addFlash('error', 'Current password entered is incorrect.');
                    return $this->redirectToRoute('staff_profile');
                }

                if (strlen($newPassword) < 6) {
                    $this->addFlash('error', 'New password must be at least 6 characters.');
                    return $this->redirectToRoute('staff_profile');
                }

                if ($newPassword !== $confirmPassword) {
                    $this->addFlash('error', 'New password confirmation does not match.');
                    return $this->redirectToRoute('staff_profile');
                }

                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $em->flush();

                $this->addFlash('success', 'Your password has been changed successfully.');
            } elseif ($action === 'update_profile') {
                $email = trim($request->request->get('email', ''));
                $user->setEmail($email ?: null);
                $em->flush();
                $this->addFlash('success', 'Profile information updated.');
            }

            return $this->redirectToRoute('staff_profile');
        }

        return $this->render('staff/profile.html.twig', [
            'user' => $user,
        ]);
    }
}

