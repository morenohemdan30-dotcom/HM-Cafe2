<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class ClientAdminController extends AbstractController
{
    #[Route('/clients', name: 'admin_clients')]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $roleFilter = $request->query->get('role', 'all');
        $statusFilter = $request->query->get('status', 'all');
        $search = trim($request->query->get('search', ''));

        $qb = $userRepository->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC');

        if ($roleFilter !== 'all' && $roleFilter !== '') {
            $qb->andWhere('u.role = :rl')
               ->setParameter('rl', $roleFilter);
        }

        if ($statusFilter !== 'all' && $statusFilter !== '') {
            $qb->andWhere('u.status = :st')
               ->setParameter('st', $statusFilter);
        }

        if ($search !== '') {
            $qb->andWhere('u.username LIKE :srch OR u.email LIKE :srch')
               ->setParameter('srch', '%' . $search . '%');
        }

        $clients = $qb->getQuery()->getResult();

        $totalUsers = $userRepository->count([]);
        $totalCustomers = $userRepository->count(['role' => 'customer']);
        $totalStaff = $userRepository->count(['role' => 'staff']);
        $totalAdmins = $userRepository->count(['role' => 'admin']);

        return $this->render('admin/clients.html.twig', [
            'clients' => $clients,
            'totalUsers' => $totalUsers,
            'totalCustomers' => $totalCustomers,
            'totalStaff' => $totalStaff,
            'totalAdmins' => $totalAdmins,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
            'search' => $search,
            'activePage' => 'clients',
            'topbarTitle' => 'Users & Staff Management',
        ]);
    }

    #[Route('/clients/create', name: 'admin_client_create', methods: ['POST'])]
    public function create(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_client_create', $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_clients');
        }

        $username = trim($request->request->get('username', ''));
        $email = trim($request->request->get('email', ''));
        $plainPassword = $request->request->get('password', '');
        $role = $request->request->get('role', 'staff');
        $status = $request->request->get('status', 'active');

        if ($username === '' || $plainPassword === '') {
            $this->addFlash('error', 'Username and password are required.');
            return $this->redirectToRoute('admin_clients');
        }

        if ($userRepository->findOneBy(['username' => $username])) {
            $this->addFlash('error', "Username '$username' is already taken.");
            return $this->redirectToRoute('admin_clients');
        }

        if ($email !== '' && $userRepository->findOneBy(['email' => $email])) {
            $this->addFlash('error', "Email '$email' is already registered to another user.");
            return $this->redirectToRoute('admin_clients');
        }

        $user = new User();
        $user->setUsername($username);
        if ($email !== '') {
            $user->setEmail($email);
        }
        $user->setRole(in_array($role, ['admin', 'staff', 'customer'], true) ? $role : 'staff');
        $user->setStatus($status === 'disabled' ? 'disabled' : 'active');
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

        $em->persist($user);
        $em->flush();

        $this->addFlash('success', "Account '{$user->getUsername()}' created with role " . strtoupper($user->getRole()) . ".");
        return $this->redirectToRoute('admin_clients');
    }

    #[Route('/clients/{id}/edit', name: 'admin_client_edit', methods: ['POST'])]
    public function edit(
        int $id,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_client_edit_' . $id, $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_clients');
        }

        $user = $userRepository->find($id);
        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('admin_clients');
        }

        $username = trim($request->request->get('username', ''));
        $email = trim($request->request->get('email', ''));
        $role = $request->request->get('role', $user->getRole());
        $status = $request->request->get('status', $user->getStatus());
        $newPassword = $request->request->get('new_password', '');

        if ($username !== '') {
            $existing = $userRepository->findOneBy(['username' => $username]);
            if ($existing && $existing->getId() !== $user->getId()) {
                $this->addFlash('error', "Username '$username' is already used by another account.");
                return $this->redirectToRoute('admin_clients');
            }
            $user->setUsername($username);
        }

        if ($email !== '') {
            $existingEmail = $userRepository->findOneBy(['email' => $email]);
            if ($existingEmail && $existingEmail->getId() !== $user->getId()) {
                $this->addFlash('error', "Email '$email' is already used by another account.");
                return $this->redirectToRoute('admin_clients');
            }
            $user->setEmail($email);
        } else {
            $user->setEmail(null);
        }

        if (in_array($role, ['admin', 'staff', 'customer'], true)) {
            $user->setRole($role);
        }

        if (in_array($status, ['active', 'disabled'], true)) {
            $user->setStatus($status);
        }

        if (!empty($newPassword)) {
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $this->addFlash('success', "Password for '{$user->getUsername()}' has been reset.");
        }

        $em->flush();
        $this->addFlash('success', "User '{$user->getUsername()}' updated successfully.");

        return $this->redirectToRoute('admin_clients');
    }

    #[Route('/clients/{id}/toggle-status', name: 'admin_client_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $user = $userRepository->find($id);
        if ($user) {
            $currentUsername = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
            if ($user->getUsername() === $currentUsername) {
                $this->addFlash('error', 'You cannot disable your own active account.');
            } else {
                $newStatus = $user->getStatus() === 'disabled' ? 'active' : 'disabled';
                $user->setStatus($newStatus);
                $em->flush();
                $this->addFlash('success', "User '{$user->getUsername()}' status set to " . strtoupper($newStatus) . ".");
            }
        }
        return $this->redirectToRoute('admin_clients');
    }

    #[Route('/clients/{id}/role', name: 'admin_client_role', methods: ['POST'])]
    public function toggleRole(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $user = $userRepository->find($id);
        if ($user) {
            $newRole = $request->request->get('role', 'customer');
            if (in_array($newRole, ['admin', 'staff', 'customer'], true)) {
                $user->setRole($newRole);
                $em->flush();
                $this->addFlash('success', "Role for '{$user->getUsername()}' updated to " . strtoupper($newRole) . ".");
            }
        }
        return $this->redirectToRoute('admin_clients');
    }

    #[Route('/clients/{id}/delete', name: 'admin_client_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_client_delete_' . $id, $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_clients');
        }

        $user = $userRepository->find($id);
        if ($user) {
            $currentUsername = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
            if ($user->getUsername() === $currentUsername) {
                $this->addFlash('error', "You cannot delete your own active administrator account.");
            } else {
                $em->remove($user);
                $em->flush();
                $this->addFlash('success', "Account for '{$user->getUsername()}' has been permanently deleted.");
            }
        }
        return $this->redirectToRoute('admin_clients');
    }
}
