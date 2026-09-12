<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class ProductAdminController extends AbstractController
{
    #[Route('/products', name: 'admin_products')]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $em
    ): Response {
        $categoryFilter = $request->query->get('category', 'all');
        $search = trim($request->query->get('search', ''));

        $qb = $productRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.createdBy', 'u')
            ->addSelect('c', 'u')
            ->orderBy('p.id', 'DESC');

        if ($categoryFilter !== 'all' && $categoryFilter !== '') {
            $qb->andWhere('c.categoryname = :cat')
               ->setParameter('cat', $categoryFilter);
        }

        if ($search !== '') {
            $qb->andWhere('p.name LIKE :srch OR p.description LIKE :srch')
               ->setParameter('srch', '%' . $search . '%');
        }

        $products = $qb->getQuery()->getResult();
        $categories = $categoryRepository->findAll();

        return $this->render('admin/products.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'categoryFilter' => $categoryFilter,
            'search' => $search,
            'activePage' => 'products',
            'topbarTitle' => 'Product Catalog (System-Wide)',
        ]);
    }

    #[Route('/products/add', name: 'admin_product_add', methods: ['POST'])]
    public function add(Request $request, CategoryRepository $categoryRepository, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_product_add', $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_products');
        }

        $name = trim($request->request->get('product_name') ?: $request->request->get('name', ''));
        $catName = trim($request->request->get('category', 'Coffee'));
        $price = $request->request->get('price', '120.00');
        $stock = (int) $request->request->get('stock', 50);
        $image = trim($request->request->get('image', 'menu-iced-coffee.png'));
        $description = trim($request->request->get('description', ''));

        if ($name !== '') {
            $category = $categoryRepository->findOneBy(['categoryname' => $catName]);
            if (!$category) {
                $category = new Category();
                $category->setCategoryname($catName);
                $category->setDescription($catName . ' items');
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

            $this->addFlash('success', "Product '$name' added to catalog successfully.");
        }

        return $this->redirectToRoute('admin_products');
    }

    #[Route('/products/{id}/edit', name: 'admin_product_edit', methods: ['POST'])]
    public function edit(
        int $id,
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $em
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_product_edit_' . $id, $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_products');
        }

        $product = $productRepository->find($id);
        if (!$product) {
            $this->addFlash('error', 'Product not found.');
            return $this->redirectToRoute('admin_products');
        }

        $name = trim($request->request->get('product_name', ''));
        $catName = trim($request->request->get('category', 'Coffee'));
        $price = $request->request->get('price', $product->getPrice());
        $stock = (int) $request->request->get('stock', $product->getStock());
        $image = trim($request->request->get('image', $product->getImage()));
        $description = trim($request->request->get('description', $product->getDescription()));
        $isActive = (bool) $request->request->get('is_active', true);

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
        $product->setIsActive($isActive);

        $em->flush();
        $this->addFlash('success', "Product '{$product->getName()}' updated successfully.");

        return $this->redirectToRoute('admin_products');
    }

    #[Route('/products/{id}/toggle', name: 'admin_product_toggle', methods: ['POST'])]
    public function toggleActive(int $id, ProductRepository $productRepository, EntityManagerInterface $em): Response
    {
        $product = $productRepository->find($id);
        if ($product) {
            $product->setIsActive(!$product->isActive());
            $em->flush();
            $statusStr = $product->isActive() ? 'ACTIVE' : 'DISABLED';
            $this->addFlash('success', "Product '{$product->getName()}' status set to $statusStr.");
        }
        return $this->redirectToRoute('admin_products');
    }

    #[Route('/products/{id}/delete', name: 'admin_product_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, ProductRepository $productRepository, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_product_delete_' . $id, $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_products');
        }

        $product = $productRepository->find($id);
        if ($product) {
            $name = $product->getName();
            $em->remove($product);
            $em->flush();
            $this->addFlash('success', "Product '$name' permanently removed from catalog.");
        }
        return $this->redirectToRoute('admin_products');
    }
}
